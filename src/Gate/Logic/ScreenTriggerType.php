<?php

namespace App\Gate\Logic;

enum ScreenTriggerType : string
{

	case DEFAULT = 'default';
	case GAME_LOADED = 'game_loaded';
	case GAME_PLAYING = 'game_playing';
	case GAME_ENDED = 'game_ended';

	case CUSTOM = 'custom';

}
