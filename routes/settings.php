<?php

use App\Core\App;
use App\Http\Controllers\Settings\Gate;
use App\Http\Controllers\Settings\Modes;
use App\Http\Controllers\Settings\Music;
use App\Http\Controllers\Settings\PrintSettings;
use App\Http\Controllers\Settings\Settings;
use App\Http\Controllers\Settings\SystemsSettings;
use App\Http\Controllers\Settings\TipsSettings;
use App\Services\FeatureConfig;
use Lsr\Core\Routing\Router;

/** @var Router $this */

/** @var FeatureConfig $featureConfig */
$featureConfig = App::getService('features');

$settings = $this->group('settings');
$settings->get('', [Settings::class, 'show'])->name('settings');
$settings->post('', [Settings::class, 'saveGeneral']);

$settings->get('systems', [SystemsSettings::class, 'show'])->name('settings-systems');
$settings->post('systems', [SystemsSettings::class, 'save']);
$settings->post('systems/create', [SystemsSettings::class, 'create']);
$settings->post('systems/{id}/add-vests', [SystemsSettings::class, 'addVests']);

$settings->get('vests', [Settings::class, 'vests'])->name('settings-vests');
$settings->post('vests', [Settings::class, 'saveVests']);
$settings->delete('vests/{id}', [SystemsSettings::class, 'deleteVest']);

$settings->get('print', [PrintSettings::class, 'show'])->name('settings-print');
$settings->post('print', [PrintSettings::class, 'save']);

$gate = $settings->group('gate');
$gate->get('', [Gate::class, 'gate'])->name('settings-gate');
$gate->post('', [Gate::class, 'saveGate']);
$gate->get('settings/{screen}', [Gate::class, 'screenSettings']);

$modes = $settings->group('modes');
$modes->get('', [Modes::class, 'modes'])->name('settings-modes');
$modes->post('', [Modes::class, 'save']);
$modes->get('{system}', [Modes::class, 'modes'])->name('settings-modes-system');
$modes->get('variations', [Modes::class, 'getAllVariations']);
$modes->post('variations', [Modes::class, 'createVariation']);
$modes->post('new/{system}/{type}', [Modes::class, 'createGameMode']);

$modeId = $modes->group('{id}');
$modes->delete('', [Modes::class, 'deleteGameMode']);
$modeId->get('variations', [Modes::class, 'modeVariations']);
$modeId->post('variations', [Modes::class, 'saveModeVariations']);
$modeId->get('settings', [Modes::class, 'modeSettings']);
$modeId->get('names', [Modes::class, 'modeNames']);
$modeId->post('names', [Modes::class, 'saveModeNames']);

$music = $settings->group('music');
$music->get('', [Music::class, 'show'])->name('settings-music');
$music->post('', [Music::class, 'save']);
$music->post('upload', [Music::class, 'upload']);

$musicId = $music->group('{id}');
$musicId->delete('', [Music::class, 'delete']);
$musicId->post('intro', [Music::class, 'uploadIntro']);
$musicId->post('ending', [Music::class, 'uploadEnding']);
$musicId->post('armed', [Music::class, 'uploadArmed']);


if ($featureConfig->isFeatureEnabled('groups')) {
    $settings->get('groups', [Settings::class, 'group'])->name('settings-groups');
}

$tips = $settings->group('tips');
$tips->get('', [TipsSettings::class, 'show'])->name('settings-tips');
$tips->post('', [TipsSettings::class, 'save']);
$tips->post('{id}/delete', [TipsSettings::class, 'remove']);
$tips->delete('{id}', [TipsSettings::class, 'remove']);
