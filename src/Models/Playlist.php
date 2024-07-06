<?php

namespace App\Models;

use Dibi\DriverException;
use Dibi\Exception;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_playlist')]
class Playlist extends Model
{
    public const TABLE = 'playlists';

    public string $name;

    /** @var MusicMode[] */
    #[ManyToMany(through: 'playlist_music', foreignKey: 'id_music', localKey: 'id_playlist', class: MusicMode::class, loadingType: LoadingType::LAZY)]
    public array $music = [];

    private bool $loadedMusic = false;
    private bool $musicChanged = false;

    /**
     * @return MusicMode[]
     * @throws ValidationException
     */
    public function getMusic(): array {
        if (empty($this->music) && !$this->loadedMusic) {
            $this->music = MusicMode::query()
                                    ->where(
                                        'id_music IN %sql',
                                        DB::select('playlist_music', 'id_music')
                                        ->where('id_playlist = %i', $this->id)
                                        ->fluent
                                    )
                                    ->cacheTags($this::TABLE . '/' . $this->id . '/relations')
                                    ->get();
            $this->loadedMusic = true;
        }
        return $this->music;
    }

    public function hasMusicMode(MusicMode $musicMode): bool {
        foreach ($this->getMusic() as $music) {
            if ($musicMode->id === $music->id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int[]
     * @throws ValidationException
     */
    public function getMusicIds(): array {
        $ids = [];
        foreach ($this->getMusic() as $music) {
            $ids[] = $music->id;
        }
        return $ids;
    }

    public function save(): bool {
        return parent::save() && $this->saveMusic();
    }

    public function saveMusic(): bool {
        if (!$this->musicChanged) {
            return true;
        }

        try {
            DB::getConnection()->begin();
            DB::delete('playlist_music', ['id_playlist = %i', $this->id]);
            foreach ($this->music as $music) {
                DB::insert('playlist_music', ['id_playlist' => $this->id, 'id_music' => $music->id]);
            }
            DB::getConnection()->commit();
        } catch (DriverException | Exception $e) {
            $this->getLogger()->exception($e);
            DB::getConnection()->rollback();
            return false;
        }
        $this->clearCache();
        return true;
    }

    /**
     * @param  MusicMode[]  $music
     * @return $this
     */
    public function setMusic(array $music): static {
        $this->music = $music;
        $this->musicChanged = true;
        return $this;
    }
}
