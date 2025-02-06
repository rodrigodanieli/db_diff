<?php

namespace Migration\View\Domain\Exceptions;

use DomainException;

class ViewNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("View $base.$table not found!");
    }
}