<?php


namespace Migration\Data\Application;

use Migration\Database\Domain\Database;

class GetDataFromTableDto
{
    public Database $database;
    public string $base;
    public string $table;

    public function __construct(Database $database, string $base, string $table)
    {
        $this->base = $base;
        $this->table = $table;
        $this->database =  $database;
    }
}
