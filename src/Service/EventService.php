<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Tag;
use App\Repository\EventRepository;
use App\Repository\TagRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class EventService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly TagRepository $tagRepository,
        private readonly SluggerService $sluggerService
    ) {}

    /**
     * Finds all upcoming events from today
     * 
     * @return Event[]
     */
    public function findUpcomingEvents(): array
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select(['e'])
            ->from(Event::class, 'e')
            ->where('e.startdate >= :startdate')
            ->orderBy('e.startdate')
            ->setParameter('startdate', $now);
        return $qb->getQuery()->execute();
    }

    /**
     * Finds an event by its slug
     */
    public function findBySlug(string $slug): ?Event
    {
        return $this->eventRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * Saves an event entity
     */
    public function save(Event $event, bool $flush = true): void
    {
        $this->entityManager->persist($event);
        
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Deletes an event entity
     */
    public function delete(Event $event, bool $flush = true): void
    {
        $this->entityManager->remove($event);
        
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Process tags for an event
     */
    public function processTags(Event $event, string $tagString): void
    {
        if (strlen($tagString) > 0) {
            $tags = explode(',', strtolower($tagString));
            $event->clearTags();
            foreach ($tags as $tag) {
                $tag = trim($tag);
                $results = $this->tagRepository->findBy(['name' => $tag]);
                if (count($results) > 0) {
                    $event->addTag($results[0]);
                } else {
                    $tag_obj = new Tag();
                    $tag_obj->setName($tag);
                    $tag_obj->setSlug($this->sluggerService->generateUniqueSlug($tag_obj->getName(), $this->tagRepository));
                    $this->entityManager->persist($tag_obj);
                    $this->entityManager->flush();
                    $event->addTag($tag_obj);
                }
            }
        }
    }

    /**
     * Generate a unique slug for an event
     */
    public function generateSlug(string $title): string
    {
        return $this->sluggerService->generateUniqueSlug($title, $this->eventRepository);
    }
} 