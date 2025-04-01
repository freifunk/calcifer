<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Test für die hello-Action mit einem Namen
     */
    public function testHelloAction(): void
    {
        $this->client->request('GET', '/hello/Symfony');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Symfony');
    }
    
    /**
     * Test für die hello-Action mit einem anderen Namen
     */
    public function testHelloActionWithDifferentName(): void
    {
        $this->client->request('GET', '/hello/TestName');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'TestName');
    }
    
    /**
     * Test für die indexAction (Über-Seite)
     */
    public function testIndexAction(): void
    {
        $this->client->request('GET', '/über');

        $this->assertResponseIsSuccessful();
        // Hier könnten spezifischere Prüfungen erfolgen, wenn der Inhalt der Seite bekannt ist
        // Zum Beispiel Prüfung auf Überschrift, bestimmte Texte, etc.
    }
    
    /**
     * Test für die Startseite (Route /)
     */
    public function testHomepage(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        // Hier könnten spezifischere Prüfungen erfolgen, wenn der Inhalt der Seite bekannt ist
    }
} 