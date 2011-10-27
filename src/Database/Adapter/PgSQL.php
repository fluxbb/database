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

	public function compileReplace(Flux_Database_Query_Replace $query)
	{
		if (empty($query->getTable()))
			throw new Exception('A REPLACE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('A REPLACE query must contain at least 1 value.');

		$keys = array();
		foreach ($query->keys as $key)
		{
			$value = $query->values[$key];
			$keys[] = $key.' = '.$value;
		}

		// TODO: What if keys is just a string (like one query in include/functions.php)? This needs to be handled.
		$sql = 'INSERT INTO '.$query->getTable().' ('.implode(', ', array_keys($query->values)).') SELECT '.implode(', ', array_values($query->values)).' WHERE NOT EXISTS (SELECT 1 FROM '.$query->getTable().' WHERE ('.implode(' AND ', $keys).'))';
		return $sql;
	}

	public function runTruncate(Flux_Database_Query_Truncate $query)
	{
		if (empty($query->getTable()))
			throw new Exception('A TRUNCATE query must have a table specified.');

		$sql = 'TRUNCATE TABLE '.$query->getTable().' RESTART IDENTITY';
		return $this->exec($sql);
	}

	public function runTableExists(Flux_Database_Query_TableExists $query)
	{
		$sql = 'SELECT 1 FROM pg_class WHERE relname = \''.$query->getTable().'\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddField(Flux_Database_Query_AddField $query)
	{
		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		$this->exec('ALTER TABLE '.$query->getTable().' ADD '.$query->field->name.' '.$field_type);

		$default_value = $query->field->default;
		if ($default_value !== null)
		{
			if (!is_int($default_value) && !is_float($default_value))
				$default_value = '\''.$this->escape($default_value).'\'';

			$this->exec('ALTER TABLE '.$query->getTable().' ALTER '.$query->field->name.' SET DEFAULT '.$default_value);
			$this->exec('UPDATE '.$query->getTable().' SET '.$query->field->name.'='.$default_value);
		}

		// FIXME: allow null or can we just check that the default value is not null?
		if (!$query->field->allow_null)
			$this->exec('ALTER TABLE '.$query->getTable().' ALTER '.$query->field->name.' SET NOT NULL');

		// TODO: Return type!?
		return true;
	}

	public function runFieldExists(Flux_Database_Query_FieldExists $query)
	{
		$sql = 'SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = \''.$query->getTable().'\' AND a.attname = \''.$query->field.'\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runAddIndex(Flux_Database_Query_AddIndex $query)
	{
		$sql = 'CREATE '.($query->unique ? 'UNIQUE ' : '').'INDEX '.$query->getTable().'_'.$query->index.' ON '.$query->getTable().'('.implode(',', $query->fields).')';
		return $this->exec($sql);
	}

	public function runIndexExists(Flux_Database_Query_IndexExists $query)
	{
		$sql = 'SELECT 1 FROM pg_index i INNER JOIN pg_class c1 ON c1.oid = i.indrelid INNER JOIN pg_class c2 ON c2.oid = i.indexrelid WHERE c1.relname = \''.$query->getTable().'\' AND c2.relname = \''.$query->getTable().'_'.$query->index.'\'';
		return (bool) $this->query($sql)->fetchColumn();
	}

	public function runDropIndex(Flux_Database_Query_DropIndex $query)
	{
		$sql = 'DROP INDEX '.$query->getTable().'_'.$query->index;
		return $this->exec($sql);
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
