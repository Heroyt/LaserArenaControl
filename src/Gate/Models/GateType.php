<?php

namespace App\Gate\Models;

use App\Core\App;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;
use Nette\Utils\Strings;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_gate'), OA\Schema]
class GateType extends Model
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

    /** @var GateScreenModel[] */
    #[OneToMany(class: GateScreenModel::class, loadingType: LoadingType::LAZY)]
    public array $screens;

    public static function getBySlug(string $slug): ?GateType {
        /** @var Cache $cache */
        $cache = App::getService('cache');
        return $cache->load(
            'gateType.slug.' . $slug,
            fn() => static::query()->where('slug = %s', $slug)->first(),
            [
            $cache::Tags   => array_merge(
                ['models', self::TABLE, self::TABLE . '/' . $slug],
                self::CACHE_TAGS
            ),
            $cache::Expire => '7 days',
            ]
        );
    }

    public function getQueryData(): array {
        $data = parent::getQueryData();
        if (empty($data['slug'])) {
            $data['slug'] = $this->getSlug();
        }
        return $data;
    }

    public function getSlug(): string {
        if (empty($this->slug)) {
            $this->slug = str_replace(' ', '-', strtolower(Strings::toAscii($this->name)));
            // Test uniqueness
            $count = static::query()->where('slug LIKE %like~', $this->slug)->count();
            if ($count > 0) {
                $this->slug .= '_' . $count;
            }
        }
        return $this->slug;
    }

    public function setSlug(string $slug): GateType {
        $this->slug = $slug;
        return $this;
    }

    public function addScreenModel(GateScreenModel ...$screens): GateType {
        // Load screens if needed
        $this->getScreens();

        foreach ($screens as $screen) {
            $this->screens[] = $screen;
            $screen->gate = $this;
        }

        return $this;
    }

    /**
     * @return GateScreenModel[]
     * @throws ValidationException
     */
    public function getScreens(): array {
        if (!isset($this->screens)) {
            if (!isset($this->id)) {
                return [];
            }
            /** @var Cache $cache */
            $cache = App::getService('cache');
            $this->screens = $cache->load(
                'gateType.' . $this->id . '.screens',
                fn() => $this->loadScreens(),
                [
                $cache::Tags   => array_merge(
                    [
                    'models',
                    GateScreenModel::TABLE,
                    $this::TABLE,
                    $this::TABLE . '/' . $this->id,
                    $this::TABLE . '/' . $this->id . '/relations',
                    ],
                    GateScreenModel::CACHE_TAGS
                ),
                $cache::Expire => '7 days',
                ]
            );
        }
        return $this->screens;
    }

    private function loadScreens(): array {
        $this->screens = isset($this->id) ?
          GateScreenModel::query()
                         ->where('id_gate = %i', $this->id)
                         ->orderBy('order')
                         ->get() :
          [];
        return $this->screens;
    }

    public function removeScreenModel(GateScreenModel ...$screens): GateType {
        // Load screens if needed
        $this->getScreens();

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
    ): GateType {
        // Load screens if needed
        $this->getScreens();

        $screenModel = new GateScreenModel();
        $screenModel->setScreen($screen);
        if (isset($settings)) {
            $screenModel->setSettings($settings);
        }
        $screenModel->order = $order;
        $screenModel->trigger = $trigger;
        $this->screens[] = $screenModel;
        $screenModel->gate = $this;

        return $this;
    }

    public function findScreen(
        string            $screenType,
        ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT,
    ): ?GateScreenModel {
        foreach ($this->getScreens() as $screen) {
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
    public function getScreensForTrigger(ScreenTriggerType $trigger): array {
        $screens = [];
        foreach ($this->getScreens() as $screen) {
            if ($screen->trigger === $trigger) {
                $screens[] = $screen;
            }
        }
        return $screens;
    }

    public function save(): bool {
        return parent::save() && $this->saveScreens();
    }

    public function saveScreens(): bool {
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
        $cache->remove('gateType.' . $this->id . '.screens');

        return $success;
    }

    public function setName(string $name): GateType {
        $this->name = $name;
        return $this;
    }

    public function setDescription(?string $description): GateType {
        $this->description = $description;
        return $this;
    }

    public function setLocked(bool $locked): GateType {
        $this->locked = $locked;
        return $this;
    }

    public function getUrl(): string {
        return App::getLink($this->getPath());
    }

    /**
     * @return string[]
     */
    public function getPath(): array {
        return ['gate', $this->slug];
    }

    public function clearCache(): void {
        parent::clearCache();
        /** @var Cache $cache */
        $cache = App::getService('cache');
        $cache->clean(
            [
            $cache::Tags => ['core.menu'],
            ]
        );
    }
}
