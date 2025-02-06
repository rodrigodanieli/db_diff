<?php

namespace Migration\Event\Application;

use DomainException;
use Migration\Event\Domain\Interfaces\Repository;

class GetEvents
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetEventsDto $dto): array
    {

        return array_column($this->repository->getEventsFromBase($dto->database_1, $dto->base), "Name");
    }
}
