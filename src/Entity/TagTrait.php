<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 28.07.14
 * Time: 21:00
 */

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;

/**
 * Trait für Entitäten, die Tags haben
 */
trait TagTrait
{
    /**
     * Gibt alle Tags der Entität zurück
     *
     * @return Collection<int, Tag> Die Collection der Tags
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Entfernt alle Tags von der Entität
     * 
     * @return $this Für Method Chaining
     */
    public function clearTags(): self
    {
        if ($this->tags instanceof PersistentCollection) {
            $this->tags->clear();
        } else {
            $this->tags = new ArrayCollection();
        }
        return $this;
    }

    /**
     * Prüft, ob die Entität ein bestimmtes Tag hat
     * 
     * @param Tag $tag Das zu prüfende Tag
     * @return bool True, wenn die Entität das Tag enthält
     */
    public function hasTag(Tag $tag): bool
    {
        if ($this->tags instanceof PersistentCollection) {
            return $this->tags->contains($tag);
        } else {
            return false;
        }
    }

    /**
     * Fügt ein Tag zur Entität hinzu, wenn es noch nicht vorhanden ist
     * 
     * @param Tag $tag Das hinzuzufügende Tag
     * @return $this Für Method Chaining
     */
    public function addTag(Tag $tag): self
    {
        if (!$this->hasTag($tag)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    /**
     * Gibt alle Tags als kommagetrennten Text zurück
     * 
     * @return string|null Die Tags als kommagetrennter Text oder null, wenn keine Tags vorhanden sind
     */
    public function getTagsAsText(): ?string
    {
        if (count($this->tags) > 0) {
            $tags = [];
            foreach ($this->tags as $tag) {
                $tags[] = $tag->getName();
            }
            return implode(',', $tags);
        } else {
            return null;
        }
    }
} 