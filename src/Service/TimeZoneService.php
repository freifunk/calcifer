<?php

namespace App\Service;

use DateTimeZone;

/**
 * Service für die Zeitzonenverwaltung
 */
class TimeZoneService
{
    private string $defaultTimeZone;

    /**
     * Konstruktor mit konfigurierbarer Standardzeitzone
     */
    public function __construct(string $defaultTimeZone = 'Europe/Berlin')
    {
        $this->defaultTimeZone = $defaultTimeZone;
    }

    /**
     * Gibt die Standardzeitzone als String zurück
     */
    public function getDefaultTimeZoneName(): string
    {
        return $this->defaultTimeZone;
    }

    /**
     * Gibt die Standardzeitzone als DateTimeZone-Objekt zurück
     */
    public function getDefaultTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->defaultTimeZone);
    }

    /**
     * Setzt die Standardzeitzone
     */
    public function setDefaultTimeZone(string $timeZone): self
    {
        $this->defaultTimeZone = $timeZone;
        return $this;
    }
} 