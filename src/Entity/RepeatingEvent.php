<?php

namespace App\Entity;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use enko\RelativeDateParser\RelativeDateParser;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;

#[ORM\Table(name: "repeating_events")]
#[ORM\Entity]
class RepeatingEvent extends BaseEntity
{
    use TagTrait;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    #[ORM\Column(name: "nextdate", type: "datetimetz")]
    protected DateTimeInterface $nextdate;

    #[ORM\Column(name: "duration", type: "integer", nullable: true)]
    protected ?int $duration = null;

    #[ORM\Column(name: "repeating_pattern", type: "string", length: 255)]
    protected string $repeatingPattern = '';

    #[ORM\Column(name: "summary", type: "string", length: 255)]
    protected string $summary;

    #[ORM\Column(name: "description", type: "text", nullable: true)]
    protected ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: "locations_id", referencedColumnName: "id")]
    protected ?Location $location = null;

    #[ORM\Column(name: "url", type: "string", length: 255, nullable: true)]
    protected ?string $url = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: "repeating_events2tags",
        joinColumns: [new ORM\JoinColumn(name: "repeating_events_id", referencedColumnName: "id")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "tags_id", referencedColumnName: "id")]
    )]
    protected Collection $tags;

    public function getNextdate(): DateTimeInterface
    {
        return $this->nextdate;
    }

    public function setNextdate(DateTimeInterface $nextdate): self
    {
        $this->nextdate = $nextdate;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

     public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
         return $this;
    }

    public function getRepeatingPattern(): string
    {
        return $this->repeatingPattern;
    }

    public function setRepeatingPattern(string $repeating_pattern): self
    {
        $this->repeatingPattern = $repeating_pattern;
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

    /**
     * @return mixed
     */
    public function getLocation(): ?Location
    {
        return $this->location;
    }

     public function setLocation(?Location $location): self
    {
        $this->location = $location;
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



    public function isValid(): array
    {
        $errors = [];
        if ((is_null($this->nextdate)) && (strlen($this->nextdate) > 0)) {
            $errors['nextdate'] = "Bitte gebe einen nächsten Termin an.";
        }
        if ((is_null($this->getRepeatingPattern())) && (strlen($this->getRepeatingPattern()) > 0)) {
            $errors['repeating_pattern'] = "Bitte gebe ein gültiges Wiederholungsmuster an.";
        } else {
            $nextdate = $this->getNextdate();
            if ($nextdate instanceof DateTime) {
                $nextdate->setTimezone(Event::getDefaultTimeZone());
                $this->setNextdate($nextdate);
            }
            try {
                $parser = new RelativeDateParser($this->getRepeatingPattern(), $this->getNextdate(), 'de');
            } catch (Exception $e) {
                $errors['repeating_pattern'] = "Bitte gebe ein gültiges Wiederholungsmuster an.";
            }
        }
        if ((is_null($this->summary)) && (strlen($this->summary) > 0)) {
            $errors['summary'] = "Bitte gebe eine Zusammenfassung an.";
        }
        return $errors;
    }
}