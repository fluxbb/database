<?php

/**
 * SQL Dialect for SQLite
 * 
 * Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_SQLite extends SQLDialect
{
	const SET_NAMES = null;

	protected function limit_offset($limit, $offset)
	{
		$sql = '';

		if ($offset !== 0 && $limit === 0)
			$limit = -1;

		if ($limit !== 0)
			$sql .= ' LIMIT '.$limit;

		if ($offset !== 0)
			$sql .= ' OFFSET '.$offset;

		return $sql;
	}
}
