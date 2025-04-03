<?php

namespace App\Command;

use App\Entity\Event;
use App\Entity\RepeatingEvent;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\EventRepository;
use App\Repository\RepeatingEventRepository;
use App\Service\SluggerService;
use App\Service\TimeZoneService;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use enko\RelativeDateParser\RelativeDateParser;

#[AsCommand(
    name: 'app:events:generate',
    description: 'Generate events from repeating events',
)]
class GenerateEventsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepeatingEventRepository $repeatingEventRepository,
        private readonly EventRepository $eventRepository, 
        private readonly SluggerService $sluggerService,
        private readonly TimeZoneService $timeZoneService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'duration',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The duration you want to generate events into the future',
                '2 months'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $durationString = $input->getOption('duration');

        try {
            $duration = DateInterval::createFromDateString($durationString);
            if (!$duration) {
                throw new Exception('Invalid duration format');
            }
        } catch (Exception $e) {
            $io->error('Invalid duration: ' . $durationString);
            return Command::FAILURE;
        }
        
        // Verwende die konfigurierte Zeitzone für alle Berechnungen
        $defaultTz = $this->timeZoneService->getDefaultTimeZone();
        $now = new DateTime('now', $defaultTz);
        $end = (new DateTime('now', $defaultTz))->add($duration);
        
        $io->section('Generating events from repeating events');
        $io->progressStart($this->repeatingEventRepository->count([]));

        $repeatingEvents = $this->repeatingEventRepository->findBy([], ['id' => 'ASC']);
        $eventCount = 0;

        foreach ($repeatingEvents as $repeatingEvent) {
            /** @var RepeatingEvent $repeatingEvent */
            $nextDate = null;
            $dateObj = $repeatingEvent->getNextdate() ?? new DateTime('now', $defaultTz);
            
            // Sicherstellen, dass wir ein DateTime-Objekt mit der richtigen Zeitzone haben
            if ($dateObj instanceof DateTimeImmutable) {
                // DateTimeImmutable zu DateTime konvertieren
                $nextDate = DateTime::createFromImmutable($dateObj);
            } elseif ($dateObj instanceof DateTime) {
                // Clone um eine neue Instanz zu erhalten
                $nextDate = clone $dateObj;
            }
            
            // Stelle sicher, dass die Zeitzone korrekt gesetzt ist
            $nextDate->setTimezone($defaultTz);
            
            $parser = new RelativeDateParser(
                $repeatingEvent->getRepeatingPattern(),
                $nextDate,
                'de'
            );
            
            $lastEvent = null;
            
            while (($nextDate = $parser->getNext()) < $end) {
                /** @var DateTime $nextDate */
                
                // Stelle sicher, dass die Zeitzone für nextDate korrekt ist
                $nextDate->setTimezone($defaultTz);
                
                $event = new Event();
                $event->setLocation($repeatingEvent->getLocation());
                
                // Explizites Klonen mit korrekter Zeitzone
                $eventStartDate = clone $nextDate;
                $eventStartDate->setTimezone($defaultTz);
                $event->setStartdate($eventStartDate);
                
                if ($repeatingEvent->getDuration() > 0) {
                    $durationInterval = new DateInterval('PT' . $repeatingEvent->getDuration() . 'M');
                    $endDate = clone $nextDate;
                    $endDate->add($durationInterval);
                    // Stelle sicher, dass auch das Enddatum die richtige Zeitzone hat
                    $endDate->setTimezone($defaultTz);
                    $event->setEnddate($endDate);
                }
                
                $event->setSummary($repeatingEvent->getSummary());
                $event->setDescription($repeatingEvent->getDescription());
                $event->setUrl($repeatingEvent->getUrl());
                
                // Persist to get ID before generating slug
                $this->entityManager->persist($event);
                $this->entityManager->flush();
                
                // Generate and set slug using the injected repository
                $event->setSlug(
                    $this->sluggerService->generateUniqueSlug($event->getSummary(), $this->eventRepository)
                );
                
                // Add tags
                foreach ($repeatingEvent->getTags() as $tag) {
                    $event->addTag($tag);
                }
                
                // Create log entry
                $logEntry = new RepeatingEventLogEntry();
                $logEntry->setEvent($event);
                $logEntry->setRepeatingEvent($repeatingEvent);
                
                // Setze die Startzeit mit korrekter Zeitzone
                $logEntryStartDate = clone $event->getStartdate();
                $logEntryStartDate->setTimezone($defaultTz);
                $logEntry->setEventStartdate($logEntryStartDate);
                
                // Setze die Endzeit mit korrekter Zeitzone, falls vorhanden
                if ($event->getEnddate()) {
                    $logEntryEndDate = clone $event->getEnddate();
                    $logEntryEndDate->setTimezone($defaultTz);
                    $logEntry->setEventEnddate($logEntryEndDate);
                }
                
                $this->entityManager->persist($logEntry);
                $this->entityManager->flush();
                
                // Stelle sicher, dass die Zeitzone für den Parser korrekt ist
                $nextDate->setTimezone($defaultTz);
                $parser->setNow($nextDate);
                
                $lastEvent = $event;
                $eventCount++;
            }
            
            // Update next date in repeating event with correct timezone
            if ($lastEvent) {
                $newNextDate = clone $lastEvent->getStartdate();
                $newNextDate->setTimezone($defaultTz);
                $repeatingEvent->setNextdate($newNextDate);
                $this->entityManager->persist($repeatingEvent);
                $this->entityManager->flush();
            }
            
            $io->progressAdvance();
        }
        
        $io->progressFinish();
        $io->success(sprintf('Generated %d events up to %s', $eventCount, $end->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
} 