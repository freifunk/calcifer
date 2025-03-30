<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 28.07.14
 * Time: 21:00
 */

namespace App\Entity;

use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Mapping as ORM;

trait TagTrait
{
    public function getTags()
    {
        return $this->tags;
    }

    public function clearTags(): self
    {
        if ($this->tags !== null && $this->tags instanceof PersistentCollection) {
            $this->tags->clear();
        } elseif (is_array($this->tags)) {
            $this->tags = [];
        }
        return $this;
    }

    public function hasTag(Tag $tag)
    {
        if ($this->tags instanceof PersistentCollection) {
            return $this->tags->contains($tag);
        } elseif (is_array($this->tags)) {
            return in_array($tag, $this->tags);
        } else {
            return false;
        }

    }

    public function addTag(Tag $tag): self
    {
        if (!$this->hasTag($tag)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

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