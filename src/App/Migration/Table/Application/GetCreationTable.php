<?php

namespace Migration\Table\Application;

use Migration\Table\Domain\Interfaces\Repository;
use Migration\Table\Domain\Table;

class GetCreationTable
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetCreationTableDto $dto): string
    {
        $str = "USE " . $dto->base . ";\nDROP TABLE IF EXISTS " . $dto->table . ";\n";
        $str .= $this->repository->getCreationTable($dto->database, $dto->base, $dto->table) . ";\n\n";

        return $str;
    }
}
