<?php

namespace Migration\Functions\Application;

use DomainException;
use Migration\Functions\Domain\Interfaces\Repository;

class Compare
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(CompareDto $dto): string
    {
        //Check create and drop table
        $remove_table = false;
        try {
            $reference = $this->repository->getFunctions($dto->database_reference, $dto->base, $dto->name);
        } catch (DomainException $e) {
            $remove_table = true;
        } finally {
            try {
                $to_compare = $this->repository->getFunctions($dto->database_to_apply, $dto->base, $dto->name);
                if ($remove_table)
                    return $to_compare->getDropFunctions();
            } catch (DomainException $e) {
                if (!$remove_table)
                    return $reference->getCreateFunctions();
                else
                    return "";
            }
        }

        if (!$to_compare->isSame($reference)) {

            $script = "";
            $script .= $reference->getCreateFunctions();
            return $script;
        }
        return "";
    }
}
