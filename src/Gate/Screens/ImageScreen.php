<?php

namespace App\Gate\Screens;

use App\Core\App;
use App\Gate\Settings\AnimationType;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ImageScreenType;
use App\Gate\Settings\ImageSettings;
use App\Models\DataObjects\Image;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<ImageSettings>
 */
class ImageScreen extends GateScreen implements WithSettings, ReloadTimerInterface
{
    use WithReloadTimer;

    private ImageSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Obrázek', context: 'gate.screens');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.image';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/image.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        $type = ImageScreenType::tryFrom($data['type'] ?? '') ?? ImageScreenType::CENTER;
        $animation = AnimationType::tryFrom($data['animation'] ?? '') ?? AnimationType::FADE;
        $time = (int) ($data['time'] ?? 0);

        $keys = explode('[', str_replace(']', '', $data['key']));
        $uploadedImage = App::getRequest()->getUploadedFiles();
        foreach ($keys as $key) {
            $uploadedImage = $uploadedImage[$key] ?? [];
        }
        if (isset($uploadedImage['image']) && $uploadedImage['image'] instanceof UploadedFile && $uploadedImage['image']->getError(
          ) === UPLOAD_ERR_OK) {
            $dir = UPLOAD_DIR.'gate/';
            if (!file_exists($dir) && (!mkdir($dir) || !is_dir($dir))) {
                bdump('Error creating upload directory: '.$dir);
                $dir = UPLOAD_DIR;
            }
            $name = $uploadedImage['image']->getClientFilename();
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Validate image
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $fileName = $dir.$name;
                $uploadedImage['image']->moveTo($fileName);
                return new ImageSettings(new Image($fileName), $type, $animation, $time > 0 ? $time : null,);
            }

            bdump('Invalid file extension '.$extension);
        }

        if (!empty($data['current']) && file_exists($data['current'])) {
            return new ImageSettings(new Image($data['current']), $type, $animation, $time > 0 ? $time : null,);
        }

        return new ImageSettings(null, $type, $animation, $time > 0 ? $time : null,);
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        return $this->view(
          'gate/screens/image',
          [
            'settings' => $this->getSettings(),
            'image'    => $this->getSettings()->image,
            'addCss'   => ['gate/image.css'],
          ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings() : ImageSettings {
        if (!isset($this->settings)) {
            $this->settings = new ImageSettings();
        }
        return $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function setSettings(GateSettings $settings) : static {
        $this->settings = $settings;
        return $this;
    }
}