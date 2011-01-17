<?php

header('Content-type: text/plain');

define('PHPDB_ROOT', dirname(__FILE__).'/src/');
require PHPDB_ROOT.'db.php';

// Make sure the output is formatted nicely
header('Content-type: text/plain');

// Open a new database - sqlite in memory will do - using the sqlite dialect obviously
$db = new Database('sqlite::memory:', array(), 'sqlite');

$db->start_transaction();

// Select some rubbish as a test...
$query = new SelectQuery();
$query->fields = array('1 AS one', '(1+1) AS two', 'current_time AS time', ':1 AS little_bobby_tables');

// Execute the query, passing some rubbish as a parameter
$params = array(':1' => 'Robert\'); DROP TABLE Students;--');
$result = $db->query($query, $params);

// Display our results
print_r($result);

// Free the result
unset ($result);

$db->commit_transaction();

// Close the database
unset ($db);
