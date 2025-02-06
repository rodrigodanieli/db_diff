<?php

namespace Migration\View\Application;

use Migration\View\Domain\Interfaces\Repository;

class GetViews
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetViewsDto $dto) : array
    {
        return array_column($this->repository->getViewsFromBase($dto->database_1, $dto->base),'TABLE_NAME');
    }

}