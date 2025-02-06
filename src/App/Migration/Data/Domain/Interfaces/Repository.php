<?php 

namespace Migration\Data\Domain\Interfaces;

use Migration\Data\Domain\Data;
use Migration\Database\Domain\Database;

interface Repository
{
    public function getData(Database $database, string $base, string $table): Data;
}