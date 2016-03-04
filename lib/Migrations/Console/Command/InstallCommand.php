<?php
/**
 * @copyright Copyright (c) 2016 Studio Emma. (http://www.studioemma.com)
 */

namespace Migrations\Console\Command;

use Pimcore\Model\Tool;
use Pimcore\ExtensionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Migrations installer')
            ->addOption(
                'db-adapter',
                null,
                InputOption::VALUE_REQUIRED,
                'what adapter to use (Mysqli or Pdo_Mysql)'
            )
            ->addOption(
                'db-host',
                null,
                InputOption::VALUE_REQUIRED,
                'db host or socket'
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'db port',
                3306
            )
            ->addOption(
                'db-username',
                null,
                InputOption::VALUE_REQUIRED,
                'db username'
            )
            ->addOption(
                'db-password',
                null,
                InputOption::VALUE_REQUIRED,
                'db password'
            )
            ->addOption(
                'db-database',
                null,
                InputOption::VALUE_REQUIRED,
                'db database name'
            )
            ->addOption(
                'admin-username',
                null,
                InputOption::VALUE_REQUIRED,
                'admin username'
            )
            ->addOption(
                'admin-password',
                null,
                InputOption::VALUE_REQUIRED,
                'admin password'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errors = [];

        $dbConfig = [
            'adapter' => $input->getOption('db-adapter'),
            'params' => [
                'username' => $input->getOption('db-username'),
                'password' => $input->getOption('db-password'),
                'dbname' => $input->getOption('db-database'),
            ],
        ];

        $dbConfigHost = $input->getOption('db-host');
        if (file_exists($dbConfigHost)) {
            // socket
            $dbConfig['params']['unix_socket'] = $dbConfigHost;
        } else {
            // tcp
            $dbConfig['params']['host'] = $dbConfigHost;
            $dbConfig['params']['port'] = $input->getOption('db-port');
        }

        // try to establish a mysql connection
        try {
            $db = \Zend_Db::factory($dbConfig['adapter'], $dbConfig['params']);

            $db->getConnection();

            // check utf-8 encoding
            $result = $db
                ->fetchRow('SHOW VARIABLES LIKE "character\_set\_database"');
            if (!in_array($result['Value'], ["utf8", "utf8mb4"])) {
                $errors[] = "Database charset is not utf-8";
            }
        } catch (\Exception $e) {
            $errors[] =
                "Couldn't establish connection to mysql: " . $e->getMessage();
        }

        if (0 < count($errors)) {
            foreach ($errors as $error) {
                $output->writeln('<error>' . $error . '</error>');
            }
            return 2;
        }

        $admin = [
            'username' => $input->getOption('admin-username'),
            'password' => $input->getOption('admin-password'),
        ];

        foreach ($admin as $key => $value) {
            if (4 > strlen($value)) {
                $errors[] =
                    $key . " should have at least 4 characters";
            }
        }

        if (0 < count($errors)) {
            foreach ($errors as $error) {
                $output->writeln('<error>' . $error . '</error>');
            }
            return 2;
        }

        $setup = new Tool\Setup();

        // check if /website folder already exists, if not, look for
        // /website_demo & /website_example /website_install is just for
        // testing in dev environment
        if (!is_dir(PIMCORE_WEBSITE_PATH)) {
            foreach ([
                "website_install",
                "website_demo",
                "website_example"
            ] as $websiteDir) {
            $dir = PIMCORE_DOCUMENT_ROOT . "/" . $websiteDir;
            if (is_dir($dir)) {
                rename($dir, PIMCORE_WEBSITE_PATH);
                break;
            }
            }
        }

        $setup->config([
            "database" => $dbConfig,
        ]);

        // look for a template dump
        // eg. for use with demo installer
        $dbDataFile = PIMCORE_WEBSITE_PATH . "/dump/data.sql";

        if (!file_exists($dbDataFile)) {
            $setup->database();
            \Pimcore::initConfiguration();
            $setup->contents($admin);
        } else {
            $setup->database();
            $setup->insertDump($dbDataFile);
            \Pimcore::initConfiguration();
            $setup->createOrUpdateUser($admin);
        }

        ExtensionManager::enable('plugin', 'Migrations');

        $output->writeln('<info>successfully installed pimcore</info>');
    }
}
