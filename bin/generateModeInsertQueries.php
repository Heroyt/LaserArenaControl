<?php
declare(strict_types=1);

$system = $argv[1] ?? 'evo6';
$format = $argv[2] ?? 'sql';

$defaultSettings = [
  "Title"                     => 'Game mode',
  "Description"               => '',
  "HighScore"                 => 0,
  "HighScoreName"             => null,
  "TeamOrSolo"                => 0,
  // 0 = team, 1 = solo
  "HitYouSc"                  => -50,
  "YouHitSc"                  => 100,
  "HitYouMateSc"              => -50,
  "YouHitMateSc"              => -75,
  "MineSc"                    => -50,
  "ShotSc"                    => 0,
  "BlastSc"                   => 75,
  "StealthSc"                 => 75,
  "ShieldSc"                  => 75,
  "SpySc"                     => 75,
  "StartTime"                 => 20,
  "PlayTime"                  => 15,
  "HitTime"                   => 5,
  "GameOverTime"              => 10,
  "PacksColor"                => 0,
  "ArmedColor"                => 15,
  "StartColor"                => 15,
  "PlayColor"                 => 15,
  "HitColor"                  => 15,
  "GameoverColor"             => 15,
  "ArmedFlash"                => 1,
  "StartFlash"                => 5,
  "PlayFlash"                 => 1,
  "HitFlash"                  => 0,
  "GameoverFlash"             => 15,
  "Ammo"                      => 9999,
  "Lives"                     => 999,
  "FrontTrigger"              => false,
  "SoundOn"                   => true,
  "TeamHits"                  => true,
  "ReactionOn"                => false,
  "RapidFire"                 => false,
  "BlastDouble"               => false,
  "FlashStrobe"               => true,
  "Anonym"                    => false,
  "DisplayTime"               => true,
  "DisplayLight"              => true,
  "VibratorOn"                => true,
  "AckHits"                   => true,
  "DimmedLeds"                => true,
  "GreenTeamLaser"            => false,
  "VoiceCoachOn"              => true,
  "SearchLightOn"             => true,
  "GunSensor"                 => true,
  "FrontSensor"               => true,
  "BackSensor"                => true,
  "ShoulderSensor"            => true,
  "EffectOnArmed"             => false,
  "EffectOnPlay"              => false,
  "EffectOnGameOver"          => false,
  "EffectOnStart"             => false,
  "EffectOnStartEnd"          => false,
  "EffectOnPlayEnd"           => false,
  "EffectArmed"               => '',
  "EffectPlay"                => '',
  "EffectGameOver"            => '',
  "EffectStart"               => '',
  "EffectStartEnd"            => '',
  "EffectPlayEnd"             => '',
  "MusicOnArm"                => false,
  "MusicOnPlay"               => false,
  "MusicOnGameover"           => false,
  "Mp3Arm"                    => '',
  "Mp3Play"                   => '',
  "Mp3Gameover"               => '',
  "DimmedArmed"               => false,
  "SwatLaserOn"               => false,
  "MineOn00"                  => false,
  "MineType00"                => 0,
  "MineTeam00"                => 0,
  "MineName00"                => 'Unnamed Unit',
  "MineOn01"                  => false,
  "MineType01"                => 0,
  "MineTeam01"                => 0,
  "MineName01"                => 'Unnamed Unit',
  "MineOn02"                  => false,
  "MineType02"                => 0,
  "MineTeam02"                => 0,
  "MineName02"                => 'Unnamed Unit',
  "MineOn03"                  => false,
  "MineType03"                => 0,
  "MineTeam03"                => 0,
  "MineName03"                => 'Unnamed Unit',
  "MineOn04"                  => false,
  "MineType04"                => 0,
  "MineTeam04"                => 0,
  "MineName04"                => 'Unnamed Unit',
  "MineOn05"                  => false,
  "MineType05"                => 0,
  "MineTeam05"                => 0,
  "MineName05"                => 'Unnamed Unit',
  "MineOn06"                  => false,
  "MineType06"                => 0,
  "MineTeam06"                => 0,
  "MineName06"                => 'Unnamed Unit',
  "MineOn07"                  => false,
  "MineType07"                => 0,
  "MineTeam07"                => 0,
  "MineName07"                => 'Unnamed Unit',
  "ForceLasersOff"            => false,
  "VirtualAmmoClips"          => false,
  "MusicOnStart"              => false,
  "Mp3Start"                  => '',
  "BonusAccType"              => 0,
  "BonusAccParam1"            => 0,
  "BonusAccParam2"            => 0,
  "VirtualAmmoClipsAuto"      => false,
  "DmxSceneArm"               => null,
  "DmxSceneStart"             => null,
  "DmxScenePlay"              => null,
  "DmxSceneGameover"          => null,
  "Locked"                    => false,
  "VampireOn"                 => false,
  "VampireColor"              => 0,
  "VampireLives"              => 1,
  "VampireAmmo"               => 9999,
  "HitstreakLength"           => 5,
  "HitstreakReward"           => 15,
  "HitStreakOn"               => false,
  "VipOn"                     => false,
  "VipLives"                  => 1,
  "VipAmmo"                   => 20,
  "VipKills"                  => false,
  "VipFlash"                  => 1,
  "VipTeamKill"               => true,
  "VipHitScore"               => 200,
  "AssistOn"                  => false,
  "AssistBlast"               => false,
  "AssistMakeDoubleHits"      => false,
  "AssistIgnoreFrontTrigger"  => false,
  "AssistIgnoreTeamHits"      => false,
  "AssistHitTime"             => 5,
  "AssistIgnoreTeamHitScores" => false,
  "VampireSpecialPlayers"     => false,
  "AssistHitTimeOn"           => false,
  "ActivityBonusType"         => 0,
  "ActivityBonusPoints"       => 250,
  "ActivityActiveLeds"        => 0,
  "VampireResistance"         => 0,
  "ShowdownOn"                => false,
  "ShowdownLeds"              => 0,
  "ShowdownBlast"             => true,
  "ShowdownMinutes"           => 1,
  "ShowdownHittype"           => 0,
  "SwitchOn"                  => false,
  "SwitchResistance"          => 0,
  "SampleTable"               => 255,
  "TerminateCompound"         => 0,
  "SpecialPacksBlinkArmed"    => true,
  "EncouragementType"         => 0,
  "EncouragementBonus"        => 0,
  "FormatNumber"              => 1,
  "VipTerminate"              => 0,
  "KnockoutOn"                => false,
  "KnockoutBonusPoints"       => 100,
  "AmmoClips"                 => 0,
  // 0 = off, 41 = reload after 5 seconds, 42 = reload after 5 trigger pulls, 3 = reload after pressing chest button
  "Reality"                   => '0',
  "PrintCards"                => false,
  "AmmoAsClips"               => 25359,
  "AmmoAsClipsTrooper"        => 25359,
  "VirtualAmmoClipsTrooper"   => false,
  "AssistMakeTenfoldHits"     => false,
  "AssistMakeBazookaHits"     => false,
  "VipIgnoreTeamHits"         => true,
  "VipHitTimeOn"              => false,
  "VipHitTime"                => 5,
  "VipBlastShots"             => false,
  "VipMakeDoubleHits"         => false,
  "VipMakeTenfoldHits"        => false,
  "PowerSc"                   => 75,
  "PenaltySc"                 => -200,
  "TriggerSpeed"              => 0,
  "SpecialShotSound"          => 0,
  "HitgainAmmo"               => 0,
  "HitgainLives"              => 0,
  "HitgainSeconds"            => 0,
  "VipMessageSilent"          => false,
  "VampireRainbow"            => false,
  "VipMessageHitDuration"     => 2,
  "VipMessageKillDuration"    => 0,
  "RespawnWhen"               => 0,
  "RespawnLives"              => 5,
  "RespawnWhenParam1"         => 0,
];

