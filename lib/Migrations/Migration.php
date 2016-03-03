<?php

namespace Migrations;

interface Migration
{
    const DIRECTION_UP = 'up';
    const DIRECTION_DOWN = 'down';

    public function up();

    public function down();
}
