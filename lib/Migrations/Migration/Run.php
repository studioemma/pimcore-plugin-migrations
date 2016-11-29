<?php

namespace Migrations\Migration;

use Exception;

class Run
{
    /** @var string */
    protected $migrationsFolder = null;

    /** @var \Pimcore\Db\Wrapper */
    protected $db = null;

    /** @var int */
    protected $currentVersion = null;

    public function __construct($migrationsFolder)
    {
        if (! file_exists($migrationsFolder)) {
            throw new Exception('Migrations folder does not exist');
        }

        $this->migrationsFolder = $migrationsFolder;
        $pimcoreDbWrapper = \Pimcore\Db::getConnection();
        // We want exceptions, no silent failures
        $this->db = $pimcoreDbWrapper->getResource();
        if (! $this->hasVersionTable()) {
            $this->createVersionTable();
        } else {
            $this->checkVersionTable();
        }
    }

    protected function hasVersionTable()
    {
        try {
            $query = 'SELECT 1 FROM `_migration_version` LIMIT 1;';
            $stmt = $this->db->query($query);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function createVersionTable()
    {
        $query = 'CREATE TABLE `_migration_version` ('
            . 'version INT(10) UNSIGNED PRIMARY KEY,'
            . ' pimcore_revision INT(10) UNSIGNED'
            . ')';
        $this->db->query($query);
        $query = 'INSERT INTO `_migration_version` (`version`, `pimcore_revision`)'
            . ' VALUE (0, ' . \Pimcore\Version::getRevision() . ')';
        $this->db->query($query);
    }

    protected function checkVersionTable()
    {
        $fields = ['pimcore_revision'];
        $query = 'SHOW COLUMNS from `_migration_version`';
        $stmt = $this->db->query($query);
        $tableFields = $stmt->fetchAll();
        $existingFields = [];

        foreach ($tableFields as $field) {
            $existingFields[] = $field['Field'];
        }

        foreach ($fields as $field) {
            if (! in_array($field, $existingFields)) {
                $query = 'ALTER TABLE `_migration_version`'
                    . ' ADD `pimcore_revision` INT(10) UNSIGNED after `version`';
                $this->db->query($query);
            }
        }
    }

    public function getCurrentVersion()
    {
        if (null === $this->currentVersion) {
            $query = 'SELECT version FROM `_migration_version` LIMIT 1';
            $stmt = $this->db->query($query);
            $this->currentVersion = (int) $stmt->fetchColumn();
        }

        return $this->currentVersion;
    }

    protected function updateCurrentVersion($version)
    {
        /**
         * We could have set the new version here, but incase of a failure
         * we just want currentVersion to be filled with the one from the
         * database again.
         */
        $this->currentVersion = null;

        $query = 'UPDATE `_migration_version` SET `version` = ?';
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$version]);
    }

    public function getMigratedPimcoreRevision()
    {
        $query = 'SELECT pimcore_revision FROM `_migration_version` LIMIT 1';
        $stmt = $this->db->query($query);
        $revision = (int) $stmt->fetchColumn();

        return $revision;
    }

