<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Service\SluggerService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Location;
use League\CommonMark\CommonMarkConverter;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationRepository     $locationRepository,
        private readonly SluggerService        $sluggerService
    ){}
    #[Route('/{slug}.{format}', name: 'location_show', defaults: ['format' => 'html'], methods: ['GET'])]
    public function showAction(string $slug, string $format): Response
    {
        $location = $this->locationRepository->findOneBy(['slug' => $slug]);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(Event::class, 'e')
            ->where('e.startdate >= :startdate')
            ->andWhere('e.locations_id = :location')
            ->orderBy('e.startdate')
            ->setParameter('startdate', $now)
            ->setParameter('location', $location->getId());
        $entities = $qb->getQuery()->execute();

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
        $location = $this->locationRepository->findOneBy(['slug' => $slug]);

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
        $location = $this->locationRepository->findOneBy(['slug' => $slug]);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        if ($location->getName() !== $request->get('name')) {
            $newLocation = $this->locationRepository->findOneBy(['name' => $request->get('name')]);
            if (is_null($newLocation)) {
                $location->setName($request->get('name'));
                $location->setSlug($this->sluggerService->generateUniqueSlug($location->getName(), $this->locationRepository));
            } else {
                $this->addFlash('error', 'Ort mit diesem Namen existiert bereits.');
                return $this->redirectToRoute('location_edit', ['slug' => $location->getSlug()]);
            }
        }
        $location->setStreetAddress($request->get('streetaddress'));
        $location->setStreetNumber($request->get('streetnumber'));
        $location->setZipCode($request->get('zipcode'));
        $location->setCity($request->get('city'));
        $location->setDescription($request->get('description'));

        $latlon = explode(',', $request->get('geocords'));
        if (count($latlon) === 2) {
            $location->setLat($latlon[0]);
            $location->setLon($latlon[1]);
        }

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        return $this->redirectToRoute('location_show', ['slug' => $location->getSlug()]);
    }

    #[Route('/',
        methods: ['GET'],
        name: 'location_list_json',
        condition: "request.headers.get('Accept') == 'application/json'",
    )]
    public function indexAction(#[MapQueryParameter] ?String $q): Response
    {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('l')
                ->from(Location::class, 'l')
                ->where('LOWER(l.name) LIKE LOWER(:location)')
                ->orderBy('l.name')
                ->setParameter('location', sprintf('%%%s%%', $q));

            $entities = $qb->getQuery()->execute();

            $locations = array_map(function (Location $location) {
                $mdConverter = new CommonMarkConverter();
                return [
                    'id' => $location->getId(),
                    'name' => $location->getName(),
                    'description' => $location->getDescription() != null ? $mdConverter->convert($location->getDescription()): '',
                    'streetaddress' => $location->getStreetAddress(),
                    'streetnumber' => $location->getStreetNumber(),
                    'zipcode' => $location->getZipCode(),
                    'city' => $location->getCity(),
                    'lon' => $location->getLon(),
                    'lat' => $location->getLat(),
                ];
            }, $entities);

            $response = new Response(json_encode([
                'success' => true,
                'results' => $locations,
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