<?php

namespace App\Models;

use Dibi\DriverException;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Interfaces\LoadedModel;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_playlist')]
class Playlist extends BaseModel
{
    public const string TABLE = 'playlists';

    public string $name;

    /** @var ModelCollection<MusicMode&LoadedModel> */
    #[ManyToMany(through: 'playlist_music', foreignKey: 'id_music', localKey: 'id_playlist', class: MusicMode::class)]
    public ModelCollection $music;

    private bool $loadedMusic = false;
    private bool $musicChanged = false;

    public function hasMusicMode(MusicMode $musicMode) : bool {
        foreach ($this->getMusic() as $music) {
            if ($musicMode->id === $music->id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return ModelCollection<MusicMode&LoadedModel>
     */
    public function getMusic() : ModelCollection {
        if (empty($this->music) && !$this->loadedMusic) {
            $this->music = new ModelCollection(
              MusicMode::query()
                       ->where(
                         'id_music IN %sql',
                         DB::select('playlist_music', 'id_music')
                           ->where('id_playlist = %i', $this->id)
                           ->fluent
                       )
                       ->cacheTags($this::TABLE.'/'.$this->id.'/relations')
                       ->get()
            );
            $this->loadedMusic = true;
        }
        return $this->music;
    }

    /**
     * @param  (MusicMode&LoadedModel)[]|ModelCollection<MusicMode&LoadedModel>  $music
     * @return $this
     */
    public function setMusic(array | ModelCollection $music) : static {
        $this->music = $music instanceof ModelCollection ? $music : new ModelCollection($music);
        $this->musicChanged = true;
        return $this;
    }

    /**
     * @return int[]
     */
    public function getMusicIds() : array {
        $ids = [];
        foreach ($this->getMusic() as $music) {
            assert($music->id !== null);
            $ids[] = $music->id;
        }
        return $ids;
    }

    public function save() : bool {
        return parent::save() && $this->saveMusic();
    }

    public function saveMusic() : bool {
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
}
