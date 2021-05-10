<?php
session_start();
header('content-type: application/json; charset=utf-8');
include "../../ut_lib/includes.php";
$csrf = new CSRF();

// check csrf token
if (!$csrf->verify('post')) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you don\'t have permission to edit a member.' ));
    die;
}

// verify had_inv_talk / starting_inv / been_pinned / had_debut
$db = new DBConnect();
$db_conn = $db->connect();
if (!$db_conn) {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}
$_POST = sanitize($_POST, $db_conn);
if (!isset($_POST['had_inv_talk']) && !isset($_POST['starting_inv']) && !isset($_POST['been_pinned']) && !isset($_POST['had_debut'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include any information to save.' ));
    die;
}
if (!preg_match('/^\d+$/', $_POST['member_id'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid member ID.' ));
    die;
}

// add the member
$fields = array();
if (isset($_POST['had_inv_talk'])) $fields[] = 'had_inv_talk='.$_POST['had_inv_talk'];
if (isset($_POST['starting_inv'])) $fields[] = 'starting_inv='.$_POST['starting_inv'];
if (isset($_POST['been_pinned']))  $fields[] = 'been_pinned='.$_POST['been_pinned'];
if (isset($_POST['had_debut']))    $fields[] = 'had_debut='.$_POST['had_debut'];
$query = 'UPDATE '.MEMBERS_TABLE.'
          SET '.implode(',', $fields).'
          WHERE member_id='.$_POST['member_id'];
$result = $db->query($query);

if (!$result) {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}

print json_encode(array( 'success'=>1 ));
?>