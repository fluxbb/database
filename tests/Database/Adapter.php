<?php

define('PHPCACHE_ROOT', realpath(dirname(__FILE__).'/../../src/').'/');
require PHPCACHE_ROOT.'Database/Adapter.php';

abstract class Flux_Database_AdapterTestCase extends PHPUnit_Framework_TestCase
{
	/**
	 * @var Flux_Database_Adapter
	 */
	protected $db;

	public function setUp()
	{
		$this->db = $this->createAdapter();
	}

	/**
	 * @return Flux_Database_Adapter
	 */
	abstract public function createAdapter();
}
