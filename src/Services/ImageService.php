<?php

/** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */

namespace App\Services;

use GdImage;
use InvalidArgumentException;
use Lsr\Exceptions\FileException;
use RuntimeException;
use function imagecreatefromgif;
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromwebp;

/**
 *
 */
readonly class ImageService
{
    /**
     * @param  int[]  $sizes
     */
    public function __construct(
      public array $sizes = [
        1000,
        800,
        500,
        400,
        300,
        200,
        150,
      ]
    ) {}

    /**
     * @param  string  $file
     *
     * @return void
     * @throws FileException
     */
    public function optimize(string $file) : void {
        if (!file_exists($file)) {
            throw new FileException('File doesn\'t exist - '.$file);
        }

        $type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $name = pathinfo($file, PATHINFO_FILENAME);
        $path = pathinfo($file, PATHINFO_DIRNAME).'/';

        $optimizedDir = $path.'optimized';
        if (!is_dir($optimizedDir) && !mkdir($optimizedDir) && !is_dir($optimizedDir)) {
            throw new FileException('Cannot create an optimized image directory - '.$file);
        }

        $image = match ($type) {
            'jpg', 'jpeg' => imagecreatefromjpeg($file),
            'png'         => imagecreatefrompng($file),
            'gif'         => imagecreatefromgif($file),
            'webp'        => imagecreatefromwebp($file),
            default => throw new RuntimeException('Invalid image type: '.$type),
        };

        if ($image === false) {
            /* Create a black image */
            $image = imagecreatetruecolor(150, 30);

            if ($image === false) {
                throw new RuntimeException('Cannot create a GD image');
            }

            $bgc = imagecolorallocate($image, 255, 255, 255);
            $tc = imagecolorallocate($image, 0, 0, 0);

            if ($bgc === false || $tc === false) {
                throw new RuntimeException('Error while allocating GD image color');
            }

            imagefilledrectangle($image, 0, 0, 150, 30, $bgc);

            /* Output an error message */
            imagestring($image, 1, 5, 5, 'Error loading '.$file, $tc);
            //throw new RuntimeException('Failed to read image - '.$file);
        }

        if ($type !== 'webp') {
            imagewebp($image, $optimizedDir.'/'.$name.'.webp');
        }

        $originalWidth = imagesx($image);

        foreach ($this->getSizes() as $size) {
            if ($originalWidth < $size) {
                continue;
            }

            $resized = $this->resize($image, $size);

            if (!$resized) {
                continue;
            }

            $resizedFileName = $optimizedDir.'/'.$name.'x'.$size.'.'.$type;
            match ($type) {
                'jpg', 'jpeg' => imagejpeg($resized, $resizedFileName),
                'png'         => imagepng($resized, $resizedFileName),
                'gif'         => imagegif($resized, $resizedFileName),
                default => null,
            };

            imagewebp($resized, $optimizedDir.'/'.$name.'x'.$size.'.webp');
        }

    }

    /**
     * @return int[]
     */
    public function getSizes() : array {
        return $this->sizes;
    }

    /**
     * @param  GdImage  $image
     * @param  int|null  $width
     * @param  int|null  $height
     *
     * @return GdImage|false
     */
    public function resize(GdImage $image, ?int $width = null, ?int $height = null) : GdImage | false {
        if ($width === null && $height === null) {
            throw new InvalidArgumentException('At least 1 argument $width or $height must be set.');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if ($width === null) {
            $width = (int) ceil($originalWidth * $height / $originalHeight);

            $out = imagecreatetruecolor($width, $height);
            imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
            return $out;
        }

        if ($height === null) {
            $height = (int) ceil($originalHeight * $width / $originalWidth);

            $out = imagecreatetruecolor($width, $height);
            imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
            return $out;
        }

        $ratio1 = $originalWidth / $originalHeight;
        $ratio2 = $width / $height;

        $out = imagecreatetruecolor($width, $height);

        if ($ratio1 === $ratio2) {
            imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
            return $out;
        }

        $srcX = 0;
        $srcY = 0;

        if ($ratio1 > $ratio2) {
            $resizedWidth = $originalWidth * $height / $originalHeight;
            $srcX = ($resizedWidth - $width) / 2;
        }
        else {
            $resizedHeight = $originalHeight * $width / $originalWidth;
            $srcY = ($resizedHeight - $height) / 2;
        }


        imagecopyresized($out, $image, 0, 0, $srcX, $srcY, $width, $height, $originalWidth, $originalHeight);
        return $out;
    }
}
