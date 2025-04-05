<?php

namespace App\Tests\Controller;

use App\Entity\RepeatingEvent;
use App\Entity\Tag;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RepeatingEventControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;
    private ?RepeatingEvent $testEvent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        
        // Testdaten erstellen
        $this->createTestData();
    }
    
    private function createTestData(): void 
    {
        // Überprüfe, ob bereits ein Test-Event existiert
        $event = $this->entityManager->getRepository(RepeatingEvent::class)->findOneBy(['summary' => 'Test Wiederholendes Event']);
        
        if (!$event) {
            // Erstelle Test-Event
            $event = new RepeatingEvent();
            $event->setSummary('Test Wiederholendes Event');
            $event->setSlug('test-wiederholendes-event');
            
            // RepeatingEvent erwartet jetzt ein DateTimeImmutable, also verwenden wir die setNextdate-Methode richtig
            $event->setNextdate(new \DateTime('+1 day'));
            
            $event->setRepeatingPattern('Jede Woche');
            $event->setDuration(60);
            $event->setDescription('Ein Test-Event für automatisierte Tests');
            
            // Tag hinzufügen
            $tag = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => 'test']);
            if (!$tag) {
                $tag = new Tag();
                $tag->setName('test');
                $tag->setSlug('test');
                $this->entityManager->persist($tag);
            }
            $event->addTag($tag);
            
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        }
        
        $this->testEvent = $this->entityManager->getRepository(RepeatingEvent::class)->findOneBy(['summary' => 'Test Wiederholendes Event']);
    }

    /**
     * Test für indexAction
     */
    public function testIndexAction(): void
    {
        $this->client->request('GET', '/termine/wiederholend/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Wiederholende Termine');
    }
    
    /**
     * Test für logIndexAction
     */
    public function testLogIndexAction(): void
    {
        $this->client->request('GET', '/termine/wiederholend/logs');

        $this->assertResponseIsSuccessful();
        // Überprüfe, dass die Logs-Seite angezeigt wird
        $this->assertSelectorExists('table');
    }
    
    /**
     * Test für newAction
     */
    public function testNewAction(): void
    {
        $this->client->request('GET', '/termine/wiederholend/neu');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="summary"]');
        $this->assertSelectorExists('input[name="repeating_pattern"]');
    }
    
    /**
     * Test für createAction mit gültigen Daten
     */
    public function testCreateActionWithValidData(): void
    {
        // Vorbereiten der Testdaten für ein neues Event
        $newSummary = 'Neues Test-Event';
        $newPattern = 'Alle 14 Tage';
        $newDescription = 'Beschreibung für das neue Test-Event';
        $newUrl = 'https://example.com/neues-event';
        $nextDate = new \DateTime('+3 days');
        
        // Direkte POST-Anfrage an die Create-URL
        $this->client->request(
            'POST',
            '/termine/wiederholend/neu',
            [
                'summary' => $newSummary,
                'repeating_pattern' => $newPattern,
                'description' => $newDescription,
                'duration' => 120,
                'nextdate' => $nextDate->format('Y-m-d H:i'),
                'url' => $newUrl,
                'location' => 'Neuer Testort',
                'tags' => 'test',
                'origin' => '',
            ]
        );
        
        $response = $this->client->getResponse();
        
        // Prüfen, ob nach dem Absenden eine Weiterleitung stattfindet
        $this->assertTrue($response->isRedirect(), 'Es erfolgte keine Weiterleitung nach dem Erstellen');
        
        // Folge der Weiterleitung
        $this->client->followRedirect();
        
        // Überprüfe, ob das Event erstellt wurde
        $this->entityManager->clear(); // Cache leeren
        $newEvent = $this->entityManager->getRepository(RepeatingEvent::class)
            ->findOneBy(['summary' => $newSummary]);
        
        $this->assertNotNull($newEvent, 'Das neue Event konnte nicht gefunden werden');
        $this->assertEquals($newSummary, $newEvent->getSummary(), 'Summary ist nicht korrekt');
        $this->assertEquals($newPattern, $newEvent->getRepeatingPattern(), 'Wiederholungsmuster ist nicht korrekt');
        $this->assertEquals($newDescription, $newEvent->getDescription(), 'Beschreibung ist nicht korrekt');
        $this->assertEquals(120, $newEvent->getDuration(), 'Dauer ist nicht korrekt');
        $this->assertEquals($newUrl, $newEvent->getUrl(), 'URL ist nicht korrekt');
        
        // Optional: Aufräumen nach dem Test (wenn das Event nicht für andere Tests benötigt wird)
        $this->entityManager->remove($newEvent);
        $this->entityManager->flush();
    }
    
    /**
     * Test für editAction
     */
    public function testEditAction(): void
    {
        $this->client->request('GET', '/termine/wiederholend/' . $this->testEvent->getSlug() . '/bearbeiten');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="summary"]');
        $this->assertSelectorExists('input[name="repeating_pattern"]');
    }
    
    /**
     * Test für updateAction mit gültigen Daten
     */
    public function testUpdateActionWithValidData(): void
    {
        // Create test event if it doesn't exist
        if (!$this->testEvent) {
            $this->createTestData();
        }
        
        // Den Test-Event-Slug abrufen
        $eventSlug = $this->testEvent->getSlug();
        $eventId = $this->testEvent->getId();
        
        // Update values
        $updatedSummary = 'Aktualisiertes Wiederholendes Event';
        $updatedPattern = 'Alle 14 Tage';
        $updatedDescription = 'Diese Beschreibung wurde aktualisiert';
        $updatedUrl = 'https://example.org';
        $nextDate = new \DateTime('+2 days');
        
        // Einen einfacheren Ansatz wählen: Direkte POST-Anfrage an die Update-URL
        $this->client->request(
            'POST',
            '/termine/wiederholend/' . $eventSlug . '/bearbeiten',
            [
                'summary' => $updatedSummary,
                'repeating_pattern' => $updatedPattern,
                'description' => $updatedDescription,
                'duration' => 90,
                'nextdate' => $nextDate->format('Y-m-d H:i'),
                'url' => $updatedUrl,
                'location' => 'Testort',
                'tags' => 'test',
                'origin' => '',
            ]
        );
        
        // Nachdem wir die Anfrage gesendet haben, prüfen wir im Log, wie die Antwort aussieht
        $response = $this->client->getResponse();
        
        // Prüfen, ob nach dem Absenden eine Weiterleitung stattfindet
        $this->assertTrue($response->isRedirect(), 'Es erfolgte keine Weiterleitung nach dem Update');
        
        // Folge der Weiterleitung
        $this->client->followRedirect();
        
        // Überprüfe, ob das Event aktualisiert wurde
        $this->entityManager->clear(); // Cache leeren
        $updatedEvent = $this->entityManager->getRepository(RepeatingEvent::class)
            ->find($eventId);
        
        $this->assertNotNull($updatedEvent, 'Das Event konnte nicht gefunden werden');
        $this->assertEquals($updatedSummary, $updatedEvent->getSummary(), 'Summary wurde nicht aktualisiert');
        $this->assertEquals($updatedPattern, $updatedEvent->getRepeatingPattern(), 'Wiederholungsmuster wurde nicht aktualisiert');
        $this->assertEquals($updatedDescription, $updatedEvent->getDescription(), 'Beschreibung wurde nicht aktualisiert');
        $this->assertEquals(90, $updatedEvent->getDuration(), 'Dauer wurde nicht aktualisiert');
        $this->assertEquals(
            $nextDate->format('Y-m-d H:i'), 
            $updatedEvent->getNextdate()->format('Y-m-d H:i'), 
            'Nächster Termin wurde nicht aktualisiert'
        );
        $this->assertEquals($updatedUrl, $updatedEvent->getUrl(), 'URL wurde nicht aktualisiert');
    }
    
    /**
     * Test für deleteAction (GET-Anfrage - Bestätigungsseite)
     */
    public function testDeleteActionConfirmation(): void
    {
        // Zuvor aktualisiertes Event verwenden
        $event = $this->entityManager->getRepository(RepeatingEvent::class)
            ->findOneBy(['summary' => 'Aktualisiertes Wiederholendes Event']);
        
        if (!$event) {
            $event = $this->testEvent;
        }
        
        $this->client->request('GET', '/termine/wiederholend/' . $event->getSlug() . '/löschen');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        // Überprüfe, dass die Bestätigungsseite angezeigt wird
        $this->assertSelectorTextContains('body', 'löschen');
    }
    
    /**
     * Test für deleteAction (POST-Anfrage - tatsächliches Löschen)
     */
    public function testDeleteActionWithConfirmation(): void
    {
        // Erstelle ein neues Event zum Löschen
        $eventToDelete = new RepeatingEvent();
        $eventToDelete->setSummary('Event zum Löschen');
        $eventToDelete->setSlug('event-zum-loeschen');
        $eventToDelete->setNextdate(new \DateTime('+1 day'));
        $eventToDelete->setRepeatingPattern('Jede Woche');
        
        $this->entityManager->persist($eventToDelete);
        $this->entityManager->flush();
        
        $eventId = $eventToDelete->getId();
        $eventSlug = $eventToDelete->getSlug();
        
        // Direkt eine POST-Anfrage an die Delete-URL senden
        $this->client->request(
            'POST',
            '/termine/wiederholend/' . $eventSlug . '/löschen',
            ['confirmation' => '1']
        );
        
        // Nach erfolgreichem Löschen sollte eine Weiterleitung erfolgen
        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Es erfolgte keine Weiterleitung nach dem Löschen');
        
        // Überprüfe in der Datenbank, ob das Event gelöscht wurde
        $this->entityManager->clear(); // Cache leeren
        $deletedEvent = $this->entityManager->getRepository(RepeatingEvent::class)
            ->find($eventId);
        
        $this->assertNull($deletedEvent, 'Das Event wurde nicht gelöscht');
    }
    
    /**
     * Test für repeatingPatternsHelpAction
     */
    public function testRepeatingPatternsHelpAction(): void
    {
        $this->client->request('GET', '/termine/wiederholend/wiederholungsmuster');

        $this->assertResponseIsSuccessful();
        // Überprüfe, dass die Hilfeseite angezeigt wird
        $this->assertSelectorExists('body');
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Vermeiden von Memory-Leaks
        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
} 