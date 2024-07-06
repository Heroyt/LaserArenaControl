<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Install;

use App\Core\App;
use LAC\Modules\Core\Module;

class Install implements InstallInterface
{
    public static function install(bool $fresh = false): bool {
        return DbInstall::install($fresh) && Seeder::install($fresh) && self::installModules();
    }

    private static function installModules(): bool {
        /** @var string[] $modules */
        $modules = App::getContainer()->findByType(Module::class);
        foreach ($modules as $moduleName) {
            /** @var Module $module */
            $module = App::getService($moduleName);
            echo 'Installing module ' . $module::NAME . PHP_EOL;
            $module->install();
        }
        return true;
    }
}
