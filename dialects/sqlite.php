<?php

/**
 * SQL Dialect for SQLite
 *
 * Copyright (C) 2011 FluxBB (http://fluxbb.org)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLiteDialect extends SQLDialect
{
	public function __construct($db, $args = array())
	{
		parent::__construct($db, $args = array());
	}

	public function set_names($charset)
	{
		return 'PRAGMA encoding = '.$this->db->quote($charset);
	}

	protected function truncate(TruncateQuery $query)
	{
		if (empty($query->table))
			throw new Exception('A TRUNCATE query must have a table specified.');

		// Reset sequence counter
		$deleteQuery = new DirectQuery('DELETE FROM sqlite_sequence WHERE name = :name');
		$params = array(':name' => $query->table);
		$this->db->query($deleteQuery, $params);

		return 'DELETE FROM '.($query->use_prefix ? $this->db->prefix : '').$query->table;
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
			$sql .= ' LIMIT '.intval($limit);

		if ($offset > 0)
			$sql .= ' OFFSET '.intval($offset);

		return $sql;
	}
}
