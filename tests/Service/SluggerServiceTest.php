<?php

namespace App\Tests\Service;

use App\Entity\Tag;
use App\Repository\LocationRepository;
use App\Service\SluggerService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\AbstractUnicodeString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class SluggerServiceTest extends TestCase
{
    public function testGenerateUniqueSlugIfItExists()
    {
        $slugger = $this->createMock(SluggerInterface::class);
        $repository = $this->createMock(LocationRepository::class);

        $slugger->method('slug')
            ->willReturnCallback(function ($string) {
                return new UnicodeString($string);
            });

        $myTag = new Tag();
        $myTag->setSlug('test-slug');

        $repository->method('findOneBy')
            ->willReturn($myTag, null, null);

        $sluggerService = new SluggerService($slugger);

        $slug = $sluggerService->generateUniqueSlug('test-slug', $repository);

        $this->assertEquals('test-slug-1', $slug);
    }

    public function testGenerateUniqueSlugIfItIsNew()
    {
        $slugger = $this->createMock(SluggerInterface::class);
        $repository = $this->createMock(LocationRepository::class);

        $slugger->method('slug')
            ->willReturnCallback(function ($string) {
                return new UnicodeString($string);
            });

        $myTag = new Tag();
        $myTag->setSlug('test-slug');

        $repository->method('findOneBy')
            ->willReturn(null);

        $sluggerService = new SluggerService($slugger);

        $slug = $sluggerService->generateUniqueSlug('test-slug', $repository);

        $this->assertEquals('test-slug', $slug);
    }
}