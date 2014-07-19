<?php

namespace Hackspace\Bundle\CalciferBundle\Controller;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Hackspace\Bundle\CalciferBundle\Entity\Location;
use Hackspace\Bundle\CalciferBundle\Entity\Tag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Hackspace\Bundle\CalciferBundle\Entity\Event;
use Hackspace\Bundle\CalciferBundle\Form\EventType;
use Symfony\Component\HttpFoundation\Response;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Relationship\Attendee;
use Jsvrcek\ICS\Model\Relationship\Organizer;

use Jsvrcek\ICS\Utility\Formatter;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\CalendarExport;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Location controller.
 *
 * @Route("/orte")
 */
class LocationController extends Controller
{
    /**
     * Finds and displays a Event entity.
     *
     * @Route("/{slug}.{format}", name="location_show", defaults={"format" = "html"})
     * @Method("GET")
     * @Template("CalciferBundle:Event:index.html.twig")
     */
    public function showAction($slug,$format)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EntityRepository $repo */
        $repo = $em->getRepository('CalciferBundle:Location');

        /** @var Location $location */
        $location = $repo->findOneBy(['slug' => $slug]);

        if (!$location) {
            throw $this->createNotFoundException('Unable to find Location entity.');
        }

        $em = $this->getDoctrine()->getManager();

        $now = new \DateTime();
        $now->setTime(0,0,0);

        /** @var QueryBuilder $qb */
        $qb = $em->createQueryBuilder();
        $qb ->select(array('e'))
            ->from('CalciferBundle:Event', 'e')
            ->where('e.startdate >= :startdate')
            ->andWhere('e.locations_id = :location')
            ->orderBy('e.startdate')
            ->setParameter('startdate',$now)
            ->setParameter('location',$location->id);
        $entities = $qb->getQuery()->execute();

        if ($format == 'ics') {
            $calendar = new Calendar();
            $calendar->setProdId('-//My Company//Cool Calendar App//EN');

            foreach ($entities as $entity) {
                /** @var Event $entity */
                $event = new CalendarEvent();
                $event->setStart($entity->startdate);
                $event->setEnd($entity->enddate);
                $event->setSummary($entity->summary);
                $event->setDescription($entity->description);
                $location = new \Jsvrcek\ICS\Model\Description\Location();
                $location->setName($entity->getLocation()->name);
                $event->setLocations([$location]);
                $calendar->addEvent($event);
            }

            $calendarExport = new CalendarExport(new CalendarStream, new Formatter());
            $calendarExport->addCalendar($calendar);

            //output .ics formatted text
            $result = $calendarExport->getStream();

            $response = new Response($result);
            $response->headers->set('Content-Type', 'text/calendar');

            return $response;
        } else {
            return array(
                'entities' => $entities,
                'location' => $location,
            );
        }
    }
}
