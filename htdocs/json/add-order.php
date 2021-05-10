<?php
session_start();
header('content-type: application/json; charset=utf-8');
include "../../ut_lib/includes.php";
$csrf = new CSRF();

// check csrf token
if (!$csrf->verify('post')) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you don\'t have permission to add an order.' ));
    die;
}

// verify amount, date, & member_id
$db = new DBConnect();
$db_conn = $db->connect();
if (!$db_conn) {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}
$_POST = sanitize($_POST, $db_conn);
if (!isset($_POST['amount']) || !isset($_POST['date']) || !isset($_POST['member_id'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include an amount, a date, and a member ID.' ));
    die;
}
if (!preg_match('/^[\d]{0,7}\.?[\d]{0,2}$/', $_POST['amount'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid amount.' ));
    die;
}
if (!preg_match('/^[\d]{4}\-[\d]{2}\-[\d]{2}$/', $_POST['date'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid date.' ));
    die;
}
if (!preg_match('/^\d+$/', $_POST['member_id']) && ('null' != $_POST['member_id'])) {
    print json_encode(array( 'success'=>0, 'error'=>'Sorry, but you didn\'t include a valid member ID.' ));
    die;
}

// add the member
$query = 'INSERT INTO '.ORDERS_TABLE.'
          SET date="'.$_POST['date'].'",
              amount="'.$_POST['amount'].'"'
              .('null' != $_POST['member_id'] ? ', member_id='.intval($_POST['member_id'], 10) : '');
$result = $db->query($query);

if ($result) {
    $date_query = 'SELECT last_updated_date
                   FROM ut_meta
                   WHERE director_number="'. DIRECTOR_NUMBER .'"';
    $date_row = $db->select_query($date_query);
    $last_date = $date_row[0]['last_updated_date'];
    $last_epoch = strtotime($last_date);
    $order_epoch = strtotime($_POST['date']);
    if ($order_epoch > $last_epoch) {
        $query = 'UPDATE ut_meta
                  SET last_updated_date="'. $_POST['date'] .'"
                  WHERE director_number="'. DIRECTOR_NUMBER .'"';
        $db->query($query);
    }
}
else {
    print json_encode(array( 'success'=>0, 'error'=>$db->error ));
    die;
}

print json_encode(array( 'success'=>1, 'order_id'=>mysql_insert_id() ));
?>