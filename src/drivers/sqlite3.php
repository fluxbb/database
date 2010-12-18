<?php

/**
 * The SQLite3 database supports SQLite3
 * http://uk3.php.net/manual/en/book.sqlite3.php
 * 
 * Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Database_SQLite3 extends Database
{
	const SQL_DIALECT = 'sqlite';

	protected $sqlite;

	/**
	* Initialise a new SQLite3 database.
	*/
	public function __construct($config)
	{
		if (!extension_loaded('sqlite3'))
			throw new Exception('The SQLite3 database requires the SQLite3 extension.');

		// If we were given a SQLite3 instance use that
		if (isset($config['instance']))
			$this->sqlite = $config['instance'];
		else
		{
			if (!isset($config['filename']))
				throw new Exception('Missing SQLite3 argument: filename');

			if (isset($config['encryption_key']))
				$this->sqlite = new SQLite3($config['filename'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $config['encryption_key']);
			else
				$this->sqlite = new SQLite3($config['filename'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

			// TODO: Check for errors
		}
	}

	protected function _query($query)
	{
		return @$this->sqlite->query($query);
	}

	public function start_transaction()
	{
		return $this->sqlite->query('BEGIN');
	}

	public function commit_transaction()
	{
		return $this->sqlite->query('COMMIT');
	}

	public function rollback_transaction()
	{
		return $this->sqlite->query('ROLLBACK');
	}

	public function escape($str)
	{
		return '\''.$this->sqlite->escapeString($str).'\'';
	}

	public function insert_id()
	{
		return $this->sqlite->lastInsertRowID();
	}

	public function error()
	{
		return $this->sqlite->lastErrorMsg();
	}

	public function affected_rows($result)
	{
		return $this->sqlite->changes();
	}

	public function has_rows($result)
	{
		return $result->numColumns() > 0 && $result->columnType(0) != SQLITE3_NULL;
	}

	public function fetch_row($result)
	{
		return $result->fetchArray(SQLITE3_NUM);
	}

	public function fetch_assoc($result)
	{
		// TODO: Remove the aliases?
		return $result->fetchArray(SQLITE3_ASSOC);
	}

	public function free($result)
	{
		
	}

	protected function _close()
	{
		$this->sqlite->close();
	}
}
