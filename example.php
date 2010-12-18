<?php

header('Content-type: text/plain');

define('PHPDB_ROOT', dirname(__FILE__).'/src/');
require PHPDB_ROOT.'db.php';

try
{
	$db = Database::load('sqlite3', array('filename' => 'sqlite3.db'));
}
catch (Exception $e)
{
	exit($e->getMessage());
}

$db->start_transaction();

$query = new SelectQuery();
$query->fields[] = '(1+1)';

$sql = $db->compile($query);
$result = $db->query($sql);

var_dump($result->fetch_single());

unset ($result); // Free the result

$db->commit_transaction();

$db->close();
