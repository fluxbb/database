<?php
include 'src/Database/Adapter.php';
include 'src/Database/Query.php';

$db = Flux_Database_Adapter::factory('MySQL', array('dbname' => 'flux_db', 'username' => 'root', 'password' => '', 'prefix' => 'forum_', 'debug' => true));


// Create a select query and manipulate it a little
$query = $db->select(array('subject' => 't.subject', 'closed' => 't.closed', 'num_replies' => 't.num_replies', 'sticky' => 't.sticky', 'first_post_id' => 't.first_post_id', 'forum_id' => 'f.id AS forum_id', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators', 'post_replies' => 'fp.post_replies', 'is_subscribed' => '0 AS is_subscribed'), 'topics AS t');

$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');
$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.id = :tid AND t.moved_to IS NULL';

$params = array(':group_id' => 1, ':tid' => 1);

$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

$query->leftJoin('s', 'topic_subscriptions AS s', 't.id = s.topic_id AND s.user_id = :user_id');
$params[':user_id'] = 1;

$result = $query->run($params);



echo '<pre>';
var_dump($query);
echo '</pre>';
echo '<br><br><br>';
echo '<pre>';
var_dump($result);
echo '</pre>';
echo '<br><br><br>';


