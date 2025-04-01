<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A base class for all other entities
 */
#[ORM\MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = -1;

    #[ORM\Column(length: 255)]
    protected string $slug = '';

    /**
     * Returns the entity ID or null if not persisted yet
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the entity slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Sets the entity slug
     * 
     * @param string $slug The slug to set
     * @return self For method chaining
     */
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }
}