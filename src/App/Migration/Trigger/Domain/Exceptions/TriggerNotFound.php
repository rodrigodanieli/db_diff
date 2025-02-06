<?php

namespace Migration\Trigger\Domain\Exceptions;

use DomainException;

class TriggerNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("Trigger $base.$table not found!");
    }
}