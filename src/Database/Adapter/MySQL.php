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
		
		if (isset($this->options['unix_socket'])) {
			// Replace current arguments with unix socket, as they cannot be used together
			$args = array('unix_socket='.$this->options['unix_socket']);
		}

		if (isset($this->options['dbname'])) {
			$args[] = 'dbname='.$this->options['dbname'];
		} else {
			throw new Exception('No database name specified for MySQL database.');
		}

		return 'mysql:'.implode(';', $args);
	}

	public function runCreateTable(Flux_Database_Query_CreateTable $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A CREATE TABLE query must have a table specified.');
		
		if (empty($query->fields))
			throw new Exception('A CREATE TABLE query must contain at least one field.');
		
		$fields = array();
		foreach ($query->fields as $field)
			$fields[] = $this->compileColumnDefinition($field);
		
		try {
			$sql = 'CREATE TABLE '.$table.' ('.implode(', ', $fields);
		
			if (!empty($query->indices))
			{
				foreach ($query->indices as $index)
				{
					$sql .= ' ,'.($index['unique'] ? ' UNIQUE' : '').' KEY '.$table.'_'.$index['name'].' ('.implode(', ', $index['columns']).')';
				}
			}
			
			$sql .= ')';
		
			// TODO: Maybe allow for this function to overwrite the engine for just one query
			if (!empty($this->engine))
				$sql .= ' ENGINE = '.$this->quote($this->engine);
			
			if (!empty($this->charset))
				$sql .= ' CHARSET = '.$this->quote($this->charset);
			
			$this->exec($sql);
		} catch (PDOException $e) {
			return false;
		}
		
		return true;
	}
}
