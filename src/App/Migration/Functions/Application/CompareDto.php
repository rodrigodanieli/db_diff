<?php

namespace Migration\Functions\Application;

use Migration\Database\Domain\Database;

class CompareDto
{
    public Database $database_reference;
    public Database $database_to_apply;
    public string $base;
    public string $name;

    public function __construct(string $base, string $name, Database $database_reference, Database $database_to_apply)
    {   
        $this->base = $base;
        $this->name = $name;
        $this->database_reference = $database_reference;
        $this->database_to_apply = $database_to_apply;
    }

}