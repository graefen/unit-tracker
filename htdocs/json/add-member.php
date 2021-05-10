<?php
session_start();
header('content-type: application/json; charset=utf-8');
include "../../ut_lib/includes.php";
$csrf = new CSRF();

// check csrf token
if (!$csrf->verify('post')) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you don\'t have permission to add a member.' ));
    die;
}

// verify firstname, lastname, recruiter_id, & recruit_date
$db = new DBConnect();
$db_conn = $db->connect();
if (!$db_conn) {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}
$_POST = sanitize($_POST, $db_conn);
if (!isset($_POST['firstname']) || !isset($_POST['lastname']) || !isset($_POST['recruit_date'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include both first and last names and a recruitment date.' ));
    die;
}
if (isset($_POST['recruiter_id']) && !preg_match('/^\d+$/', $_POST['recruiter_id'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid recruiter ID.' ));
    die;
}
if (!preg_match('/^[\d]{4}\-[\d]{2}\-[\d]{2}$/', $_POST['recruit_date'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid recruitment date.' ));
    die;
}

// add the member
$query = 'INSERT INTO '.MEMBERS_TABLE.'
          SET firstname="'.$_POST['firstname'].'",
              lastname="'.$_POST['lastname'].'",
              recruit_date="'.$_POST['recruit_date'].'"'
              .(isset($_POST['recruiter_id']) ? ', recruiter_id='.intval($_POST['recruiter_id'], 10) : '');
$result = $db->query($query);

if (!$result) {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}

print json_encode(array( 'success'=>1, 'member_id'=>mysql_insert_id() ));
?>