$evo5ExclusiveFields = [
  'VirtualAmmoClipsTrooper',
  'AmmoAsClipsTrooper',
];

$evo6Fields = [
  "AssistMakeTenfoldHits",
  "AssistMakeBazookaHits",
  "VipIgnoreTeamHits",
  "VipHitTimeOn",
  "VipHitTime",
  "VipBlastShots",
  "VipMakeDoubleHits",
  "VipMakeTenfoldHits",
  "PowerSc",
  "PenaltySc",
  "TriggerSpeed",
  "SpecialShotSound",
  "HitgainAmmo",
  "HitgainLives",
  "HitgainSeconds",
  "VipMessageSilent",
  "VampireRainbow",
  "VipMessageHitDuration",
  "VipMessageKillDuration",
  "RespawnWhen",
  "RespawnLives",
  "RespawnWhenParam1",
];

$gameLengthVariations = [
  ''    => [
    'PlayTime' => 15,
  ],
  '-13' => [
    'PlayTime' => 13,
  ],
  '-10' => [
    'PlayTime' => 10,
  ],
];

$hitStreakVariations = [
  ''     => [
    'HitStreakOn' => false,
  ],
  '-H3S' => [
    'HitstreakLength' => 3,
    'HitstreakReward' => 15,
    'HitStreakOn'     => true,
  ],
  '-H5S' => [
    'HitstreakLength' => 5,
    'HitstreakReward' => 15,
    'HitStreakOn'     => true,
  ],
];

