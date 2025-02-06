<?php

namespace Migration\Event\Application;

use DomainException;
use Migration\Event\Domain\Interfaces\Repository;

class GetEventCreation
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetEventCreationDto $dto): string
    {
        //Check create and drop table
        $ref = $this->repository->getEvent($dto->database_reference, $dto->base, $dto->name);
        return $ref->getCreateEvent();
    }
}
