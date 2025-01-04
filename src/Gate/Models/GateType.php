<?php

namespace App\Gate\Models;

use App\Core\App;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use App\Models\BaseModel;
use Lsr\Caching\Cache;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;
use Nette\Utils\Strings;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_gate'), OA\Schema]
class GateType extends BaseModel
{
    public const string TABLE = 'gate_types';

    #[OA\Property(example: 'Main gate')]
    public string $name;
    #[OA\Property(example: 'main_gate')]
    public string $slug = '';
    #[OA\Property(example: 'This is the main gate that is shown on the screen right in front of the arena.')]
    public ?string $description = null;
    #[OA\Property]
    public bool $locked = false;

    /** @var ModelCollection<GateScreenModel> */
    #[OneToMany(class: GateScreenModel::class, factoryMethod: 'loadScreens')]
    public ModelCollection $screens;

    public static function getBySlug(string $slug) : ?GateType {
        $cache = App::getService('cache');
        assert($cache instanceof Cache);
        /** @var non-empty-string[] $tags */
        $tags = array_merge(
          ['models', self::TABLE, self::TABLE.'/'.$slug],
          self::CACHE_TAGS
        );
        return $cache->load(
          'gateType.slug.'.$slug,
          fn() => static::query()->where('slug = %s', $slug)->first(),
          [
            Cache::Tags   => $tags,
            Cache::Expire => '7 days',
          ]
        );
    }

    public function getQueryData() : array {
        $data = parent::getQueryData();
        if (empty($data['slug'])) {
            $data['slug'] = $this->getSlug();
        }
        return $data;
    }

    public function getSlug() : string {
        if (empty($this->slug)) {
            $this->slug = str_replace(' ', '-', strtolower(Strings::toAscii($this->name)));
            // Test uniqueness
            $count = static::query()->where('slug LIKE %like~', $this->slug)->count();
            if ($count > 0) {
                $this->slug .= '_'.$count;
            }
        }
        return $this->slug;
    }

    public function setSlug(string $slug) : GateType {
        $this->slug = $slug;
        return $this;
    }

    public function addScreenModel(GateScreenModel ...$screens) : GateType {
        foreach ($screens as $screen) {
            $this->screens->add($screen);
            $screen->gate = $this;
        }

        return $this;
    }

    public function removeScreenModel(GateScreenModel ...$screens) : GateType {
        foreach ($screens as $key2 => $findScreen) {
            foreach ($this->screens as $key => $screen) {
                if ($screen->id === $findScreen->id) {
                    unset($this->screens[$key], $screens[$key2]);
                    $findScreen->delete();
                    break;
                }
            }
        }
        return $this;
    }

    public function addScreen(
      GateScreen        $screen,
      ?GateSettings     $settings = null,
      ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT,
      int               $order = 0
    ) : GateType {
        $screenModel = new GateScreenModel();
        $screenModel->setScreen($screen);
        if (isset($settings)) {
            $screenModel->setSettings($settings);
        }
        $screenModel->order = $order;
        $screenModel->trigger = $trigger;
        $this->screens->add($screenModel);
        $screenModel->gate = $this;

        return $this;
    }

    public function findScreen(
      string            $screenType,
      ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT,
    ) : ?GateScreenModel {
        foreach ($this->screens as $screen) {
            if ($screen->screenSerialized === $screenType && $screen->trigger === $trigger) {
                return $screen;
            }
        }
        return null;
    }

    /**
     * @param  ScreenTriggerType  $trigger
     * @return GateScreenModel[]
     * @throws ValidationException
     */
    public function getScreensForTrigger(ScreenTriggerType $trigger) : array {
        $screens = [];
        foreach ($this->screens as $screen) {
            if ($screen->trigger === $trigger) {
                $screens[] = $screen;
            }
        }
        return $screens;
    }

    public function save() : bool {
        return parent::save() && $this->saveScreens();
    }

    public function saveScreens() : bool {
        $success = true;

        if (isset($this->screens)) {
            foreach ($this->screens as $screen) {
                if (!isset($screen->gate)) {
                    $screen->gate = $this;
                }
                $success = $success && $screen->save();
            }
        }

        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->remove('gateType.'.$this->id.'.screens');

        return $success;
    }

    public function setName(string $name) : GateType {
        $this->name = $name;
        return $this;
    }

    public function setDescription(?string $description) : GateType {
        $this->description = $description;
        return $this;
    }

    public function setLocked(bool $locked) : GateType {
        $this->locked = $locked;
        return $this;
    }

    public function getUrl() : string {
        return App::getLink($this->getPath());
    }

    /**
     * @return string[]
     */
    public function getPath() : array {
        return ['gate', $this->slug];
    }

    public function clearCache() : void {
        parent::clearCache();
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean(
          [
            $cache::Tags => ['core.menu'],
          ]
        );
    }

    /**
     * @return ModelCollection<GateScreenModel>
     */
    protected function loadScreens() : ModelCollection {
        if (!isset($this->id)) {
            return new ModelCollection();
        }

        /** @var non-empty-string[] $tags */
        $tags = array_merge(
          [
            'models',
            GateScreenModel::TABLE,
            $this::TABLE,
            $this::TABLE.'/'.$this->id,
            $this::TABLE.'/'.$this->id.'/relations',
          ],
          GateScreenModel::CACHE_TAGS
        );

        $cache = App::getService('cache');
        assert($cache instanceof Cache);
        return new ModelCollection(
          $cache->load(
            'gateType.'.$this->id.'.screens',
            fn() => GateScreenModel::query()
                                   ->where('id_gate = %i', $this->id)
                                   ->orderBy('order')
                                   ->get(),
            [
              Cache::Tags   => $tags,
              Cache::Expire => '7 days',
            ]
          )
        );
    }
}
