<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';
require_once dirname(__FILE__).'/../AdapterTest.php';

class Flux_Database_Adapter_PgSQLTest extends Flux_Database_AdapterTest
{
	public function createAdapter()
	{
		if (!in_array('pgsql', PDO::getAvailableDrivers())) {
            $this->markTestSkipped(
              'The PgSQL driver cannot be loaded.'
            );
        }
        
        $conf = array(
        		'dbname'	=> $_ENV['DB_PGSQL_DBNAME'],
        		'host'		=> $_ENV['DB_PGSQL_HOST'],
        		'username'	=> $_ENV['DB_PGSQL_USER'],
        		'password'	=> $_ENV['DB_PGSQL_PASSWD'],
        );
        
        $adapter = Flux_Database_Adapter::factory('PgSQL', $conf);
        
        $result = $adapter->query('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\'');
        while ($table = $result->fetchColumn()) {
        	$adapter->exec('DROP TABLE '.$table);
        }
        
        return $adapter;
	}
}
