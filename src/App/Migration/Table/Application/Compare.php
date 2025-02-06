<?php

namespace Migration\Table\Application;

use DomainException;
use Migration\Table\Domain\Interfaces\Repository;
use Migration\Table\Domain\Table;

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
            $reference = $this->repository->getTable($dto->database_reference, $dto->base, $dto->table);
        } catch (DomainException $e) {
            $remove_table = true;
        } catch (\Exception $e) {
            $remove_table = true;
        } finally {
            try{
                $to_compare = $this->repository->getTable($dto->database_to_apply, $dto->base, $dto->table);
                if($remove_table){
                    return $to_compare->getDropTable();
                }
            }catch (DomainException $e)
            {
            if(!$remove_table){
                    return $reference->getCreateTable();
                }else
                    return "";
            }
        }
        return $to_compare->equilizeTable($reference);        
    }
}
