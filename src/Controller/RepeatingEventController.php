<?php

namespace App\Controller;

use App\Entity\RepeatingEvent;
use App\Entity\Location;
use App\Entity\RepeatingEventLogEntry;
use App\Entity\Tag;
use App\Repository\RepeatingEventRepository;
use App\Repository\RepeatingEventLogRepository;
use App\Repository\TagRepository;
use App\Repository\LocationRepository;
use App\Service\SluggerService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/termine/wiederholend', priority: 1)]
class RepeatingEventController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepeatingEventRepository $repeatingEventRepository,
        private readonly TagRepository $tagRepository,
        private readonly LocationRepository $locationRepository,
        private readonly RepeatingEventLogRepository $repeatingEventLogRepository,
        private readonly SluggerService $slugger
    ) {}

    #[Route('/', name: 'repeating_event_show', methods: ['GET'])]
    public function indexAction(): Response
    {
        $entities = $this->repeatingEventRepository->findAll();

        return $this->render('repeating_event/index.html.twig', [
            'entities' => $entities,
        ]);
    }

    #[Route('/logs', name: 'repeating_event_logs', methods: ['GET'])]
    public function logIndexAction(): Response
    {
        $entities = $this->repeatingEventLogRepository->findBy([], ['eventStartdate' => 'DESC']);

        return $this->render('repeating_event/logs.html.twig', [
            'entities' => $entities,
        ]);
    }

    #[Route('/neu', name: 'repeating_event_new', methods: ['GET'])]
    public function newAction(): Response
    {
        $entity = new RepeatingEvent();
        $entity->setNextdate(new \DateTime());
        $entity->setSummary('');
        $entity->setTags(new ArrayCollection());

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
            $ret = $this->saveRepeatingEvent($request, $entity);
            if ($entity->getId() > 0) {
                return $this->redirect($this->generateUrl('repeating_event_show'));
            } else {
                throw new \Exception('Could not save repeating event?!?');
            }
        }
        return $this->render('repeating_event/new.html.twig', [
            'entity' => $entity,
            'errors' => $errors,
        ]);
    }

    #[Route('/{slug}/bearbeiten', name: 'repeating_event_edit', methods: ['GET'])]
    public function editAction(string $slug): Response
    {
        $entity = $this->repeatingEventRepository->findOneBy(['slug' => $slug]);

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
        /** @var RepeatingEvent $entity */
        $entity = $this->repeatingEventRepository->findOneBy(['slug' => $slug]);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find RepeatingEvent entity.');
        }

        $this->fillEntity($request, $entity);
        $errors = $entity->isValid();
        if (count($errors) == 0) {
            $ret = $this->saveRepeatingEvent($request, $entity);
            if ($entity->getId() > 0) {
                return $this->redirect($this->generateUrl('repeating_event_show'));
            } else {
                throw new \Exception('Could not save repeating event?!?');
            }
        }
        return $this->render('repeating_event/edit.html.twig', [
            'entity' => $entity,
            'errors' => $errors,
        ]);
    }

    private function fillEntity(Request $request, RepeatingEvent $entity): void
    {
        // Explicit setters instead of dynamic field mapping
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
            $entity->setNextdate(new \DateTime($nextdate));
        }
    }

    private function saveRepeatingEvent(Request $request, RepeatingEvent $entity): RepeatingEvent|false
    {
        if ($request->get('origin')) {
            return false;
        }
        $location = $request->get('location');
        $location_lat = $request->get('location_lat');
        $location_lon = $request->get('location_lon');

        if (strlen($location) > 0) {
            // check if the location already exists
            $results = $this->locationRepository->findBy(['name' => $location]);
            if (count($results) > 0) {
                $location_obj = $results[0];
                if (strlen($location_lat) > 0) {
                    $location_obj->setLat($location_lat);
                }
                if (strlen($location_lon) > 0) {
                    $location_obj->setLon($location_lon);
                }
                $this->entityManager->persist($location_obj);
                $this->entityManager->flush();
                $entity->setLocation($results[0]);
            } else {
                $location_obj = new Location();
                $location_obj->setName($location);
                if (strlen($location_lat) > 0) {
                    $location_obj->setLat($location_lat);
                }
                if (strlen($location_lon) > 0) {
                    $location_obj->setLon($location_lon);
                }
                $location_obj->setSlug($this->slugger->generateUniqueSlug($location_obj->getName(), $this->locationRepository));
                $this->entityManager->persist($location_obj);
                $this->entityManager->flush();
                $entity->setLocation($location_obj);
            }
        } else {
            $entity->setLocation(null);
        }

        $tags = $request->get('tags');
        if (strlen($tags) > 0) {
            $tags = explode(',', $tags);
            $entity->clearTags();
            foreach ($tags as $tag) {
                $tag = trim($tag);
                $results = $this->tagRepository->findBy(['name' => $tag]);
                if (count($results) > 0) {
                    $entity->addTag($results[0]);
                } else {
                    $tag_obj = new Tag();
                    $tag_obj->setName($tag);
                    $tag_obj->setSlug($this->slugger->generateUniqueSlug($tag_obj->getName(), $this->tagRepository));
                    $this->entityManager->persist($tag_obj);
                    $this->entityManager->flush();
                    $entity->addTag($tag_obj);
                }
            }
        } else {
            $entity->clearTags();
        }

        $entity->setSlug($this->slugger->generateUniqueSlug($entity->getSummary(), $this->repeatingEventRepository));

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    #[Route('/{slug}/löschen', name: 'repeating_event_delete', methods: ['GET', 'POST'])]
    public function deleteAction(Request $request, string $slug): Response
    {
        $entity = $this->repeatingEventRepository->findOneBy(['slug' => $slug]);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Event entity.');
        }

        $confirmation = $request->get('confirmation',false);

        if (($request->getMethod() == 'POST') && ($confirmation)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return $this->redirect('/');
        }

        return $this->render('repeating_event/delete.html.twig', [
            'entity' => $entity,
        ]);
    }

    #[Route('/wiederholungsmuster', name: 'repeating_patterns', methods: ['GET', 'POST'])]
    public function repeatingPatternsHelpAction(Request $request): Response
    {
        return $this->render('repeating_event/repeating_patterns.html.twig');
    }
}
