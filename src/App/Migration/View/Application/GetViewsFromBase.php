<?php

namespace Migration\View\Application;

use Migration\View\Domain\Interfaces\Repository;

class GetViewsFromBase
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetViewsFromBaseDto $dto) : array
    {
        return $this->repository->getViewsFromBases($dto->database_1,$dto->database_2, $dto->base);
    }

}