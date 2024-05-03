<?php

namespace App\Cli\Commands\Translation;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\App;
use Gettext\Generator\MoGenerator;
use Gettext\Loader\PoLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileTranslationsCommand extends Command
{

	public static function getDefaultName(): ?string {
		return 'translations:compile';
	}

	public static function getDefaultDescription(): ?string {
		return 'Compile all translation PO files into MO.';
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$poLoader = new PoLoader();
		$moGenerator = new MoGenerator();
		/** @var string[] $languages */
		$languages = App::getSupportedLanguages();
		foreach ($languages as $lang => $country) {
			$concatLang = $lang . '_' . $country;
        $path = LANGUAGE_DIR.$concatLang;
			if (!is_dir($path)) {
				continue;
			}
			$file = $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po';
        $output->writeln('Loading '.$file);
			$translation = $poLoader->loadFile($file);
			if ($moGenerator->generateFile(
				$translation,
        $path.'/LC_MESSAGES/'.LANGUAGE_FILE_NAME.'.mo'
			)) {
				$output->writeln('Compiled ' . $file);
			}
		}

		$output->writeln(
			Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
		);
		return self::SUCCESS;
	}

}