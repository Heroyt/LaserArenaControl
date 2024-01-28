<?php
/**
 * @file  api.php
 * @brief API route definitions
 */

use App\Controllers\Api\Debug;
use App\Controllers\Api\Events;
use App\Controllers\Api\GameHelpers;
use App\Controllers\Api\Games;
use App\Controllers\Api\Logs;
use App\Controllers\Api\Mount;
use App\Controllers\Api\Results;
use App\Controllers\Api\Updater;
use Lsr\Core\Routing\Route;

$apiGroup = Route::group('api')
                 ->post('mount', [Mount::class, 'mount'])
                 ->post('update', [Updater::class, 'update'])
                 ->post('build', [Updater::class, 'build'])
                 ->post('install', [Updater::class, 'install'])
                 ->post('events', [Events::class, 'triggerEvent']);

$resultGroup = $apiGroup->group('results')
                        ->post('import', [Results::class, 'import'])
                        ->post(
	                        'import/{game}',
	                        [Results::class, 'importGame']
                        )
                        ->get('last', [Results::class, 'getLastGameFile'])
                        ->get('download', [Results::class, 'downloadLastGameFiles']);

$gitGroup = $apiGroup->group('git')
                     ->post('pull', [Updater::class, 'pull'])
                     ->post('fetch', [Updater::class, 'fetch'])
                     ->post('status', [Updater::class, 'status']);

$logGroup = $apiGroup->group('logs')
                     ->get('', [Logs::class, 'show'])
                     ->get('download', [Logs::class, 'download']);

$debugGroup = $apiGroup->group('debug')
                       ->get('pwd', [Debug::class, 'pwd'])
                       ->get('whoami', [Debug::class, 'whoami'])
                       ->post('enable', [Debug::class, 'enable'])
                       ->post('disable', [Debug::class, 'disable'])
                       ->update('incrementCache', [Debug::class, 'incrementCache'])
                       ->put('incrementCache', [Debug::class, 'incrementCache'])
                       ->get('glob', [Debug::class, 'glob']);

$gameGroup = $apiGroup->group('game')
                      ->get('loaded', [GameHelpers::class, 'getLoadedGameInfo'])
                      ->get('gate', [GameHelpers::class, 'getGateGameInfo'])
                      ->post('{code}/recalcSkill', [GameHelpers::class, 'recalcSkill'])
                      ->post('{code}/recalcScores', [GameHelpers::class, 'recalcScores'])
                      ->post('{code}/changeMode', [GameHelpers::class, 'changeGameMode']);

$gamesGroup = $apiGroup->group('games')
                       ->get('', [Games::class, 'listGames'])
                       ->post('sync', [Games::class, 'syncGames'])
                       ->post('sync/{limit}', [Games::class, 'syncGames'])
                       ->get('{code}', [Games::class, 'getGame'])
                       ->post('simulate', [Games::class, 'simulate']);