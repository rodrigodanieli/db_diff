<?php

namespace Migration\Event\Application;

use Migration\Event\Domain\Interfaces\Repository;

class GetEventsFromBase
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetEventsFromBaseDto $dto) : array
    {
        return $this->repository->getEventsFromBases($dto->database_1,$dto->database_2, $dto->base);
    }

}