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
 * SQL Dialect for SQLite
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

namespace fluxbb\database\adapter;

class SQLite extends \fluxbb\database\Adapter
{
	public function generateDsn()
	{
		if (!isset($this->options['dbname'])) {
			throw new \Exception('No database name specified for SQLite database.');
		}

		return 'sqlite:'.$this->options['dbname'];
	}

	public function setCharset($charset)
	{
		$sql = 'PRAGMA encoding = '.$this->quote($charset);
		if ($this->exec($sql) === false)
			return;

		$this->charset = $charset;
	}

	/**
	 * Compile and run a TRUNCATE query.
	 * 
	 * @param query\Truncate $query
	 * @throws \Exception
	 */
	public function runTruncate(\fluxbb\database\query\Truncate $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A TRUNCATE query must have a table specified.');

		// Reset sequence counter
		$sql = 'DELETE FROM sqlite_sequence WHERE name = '.$this->quote($table).';';
		$sql .= 'DELETE FROM '.$table;

		try {
			$this->exec($sql);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
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
		$has_serial = false;
		foreach ($query->fields as $field) {
			// Workaround: AUTOINCREMENT columns have to be declared PRIMARY KEY in SQLite.
			// Thus we cannot declare the PRIMARY KEY later on.
			if ($field->type == \fluxbb\database\query\Helper_TableColumn::TYPE_SERIAL) {
				$has_serial = true;
			}
			$fields[] = $this->compileColumnDefinition($field);
		}

		try {
			$sql = 'CREATE TABLE '.$table.' ('.implode(', ', $fields);

			if (!empty($query->primary) && !$has_serial)
			{
				$sql .= ', PRIMARY KEY ('.implode(', ', $query->primary).')';
			}

			$sql .= ')';

			$this->exec($sql);

			if (!empty($query->indices))
			{
				foreach ($query->indices as $name => $index)
				{
					// Add indices manually
					$q = $this->addIndex($table, $name);
					$q->fields = $index['columns'];
					$q->unique = $index['unique'];
					$q->usePrefix = false;
					$q->run();
				}
			}
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Compile and run a TABLE EXISTS query.
	 * 
	 * @param query\TableExists $query
	 * @throws \Exception
	 */
	public function runTableExists(\fluxbb\database\query\TableExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A TABLE EXISTS query must have a table specified.');

		$sql = 'SELECT 1 FROM sqlite_master WHERE name = '.$this->quote($table).' AND type=\'table\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	/**
	 * Compile and run an ALTER FIELD query.
	 * 
	 * @param query\AlterField $query
	 * @throws \Exception
	 */
	public function runAlterField(\fluxbb\database\query\AlterField $query)
	{
		// SQLite does not need to change the type of the column, as long as the values are according to the type
		return true;
	}

	/**
	 * Compile and run a DROP FIELD query.
	 * 
	 * @param query\DropField $query
	 * @throws \Exception
	 */
	public function runDropField(\fluxbb\database\query\DropField $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A DROP FIELD query must have a table specified.');

		if (empty($query->field))
			throw new \Exception('A DROP FIELD query must have a field specified.');

		try {
			$now = time();
			$q = $this->tableInfo($table);
			$q->usePrefix = false;
			$table_info = $q->run();

			// Create temporary table
			$sql = 'CREATE TABLE '.$table.'_t'.$now.' AS SELECT * FROM '.$table;
			$this->exec($sql);

			unset($table_info['columns'][$query->field]);
			$new_columns = array_keys($table_info['columns']);

			$new_sql = 'CREATE TABLE '.$table.' (';

			foreach ($table_info['columns'] as $cur_column => $column)
			{
				$new_sql .= "\n".$cur_column.' '.$column['type'].(!empty($column['default']) ? ' DEFAULT '.$column['default'] : '').($column['allow_null'] ? '' : ' NOT NULL').',';
			}

			if (isset($table_info['unique'])) {
				foreach ($table_info['unique'] as $unique) {
					$new_sql .= "\n".'UNIQUE ('.implode(', ', $unique).'),';
				}
			}

			if (!empty($table_info['primary_key']))
				$new_sql .= "\n".'PRIMARY KEY ('.implode(', ', $table_info['primary_key']).'),';

			$new_sql = trim($new_sql, ',')."\n".');';

			// Drop old table
			$this->exec('DROP TABLE '.$table);

			// Create new table
			$this->exec($new_sql);

			// Recreate indexes
			if (!empty($table_info['indices']))
			{
				foreach ($table_info['indices'] as $index_name => $cur_index)
				{
					if (!in_array($query->field, $cur_index['fields']))
					{
						$q = $this->dropIndex($table, $index_name);
						$q->usePrefix = false;
						$q->run();
					}
				}
			}

			// Copy content back
			$this->exec('INSERT INTO '.$query->getTable().' SELECT '.implode(', ', $new_columns).' FROM '.$query->getTable().'_t'.$now);

			$this->exec('DROP TABLE '.$query->getTable().'_t'.$now);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Compile and run a FIELD EXISTS query.
	 * 
	 * @param query\FieldExists $query
	 * @throws \Exception
	 */
	public function runFieldExists(\fluxbb\database\query\FieldExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A FIELD EXISTS query must have a table specified.');

		if (empty($query->field))
			throw new \Exception('A FIELD EXISTS query must have a field specified.');

		$result = $this->query('PRAGMA table_info('.$table.')');
		foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row)
		{
			if ($row['name'] == $query->field)
			{
				return true;
			}
		}
		return false;
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
			$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$table.'_'.$query->index.' ON '.$table.'('.implode(',', $query->fields).')';
			$this->exec($sql);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Compile and run a DROP INDEX query.
	 * 
	 * @param query\DropIndex $query
	 * @throws \Exception
	 */
	public function runDropIndex(\fluxbb\database\query\DropIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('A DROP INDEX query must have a table specified.');

		if (empty($query->index))
			throw new \Exception('A DROP INDEX query must have an index specified.');

		try {
			$sql = 'DROP INDEX '.$table.'_'.$query->index;
			$this->exec($sql);
		} catch (\PDOException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Compile and run an INDEX EXISTS query.
	 * 
	 * @param query\IndexExists $query
	 * @throws \Exception
	 */
	public function runIndexExists(\fluxbb\database\query\IndexExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new \Exception('An INDEX EXISTS query must have a table specified.');

		if (empty($query->index))
			throw new \Exception('An INDEX EXISTS query must have an index specified.');

		$sql = 'SELECT 1 FROM sqlite_master WHERE name = '.$this->quote($table.'_'.$query->index).' AND tbl_name = '.$this->quote($table).' AND type=\'index\'';
		return (bool) $this->query($sql)->fetchColumn();
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

		// Work out the columns in the table
		$result = $this->query('PRAGMA table_info('.$table.')');
		foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row)
		{
			$table_info['columns'][$row['name']] = array(
				'type'			=> $row['type'],
				'allow_null'	=> $row['notnull'] == 0,
			);

			if ($row['dflt_value'] !== NULL) {
				if (substr($row['dflt_value'], 0, 1) == '\'' && substr($row['dflt_value'], -1) == '\'') {
					$row['dflt_value'] = substr($row['dflt_value'], 1, -1);
				} else if ($row['dflt_value'] == 'NULL') {
					$row['dflt_value'] = NULL;
				}

				$table_info['columns'][$row['name']]['default'] = $row['dflt_value'];
			}

			if ($row['pk'] == 1)
			{
				$table_info['primary_key'][] = $row['name'];
			}
		}

		$result = $this->query('PRAGMA index_list('.$table.')');
		foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $cur_index)
		{
			// Ignore automatically-generated indices (like primary keys)
			if (substr($cur_index['name'], 0, 17) == 'sqlite_autoindex_')
			{
				continue;
			}

			// Remove table name prefix
			if (substr($cur_index['name'], 0, strlen($table.'_')) == $table.'_') {
				$index_name = substr($cur_index['name'], strlen($table.'_'));
			}

			$table_info['indices'][$index_name] = array(
				'fields'	=> array(),
				'unique'	=> $cur_index['unique'] != 0,
			);

			if ($cur_index['unique'] != 0)
			{
				$table_info['unique'][] = array();
				$k = count($table_info['unique']) - 1;
			}

			$r2 = $this->query('PRAGMA index_info('.$cur_index['name'].')');
			foreach ($r2->fetchAll(\PDO::FETCH_ASSOC) as $row)
			{
				if ($cur_index['unique'] != 0)
				{
					$table_info['unique'][$k][] = $row['name'];
				}

				$table_info['indices'][$index_name]['fields'][] = $row['name'];
			}
		}

		return $table_info;
	}

	/**
	 * Compile a table column type definition for serial columns.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT';
	}

	/**
	 * Compile LIMIT and OFFSET clauses.
	 *
	 * @param int $limit
	 * @param int $offset
	 * @return string
	 */
	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
			$limit = -1;

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
