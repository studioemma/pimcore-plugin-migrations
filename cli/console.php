<?php
/**
 * @copyright Copyright (c) 2016 Studio Emma. (http://www.studioemma.com)
 */

include_once(__DIR__ . "/../../../pimcore/cli/startup.php");

use Symfony\Component\Console\Application;

$application = new Application('Pimcore Migrations', '1.0.0');
try {
    $application->add(new \Migrations\Console\Command\MigrateCommand());
} catch (Error $err) {
    include_once(
        realpath(
            __DIR__ . "/../lib/Migrations/Console/Command/InstallCommand.php"
        )
    );

    $application->add(new \Migrations\Console\Command\InstallCommand());
}
$application->run();
