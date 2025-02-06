<?php

namespace Migration\Trigger\Application;

use Migration\Trigger\Domain\Interfaces\Repository;

class GetTriggersFromBase
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetTriggersFromBaseDto $dto) : array
    {
        return $this->repository->getTriggersFromBases($dto->database_1,$dto->database_2, $dto->base);
    }

}