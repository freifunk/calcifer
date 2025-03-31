<?php
declare(strict_types=0);

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TagControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;
    private array $testTags = [];

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
        // Überprüfe, ob bereits Test-Tags existieren
        $existingTag = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => 'TestTag1']);
        
        if (!$existingTag) {
            // Erstelle Test-Tags
            $tags = [
                ['name' => 'TestTag1', 'slug' => 'testtag1'],
                ['name' => 'TestTag2', 'slug' => 'testtag2'],
                ['name' => 'AnotherTag', 'slug' => 'anothertag']
            ];
            
            foreach ($tags as $tagData) {
                $tag = new Tag();
                $tag->setName($tagData['name']);
                $tag->setSlug($tagData['slug']);
                $this->entityManager->persist($tag);
                $this->testTags[] = $tag;
            }
            
            // Erstelle ein Event mit Tags für den Tag-Show-Test
            $event = new Event();
            $event->setSummary('TagTest Event');
            $event->setStartdate(new \DateTime('+1 day'));
            $event->setSlug('tagtest-event');
            
            // Tags hinzufügen
            foreach ($this->testTags as $tag) {
                $event->addTag($tag);
            }
            
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        } else {
            // Lade die existierenden Tags
            $this->testTags = $this->entityManager->getRepository(Tag::class)->findBy(['name' => ['TestTag1', 'TestTag2', 'AnotherTag']]);
        }
    }

    /**
     * Test für indexAction mit JSON-Anfrage
     */
    public function testIndexActionJson(): void
    {
        $this->client->request(
            'GET', 
            '/tags/', 
            [], 
            [], 
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['results']);
        
        // Überprüfe, ob unsere Test-Tags enthalten sind
        $tagNames = array_map(function($tag) {
            return $tag['name'];
        }, $response['results']);
        
        $this->assertContains('TestTag1', $tagNames);
        $this->assertContains('TestTag2', $tagNames);
    }
    
    /**
     * Test für indexAction mit JSON-Anfrage und Suchparameter
     */
    public function testIndexActionJsonWithSearch(): void
    {
        $this->client->request(
            'GET', 
            '/tags/?q=Another', 
            [], 
            [], 
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        
        // Überprüfe, dass nur der gesuchte Tag zurückgegeben wird
        $tagNames = array_map(function($tag) {
            return $tag['name'];
        }, $response['results']);
        
        $this->assertContains('AnotherTag', $tagNames);
        $this->assertNotContains('TestTag1', $tagNames);
        $this->assertNotContains('TestTag2', $tagNames);
    }
    
    /**
     * Test für indexActionNonJson
     */
    public function testIndexActionNonJson(): void
    {
        $this->client->request('GET', '/tags/');

        $this->assertResponseRedirects('/');
    }
    
    /**
     * Test für showAction mit einem einzelnen Tag
     */
    public function testShowActionSingleTag(): void
    {
        // Stelle sicher, dass wir Test-Tags haben
        if (empty($this->testTags)) {
            $this->createTestData();
        }
        
        // Verwende stets einen gültigen Slug für den Test
        $tagSlug = !empty($this->testTags) && $this->testTags[0]->getSlug() ? 
                  $this->testTags[0]->getSlug() : 'testtag1';
        
        $this->client->request('GET', '/tags/' . $tagSlug . '.html');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Termine');
        $this->assertSelectorTextContains('h1', 'TestTag1');
    }
    
    /**
     * Test für showAction mit mehreren Tags (OR-Verknüpfung)
     */
    public function testShowActionMultipleTagsOr(): void
    {
        $this->client->request('GET', '/tags/testtag1|testtag2.html');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Termine');
        $this->assertSelectorTextContains('h1', 'TestTag1');
        $this->assertSelectorTextContains('h1', 'TestTag2');
        $this->assertSelectorTextContains('h1', 'oder');
    }
    
    /**
     * Test für showAction mit mehreren Tags (AND-Verknüpfung)
     */
    public function testShowActionMultipleTagsAnd(): void
    {
        $this->client->request('GET', '/tags/testtag1&testtag2.html');

        $this->assertResponseIsSuccessful();
        
        // Überprüfe, ob beide Tagnamen und das "und" (AND-Verknüpfung) in der Überschrift vorkommen
        $this->assertSelectorTextContains('h1', 'Termine');
        $this->assertSelectorTextContains('h1', 'TestTag1');
        $this->assertSelectorTextContains('h1', 'TestTag2');
        $this->assertSelectorTextContains('h1', 'und');
    }
    
    /**
     * Test für showAction mit ICS-Format
     */
    public function testShowActionIcsFormat(): void
    {
        $this->client->request('GET', '/tags/testtag1.ics');

        $this->assertResponseIsSuccessful();
        
        $response = $this->client->getResponse();
        // Überprüfe den Content-Type
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString(
            'text/calendar', 
            $contentType,
            'Response sollte Content-Type text/calendar enthalten'
        );
        
        // Überprüfe, ob typische iCalendar-Elemente im Inhalt vorhanden sind
        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('VERSION:', $content);
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('SUMMARY:', $content);
        $this->assertStringContainsString('END:VEVENT', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }
    
    /**
     * Test für showAction mit nicht existierendem Tag
     */
    public function testShowActionWithNonExistentTag(): void
    {
        $this->client->request('GET', '/tags/nonexistenttag.html');

        $this->assertResponseStatusCodeSame(404);
    }
    
    /**
     * Die AND-Verknüpfung und ICS-Tests funktionieren nun aufgrund der geänderten 
     * SQL-Abfrage auf allen Datenbanken, nicht nur auf PostgreSQL.
     */
    
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