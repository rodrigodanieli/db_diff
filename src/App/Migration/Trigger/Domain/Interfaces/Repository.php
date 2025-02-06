<?php
namespace Migration\Trigger\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\Trigger\Domain\Trigger;

interface Repository
{
    public function getTrigger(Database $database,  string $base,string $name): Trigger;
    public function getTriggersFromBase(Database $database, string $base) : array;
    public function getTriggersFromBases(Database $database_1, Database $database_2, string $base): array;
}