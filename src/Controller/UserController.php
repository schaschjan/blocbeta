<?php

namespace App\Controller;

use App\Components\Controller\ContextualizedControllerTrait;
use App\Entity\User;
use App\Factory\RedisConnectionFactory;
use App\Factory\ResponseFactory;
use App\Service\ContextService;
use Swift_Mailer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    use ContextualizedControllerTrait;

    private $entityManager;
    private $contextService;
    private $mailer;

    public function __construct(
        EntityManagerInterface $entityManager,
        ContextService $contextService,
        Swift_Mailer $mailer
    )
    {
        $this->entityManager = $entityManager;
        $this->contextService = $contextService;
        $this->mailer = $mailer;
    }

    /**
     * @Route(path="", methods={"GET"})
     */
    public function findUser(Request $request)
    {
        $this->denyUnlessLocationAdmin();

        $term = $request->query->get('username');

        if (!$term) {
            return $this->json(ResponseFactory::createError("No username provided", Response::HTTP_BAD_REQUEST), Response::HTTP_BAD_REQUEST);
        }

        $builder = $this->entityManager->createQueryBuilder();

        $users = $builder
            ->from(User::class, 'user')
            ->distinct()
            ->select('user.id, user.username, user.visible, user.roles')
            ->where('user.visible = true')
            ->andWhere($builder->expr()->like('lower(user.username)', ':term'))
            ->setParameter('term', '%' . addcslashes(strtolower($term), '%') . '%')
            ->orderBy('user.username')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();

        return $this->json($users);
    }

    /**
     * @Route("/invite", methods={"POST", "OPTIONS"})
     */
    public function sendRoleInvite(Request $request)
    {
        $this->denyUnlessLocationAdmin();

        $redis = RedisConnectionFactory::create();
    }

    /**
     * @Route("/invite/accept", methods={"POST", "OPTIONS"})
     */
    public function acceptRoleInvite()
    {
        $this->denyUnlessLocationAdmin();
    }
}