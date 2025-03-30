<?php

namespace App\Entity;



use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "events")]
#[ORM\Entity]
class Event extends BaseEntity
{
    use TagTrait;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    #[ORM\Column(name: "startdate", type: "datetimetz")]
    protected DateTime $startdate;

    #[ORM\Column(name: "enddate", type: "datetimetz", nullable: true)]
    protected ?DateTime $enddate = null;

    #[ORM\Column(name: "summary", type: "string", length: 255)]
    protected string $summary = "";

    #[ORM\Column(name: "description", type: "text", nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(name: "locations_id", type: "integer", nullable: true)]
    protected ?int $locations_id = null;

     #[ORM\ManyToOne(targetEntity: "Location")]
     #[ORM\JoinColumn(name: "locations_id", referencedColumnName: "id")]
     protected ?Location $location = null;


     #[ORM\Column(name: "url", type: "string", length: 255, nullable: true)]
     protected ?string $url = null;


     #[ORM\ManyToMany(targetEntity: "Tag")]
     #[ORM\JoinTable(name: "events2tags",
         joinColumns: [new ORM\JoinColumn(name: "events_id", referencedColumnName: "id")],
         inverseJoinColumns: [new ORM\JoinColumn(name: "tags_id", referencedColumnName: "id")]
     )]
     protected Collection $tags;

    public function getStartdate(): DateTime
    {
        return $this->startdate;
    }

    public function setStartdate(DateTime $startdate): self
    {
        $this->startdate = $startdate;

        return $this;
    }

    public function getEnddate(): ?DateTime
    {
        return $this->enddate;
    }

    public function setEnddate(?DateTime $enddate): self
    {
        $this->enddate = $enddate;

        return $this;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLocationsId(): ?int
    {
        return $this->locations_id;
    }

    public function setLocationsId(?int $locations_id): self
    {
        $this->locations_id = $locations_id;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function setTags(Collection $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function setLocation(?Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

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
     * @return array Leeres Array wenn gültig, ansonsten Fehlermeldungen
     */
    public function isValid(): array
    {
        return $this->getValidationResult();
    }

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
            if (\is_float($this->location->getLon()) && \is_float($this->location->getLat())) {
                $event["GEO"] = [$this->location->getLat(), $this->location->getLon()];
            }
        }
        if (!array_key_exists('HTTP_HOST',$_SERVER)) {
            $dtstamp = new \DateTime();
            $dtstamp->setDate(2016,06,27);
            $dtstamp->setTime(0,0,0);
            $event['DTSTAMP'] = $dtstamp;
        }

        return $event;
    }
}
