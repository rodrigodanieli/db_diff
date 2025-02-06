<?php

namespace Migration\Database\Domain\Interfaces;

use Migration\Database\Domain\Database;

interface Repository
{
    public function getDatabase(int $project, string $name): Database;
    public function getDatabases(int $project): array;
    public function getTablesMigration(int $project):array;
    public function getBasesMigration(int $project):array;
}

