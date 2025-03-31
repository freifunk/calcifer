<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\Tag;
use App\Service\EventService;
use App\Service\LocationService;
use App\Service\SluggerService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sabre\VObject;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Collection;

#[Route("/")]
class EventController extends AbstractController
{
    public function __construct(
        private readonly SluggerService         $sluggerService,
        private readonly LocationService        $locationService,
        private readonly EventService           $eventService
    )
    {
    }

    #[Route('/all.ics', name: 'events_ics', methods: ['GET'])]
    public function allEventsAsICSAction(): Response
    {
        $entities = $this->eventService->findUpcomingEvents();

        $vcalendar = new VObject\Component\VCalendar();

        foreach ($entities as $entity) {
            $vcalendar->add('VEVENT', $entity->ConvertToCalendarEvent());
        }

        $response = new Response($vcalendar->serialize());
        $response->headers->set('Content-Type', 'text/calendar');

        return $response;
    }


    #[Route('/', name: "_index", methods: ['GET'])]
    public function indexAction()
    {
        $entities = $this->eventService->findUpcomingEvents();

        return $this->render(
            "event/index.html.twig",
            array(
                'entities' => $entities,
            )
        );
    }

    #[Route('/termine/', name: '_create', methods: ['POST'])]
    public function createAction(Request $request): Response
    {
        $entity = new Event();

        if (!$request->get('origin')) {
            $this->saveEvent($request, $entity);
            $errors = $entity->getValidationResult();
            if (count($errors) == 0) {
                $this->eventService->save($entity);
                return $this->redirect($this->generateUrl('_show', array('slug' => $entity->getSlug())));
            }
        } else {
            return $this->redirect($this->generateUrl(''));
        }

        return $this->render('event/edit.html.twig',
            array(
                'entity' => $entity,
                'errors' => $errors,
            ));
    }

    #[Route('/termine/neu', name: '_new', methods: ['GET'])]
    public function newAction(Request $request): Response
    {
        $entity = new Event();
        $entity->setTags(new ArrayCollection());

        $entity->setDescription(htmlspecialchars($request->get('description'), ENT_QUOTES, 'UTF-8'));
        $entity->setSummary(filter_var($request->get('summary'), FILTER_SANITIZE_SPECIAL_CHARS));
        $entity->setUrl(filter_var($request->get('url'), FILTER_SANITIZE_URL));
        if (strlen($request->get('tags')) > 0) {
            $tags = explode(",", $request->get('tags'));
            foreach ($tags as $tag) {
                $_tag = new Tag();
                $_tag->setName($tag);
                $entity->addTag($_tag);
            }
        }

        if (strlen($request->get('location')) > 0) {
            $location = new Location();
            $location->setName(filter_var($request->get('location'), FILTER_SANITIZE_SPECIAL_CHARS));
            $entity->setLocation($location);
        }

        $entity->setStartdate(new DateTime());
        $entity->setTags(new ArrayCollection());

        return $this->render('event/edit.html.twig',
            array(
                'entity' => $entity,
            )
        );
    }

    #[Route('/termine/{slug}', name: '_show', methods: ['GET'])]
    public function showAction(string $slug): Response
    {
        $entity = $this->eventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        return $this->render('event/show.html.twig',
            array(
                'entity' => $entity
            )
        );
    }

    #[Route('/termine/{slug}/edit', name: '_edit', methods: ['GET'])]
    public function editAction(string $slug): Response
    {
        $entity = $this->eventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        return $this->render('event/edit.html.twig',
            array(
                'entity' => $entity,
            )
        );
    }

    #[Route("/termine/{slug}", name: "_update", methods: ["POST"])]
    public function updateAction(Request $request, string $slug): Response
    {
        $entity = $this->eventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        $this->saveEvent($request, $entity);
        
        $errors = $entity->getValidationResult();
        
        if (count($errors) == 0 && (!$request->get('origin'))) {
            $this->eventService->save($entity);

            return $this->redirect($this->generateUrl('_show', array('slug' => $entity->getSlug())));
        } else {
            return $this->render('event/edit.html.twig', [
                'entity' => $entity,
                'errors' => $errors,
            ]);
        }
    }

    public function saveEvent(Request $request, Event $entity): void
    {
        $entity->setDescription(htmlspecialchars($request->get('description'), ENT_QUOTES, 'UTF-8'));
        $entity->setSummary(filter_var($request->get('summary'), FILTER_SANITIZE_SPECIAL_CHARS));
        $entity->setUrl(filter_var($request->get('url'), FILTER_SANITIZE_URL));
        $startdate = $request->get('startdate');
        if (strlen($startdate) > 0) {
            $startdate = new DateTime($startdate);
            $entity->setStartdate($startdate);
        }
        $entity->setSlug($this->eventService->generateSlug($entity->getSummary()));

        $enddate = $request->get('enddate');
        if (strlen($enddate) > 0) {
            $enddate = new DateTime($enddate);
            $entity->setEnddate($enddate);
        } else {
            $entity->setEnddate(null);
        }

        $location = filter_var($request->get('location'), FILTER_SANITIZE_SPECIAL_CHARS);
        $location_lat = filter_var($request->get('location_lat'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $location_lon = filter_var($request->get('location_lon'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if (strlen($location) > 0) {
            // LocationService verwenden, um den Ort zu finden oder zu erstellen
            $location_obj = $this->locationService->findOrCreateLocation($location, $location_lat, $location_lon);
            $entity->setLocation($location_obj);
        }

        $tags = filter_var($request->get('tags'), FILTER_SANITIZE_SPECIAL_CHARS);
        if (strlen($tags) > 0) {
            $this->eventService->processTags($entity, $tags);
        }
    }

    #[Route("/termine/{slug}/löschen", name: "_delete", methods: ["GET", "POST"])]
    public function deleteAction(Request $request, $slug)
    {
        $entity = $this->eventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        $confirmation = $request->get('confirmation', false);

        if (($request->getMethod() == 'POST') && ($confirmation)) {
            $this->eventService->delete($entity);

            return $this->redirect('/');
        }

        return $this->render('event/delete.html.twig',
            array(
                'entity' => $entity,

            )
        );
    }

    #[Route("/termine/{slug}/kopieren", name: "_copy", methods: ["GET"])]
    public function copyAction(Request $request, string $slug): Response
    {
        $originalEvent = $this->eventService->findBySlug($slug);

        if (!$originalEvent) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        // Create a new event with the same properties
        $entity = new Event();
        $entity->setSummary($originalEvent->getSummary());
        $entity->setDescription($originalEvent->getDescription());
        $entity->setStartdate(clone $originalEvent->getStartdate());
        if ($originalEvent->getEnddate()) {
            $entity->setEnddate(clone $originalEvent->getEnddate());
        }
        $entity->setUrl($originalEvent->getUrl());
        $entity->setLocation($originalEvent->getLocation());
        
        // Copy tags
        $entity->setTags(new ArrayCollection());
        foreach ($originalEvent->getTags() as $tag) {
            $entity->addTag($tag);
        }

        return $this->render('event/edit.html.twig',
            array(
                'entity' => $entity,
            )
        );
    }
}
