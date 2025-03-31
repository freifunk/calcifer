<?php

namespace App\Tests\Repository;

use App\Entity\Event;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface|null $entityManager;
    private EntityRepository|null $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Event::class);
        
        // Stelle sicher, dass es keine Test-Events gibt
        $this->clearTestEvents();
    }
    
    /**
     * Entfernt alle Test-Events aus der Datenbank
     */
    private function clearTestEvents(): void
    {
        $events = $this->repository->findBy(['summary' => ['Test Event 1', 'Test Event 2']]);
        foreach ($events as $event) {
            $this->entityManager->remove($event);
        }
        $this->entityManager->flush();
    }

    /**
     * Teste das Erstellen, Speichern und Abrufen von Events über das Repository
     */
    public function testCreateAndFindEvents(): void
    {
        // Erstelle Testdaten
        $event1 = new Event();
        $event1->setSummary('Test Event 1');
        $event1->setStartdate(new DateTime('tomorrow 10:00'));
        $event1->setEnddate(new DateTime('tomorrow 12:00'));
        $event1->setDescription('Test Description 1');
        $event1->setSlug('test-event-1');
        
        $event2 = new Event();
        $event2->setSummary('Test Event 2');
        $event2->setStartdate(new DateTime('next week'));
        $event2->setDescription('Test Description 2');
        $event2->setSlug('test-event-2');
        
        // Speichere die Events
        $this->entityManager->persist($event1);
        $this->entityManager->persist($event2);
        $this->entityManager->flush();
        
        // Teste das Abrufen nach ID
        $foundEvent1 = $this->repository->find($event1->getId());
        $this->assertNotNull($foundEvent1);
        $this->assertEquals('Test Event 1', $foundEvent1->getSummary());
        
        // Teste das Abrufen mit Kriterien
        $foundEvents = $this->repository->findBy(['summary' => 'Test Event 2']);
        $this->assertCount(1, $foundEvents);
        $this->assertEquals('Test Event 2', $foundEvents[0]->getSummary());
        
        // Teste das Abrufen aller Events
        $allEvents = $this->repository->findAll();
        $this->assertGreaterThanOrEqual(2, count($allEvents));
        
        // Teste das Abrufen von Events für einen bestimmten Zeitraum
        $tomorrow = new DateTime('tomorrow');
        $tomorrowStart = clone $tomorrow;
        $tomorrowStart->setTime(0, 0, 0);
        $tomorrowEnd = clone $tomorrow;
        $tomorrowEnd->setTime(23, 59, 59);
        
        $eventsForTomorrow = $this->entityManager->createQuery(
            'SELECT e FROM App\Entity\Event e WHERE e.startdate >= :startDate AND e.startdate <= :endDate'
        )
            ->setParameter('startDate', $tomorrowStart)
            ->setParameter('endDate', $tomorrowEnd)
            ->getResult();
        
        $this->assertGreaterThanOrEqual(1, count($eventsForTomorrow));
        $foundTomorrowEvent = false;
        foreach ($eventsForTomorrow as $event) {
            if ($event->getSlug() === 'test-event-1') {
                $foundTomorrowEvent = true;
                break;
            }
        }
        $this->assertTrue($foundTomorrowEvent, 'Das für morgen erstellte Event wurde nicht gefunden');
    }
    
    /**
     * Teste das Filtern von Events nach zukünftigen Daten
     */
    public function testFindFutureEvents(): void
    {
        $now = new DateTime();
        
        // Erstelle ein Event in der Vergangenheit
        $pastEvent = new Event();
        $pastEvent->setSummary('Test Event 1');
        $pastEvent->setStartdate(new DateTime('-1 day'));
        $pastEvent->setSlug('past-test-event');
        
        // Erstelle ein Event in der Zukunft
        $futureEvent = new Event();
        $futureEvent->setSummary('Test Event 2');
        $futureEvent->setStartdate(new DateTime('+1 day'));
        $futureEvent->setSlug('future-test-event');
        
        // Speichere die Events
        $this->entityManager->persist($pastEvent);
        $this->entityManager->persist($futureEvent);
        $this->entityManager->flush();
        
        // Finde zukünftige Events
        $futureEvents = $this->entityManager->createQuery(
            'SELECT e FROM App\Entity\Event e WHERE e.startdate >= :now ORDER BY e.startdate ASC'
        )
            ->setParameter('now', $now)
            ->getResult();
        
        // Stelle sicher, dass wir zukünftige Events finden
        $this->assertGreaterThanOrEqual(1, count($futureEvents));
        
        // Überprüfe, ob unser zukünftiges Test-Event enthalten ist
        $foundFutureEvent = false;
        foreach ($futureEvents as $event) {
            if ($event->getSlug() === 'future-test-event') {
                $foundFutureEvent = true;
                break;
            }
        }
        $this->assertTrue($foundFutureEvent, 'Das zukünftige Event wurde nicht gefunden');
    }
    
    protected function tearDown(): void
    {
        // Lösche Testdaten
        $this->clearTestEvents();
        
        parent::tearDown();
        
        // Datenbank zurücksetzen
        $this->entityManager->close();
        $this->entityManager = null;
        $this->repository = null;
    }
} 