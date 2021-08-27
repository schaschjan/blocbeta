<?php

namespace App\Controller;

use App\Entity\Area;
use App\Form\AreaType;
use App\Repository\AreaRepository;
use App\Service\ContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/area")
 */
class AreaController extends AbstractController
{
    use CrudTrait;
    use ContextualizedControllerTrait;

    private AreaRepository $areaRepository;
    private ContextService $contextService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AreaRepository $areaRepository,
        ContextService $contextService,
        EntityManagerInterface $entityManager
    )
    {
        $this->areaRepository = $areaRepository;
        $this->contextService = $contextService;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route(methods={"GET"})
     */
    public function index(Request $request)
    {
        $filters = $request->get("filter");

        if ($filters) {
            return $this->okResponse($this->areaRepository->queryWhere(
                $this->getLocationId(),
                ["active" => "bool"],
                $filters
            ));
        }

        return $this->okResponse($this->areaRepository->getActive(
            $this->getLocationId()
        ));
    }

    /**
     * @Route("/{id}", methods={"GET"})
     */
    public function read(int $id)
    {
        $this->denyUnlessLocationAdmin();

        return $this->readEntity(Area::class, $id, ["default", "detail"]);
    }

    /**
     * @Route(methods={"POST"})
     */
    public function create(Request $request)
    {
        $this->denyUnlessLocationAdmin();

        return $this->createEntity($request, Area::class, AreaType::class);
    }

    /**
     * @Route("/{id}", methods={"PUT"})
     */
    public function update(Request $request, string $id)
    {
        $this->denyUnlessLocationAdmin();

        return $this->updateEntity($request, Area::class, AreaType::class, $id);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(string $id)
    {
        $this->denyUnlessLocationAdmin();

        return $this->deleteEntity(Area::class, $id, true);
    }
}