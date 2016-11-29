<?php

namespace Migrations\Migration;

use Migrations\Migration;
use ReflectionClass;
use Exception;

abstract class AbstractMigration implements Migration
{
    /** @var Pimcore\Db\Wrapper */
    protected $db = null;

    /** @var \Symfony\Component\Console\Output\OutputInterface */
    protected $output = null;

    /** @var string */
    protected $dataFolder = null;

    public function __construct($output, $db)
    {
        $this->output = $output;
        $this->db = $db;

        $reflection = new ReflectionClass($this);
        $this->dataFolder = str_replace('.php', '', $reflection->getFileName());
    }

    protected function getDatafilePath($filename)
    {
        $file = $this->dataFolder . '/' . $filename;
        if (! file_exists($file)) {
            throw new Exception('datafile not found ' . $filename);
        }

        return $file;
    }

    protected function getDataFile($filename)
    {
        $file = $this->getDatafilePath($filename);

        return file_get_contents($file);
    }

    abstract public function up();

    abstract public function down();
}
