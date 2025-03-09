<?php
declare(strict_types=1);

enum AmmoClipsSettings : int
{
    case OFF                                = 0;
    case RELOAD_AFTER_5_SECONDS             = 41;
    case RELOAD_AFTER_5_TRIGGER_PULLS       = 42;
    case RELOAD_AFTER_PRESSING_CHEST_BUTTON = 3;
}

enum GameType : int
{
    case SOLO                = 0;
    case TEAM                = 1;
    case TEAM_CAPTURE        = 2;
    case ZOMBIES_SOLO        = 3;
    case ZOMBIES_TEAM        = 4;
    case VIP                 = 5;
    case CROSSFIRE           = 6;
    case SENSORTAG_SOLO      = 7;
    case SENSORTAG_TEAM      = 8;
    case ROCK_PAPER_SCISSORS = 9;
    case PARALLEL            = 10;
}

enum TeamOrSolo : int
{
    case TEAM = 0;
    case SOLO = 1;
}

enum Flash : int
{
    case OFF              = 0;
    case ON               = 1;
    case FADE_ULTRA_FAST  = 2;
    case FADE_VERY_FAST   = 3;
    case FADE_FAST        = 4;
    case FADE_NORMAL      = 5;
    case FADE_SLOW        = 6;
    case BLINK_ULTRA_FAST = 7;
    case BLINK_VERY_FAST  = 8;
    case BLINK_FAST       = 9;
    case BLINK_NORMAL     = 10;
    case BLINK_SLOW       = 11;
    case HEART            = 12;
    case HEART_FAST       = 13;
    case HEART_INVERSE    = 14;
    case FADE_TO_OFF      = 15;
    case RAINBOW          = 16;
    case POWER_BLINK      = 17;
    case POLICE_SIREN     = 18;

    public function evo6Value() : int {
        return $this->value;
    }

    public function evo5Value() : int {
        return match ($this) {
            self::FADE_TO_OFF                 => self::FADE_SLOW->value,
            self::POWER_BLINK                 => self::BLINK_FAST->value,
            self::POLICE_SIREN, self::RAINBOW => 15, // Evo5 rainbow
            default                           => $this->value,
        };
    }
}

$system = $argv[1] ?? 'evo6';
$format = $argv[2] ?? 'sql';

