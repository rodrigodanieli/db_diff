<?php

namespace Migration\Trigger\Application;

use DomainException;
use Migration\Trigger\Domain\Interfaces\Repository;

class GetTriggers
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetTriggersDto $dto): array
    {

        return array_column($this->repository->getTriggersFromBase($dto->database_1, $dto->base), "Trigger");
    }
}
