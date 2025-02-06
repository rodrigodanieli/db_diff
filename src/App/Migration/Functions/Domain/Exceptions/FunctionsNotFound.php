<?php

namespace Migration\Functions\Domain\Exceptions;

use DomainException;

class FunctionsNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("Functions $base.$table not found!");
    }
}