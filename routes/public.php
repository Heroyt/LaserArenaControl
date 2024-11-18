<?php

declare(strict_types=1);

use App\Controllers\Public\LaserLiga;
use App\Controllers\Public\Music;
use App\Controllers\Public\NewGame;
use Lsr\Core\Routing\Route;

$publicGroup = Route::group('public');

$publicGroup->get('', [NewGame::class, 'show'])->name('public');
$publicGroup->get('music', [Music::class, 'show'])->name('public-music');
$publicGroup->get('liga', [LaserLiga::class, 'show'])->name('public-liga');
$publicGroup->post('liga', [LaserLiga::class, 'register'])->name('public-liga-post');
$publicGroup->get('liga/players', [LaserLiga::class, 'topPlayers']);
