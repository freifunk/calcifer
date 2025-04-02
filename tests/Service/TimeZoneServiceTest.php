<?php

namespace App\Tests\Service;

use App\Service\TimeZoneService;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class TimeZoneServiceTest extends TestCase
{
    public function testDefaultTimeZone(): void
    {
        $service = new TimeZoneService();
        $this->assertEquals('Europe/Berlin', $service->getDefaultTimeZoneName());
        $this->assertEquals(new DateTimeZone('Europe/Berlin'), $service->getDefaultTimeZone());
    }
    
    public function testCustomTimeZone(): void
    {
        $service = new TimeZoneService('America/New_York');
        $this->assertEquals('America/New_York', $service->getDefaultTimeZoneName());
        $this->assertEquals(new DateTimeZone('America/New_York'), $service->getDefaultTimeZone());
    }
    
    public function testSetTimeZone(): void
    {
        $service = new TimeZoneService();
        $service->setDefaultTimeZone('Asia/Tokyo');
        $this->assertEquals('Asia/Tokyo', $service->getDefaultTimeZoneName());
        $this->assertEquals(new DateTimeZone('Asia/Tokyo'), $service->getDefaultTimeZone());
    }
} 