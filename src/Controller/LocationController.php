<?php

namespace App\Controller;

use App\Service\LocationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Sabre\VObject;

#[Route('/orte')]
class LocationController extends AbstractController
{

    public function __construct(
        private readonly LocationService $locationService
    ){}
    
    #[Route('/{slug}.{format}', name: 'location_show', defaults: ['format' => 'html'], methods: ['GET'])]
    public function showAction(string $slug, string $format): Response
    {
        $location = $this->locationService->findBySlug($slug);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        $entities = $this->locationService->findUpcomingEventsForLocation($location);

        if ($format === 'ics') {
            $vcalendar = new VObject\Component\VCalendar();

            foreach ($entities as $entity) {
                $vcalendar->add('VEVENT', $entity->ConvertToCalendarEvent());
            }

            $response = new Response($vcalendar->serialize());
            $response->headers->set('Content-Type', 'text/calendar');
            return $response;
        } else {
            return $this->render('event/index.html.twig', [
                'entities' => $entities,
                'location' => $location,
                'tags' => []
            ]);
        }
    }

    #[Route('/{slug}/bearbeiten', name: 'location_edit', methods: ['GET'])]
    public function editAction(string $slug): Response
    {
        $location = $this->locationService->findBySlug($slug);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        return $this->render('location/edit.html.twig', [
            'entity' => $location
        ]);
    }

    #[Route('/{slug}/bearbeiten', name: 'location_update', methods: ['POST'])]
    public function updateAction(Request $request, string $slug): Response
    {
        $location = $this->locationService->findBySlug($slug);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        $data = [
            'name' => $request->get('name'),
            'streetaddress' => $request->get('streetaddress'),
            'streetnumber' => $request->get('streetnumber'),
            'zipcode' => $request->get('zipcode'),
            'city' => $request->get('city'),
            'description' => $request->get('description'),
            'geocords' => $request->get('geocords')
        ];

        $result = $this->locationService->updateLocation($location, $data);
        
        if (!$result['success']) {
            $this->addFlash('error', $result['message']);
            return $this->redirectToRoute('location_edit', ['slug' => $location->getSlug()]);
        }

        return $this->redirectToRoute('location_show', ['slug' => $result['location']->getSlug()]);
    }

    #[Route('/',
        name: 'location_list_json',
        methods: ['GET'],
        condition: "request.headers.get('Accept') == 'application/json'",
    )]
    public function indexAction(#[MapQueryParameter] ?String $q = null): Response
    {
        $locations = $this->locationService->findLocationsLike($q);
        $locationsArray = $this->locationService->convertLocationsToArray($locations);

        $response = new Response(json_encode([
            'success' => true,
            'results' => $locationsArray,
        ]));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('/', methods: ['GET'])]
    public function indexActionNonJson(): Response
    {
        return $this->redirect('/');
    }
}