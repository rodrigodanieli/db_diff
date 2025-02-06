<?php


namespace Migration\Table\Domain;

use Migration\Table\Domain\Properties\Column;
use Migration\Table\Domain\Properties\Key;
use Migration\Table\Domain\Properties\Partitions;
use Sohris\Core\Logger;

class Table
{
    private string $name;
    private string $base;
    private Logger $logger;

    private string $create_table = "";

    /**
     * @var Column[]
     */
    private array $columns = [];

    /**
     * @var Key[]
     */
    private array $keys = [];

    /**
     * 
     */
    private array $partitions = [];

    public function __construct(string $name, string $base)
    {
        $this->logger = new Logger("Table");
        $this->name = $name;
        $this->base = $base;
    }

    public function addColumn(array $info)
    {
        $validation = [
            'Field',
            'Type',
            'Null'
        ];

        foreach ($validation as $val) {
            if (empty($info[$val]) || is_null($info[$val])) {
                $this->logger->error("INVALID COLUMN", [
                    "base" => $this->base, "table" => $this->name, "column" => $info
                ]);
                return;
            }
        }

        $this->columns[] = new Column($info['Field'], $info['Type'], $info['Null'] == "Yes", $info['Key'], $info['Default'], $info['Extra']);
    }

    public function addColumns(array $info)
    {
        var_dump($info);
        $previous = null;
        foreach ($info as $column) {
            $column = new Column($column['Field'], $column['Type'], strtolower($column['Null']) == "yes", $column['Key'], $column['Default'], $column['Extra']);
            if ($previous)
                $column->addPrevious($previous);
            $this->columns[] = $column;
            $previous = $column;
        }
    }

    public function setStructure(string $info)
    {
        $this->create_table = $info;
        $depara = $this->depara($info);
        foreach ($depara['columns'] as $column) {
            $this->addColumn($column);
        }
        $this->addKeys($depara['keys']);
    }

