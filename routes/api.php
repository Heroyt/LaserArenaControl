<?php
/**
 * @file  api.php
 * @brief API route definitions
 */

use App\Controllers\Api\Cache;
use App\Controllers\Api\Debug;
use App\Controllers\Api\Events;
use App\Controllers\Api\GameHelpers;
use App\Controllers\Api\GameLoading;
use App\Controllers\Api\Games;
use App\Controllers\Api\Gates;
use App\Controllers\Api\Helpers;
use App\Controllers\Api\LaserLiga;
use App\Controllers\Api\Logs;
use App\Controllers\Api\Mount;
use App\Controllers\Api\PriceGroups;
use App\Controllers\Api\Results;
use App\Controllers\Api\Tasks;
use App\Controllers\Api\Updater;
use Lsr\Core\Routing\Route;

$apiGroup = Route::group('api')
                 ->post('mount', [Mount::class, 'mount'])
                 ->post('update', [Updater::class, 'update'])
                 ->post('build', [Updater::class, 'build'])
                 ->post('install', [Updater::class, 'install'])
                 ->post('events', [Events::class, 'triggerEvent']);

$resultGroup = $apiGroup->group('results')->post('import', [Results::class, 'import'])->post(
  'import/{game}',
  [
    Results::class,
    'importGame',
  ]
)->get('last', [Results::class, 'getLastGameFile'])->get('download', [Results::class, 'downloadLastGameFiles']);

$gitGroup = $apiGroup->group('git')
                     ->post('pull', [Updater::class, 'pull'])
                     ->post('fetch', [Updater::class, 'fetch'])
                     ->post('status', [Updater::class, 'status']);

$logGroup = $apiGroup->group('logs')->get('', [Logs::class, 'show'])->get('download', [Logs::class, 'download']);

$debugGroup = $apiGroup->group('debug')
                       ->get('pwd', [Debug::class, 'pwd'])
                       ->get('whoami', [Debug::class, 'whoami'])
                       ->post('enable', [Debug::class, 'enable'])
                       ->post('disable', [Debug::class, 'disable'])
                       ->update('incrementCache', [Debug::class, 'incrementCache'])
                       ->put('incrementCache', [Debug::class, 'incrementCache'])
                       ->get('glob', [Debug::class, 'glob']);

$gameGroup = $apiGroup->group('game')->post('load/{system}', [GameLoading::class, 'loadGame'])->get(
  'loaded',
  [
    GameHelpers::class,
    'getLoadedGameInfo',
  ]
)->get('gate', [GameHelpers::class, 'getGateGameInfo']);

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


$apiGroup->group('tasks')->post('precache', [Tasks::class, 'planGamePrecache'])->post(
  'highlights',
  [
    Tasks::class,
    'planGameHighlights',
  ]
);

$apiGroup->group('laserliga')->group('games')->group('{code}')->get('highlights', [LaserLiga::class, 'highlights']);

$apiGroup->group('cache')->group('clear')->post('', [Cache::class, 'clearAll'])->name('cache-clear')->post(
  'system',
  [
    Cache::class,
    'clearSystem',
  ]
)->name('cache-clear-system')->post('di', [Cache::class, 'clearDi'])->name('cache-clear-di')->post(
  'models',
  [
    Cache::class,
    'clearModels',
  ]
)->name('cache-clear-models')->post('config', [Cache::class, 'clearConfig'])->name('cache-clear-config')->post(
  'results',
  [Cache::class, 'clearResults']
)->name('cache-clear-results')->post('latte', [Cache::class, 'clearLatte'])->name('cache-clear-latte');

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