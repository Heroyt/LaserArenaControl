<?php

declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\PrepareGameCommand;
use App\DataObjects\PreparedGames\PreparedGameType;
use App\Http\Controllers\PreparedGames;
use App\Models\System;
use App\Models\SystemType;
use DateTimeImmutable;
use Lsr\Caching\Cache;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;

final readonly class PrepareGameCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private Serializer $serializer,
        private Cache      $cache,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @param PrepareGameCommand $command
     */
    public function handle(CommandInterface $command): bool {
        // Normalize system
        $system = $command->system;
        if (is_numeric($system)) {
            try {
                $system = System::get((int)$system);
            } catch (ModelNotFoundException) {
                $system = null;
            }
        } elseif (is_string($system)) {
            $type = SystemType::tryFrom($system);
            if ($type !== null) {
                $systems = System::getForType($type);
                $system = first($systems);
            } else {
                $system = null;
            }
        }

        try {
            $data = $this->serializer->serialize($command->data, 'json');
        } catch (ExceptionInterface) {
            return false;
        }

        $insertData = [
            'datetime' => new DateTimeImmutable(),
            'data' => $data,
            'type' => $command->type->value,
            'id_system' => $system?->id,
            'active' => 1,
        ];

        if ($command->type === PreparedGameType::LOADED) {
            // Upsert loaded prepared game
            $test = DB::select(PreparedGames::TABLE, 'id_game')
                ->where('id_system = %i', $system?->id)
                ->where('type = %s', PreparedGameType::LOADED->value)
                ->fetchSingle(false);

            if ($test !== null) {
                DB::update(PreparedGames::TABLE, $insertData, ['id_game = %i', $test]);
            } else {
                DB::insert(PreparedGames::TABLE, $insertData);
            }
        } else {
            // Insert prepared game
            DB::insert(PreparedGames::TABLE, $insertData);
        }

        $this->cache->clean([$this->cache::Tags => PreparedGames::CACHE_TAGS]);

        return true;
    }
}
