<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tags")]
#[ORM\Entity(repositoryClass: "App\Repository\TagRepository")]
class Tag extends BaseEntity
{
    #[ORM\Column(name: "name", type: "string", length: 255)]
    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