$podsVariations = [
  ''    => [
    'MineType00' => 2,
    'MineTeam00' => 6,
    'MineType01' => 2,
    'MineTeam01' => 6,
    'MineType02' => 2,
    'MineTeam02' => 6,
    'MineType03' => 2,
    'MineTeam03' => 6,
    'MineType04' => 2,
    'MineTeam04' => 6,
    'MineType05' => 2,
    'MineTeam05' => 6,
    'MineType06' => 2,
    'MineTeam06' => 6,
    'MineType07' => 2,
    'MineTeam07' => 6,
  ],
  '-BM' => [
    'MineType00' => 1,
    'MineTeam00' => 6,
    'MineType01' => 1,
    'MineTeam01' => 6,
    'MineType02' => 1,
    'MineTeam02' => 6,
    'MineType03' => 1,
    'MineTeam03' => 6,
    'MineType04' => 1,
    'MineTeam04' => 6,
    'MineType05' => 1,
    'MineTeam05' => 6,
    'MineType06' => 1,
    'MineTeam06' => 6,
    'MineType07' => 1,
    'MineTeam07' => 6,
  ],
];

$noVariations = [
  [
    '' => [],
  ],
];

$modes = [
  '1-T-DM'       => [
    'settings'   => [
      'Description'  => 'Klasicka tymova hra',
      'TeamOrSolo'   => 0,
      'FormatNumber' => 1,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
      'hitstreak'  => $hitStreakVariations,
    ],
  ],
  '1-T-TMA'      => [
    'settings'   => [
      'Description'          => 'Klasicka tymova hra ve tme',
      'TeamOrSolo'           => 0,
      'PacksColor'           => 0,
      'PlayFlash'            => 0,
      'HitFlash'             => 6,
      'AmmoClips'            => 42,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'HitStreakOn'          => true,
      'HitstreakLength'      => 5,
      'HitstreakReward'      => 12,
      'AmmoAsClips'          => 25359,
      'AmmoAsClipsTrooper'   => 25359,
      'TeamHits'             => false,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
    ],
  ],
  '2-S-DM'       => [
    'settings'   => [
      'Description'  => 'Klasicka solo hra',
      'TeamOrSolo'   => 1,
      'FormatNumber' => 0,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
      'hitstreak'  => $hitStreakVariations,
    ],
  ],
  '2-S-TMA'      => [
    'settings'   => [
      'Description'          => 'Klasicka solo hra ve tme',
      'TeamOrSolo'           => 1,
      'FormatNumber'         => 0,
      'PacksColor'           => 0,
      'PlayFlash'            => 0,
      'HitFlash'             => 6,
      'AmmoClips'            => 42,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'HitStreakOn'          => true,
      'HitstreakLength'      => 5,
      'HitstreakReward'      => 12,
      'AmmoAsClips'          => 25359,
      'AmmoAsClipsTrooper'   => 25359,
      'TeamHits'             => false,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
    ],
  ],
  '3-S-NABOJU'   => [
    'settings'   => [
      'Description'  => 'Solo hra s omezenym poctem naboju',
      'TeamOrSolo'   => 1,
      'FormatNumber' => 0,
      'TriggerSpeed' => 1,
    ],
    'variations' => [
      'ammo' => [
        '100' => [
          'Ammo' => 100,
        ],
        '150' => [
          'Ammo' => 150,
        ],
        '200' => [
          'Ammo' => 200,
        ],
      ],
      'pods' => $podsVariations,
    ],
  ],
  '4-S-SURVIVAL' => [
    'settings'   => [
      'Description'  => 'Omezeny pocet zivotu a naboju',
      'TeamOrSolo'   => 1,
      'FormatNumber' => 0,
      'Ammo'         => 300,
      'Lives'        => 30,
    ],
    'variations' => [
      'pods' => $podsVariations,
    ],
  ],
  '4-T-SURVIVAL' => [
    'settings'   => [
      'Description'  => 'Omezeny pocet zivotu a naboju',
      'TeamOrSolo'   => 0,
      'FormatNumber' => 1,
      'Ammo'         => 300,
      'Lives'        => 30,
    ],
    'variations' => [
      'pods' => $podsVariations,
    ],
  ],
  '5-ZAKLADNY'   => [
    'settings'   => [
      'Description'          => 'Takticka hra dvou tymu, kteri si vzajemne utoci na zakladny.',
      'TeamOrSolo'           => 0,
      'Lives'                => 900,
      'TeamHits'             => false,
      'MineOn06'             => true,
      'MineType06'           => 4,
      'MineTeam06'           => 2,
      'MineName06'           => 'ZakladnaHorni',
      'MineOn07'             => true,
      'MineType07'           => 4,
      'MineTeam07'           => 1,
      'MineName07'           => 'ZakladnaDolni',
      'AmmoClips'            => 42,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => 25359,
      'AmmoAsClipsTrooper'   => 25359,
      'HitStreakOn'          => true,
      'HitstreakLength'      => 5,
      'HitstreakReward'      => 2,
    ],
    'variations' => [
      'sides' => [
        '-MZ' => [
          'MineTeam06' => 2,
          'MineTeam07' => 1,
        ],
        '-ZM' => [
          'MineTeam06' => 1,
          'MineTeam07' => 2,
        ],
      ],
    ],
  ],
  '6-CSGO'       => [
    'settings'   => [
      'Description' => 'Kazdy tym zacina v jednom z domecku. Kazdy ma jen 3 zivoty.',
      'Lives'       => 3,
      'TeamOrSolo'  => 0,
      'HitTime'     => 1,
    ],
    'variations' => $noVariations,
  ],
];

