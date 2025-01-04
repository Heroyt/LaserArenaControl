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
use App\Controllers\System\Cache;
use App\Controllers\System\Roadrunner;
use App\Controllers\System\System;
use App\Core\App;
use App\Services\FeatureConfig;
use Lsr\Core\Routing\Route;

/** @var \Lsr\Core\Routing\Router $this */

/** @var FeatureConfig $featureConfig */
$featureConfig = App::getService('features');

$this->get('/lang/{lang}', [Lang::class, 'setLang']);

$this->get('/', [NewGame::class, 'show'])->name('dashboard');

$this->group('/results')
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

$this->group('/list')->get('/', [GamesList::class, 'show'])->name('games-list')->get(
    '/{game}',
    [GamesList::class, 'game']
);

$gateGroup = $this->group('/gate');
$gateGroup->get('/', [GateController::class, 'show'])->name('gate');
$gateGroup->get('/{gate}', [GateController::class, 'show'])->name('gate-slug');
$gateGroup->post('/event', [GateController::class, 'setEvent']);
$gateGroup->post('/set', [GateController::class, 'setGateGame']);      // Error
$gateGroup->post('/loaded', [GateController::class, 'setGateLoaded']); // Error
$gateGroup->post('/idle', [GateController::class, 'setGateIdle']); // Error
$gateGroup->post('/set/{system}', [GateController::class, 'setGateGame']);
$gateGroup->post('/loaded/{system}', [GateController::class, 'setGateLoaded']);
$gateGroup->post('/idle/{system}', [GateController::class, 'setGateIdle']);

$playersGroup = $this->group('/players');
$playersGroup->get('', [Players::class, 'show'])->name('liga-players');
$playersGroup->group('sync')
  ->post('', [Players::class, 'sync'])
  ->get('{code}', [Players::class, 'syncPlayer'])
  ->post('{code}', [Players::class, 'syncPlayer']);
$playersGroup->group('find')
  ->get('', [Players::class, 'find'])
  ->get('{code}', [Players::class, 'getPlayer']);
$playersGroup->group('public')
  ->get('find', [Players::class, 'findPublic']);

$prepared = $this->group('prepared');
$prepared->get('', [PreparedGames::class, 'get']);
$prepared->post('', [PreparedGames::class, 'save']);
$prepared->post('{type}', [PreparedGames::class, 'save']);
$prepared->delete('', [PreparedGames::class, 'deleteAll'])->post('delete', [PreparedGames::class, 'deleteAll']);

$preparedId = $prepared->group('{id}');
$preparedId->delete('', [PreparedGames::class, 'delete'])->post('/delete', [PreparedGames::class, 'delete']);

$groups = $this->group('gameGroups');
$groups->get('', [GameGroups::class, 'listGroups']);
$groups->get('find', [GameGroups::class, 'findGroups']);
$groups->post('', [GameGroups::class, 'create']);

$groupsId = $groups->group('{id}');
$groupsId->get('', [GameGroups::class, 'getGroup']);
$groupsId->update('', [GameGroups::class, 'update']);
$groupsId->post('', [GameGroups::class, 'update']);
$groupsId->get('print', [GameGroups::class, 'printPlayerList']);

$control = $this->group('control');
$control->get('status', [GameControl::class, 'status'])->name('getGameStatus');

$control->post('load', [GameControl::class, 'load'])->name('loadGame');
$control->post('loadSafe', [GameControl::class, 'loadSafe'])->name('loadGameSafe');

$control->post('start', [GameControl::class, 'start'])->name('startGame');
$control->post('startSafe', [GameControl::class, 'startSafe'])->name('startGameSafe');

$control->post('stop', [GameControl::class, 'stop'])->name('stopGame');
$control->post('retry', [GameControl::class, 'retryDownload'])->name('retryDownload');
$control->post('cancel', [GameControl::class, 'cancelDownload'])->name('cancelDownload');

$roadrunner = $this->group('roadrunner');
$roadrunner->get('reset', [Roadrunner::class, 'reset'])->name('resetRoadrunnerGet');
$roadrunner->post('reset', [Roadrunner::class, 'reset'])->name('resetRoadrunner');

$system = $this->group('system');
$system->get('cache', [Cache::class, 'show'])->name('settings-cache');
$system->get('restart', [System::class, 'restart'])->name('resetDockerGet');
$system->post('restart', [System::class, 'restart'])->name('resetDocker');
$system->get('ffmpeg/restart', [System::class, 'restartFfmpeg'])->name('resetFFMPEGDockerGet');
$system->post('ffmpeg/restart', [System::class, 'restartFfmpeg'])->name('resetFFMPEGDocker');
