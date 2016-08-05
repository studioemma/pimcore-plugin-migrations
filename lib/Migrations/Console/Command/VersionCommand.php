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

class VersionCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('version')
            ->setDescription('current migrations version')
            ->addOption(
                'pimcore',
                null,
                InputOption::VALUE_NONE,
                'show pimcore migration version'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationsFolder = realpath(PIMCORE_WEBSITE_PATH . '/lib/Website/Migrations');
        if (null == $migrationsFolder) {
            $output->writeln('<error>No migrations folder found</error>');
            return 1;
        }

        $pimcoreVersion = $input->getOption('pimcore');

        $run = new \Migrations\Migration\Run($migrationsFolder);
        if (true === $pimcoreVersion) {
            $version = $run->getMigratedPimcoreRevision();
        } else {
            $version = $run->getCurrentVersion();
        }

        $output->writeln($version);
    }
}
