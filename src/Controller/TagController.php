<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

use Sabre\VObject;

#[Route('/tags')]
class TagController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tagRepository
    ) {}
    
    #[Route('/{slug}.{format}', name: 'tag_show', defaults: ['format' => 'html'], methods: ['GET'])]
    public function showAction(string $slug, string $format): Response
    {
        $tags = [];
        $operator = 'or';
        if (str_contains($slug, '|')) {
            $slugs = explode('|', $slug);
            foreach ($slugs as $item) {
                $tag = $this->tagRepository->findOneBy(['slug' => $item]);

                if ($tag instanceof Tag) {
                    $tags[] = $tag;
                }
            }
        } else if (str_contains($slug, '&')) {
            $slugs = explode('&', $slug);
            $operator = 'and';
            foreach ($slugs as $item) {
                $tag = $this->tagRepository->findOneBy(['slug' => $item]);

                if ($tag instanceof Tag) {
                    $tags[] = $tag;
                }
            }
        } else {
            $tag = $this->tagRepository->findOneBy(['slug' => $slug]);

            if ($tag instanceof Tag) {
                $tags[] = $tag;
            }
        }

        if (count($tags) == 0) {
            throw $this->createNotFoundException('Unable to find tag entity.');
        }

        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $entities = null;
        if ($operator == 'and') {
            $sql = <<<EOF
SELECT * FROM events AS e
WHERE id IN (
WITH events_on_tags AS (
  SELECT events_id, array_agg(tags_id) as tags
  FROM events2tags
  GROUP BY events_id
)
SELECT events_id FROM events_on_tags
WHERE tags @> array[@tags@]
)
AND e.startdate >= :startdate
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

            $entities = $query->getResult();

        } else {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select(array('e'))
                ->from('App\Entity\Event', 'e')
                ->where('e.startdate >= :startdate')
                ->orderBy('e.startdate')
                ->setParameter('startdate', $now);

            $qb->join('e.tags', 't', 'WITH', $qb->expr()->in('t.id', array_reduce($tags, function ($carry, $item) {
                if (strlen($carry) == 0) {
                    return $item->getId();
                } else {
                    return $carry . ',' . $item->getId();
                }
            })));
            $entities = $qb->getQuery()->execute();
        }

        if ($format == 'ics') {
            $vcalendar = new VObject\Component\VCalendar();

            foreach ($entities as $entity) {
                $vcalendar->add('VEVENT',$entity->ConvertToCalendarEvent());
            }

            $response = new Response($vcalendar->serialize());
            $response->headers->set('Content-Type', 'text/calendar');

            return $response;
        } else {
            return $this->render('event/index.html.twig', [
                    'entities' => $entities,
                    'tags' => $tags,
                    'operator' => $operator,
                ]
            );
        }
    }

    #[Route('/',
        name: 'tag_list_json',
        methods: ['GET'],
        condition: "request.headers.get('Accept') == 'application/json'",
    )]
    public function indexAction(#[MapQueryParameter] ?string $q = ''): Response
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select(['t'])
            ->from('App\Entity\Tag', 't');
        
        if (!empty($q)) {
            $qb->where('t.name LIKE :tag')
                ->setParameter('tag', sprintf('%%%s%%', strtolower($q)));
        }
        
        $qb->orderBy('t.name');
        $entities = $qb->getQuery()->execute();

        $tags = [];
        foreach($entities as $tag) {
            /** @var Tag $tag */
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        $retval = [
            'success' => true,
            'results' => $tags,
        ];

        $response = new Response(json_encode($retval));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('/',
        name: 'tag_list',
        methods: ['GET'],
        condition: "request.headers.get('Accept') != 'application/json'",
    )]
    public function indexActionNonJson(): Response
    {
        return $this->redirect('/');
    }
}
