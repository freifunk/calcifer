<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Tag;
use App\Repository\EventRepository;
use App\Repository\TagRepository;
use App\Repository\RepeatingEventLogRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Event entities
 */
class EventService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly TagRepository $tagRepository,
        private readonly SluggerService $sluggerService,
        private readonly RepeatingEventLogRepository $repeatingEventLogRepository
    ) {}

    /**
     * Finds all upcoming events from today
     * 
     * @return array<int, Event> List of upcoming events sorted by start date
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
     * 
     * @param string $slug The slug to search for
     * @return Event|null The event if found, null otherwise
     */
    public function findBySlug(string $slug): ?Event
    {
        return $this->eventRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * Saves an event entity
     * 
     * @param Event $event The event to save
     * @param bool $flush Whether to flush changes immediately
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
     * 
     * @param Event $event The event to delete
     * @param bool $flush Whether to flush changes immediately
     */
    public function delete(Event $event, bool $flush = true): void
    {
        // Zuerst Logeinträge löschen, die auf dieses Event verweisen
        $logEntries = $this->repeatingEventLogRepository->findBy(['event' => $event]);
        foreach ($logEntries as $logEntry) {
            $this->entityManager->remove($logEntry);
        }
        
        // Optional hier ein Zwischenflushen einfügen, wenn viele Logeinträge vorhanden sind
        if (count($logEntries) > 0 && $flush) {
            $this->entityManager->flush();
        }
        
        // Dann das Event selbst löschen
        $this->entityManager->remove($event);
        
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Process tags for an event by creating missing tags and associating 
     * them with the event
     * 
     * @param Event $event The event to process tags for
     * @param string $tagString Comma-separated list of tags
     */
    public function processTags(Event $event, string $tagString): void
    {
        if (strlen($tagString) > 0) {
            $tags = explode(',', strtolower($tagString));
            $event->clearTags();
            foreach ($tags as $tag) {
                $tag = trim($tag);
                /** @var array<int, Tag> $results */
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
     * 
     * @param string $title The title to generate a slug from
     * @return string The generated unique slug
     */
    public function generateSlug(string $title): string
    {
        return $this->sluggerService->generateUniqueSlug($title, $this->eventRepository);
    }
} 