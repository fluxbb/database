<?php

/**
 * The PgSQL database supports PostgreSQL
 * http://uk3.php.net/manual/en/book.pgsql.php
 * 
 * Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Database_PgSQL extends Database
{
	const SQL_DIALECT = 'pgsql';

	protected $resource;

	/**
	* Initialise a new PgSQL database.
	*/
	public function __construct($config)
	{
		if (!extension_loaded('pgsql'))
			throw new Exception('The PgSQL database requires the PgSQL extension.');

		// If we were given a PgSQL instance use that
		if (isset($config['instance']))
			$this->resource = $config['instance'];
		else
		{
			$options = array();
			foreach ($config as $key => $value)
				$options[] = $key.'=\''.addslashes($value).'\'';

			$this->resource = @pg_connect(implode(' ', $options));

			// Check if the connection was successful
			if ($this->resource === false)
				throw new Exception('Error connecting to PgSQL server'); // TODO: Why?
		}

		pg_query($this->resource, 'SET NAMES \'utf8\'');
	}

	protected function _query($query)
	{
		return @pg_query($this->resource, $query);
	}

	public function start_transaction()
	{
		return pg_query($this->resource, 'BEGIN');
	}

	public function commit_transaction()
	{
		return pg_query($this->resource, 'COMMIT');
	}

	public function rollback_transaction()
	{
		return pg_query($this->resource, 'ROLLBACK');
	}

	public function escape($str)
	{
		return '\''.pg_escape_string($this->resource, $str).'\'';
	}

	public function insert_id()
	{
		// TODO: Needs hacked
	}

	public function error()
	{
		return pg_last_error($this->resource);
	}

	public function affected_rows($result)
	{
		return pg_affected_rows($result);
	}

	public function has_rows($result)
	{
		return pg_num_rows($result) > 0;
	}

	public function fetch_row($result)
	{
		return pg_fetch_array($result, null, PGSQL_NUM);
	}

	public function fetch_assoc($result)
	{
		return pg_fetch_array($result, null, PGSQL_ASSOC);
	}

	public function free($result)
	{
		pg_free_result($result);
	}

	protected function _close()
	{
		pg_close($this->resource);
	}
}
