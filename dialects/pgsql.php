<?php

/**
 * SQL Dialect for PostgreSQL
 * 
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_PgSQL extends SQLDialect
{
	protected function replace(ReplaceQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A REPLACE query must have a table specified.');

		if (empty($query->values))
			throw new Exception('A REPLACE query must contain at least 1 value.');

		$keys = array();
		foreach ($query->keys as $key)
		{
			$value = $query->values[$key];
			$keys[] = $key.' = '.$value;
		}

		$sql = 'INTO INTO '.$this->prefix.$query->table.' ('.implode(', ', array_keys($query->values)).') SELECT '.implode(', ', array_values($query->values)).' WHERE NOT EXISTS (SELECT 1 FROM '.$this->prefix.$query->table.' WHERE ('.implode(' AND ', $keys).'))';
	}

	protected function column_serial($name)
	{
		return $name.' SERIAL NOT NULL PRIMARY KEY';
	}

	protected function conditions($conditions)
	{
		$sql = parent::conditions($conditions);

		// Replace LIKE with ILIKE to get case insensitive match
		return preg_replace('%(\s)(LIKE)(\s)%i', '$1ILIKE$1', $sql);
	}

	protected function limit_offset($limit, $offset)
	{
		$sql = '';

		if ($limit > 0)
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
