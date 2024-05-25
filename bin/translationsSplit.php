<?php

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translations as GettextTranslations;
use Lsr\Helpers\Cli\CliHelper;

const ROOT = __DIR__.'/../';
const INDEX = false;

const CHECK_TRANSLATIONS = true;
const TRANSLATIONS_COMMENTS = false;

require_once ROOT."vendor/autoload.php";
require_once ROOT.'include/config.php';

$skipContext = array_map('trim', explode(',', $argv[2] ?? ''));

function errorPrint(string $message, ...$args) : void {
    CliHelper::printErrorMessage($message, ...$args);
}

function getSupportedLanguages() : array {
    $return = [];
    // By default, load all languages in language directory
    /** @var string[] $files */
    $files = glob(LANGUAGE_DIR.'*');
    $languages = array_map(
      static function (string $dir) {
          return str_replace(LANGUAGE_DIR, '', $dir);
      },
      $files
    );

    foreach ($languages as $language) {
        $explode = explode('_', $language);
        if (count($explode) !== 2) {
            continue;
        }
        [$lang, $country] = $explode;
        $return[$lang] = $country;
    }

    return $return;
}

$poLoader = new PoLoader();
/** @var GettextTranslations[][] $translations */
$translations = [];
/** @var string[] $languages */
$languages = getSupportedLanguages();
foreach ($languages as $lang => $country) {
    $concatLang = $lang.'_'.$country;
    $path = LANGUAGE_DIR.'/'.$concatLang.'/LC_MESSAGES/';
    if (!is_dir($path)) {
        continue;
    }
    $translations[$concatLang] = [];
    foreach (glob($path.'*.po') as $file) {
        $domain = str_replace([$path, '.po'], '', $file);
        $translations[$concatLang][$domain] = $poLoader->loadFile($file);
    }
}

// Extract translations from LAC into separate domains
foreach ($translations as $lang => $domains) {
    $core = $domains[LANGUAGE_FILE_NAME];

    /** @var \Gettext\Translation $translation */
    foreach ($core->getTranslations() as $translation) {
        $context = $translation->getContext();
        foreach ($domains as $domain => $domainTranslations) {
            if ($domain === LANGUAGE_FILE_NAME) {
                continue;
            }
            if (isset($context) && str_starts_with($context, $domain)) {
                $context = substr($context, strlen($domain));
                // Remove leading '.'
                if ($context !== '' && $context[0] === '.') {
                    $context = substr($context, 1);
                }
                if ($context === '') {
                    $context = null;
                }

                $domainTranslation = $domainTranslations->find($context, $translation->getOriginal());
                if (!isset($domainTranslation)) {
                    $domainTranslation = $translation->withContext($context);
                }
                else {
                    $domainTranslation = $domainTranslation->mergeWith($translation)->withContext($context);
                }
                $domainTranslations->addOrMerge($domainTranslation);
                $core->remove($translation);
                break;
            }
        }
    }
}


// Save template files
$poGenerator = new PoGenerator();
// Save and compile translation files
$moGenerator = new MoGenerator();
foreach ($translations as $lang => $files) {
    foreach ($files as $domain => $file) {
        $poGenerator->generateFile($file, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.$domain.'.po');
        $moGenerator->generateFile($file, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.$domain.'.mo');
    }
}

// Generate templates
foreach ($translations as $lang => $files) {
    foreach ($files as $domain => $file) {
        foreach ($file->getTranslations() as $translation) {
            $translation->translate('');
            $pluralCount = count($translation->getPluralTranslations());
            if ($pluralCount > 0) {
                $plural = [];
                for ($i = 0; $i < $pluralCount; $i++) {
                    $plural[] = '';
                }
                $translation->translatePlural(...$plural);
            }
        }
        $poGenerator->generateFile($file, LANGUAGE_DIR.$domain.'.pot');
    }
    break;
}