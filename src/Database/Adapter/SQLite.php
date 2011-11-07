<?php

/**
 * SQL Dialect for SQLite
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Flux_Database_Adapter_SQLite extends Flux_Database_Adapter
{
	public function generateDsn()
	{
		if (!isset($this->options['dbname'])) {
			throw new Exception('No database name specified for SQLite database.');
		}

		return 'sqlite:'.$this->options['dbname'];
	}

	public function setNames($charset)
	{
		$sql = 'PRAGMA encoding = '.$this->quote($charset);
		if ($this->exec($sql) === false)
			return;

		$this->charset = $charset;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		// Reset sequence counter
		$sql = 'DELETE FROM sqlite_sequence WHERE name = '.$this->quote($table).';';
		$sql .= 'DELETE FROM '.$table;

		return $this->exec($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A TABLE EXISTS query must have a table specified.');
		
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = '.$this->quote($table).' AND type=\'table\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAlterField(Flux_Database_Query_AlterField $query)
	{
		// SQLite does not need to change the type of the column, as long as the values are according to the type
		return true;
	}

	public function runDropField(Flux_Database_Query_DropField $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A DROP FIELD query must have a table specified.');
		
		if (empty($query->field))
			throw new Exception('A DROP FIELD query must have a field specified.');
		
		// Fetch table SQL
		$result = $this->query('SELECT sql FROM sqlite_master WHERE type = \'table\' AND tbl_name = '.$this->quote($table));

		$table_sql = $result->fetchColumn();
		if ($table_sql == NULL)
		{
			return false;
		}

		// Create temporary table
		$now = time();
		// TODO: Use tableInfo data instead to create table
		$tmptable_sql = str_replace('CREATE TABLE '.$table.' (', 'CREATE TABLE '.$table.'_t'.$now.' (', $table_sql);
		$this->exec($tmptable_sql);

		$this->exec('INSERT INTO '.$table.'_t'.$now.' SELECT * FROM '.$table);

		$table_info = $this->tableInfo($table)->run();

		unset($table_info['columns'][$query->field]);
		$new_columns = array_keys($table_info['columns']);

		$new_sql = 'CREATE TABLE '.$table.' (';

		foreach ($table_info['columns'] as $cur_column => $column)
		{
			$new_sql .= "\n".$cur_column.' '.$column['type'].(!empty($column['default']) ? ' DEFAULT '.$column['default'] : '').($column['allow_null'] ? '' : ' NOT NULL').',';
		}

		// TODO!
		if (isset($table_info['unique']))
			$new_sql .= "\n".$table_info['unique'].',';

		if (!empty($table_info['primary_key']))
			$new_sql .= "\n".'PRIMARY KEY ('.$table_info['primary_key'].'),';

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
					$this->dropIndex($table, $index_name)->run();
				}
			}
		}

		// Copy content back
		$this->exec('INSERT INTO '.$query->getTable().' SELECT '.implode(', ', $new_columns).' FROM '.$query->getTable().'_t'.$now);

		$this->exec('DROP TABLE '.$query->getTable().'_t'.$now);

		// TODO: Handle query errors
		return true;
	}

	public function runFieldExists(Flux_Database_Query_FieldExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A FIELD EXISTS query must have a table specified.');
		
		if (empty($query->field))
			throw new Exception('A FIELD EXISTS query must have a field specified.');
		
		$result = $this->query('PRAGMA table_info('.$table.')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			if ($row['name'] == $query->field)
			{
				return true;
			}
		}
		return false;
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('An ADD INDEX query must have a table specified.');
		
		if (empty($query->index))
			throw new Exception('An ADD INDEX query must have an index specified.');
		
		if (empty($query->fields))
			throw new Exception('An ADD INDEX query must have at least one field specified.');
		
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$table.'_'.$query->index.' ON '.$table.'('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A DROP INDEX query must have a table specified.');
		
		if (empty($query->index))
			throw new Exception('A DROP INDEX query must have an index specified.');
		
		$sql = 'DROP INDEX '.$table.'_'.$query->index;
		return $this->exec($sql);
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('An INDEX EXISTS query must have a table specified.');
		
		if (empty($query->index))
			throw new Exception('An INDEX EXISTS query must have an index specified.');
		
		$sql = 'SELECT 1 FROM sqlite_master WHERE name = '.$this->quote($table.'_'.$query->index).' AND tbl_name = '.$this->quote($table).' AND type=\'index\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runTableInfo(Flux_Database_Query_TableInfo $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A TABLE INFO query must have a table specified.');
		
		$table_info = array(
			'columns'		=> array(),
			'primary_key'	=> '',
			'unique'		=> array(),
			'indices'		=> array(),
		);

		// Work out the columns in the table
		$result = $this->query('PRAGMA table_info('.$table.')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$table_info['columns'][$row['name']] = array(
				'type'			=> $row['type'],
				'default'		=> $row['dflt_value'],
				'allow_null'	=> $row['notnull'] == 0,
			);

			if ($row['pk'] == 1)
			{
				$table_info['primary_key'] = $row['name'];
			}
		}

		$result = $this->query('PRAGMA index_list('.$table.')');
		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $cur_index)
		{
			// Ignore automatically-generated indices (like primary keys)
			if (substr($cur_index['name'], 0, 17) == 'sqlite_autoindex_')
			{
				continue;
			}

			$r2 = $this->query('PRAGMA index_info('.$cur_index['name'].')');

			$table_info['indices'][$cur_index['name']] = array(
				'fields'	=> array(),
				'unique'	=> $cur_index['unique'] != 0,
			);

			if ($cur_index['unique'] != 0)
			{
				$table_info['unique'][] = array();
				$k = count($table_info) - 1;
			}

			foreach ($r2->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
				if ($cur_index['unique'] != 0)
				{
					$table_info['unique'][$k][] = $row['name'];
				}

				$table_info['indices'][$cur_index['name']]['fields'][] = $row['name'];
			}
		}

		return $table_info;
	}

	protected function compileColumnSerial($name)
	{
		return $name.' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT';
	}

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
