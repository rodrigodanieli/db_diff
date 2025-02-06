<?php

namespace Migration\Event\Application;

use Migration\Database\Domain\Database;

class GetEventCreationDto
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