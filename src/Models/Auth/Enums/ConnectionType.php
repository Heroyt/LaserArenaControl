<?php

namespace App\Models\Auth\Enums;

/**
 * @property string $value
 * @method static ConnectionType from(string $type)
 * @method static ConnectionType|null tryFrom(string $type)
 */
enum ConnectionType: string
{

	case RFID = 'rfid';
	case LASER_FORCE = 'laserforce';

	case OTHER = 'other';

}