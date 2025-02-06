<?php


namespace Migration\Data\Application;

use Migration\Data\Domain\Data;
use Migration\Data\Domain\Interfaces\Repository;

class GetDataFromTable
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;   
    }

    public function execute(GetDataFromTableDto $dto) : Data
    {
        return $this->repository->getData($dto->database, $dto->base, $dto->table);
    }

}