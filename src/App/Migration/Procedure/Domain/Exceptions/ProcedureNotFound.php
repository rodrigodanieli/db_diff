<?php

namespace Migration\Procedure\Domain\Exceptions;

use DomainException;

class ProcedureNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("Procedure $base.$table not found!");
    }
}