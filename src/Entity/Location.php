<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "locations")]
class Location extends BaseEntity
{
    #[ORM\Column(name: "name", type: "string", length: 255)]
    protected string $name;

    #[ORM\Column(name: "description", type: "text", nullable: true)]
    protected ?string $description;

    #[ORM\Column(name: "streetaddress", type: "string", length: 255, nullable: true)]
    protected ?string $streetaddress;

    #[ORM\Column(name: "streetnumber", type: "string", length: 255, nullable: true)]
    protected ?string $streetnumber;

    #[ORM\Column(name: "zipcode", type: "string", length: 255, nullable: true)]
    protected ?string $zipcode;

     #[ORM\Column(name: "city", type: "string", length: 255, nullable: true)]
     protected ?string $city;

     #[ORM\Column(name: "lon", type: "float", nullable: true)]
     protected ?float $lon;

     #[ORM\Column(name: "lat", type: "float", nullable: true)]
     protected ?float $lat;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getStreetaddress(): ?string
    {
        return $this->streetaddress;
    }

    public function setStreetaddress(?string $streetaddress): self
    {
        $this->streetaddress = $streetaddress;

        return $this;
    }

    public function getStreetnumber(): ?string
    {
        return $this->streetnumber;
    }

    public function setStreetnumber(?string $streetnumber): self
    {
        $this->streetnumber = $streetnumber;

        return $this;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function setZipcode(?string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function hasAddress() {
        return ((strlen($this->streetaddress) > 0) && (strlen($this->city)));
    }


}