$defaultSettings = [
  "Title"                     => 'Game mode',
  "Description"               => '',
  "HighScore"                 => 0,
  "HighScoreName"             => null,
  // 0 = team, 1 = solo
  "TeamOrSolo"         => TeamOrSolo::TEAM,
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
  "ArmedFlash"         => Flash::ON,
  "StartFlash"         => Flash::FADE_NORMAL,
  "PlayFlash"          => Flash::ON,
  "HitFlash"           => Flash::OFF,
  "GameoverFlash"      => Flash::RAINBOW,
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
  "MineType05"         => 2,
  "MineTeam05"         => 6,
  "MineName05"         => 'MiniMina',
  "MineOn06"           => true,
  "MineType06"         => 2,
  "MineTeam06"         => 6,
  "MineName06"         => 'MinaHorni',
  "MineOn07"           => true,
  "MineType07"         => 2,
  "MineTeam07"         => 6,
  "MineName07"         => 'MinaD/Brana',
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
  "FormatNumber"       => GameType::TEAM,
  "VipTerminate"              => 0,
  "KnockoutOn"                => false,
  "KnockoutBonusPoints"       => 100,
  "AmmoClips"          => AmmoClipsSettings::OFF,
  // 0 = off, 41 = reload after 5 seconds, 42 = reload after 5 trigger pulls, 3 = reload after pressing chest button
  "Reality"                   => '0',
  "PrintCards"                => false,
  "AmmoAsClips"        => clips(15),
  "AmmoAsClipsTrooper" => clips(15),
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
$gameLengthVariationsMin = [
  ''    => [
    'PlayTime' => 15,
  ],
  '-10' => [
    'PlayTime' => 10,
  ],
];
$gameLengthVariationsExtended = [
  ''    => [
    'PlayTime' => 15,
  ],
  '-13' => [
    'PlayTime' => 13,
  ],
  '-12' => [
    'PlayTime' => 12,
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
  '00-T-TURNAJ'   => [
    'settings'   => [
      'Description'  => 'Klasicka tymova hra - turnaj',
      'TeamOrSolo'   => TeamOrSolo::TEAM,
      'FormatNumber' => GameType::TEAM,
    ],
    'variations' => $noVariations,
  ],
  '00-S-TURNAJ'   => [
    'settings'   => [
      'Description'  => 'Klasicka solo hra - turnaj',
      'TeamOrSolo'   => TeamOrSolo::SOLO,
      'FormatNumber' => GameType::SOLO,
    ],
    'variations' => $noVariations,
  ],
  '01-T-DM'       => [
    'settings'   => [
      'Description'  => 'Klasicka tymova hra',
      'TeamOrSolo'   => TeamOrSolo::TEAM,
      'FormatNumber' => GameType::TEAM,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariationsExtended,
      'pods'       => $podsVariations,
      'hitstreak'  => $hitStreakVariations,
    ],
  ],
  '01-T-TMA'      => [
    'settings'   => [
      'Description'          => 'Klasicka tymova hra ve tme',
      'TeamOrSolo'         => TeamOrSolo::TEAM,
      'PacksColor'           => 0,
      'PlayFlash'          => Flash::OFF,
      'HitFlash'           => Flash::HEART,
      'AmmoClips'          => AmmoClipsSettings::RELOAD_AFTER_5_TRIGGER_PULLS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'HitStreakOn'          => true,
      'HitstreakLength'      => 5,
      'HitstreakReward'      => 12,
      'AmmoAsClips'        => clips(15),
      'AmmoAsClipsTrooper' => clips(15),
      'TeamHits'             => false,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
    ],
  ],
  '02-S-DM'       => [
    'settings'   => [
      'Description'  => 'Klasicka solo hra',
      'TeamOrSolo'   => TeamOrSolo::SOLO,
      'FormatNumber' => GameType::SOLO,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariationsExtended,
      'pods'       => $podsVariations,
      'hitstreak'  => $hitStreakVariations,
    ],
  ],
  '02-S-TMA'      => [
    'settings'   => [
      'Description'          => 'Klasicka solo hra ve tme',
      'TeamOrSolo'         => TeamOrSolo::SOLO,
      'FormatNumber'       => GameType::SOLO,
      'PacksColor'           => 0,
      'PlayFlash'          => Flash::OFF,
      'HitFlash'           => Flash::HEART,
      'AmmoClips'          => AmmoClipsSettings::RELOAD_AFTER_5_TRIGGER_PULLS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'HitStreakOn'          => true,
      'HitstreakLength'      => 5,
      'HitstreakReward'      => 12,
      'AmmoAsClips'        => clips(15),
      'AmmoAsClipsTrooper' => clips(15),
      'TeamHits'             => false,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
    ],
  ],
  '03-S-NABOJU'   => [
    'settings'   => [
      'Description'  => 'Solo hra s omezenym poctem naboju',
      'TeamOrSolo'   => TeamOrSolo::SOLO,
      'FormatNumber' => GameType::SOLO,
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
  '04-S-SURVIVAL' => [
    'settings'   => [
      'Description'          => 'Omezeny pocet zivotu a naboju',
      'TeamOrSolo'           => TeamOrSolo::SOLO,
      'FormatNumber'         => GameType::SOLO,
      'Ammo'                 => 300,
      'Lives'                => 30,
      'AmmoClips'            => AmmoClipsSettings::RELOAD_AFTER_5_SECONDS, // Auto-reload after 5 seconds
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => clips(10, 30), // 30*10
      'AmmoAsClipsTrooper'   => clips(10, 30), // 30*10
    ],
    'variations' => [
      'pods' => $podsVariations,
    ],
  ],
  '04-T-SURVIVAL' => [
    'settings'   => [
      'Description'          => 'Omezeny pocet zivotu a naboju',
      'TeamOrSolo'           => TeamOrSolo::TEAM,
      'FormatNumber'         => GameType::TEAM,
      'Ammo'                 => 300,
      'Lives'                => 30,
      'AmmoClips'            => AmmoClipsSettings::RELOAD_AFTER_5_SECONDS, // Auto-reload after 5 seconds
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => clips(10, 30), // 30*10
      'AmmoAsClipsTrooper'   => clips(10, 30), // 30*10
    ],
    'variations' => [
      'pods' => $podsVariations,
    ],
  ],
  '05-ZAKLADNY'   => [
    'settings'   => [
      'Description'          => 'Takticka hra dvou tymu, kteri si vzajemne utoci na zakladny.',
      'TeamOrSolo'         => TeamOrSolo::TEAM,
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
      'AmmoClips'          => AmmoClipsSettings::RELOAD_AFTER_5_TRIGGER_PULLS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'        => clips(15),
      'AmmoAsClipsTrooper' => clips(15),
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
  '06-CSGO'       => [
    'settings'   => [
      'Description' => 'Kazdy tym zacina v jednom z domecku. Kazdy ma jen 3 zivoty.',
      'Lives'       => 3,
      'TeamOrSolo' => TeamOrSolo::TEAM,
      'HitTime'     => 1,
    ],
    'variations' => $noVariations,
  ],
  '07-APOKALYPSA' => [
    'settings'   => [
      'Description'           => 'Zombie mod. Cerveny tym jsou zombie a snazi se infikovat ostatni tymy. Zombie ma 10 zivotu. Hrac se infikuje po 3 zasazich.',
      'TeamOrSolo'            => TeamOrSolo::TEAM,
      'FormatNumber'          => GameType::ZOMBIES_TEAM,
      'VampireOn'             => true,
      'VampireColor'          => 0,
      'VampireLives'          => 10,
      'VampireAmmo'           => 9999,
      'VampireSpecialPlayers' => false,
      'VampireResistance'     => 3,
      'VampireRainbow'        => false,
    ],
    'variations' => $noVariations,
  ],
  '08-BARVICKY'   => [
    'settings'   => [
      'Description'      => 'Silena hra, kdy po trech zasazich zmenis barvu tymu na toho, kdo te trefil posledni.',
      'TeamOrSolo'       => TeamOrSolo::TEAM,
      'FormatNumber'     => GameType::TEAM_CAPTURE,
      'SwitchOn'         => true,
      'SwitchResistance' => 3,
    ],
    'variations' => $noVariations,
  ],
  '09-KNP'        => [
    'system'     => 'evo6',
    'settings'   => [
      'Description'  => 'Kamen, nuzky, papir. Cerveny tym je kamen, zeleny nuzky, modry papir.',
      "TeamHits"     => true,
      'TeamOrSolo'   => TeamOrSolo::TEAM,
      'FormatNumber' => GameType::ROCK_PAPER_SCISSORS,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariationsMin,
      'pods'       => $podsVariations,
    ],
  ],
  '10-T-REVOLVER' => [
    'system'     => 'evo6',
    'settings'   => [
      'Description'  => 'Omezeny pocet naboju v zasobniku. Pomalejsi strelba. Dlouhe prebijeni.',
      "TeamHits"             => true,
      'TeamOrSolo'           => TeamOrSolo::TEAM,
      'FormatNumber'         => GameType::TEAM,
      'HitgainAmmo'          => 1,
      'AmmoClips'            => AmmoClipsSettings::RELOAD_AFTER_5_SECONDS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => clips(6),
      'AmmoAsClipsTrooper'   => clips(6),
      'TriggerSpeed' => 1,
    ],
    'variations' => [
      'ammo' => [
        '-6'  => [
          'AmmoAsClips'        => clips(6),
          'AmmoAsClipsTrooper' => clips(6),
        ],
        '-10' => [
          'AmmoAsClips'        => clips(10),
          'AmmoAsClipsTrooper' => clips(10),
        ],
      ],
      'pods' => $podsVariations,
    ],
  ],
  '10-S-REVOLVER' => [
    'system'     => 'evo6',
    'settings'   => [
      'Description'  => 'Omezeny pocet naboju v zasobniku. Pomalejsi strelba. Dlouhe prebijeni.',
      'TeamOrSolo'           => TeamOrSolo::SOLO,
      'FormatNumber'         => GameType::SOLO,
      'HitgainAmmo'          => 1,
      'AmmoClips'            => AmmoClipsSettings::RELOAD_AFTER_5_SECONDS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => clips(6),
      'AmmoAsClipsTrooper'   => clips(6),
      'TriggerSpeed' => 1,
    ],
    'variations' => [
      'ammo' => [
        '-6'  => [
          'AmmoAsClips'        => clips(6),
          'AmmoAsClipsTrooper' => clips(6),
        ],
        '-10' => [
          'AmmoAsClips'        => clips(10),
          'AmmoAsClipsTrooper' => clips(10),
        ],
      ],
      'pods' => $podsVariations,
    ],
  ],
  '12-GLADIATOR'  => [
    'system'     => 'evo6',
    'settings'   => [
      'Description'          => 'Omezeny pocet zivotu, za zasah hrac zivot ziska zpet, pokud hraci zivoty dojdou, musi cekat 20s na oziveni.',
      'TeamOrSolo'           => TeamOrSolo::SOLO,
      'FormatNumber'         => GameType::SOLO,
      'HitgainLives'         => 1,
      'Lives'                => 10,
      'AmmoClips'            => AmmoClipsSettings::RELOAD_AFTER_5_SECONDS,
      'VirtualAmmoClips'     => true,
      'VirtualAmmoClipsAuto' => true,
      'AmmoAsClips'          => clips(15),
      'AmmoAsClipsTrooper'   => clips(15),
      "RespawnWhen"          => 1,
      "RespawnLives"         => 4,
      "RespawnWhenParam1"    => 30,
      "ShowdownOn"           => true,
      "ShowdownLeds"         => 3,
      "ShowdownBlast"        => false,
      "ShowdownMinutes"      => 1,
      "ShowdownHittype"      => 2,
      "AssistOn"             => true,
      "AssistMakeDoubleHits" => true,
    ],
    'variations' => [
      'gameLength' => $gameLengthVariations,
      'pods'       => $podsVariations,
    ],
  ],
];

$inserts = [];
$count = 0;

// Print csv header
if ($format === 'csv') {
    echo implode(
        ',',
        array_map(
          fn(string $value) => "\"$value\"",
          array_filter(
            array_keys($defaultSettings),
            fn(string $key) => ($system === 'evo5' && !in_array($key, $evo6Fields, true))
              || ($system === 'evo6' && !in_array($key, $evo5ExclusiveFields, true))
          )
        )
      )."\n";
}

foreach ($modes as $name => $mode) {
    if (isset($mode['system']) && $mode['system'] !== $system) {
        continue;
    }
    $settings = array_merge($defaultSettings, $mode['settings']);
    variations($name, $settings, $mode['variations']);
}

fwrite(STDERR, "$count modes generated\n");
if ($count > 100) {
    // Print a red warning on stderr
    fwrite(STDERR, "\033[1;31mWarning: More than 100 modes generated.\033[0m\n");
}

function printMode(string $name, array $settings) : void {
    global $system, $format, $evo6Fields, $evo5ExclusiveFields, $count;
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
    $count++;
}

function formatCsvValue(mixed $value) : string {
    if ($value instanceof BackedEnum) {
        $value = enumValue($value);
    }

    if (is_string($value)) {
        return "\"$value\"";
    }
    if (is_int($value)) {
        return (string) $value;
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
    if ($value instanceof BackedEnum) {
        $value = enumValue($value);
    }

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

function enumValue(BackedEnum $enum) : mixed {
    global $system;
    if ($system === 'evo5' && method_exists($enum, 'evo5Value')) {
        return $enum->evo5Value();
    }
    if ($system === 'evo6' && method_exists($enum, 'evo6Value')) {
        return $enum->evo6Value();
    }
    return $enum->value;
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

/**
 * @param  int<0,255>  $ammoPerClip
 * @param  int<0,255>  $clips
 * @return int
 */
function clips(int $ammoPerClip, int $clips = 255) : int {
    assert($ammoPerClip > 0 && $ammoPerClip < 256);
    assert($clips > 0 && $clips < 256);

    return $ammoPerClip + ($clips << 8);
}