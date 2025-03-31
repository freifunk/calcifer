<?php

namespace App\Controller;

use App\Entity\RepeatingEvent;
use App\Service\RepeatingEventService;
use App\Service\LocationService;
use App\Service\TagService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/termine/wiederholend', priority: 1)]
class RepeatingEventController extends AbstractController
{
    public function __construct(
        private readonly RepeatingEventService $repeatingEventService,
        private readonly TagService $tagService,
        private readonly LocationService $locationService
    ) {}

    #[Route('/', name: 'repeating_event_show', methods: ['GET'])]
    public function indexAction(): Response
    {
        $entities = $this->repeatingEventService->findAll();

        return $this->render('repeating_event/index.html.twig', [
            'entities' => $entities,
        ]);
    }

    #[Route('/logs', name: 'repeating_event_logs', methods: ['GET'])]
    public function logIndexAction(): Response
    {
        $entities = $this->repeatingEventService->findAllLogs();

        return $this->render('repeating_event/logs.html.twig', [
            'entities' => $entities,
        ]);
    }

    #[Route('/neu', name: 'repeating_event_new', methods: ['GET'])]
    public function newAction(): Response
    {
        $entity = new RepeatingEvent();
        $entity->setNextdate(new DateTime());
        $entity->setSummary('');

        return $this->render('repeating_event/new.html.twig', [
            'entity' => $entity,
        ]);
    }

    #[Route('/neu', name: 'repeating_event_create', methods: ['POST'])]
    public function createAction(Request $request): Response
    {
        $entity = new RepeatingEvent();
        $this->fillEntity($request, $entity);
        $errors = $entity->isValid();
        
        if (count($errors) == 0) {
            if ($request->get('origin')) {
                return $this->redirect($this->generateUrl('repeating_event_show'));
            }
            
            $locationName = $request->get('location');
            if (!empty($locationName)) {
                $location = $this->locationService->findOrCreateLocation(
                    $locationName,
                    $request->get('location_lat'),
                    $request->get('location_lon')
                );
                $entity->setLocation($location);
            }
            
            $tagsString = $request->get('tags');
            if (!empty($tagsString)) {
                $tags = $this->tagService->createTagsFromString($tagsString);
                foreach ($tags as $tag) {
                    $entity->addTag($tag);
                }
            }
            
            $this->repeatingEventService->save($entity);
            
            return $this->redirect($this->generateUrl('repeating_event_show'));
        }
        
        return $this->render('repeating_event/new.html.twig', [
            'entity' => $entity,
            'errors' => $errors,
        ]);
    }

    #[Route('/{slug}/bearbeiten', name: 'repeating_event_edit', methods: ['GET'])]
    public function editAction(string $slug): Response
    {
        $entity = $this->repeatingEventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find RepeatingEvent entity.');
        }

        return $this->render('repeating_event/edit.html.twig', [
            'entity' => $entity,
        ]);
    }

    #[Route('/{slug}/bearbeiten', name: 'repeating_event_update', methods: ['POST'])]
    public function updateAction(Request $request, string $slug): Response
    {
        $entity = $this->repeatingEventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find RepeatingEvent entity.');
        }

        $entity->clearTags();
        
        $this->fillEntity($request, $entity);
        $errors = $entity->isValid();
        
        if (count($errors) == 0) {
            if ($request->get('origin')) {
                return $this->redirect($this->generateUrl('repeating_event_show'));
            }
            
            $locationName = $request->get('location');
            if (!empty($locationName)) {
                $location = $this->locationService->findOrCreateLocation(
                    $locationName,
                    $request->get('location_lat'),
                    $request->get('location_lon')
                );
                $entity->setLocation($location);
            } else {
                $entity->setLocation(null);
            }
            
            $tagsString = $request->get('tags');
            if (!empty($tagsString)) {
                $tags = $this->tagService->createTagsFromString($tagsString);
                foreach ($tags as $tag) {
                    $entity->addTag($tag);
                }
            }
            
            $this->repeatingEventService->save($entity);
            
            return $this->redirect($this->generateUrl('repeating_event_show'));
        }
        
        return $this->render('repeating_event/edit.html.twig', [
            'entity' => $entity,
            'errors' => $errors,
        ]);
    }

    private function fillEntity(Request $request, RepeatingEvent $entity): void
    {
        $entity->setDuration($request->get('duration'));
        $entity->setRepeatingPattern($request->get('repeating_pattern'));
        $entity->setSummary($request->get('summary'));
        $entity->setDescription($request->get('description'));
        $entity->setUrl($request->get('url'));
        
        if (strlen($request->get('duration')) == 0) {
            $entity->setDuration(null);
        }
        
        $nextdate = $request->get('nextdate');
        if ($nextdate) {
            $entity->setNextdate(new DateTime($nextdate));
        }
    }

    #[Route('/{slug}/löschen', name: 'repeating_event_delete', methods: ['GET', 'POST'])]
    public function deleteAction(Request $request, string $slug): Response
    {
        $entity = $this->repeatingEventService->findBySlug($slug);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        $confirmation = $request->get('confirmation', false);

        if (($request->getMethod() == 'POST') && $confirmation) {
            $this->repeatingEventService->delete($entity);
            return $this->redirect('/');
        }

        return $this->render('repeating_event/delete.html.twig', [
            'entity' => $entity,
        ]);
    }

    #[Route('/wiederholungsmuster', name: 'repeating_patterns', methods: ['GET', 'POST'])]
    public function repeatingPatternsHelpAction(): Response
    {
        return $this->render('repeating_event/repeating_patterns.html.twig');
    }
}
