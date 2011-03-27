<?php

/**
 * SQL Dialect for SQLite
 * 
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_SQLite extends SQLDialect
{
	protected function truncate(TruncateQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		return 'DELETE FROM '.$this->prefix.$query->table;
	}

	protected function set_names(SetNamesQuery $query)
	{
		return ''; // No need for SET NAMES in SQLite
	}

	protected function column_serial($name)
	{
		return $name.' INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT';
	}

	protected function limit_offset($limit, $offset)
	{
		$sql = '';

		if ($offset > 0 && $limit == 0)
			$limit = -1;

		if ($limit > 0)
			$sql .= ' LIMIT '.$limit;

		if ($offset > 0)
			$sql .= ' OFFSET '.$offset;

		return $sql;
	}
}
