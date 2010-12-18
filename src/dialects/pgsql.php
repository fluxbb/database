<?php

/**
 * SQL Dialect for PostgreSQL
 * 
 * Copyright (C) 2010 Jamie Furness (http://www.jamierf.co.uk)
 * License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
 */

class SQLDialect_PgSQL extends SQLDialect
{
	protected function limit_offset($limit, $offset)
	{
		$sql = '';

		if ($offset !== 0 && $limit === 0)
			$limit = 'ALL';

		if ($limit !== 0)
			$sql .= ' LIMIT '.$limit;

		if ($offset !== 0)
			$sql .= ' OFFSET '.$offset;

		return $sql;
	}
}
