<?php

require_once dirname(__FILE__).'/../../../src/Database/Adapter.php';

class Flux_Database_Adapter_MySQLTestCase extends Flux_Database_AdapterTestCase
{
	public function createAdapter()
	{
		return Flux_Database_Adapter::factory('MySQL');
	}
}
