<?php
/**
 * FluxBB
 *
 * LICENSE
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category	FluxBB
 * @package		Database
 * @subpackage	Adapter
 * @copyright	Copyright (c) 2011 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/lgpl.html	GNU Lesser General Public License
 */

/**
 * SQL Dialect for MySQL
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

namespace fluxbb\database\adapter;

class MySQL extends \fluxbb\database\Adapter
{
	const DEFAULT_ENGINE = 'MyISAM';

	protected $engine;

	public function __construct(array $options = array())
	{
		parent::__construct($options);

		$this->engine = isset($options['engine']) ? $options['engine'] : self::DEFAULT_ENGINE;

		if (!isset($this->options['driver_options'])) {
			$this->options['driver_options'] = array();
		}
		$this->options['driver_options'][\PDO::MYSQL_ATTR_FOUND_ROWS] = true;
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
			throw new \Exception('No database name specified for MySQL database.');
		}

		return 'mysql:'.implode(';', $args);
	}

	/**
	 * Compile and run a CREATE TABLE query.
	 * 
	 * @param query\CreateTable $query
	 * @throws \Exception
	 */
	public function runCreateTable(\fluxbb\database\query\CreateTable $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A CREATE TABLE query must have a table specified.');

		if (empty($query->fields))
			throw new \Exception('A CREATE TABLE query must contain at least one field.');

		$fields = array();
		foreach ($query->fields as $field)
			$fields[] = $this->compileColumnDefinition($field);

		try {
			$sql = 'CREATE TABLE '.$table.' ('.implode(', ', $fields);

			if (!empty($query->primary))
			{
				$sql .= ', PRIMARY KEY ('.implode(', ', $query->primary).')';
			}

			if (!empty($query->indices))
			{
				foreach ($query->indices as $name => $index)
				{
					$sql .= ', '.($index['unique'] ? ' UNIQUE' : '').' KEY '.$table.'_'.$name.' ('.implode(', ', $index['columns']).')';
				}
			}

			$sql .= ')';

			if (!empty($query->engine))
				$sql .= ' ENGINE = '.$this->quote($query->engine);
			else if (!empty($this->engine))
				$sql .= ' ENGINE = '.$this->quote($this->engine);

			if (!empty($this->charset))
				$sql .= ' CHARSET = '.$this->quote($this->charset);

			$this->exec($sql);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Compile and run an ADD INDEX query.
	 * 
	 * @param query\AddIndex $query
	 * @throws \Exception
	 */
	public function runAddIndex(\fluxbb\database\query\AddIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('An ADD INDEX query must have a table specified.');

		if (empty($query->index))
			throw new \Exception('An ADD INDEX query must have an index specified.');

		if (empty($query->fields))
			throw new \Exception('An ADD INDEX query must have at least one field specified.');

		try {
			$sql = 'ALTER TABLE '.$table.' ADD '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$table.'_'.$query->index.' ('.implode(',', $query->fields).')';
			$this->exec($sql);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Run a table info query.
	 *
	 * @param query\TableInfo $query
	 */
	public function runTableInfo(\fluxbb\database\query\TableInfo $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A TABLE INFO query must have a table specified.');

		$table_info = array(
				'columns'		=> array(),
				'primary_key'	=> array(),
				'unique'		=> array(),
				'indices'		=> array(),
		);

		// Fetch column information
		$result = $this->query('DESCRIBE '.$table);
		foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row)
		{
			$table_info['columns'][$row['Field']] = array(
					'type'			=> $this->understandColumnType($row['Type']),
					'allow_null'	=> $row['Null'] == 'YES',
			);

			if ($row['Default'] !== NULL || $row['Null'] == 'YES') {
				$table_info['columns'][$row['Field']]['default'] = $row['Default'];
			}
		}

		// Fetch all indices
		$result = $this->query('SHOW INDEXES FROM '.$table);
		foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row)
		{
			// Save primary key
			if ($row['Key_name'] == 'PRIMARY')
			{
				$table_info['primary_key'][] = $row['Column_name'];
				continue;
			}

			// Remove table name prefix
			if (substr($row['Key_name'], 0, strlen($table.'_')) == $table.'_') {
				$row['Key_name'] = substr($row['Key_name'], strlen($table.'_'));
			}

			if (!isset($table_info['indices'][$row['Key_name']]))
			{
				$table_info['indices'][$row['Key_name']] = array(
						'fields'	=> array(),
						'unique'	=> $row['Non_unique'] != 1,
				);

				if ($row['Non_unique'] != 1)
				{
					$table_info['unique'][] = array($row['Column_name']);
				}
			}
			else if ($row['Non_unique'] != 1)
			{
				$table_info['unique'][count($table_info['unique']) -1][] = $row['Column_name'];
			}

			$table_info['indices'][$row['Key_name']]['fields'][] = $row['Column_name'];
		}

		return $table_info;
	}

	protected function understandColumnType($str)
	{
		// TODO: Complete implementation
		if (preg_match('%int\(.+%', $str)) {
			return 'INTEGER';
		}

		return $str;
	}
}
