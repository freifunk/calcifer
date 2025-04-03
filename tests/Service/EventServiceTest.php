<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Tag;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\EventRepository;
use App\Repository\TagRepository;
use App\Service\EventService;
use App\Service\SluggerService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use App\Repository\RepeatingEventLogRepository;

class EventServiceTest extends TestCase
{
    private $entityManager;
    private $eventRepository;
    private $tagRepository;
    private $sluggerService;
    private $service;
    private $queryBuilder;
    private $query;
    private $repeatingEventLogRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventRepository = $this->createMock(EventRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->sluggerService = $this->createMock(SluggerService::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        $this->repeatingEventLogRepository = $this->createMock(RepeatingEventLogRepository::class);
        
        $this->service = new EventService(
            $this->entityManager, 
            $this->eventRepository,
            $this->tagRepository,
            $this->sluggerService,
            $this->repeatingEventLogRepository
        );
    }

    public function testFindUpcomingEvents(): void
    {
        // Mock objects for the query builder
        $this->queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with(['e'])
            ->willReturn($this->queryBuilder);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(Event::class, 'e')
            ->willReturn($this->queryBuilder);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('e.startdate >= :startdate')
            ->willReturn($this->queryBuilder);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('orderBy')
            ->with('e.startdate')
            ->willReturn($this->queryBuilder);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('startdate', $this->anything())
            ->willReturn($this->queryBuilder);
        
        $this->queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);
        
        // Mock the entity manager to return our query builder
        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);
        
        // Expected result
        $expectedEvents = [new Event(), new Event()];
        
        $this->query
            ->expects($this->once())
            ->method('execute')
            ->willReturn($expectedEvents);
        
        // Call service method and check result
        $events = $this->service->findUpcomingEvents();
        $this->assertSame($expectedEvents, $events);
    }

    public function testFindBySlug(): void
    {
        $event = new Event();
        $event->setSummary('Test Event');
        
        $this->eventRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'test-slug'])
            ->willReturn($event);
        
        $result = $this->service->findBySlug('test-slug');
        $this->assertSame($event, $result);
    }

    public function testSave(): void
    {
        $event = new Event();
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($event);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $this->service->save($event);
    }

    public function testDelete(): void
    {
        $event = new Event();
        
        $this->repeatingEventLogRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['event' => $event])
            ->willReturn([]);
        
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($event);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        $this->service->delete($event);
    }
    
    public function testDeleteWithLogEntries(): void
    {
        $event = new Event();
        
        // Mock log entries
        $logEntry1 = new RepeatingEventLogEntry();
        $logEntry1->setEventsId($event->getId());
        $logEntry1->setEvent($event);
        $logEntries = [$logEntry1];
        
        // Configure repeatingEventLogRepository to return the log entries
        $this->repeatingEventLogRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['event' => $event])
            ->willReturn($logEntries);
        
        // Da es schwierig ist, die genaue Reihenfolge zu testen, überprüfen wir stattdessen
        // dass die remove-Methode mindestens zweimal aufgerufen wird
        // (einmal für das LogEntry und einmal für das Event)
        $this->entityManager
            ->expects($this->atLeast(2))
            ->method('remove');
        
        // flush() sollte zweimal aufgerufen werden
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');
        
        $this->service->delete($event);
    }

    public function testProcessTags(): void
    {
        $event = new Event();
        $event->setTags(new ArrayCollection());
        
        $existingTag = new Tag();
        $existingTag->setName('existing');
        
        // Mock finding existing tag
        $this->tagRepository
            ->method('findBy')
            ->willReturnCallback(function($criteria) use ($existingTag) {
                if ($criteria['name'] === 'existing') {
                    return [$existingTag];
                }
                return [];
            });
        
        // Mock slug generation for new tag
        $this->sluggerService
            ->expects($this->once())
            ->method('generateUniqueSlug')
            ->with('new', $this->tagRepository)
            ->willReturn('new-slug');
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function($tag) {
                return $tag instanceof Tag && $tag->getName() === 'new';
            }));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        // Call service method
        $this->service->processTags($event, 'existing, new');
        
        // Check tags were added to event
        $tags = $event->getTags();
        $this->assertCount(2, $tags);
        
        $tagNames = array_map(function($tag) { return $tag->getName(); }, $tags->toArray());
        $this->assertContains('existing', $tagNames);
        $this->assertContains('new', $tagNames);
    }

    public function testGenerateSlug(): void
    {
        $expectedSlug = 'test-slug';
        
        $this->sluggerService
            ->expects($this->once())
            ->method('generateUniqueSlug')
            ->with('Test Title', $this->eventRepository)
            ->willReturn($expectedSlug);
        
        $result = $this->service->generateSlug('Test Title');
        $this->assertEquals($expectedSlug, $result);
    }
} 