<?php

namespace App\Helpers\Serializer\Normalizer;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function is_float;
use function is_int;
use function is_string;

/**
 * Extended default implementation of {@see \Symfony\Component\Serializer\Normalizer\DateTimeNormalizer} adding the
 * ability to denormalize array-encoded date time objects.
 *
 *  Normalizes an object implementing the {@see DateTimeInterface} to a date string.
 *  Denormalizes a date string to an instance of {@see DateTime} or {@see DateTimeImmutable}.
 */
final class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const string FORMAT_KEY = 'datetime_format';
    public const string TIMEZONE_KEY = 'datetime_timezone';
    public const string CAST_KEY = 'datetime_cast';
    private const array SUPPORTED_TYPES = [
      DateTimeInterface::class => true,
      DateTimeImmutable::class => true,
      DateTime::class => true,
    ];
    /**
     * @var array<string,mixed>
     */
    private array $defaultContext = [
      self::FORMAT_KEY => DateTimeInterface::RFC3339,
      self::TIMEZONE_KEY => null,
      self::CAST_KEY   => null,
    ];

    /**
     * @param  array<string,mixed>  $defaultContext
     */
    public function __construct(array $defaultContext = []) {
        $this->setDefaultContext($defaultContext);
    }

    /**
     * @param  array<string,mixed>  $defaultContext
     * @return void
     */
    public function setDefaultContext(array $defaultContext) : void {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function getSupportedTypes(?string $format) : array {
        return [
          DateTimeInterface::class => true,
          DateTimeImmutable::class => true,
          DateTime::class => true,
        ];
    }

    /**
     * @param  mixed  $object
     * @param  string|null  $format
     * @param  array<string,mixed>  $context
     * @return int|float|string
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []) : int | float | string {
        if (!$object instanceof DateTimeInterface) {
            throw new InvalidArgumentException('The object must implement the "\DateTimeInterface".');
        }

        $dateTimeFormat = $context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY];
        $timezone = $this->getTimezone($context);

        if (null !== $timezone) {
            /** @var DateTime|DateTimeImmutable $object */
            $object = clone $object;
            $object = $object->setTimezone($timezone);
        }

        return match ($context[self::CAST_KEY] ?? $this->defaultContext[self::CAST_KEY] ?? false) {
            'int' => (int) $object->format($dateTimeFormat),
            'float' => (float) $object->format($dateTimeFormat),
            default => $object->format($dateTimeFormat),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return DateTimeZone|null
     */
    private function getTimezone(array $context) : ?DateTimeZone {
        $dateTimeZone = $context[self::TIMEZONE_KEY] ?? $this->defaultContext[self::TIMEZONE_KEY];

        if (null === $dateTimeZone) {
            return null;
        }

        return $dateTimeZone instanceof DateTimeZone ? $dateTimeZone : new DateTimeZone($dateTimeZone);
    }

    /**
     * @param  mixed  $data
     * @param  string|null  $format
     * @param  array<string,mixed>  $context
     * @return bool
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []) : bool {
        return $data instanceof DateTimeInterface;
    }

    /**
     * @param  mixed  $data
     * @param  class-string<DateTimeInterface>  $type
     * @param  string|null  $format
     * @param  array<string,mixed>  $context
     * @return DateTimeInterface
     */
    public function denormalize(
      mixed   $data,
      string  $type,
      ?string $format = null,
      array   $context = []
    ) : DateTimeInterface {
        if (DateTimeInterface::class === $type) {
            $type = DateTimeImmutable::class;
        }

        $timezone = $this->getTimezone($context);

        if (is_int($data) || is_float($data)) {
            switch ($context[self::FORMAT_KEY] ?? $this->defaultContext[self::FORMAT_KEY] ?? null) {
                case 'U':
                    $data = sprintf('%d', $data);
                    break;
                case 'U.u':
                    $data = sprintf('%.6F', $data);
                    break;
            }
        }

        if (is_array($data) && array_key_exists('date', $data)) {
            if (!is_string($data['date']) || '' === trim($data['date'])) {
                throw NotNormalizableValueException::createForUnexpectedDataType(
                  'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.',
                  $data,
                  ['string'],
                  $context['deserialization_path'] ?? null,
                  true
                );
            }

            if (array_key_exists('timezone', $data)) {
                $timezone = new DateTimeZone($data['timezone']);
            }
            return $this->createDateTime($data['date'], $type, $timezone, $context);
        }

        if (!is_string($data) || '' === trim($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
              'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.',
              $data,
              ['string'],
              $context['deserialization_path'] ?? null,
              true
            );
        }

        return $this->createDateTime($data, $type, $timezone, $context);
    }

    /**
     * @param  string  $data
     * @param  class-string<DateTime|DateTimeImmutable>  $type
     * @param  DateTimeZone  $timezone
     * @param  array<string, mixed>  $context
     * @return DateTimeInterface
     */
    private function createDateTime(
      string       $data,
      string       $type,
      DateTimeZone $timezone,
      array        $context
    ) : DateTimeInterface {
        try {
            $dateTimeFormat = $context[self::FORMAT_KEY] ?? null;

            if (null !== $dateTimeFormat) {
                if (false !== $object = $type::createFromFormat($dateTimeFormat, $data, $timezone)) {
                    return $object;
                }

                $dateTimeErrors = $type::getLastErrors();

                throw NotNormalizableValueException::createForUnexpectedDataType(
                  sprintf(
                    'Parsing datetime string "%s" using format "%s" resulted in %d errors: ',
                    $data,
                    $dateTimeFormat,
                    $dateTimeErrors['error_count'] ?? 1,
                  ).
                  "\n".
                  implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors'] ?? [])),
                  $data,
                  ['string'],
                  $context['deserialization_path'] ?? null,
                  true
                );
            }

            $defaultDateTimeFormat = $this->defaultContext[self::FORMAT_KEY] ?? null;

            if (
              (null !== $defaultDateTimeFormat) && false !== $object = $type::createFromFormat(
                $defaultDateTimeFormat,
                $data,
                $timezone
              )
            ) {
                return $object;
            }

            return new $type($data, $timezone);
        } catch (NotNormalizableValueException $e) {
            throw $e;
        } catch (Exception $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
              $e->getMessage(),
              $data,
              ['string'],
              $context['deserialization_path'] ?? null,
              false,
              $e->getCode(),
              $e
            );
        }
    }

    /**
     * Formats datetime errors.
     *
     * @param  string[]  $errors
     *
     * @return string[]
     */
    private function formatDateTimeErrors(array $errors) : array {
        $formattedErrors = [];

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }

    /**
     * @param  mixed  $data
     * @param  class-string<DateTimeInterface>  $type
     * @param  string|null  $format
     * @param  array<string,mixed>  $context
     * @return bool
     */
    public function supportsDenormalization(
      mixed   $data,
      string  $type,
      ?string $format = null,
      array   $context = []
    ) : bool {
        return isset(self::SUPPORTED_TYPES[$type]);
    }
}
