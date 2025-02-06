<?php

namespace Migration\Table\Domain\Exceptions;

use DomainException;

class TableNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("Table $base.$table not found!");
    }
}