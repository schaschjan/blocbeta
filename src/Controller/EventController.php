<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\ContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/events")
 */
class EventController extends AbstractController
{
    use CrudTrait;
    use ContextualizedControllerTrait;
    use FilterTrait;

    private EventRepository $eventRepository;
    private ContextService $contextService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EventRepository $eventRepository,
        ContextService $contextService,
        EntityManagerInterface $entityManager
    )
    {
        $this->eventRepository = $eventRepository;
        $this->contextService = $contextService;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route(methods={"GET"}, name="events_index")
     */
    public function index(Request $request)
    {
        $filter = $request->get("filter");
        $locationId = $this->contextService->getLocation()->getId();

        if ($filter === "all") {
            $this->denyUnlessLocationAdmin();

            return $this->okResponse($this->eventRepository->getAll($locationId));
        }

        if ($filter === "upcoming") {
            return $this->okResponse($this->eventRepository->getUpcoming($locationId));
        }

        if ($filter === "active") {
            return $this->okResponse($this->eventRepository->getActive($locationId));
        }

        return $this->okResponse($this->eventRepository->getParticipating($locationId, $this->getUser()->getId()));
    }

    /**
     * @Route("/{id}/registration", methods={"POST"}, name="events_registration")
     */
    public function registration(int $id)
    {
        /**
         * @var Event $event
         */
        $event = $this->eventRepository->find($id);
        $date = new \DateTime("now", new \DateTimeZone("Europe/Berlin"));

        if (!$event || !$event->getVisible()) {
            return $this->resourceNotFoundResponse(Event::RESOURCE_NAME, $id);
        }

        if( !$event->isPublic() ){
            return $this->badRequestResponse("Event does not allow public registrations");
        }

        if ($event->isParticipant($this->getUser())) {
            return $this->badRequestResponse("You are already registered to this event");
        }

        if ($event->hasEnded()) {
            return $this->badRequestResponse("This event ended");
        }

        $event->getParticipants()->add($this->getUser());

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->noContentResponse();
    }

    /**
     * @Route("/{id}/registration", methods={"DELETE"}, name="events_registration_delete")
     */
    public function deleteRegistration(int $id)
    {
        /**
         * @var Event $event
         */
        $event = $this->eventRepository->find($id);
        $date = new \DateTime("now", new \DateTimeZone("Europe/Berlin"));

        if (!$event || !$event->isPublic() || !$event->getVisible()) {
            return $this->resourceNotFoundResponse(Event::RESOURCE_NAME, $id);
        }

        if (!$event->isParticipant($this->getUser())) {
            return $this->badRequestResponse("You are not registered to this event");
        }

        if ($date > $event->getEndDate()) {
            return $this->badRequestResponse("This event ended");
        }

        $event->getParticipants()->removeElement($this->getUser());

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->noContentResponse();
    }

    /**
     * @Route("/{id}", methods={"GET"}, name="events_read")
     */
    public function read(int $id)
    {
        return $this->readEntity(Event::class, $id, ["detail"]);
    }

    /**
     * @Route(methods={"POST"}, name="events_create")
     */
    public function create(Request $request)
    {
        $this->denyUnlessLocationAdmin();

        return $this->createEntity($request, Event::class, EventType::class);
    }

    /**
     * @Route("/{id}", methods={"PUT"}, name="events_update")
     */
    public function update(Request $request, int $id)
    {
        $this->denyUnlessLocationAdmin();

        return $this->updateEntity($request, Event::class, EventType::class, $id);
    }

    /**
     * @Route("/{id}", methods={"DELETE"}, name="events_delete")
     */
    public function delete(int $id)
    {
        $this->denyUnlessLocationAdmin();

        return $this->deleteEntity(Event::class, $id, true);
    }
}