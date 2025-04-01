<?php

namespace App\Tests\Integration;

use App\Entity\Event;
use App\Entity\Tag;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventIntegrationTest extends KernelTestCase
{
    private $entityManager;
    private $eventRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->eventRepository = $this->entityManager->getRepository(Event::class);
    }

    public function testCreateEventWithTags(): void
    {
        // Erstelle einen neuen Tag
        $tag = new Tag();
        $tag->setName('test-tag');
        $tag->setSlug('test-tag');
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        // Erstelle ein neues Event mit diesem Tag
        $event = new Event();
        $event->setSummary('Test Event');
        $event->setStartdate(new DateTime());
        $event->setSlug('test-event');
        $event->addTag($tag);
        
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        
        // Hole das Event aus der Datenbank und prüfe, ob die Tags korrekt sind
        $savedEvent = $this->eventRepository->findOneBy(['slug' => 'test-event']);
        $this->assertNotNull($savedEvent);
        $this->assertEquals('Test Event', $savedEvent->getSummary());
        
        $tags = $savedEvent->getTags();
        $this->assertCount(1, $tags);
        $this->assertEquals('test-tag', $tags[0]->getName());
    }

    public function testGetTagsAsText(): void
    {
        // Erstelle ein Event mit mehreren Tags
        $event = new Event();
        $event->setSummary('Multiple Tags Event');
        $event->setStartdate(new DateTime());
        $event->setSlug('multiple-tags-event');
        
        $tag1 = new Tag();
        $tag1->setName('tag1');
        $tag1->setSlug('tag1');
        
        $tag2 = new Tag();
        $tag2->setName('tag2');
        $tag2->setSlug('tag2');
        
        $this->entityManager->persist($tag1);
        $this->entityManager->persist($tag2);
        $this->entityManager->flush();
        
        $event->addTag($tag1);
        $event->addTag($tag2);
        
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        
        // Prüfe, ob getTagsAsText korrekt funktioniert
        $savedEvent = $this->eventRepository->findOneBy(['slug' => 'multiple-tags-event']);
        $this->assertNotNull($savedEvent);
        
        $tagsText = $savedEvent->getTagsAsText();
        $this->assertStringContainsString('tag1', $tagsText);
        $this->assertStringContainsString('tag2', $tagsText);
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Datenbank zurücksetzen
        $this->entityManager->close();
        $this->entityManager = null;
    }
} 