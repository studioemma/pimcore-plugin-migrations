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

class MigrateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrations')
            ->addArgument('direction', InputArgument::REQUIRED, 'The migration direction up/down')
            ->addArgument('to', InputArgument::OPTIONAL, 'up to what migration version');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationsFolder = realpath(PIMCORE_WEBSITE_PATH . '/lib/Website/Migrations');
        if (null == $migrationsFolder) {
            $output->writeln('<error>No migrations folder found</error>');
            return 1;
        }

        $direction = $input->getArgument('direction');
        $output->writeln('Running migrations <comment>' . $direction . '</comment>');

        $run = new \Migrations\Migration\Run($migrationsFolder);
        $run->runMigrations($direction);

        $output->writeln('migrate');
    }
}
