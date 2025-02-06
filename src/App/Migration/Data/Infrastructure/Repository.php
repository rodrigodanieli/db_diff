<?php


namespace Migration\Data\Infrastructure;

use DomainException;
use Exception;
use Migration\Data\Domain\Data;
use Migration\Data\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Database\Domain\Database;
use PDOException;

class Repository implements InterfacesRepository
{
    public function getData(Database $database, string $base, string $table): Data
    {
        try {
            $conn = $database->getConnection();

            $data = new Data($table, $base);

            $stm_1 = $conn->query("DESC `$base`.`$table`");
            $result_1 = [];
            if ($stm_1)
                $result_1 = $stm_1->fetchAll(\PDO::FETCH_ASSOC);
            $coluns = [];
            $do_unhex = [];
            foreach ($result_1 as $row) {
                if (strpos(strtolower($row['Type']), "binary") !== false) {
                    ////echo "$base - $table HEX $row[Field]\n";
                    $coluns[] = "HEX(`$row[Field]`) as `$row[Field]`";
                    $do_unhex[] = $row['Field'];
                } else
                    $coluns[] = "`$row[Field]`";
            }
            //var_dump($do_unhex);
            $query = "SELECT " . implode(",", $coluns) . " FROM `$base`.`$table`;";
            $stm = $conn->query($query);
            //var_dump($stm);
            $result = [];
            if ($stm)
                $result = $stm->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($result as &$row) {
                foreach ($row as $key => &$col) {
                    if (!empty($col)) {
                        if (in_array($key, $do_unhex))
                            $col = "UNHEX('$col')";
                        else
                            $col = $col;
                    } else
                        $col = "NULL";
                    //$col = $conn->quote($col);
                }
            }
            $data->setData($result);

            return $data;
        } catch (PDOException $e) {
            throw new DomainException("Empty Data");
        }
    }
}
