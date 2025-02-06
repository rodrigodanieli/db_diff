<?php


namespace Migration\Table\Domain\Properties;

class Partitions
{
    private string $name;
    private string $method;
    private string $expression;
    private string $description;

    public function __construct(
        string $name,
        string $method,
        string $expression,
        string $description
    ) {
        $this->name = $name;
        $this->method = $method;
        $this->expression = $expression;
        $this->description = $description;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function isValid(): bool
    {
        return $this->method == "RANGE";
    }


    public function getStructure(): string
    {
        if (!$this->isValid()) return "";

        switch ($this->method) {
            case "RANGE":
                return " PARTITION " . $this->name . " VALUES LESS THAN (" . $this->description . ") ";
        }

        return "";
    }

    public function isSame(Key $compare_key): bool
    {
        return $this->getStructure() == $compare_key->getStructure();
    }

    public function getCreatePartitions(string $base, string $table)
    {
        if (!$this->isValid()) return "";

        switch ($this->method) {
            case "RANGE":
                return "ALTER TABLE $base.$table PARTITION BY RANGE(" . $this->expression . ") (" . $this->getStructure() . ");\n";
        }
    }

    public function getCreatePartition(string $base, string $table)
    {
        if (!$this->isValid()) return "";

        switch ($this->method) {
            case "RANGE":
                return "ALTER TABLE $base.$table ADD PARTITION (" . $this->getStructure() . ");\n";
        }
    }

    public function getDropPartition(string $base, string $table)
    {
        if (!$this->isValid()) return "";

        switch ($this->method) {
            case "RANGE":
                return "ALTER TABLE $base.$table DROP PARTITION  " . $this->name . ";\n";
        }
    }

}
