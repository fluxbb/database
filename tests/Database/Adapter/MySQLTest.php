<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_MySQLTest extends Flux_Database_AdapterTest
{
	public function createAdapter()
	{
		if (!in_array('mysql', PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The MySQL driver cannot be loaded.'
            );
        }

		$conf = array(
			'dbname'	=> $_ENV['DB_MYSQL_DBNAME'],
			'host'		=> $_ENV['DB_MYSQL_HOST'],
			'username'	=> $_ENV['DB_MYSQL_USER'],
			'password'	=> $_ENV['DB_MYSQL_PASSWD'],
		);

		$adapter = Flux_Database_Adapter::factory('MySQL', $conf);

		$result = $adapter->query('SHOW TABLES');
		while ($table = $result->fetchColumn()) {
			$adapter->exec('DROP TABLE '.$table);
		}

		return $adapter;
	}
}
