<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Service\TagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

use Sabre\VObject;

#[Route('/tags')]
class TagController extends AbstractController
{

    public function __construct(
        private readonly TagService $tagService
    ) {}
    
    #[Route('/{slug}.{format}', name: 'tag_show', defaults: ['format' => 'html'], methods: ['GET'])]
    public function showAction(string $slug, string $format): Response
    {
        $result = $this->tagService->findTagsBySlugString($slug);
        $tags = $result['tags'];
        $operator = $result['operator'];

        if (count($tags) == 0) {
            throw $this->createNotFoundException('Unable to find tag entity.');
        }

        // Lade die entsprechenden Events basierend auf den gefundenen Tags
        $entities = $operator === 'and'
            ? $this->tagService->findEventsWithTagsAND($tags)
            : $this->tagService->findEventsWithTagsOR($tags);

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
        $entities = $this->tagService->findTagsLike($q);

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
