<?php

namespace Migrations\Migration;

use Migrations\Migration;
use ReflectionClass;

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
        return $this->dataFolder . '/' . $filename;
    }

    abstract public function up();

    abstract public function down();
}
