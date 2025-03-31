<?php

namespace App\Tests\Service;

use App\Entity\RepeatingEvent;
use App\Entity\RepeatingEventLogEntry;
use App\Repository\RepeatingEventLogRepository;
use App\Repository\RepeatingEventRepository;
use App\Service\RepeatingEventService;
use App\Service\SluggerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RepeatingEventServiceTest extends TestCase
{
    private $entityManager;
    private $repository;
    private $logRepository;
    private $sluggerService;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(RepeatingEventRepository::class);
        $this->logRepository = $this->createMock(RepeatingEventLogRepository::class);
        $this->sluggerService = $this->createMock(SluggerService::class);
        
        $this->service = new RepeatingEventService(
            $this->entityManager, 
            $this->repository,
            $this->logRepository,
            $this->sluggerService
        );
    }

    public function testFindBySlug(): void
    {
        // Testdaten vorbereiten
        $event = new RepeatingEvent();
        $event->setSummary('Test Event');
        
        // Mock-Verhalten konfigurieren
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'test-slug'])
            ->willReturn($event);
        
        // Service-Methode aufrufen und prüfen
        $result = $this->service->findBySlug('test-slug');
        $this->assertSame($event, $result);
    }

    public function testSave(): void
    {
        // Testdaten vorbereiten
        $event = new RepeatingEvent();
        $event->setSummary('Test Event');
        $event->setSlug('existing-slug'); // Slug ist bereits gesetzt
        
        // Mock-Verhalten konfigurieren
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($event);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        // Service-Methode aufrufen
        $this->service->save($event);
    }
    
    public function testSaveWithSlugGeneration(): void
    {
        // Testdaten vorbereiten
        $event = new RepeatingEvent();
        $event->setSummary('Test Event');
        // Kein Slug gesetzt, sollte generiert werden
        
        // SluggerService Mock konfigurieren
        $this->sluggerService
            ->expects($this->once())
            ->method('generateUniqueSlug')
            ->with(strtolower('Test Event'), $this->repository)
            ->willReturn('test-event');
        
        // Mock-Verhalten für EntityManager
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($savedEvent) {
                return $savedEvent->getSlug() === 'test-event';
            }));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        // Service-Methode aufrufen
        $this->service->save($event);
        
        // Prüfen, dass der Slug gesetzt wurde
        $this->assertEquals('test-event', $event->getSlug());
    }

    public function testDelete(): void
    {
        // Testdaten vorbereiten
        $event = new RepeatingEvent();
        
        // Mock-Verhalten konfigurieren
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($event);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');
        
        // Service-Methode aufrufen
        $this->service->delete($event);
    }
    
    public function testFindAllLogs(): void
    {
        // Testdaten vorbereiten
        $logEntry1 = new RepeatingEventLogEntry();
        $logEntry2 = new RepeatingEventLogEntry();
        $logs = [$logEntry1, $logEntry2];
        
        // Mock-Verhalten konfigurieren
        $this->logRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['eventStartdate' => 'DESC'])
            ->willReturn($logs);
        
        // Service-Methode aufrufen und prüfen
        $result = $this->service->findAllLogs();
        $this->assertSame($logs, $result);
    }
    
    public function testFindLogsByEvent(): void
    {
        // Testdaten vorbereiten
        $event = new RepeatingEvent();
        $logEntry = new RepeatingEventLogEntry();
        $logs = [$logEntry];
        
        // Mock-Verhalten konfigurieren
        $this->logRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['event' => $event], ['eventStartdate' => 'DESC'])
            ->willReturn($logs);
        
        // Service-Methode aufrufen und prüfen
        $result = $this->service->findLogsByEvent($event);
        $this->assertSame($logs, $result);
    }
} 