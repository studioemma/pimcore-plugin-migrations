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
            . 'version INT(10) UNSIGNED PRIMARY KEY'
            . ')';
        $this->db->query($query);
        $query = 'INSERT INTO `_migration_version` (`version`)'
            . ' VALUE (0)';
        $this->db->query($query);
    }

    protected function getCurrentVersion()
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
            return $updated;
        }

        $migrations = $this->getMigrationList($direction);

        $execMatch = 1;
        if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
            $execMatch = -1;
        }

        foreach ($migrations as $migrationVersion => $migration) {
            $migrate = false;
            if (\Migrations\Migration::DIRECTION_UP === $direction) {
                $compare = $this->compareVersion($migrationVersion);
                if (1 === $compare) {
                    $migrate = true;
                }
            } elseif (\Migrations\Migration::DIRECTION_DOWN === $direction) {
                $compare = $this->compareVersion($migrationVersion);
                if (0 === $compare
                    || -1 === $compare) {
                    $migrate = true;
                }
            }

            if (false === $migrate) {
                $skipped[] = $migration['file'];
                $migrationVersion = $currentVersion;
                continue;
            }

            $migrationVersion = $this->runMigration($migrationVersion, $migration, $direction);
            $updated[] = $migration['file'];
        }

        return [
            'skipped' => $skipped,
            'updated' => $updated,
            'from' => $from,
            'to' => $migrationVersion,
        ];
    }

    protected function getMigrationList($direction = \Migrations\Migration::DIRECTION_UP)
    {
        $files = [];

        foreach (new \DirectoryIterator($this->migrationsFolder) as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir()) {
                continue;
            }

            $files[] = $fileInfo->getFileName();
        }

        natsort($files);

        if (\Migrations\Migration::DIRECTION_DOWN === $direction) {
            $files = array_reverse($files);
        }

        $migrations = [];

        foreach ($files as $file) {
            preg_match('/(?P<version>\d*)-(?P<class>[A-Za-z0-9]*)\.php/', $file, $matches);
            if (! isset($matches['version']) || ! isset($matches['class'])) {
                throw new Exception('Migration file structure issue in: ' . $file);
            }

            $version = $matches['version'];
            $class = $matches['class'];

            $migrations[(int) $version] = [
                'class' => 'PMigration_' . $class,
                'file' => $file,
            ];
        }

        return $migrations;
    }

    protected function runMigration($migrationVersion, $migration, $direction)
    {
        require_once($this->migrationsFolder . '/' . $migration['file']);

        $className = $migration['class'];
        $pMigration = new $className($this->db);

        $this->db->beginTransaction();
        try {
            $pMigration->$direction();
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
