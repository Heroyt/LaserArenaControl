<?php

use App\Core\Routing\Route;
use App\Pages\Dashboard;
use App\Pages\GamesList;
use App\Pages\Results;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');
Route::get('/results', [Results::class, 'show'])->name('results');
Route::get('/results/print', [Results::class, 'print'])->name('print');
Route::get('/results/print/{lang}/{copies}', [Results::class, 'print']);
Route::get('/list', [GamesList::class, 'show']);
Route::get('/list/{game}', [GamesList::class, 'game']);
