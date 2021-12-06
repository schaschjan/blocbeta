<?php

namespace App\Controller;

use App\Entity\Ascent;
use App\Form\AscentType;
use App\Repository\AscentRepository;
use App\Service\ContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/ascents")
 */
class AscentController extends AbstractController
{
    use CrudTrait;
    use ResponseTrait;
    use RequestTrait;

    private EntityManagerInterface $entityManager;
    private ContextService $contextService;
    private AscentRepository $ascentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ContextService $contextService,
        AscentRepository $ascentRepository
    )
    {
        $this->entityManager = $entityManager;
        $this->contextService = $contextService;
        $this->ascentRepository = $ascentRepository;
    }

    /**
     * @Route(methods={"POST"}, name="ascents_create")
     */
    public function create(Request $request)
    {
        $ascent = new Ascent();
        $ascent->setUser($this->getUser());
        
        return $this->createEntity($request, $ascent, AscentType::class);
    }

    /**
     * @Route("/{id}", methods={"DELETE"}, name="ascents_delete")
     */
    public function delete(string $id)
    {
        return $this->deleteEntity(Ascent::class, $id);
    }
}
