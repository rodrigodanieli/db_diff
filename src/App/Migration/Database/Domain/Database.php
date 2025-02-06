<?php

namespace Migration\Database\Domain;


class Database
{
    private string $user;
    private string $pass;
    private string $host;
    private string $port;
    private int $project;
    private \PDO $connection;

    public function __construct(
        string $user,
        string $pass,
        string $host,
        string $port,
        int $project
    ) {
        $this->user = $user;
        $this->pass = $pass;
        $this->host = $host;
        $this->port = $port;
        $this->project = $project;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function getConnection(): \PDO
    {
        if (isset($this->connection)) return $this->connection;

        $dns = "mysql:host=" . $this->host . ";port=" . $this->port;

        return new \PDO($dns, $this->user, $this->pass);
    }

    public static function create(array $params): self
    {
        $config = json_decode($params['connection_config'], true);

        return new self($config['user'], $config['pass'], $config['host'], $config['port'], $params['project_id']);
    }
}
