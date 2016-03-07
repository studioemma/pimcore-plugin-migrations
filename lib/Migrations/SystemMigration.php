<?php

namespace Migrations;

interface SystemMigration
{
    public function getStartRevision();

    public function getEndRevision();
}
