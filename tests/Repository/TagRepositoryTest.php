<?php

namespace App\Tests\Repository;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TagRepositoryTest extends KernelTestCase
{
    private $entityManager;
    private $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Tag::class);
        
        // Stelle sicher, dass keine Test-Tags vorhanden sind
        $this->clearTestTags();
    }
    
    /**
     * Entfernt Test-Tags aus der Datenbank
     */
    private function clearTestTags(): void
    {
        $tags = $this->repository->findBy([
            'name' => [
                'CreateTestTag', 
                'UpdateTestTag', 
                'UpdatedTestTag', 
                'DeleteTestTag'
            ]
        ]);
        
        foreach ($tags as $tag) {
            $this->entityManager->remove($tag);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Test für die Erstellung eines neuen Tags
     */
    public function testCreateTag(): void
    {
        $tag = new Tag();
        $tag->setName('CreateTestTag');
        $tag->setSlug('create-test-tag');
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        // Überprüfen, ob der Tag in der Datenbank ist
        $foundTag = $this->repository->findOneBy(['slug' => 'create-test-tag']);
        $this->assertNotNull($foundTag);
        $this->assertEquals('CreateTestTag', $foundTag->getName());
    }

    /**
     * Test für die Aktualisierung eines Tags
     */
    public function testUpdateTag(): void
    {
        // Erstelle zuerst einen Tag
        $tag = new Tag();
        $tag->setName('UpdateTestTag');
        $tag->setSlug('update-test-tag');
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        // Ändere den Tag
        $tag->setName('UpdatedTestTag');
        $this->entityManager->flush();
        
        // Überprüfen, ob die Änderung gespeichert wurde
        $foundTag = $this->repository->findOneBy(['slug' => 'update-test-tag']);
        $this->assertEquals('UpdatedTestTag', $foundTag->getName());
    }

    /**
     * Test für das Löschen eines Tags
     */
    public function testDeleteTag(): void
    {
        // Erstelle einen Tag
        $tag = new Tag();
        $tag->setName('DeleteTestTag');
        $tag->setSlug('delete-test-tag');
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        // Tag ID merken
        $tagId = $tag->getId();
        
        // Lösche den Tag
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        
        // Überprüfen, ob der Tag gelöscht wurde
        $foundTag = $this->repository->find($tagId);
        $this->assertNull($foundTag);
    }
    
    /**
     * Test für die findAllOrderedBySlug-Methode
     */
    public function testFindAllOrderedBySlug(): void
    {
        // Erstelle mehrere Tags mit unterschiedlichen Slugs
        $tagData = [
            ['name' => 'TagC', 'slug' => 'c-tag'],
            ['name' => 'TagA', 'slug' => 'a-tag'],
            ['name' => 'TagB', 'slug' => 'b-tag']
        ];
        
        $createdTags = [];
        
        foreach ($tagData as $data) {
            $tag = new Tag();
            $tag->setName($data['name']);
            $tag->setSlug($data['slug']);
            $this->entityManager->persist($tag);
            $createdTags[] = $tag;
        }
        
        $this->entityManager->flush();
        
        // Rufe findAllOrderedBySlug auf
        $orderedTags = $this->repository->findAllOrderedBySlug();
        
        // Überprüfe, dass mindestens 3 Tags zurückgegeben werden
        $this->assertGreaterThanOrEqual(3, count($orderedTags));
        
        // Extrahiere die Slugs für einen einfacheren Vergleich
        $slugs = array_map(function($tag) {
            return $tag->getSlug();
        }, $orderedTags);
        
        // Überprüfe, dass die Tags nach Slug sortiert sind
        $this->assertLessThan(
            array_search('b-tag', $slugs),
            array_search('a-tag', $slugs),
            'Tags sind nicht korrekt nach Slug sortiert'
        );
        
        $this->assertLessThan(
            array_search('c-tag', $slugs),
            array_search('b-tag', $slugs),
            'Tags sind nicht korrekt nach Slug sortiert'
        );
        
        // Entferne die erstellten Tags
        foreach ($createdTags as $tag) {
            $this->entityManager->remove($tag);
        }
        
        $this->entityManager->flush();
    }
    
    protected function tearDown(): void
    {
        // Stelle sicher, dass alle Test-Tags gelöscht werden
        $this->clearTestTags();
        
        parent::tearDown();
        
        // Vermeiden von Memory-Leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
} 