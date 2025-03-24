<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\App;
use LAC\Modules\Core\Module;
use Symfony\Component\Console\Output\OutputInterface;

class Install implements InstallInterface
{
    use InstallPrints;


    public static function install(bool $fresh = false, ?OutputInterface $output = null) : bool {
        self::printInfo('Starting installation', $output);
        if (DbInstall::install($fresh, $output) && Seeder::install($fresh, $output) && self::installModules($output)) {
            self::printInfo('Installation successful', $output);
            return true;
        }
        else {
            self::printError('Installation failed', $output);
            return false;
        }
    }

    private static function installModules(?OutputInterface $output) : bool {
        /** @var string[] $modules */
        $modules = App::getContainer()->findByType(Module::class);
        foreach ($modules as $moduleName) {
            /** @var Module $module */
            $module = App::getService($moduleName);
            self::printInfo('Installing module '.$module::NAME, $output);
            $module->install();
        }
        return true;
    }
}
