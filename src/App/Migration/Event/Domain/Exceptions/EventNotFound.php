<?php

namespace Migration\Event\Domain\Exceptions;

use DomainException;

class EventNotFound extends DomainException
{
    public function __construct(string $base, string $table)
    {
        parent::__construct("Event $base.$table not found!");
    }
}