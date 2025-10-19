<?php

declare(strict_types=1);

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Throwable;

trait WithGameCodeParam
{
    protected function getGameFromCode(string $code) : Game | ErrorResponse {
        if (empty($code)) {
            return new ErrorResponse('Invalid code', ErrorType::VALIDATION);
        }
        try {
            $game = GameFactory::getByCode($code);
        } catch (Throwable $e) {
            return new ErrorResponse('Game not found', ErrorType::NOT_FOUND, exception: $e);
        }
        if ($game === null) {
            return new ErrorResponse('Game not found', ErrorType::NOT_FOUND);
        }
        return $game;
    }
}
