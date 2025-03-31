<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use function is_float;

/**
 * Event-Entität für Veranstaltungen
 */
#[ORM\Table(name: "events")]
#[ORM\Entity(repositoryClass: "App\Repository\EventRepository")]
class Event extends BaseEntity
{
    use TagTrait;

    /**
     * Konstruktor für neue Event-Instanzen
     */
    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    /**
     * Startdatum und -uhrzeit des Events
     */
    #[ORM\Column(name: "startdate", type: "datetimetz")]
    protected DateTime $startdate;

    /**
     * Enddatum und -uhrzeit des Events (optional)
     */
    #[ORM\Column(name: "enddate", type: "datetimetz", nullable: true)]
    protected ?DateTime $enddate = null;

    /**
     * Kurze Zusammenfassung/Titel des Events
     */
    #[ORM\Column(name: "summary", type: "string", length: 255)]
    protected string $summary = "";

    /**
     * Ausführliche Beschreibung des Events (optional)
     */
    #[ORM\Column(name: "description", type: "text", nullable: true)]
    protected ?string $description = null;

    /**
     * ID des zugehörigen Ortes (optional)
     */
    #[ORM\Column(name: "locations_id", type: "integer", nullable: true)]
    protected ?int $locations_id = null;

    /**
     * Zugehöriger Ort (Location-Entität)
     */
    #[ORM\ManyToOne(targetEntity: "Location")]
    #[ORM\JoinColumn(name: "locations_id", referencedColumnName: "id")]
    protected ?Location $location = null;

    /**
     * URL zur externen Webseite des Events (optional)
     */
    #[ORM\Column(name: "url", type: "string", length: 255, nullable: true)]
    protected ?string $url = null;

    /**
     * Verknüpfte Tags des Events
     * 
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: "Tag")]
    #[ORM\JoinTable(name: "events2tags",
        joinColumns: [new ORM\JoinColumn(name: "events_id", referencedColumnName: "id")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "tags_id", referencedColumnName: "id")]
    )]
    protected Collection $tags;

    /**
     * Gibt das Startdatum und -uhrzeit zurück
     */
    public function getStartdate(): DateTime
    {
        return $this->startdate;
    }

    /**
     * Setzt das Startdatum und -uhrzeit
     */
    public function setStartdate(DateTime $startdate): self
    {
        $this->startdate = $startdate;

        return $this;
    }

    /**
     * Gibt das Enddatum und -uhrzeit zurück (kann null sein)
     */
    public function getEnddate(): ?DateTime
    {
        return $this->enddate;
    }

    /**
     * Setzt das Enddatum und -uhrzeit (kann null sein)
     */
    public function setEnddate(?DateTime $enddate): self
    {
        $this->enddate = $enddate;

        return $this;
    }

    /**
     * Gibt die Zusammenfassung/den Titel zurück
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Setzt die Zusammenfassung/den Titel
     */
    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Gibt die Beschreibung zurück (kann null sein)
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Setzt die Beschreibung (kann null sein)
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gibt die ID des zugehörigen Ortes zurück (kann null sein)
     */
    public function getLocationsId(): ?int
    {
        return $this->locations_id;
    }

    /**
     * Setzt die ID des zugehörigen Ortes (kann null sein)
     */
    public function setLocationsId(?int $locations_id): self
    {
        $this->locations_id = $locations_id;

        return $this;
    }

    /**
     * Gibt die URL zur externen Webseite zurück (kann null sein)
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Setzt die URL zur externen Webseite (kann null sein)
     */
    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Gibt die verknüpften Tags zurück
     * 
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Setzt die verknüpften Tags
     * 
     * @param Collection<int, Tag> $tags
     */
    public function setTags(Collection $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Setzt den zugehörigen Ort (kann null sein)
     */
    public function setLocation(?Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Gibt den zugehörigen Ort zurück (kann null sein)
     */
    public function getLocation(): ?Location
    {
        return $this->location;
    }

    /**
     * Prüft ob das Event gültig ist und gibt ein Array mit Fehlermeldungen zurück
     * 
     * @return array<string, string> Leeres Array wenn gültig, ansonsten Fehlermeldungen
     */
    public function getValidationResult(): array
    {
        $errors = [];
        if (!is_null($this->enddate) && $this->enddate < $this->startdate) {
            $errors['enddate'] = 'Bitte setze ein Enddatum das nach dem Startdatum ist.';
        }
        if (strlen($this->summary) == 0) {
            $errors['summary'] = 'Bitte gebe eine Zusammenfassung an.';
        }
        return $errors;
    }

    /**
     * Prüft ob das Event gültig ist und gibt ein Array mit Fehlern zurück
     * 
     * @return array<string, string> Leeres Array wenn gültig, ansonsten Fehlermeldungen
     */
    public function isValid(): array
    {
        return $this->getValidationResult();
    }

    /**
     * Gibt ein formatiertes Datum zurück
     * 
     * Format: "YYYY-MM-DD HH:MM — HH:MM" oder "YYYY-MM-DD HH:MM — YYYY-MM-DD HH:MM"
     */
    public function getFormatedDate(): string
    {
        $retval = $this->startdate->format('Y-m-d H:i');
        if (!is_null($this->enddate)) {
            $retval .= " — ";
            if ($this->startdate->format('Y-m-d') == $this->enddate->format('Y-m-d')) {
                $retval .= $this->enddate->format('H:i');
            } else {
                $retval .= $this->enddate->format('Y-m-d H:i');
            }
        }
        return $retval;
    }

    /**
     * Konvertiert das Event in ein Format für Kalender-Exporte
     * 
     * @return array<string, mixed> Array mit Kalender-Eigenschaften
     */
    public function convertToCalendarEvent(): array
    {
        $categories = [];
        foreach($this->tags as $tag) {
            $categories[] = $tag->getName();
        }

        if (array_key_exists('HTTP_HOST',$_SERVER)) {
            $uid = "https://{$_SERVER['HTTP_HOST']}/termine/{$this->getSlug()}";
        } else {
            $uid = "https://localhost/termine/{$this->getSlug()}";
        }

        $event = [
            'SUMMARY' => $this->summary,
            'DTSTART' => $this->startdate,
            'DESCRIPTION' => $this->description,
            'URL' => $this->getUrl(),
            'CATEGORIES' => $categories,
            'UID' => $uid,
        ];
        if (!is_null($this->enddate))
            $event["DTEND"] = $this->getEnddate();

        if ($this->location instanceof Location) {
            $event["LOCATION"] = $this->location->getName();
            if (is_float($this->location->getLon()) && is_float($this->location->getLat())) {
                $event["GEO"] = [$this->location->getLat(), $this->location->getLon()];
            }
        }
        if (!array_key_exists('HTTP_HOST',$_SERVER)) {
            $dtstamp = new DateTime();
            $dtstamp->setDate(2016,06,27);
            $dtstamp->setTime(0,0,0);
            $event['DTSTAMP'] = $dtstamp;
        }

        return $event;
    }
}
