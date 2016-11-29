<?php
/**
 * @copyright Copyright (c) 2016 Studio Emma. (http://www.studioemma.com)
 */

include_once(__DIR__ . "/../../../pimcore/cli/startup.php");

use Symfony\Component\Console\Application;
use Pimcore\ExtensionManager;

$application = new Application('Pimcore Migrations', '1.2');
if (ExtensionManager::isEnabled('plugin', 'Migrations')) {
    $application->add(new \Migrations\Console\Command\MigrateCommand());
    $application->add(new \Migrations\Console\Command\GenerateCommand());
    $application->add(new \Migrations\Console\Command\VersionCommand());
    $application->add(new \Migrations\Console\Command\CheckCommand());
} else {
    include_once(
        realpath(
            __DIR__ . "/../lib/Migrations/Console/Command/InstallCommand.php"
        )
    );

    $application->add(new \Migrations\Console\Command\InstallCommand());
}
$application->run();