$inserts = [];

// Print csv header
if ($format === 'csv') {
    echo implode(',', array_map(fn(string $value) => "\"$value\"", array_keys($defaultSettings)))."\n";
}

foreach ($modes as $name => $mode) {
    $settings = array_merge($defaultSettings, $mode['settings']);
    variations($name, $settings, $mode['variations']);
}

function printMode(string $name, array $settings) : void {
    global $system, $format, $evo6Fields, $evo5ExclusiveFields;
    if ($system === 'evo5') {
        foreach ($evo6Fields as $field) {
            unset($settings[$field]);
        }
    }
    else {
        foreach ($evo5ExclusiveFields as $field) {
            unset($settings[$field]);
        }
    }

    $settings['Title'] = $name;

    switch ($format) {
        case 'csv':
            echo implode(',', array_map('formatCsvValue', $settings))."\n";
            break;
        default:
            $columns = implode(', ', array_map(fn($column) => "[$column]", array_keys($settings)));
            $values = implode(', ', array_map('formatSqlValue', array_values($settings)));
            echo "INSERT INTO Profiles ($columns) VALUES ($values);\n";
            break;
    }
}

function formatCsvValue(mixed $value) : string {
    if (is_string($value)) {
        return "\"$value\"";
    }
    if (is_bool($value)) {
        return '"'.($value ? 'true' : 'false').'"';
    }
    if ($value === null) {
        return '""';
    }
    return (string) $value;
}

function formatSqlValue(mixed $value) : mixed {
    if (is_string($value)) {
        return "'$value'";
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return 'null';
    }
    return $value;
}

function variations(string $name, array $settings, array $others) : void {
    if (count($others) === 0) {
        printMode($name, $settings);
        return;
    }
    $nextVariation = array_shift($others);

    foreach ($nextVariation as $key => $variationSettings) {
        $variationName = $name.$key;
        $variationSettings = array_merge($settings, $variationSettings);
        variations($variationName, $variationSettings, $others);
    }
}