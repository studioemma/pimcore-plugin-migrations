<?php

namespace Migrations\Migration;

use Migrations\Migration;
use ReflectionClass;
use Exception;

abstract class AbstractMigration implements Migration
{
    /** @var Pimcore\Db\Wrapper */
    protected $db = null;

    /** @var string */
    protected $dataFolder = null;

    public function __construct($db)
    {
        $this->db = $db;

        $reflection = new ReflectionClass($this);
        $this->dataFolder = str_replace('.php', '', $reflection->getFileName());
    }

    protected function getDataFile($filename)
    {
        $file = $this->dataFolder . '/' . $filename;
        if (! file_exists($file)) {
            throw new Exception('datafile not found ' . $filename);
        }

        return file_get_contents($file);
    }

    abstract public function up();

    abstract public function down();
}
