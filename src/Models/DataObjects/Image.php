<?php

namespace App\Models\DataObjects;

use App\Services\ImageService;
use Lsr\Core\App;
use Lsr\Exceptions\FileException;
use RuntimeException;

class Image
{
    private string $name;
    private string $path;
    private ?string $type = null;

    private array $optimized = [];

    public function __construct(
        public readonly string $image
    ) {
        if (!file_exists($image)) {
            throw new RuntimeException('Image doesn\'t exist.');
        }

        $this->name = pathinfo($this->image, PATHINFO_FILENAME);
        $this->path = pathinfo($this->image, PATHINFO_DIRNAME) . '/';
    }


    public function getSize(int $size): string {
        $optimized = $this->getOptimized();
        $index = $size . '-webp';
        if (isset($optimized[$index])) {
            return $optimized[$index];
        }
        $index = (string) $size;
        return $optimized[$index] ?? $optimized['webp'] ?? $optimized['original'];
    }

    /**
     * @return array<string,string>
     */
    public function getOptimized(): array {
        if (!empty($this->optimized)) {
            return $this->optimized;
        }

        $images = [
          'original' => $this->getUrl(),
        ];

        $this->findOptimizedImages($images);

        if (count($images) === 1) {
            try {
                $this->optimize();
                $this->findOptimizedImages($images);
            } catch (FileException $e) {
            }
        }

        $this->optimized = $images;
        return $this->optimized;
    }

    public function getUrl(): string {
        return $this->pathToUrl($this->image);
    }

    private function pathToUrl(string $file): string {
        $path = explode('/', str_replace(ROOT, '', $file));
        $index = count($path) - 1;
        $path[$index] = urlencode($path[$index]);
        return App::getInstance()->getBaseUrl() . implode('/', $path);
    }

    /**
     * @param  array<string,string>  $images
     *
     * @return void
     */
    private function findOptimizedImages(array &$images): void {
        if ($this->getType() === 'svg') {
            return;
        }
        $webP = $this->getWebp();
        if (isset($webP)) {
            $images['webp'] = $webP;
        }

        $optimizedDir = $this->path . 'optimized/';

        /** @var ImageService $imageService */
        $imageService = App::getService('image');
        foreach ($imageService->getSizes() as $size) {
            $file = $optimizedDir . $this->name . 'x' . $size . '.' . $this->getType();
            if (file_exists($file)) {
                $images[(string) $size] = $this->pathToUrl($file);
            }
            $file = $optimizedDir . $this->name . 'x' . $size . '.webp';
            if (file_exists($file)) {
                $images[$size . '-webp'] = $this->pathToUrl($file);
            }
        }
    }

    public function getType(): string {
        if (!isset($this->type)) {
            // Default to using provided extension
            $this->type = strtolower(pathinfo($this->image, PATHINFO_EXTENSION));
            if (function_exists('exif_imagetype')) {
                /** @var int|false $type */
                $type = exif_imagetype($this->image);
                if ($type !== false) {
                    $this->type = match ($type) {
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_GIF  => 'gif',
                        IMAGETYPE_PNG  => 'png',
                        IMAGETYPE_WEBP => 'webp',
                        default        => $this->type,
                    };
                }
            }
        }
        return $this->type;
    }

    public function getWebp(): ?string {
        if ($this->getType() === 'svg') {
            return null;
        }
        if ($this->getType() === 'webp') {
            return $this->pathToUrl($this->image);
        }
        $webp = $this->path . 'optimized/' . $this->name . '.webp';
        if (!file_exists($webp)) {
            return null;
        }
        return $this->pathToUrl($webp);
    }

    /**
     * @return void
     * @throws FileException
     */
    public function optimize(): void {
        // Do not optimize SVG
        if ($this->getType() === 'svg') {
            return;
        }

        /** @var ImageService $imageService */
        $imageService = App::getService('image');
        $imageService->optimize($this->image);
    }

    public function getMimeType(): string {
        if (function_exists('exif_imagetype') && function_exists('image_type_to_mime_type')) {
            /** @var int|false $type */
            $type = exif_imagetype($this->image);
            if ($type !== false) {
                return image_type_to_mime_type($type);
            }
        }
        return match ($this->getType()) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    public function getPath(): string {
        return $this->image;
    }
}
