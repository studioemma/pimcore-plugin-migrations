<?php
namespace Migrations\Console\Command;

use DirectoryIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Generate skeleton migration')
            ->addArgument('className', InputArgument::REQUIRED, 'The classname of the migration')
            ->addOption('directory', 'd', InputOption::VALUE_NONE, 'Create a data directory for this migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make sure the migrationfolder exists
        $migrationsFolder = PIMCORE_WEBSITE_PATH . '/lib/Website/Migrations';
        if (!file_exists($migrationsFolder)) {
            $output->writeln('<info>No migrations folder found, creating it</info>');
            mkdir($migrationsFolder, 0700, true);
        }

        // Define the classname
        $className = preg_replace("/[^a-zA-Z0-9]/", "", ucfirst($input->getArgument('className')));

        // Find next migration number
        $highestMigrationNumber = 0;

        foreach (new DirectoryIterator($migrationsFolder) as $fileInfo) {
            if (!$fileInfo->isDot()) {
                $fileNameParts = explode_and_trim('-', $fileInfo->getFilename());
                $numberPart = reset($fileNameParts);
                $classNamePart = str_replace('.php', '', $fileNameParts[1]);

                // We cannot allow the same classname in multiple migrations, because of namespacing issues
                if ($classNamePart == $className) {
                    $output->writeln(sprintf('<error>The classname %s already exists in migration %s</error>',
                        $className,
                        $fileInfo->getFilename()));
                    return 1;
                }
                $number = (int)$numberPart;
                if ($number > $highestMigrationNumber) {
                    $highestMigrationNumber = $number;
                }
            }
        }
        $nextMigrationNumber = $highestMigrationNumber + 1;

        // Generate the file
        $migrationFileName = sprintf('%03d', $nextMigrationNumber) . '-' . $className . '.php';

        $template = "
<?php
use Migrations\Migration\AbstractMigration;

class PMigration_$className extends AbstractMigration
{
    public function up()
    {
        // do something
    }

    public function down()
    {
        // do the reverse
    }
}";
        // Write the file
        file_put_contents($migrationsFolder . DIRECTORY_SEPARATOR . $migrationFileName, $template);

        // Optional create migration data folder
        if ($input->getOption('directory')) {
            $migrationDataFolderName = str_replace('.php', '', $migrationFileName);
            $migrationDataFolder = $migrationsFolder . DIRECTORY_SEPARATOR . $migrationDataFolderName;
            mkdir($migrationDataFolder, 0700, true);
            if (!file_exists($migrationDataFolder)) {
                $output->writeln("<error>Could not create migration data folder $migrationDataFolderName</error>");
            } else {
                $output->writeln("<info>Successfully created migration data folder $migrationDataFolderName</info>");
            }
        }

        // Say something about what has happened
        if (file_exists($migrationsFolder . DIRECTORY_SEPARATOR . $migrationFileName)) {
            $output->writeln("<info>Successfully created migration file $migrationFileName</info>");
        } else {
            $output->writeln("<error>Could not write migration file $migrationFileName to migration folder $migrationsFolder</error>");
        }
    }
}
