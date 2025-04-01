<?php

namespace App\Service;

use App\Entity\RepeatingEvent;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\RepeatingEventLogRepository;
use App\Repository\RepeatingEventRepository;
use Doctrine\ORM\EntityManagerInterface;

class RepeatingEventService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepeatingEventRepository $repository,
        private readonly RepeatingEventLogRepository $logRepository,
        private readonly SluggerService $sluggerService
    ) {}

    /**
     * Gibt alle wiederholenden Events zurück
     * 
     * @return RepeatingEvent[]
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Sucht ein Event anhand des Slugs
     */
    public function findBySlug(string $slug): ?RepeatingEvent
    {
        return $this->repository->findOneBy(['slug' => $slug]);
    }

    /**
     * Findet ein Event anhand der ID
     */
    public function findById(int $id): ?RepeatingEvent
    {
        return $this->repository->find($id);
    }

    /**
     * Speichert ein Event und führt Validierungen durch
     */
    public function save(RepeatingEvent $event, bool $flush = true): void
    {
        // Wenn kein Slug gesetzt ist, generieren wir einen aus dem Summary
        if (empty($event->getSlug()) && !empty($event->getSummary())) {
            $event->setSlug($this->sluggerService->generateUniqueSlug(strtolower($event->getSummary()), $this->repository));
        }

        // Persistieren und Flushen wenn gewünscht
        $this->entityManager->persist($event);
        
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Löscht ein Event
     */
    public function delete(RepeatingEvent $event, bool $flush = true): void
    {
        $this->entityManager->remove($event);
        
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Findet alle Log-Einträge, sortiert nach eventStartdate absteigend
     * 
     * @return RepeatingEventLogEntry[]
     */
    public function findAllLogs(): array
    {
        return $this->logRepository->findBy([], ['eventStartdate' => 'DESC']);
    }

    /**
     * Findet Log-Einträge für ein bestimmtes Event
     * 
     * @param RepeatingEvent $event Das Event, für das Logs gesucht werden
     * @return RepeatingEventLogEntry[]
     */
    public function findLogsByEvent(RepeatingEvent $event): array
    {
        return $this->logRepository->findBy(['event' => $event], ['eventStartdate' => 'DESC']);
    }
} 