<?php
/**
 * @copyright Copyright (c) 2016 Studio Emma. (http://www.studioemma.com)
 */

include_once(__DIR__ . "/../../../pimcore/cli/startup.php");

use Symfony\Component\Console\Application;
use Pimcore\ExtensionManager;

$application = new Application('Pimcore Migrations', '1.2.3');

$conf = Pimcore\Config::getSystemConfig();
if (!$conf) {
    /**
     * Pimcore is not installed, we will allow cli installation of pimcore and
     * enable this plugin in one go
     */
    include_once(
        realpath(
            __DIR__ . "/../lib/Migrations/Console/Command/InstallCommand.php"
        )
    );

    $application->add(new \Migrations\Console\Command\InstallCommand());
} else {
    if (! ExtensionManager::isEnabled('plugin', 'Migrations')) {
        /**
         * Pimcore is installed but the migrations plugin is not installed,
         * install the plugin to allow use of it
         */
        ExtensionManager::enable('plugin', 'Migrations');

        // re-init plugins
        Pimcore::initPlugins();
    }

    /**
     * Plugin is fully available, allow migrations
     */
    $application->add(new \Migrations\Console\Command\MigrateCommand());
    $application->add(new \Migrations\Console\Command\GenerateCommand());
    $application->add(new \Migrations\Console\Command\VersionCommand());
    $application->add(new \Migrations\Console\Command\CheckCommand());
}
$application->run();
