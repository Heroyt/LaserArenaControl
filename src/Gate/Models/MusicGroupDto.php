<?php

namespace App\Gate\Models;

use App\Models\DataObjects\Image;
use App\Models\MusicMode;

class MusicGroupDto
{
    /** @var MusicMode[] */
    public array $music = [];
    private ?Image $icon = null;
    private ?Image $backgroundImage = null;

    public function __construct(
        public string $name,
    ) {
    }

    public function getIcon(): ?Image {
        if (!isset($this->icon)) {
            foreach ($this->music as $music) {
                if (isset($music->icon)) {
                    $this->icon = $music->getIcon();
                    return $this->icon;
                }
            }
        }
        return $this->icon;
    }

    public function getBackgroundImage(): ?Image {
        if (!isset($this->backgroundImage)) {
            foreach ($this->music as $music) {
                if (isset($music->backgroundImage)) {
                    $this->backgroundImage = $music->getBackgroundImage();
                    return $this->backgroundImage;
                }
            }
        }
        return $this->backgroundImage;
    }
}
