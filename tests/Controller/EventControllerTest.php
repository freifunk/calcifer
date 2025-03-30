<?php

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        
        // Überprüfe, ob Tabellen existieren
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        
        try {
            $tables = $schemaManager->listTableNames();
            
            // Wenn die tags-Tabelle nicht existiert, erstellen wir das Schema neu
            if (!in_array('tags', $tables)) {
                // Schema neu erstellen
                $schemaTool = new SchemaTool($this->entityManager);
                $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
                $schemaTool->dropSchema($metadata);
                $schemaTool->createSchema($metadata);
                echo "Test database schema created in EventControllerTest\n";
            }
            
            // Erstelle Testdaten nur, wenn die Tabellen existieren
            $this->createTestData();
            
        } catch (\Exception $e) {
            echo "Error checking database schema: " . $e->getMessage() . "\n";
            
            // Schema neu erstellen
            $schemaTool = new SchemaTool($this->entityManager);
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            
            // Testdaten erstellen
            $this->createTestData();
        }
    }
    
    private function createTestData(): void 
    {
        // Überprüfe, ob bereits Test-Tag existiert
        $tag = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => 'test']);
        
        if (!$tag) {
            // Erstelle Test-Tag
            $tag = new Tag();
            $tag->setName('test');
            $tag->setSlug('test');
            $this->entityManager->persist($tag);
        }
        
        // Überprüfe, ob bereits Testevent existiert
        $event = $this->entityManager->getRepository(Event::class)->findOneBy(['summary' => 'Testevent']);
        
        if (!$event) {
            // Erstelle Testevent
            $event = new Event();
            $event->setSummary('Testevent');
            $event->setStartdate(new \DateTime());
            $event->setSlug('testevent');
            $this->entityManager->persist($event);
        }
        
        $this->entityManager->flush();
    }

    public function testIndexAction(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testNewAction(): void
    {
        $this->client->request('GET', '/termine/neu');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[id="event-form"]');
    }

    public function testCreateActionWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/termine/neu');

        $form = $crawler->selectButton('Speichern')->form([
            'summary' => 'Testevent über WebTestCase',
            'startdate' => '2023-10-01T18:00',
            'description' => 'Dies ist ein Testevent',
            'location' => 'Testort',
            'tags' => 'test,symfony,phpunit'
        ]);

        $this->client->submit($form);
        
        // Prüfe, ob die Weiterleitung funktioniert (nach erfolgreicher Erstellung)
        $this->assertResponseRedirects();
    }

    public function testCreateActionWithInvalidData(): void
    {
        $crawler = $this->client->request('GET', '/termine/neu');

        // Absenden ohne required field 'summary'
        $form = $crawler->selectButton('Speichern')->form([
            'startdate' => '2023-10-01T18:00',
            'description' => 'Dies ist ein Testevent',
        ]);

        $this->client->submit($form);
        
        // Da die Form nicht gültig ist, sollten wir auf der gleichen Seite bleiben
        $this->assertResponseIsSuccessful();
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