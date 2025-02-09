<?php

namespace App\Models;

use App\Core\App;
use App\Models\DataObjects\Image;
use Lsr\Lg\Results\Interface\Models\MusicModeInterface;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\Orm\Attributes\PrimaryKey;
use RuntimeException;

#[PrimaryKey('id_music')]
class MusicMode extends BaseModel implements MusicModeInterface
{
    public const string TABLE = 'music';

    #[Required]
    #[StringLength(min: 1, max: 80)]
    public string $name;
    public ?string $group = null;
    #[Required]
    public string $fileName = '';
    public ?string $introFile = null;
    public ?string $endingFile = null;

    public ?string $armedFile = null;

    public int $order = 0;
    /** @var int Preview start time in seconds */
    public int $previewStart = 0;
    /** @var bool If the music mode should be synchronized and shown publically */
    public bool $public = true;

    public ?string $backgroundImage = null;
    public ?string $icon = null;

    private ?Image $backgroundImageObject = null;
    private ?Image $iconObject = null;

    /**
     * @return MusicMode[]
     */
    public static function getAll() : array {
        return self::query()->orderBy('order')->get();
    }

    public function getMediaUrl() : string {
        return str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->fileName);
    }

    public function getIntroFileName() : ?string {
        return $this->introFile === null ? null : basename($this->introFile);
    }

    public function getArmedFileName() : ?string {
        return $this->armedFile === null ? null : basename($this->armedFile);
    }

    public function getEndingFileName() : ?string {
        return $this->endingFile === null ? null : basename($this->endingFile);
    }

    public function getIntroMediaUrl() : ?string {
        return $this->introFile === null ? null : str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->introFile);
    }

    public function getEndingMediaUrl() : ?string {
        return $this->endingFile === null ? null :
          str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->endingFile);
    }

    public function getArmedMediaUrl() : ?string {
        return $this->armedFile === null ? null : str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->armedFile);
    }

    public function setPreviewStartFromFormatted(string $formatted) : MusicMode {
        $this->previewStart = 0;
        /** @var int[] $exploded */
        $exploded = array_reverse(array_map(static fn(string $part) => (int) trim($part), explode(':', $formatted)));
        $multiplier = 1;
        foreach ($exploded as $part) {
            $this->previewStart += $part * $multiplier;
            $multiplier *= 60;
        }
        return $this;
    }

    public function getPreviewUrl() : string {
        return str_replace(ROOT, App::getInstance()->getBaseUrl(), $this->getPreviewFileName());
    }

    public function getPreviewFileName() : string {
        $extension = pathinfo($this->fileName, PATHINFO_EXTENSION);
        return str_replace('.'.$extension, '.preview.mp3', $this->fileName);
    }

    public function trimMediaToPreview() : string {
        $outFile = $this->getPreviewFileName();
        $out = exec(
          'ffmpeg -i "'.$this->fileName.'" -ss '.$this->getFormattedPreviewStart(
          ).' -t 0:30 -acodec copy -y "'.$outFile.'" 2>&1',
          $output,
          $returnCode
        );
        if ($out === false || $returnCode !== 0) {
            throw new RuntimeException('FFMPEG failed to trim the preview ('.$returnCode.'). '.implode(';', $output));
        }
        return $outFile;
    }

    public function getFormattedPreviewStart(int $offset = 0) : string {
        $start = $this->previewStart + $offset;
        return floor($start / 60).':'.str_pad((string) ($start % 60), 2, '0', STR_PAD_LEFT);
    }

    public function getBackgroundImage() : ?Image {
        if (!isset($this->backgroundImageObject) && isset($this->backgroundImage)) {
            $this->backgroundImageObject = new Image($this->backgroundImage);
        }
        return $this->backgroundImageObject;
    }

    public function getIcon() : ?Image {
        if (!isset($this->iconObject) && isset($this->icon)) {
            $this->iconObject = new Image($this->icon);
        }
        return $this->iconObject;
    }
}
