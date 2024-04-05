<?php

namespace App\Gate\Settings;

/**
 * @property string $value
 * @method static AnimationType[] cases()
 * @method static AnimationType from(string $value)
 * @method static AnimationType|null tryFrom(string $value)
 */
enum AnimationType : string
{

	case FADE = 'fade';
	case SCALE = 'scale';
	case SLIDE_TOP = 'slide_top';
	case SLIDE_RIGHT = 'slide_right';
	case SLIDE_LEFT = 'slide_left';
	case SLIDE_BOTTOM = 'slide_bottom';

	public function getReadableName() : string {
		return match ($this) {
			self::FADE         => lang('Prolnutí', context: 'animation'),
			self::SCALE        => lang('Zmenšení', context: 'animation'),
			self::SLIDE_TOP    => lang('Posun shora', context: 'animation'),
			self::SLIDE_RIGHT  => lang('Posun z prava', context: 'animation'),
			self::SLIDE_LEFT   => lang('Posun z leva', context: 'animation'),
			self::SLIDE_BOTTOM => lang('Posun ze spodu', context: 'animation'),
		};
	}

}
