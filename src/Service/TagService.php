<?php

namespace App\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class TagService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $repository,
        private readonly SluggerService $sluggerService
    ) {}

    /**
     * Findet ein Tag anhand des Namens oder erstellt es, wenn es nicht existiert
     */
    public function findOrCreateTag(string $name): Tag
    {
        $name = trim($name);
        $tag = $this->repository->findOneBy(['name' => $name]);
        
        if (!$tag) {
            $tag = new Tag();
            $tag->setName($name);
            $tag->setSlug($this->sluggerService->generateUniqueSlug(strtolower($name), $this->repository));
            
            $this->entityManager->persist($tag);
            $this->entityManager->flush();
        }
        
        return $tag;
    }
    
    /**
     * Erstellt Tags aus einem kommagetrennten String
     * 
     * @return Tag[]
     */
    public function createTagsFromString(string $tagsString): array
    {
        $tagNames = explode(',', $tagsString);
        $tags = [];
        
        foreach ($tagNames as $name) {
            $name = trim($name);
            if (!empty($name)) {
                $tags[] = $this->findOrCreateTag($name);
            }
        }
        
        return $tags;
    }

    /**
     * Findet Tags basierend auf einem Slug-String
     * Unterstützt einfache Slugs, OR-Verknüpfung (|) und AND-Verknüpfung (&)
     * 
     * @return array{tags: Tag[], operator: string}
     */
    public function findTagsBySlugString(string $slug): array
    {
        $tags = [];
        $operator = 'or';
        
        if (str_contains($slug, '|')) {
            $slugs = explode('|', $slug);
            $tags = $this->findTagsBySlugs($slugs);
        } elseif (str_contains($slug, '&')) {
            $slugs = explode('&', $slug);
            $tags = $this->findTagsBySlugs($slugs);
            $operator = 'and';
        } else {
            $tag = $this->repository->findOneBy(['slug' => $slug]);
            
            if ($tag instanceof Tag) {
                $tags[] = $tag;
            }
        }
        
        return [
            'tags' => $tags,
            'operator' => $operator
        ];
    }
    
    /**
     * Findet Ereignisse, die alle der angegebenen Tags enthalten
     * 
     * @param Tag[] $tags
     * @return array
     */
    public function findEventsWithTagsAND(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }
        
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        
        $sql = <<<EOF
SELECT e.* FROM events AS e
JOIN events2tags et ON e.id = et.events_id
WHERE et.tags_id IN (@tags@)
AND e.startdate >= :startdate
GROUP BY e.id
HAVING COUNT(DISTINCT et.tags_id) = :tag_count
ORDER BY e.startdate
EOF;
        
        $tag_ids = array_reduce($tags, function ($carry, $item) {
            if (strlen($carry) == 0) {
                return $item->getId();
            } else {
                return $carry . ',' . $item->getId();
            }
        });

        $sql = str_replace('@tags@', $tag_ids, $sql);

        $rsm = new ResultSetMappingBuilder($this->entityManager);
        $rsm->addRootEntityFromClassMetadata('App\Entity\Event', 'e');

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('startdate', $now);
        $query->setParameter('tag_count', count($tags));

        return $query->getResult();
    }
    
    /**
     * Findet Ereignisse, die mindestens eines der angegebenen Tags enthalten
     * 
     * @param Tag[] $tags
     * @return array
     */
    public function findEventsWithTagsOR(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }
        
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select(array('e'))
            ->from('App\Entity\Event', 'e')
            ->where('e.startdate >= :startdate')
            ->orderBy('e.startdate')
            ->setParameter('startdate', $now);
        
        $tag_ids = array_reduce($tags, function ($carry, $item) {
            if (strlen($carry) == 0) {
                return $item->getId();
            } else {
                return $carry . ',' . $item->getId();
            }
        });
        
        $qb->join('e.tags', 't', 'WITH', $qb->expr()->in('t.id', $tag_ids));
        
        return $qb->getQuery()->execute();
    }
    
    /**
     * Findet Tags, deren Name einen bestimmten Suchbegriff enthält
     * 
     * @return Tag[]
     */
    public function findTagsLike(?string $searchTerm = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select(['t'])
            ->from('App\Entity\Tag', 't');
        
        if (!empty($searchTerm)) {
            $qb->where('t.name LIKE :tag')
                ->setParameter('tag', sprintf('%%%s%%', strtolower($searchTerm)));
        }
        
        $qb->orderBy('t.name');
        return $qb->getQuery()->execute();
    }

    /**
     * Findet Tags basierend auf einer Liste von Slugs
     * 
     * @param string[] $slugs
     * @return Tag[]
     */
    public function findTagsBySlugs(array $slugs): array
    {
        return $this->repository->findBy(['slug' => $slugs]);
    }
} 