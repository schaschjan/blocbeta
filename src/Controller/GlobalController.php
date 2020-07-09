<?php

namespace App\Controller;

use App\Command\User\ProcessAccountDeletionsCommand;
use App\Components\Controller\ApiControllerTrait;
use App\Entity\User;
use App\Factory\RedisConnectionFactory;
use App\Form\PasswordResetRequestType;
use App\Form\PasswordResetType;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Serializer\LocationSerializer;
use App\Serializer\UserSerializer;
use App\Service\ContextService;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class GlobalController extends AbstractController
{
    const ACCOUNT_DELETION_TIMEOUT = '+1 day';
    const PASSWORD_RESET_EXPIRY = 60 * 60;

    use ApiControllerTrait;

    private $entityManager;
    private $redis;
    private $userRepository;
    private $passwordEncoder;
    private $mailer;
    private $contextService;
    private $tokenStorage;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordEncoderInterface $passwordEncoder,
        MailerInterface $mailer,
        ContextService $contextService,
        TokenStorageInterface $tokenStorage
    )
    {
        $this->entityManager = $entityManager;
        $this->redis = RedisConnectionFactory::create();
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->mailer = $mailer;
        $this->contextService = $contextService;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route("/me", methods={"GET"})
     */
    public function getMe()
    {
        /**s
         * @var User $user
         */
        $user = $this->getUser();

        return $this->json(UserSerializer::serialize($user));
    }

    /**
     * @Route("/me", methods={"PUT"})
     */
    public function updateMe(Request $request)
    {
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user);

        $form->submit(json_decode($request->getContent(), true), false);

        if (!$form->isValid()) {
            return $this->json($this->getFormErrors($form));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/me", methods={"DELETE"})
     */
    public function deleteMe()
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        $user->setActive(false);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $current = new \DateTime();
        $current->modify(self::ACCOUNT_DELETION_TIMEOUT);

        $this->redis->set(
            ProcessAccountDeletionsCommand::getAccountDeletionCacheKey($user->getId()),
            $current->getTimestamp()
        );

        return $this->json([
            "message" => "Your account was scheduled for deletion and will be removed on {$current->format('c')}",
            "time" => $current->format('c')
        ], Response::HTTP_OK);
    }

    /**
     * @Route("/request-reset", methods={"POST"})
     */
    public function requestReset(Request $request)
    {
        self::rateLimit($request, 'reset', 10);

        $form = $this->createForm(PasswordResetRequestType::class);
        $form->submit(json_decode($request->getContent(), true));

        $username = $form->getData()['username'];

        if ($form->isSubmitted()) {
            if (!$this->userRepository->userExists('username', $username)) {
                $form->get('username')->addError(
                    new FormError("Username '$username' not found")
                );
            }
        }

        if (!$form->isValid()) {
            return $this->badRequest($this->getFormErrors($form));
        }

        /**
         * @var User $user
         */
        $user = $this->userRepository->findUserByUsername($username);

        $clientHostname = $_ENV['CLIENT_HOSTNAME'];
        $storageKey = "pending_password_reset_{$user->getId()}";
        $hash = hash('sha256', $storageKey);

        $this->redis->set($hash, $user->getId(), self::PASSWORD_RESET_EXPIRY);

        $email = (new Email())
            ->from('info@blocbeta.com')
            ->to($user->getEmail())
            ->subject('Password reset')
            ->html("<p>Please use the following <a href='$clientHostname/password-reset/$hash'>link</a> to reset your password.</p>");

        $this->mailer->send($email);

        return $this->noContent();
    }

    /**
     * @Route("/reset/{hash}", methods={"GET"})
     */
    public function checkReset(string $hash)
    {
        if (!$this->redis->exists($hash)) {
            return $this->badRequest(null, 'Hash invalid');
        }

        return $this->noContent();
    }

    /**
     * @Route("/reset/{hash}", methods={"POST"})
     */
    public function reset(Request $request, string $hash)
    {
        if (!$this->redis->exists($hash)) {
            return $this->badRequest([
                'hash' => "Hash invalid"
            ]);
        }

        $userId = $this->redis->get($hash);

        /**
         * @var User $user
         */
        $user = $this->userRepository->find($userId);

        $form = $this->createForm(PasswordResetType::class);
        $form->submit(json_decode($request->getContent(), true));

        if (!$form->isValid()) {
            return $this->badRequest($this->getFormErrors($form));
        }

        $password = $this->passwordEncoder->encodePassword($user, $form->getData()['password']);
        $user->setPassword($password);
        $this->redis->del($hash);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @Route("/register", methods={"POST"})
     */
    public function register(Request $request)
    {
        self::rateLimit($request, 'register');

        $user = new User();

        $form = $this->createForm(UserType::class, $user);

        $form->add(...UserType::usernameField());
        $form->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank()
            ]
        ]);
        $form->add(...UserType::passWordField());

        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted()) {
            // check bot traps and return fake id response if filled
            if (isset($form->getExtraData()['firstName']) || isset($form->getExtraData()['lastName'])) {
                return $this->created('everything is fine');
            }

            if ($this->userRepository->userExists('email', $form->getData()->getEmail())) {
                $form->get('email')->addError(
                    new FormError('This email is already taken')
                );
            }

            if ($this->userRepository->userExists('username', $form->getData()->getUsername())) {
                $form->get('username')->addError(
                    new FormError('This username is already taken')
                );
            }
        }

        if (!$form->isValid()) {
            return $this->badRequest($this->getFormErrors($form));
        }

        $password = $this->passwordEncoder->encodePassword($user, $user->getPlainPassword());
        $user->setPassword($password);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->created($user->getId());
    }

    /**
     * @Route("/location", methods={"GET"})
     */
    public function location()
    {
        $fields = [
            'id',
            'name',
            'url',
            'public',
            'city',
            'zip',
            'address_line_one',
            'address_line_two',
            'country_code',
            'image',
            'website',
            'facebook',
            'instagram',
            'twitter',
        ];

        $fields = implode(', ', $fields);

        $connection = $this->entityManager->getConnection();
        $statement = "select {$fields} from tenant where public = true";
        $query = $connection->prepare($statement);

        $query->execute();
        $results = $query->fetchAll();

        return $this->json(
            array_map(function ($result) {
                return LocationSerializer::serializeArray($result);
            }, $results)
        );
    }

    /**
     * @Route("/{location}/ping", methods={"GET"})
     */
    public function ping()
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        $location = $this->contextService->getLocation();

        if ($user->getLastVisitedLocation() !== $location->getId()) {
            $user->setLastVisitedLocation($location->getId());

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $hash = hash('sha256', $this->tokenStorage->getToken()->getCredentials());
        $this->redis->select(RedisConnectionFactory::DB_TRACKING);
        $this->redis->incr("session={$hash}:user={$user->getId()}:location={$location->getId()}");

        return $this->noContent();
    }
}