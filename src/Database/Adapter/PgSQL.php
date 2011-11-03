<?php

/**
 * SQL Dialect for PostgreSQL
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class Flux_Database_Adapter_PgSQL extends Flux_Database_Adapter
{
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
			throw new Exception('No database name specified for PostgreSQL database.');
		}

		return 'pgsql:'.implode(';', $args);
	}

	public function runReplace(Flux_Database_Query_Replace $query, array $params = array())
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A REPLACE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('A REPLACE query must contain at least 1 value.');

		$values = array();
		foreach ($query->values as $key => $value)
		{
			$values[] = $key.' = '.$value;
		}
		
		$keys = array();
		foreach ($query->keys as $key => $value)
		{
			$keys[] = $key.' = '.$value;
		}
		
		// Update if row exists
		$sql = 'UPDATE '.$table.' SET '.implode(', ', $values).' WHERE '.implode(' AND ', $keys);
		$r1 = $this->query($sql, $params);
		if ($r1->rowCount() > 0)
		{
			return 2;
		}
		
		// Insert if it did not
		$sql = 'INSERT INTO '.$table.' ('.implode(', ', array_keys($query->values)).') SELECT '.implode(', ', array_values($query->values)).' WHERE NOT EXISTS (SELECT 1 FROM '.$table.' WHERE ('.implode(' AND ', $keys).'))';
		$r2 = $this->query($sql, $params);
		
		return 1;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		$table = $query->getTable();
		if (empty($table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		$sql = 'TRUNCATE TABLE '.$table.' RESTART IDENTITY';
		return $this->exec($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$sql = 'SELECT 1 FROM pg_class WHERE relname = '.$this->quote($query->getTable());
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAlterField(Flux_Database_Query_AlterField $query)
	{
		$now = time();

		// Add a temporary field with new constraints and old values instead of the new one
		$subquery = $this->addField($query->getTable());
		$new_field = clone $query->field;
		$new_field->name = $new_field->name.'_t'.$now;
		$subquery->field = $new_field;
		$subquery->run();

		$this->exec('UPDATE '.$query->getTable().' SET '.$query->field->name.'_t'.$now.' = '.$query->field->name);
		$this->dropField($query->getTable(), $query->field->name)->run();
		$this->exec('ALTER TABLE '.$query->getTable().' RENAME COLUMN '.$query->field->name.'_t'.$now.' TO '.$query->field->name);

		return true;
	}

	public function runFieldExists(Flux_Database_Query_FieldExists $query)
	{
		$sql = 'SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = '.$this->quote($query->getTable()).' AND a.attname = '.$this->quote($query->field);
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->getTable().'_'.$query->index.' ON '.$query->getTable().' ('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$sql = 'SELECT 1 FROM pg_index i INNER JOIN pg_class c1 ON c1.oid = i.indrelid INNER JOIN pg_class c2 ON c2.oid = i.indexrelid WHERE c1.relname = '.$this->quote($query->getTable()).' AND c2.relname = '.$this->quote($query->getTable().'_'.$query->index);
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$sql = 'DROP INDEX '.$query->getTable().'_'.$query->index;
		return $this->exec($sql);
	}

	public function runTableInfo(Flux_Database_Query_TableInfo $query)
	{
		$table = array(
			'columns'		=> array(),
			'primary_key'	=> '',
			'unique'		=> '',
			'indices'		=> array(),
		);

		// Fetch column information
		$sql = 'SELECT column_name FROM information_schema.columns WHERE table_name = '.$this->quote($query->getTable()).' AND table_schema = '.$this->quote($this->options['dbname']).' ORDER BY ordinal_position ASC';
		$result = $this->query($sql);

		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$table['columns'][$row['column_name']] = array(
				'type'			=> $row['column_type'],
				'default'		=> $row['column_default'],
				'allow_null'	=> $row['is_nullable'] == 'YES',
			);

			if ($row['column_key'] == 'PRI')
			{
				$table['primary_key'] = $row['column_name'];
			}
		}

		// Fetch index information
		$sql = 'SELECT t.relname AS table_name, i.relname AS index_name, a.attname AS column_name, ix.indisunique FROM pg_class t, pg_class i, pg_index ix, pg_attribute a, pg_constraint c WHERE t.oid = ix.indrelid AND i.oid = ix.indexrelid AND a.attrelid = t.oid AND i.oid = c.conindid AND a.attnum = ANY(ix.indkey) AND c.contype != \'p\' AND t.relkind = \'r\' AND t.relname = '.$this->quote($query->getTable()).' ORDER BY t.relname, i.relname';
		$result = $this->query($sql);

		foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			if (!isset($table['indices'][$row['index_name']]))
			{
				$table['indices'][$row['index_name']] = array(
					'fields'	=> array(),
					'unique'	=> $row['indisunique'],
				);

				if ($row['indisunique'])
				{
					$table['unique'][] = $row['column_name'];
				}
			}
			else
			{
				// TODO: multiple primary keys?
				$table['unique'][count($table['unique']) - 1][] = $row['column_name'];
			}

			$table['indices'][$row['index_name']]['fields'][] = $row['column_name'];
		}
	}

	protected function compileColumnSerial($name)
	{
		return $name.' SERIAL NOT NULL PRIMARY KEY';
	}

	protected function compileConditions($conditions)
	{
		$sql = parent::compileConditions($conditions);

		// Replace LIKE with ILIKE to get case insensitive match
		// TODO: Really "$1" twice?
		return preg_replace('%(\s)(LIKE)(\s)%i', '$1ILIKE$1', $sql);
	}

	protected function compileLimitOffset($limit, $offset)
	{
		$sql = '';

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