    private function explode($delimiter, $string)
    {
        // Remove espaços em branco no início e no final da string
        $string = trim($string);

        // Regex para encontrar vírgulas no final das linhas
        $pattern = "/,(\s*$)/m";

        // Substitui vírgulas no final das linhas por um delimitador específico (por exemplo, um caractere especial)
        $tempString = preg_replace($pattern, "$1<DELIMITER>", $string);

        // Usa o delimitador específico para dividir a string
        $parts = explode("<DELIMITER>", $tempString);

        // Remove espaços em branco no início e no final de cada parte
        $parts = array_map('trim', $parts);

        return $parts;
    }
    private function depara(string $table)
    {
        $columns = [];
        $keys = [];

        $pattern = "/CREATE TABLE `[^`]+` \((.*)\) ENGINE=.*/s";
        preg_match($pattern, $table, $matches);

        $columnsString = trim($matches[1]);

        // Divide a string em linhas
        $columnsArray = $this->explode(',', $columnsString);

        // Remover espaços em branco extras
        $columnsArray = array_map('trim', $columnsArray);

        // Filtrar linhas que não são definições de coluna
        $columnsArray = array_filter($columnsArray, function ($line) {
            return !preg_match('/^(PRIMARY|UNIQUE|KEY|FULLTEXT|SPATIAL|FOREIGN|CONSTRAINT|CHECK|INDEX)/i', $line);
        });

        // Agrupar linhas do tipo ENUM e SET que podem ser divididas incorretamente
        $groupedColumns = [];
        $currentColumn = '';
        foreach ($columnsArray as $line) {
            if (empty($currentColumn)) {
                $currentColumn = $line;
            } else {
                $currentColumn .= ", " . $line;
            }
            if (substr_count($currentColumn, '(') === substr_count($currentColumn, ')')) {
                $groupedColumns[] = $currentColumn;
                $currentColumn = '';
            }
        }

        // Iterar sobre as colunas e exibir informações
        foreach ($groupedColumns as $column) {
            // Pegar o nome da coluna
            preg_match('/`([^`]+)`/', $column, $fieldMatch);
            $fieldName = $fieldMatch[1];


            // Pegar o tipo da coluna
            preg_match('/`([\w)(0-9]+)` (\w+)(?:\(([^)]+)\))?(?: DEFAULT NULL)?/', $column, $typeMatch);
            if (empty($typeMatch) || count($typeMatch) <= 1)
                var_dump($typeMatch, $column);
            $fieldType = $typeMatch[2];
            if (isset($typeMatch[3])) {
                unset($typeMatch[0]);
                unset($typeMatch[1]);
                unset($typeMatch[2]);
                $fieldType .= "(" . implode(",", $typeMatch) . ")";
            }

            // Verificar se a coluna permite NULL
            $allowsNull = stripos($column, 'NOT NULL') === false;

            // Pegar valor default da coluna, se existir
            preg_match('/DEFAULT\s+([^,\s]+)/', $column, $defaultMatch);
            $defaultValue = isset($defaultMatch[1]) ? trim($defaultMatch[1], "'") : 'NULL';

            // Verificar se a coluna tem AUTO_INCREMENT
            $autoIncrement = stripos($column, 'AUTO_INCREMENT') !== false;
            $columns[] = [
                "Field" => $fieldName,
                "Type" => $fieldType,
                "Null" => ($allowsNull ? 'YES' : 'NO'),
                "Default" => $defaultValue,
                "Key" => false,
                "Extra" => ($autoIncrement ? 'AUTO_INCREMENT' : "")
            ];
        }

        // Regex para capturar chaves
        $keysPattern = "/(PRIMARY KEY|KEY|UNIQUE KEY|FULLTEXT KEY|SPATIAL KEY|FOREIGN KEY)\s*`?([^`(]*?)`?\s*\(([^)]+)\)/";

        // Regex para capturar partições
        // $partitionsPattern = "/PARTITION BY ([^ ]+) \(([^)]+)\)\s*\(([^)]+)\)/s";

        // Capturar chaves
        preg_match_all($keysPattern, $table, $keysMatches, PREG_SET_ORDER);

        // Capturar partições
        // preg_match($partitionsPattern, $table, $partitionsMatch);

        foreach ($keysMatches as $match) {
            $keys[] = [
                "Type" => $match[1],
                "Name" => $match[2],
                "Columns" => $match[3],
            ];
        }

        return [
            "columns" => $columns,
            "keys" => $keys
        ];
    }

    public function addPartitions(array $info)
    {
        foreach ($info as $partition) {
            $partition = new Partitions($partition['PARTITION_NAME'], $partition['PARTITION_METHOD'], $partition['PARTITION_EXPRESSION'], $partition['PARTITION_DESCRIPTION']);
            $this->partitions[] = $partition;
        }
    }

    public function addKeys(array $info)
    {
        foreach ($info as $key)
            $this->keys[] = new Key($key['Name'], $key["Type"], $key['Columns']);
    }

    public function getCreateTable()
    {

        return $this->create_table . "\n";
        // $columns = implode(",\n    ", array_map(fn ($el) => $el->getStructure(), $this->columns));
        // $keys = implode(",\n    ", array_map(fn ($el) => $el->getStructure(), $this->keys));
        // $g_partitions = [];
        // $partitions = '';
        // foreach ($this->partitions as $g_partition) {
        //     $method = $g_partition->method();
        //     $expression = $g_partition->expression();
        //     if (!array_key_exists($method, $g_partitions))
        //         $g_partitions[$method] = [];
        //     if (!array_key_exists($expression, $g_partitions[$method]))
        //         $g_partitions[$method][$expression] = [];
        //     $g_partitions[$method][$expression][] = $g_partition->getStructure();
        // }

        // foreach ($g_partitions as $method => $e_partititons) {
        //     foreach ($e_partititons as $expression => $f_partition) {
        //         $partitions .= "PARTITION BY $method($expression) \n (" . implode(",\n    ", array_map(fn ($el) => $el, $f_partition)) . ")\n";
        //     }
        // }

        // if (!empty($partitions)) {
        //     $partitions = "\n/*!50100 $partitions */";
        // }
        // //var_dump($partitions);
        // return "CREATE TABLE " . $this->base . "." . $this->name . " (\n    $columns" . (!empty($keys) ? ",\n   $keys" : "") . "\n)$partitions;\n";
    }

