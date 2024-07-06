<?php

/**
 * @file   web.php
 * @brief  web route definitions
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

use App\Controllers\GameControl;
use App\Controllers\GameGroups;
use App\Controllers\GamesList;
use App\Controllers\Gate\GateController;
use App\Controllers\Lang;
use App\Controllers\NewGame;
use App\Controllers\Players;
use App\Controllers\PreparedGames;
use App\Controllers\Results;
use App\Core\App;
use App\Services\FeatureConfig;
use Lsr\Core\Routing\Route;

/** @var FeatureConfig $featureConfig */
$featureConfig = App::getService('features');

Route::get('/lang/{lang}', [Lang::class, 'setLang']);

Route::get('/', [NewGame::class, 'show'])->name('dashboard');

Route::group('/results')
  ->get('/', [Results::class, 'show'])
  ->name('results')
  ->get('/{code}', [Results::class, 'show'])
  ->name('results-game')
  ->get('/{code}/print', [Results::class, 'printGame'])
  ->name('print')
  ->get('/{code}/print/{lang}', [Results::class, 'printGame'])
  ->get('/{code}/print/{lang}/{copies}', [Results::class, 'printGame'])
  ->get('/{code}/print/{lang}/{copies}/{style}', [Results::class, 'printGame'])
  ->get('/{code}/print/{lang}/{copies}/{style}/{template}', [Results::class, 'printGame'])
  ->get('/{code}/print/{lang}/{copies}/{style}/{template}/{type}', [Results::class, 'printGame']);

Route::group('/list')->get('/', [GamesList::class, 'show'])->name('games-list')->get(
    '/{game}',
    [GamesList::class, 'game']
);

Route::group('/gate')->get('/', [GateController::class, 'show'])->name('gate')->get(
    '/{gate}',
    [GateController::class, 'show']
)->name('gate-slug')->post('/event', [GateController::class, 'setEvent'])->post(
    '/set',
    [GateController::class, 'setGateGame']
)      // Error
     ->post('/loaded', [GateController::class, 'setGateLoaded']) // Error
     ->post('/idle', [GateController::class, 'setGateIdle']) // Error
     ->post('/set/{system}', [GateController::class, 'setGateGame'])->post(
         '/loaded/{system}',
         [GateController::class, 'setGateLoaded']
     )->post('/idle/{system}', [GateController::class, 'setGateIdle']);

Route::group('/players')
  ->get('/find', [Players::class, 'find'])
  ->get('/find/{code}', [Players::class, 'getPlayer'])
  ->get('/sync/{code}', [Players::class, 'syncPlayer'])
  ->group('/public')
  ->get('/find', [Players::class, 'findPublic']);

$prepared = Route::group('prepared');
$prepared->get('', [PreparedGames::class, 'get']);
$prepared->post('', [PreparedGames::class, 'save']);
$prepared->delete('', [PreparedGames::class, 'deleteAll'])->post('delete', [PreparedGames::class, 'deleteAll']);

$preparedId = $prepared->group('{id}');
$preparedId->delete('', [PreparedGames::class, 'delete'])->post('/delete', [PreparedGames::class, 'delete']);

$groups = Route::group('gameGroups');
$groups->get('', [GameGroups::class, 'listGroups']);
$groups->post('', [GameGroups::class, 'create']);

$groupsId = $groups->group('{id}');
$groupsId->get('', [GameGroups::class, 'getGroup']);
$groupsId->update('', [GameGroups::class, 'update']);
$groupsId->post('', [GameGroups::class, 'update']);
$groupsId->get('print', [GameGroups::class, 'printPlayerList']);

$control = Route::group('control');
$control->get('status', [GameControl::class, 'status'])->name('getGameStatus');

$control->post('load', [GameControl::class, 'load'])->name('loadGame');
$control->post('loadSafe', [GameControl::class, 'loadSafe'])->name('loadGameSafe');

$control->post('start', [GameControl::class, 'start'])->name('startGame');
$control->post('startSafe', [GameControl::class, 'startSafe'])->name('startGameSafe');

$control->post('stop', [GameControl::class, 'stop'])->name('stopGame');
$control->post('retry', [GameControl::class, 'retryDownload'])->name('retryDownload');
$control->post('cancel', [GameControl::class, 'cancelDownload'])->name('cancelDownload');
