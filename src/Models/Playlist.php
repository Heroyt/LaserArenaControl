<?php

namespace App\Models;

use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_playlist')]
class Playlist extends Model
{

	public const TABLE = 'playlists';

	public string $name;

	/** @var MusicMode[] */
	#[ManyToMany(through: 'playlist_music', foreignKey: 'id_music', localKey: 'id_playlist', class: MusicMode::class)]
	public array $music = [];

}