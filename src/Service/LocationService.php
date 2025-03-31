<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Location;
use App\Repository\LocationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;

class LocationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationRepository $repository,
        private readonly SluggerService $sluggerService
    ) {}

    /**
     * Findet einen Ort anhand des Slugs
     */
    public function findBySlug(string $slug): ?Location
    {
        return $this->repository->findOneBy(['slug' => $slug]);
    }
    
    /**
     * Findet zukünftige Veranstaltungen für einen Ort
     *
     * @return Event[]
     */
    public function findUpcomingEventsForLocation(Location $location): array
    {
        $now = new DateTime();
        $now->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(Event::class, 'e')
            ->where('e.startdate >= :startdate')
            ->andWhere('e.locations_id = :location')
            ->orderBy('e.startdate')
            ->setParameter('startdate', $now)
            ->setParameter('location', $location->getId());
            
        return $qb->getQuery()->execute();
    }
    
    /**
     * Aktualisiert einen Ort
     * 
     * @return array{success: bool, message: string, location: Location}
     */
    public function updateLocation(Location $location, array $data): array
    {
        $nameChanged = false;
        
        // Prüfe auf Namensänderung und ob der neue Name bereits existiert
        if ($location->getName() !== $data['name']) {
            $nameChanged = true;
            $newLocation = $this->repository->findOneBy(['name' => $data['name']]);
            if (!is_null($newLocation)) {
                return [
                    'success' => false,
                    'message' => 'Ort mit diesem Namen existiert bereits.',
                    'location' => $location
                ];
            }
            $location->setName($data['name']);
        }
        
        // Adressdaten aktualisieren
        $location->setStreetAddress($data['streetaddress'] ?? null);
        $location->setStreetNumber($data['streetnumber'] ?? null);
        $location->setZipCode($data['zipcode'] ?? null);
        $location->setCity($data['city'] ?? null);
        $location->setDescription($data['description'] ?? null);
        
        // Geokoordinaten aktualisieren
        if (isset($data['geocords'])) {
            $latlon = explode(',', $data['geocords']);
            if (count($latlon) === 2) {
                $location->setLat((float)$latlon[0]);
                $location->setLon((float)$latlon[1]);
            }
        }
        
        // Aktualisiere Slug bei Namensänderung
        if ($nameChanged) {
            $location->setSlug($this->sluggerService->generateUniqueSlug($location->getName(), $this->repository));
        }
        
        $this->entityManager->persist($location);
        $this->entityManager->flush();
        
        return [
            'success' => true,
            'message' => '',
            'location' => $location
        ];
    }
    
    /**
     * Sucht nach Orten anhand eines Suchbegriffs
     * 
     * @return Location[]
     */
    public function findLocationsLike(?string $searchTerm = ''): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l')
            ->from(Location::class, 'l')
            ->orderBy('l.name');
            
        if (!empty($searchTerm)) {
            $qb->where('LOWER(l.name) LIKE LOWER(:location)')
                ->setParameter('location', sprintf('%%%s%%', $searchTerm));
        }
            
        return $qb->getQuery()->execute();
    }
    
    /**
     * Konvertiert Location-Entities in ein Array für JSON-Ausgabe
     * 
     * @param Location[] $locations
     * @return array
     */
    public function convertLocationsToArray(array $locations): array
    {
        $mdConverter = new CommonMarkConverter();
        
        return array_map(function (Location $location) use ($mdConverter) {
            return [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'description' => $location->getDescription() != null ? $mdConverter->convert($location->getDescription()) : '',
                'streetaddress' => $location->getStreetAddress(),
                'streetnumber' => $location->getStreetNumber(),
                'zipcode' => $location->getZipCode(),
                'city' => $location->getCity(),
                'lon' => $location->getLon(),
                'lat' => $location->getLat(),
            ];
        }, $locations);
    }
    
    /**
     * Findet einen Ort anhand des Namens oder erstellt ihn, wenn er nicht existiert
     */
    public function findOrCreateLocation(string $name, ?string $lat = null, ?string $lon = null): Location
    {
        $name = trim($name);
        $location = $this->repository->findOneBy(['name' => $name]);
        
        if ($location) {
            // Wenn der Ort existiert, aktualisieren wir ggf. die Koordinaten
            $this->updateCoordinates($location, $lat, $lon);
            return $location;
        } else {
            // Wenn der Ort nicht existiert, erstellen wir ihn
            $location = new Location();
            $location->setName($name);
            $location->setSlug($this->sluggerService->generateUniqueSlug(strtolower($name), $this->repository));
            
            $this->updateCoordinates($location, $lat, $lon);
            
            $this->entityManager->persist($location);
            $this->entityManager->flush();
            
            return $location;
        }
    }
    
    /**
     * Aktualisiert die Koordinaten einer Location
     */
    private function updateCoordinates(Location $location, ?string $lat, ?string $lon): void
    {
        if (!empty($lat)) {
            // String zu Float konvertieren
            $location->setLat((float)$lat);
        }
        
        if (!empty($lon)) {
            // String zu Float konvertieren
            $location->setLon((float)$lon);
        }
    }
} 