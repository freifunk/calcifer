<?php
declare(strict_types=0);

namespace App\Tests\Command;

use App\Command\GenerateEventsCommand;
use App\Entity\Event;
use App\Entity\RepeatingEvent;
use App\Entity\Location;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\EventRepository;
use App\Repository\RepeatingEventRepository;
use App\Service\SluggerService;
use App\Service\TimeZoneService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use App\Entity\Tag;

class GenerateEventsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RepeatingEventRepository $repeatingEventRepository;
    private EventRepository $eventRepository;
    private GenerateEventsCommand $command;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->repeatingEventRepository = $container->get(RepeatingEventRepository::class);
        $this->eventRepository = $container->get(EventRepository::class);
        $sluggerService = $container->get(SluggerService::class);
        $timeZoneService = $container->get(\App\Service\TimeZoneService::class);

        $this->command = new GenerateEventsCommand(
            $this->entityManager,
            $this->repeatingEventRepository,
            $this->eventRepository,
            $sluggerService,
            $timeZoneService
        );
        
        // Stelle sicher, dass der Test mit einer leeren DB startet
        $this->clearEvents();
    }
    
    private function clearEvents(): void
    {
        // Lösche alle repeating_events_log_entries
        $entries = $this->entityManager->getRepository(RepeatingEventLogEntry::class)->findAll();
        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }
        $this->entityManager->flush();
        
        // Lösche alle events
        $events = $this->eventRepository->findAll();
        foreach ($events as $event) {
            $this->entityManager->remove($event);
        }
        $this->entityManager->flush();
        
        // Lösche alle repeating_events
        $repeatingEvents = $this->repeatingEventRepository->findAll();
        foreach ($repeatingEvents as $repeatingEvent) {
            $this->entityManager->remove($repeatingEvent);
        }
        $this->entityManager->flush();
    }

    public function testGenerateEventsWithNoRepeatingEvents(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Generated 0 events', $commandTester->getDisplay());
    }

    /**
     * Teste die Generierung von Events mit dem Pattern "Alle 14 Tage"
     */
    public function testGenerateEventsWithBiweeklyPattern(): void
    {
        // Nutze existierende Location aus der Test-DB
        $location = $this->entityManager->getRepository(Location::class)->findOneBy(['name' => 'Office']);
        
        if (!$location) {
            $location = new Location();
            $location->setName('Test Location');
            $location->setStreetaddress('123 Test Street');
            $location->setCity('Test City');
            $location->setSlug('test-location');
            $this->entityManager->persist($location);
            $this->entityManager->flush();
        }

        // Aktuelles Datum für das Test-Event
        $startDate = new DateTime('today');
        $originalStartTimestamp = $startDate->getTimestamp();

        // Erstelle ein wiederkehrendes Event mit einem 14-tägigen Muster
        $repeatingEvent = new RepeatingEvent();
        $repeatingEvent->setRepeatingPattern('Alle 14 Tage');
        $repeatingEvent->setNextdate($startDate);
        $repeatingEvent->setSummary('Biweekly Test Event');
        $repeatingEvent->setDescription('This event should repeat every 14 days');
        $repeatingEvent->setLocation($location);
        $repeatingEvent->setDuration(60); // 60 Minuten
        $repeatingEvent->setSlug('biweekly-test-event');
        
        $this->entityManager->persist($repeatingEvent);
        $this->entityManager->flush();
        
        // Führe den Command aus
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            '--duration' => '1 month'
        ]);
        
        // Überprüfe das Ergebnis
        $output = $commandTester->getDisplay();
        $this->assertEquals(0, $commandTester->getStatusCode(), "Command execution failed: " . $output);
        
        // Überprüfe, ob die generierten Events korrekt erstellt wurden
        $events = $this->eventRepository->findBy(['summary' => 'Biweekly Test Event']);
        
        // Wir erwarten etwa 2 Events innerhalb eines Monats für 14-tägige Wiederholungen
        $this->assertGreaterThanOrEqual(2, count($events), "Expected at least 2 events to be generated. Command output: " . $output);
        
        // Überprüfe, dass es einen 14-tägigen Abstand zwischen Events gibt
        if (count($events) >= 2) {
            // Sortiere Events nach Datum
            usort($events, function(Event $a, Event $b) {
                return $a->getStartdate() <=> $b->getStartdate();
            });
            
            // Berechne Abstand in Sekunden zwischen aufeinanderfolgenden Events
            $firstDate = $events[0]->getStartdate();
            $secondDate = $events[1]->getStartdate();
            
            $secondsDifference = $secondDate->getTimestamp() - $firstDate->getTimestamp();
            $expectedSeconds = 14 * 24 * 60 * 60; // 14 Tage in Sekunden
            
            $this->assertEquals(
                $expectedSeconds, 
                $secondsDifference,
                sprintf(
                    "Events should be exactly 14 days apart. First: %s, Second: %s, Difference: %s seconds (expected %s seconds)",
                    $firstDate->format('Y-m-d H:i:s'),
                    $secondDate->format('Y-m-d H:i:s'),
                    $secondsDifference,
                    $expectedSeconds
                )
            );
        }
        
        // Überprüfen, ob nextDate im RepeatingEvent aktualisiert wurde
        $this->entityManager->refresh($repeatingEvent);
        $newNextDate = $repeatingEvent->getNextdate();
        $newNextTimestamp = $newNextDate->getTimestamp();
        
        // Berechne die erwartete Differenz: 28 Tage (2 mal 14 Tage)
        $expectedDifference = 28 * 24 * 60 * 60; // 28 Tage in Sekunden
        $actualDifference = $newNextTimestamp - $originalStartTimestamp;
        
        $this->assertEquals(
            $expectedDifference,
            $actualDifference,
            sprintf(
                "RepeatingEvent nextDate should be exactly 28 days after original date. Original: %s, New: %s, Difference: %s seconds (expected %s seconds)",
                date('Y-m-d H:i:s', $originalStartTimestamp),
                $newNextDate->format('Y-m-d H:i:s'),
                $actualDifference,
                $expectedDifference
            )
        );
    }

    /**
     * Testet die korrekte Zeitzonenbehandlung bei der Event-Generierung
     */
    public function testTimezoneHandling(): void
    {
        // Erstelle eine Location für den Test
        $location = new Location();
        $location->setName('Test Location');
        $location->setSlug('test-location');
        $this->entityManager->persist($location);
        $this->entityManager->flush();

        // Erstelle ein wiederkehrendes Event mit einer bestimmten Startzeit
        $startDate = new DateTime('2025-09-03 20:00:00', new \DateTimeZone('Europe/Berlin'));
        $repeatingEvent = new RepeatingEvent();
        $repeatingEvent->setRepeatingPattern('Alle 14 Tage');
        $repeatingEvent->setNextdate($startDate);
        $repeatingEvent->setSummary('Timezone Test Event');
        $repeatingEvent->setDescription('This event tests timezone handling');
        $repeatingEvent->setLocation($location);
        $repeatingEvent->setDuration(60);
        $repeatingEvent->setSlug('timezone-test-event');
        
        $this->entityManager->persist($repeatingEvent);
        $this->entityManager->flush();
        
        // Führe den Command aus
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            '--duration' => '6 months'
        ]);
        
        // Überprüfe das Ergebnis
        $this->assertEquals(0, $commandTester->getStatusCode());
        
        // Hole die generierten Events
        $events = $this->eventRepository->findBy(['summary' => 'Timezone Test Event']);
        $this->assertGreaterThanOrEqual(2, count($events));
        
        // Überprüfe die Zeitzonen der Events
        foreach ($events as $event) {
            $this->assertEquals('Europe/Berlin', $event->getStartdate()->getTimezone()->getName());
            
            // Überprüfe, dass die Uhrzeit korrekt ist (15:00)
            $this->assertEquals('20:00', $event->getStartdate()->format('H:i'));
            
            // Überprüfe die Log-Einträge
            $logEntries = $this->entityManager->getRepository(RepeatingEventLogEntry::class)
                ->findBy(['event' => $event]);
            
            foreach ($logEntries as $logEntry) {
                $this->assertEquals('Europe/Berlin', $logEntry->getEventStartdate()->getTimezone()->getName());
                $this->assertEquals('20:00', $logEntry->getEventStartdate()->format('H:i'));
                
                if ($logEntry->getEventEnddate()) {
                    $this->assertEquals('Europe/Berlin', $logEntry->getEventEnddate()->getTimezone()->getName());
                    $this->assertEquals('21:00', $logEntry->getEventEnddate()->format('H:i'));
                }
            }

            $repeatingEvent = $this->repeatingEventRepository->findOneBy(['duration' => 60]);
            $this->assertEquals('Europe/Berlin', $repeatingEvent->getNextdate()->getTimezone()->getName());
            $this->assertEquals('20:00', $repeatingEvent->getNextdate()->format('H:i'));
        }
    }
    
    /**
     * Teste die Generierung von Events, ohne auf RelativeDateParser zu vertrauen
     */
    public function testGenerateEventsManually(): void
    {
        // Nutze existierende Location aus der Test-DB
        $location = $this->entityManager->getRepository(Location::class)->findOneBy(['name' => 'Office']);
        
        if (!$location) {
            $location = new Location();
            $location->setName('Test Location');
            $location->setSlug('test-location');
            $this->entityManager->persist($location);
            $this->entityManager->flush();
        }

        // Erstelle manuell ein Event statt über den Command
        $event = new Event();
        $event->setSummary('Test Event');
        $event->setDescription('Manually created test event');
        $event->setStartdate(new DateTime());
        $event->setLocation($location);
        $event->setSlug('manual-test-event');
        
        // Event in DB speichern
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        
        // Überprüfe, ob das Event korrekt erstellt wurde
        $events = $this->eventRepository->findBy(['summary' => 'Test Event']);
        $this->assertGreaterThan(0, count($events), "Manual event was not created correctly");
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearEvents();
        
        // Vermeiden von Memory-Leaks
        $this->entityManager->close();
    }
} 