    protected function updateMigratedPimcoreRevision($revision)
    {
        $query = 'UPDATE `_migration_version` SET `pimcore_revision` = ?';
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$revision]);
    }

    protected function compareVersion($newVersion)
    {
        /**
         * new version == current version => 0
         * new version <  current version => -1
         * new version  > current version => 1
         */
        return $newVersion <=> $this->getCurrentVersion();
    }

    public function runMigrations($direction = \Migrations\Migration::DIRECTION_UP, $to = null)
    {
        $updated = [];
        $skipped = [];
        $done = false;

        $from = $currentVersion = $this->getCurrentVersion();

        if (null !== $to) {
            $to = (int) $to;
            $toCompare = $this->compareVersion($to);
            if (-1 == $toCompare) {
                $direction = \Migrations\Migration::DIRECTION_DOWN;
            } elseif (1 == $toCompare) {
                $direction = \Migrations\Migration::DIRECTION_UP;
            } else {
                $done = true;
            }
        }

        if (true === $done) {
            return [
                'skipped' => $skipped,
                'updated' => $updated,
                'from' => $from,
                'to' => $to,
            ];
        }

        $migrations = $this->getMigrationList($direction);

        $toVersion = null;
        if (null === $to) {
            end($migrations);
            $toVersion = key($migrations);
            reset($migrations);
        }

        $execMatch = 1;
        if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
            $execMatch = -1;
            if (null !== $toVersion) {
                $toVersion -= 1;
            }
        }

        if (null !== $toVersion) {
            $to = $toVersion;
        }

        $migratedVersion = $currentVersion;
        foreach ($migrations as $migrationVersion => $migration) {
            $migrate = false;
            $compare = $this->compareVersion($migrationVersion);
            $toCompare = $this->compareVersion($to);
            if (\Migrations\Migration::DIRECTION_UP === $direction) {
                if (1 === $compare
                    && 1 === $toCompare) {
                    $migrate = true;
                }
            } elseif (\Migrations\Migration::DIRECTION_DOWN === $direction) {
                if ((
                        0 === $compare
                        || -1 === $compare
                    )
                    && -1 === $toCompare) {
                    $migrate = true;
                }
            }

            if (false === $migrate) {
                $skipped[] = $migration['file'];
                continue;
            }

            $migratedVersion = $this->runMigration($migrationVersion, $migration, $direction);
            $updated[] = $migration['file'];
        }

        return [
            'skipped' => $skipped,
            'updated' => $updated,
            'from' => $from,
            'to' => $migratedVersion,
        ];
    }

    protected function getMigrationList($direction = \Migrations\Migration::DIRECTION_UP)
    {
        $files = [];

        foreach (new \DirectoryIterator($this->migrationsFolder) as $fileInfo) {
            if ($fileInfo->isDot()
                || $fileInfo->isDir()
                || $fileInfo->getBasename() == '.DS_Store') {
                continue;
            }

            $files[] = $fileInfo->getFileName();
        }

        natsort($files);

        if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
            $files = array_reverse($files);
        }

        $migrations = [];
        $errors = [];

        foreach ($files as $file) {
            preg_match('/(?P<version>\d*)-(?P<class>[A-Za-z0-9]*)\.php/', $file, $matches);
            if (! isset($matches['version']) || ! isset($matches['class'])) {
                throw new Exception('Migration file structure issue in: ' . $file);
            }

            $version = (int) $matches['version'];
            $class = $matches['class'];

            if (isset($migrations[$version])) {
                $errors[] = $file;
            } else {
                $migrations[$version] = [
                    'class' => 'PMigration_' . $class,
                    'file' => $file,
                ];
            }
        }

        if (! empty($errors)) {
            throw new Exception(
                'Encoutered version number issues with:'
                . PHP_EOL
                . implode(PHP_EOL, $errors)
            );
        }

        return $migrations;
    }

    protected function runMigration($migrationVersion, $migration, $direction)
    {
        require_once($this->migrationsFolder . '/' . $migration['file']);

        $className = $migration['class'];
        $pMigration = new $className($this->db);

        if (! ($pMigration instanceof \Migrations\Migration)) {
            throw new Exception('A migration must be an instance of Migrations\Migration');
        }

        $systemMigration = false;
        if ($pMigration instanceof \Migrations\SystemMigration) {
            $systemMigration = true;
        }

        $this->db->beginTransaction();
        try {
            $execMigration = true;
            if (true === $systemMigration) {
                /**
                 * only run system upgrades if needed
                 *
                 * NOTE: you will not be able to run down on system migrations
                 *       because that would break the running code.
                 */
                if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
                    $execMigration = false;
                }

                $migratedPimcoreRevision = $this->getMigratedPimcoreRevision();

                if (! ($migratedPimcoreRevision < $pMigration->getEndRevision()
                    && \Pimcore\Version::getRevision() >= $pMigration->getStartRevision())) {
                    $execMigration = false;
                }

                if (true === $execMigration) {
                    $this->updateMigratedPimcoreRevision(
                        $pMigration->getEndRevision()
                    );
                }
            }
            if (true === $execMigration) {
                $pMigration->$direction();
            }
            if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
                $migrationVersion -= 1;
            }
            $this->updateCurrentVersion($migrationVersion);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $migrationVersion;
    }
}
