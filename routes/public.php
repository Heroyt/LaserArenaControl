<?php

declare(strict_types=1);

use App\Controllers\Public\Music;
use App\Controllers\Public\NewGame;
use Lsr\Core\Routing\Route;

$publicGroup = Route::group('public');

$publicGroup->get('', [NewGame::class, 'show'])->name('public');
$publicGroup->get('music', [Music::class, 'show'])->name('public-music');
