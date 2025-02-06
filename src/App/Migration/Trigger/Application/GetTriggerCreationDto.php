<?php

namespace Migration\Trigger\Application;

use Migration\Database\Domain\Database;

class GetTriggerCreationDto
{
    public Database $database_reference;
    public string $base;
    public string $name;

    public function __construct(string $base, string $name, Database $database_reference)
    {   
        $this->base = $base;
        $this->name = $name;
        $this->database_reference = $database_reference;
    }

}