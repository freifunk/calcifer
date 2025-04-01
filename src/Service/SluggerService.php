<?php

namespace App\Service;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class SluggerService
{

    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function generateUniqueSlug(string $string, ServiceEntityRepository $repository): string
    {
        $slug = $this->slugger->slug($string)->toString();
        $originalSlug = $slug;
        $i = 1;

        while ($repository->findOneBy(['slug' => $slug])) {
            $slug = $originalSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }
}