<?php

/**
 * The MySQLi database supports MySQLi
 * http://uk3.php.net/manual/en/book.mysqli.php
 * 
 * Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Database_MySQLi extends Database
{
	protected $mysqli;

	/**
	* Initialise a new MySQLi database.
	*/
	public function __construct($config)
	{
		if (!extension_loaded('mysqli'))
			throw new Exception('The MySQLi database requires the MySQLi extension.');

		// If we were given a MySQLi instance use that
		if (isset($config['instance']))
			$this->mysqli = $config['instance'];
		else
		{
			if (!isset($config['dbname']))
				throw new Exception('Missing MySQLi argument: dbname');

			// TODO: These defaults kinda suck
			$host = isset($config['host']) ? $config['host'] : @ini_get('mysqli.default_host');
			$port = isset($config['port']) ? $config['port'] : @ini_get('mysqli.default_port');

			$username = isset($config['username']) ? $config['username'] : @ini_get('mysqli.default_user');
			$password = isset($config['password']) ? $config['password'] : @ini_get('mysqli.default_pw');

			$this->mysqli = new MySQLi($host, $username, $password, $config['dbname'], $port);

			// TODO: Check for errors
		}

		$this->mysqli->query('SET NAMES \'utf8\'');
	}

	protected function _query($query)
	{
		return @$this->mysqli->query($query);
	}

	// TODO: Only supported in InnoDB
	public function start_transaction()
	{
		return $this->mysqli->query('BEGIN');
	}

	// TODO: Only supported in InnoDB
	public function commit_transaction()
	{
		return $this->mysqli->query('COMMIT');
	}

	// TODO: Only supported in InnoDB
	public function rollback_transaction()
	{
		return $this->mysqli->query('ROLLBACK');
	}

	public function escape($str)
	{
		return '\''.$this->mysqli->real_escape_string($str).'\'';
	}

	public function insert_id()
	{
		return $this->mysqli->insert_id;
	}

	public function error()
	{
		return $this->mysqli->error;
	}

	public function affected_rows($result)
	{
		return $this->mysqli->affected_rows;
	}

	public function has_rows($result)
	{
		return $result->num_rows > 0;
	}

	public function fetch_row($result)
	{
		return $result->fetch_array(MYSQLI_NUM);
	}

	public function fetch_assoc($result)
	{
		return $result->fetch_array(MYSQLI_ASSOC);
	}

	public function free($result)
	{
		$result->free();
	}

	protected function _close()
	{
		$this->mysqli->close();
	}
}