    public function getDropTable()
    {
        return "DROP TABLE " . $this->base . "." . $this->name . ";\n";
    }

    public function getColumn(string $column_name)
    {
        $finder =  array_filter($this->columns, fn ($el) => $el->field() == $column_name);
        if (empty($finder)) return false;
        return array_pop($finder);
    }

    public function getKey(string $key_name)
    {
        $finder =  array_filter($this->keys, fn ($el) => $el->name() == $key_name);
        if (empty($finder)) return false;
        return array_pop($finder);
    }

    public function getParition(string $key_name)
    {
        $finder =  array_filter($this->partitions, fn ($el) => $el->name() == $key_name);
        if (empty($finder)) return false;
        return array_pop($finder);
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return Key[]
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @return Partitions[]
     */
    public function getPartitions(): array
    {
        return $this->partitions;
    }

    public function hasPartition(): bool
    {
        return !empty($this->partitions);
    }

    public function getCreateAllPartitions($base, $table): string
    {
        if (!$this->hasPartition()) return "";
        $p_ref = $this->partitions[0];
        $method = $p_ref->method();
        $script = "";
        switch ($method) {
            case "RANGE":
                $partition = implode(",\n", array_map(fn ($el) => $el->getStructure(), $this->partitions));
                $script .= "ALTER TABLE $base.$table PARTITION BY RANGE(" . $p_ref->expression() . ") $partition ; \n";
        }

        return $script;
    }

    public function equilizeTable(Table $reference_table): string
    {
        $script = "";

        //Check Columns structure
        foreach ($this->columns as $column) {
            if (!$compare_column = $reference_table->getColumn($column->field())) {
                $script .= $column->getDropColumn($this->base, $this->name);
                continue;
            }
            if (!$compare_column->isSame($column)) {
                $script .= $compare_column->getModifyColumn($this->base, $this->name);
                continue;
            }
        }

        foreach ($reference_table->getColumns() as $reference_column) {
            if (!$compare_column = $this->getColumn($reference_column->field())) {
                $script .= $reference_column->getAddColumn($this->base, $this->name);
                continue;
            }
        }

        //Check keys structure
        foreach ($this->keys as $key) {
            if (!$compare_key = $reference_table->getKey($key->name())) {
                $script .= $key->getDropKey($this->base, $this->name);
                continue;
            }

            if (!$compare_key->isSame($key)) {
                $script .= $compare_key->getDropKey($this->base, $this->name);
                $script .= $compare_key->getCreateKey($this->base, $this->name);
                continue;
            }
        }

        foreach ($reference_table->getKeys() as $reference_key) {
            if (!$compare_key = $this->getKey($reference_key->name())) {
                $script .= $reference_key->getCreateKey($this->base, $this->name);
                continue;
            }
        }

        //Check partitions structure
        if ($reference_table->hasPartition() && !$this->hasPartition()) {
            $script .= $reference_table->getCreateAllPartitions($this->base, $this->name);
        } else {
            foreach ($reference_table->getPartitions() as $reference_partition) {
                if (!$this->getParition($reference_partition->name())) {
                    $script .= $reference_partition->getCreatePartition($this->base, $this->name);
                    continue;
                }
            }
            foreach ($this->getPartitions() as $reference_partition) {
                if (!$reference_table->getParition($reference_partition->name())) {
                    $script .= $reference_partition->getDropPartition($this->base, $this->name);
                    continue;
                }
            }
        }

        return $script;
    }
}
