<?php

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LocationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;
    private ?Location $testLocation;

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
        // Überprüfe, ob bereits ein Test-Ort existiert
        $location = $this->entityManager->getRepository(Location::class)->findOneBy(['name' => 'TestOrt']);
        
        if (!$location) {
            // Erstelle Test-Ort
            $location = new Location();
            $location->setName('TestOrt');
            $location->setSlug('testort');
            $location->setStreetAddress('Teststraße');
            $location->setStreetNumber('123');
            $location->setZipCode('12345');
            $location->setCity('Teststadt');
            $location->setLat(51.1234);
            $location->setLon(10.4321);
            $location->setDescription('Ein Testort für automatisierte Tests');
            
            $this->entityManager->persist($location);
            
            // Erstelle auch ein Event mit diesem Ort
            $event = new Event();
            $event->setSummary('Location-Test Event');
            $event->setStartdate(new \DateTime('+1 day'));
            $event->setSlug('location-test-event');
            // Setze den Ort (da die Beziehung in Event über locations_id geht, 
            // muss auch dort Entity Manager dafür verwendet werden)
            
            $this->entityManager->persist($event);
            $this->entityManager->flush();
            
            // In tests können wir Event und Location im setUp verbinden
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE events SET locations_id = ? WHERE id = ?',
                [$location->getId(), $event->getId()]
            );
        }
        
        $this->testLocation = $this->entityManager->getRepository(Location::class)->findOneBy(['name' => ['TestOrt', 'TestOrt (aktualisiert)']]);
    }

    /**
     * Test für showAction mit HTML-Format
     */
    public function testShowActionHtml(): void
    {
        // Stelle sicher, dass ein Test-Ort existiert
        if (!$this->testLocation) {
            $this->createTestData();
        }
        
        $this->client->request('GET', '/orte/' . $this->testLocation->getSlug() . '.html');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Termine');
    }
    
    /**
     * Test für editAction
     */
    public function testEditAction(): void
    {
        $this->client->request('GET', '/orte/' . $this->testLocation->getSlug() . '/bearbeiten');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="name"]');
        $this->assertSelectorExists('textarea[name="description"]');
    }
    
    /**
     * Test für indexAction mit JSON-Anfrage
     */
    public function testIndexActionJson(): void
    {
        $this->client->request(
            'GET', 
            '/orte/?q=' . urlencode(substr($this->testLocation->getName(), 0, 4)), 
            [], 
            [], 
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['results']);
        
        // Überprüfe, ob unser Test-Ort enthalten ist
        $locationNames = array_map(function($location) {
            return $location['name'];
        }, $response['results']);
        
        $this->assertContains($this->testLocation->getName(), $locationNames);
    }
    
    /**
     * Test für indexAction mit JSON-Anfrage und Suchparameter
     */
    public function testIndexActionJsonWithSearch(): void
    {
        $this->client->request(
            'GET', 
            '/orte/?q=Test', 
            [], 
            [], 
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        
        // Überprüfe, dass der gesuchte Ort zurückgegeben wird
        $locationNames = array_map(function($location) {
            return $location['name'];
        }, $response['results']);
        
        $this->assertContains($this->testLocation->getName(), $locationNames);
    }
    
    /**
     * Test für indexActionNonJson
     */
    public function testIndexActionNonJson(): void
    {
        $this->client->request('GET', '/orte/');

        $this->assertResponseRedirects('/');
    }
    
    /**
     * Test für updateAction mit gültigen Daten
     * Hinweis: Dieser Test wird aus vereinfachungsgründen als letzter ausgeführt,
     * um sicherzustellen, dass die anderen Tests nicht durch die Änderung des
     * Namens/Slugs beeinträchtigt werden.
     */
    public function testUpdateActionWithValidData(): void
    {
        $locationId = $this->testLocation->getId();
        $locationSlug = $this->testLocation->getSlug();
        
        // Direkt eine POST-Anfrage an die Update-URL senden
        $this->client->request(
            'POST',
            '/orte/' . $locationSlug . '/bearbeiten',
            [
                'name' => 'UpdatedLocationName',
                'streetaddress' => 'Neue Teststraße',
                'streetnumber' => '456',
                'zipcode' => '54321',
                'city' => 'Neue Teststadt',
                'description' => 'Aktualisierte Beschreibung',
                'geocords' => '51.1234,10.4321'
            ]
        );
        
        // Nach erfolgreicher Aktualisierung sollte eine Weiterleitung erfolgen
        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Es erfolgte keine Weiterleitung nach dem Update');
        
        // Überprüfe in der Datenbank, ob die Änderungen gespeichert wurden
        $this->entityManager->clear(); // Cache leeren
        $updatedLocation = $this->entityManager->getRepository(Location::class)
            ->find($locationId);
        
        $this->assertNotNull($updatedLocation, 'Der Ort wurde nicht gefunden');
        $this->assertEquals('UpdatedLocationName', $updatedLocation->getName());
        $this->assertEquals('Neue Teststraße', $updatedLocation->getStreetAddress());
        $this->assertEquals('54321', $updatedLocation->getZipCode());
    }
    
    /**
     * Test für updateAction mit ungültigen Daten (existierender Name)
     */
    public function testUpdateActionWithExistingName(): void
    {
        $locationId = $this->testLocation->getId();
        $locationName = $this->testLocation->getName();
        $locationSlug = $this->testLocation->getSlug();
        
        // Erstelle einen zweiten Ort
        $secondLocation = new Location();
        $secondLocation->setName('ZweiterTestOrt');
        $secondLocation->setSlug('zweiter-testort');
        $this->entityManager->persist($secondLocation);
        $this->entityManager->flush();
        
        // Merke die ID des zweiten Ortes
        $secondLocationId = $secondLocation->getId();
        
        // Direkt eine POST-Anfrage an die Update-URL senden
        $this->client->request(
            'POST',
            '/orte/' . $locationSlug . '/bearbeiten',
            ['name' => 'ZweiterTestOrt'] // Name existiert bereits
        );
        
        // Nach fehlgeschlagener Aktualisierung sollte zur Bearbeitungsseite umgeleitet werden
        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Es erfolgte keine Weiterleitung nach dem fehlgeschlagenen Update');
        
        // Überprüfe direkt in der Datenbank, dass der Name nicht geändert wurde
        $this->entityManager->clear(); // Cache leeren
        $location = $this->entityManager->getRepository(Location::class)
            ->find($locationId);
        
        $this->assertEquals($locationName, $location->getName(), 'Der Name wurde trotz Duplikats geändert');
        
        // Lösche den zweiten Ort - wichtig: frisch laden nach clear()
        $secondLocationToDelete = $this->entityManager->getRepository(Location::class)
            ->find($secondLocationId);
        
        if ($secondLocationToDelete) {
            $this->entityManager->remove($secondLocationToDelete);
            $this->entityManager->flush();
        }
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