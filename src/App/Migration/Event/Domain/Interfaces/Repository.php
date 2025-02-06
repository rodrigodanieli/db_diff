<?php
namespace Migration\Event\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\Event\Domain\Event;

interface Repository
{
    public function getEvent(Database $database,  string $base,string $name): Event;
    public function getEventsFromBase(Database $database, string $base) : array;
    public function getEventsFromBases(Database $database_1, Database $database_2, string $base): array;
}