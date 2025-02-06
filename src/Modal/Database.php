<?php

namespace App\Modal;

use PDO;
use Sohris\Core\Utils;

class Database extends PDO
{
    private $config;

    public function __construct()
    {
        $config = Utils::getConfigFiles('mysql');
        $dns = "mysql:host=$config[host];port=$config[port];dbname=$config[base]";
        parent::__construct($dns, $config['user'], $config['pass']);
    }

    private function execQuery($query)
    {
        $stm = $this->query($query);
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccounts()
    {
        return $this->execQuery("SELECT * FROM accounts WHERE active = 1;");
    }

    public function getDomains(int $account)
    {
        return $this->execQuery("SELECT * FROM domains WHERE account = $account and active = 1;");
    }

    public function getAvailableDomains()
    {
        return $this->execQuery("SELECT a.`key`, a.secret, d.name as domain_name, d.id as domain_id FROM accounts a JOIN domains d ON a.id = d.account WHERE a.active = 1 and d.active = 1;");
    }

    public function getDDNS()
    {
        return $this->execQuery("SELECT a.`key`, a.secret, d.name as domain_name, d.id as domain_id,  CONCAT(
                                            '[',
                                            GROUP_CONCAT(DISTINCT
                                                JSON_OBJECT(
                                                'id', da.id,
                                                'instance', da.instance_id,
                                                'ddns_name', da.ddns_name
                                                )
                                            ),
                                            ']'
                                            ) as instances
                                FROM accounts a 
                                JOIN domains d ON a.id = d.account 
                                JOIN ddns_aws da ON d.id = da.domain_id
                                WHERE a.active = 1 and d.active = 1 GROUP BY d.id ;");
    }
    public function updateDnsDDNS($id, $current_dns)
    {
        return $this->execQuery("UPDATE ddns_aws SET current_dns = '$current_dns', last_update = NOW() WHERE id = $id");
    }
}
