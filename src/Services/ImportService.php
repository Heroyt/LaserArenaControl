<?php

/** @noinspection PhpToStringImplementationInspection */

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\CQRS\Commands\ImportResultFileCommand;
use App\DataObjects\Import\ResultFileImportStatus;
use App\GameModels\Game\Game;
use App\GameModels\Game\Lasermaxx\Team;
use App\GameModels\Game\Player;
use DateTimeImmutable;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\CQRS\CommandBus;
use Lsr\Exceptions\FileException;
use Lsr\Lg\Results\Exception\ResultsParseException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

/**
 * Service for handling import of game files from controllers
 */
class ImportService
{
    public function __construct(
        private readonly ResultFileVersionFactory        $versionFactory,
        private readonly ResultFileImportStateRepository $stateRepository,
        private readonly CommandBus                      $commandBus,
    ) {
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T, P>
     * @param G $game
     * @throws ResultsParseException
     * @throws Throwable
     * @throws FileException
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function importGame(Game $game, string $resultsDir): SuccessResponse|ErrorResponse {
        $resultsDir = trailingSlashIt($resultsDir);

        $id = $game->id;
        $code = $game->code;

        /** @var array<int|string, int> $playerIds */
        $playerIds = [];
        /** @var array<int, int> $teamIds */
        $teamIds = [];

        foreach ($game->players as $player) {
            if ($player->id !== null) {
                $playerIds[$player->vest] = $player->id;
            }
        }

        foreach ($game->teams as $team) {
            if ($team->id !== null) {
                $teamIds[$team->color] = $team->id;
            }
        }

        $file = null;
        if (!empty($game->resultsFile)) {
            $resultsFile = $game->resultsFile;
            $candidates = [$resultsFile];
            if (pathinfo($resultsFile, PATHINFO_EXTENSION) === '') {
                $candidates[] = $resultsFile . '.game';
            }
            if (!$this->isAbsolutePath($resultsFile)) {
                $candidates[] = $resultsDir . $resultsFile;
                if (pathinfo($resultsFile, PATHINFO_EXTENSION) === '') {
                    $candidates[] = $resultsDir . $resultsFile . '.game';
                }
            }

            foreach (array_unique($candidates) as $candidate) {
                if (file_exists($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
        }

        if ($file === null && $game instanceof \App\GameModels\Game\Lasermaxx\Game && !empty($game->fileNumber)) {
            $pattern = $resultsDir . str_pad((string)$game->fileNumber, 4, '0', STR_PAD_LEFT) . '*.game';
            $files = glob($pattern);
            if (empty($files)) {
                return new ErrorResponse(
                    'Cannot find game file.',
                    type: ErrorType::NOT_FOUND,
                    values: ['path' => $pattern]
                );
            }
            if (count($files) > 1) {
                return new ErrorResponse(
                    'Found more than one suitable game file.',
                    type: ErrorType::INTERNAL,
                    values: ['path' => $pattern, 'files' => $files]
                );
            }
            $file = $files[0];
        }
        if ($file === null) {
            return new ErrorResponse(
                'Cannot get game file number.',
                type: ErrorType::NOT_FOUND,
                values: ['game' => $game]
            );
        }

        try {
            $version = $this->versionFactory->fromFile($file);
            $this->stateRepository->saveSeen(
                $version,
                $game::SYSTEM,
                ResultFileImportStatus::QUEUED,
                new DateTimeImmutable(),
            );

            $result = $this->commandBus->dispatch(
                new ImportResultFileCommand(
                    path: $version->path,
                    pathHash: $version->pathHash,
                    system: $game::SYSTEM,
                    mtime: $version->mtime,
                    size: $version->size,
                    contentHash: $version->contentHash,
                    version: $version->version,
                    force: true,
                    preserveGameId: $id,
                    preserveGameCode: $code,
                    preservePlayerIdsByVest: $playerIds,
                    preserveTeamIdsByColor: $teamIds,
                )
            );
        } catch (Throwable $e) {
            return new ErrorResponse('Error while parsing game file.', type: ErrorType::INTERNAL, exception: $e);
        }

        if ($result->status === ResultFileImportStatus::IMPORTED) {
            return new SuccessResponse(values: ['game' => $game, 'import' => $result]);
        }

        return new ErrorResponse(
            $result->error ?? $result->event ?? 'Game was not imported.',
            type: $result->status === ResultFileImportStatus::FAILED ? ErrorType::INTERNAL : ErrorType::VALIDATION,
            values: ['import' => $result],
        );
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[a-z]:[\/\\\\]/i', $path) === 1;
    }
}
