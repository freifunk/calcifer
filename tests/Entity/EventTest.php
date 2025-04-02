<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\Location;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        // Boot the kernel in test environment
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testGetSetSummary(): void
    {
        $event = new Event();
        $summary = 'Test Event';
        
        $event->setSummary($summary);
        $this->assertEquals($summary, $event->getSummary());
    }
    
    public function testGetSetStartdate(): void
    {
        $event = new Event();
        $date = new DateTime();
        
        $event->setStartdate($date);
        $this->assertEquals($date, $event->getStartdate());
    }
    
    public function testGetSetLocation(): void
    {
        $event = new Event();
        $location = new Location();
        $location->setName('Test Location');
        
        $event->setLocation($location);
        $this->assertSame($location, $event->getLocation());
        
        // Test mit null
        $event->setLocation(null);
        $this->assertNull($event->getLocation());
    }
    
    public function testGetFormattedDate(): void
    {
        $event = new Event();
        $startDate = new DateTime('2023-10-01 15:00');
        $event->setStartdate($startDate);
        
        // Test nur mit Startdatum
        $this->assertStringContainsString('2023-10-01 15:00', $event->getFormatedDate());
        
        // Test mit Start- und Enddatum am selben Tag
        $endDate = new DateTime('2023-10-01 16:30');
        $event->setEnddate($endDate);
        $this->assertStringContainsString('15:00 — 16:30', $event->getFormatedDate());
        
        // Test mit Start- und Enddatum an verschiedenen Tagen
        $endDate = new DateTime('2023-10-02 16:30');
        $event->setEnddate($endDate);
        $this->assertStringContainsString('2023-10-01 15:00 — 2023-10-02 16:30', $event->getFormatedDate());
    }
    
    public function testIsValid(): void
    {
        $event = new Event();
        
        // Ungültiges Event (kein Summary)
        $errors = $event->isValid();
        $this->assertArrayHasKey('summary', $errors);
        
        // Gültiges Event
        $event->setSummary('Test Event');
        $event->setStartdate(new DateTime());
        $errors = $event->isValid();
        $this->assertEmpty($errors);
        
        // Ungültiges Event (Enddatum vor Startdatum)
        $event->setStartdate(new DateTime('2023-10-10'));
        $event->setEnddate(new DateTime('2023-10-09'));
        $errors = $event->isValid();
        $this->assertArrayHasKey('enddate', $errors);
    }
    
    public function testConvertToCalendarEvent(): void
    {
        $event = new Event();
        $event->setSummary('Testtitel');
        $event->setDescription('Testbeschreibung');
        $event->setUrl('https://example.org');
        
        // Datum mit einer bestimmten Zeitzone
        $startDate = new DateTime('2023-10-01 15:00', new \DateTimeZone('UTC'));
        $endDate = new DateTime('2023-10-01 17:30', new \DateTimeZone('UTC'));
        $event->setStartdate($startDate);
        $event->setEnddate($endDate);
        
        // Location hinzufügen
        $location = new Location();
        $location->setName('Testort');
        $location->setLat(50.123);
        $location->setLon(10.456);
        $event->setLocation($location);
        
        // Event konvertieren
        $calendarEvent = $event->convertToCalendarEvent();
        
        // Prüfen der Grundstruktur
        $this->assertEquals('Testtitel', $calendarEvent['SUMMARY']);
        $this->assertEquals('Testbeschreibung', $calendarEvent['DESCRIPTION']);
        $this->assertEquals('https://example.org', $calendarEvent['URL']);
        $this->assertEquals('Testort', $calendarEvent['LOCATION']);
        $this->assertEquals([50.123, 10.456], $calendarEvent['GEO']);
        
        // TZID-Eigenschaften prüfen
        $this->assertInstanceOf(DateTime::class, $calendarEvent['DTSTART']);
        $this->assertEquals('Europe/Berlin', $calendarEvent['DTSTART']->getTimezone()->getName());
        
        $this->assertInstanceOf(DateTime::class, $calendarEvent['DTEND']);
        $this->assertEquals('Europe/Berlin', $calendarEvent['DTEND']->getTimezone()->getName());
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
} 