<?php

/**
 * SQL Dialect for MySQL
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Flux_Database_Adapter_MySQL extends Flux_Database_Adapter
{
	const DEFAULT_ENGINE = 'MyISAM';

	protected $engine;

	public function __construct(array $options = array())
	{
		parent::__construct($options);

		$this->engine = isset($options['engine']) ? $options['engine'] : self::DEFAULT_ENGINE;
	}

	public function generateDsn()
	{
		$args = array();

		if (isset($this->options['host'])) {
			$args[] = 'host='.$this->options['host'];
		}

		if (isset($this->options['port'])) {
			$args[] = 'port='.$this->options['port'];
		}

		if (isset($this->options['dbname'])) {
			$args[] = 'dbname='.$this->options['dbname'];
		} else {
			throw new Exception('No database name specified for MySQL database.');
		}

		// TODO: unix_socket and possibly charset?

		return 'mysql:'.implode(';', $args);
	}

	public function runCreateTable(Flux_Database_Query_CreateTable $query)
	{
		$sql = parent::runCreateTable($query);

		// TODO: Maybe allow for this function to overwrite the engine for just one query
		if (!empty($this->engine))
			$sql .= ' ENGINE = '.$this->quote($this->engine);

		if (!empty($this->charset))
			$sql .= ' CHARSET = '.$this->quote($this->charset);

		return $sql;
	}
}
