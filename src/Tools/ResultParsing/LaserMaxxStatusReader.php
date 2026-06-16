<?php

declare(strict_types=1);

namespace App\Tools\ResultParsing;

use App\DataObjects\Import\ResultGameStatus;
use DateTimeImmutable;

trait LaserMaxxStatusReader
{
    private const int STATUS_READ_BYTES = 16384;

    public static function readStatus(string $file, ?string $contents = null): ResultGameStatus
    {
        $contents ??= self::readStatusContents($file);
        if ($contents === null || !static::checkFile($file, $contents)) {
            return ResultGameStatus::unknown(static::SYSTEM);
        }

        preg_match_all(static::REGEXP, $contents, $matches);
        $titles = $matches[1] ?? [];
        $argsAll = $matches[2] ?? [];

        if (empty($titles) || empty($argsAll)) {
            return ResultGameStatus::unknown(static::SYSTEM);
        }

        $fileNumber = null;
        $playerCount = null;
        $loadedAt = null;
        $playStartedAt = null;
        $playEndedAt = null;
        $realEndedAt = null;
        $importedAt = null;

        foreach ($titles as $key => $title) {
            $args = self::getStatusArgs($argsAll[$key] ?? '');
            $argsCount = count($args);

            if ($title === 'GAME' && $argsCount === 5) {
                $fileNumber = (int)$args[0];
                $loadedAt = self::parseStatusDate($args[2]);
                $importedAt = self::parseStatusDate($args[3]);
                $playerCount = (int)$args[4];
                continue;
            }

            if ($title === 'TIMING' && ($argsCount === 5 || $argsCount === 6)) {
                $playStartedAt = self::parseStatusDate($args[3]);
                $playEndedAt = self::parseStatusDate($args[4]);
                $realEndedAt = isset($args[5]) ? self::parseStatusDate($args[5]) : null;
            }
        }

        return new ResultGameStatus(
            system: static::SYSTEM,
            fileNumber: $fileNumber,
            playerCount: $playerCount,
            loadedAt: $loadedAt,
            playStartedAt: $playStartedAt,
            playEndedAt: $playEndedAt,
            realEndedAt: $realEndedAt,
            importedAt: $importedAt,
        );
    }

    private static function readStatusContents(string $file): ?string
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $contents = file_get_contents($file, false, null, 0, self::STATUS_READ_BYTES);
        return is_string($contents) && $contents !== '' ? $contents : null;
    }

    /**
     * @return string[]
     */
    private static function getStatusArgs(string $args): array
    {
        return array_map('trim', explode(',', $args));
    }

    private static function parseStatusDate(string $value): ?DateTimeImmutable
    {
        if ($value === '' || $value === static::EMPTY_DATE) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('YmdHis', $value);
        return $date === false ? null : $date;
    }
}
