<?php

namespace Migration\Data\Domain;

use App\Modal\Database;

class Data
{
    private string $table;
    private string $base;
    private array $data;

    public function __construct(string $table, string $base)
    {
        $this->base = $base;
        $this->table = $table;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function getScript(): string
    {
        $chunk = array_chunk($this->data, 10);
        $script = "TRUNCATE `" . $this->base . "`.`" . $this->table . "`;\n";
        $dao = new Database;
        foreach ($chunk as $info) {

            $values = implode(",", array_map(fn ($el) => "\n(" . implode(",", array_map(fn ($el2) => $el2 == "NULL" ? $el2 : ((strpos($el2, "UNHEX") === 0) ? $el2 : $dao->quote($el2)), $el)) . ")", $info));
            $script .= "INSERT INTO `" . $this->base . "`.`" . $this->table . "` VALUES $values;\n";
        }

        // foreach($this->data as $info)
        // {
        //     $values = "(" . implode(",", array_map(fn ($el2) => $el2 == "NULL" ? $el2 : ((strpos($el2, "UNHEX") === 0) ? $el2 : $el2), $info)) . ")";
        //     $script .= "INSERT INTO `" . $this->base . "`.`" . $this->table . "` VALUES $values;\n";
        // }
        return $script;
    }
}
