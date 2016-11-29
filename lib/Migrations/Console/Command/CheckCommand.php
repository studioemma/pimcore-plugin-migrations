<?php
/**
 * @copyright Copyright (c) 2016 Studio Emma. (http://www.studioemma.com)
 */

namespace Migrations\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('check')
            ->setDescription('check migration numbering');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationsFolder = realpath(PIMCORE_WEBSITE_PATH . '/lib/Website/Migrations');
        if (null == $migrationsFolder) {
            $output->writeln('<error>No migrations folder found</error>');
            return 1;
        }

        $run = new \Migrations\Migration\Run($migrationsFolder, $output);
        $migrations = $run->checkMigrations();

        $output->writeln('No failures found in the migrations');
    }
}
