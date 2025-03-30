<?php

namespace App\Command;

use App\Entity\Event;
use App\Entity\RepeatingEvent;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\EventRepository;
use App\Repository\RepeatingEventRepository;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly SluggerService $sluggerService
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
            $duration = \DateInterval::createFromDateString($durationString);
            if (!$duration) {
                throw new \Exception('Invalid duration format');
            }
        } catch (\Exception $e) {
            $io->error('Invalid duration: ' . $durationString);
            return Command::FAILURE;
        }

        $now = new \DateTime();
        $end = (new \DateTime())->add($duration);
        
        $io->section('Generating events from repeating events');
        $io->progressStart($this->repeatingEventRepository->count([]));

        $repeatingEvents = $this->repeatingEventRepository->findBy([], ['id' => 'ASC']);
        $eventCount = 0;

        foreach ($repeatingEvents as $repeatingEvent) {
            /** @var RepeatingEvent $repeatingEvent */
            $dateObj = $repeatingEvent->getNextdate() ?? new \DateTime();
            
            // Sicherstellen, dass wir ein DateTime-Objekt haben
            if ($dateObj instanceof \DateTimeImmutable) {
                $nextDate = \DateTime::createFromImmutable($dateObj);
            } elseif ($dateObj instanceof \DateTime) {
                $nextDate = $dateObj;
            }
            
            $nextDate->setTimezone(new \DateTimeZone('Europe/Berlin'));
            
            $parser = new RelativeDateParser(
                $repeatingEvent->getRepeatingPattern(),
                $nextDate,
                'de'
            );
            
            $lastEvent = null;
            
            while (($nextDate = $parser->getNext()) < $end) {
                /** @var \DateTime $nextDate */
                $event = new Event();
                $event->setLocation($repeatingEvent->getLocation());
                $event->setStartdate($nextDate);
                
                if ($repeatingEvent->getDuration() > 0) {
                    $durationInterval = new \DateInterval('PT' . $repeatingEvent->getDuration() . 'M');
                    $endDate = clone $nextDate;
                    $endDate->add($durationInterval);
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
                $logEntry->setEventStartdate($event->getStartdate());
                $logEntry->setEventEnddate($event->getEnddate());
                
                $this->entityManager->persist($logEntry);
                $this->entityManager->flush();
                
                $parser->setNow($nextDate);
                $lastEvent = $event;
                $eventCount++;
            }
            
            // Update next date in repeating event
            if ($lastEvent) {
                $repeatingEvent->setNextdate($lastEvent->getStartdate());
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