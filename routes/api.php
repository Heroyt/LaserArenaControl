<?php

/**
 * @file  api.php
 * @brief API route definitions
 */

use App\Http\Controllers\Api\Cache;
use App\Http\Controllers\Api\Debug;
use App\Http\Controllers\Api\Events;
use App\Http\Controllers\Api\GameHelpers;
use App\Http\Controllers\Api\GameLoading;
use App\Http\Controllers\Api\Games;
use App\Http\Controllers\Api\Gates;
use App\Http\Controllers\Api\Helpers;
use App\Http\Controllers\Api\LaserLiga;
use App\Http\Controllers\Api\Logs;
use App\Http\Controllers\Api\Mount;
use App\Http\Controllers\Api\PriceGroups;
use App\Http\Controllers\Api\Results;
use App\Http\Controllers\Api\Tasks;
use App\Http\Controllers\Api\Updater;

/** @var \Lsr\Core\Routing\Router $this */

$apiGroup = $this->group('api')
                 ->post('mount', [Mount::class, 'mount'])
                 ->post('update', [Updater::class, 'update'])
                 ->post('build', [Updater::class, 'build'])
                 ->post('install', [Updater::class, 'install'])
                 ->post('events', [Events::class, 'triggerEvent']);

$resultGroup = $apiGroup->group('results');
$resultGroup->post('import', [Results::class, 'import']);
$resultGroup->post('import/{game}', [Results::class, 'importGame']);
$resultGroup->get('last', [Results::class, 'getLastGameFile']);

$gitGroup = $apiGroup->group('git')
                     ->post('pull', [Updater::class, 'pull'])
                     ->post('fetch', [Updater::class, 'fetch'])
                     ->post('status', [Updater::class, 'status']);

$logGroup = $apiGroup->group('logs');
$logGroup->get('', [Logs::class, 'show']);
$logGroup->get('download', [Logs::class, 'download']);

$debugGroup = $apiGroup->group('debug')
                       ->get('pwd', [Debug::class, 'pwd'])
                       ->get('whoami', [Debug::class, 'whoami'])
                       ->post('enable', [Debug::class, 'enable'])
                       ->post('disable', [Debug::class, 'disable'])
                       ->update('incrementCache', [Debug::class, 'incrementCache'])
                       ->put('incrementCache', [Debug::class, 'incrementCache'])
                       ->get('glob', [Debug::class, 'glob']);

$gameGroup = $apiGroup->group('game');
$gameGroup->post('load/{system}', [GameLoading::class, 'loadGame']);
$gameGroup->get('loaded', [GameHelpers::class, 'getLoadedGameInfo']);
$gameGroup->get('gate', [GameHelpers::class, 'getGateGameInfo']);

$gamesGroup = $apiGroup->group('games')
                       ->get('', [Games::class, 'listGames'])
                       ->post('sync', [Games::class, 'syncGames'])
                       ->post('sync/{limit}', [Games::class, 'syncGames'])
  ->post('simulate', [Games::class, 'simulate'])
  ->group('{code}')
  ->get('', [Games::class, 'getGame'])
  ->post('group', [Games::class, 'setGroup'])
  ->post('sync', [Games::class, 'syncGame'])
  ->post('recalcSkill', [GameHelpers::class, 'recalcSkill'])
  ->post('recalcScores', [GameHelpers::class, 'recalcScores'])
  ->post('changeMode', [GameHelpers::class, 'changeGameMode'])
  ->get('highlights', [Games::class, 'getHighlights']);


$tasksGroup = $apiGroup->group('tasks');
$tasksGroup->post('precache', [Tasks::class, 'planGamePrecache']);
$tasksGroup->post('highlights', [Tasks::class, 'planGameHighlights']);

$laserLigaGroup = $apiGroup->group('laserliga');
$laserLigaGroup->get('games/{code}/highlights', [LaserLiga::class, 'highlights']);

$cacheGroup = $apiGroup->group('cache');
$cacheClearGroup = $cacheGroup->group('clear');
$cacheClearGroup->post('', [Cache::class, 'clearAll'])->name('cache-clear');
$cacheClearGroup->post('system', [Cache::class,'clearSystem'])->name('cache-clear-system');
$cacheClearGroup->post('di', [Cache::class, 'clearDi'])->name('cache-clear-di');
$cacheClearGroup->post('models', [Cache::class, 'clearModels'])->name('cache-clear-models');
$cacheClearGroup->post('config', [Cache::class, 'clearConfig'])->name('cache-clear-config');
$cacheClearGroup->post('results', [Cache::class, 'clearResults'])->name('cache-clear-results');
$cacheClearGroup->post('latte', [Cache::class, 'clearLatte'])->name('cache-clear-latte');

$helpersGroup = $apiGroup->group('helpers');
$helpersGroup->get('translate', [Helpers::class, 'translate']);

$apiGroup->group('gates')->post('start', [Gates::class, 'start'])->post('stop', [Gates::class, 'stop']);

$priceGroup = $apiGroup->group('pricegroups');
$priceGroup->get('/', [PriceGroups::class, 'list'])->name('api-price-groups');
$priceGroup->post('/', [PriceGroups::class, 'create'])->name('api-price-groups-create');

$priceGroupId = $priceGroup->group('{id}');
$priceGroupId->get('/', [PriceGroups::class, 'show'])->name('api-price-group');
$priceGroupId->post('/', [PriceGroups::class, 'update']);
$priceGroupId->put('/', [PriceGroups::class, 'update']);
$priceGroupId->delete('/', [PriceGroups::class, 'delete']);
$priceGroupId->post('/delete', [PriceGroups::class, 'delete']);
