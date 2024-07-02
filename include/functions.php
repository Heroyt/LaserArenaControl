<?php
/**
 * @file      functions.php
 * @brief     Main functions
 * @details   File containing all main functions for the app.
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Core\App;
use App\Models\DataObjects\Image;
use App\Services\ImageService;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Add a trailing slash to a string (file/directory path)
 *
 * @param string $string
 *
 * @return string
 */
function trailingUnSlashIt(string $string) : string {
	if (substr($string, -1) === DIRECTORY_SEPARATOR) {
		$string = substr($string, 0, -1);
	}
	return $string;
}

function getImageSrcSet(Image | string $image, bool $includeAllSizes = true) : string {
	if (is_string($image)) {
		$image = new Image($image);
	}

	$versions = $image->getOptimized();

	$srcSet = [];

	if ($includeAllSizes) {
		/** @var ImageService $imageService */
		$imageService = App::getService('image');
		foreach (array_reverse($imageService->getSizes()) as $size) {
			$index = $size.'-webp';
			if (isset($versions[$index])) {
				$srcSet[] = $versions[$index].' '.$size.'w';
				continue;
			}
			$index = (string) $size;
			if (isset($versions[$index])) {
				$srcSet[] = $versions[$index].' '.$size.'w';
			}
		}
	}

	if (isset($versions['webp'])) {
		$srcSet[] = $versions['webp'];
	}
	else {
		$srcSet[] = $versions['original'];
	}

	return implode(',', $srcSet);
}

function jsonSerialize($data) : string {
    $normalizerContext = [
      AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object, string $format, array $context) {
          if (property_exists($object, 'code')) {
              return $object->code;
          }
          if (property_exists($object, 'id')) {
              return $object->id;
          }
          if (property_exists($object, 'name')) {
              return $object->name;
          }
          return null;
      },
    ];
    $serializer = new Serializer(
      [
        new DateTimeNormalizer(),
        new BackedEnumNormalizer(),
        new JsonSerializableNormalizer(defaultContext: $normalizerContext),
        new ObjectNormalizer(defaultContext: $normalizerContext),
      ], [
        new JsonEncoder(
          defaultContext: [
                            JsonDecode::ASSOCIATIVE => true,
                            JsonEncode::OPTIONS     => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
                          ]
        ),
      ],
    );
    return $serializer->serialize($data, 'json');
}