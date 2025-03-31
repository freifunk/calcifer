<?php

namespace App\Tests\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagService;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TagServiceTest extends TestCase
{
    private $entityManager;
    private $repository;
    private $sluggerService;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(TagRepository::class);
        $this->sluggerService = $this->createMock(SluggerService::class);
        
        $this->service = new TagService(
            $this->entityManager,
            $this->repository,
            $this->sluggerService
        );
    }

    public function testFindOrCreateTagWithExistingTag(): void
    {
        // Testdaten
        $existingTag = new Tag();
        $existingTag->setName('Existierend');
        $existingTag->setSlug('existierend');
        
        // Mock-Repository konfigurieren, um das existierende Tag zurückzugeben
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'Existierend'])
            ->willReturn($existingTag);
        
        // EntityManager sollte nicht aufgerufen werden
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        
        $this->entityManager
            ->expects($this->never())
            ->method('flush');
        
        // Service aufrufen
        $result = $this->service->findOrCreateTag('Existierend');
        
        // Ergebnis prüfen
        $this->assertSame($existingTag, $result);
    }
    
    public function testFindOrCreateTagWithNewTag(): void
    {
        // Mock-Repository konfigurieren, um kein existierendes Tag zurückzugeben
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'Neu'])
            ->willReturn(null);
        
        // Mock-SluggerService konfigurieren
        $this->sluggerService
            ->expects($this->once())
            ->method('generateUniqueSlug')
            ->with('neu', $this->repository)
            ->willReturn('neu');
        
        // Prüfen, ob ein neues Tag persistiert wird
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function($tag) {
                return $tag instanceof Tag 
                    && $tag->getName() === 'Neu' 
                    && $tag->getSlug() === 'neu';
            }));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        // Service aufrufen
        $result = $this->service->findOrCreateTag('Neu');
        
        // Ergebnis prüfen
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals('Neu', $result->getName());
        $this->assertEquals('neu', $result->getSlug());
    }
    
    public function testCreateTagsFromString(): void
    {
        // Testdaten
        $tagsString = 'Tag1, Tag2,Tag3';
        
        // Mock-Verhalten für findOrCreateTag
        $tag1 = new Tag();
        $tag1->setName('Tag1');
        $tag1->setSlug('tag1');
        
        $tag2 = new Tag();
        $tag2->setName('Tag2');
        $tag2->setSlug('tag2');
        
        $tag3 = new Tag();
        $tag3->setName('Tag3');
        $tag3->setSlug('tag3');
        
        // In neueren PHPUnit-Versionen gibt es kein withConsecutive mehr
        // Stattdessen verwenden wir einen eigenen einfachen Mock-Service
        $mockService = new class($tag1, $tag2, $tag3) extends TagService {
            private array $tags;
            private int $callCount = 0;
            
            public function __construct(private Tag $tag1, private Tag $tag2, private Tag $tag3) 
            {
                // Überschreiben des Eltern-Konstruktors
            }
            
            public function findOrCreateTag(string $name): Tag 
            {
                $this->callCount++;
                
                // Je nach Aufrufzähler das entsprechende Tag zurückgeben
                return match($name) {
                    'Tag1' => $this->tag1,
                    'Tag2' => $this->tag2,
                    'Tag3' => $this->tag3,
                    default => throw new \RuntimeException("Unerwarteter Tag-Name: $name")
                };
            }
            
            public function getCallCount(): int
            {
                return $this->callCount;
            }
        };
        
        // Methode aufrufen und Ergebnis prüfen
        $result = $mockService->createTagsFromString($tagsString);
        
        // Prüfen, dass die Methode 3 mal aufgerufen wurde
        $this->assertEquals(3, $mockService->getCallCount());
        
        // Prüfen, dass die richtigen Tags zurückgegeben wurden
        $this->assertCount(3, $result);
        $this->assertSame($tag1, $result[0]);
        $this->assertSame($tag2, $result[1]);
        $this->assertSame($tag3, $result[2]);
    }
} 