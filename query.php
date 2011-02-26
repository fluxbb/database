<?php

/**
* Copyright (C) 2011 FluxBB (http://fluxbb.org)
* License: LGPL - GNU Lesser General Public License (http://www.gnu.org/licenses/lgpl.html)
*/

abstract class DatabaseQuery
{
	public $sql = null;
	public $statement = null;

	public $table = null;
}

class SelectQuery extends DatabaseQuery
{
	public $fields = array();
	public $group = array();
	public $order = array();
	public $limit = 0;
	public $offset = 0;
}

class InsertQuery extends DatabaseQuery
{
	public $values = array();
}

class UpdateQuery extends DatabaseQuery
{
	public $values = array();
	public $order = array();
	public $limit = 0;
	public $offset = 0;
}

class DeleteQuery extends DatabaseQuery
{
	public $order = array();
	public $limit = 0;
	public $offset = 0;
}
