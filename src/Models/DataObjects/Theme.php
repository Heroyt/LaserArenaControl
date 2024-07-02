<?php

namespace App\Models\DataObjects;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\Tools\Color;
use Dibi\Exception;

/**
 *
 */
final class Theme
{

    public const string INFO_KEY = 'lac_theme';

    private static Theme $instance;

    public function __construct(
      public string $primaryColor = '#339af0',
      public string $secondaryColor = '#304d99ff',
    ) {}

    public static function getCssVersion() : int {
        $time = filemtime(ROOT.'dist/theme.css');
        if ($time === false) {
            return 1;
        }
        return $time;
    }

    public static function get() : Theme {
        if (isset(self::$instance)) {
            return self::$instance;
        }
        $theme = Info::get(self::INFO_KEY);
        if ($theme instanceof self) {
            self::$instance = $theme;
            return $theme;
        }
        self::$instance = new self();
        return self::$instance;
    }

    public function getCss() : string {
        $primaryColor = $this->primaryColor;
        $primaryColorText = Color::getFontColor($this->primaryColor);
        $secondaryColor = $this->secondaryColor;
        $secondaryColorText = Color::getFontColor($this->secondaryColor);

        $gameColorsRoot = '';
        $gameColorsClasses = '';
        foreach (GameFactory::getAllTeamsColors() as $system => $systemColors) {
            foreach ($systemColors as $key => $color) {
                $textColor = Color::getFontColor($color);
                $gameColorsRoot .= <<<CSS
                    --team-{$system}-{$key}: $color;
                    --team-{$system}-{$key}-text: $textColor;

                CSS;
                $gameColorsClasses .= <<<CSS
                .bg-team-{$system}-{$key} {
                    --bg-color: var(--team-{$system}-{$key});
                    --bs-table-bg: var(--team-{$system}-{$key});
                    --text-color: var(--team-{$system}-{$key}-text);
                    background-color: var(--team-{$system}-{$key});
                }
                .text-bg-team-{$system}-{$key} {
                    --bg-color: var(--team-{$system}-{$key});
                    --bs-table-bg: var(--team-{$system}-{$key});
                    --text-color: var(--team-{$system}-{$key}-text);
                    background-color: var(--team-{$system}-{$key});
                    color: var(--team-{$system}-{$key}-text);
                }
                .text-team-{$system}-{$key} {
                    --text-color: var(--team-{$system}-{$key});
                    color: var(--team-{$system}-{$key});
                }

                CSS;
            }
        }
        return <<<CSS
        :root {
            /* Theme */
            --theme-primary-color: $primaryColor;
            --theme-primary-color-text: $primaryColorText;
            --theme-secondary-color: $secondaryColor;
            --theme-secondary-color-text: $secondaryColorText;
            
            /* Game colors */
        $gameColorsRoot
        }
        
        .bg-theme-primary {
            --bg-color: var(--theme-primary-color);
            --bs-table-bg: var(--theme-primary-color);
            --text-color: var(--theme-primary-color-text);
            background-color: var(--theme-primary-color);
        }
        .text-bg-theme-primary {
            --bg-color: var(--theme-primary-color);
            --bs-table-bg: var(--theme-primary-color);
            --text-color: var(--theme-primary-color-text);
            background-color: var(--theme-primary-color);
            color: var(--theme-primary-color-text);
        }
        .text-theme-primary {
            --text-color: var(--theme-primary-color);
            color: var(--theme-primary-color);
        }
        .bg-theme-secondary {
            --bg-color: var(--theme-secondary-color);
            --bs-table-bg: var(--theme-secondary-color);
            --text-color: var(--theme-secondary-color-text);
            background-color: var(--theme-secondary-color);
        }
        .text-bg-theme-secondary {
            --bg-color: var(--theme-secondary-color);
            --bs-table-bg: var(--theme-secondary-color);
            --text-color: var(--theme-secondary-color-text);
            background-color: var(--theme-secondary-color);
            color: var(--theme-secondary-color-text);
        }
        .text-theme-secondary {
            --text-color: var(--theme-secondary-color);
            color: var(--theme-secondary-color);
        }
        $gameColorsClasses
        CSS;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function save() : void {
        self::$instance = $this;
        Info::set(self::INFO_KEY, $this);
    }

}