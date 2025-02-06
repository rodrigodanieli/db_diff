<?php


namespace Migration\Table\Domain\Properties;

class Key
{
    private string $name;
    private string $columns;
    private string $type = "KEY";

    public function __construct(
        string $name = "",
        string $type = 'KEY',
        string $columns = ""
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->columns = $columns;
    }

    public function name() :string
    {
        return $this->name;
    }

    public function getStructure(): string
    {
        return $this->type . " " . $this->name . " (" . $this->columns . ")";
    }

    private function isPrimaryKey(): bool
    {
        return $this->type === "PRIMARY KEY";
    }

    private function isUnique(): bool
    {
        return $this->type === "UNIQUE KEY";
    }

    public function isSame(Key $compare_key): bool
    {
        return $this->getStructure() == $compare_key->getStructure();
    }

    public function getCreateKey(string $base, string $table)
    {
        if ($this->isPrimaryKey())
            return "ALTER TABLE $base.$table ADD PRIMARY KEY (" .  $this->columns . ");\n";
        $unique = "";
        if ($this->isUnique())
            $unique = "UNIQUE";
        return "CREATE $unique INDEX `" . $this->name . "` ON $base.$table (" . $this->columns . ");\n";
    }

    public function getDropKey(string $base, string $table)
    {
        if ($this->isPrimaryKey())
            return "ALTER TABLE $base.$table DROP PRIMARY KEY;\n";
        return "DROP INDEX `" . $this->name . "` ON $base.$table;\n";
    }
}
