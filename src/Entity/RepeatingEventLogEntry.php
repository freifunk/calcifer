<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "repeating_events_log_entries")]
#[ORM\Entity]
class RepeatingEventLogEntry extends BaseEntity
{
    #[ORM\Column(name: "repeating_events_id", type: "integer", nullable: false)]
    protected int $repeatingEventsId;

    #[ORM\ManyToOne(targetEntity: "RepeatingEvent")]
    #[ORM\JoinColumn(name: "repeating_events_id", referencedColumnName: "id")]
    protected RepeatingEvent $repeatingEvent;

    #[ORM\Column(name: "events_id", type: "integer", nullable: false)]
    protected int $eventsId;

    #[ORM\ManyToOne(targetEntity: "Event")]
    #[ORM\JoinColumn(name: "events_id", referencedColumnName: "id")]
    protected Event $event;

    #[ORM\Column(name: "event_startdate", type: "datetimetz")]
    protected DateTimeInterface $eventStartdate;

    #[ORM\Column(name: "event_enddate", type: "datetimetz", nullable: true)]
    protected ?DateTimeInterface $eventEnddate = null;

    public function getRepeatingEventsId(): int
    {
        return $this->repeatingEventsId;
    }

    public function setRepeatingEventsId(int $repeatingEventsId): self
    {
        $this->repeatingEventsId = $repeatingEventsId;
        return $this;
    }

    public function getRepeatingEvent(): RepeatingEvent
    {
        return $this->repeatingEvent;
    }

    public function setRepeatingEvent(RepeatingEvent $repeatingEvent): self
    {
        $this->repeatingEvent = $repeatingEvent;
        return $this;
    }

    public function getEventsId(): int
    {
        return $this->eventsId;
    }

    public function setEventsId(int $eventsId): self
    {
        $this->eventsId = $eventsId;
        return $this;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getEventStartdate(): DateTimeInterface
    {
        return $this->eventStartdate;
    }

    public function setEventStartdate(DateTimeInterface $eventStartdate): self
    {
        $this->eventStartdate = $eventStartdate;
        return $this;
    }

    public function getEventEnddate(): ?DateTimeInterface
    {
        return $this->eventEnddate;
    }

    public function setEventEnddate(?DateTimeInterface $eventEnddate): self
    {
        $this->eventEnddate = $eventEnddate;
        return $this;
    }


}