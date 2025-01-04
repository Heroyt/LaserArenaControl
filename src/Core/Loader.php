<?php

/**
 * @file      Loader.php
 * @brief     Core\Loader class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

/**
 * @package Core
 * @brief   Core classes
 */

namespace App\Core;

use Dibi\DriverException;
use Dibi\Exception;
use LAC\Modules\Core\Module;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Timer;
use RuntimeException;

/**
 * @class   Loader
 * @brief   Loader class to prevent any unnecessary global variable
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class Loader
{
    /**
     * Initialize everything necessary
     *
     * @return void
     *
     * @post    URL request is parsed
     * @post    Database connection established
     *
     * @since   1.0
     * @version 1.0
     */
    public static function init() : void {
        // Initialize app
        Timer::start('core.init.app');
        App::prettyUrl();
        App::setupDi();
        Timer::stop('core.init.app');

        // Setup database connection
        Timer::start('core.init.db');
        self::initDB();
        Timer::stop('core.init.db');

        Timer::start('core.init.modules');
        self::loadModules();
        Timer::stop('core.init.modules');
    }

    /**
     * Initialize database connection
     *
     * @return void
     *
     * @throws RuntimeException
     * @since   1.0
     * @version 1.0
     */
    public static function initDB() : void {
        if (isset($_ENV['noDb'])) {
            return;
        }
        try {
            DB::init(App::getService('db.connection'));
        } catch (Exception | DriverException $e) {
            App::getInstance()->getLogger()->error(
              'Cannot connect to the database! ('.$e->getCode().') '.$e->getMessage()
            );
            throw new RuntimeException(
              'Cannot connect to the database!'.PHP_EOL.
              $e->getMessage().PHP_EOL.
              $e->getTraceAsString().PHP_EOL.
              json_encode(App::getInstance()->config->getConfig(), JSON_THROW_ON_ERROR),
              $e->getCode(),
              $e
            );
        }
    }

    public static function loadModules() : void {
        /** @var string[] $modules */
        $modules = App::getContainer()->findByType(Module::class);
        foreach ($modules as $moduleName) {
            /** @var Module $module */
            $module = App::getService($moduleName);
            $module->init();
        }
    }
}
