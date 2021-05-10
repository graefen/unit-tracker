<?php
session_start();
include "../ut_lib/includes.php";
//$db = new DBConnect();
$db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$csrf = new CSRF();

// get csrf token ID & value
$token_id = $csrf->get_token_id();
$token_value = $csrf->get_token();

// pull members from the DB
$error = NULL;
$members = NULL;

// number formatter
$fmt = new NumberFormatter( 'en_US', NumberFormatter::CURRENCY );

if (!$error = $db->connect_error) {
    // meta data
    $result = $db->query('SELECT * from ut_meta WHERE director_number="'. DIRECTOR_NUMBER .'"');
    $meta = $result->fetch_assoc();
    if ($meta && !$meta['last_updated_date']) $meta['last_updated_date'] = '--';
    
    // dynamic dates
    $year = date('Y');
    $month = intval(date('m'),10);
    $day = '-01';
    $m = array($year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).$day);
    for ($d=0; $d<8; ++$d) {
        if (!--$month) {
            $month = 12;
            $year = intval($year,10)-1;
        }
        $m[] = $year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).$day;
    }
    // seminar year start date
    $sem_start = intval(date('Y'),10);
    if (intval(date('m'),10) < 7) --$sem_start;
    $sem_start .= '-'. SEMINAR_YEAR_START;
    // pull all unit members with statuses
    //
    // SWITCH FROM $200 -> $225 happened March 1
    // REMOVE THE "-25" from I2 in July
    // REMOVE THE "-25" from I3 in August
    // REMOVE THE "-25" from T1 in September
    // REMOVE THE "-25" from T2 in October
    // REMOVE THE "-25" from T3 in November
    // REMOVE THE "-25" from T4 in December
    //
    $query = 'SELECT member_id, member_id as mbr_id, firstname, lastname, recruit_date, recruiter_id, had_inv_talk, starting_inv, been_pinned, had_debut,
IFNULL(SUM(amount),"0.00") as total_orders,
(SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[0].'" AND member_id = mbr_id) as orders_this_month,
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[0].'" AND member_id = mbr_id)>='.ACTIVE_MIN.',"A1",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[1].'" AND date < "'.$m[0].'" AND member_id = mbr_id)>='.ACTIVE_MIN.',"A2",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[2].'" AND date < "'.$m[1].'" AND member_id = mbr_id)>='.ACTIVE_MIN.',"A3",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[3].'" AND date < "'.$m[2].'" AND member_id = mbr_id)>='.ACTIVE_MIN.',"I1",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[4].'" AND date < "'.$m[3].'" AND member_id = mbr_id)>='.(ACTIVE_MIN-25).',"I2",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[5].'" AND date < "'.$m[4].'" AND member_id = mbr_id)>='.(ACTIVE_MIN-25).',"I3",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[6].'" AND date < "'.$m[5].'" AND member_id = mbr_id)>='.(ACTIVE_MIN-25).',"T1",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[7].'" AND date < "'.$m[6].'" AND member_id = mbr_id)>='.(ACTIVE_MIN-25).',"T2",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$m[8].'" AND date < "'.$m[7].'" AND member_id = mbr_id)>='.(ACTIVE_MIN-25).',"T3",
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE member_id = mbr_id)>='.(ACTIVE_MIN-25).',"T4",
IF(recruit_date >= "'.$m[0].'","-N1",
IF(recruit_date >= "'.$m[1].'","-N2",
IF(recruit_date >= "'.$m[2].'","-N3",
IF(recruit_date >= "'.$m[3].'","I1",
IF(recruit_date >= "'.$m[4].'","I2",
IF(recruit_date >= "'.$m[5].'","I3",
IF(recruit_date >= "'.$m[6].'","T1",
IF(recruit_date >= "'.$m[7].'","T2",
IF(recruit_date >= "'.$m[8].'","T3","T4"))))))))))))))))))) as status,
(SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$sem_start.'" AND member_id = mbr_id) as sem_qualified_orders,
IF((SELECT SUM(amount) FROM '.ORDERS_TABLE.' WHERE date >= "'.$sem_start.'" AND member_id = mbr_id)>='.QUALIFIED_MIN.',1,0) as sem_qualified,
IF(((recruit_date >= "'.$sem_start.'") AND (recruiter_id IS NULL)),1,0) as sem_qualified_able
              FROM '. MEMBERS_TABLE.' LEFT JOIN '.ORDERS_TABLE.'
              USING(member_id)
              GROUP BY status, firstname, lastname
              ORDER BY status, lastname, firstname';
    $result = $db->query($query);
    $members = array();
    while ($row = $result->fetch_assoc()) {
        array_push($members, $row);
    }
    if (!count($members)) $error = $db->error;

    // pull director orders
    $dir_query = 'SELECT SUM(amount) as dir_orders
                  FROM '.ORDERS_TABLE.'
                  WHERE member_id IS NULL AND date >= "'.$m[0].'"';
    $result = $db->query($dir_query);
    $dir_orders = array();
    while ($row = $result->fetch_assoc()) {
        array_push($dir_orders, $row);
    }
    if (!count($dir_orders)) $error = $db->error;
}
?><!DOCTYPE html>
<html>
<head>
    <title><?php print DIRECTOR_NAME ?>&rsquo;s UnitTracker</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="format-detection" content="telephone=no" />
    
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black" />
    
    <link rel="shortcut icon" type="image/ico" href="/img/logo-16.ico" />
    <link rel="shortcut icon" sizes="128x128" href="/img/logo-128.png" />
    <link rel="shortcut icon" sizes="196x196" href="/img/logo-196.png" />
    <link rel="apple-touch-icon" href="/img/logo-60.png" />
    <link rel="apple-touch-icon" sizes="76x76" href="/img/logo-76.png" />
    <link rel="apple-touch-icon" sizes="120x120" href="/img/logo-120.png" />
    <link rel="apple-touch-icon" sizes="152x152" href="/img/logo-152.png" />
    <link rel="apple-touch-startup-image" href="/img/logo-320x460.png" />
    <!--[if IE]><link rel="shortcut icon" href="/img/logo-16.ico" /><![endif]-->
    <meta name="msapplication-TileImage" content="/img/logo-144.png"/>
    <meta name="msapplication-TileColor" content="#ff7788"/>
    
    <link href="/ut.css" rel="stylesheet" type="text/css" />
</head>
<body>

<!-- page -->
<div class="super-body">
    <h1 class="page-title"><?= DIRECTOR_NAME ?>&rsquo;s UnitTracker</h1>
    <h2 class="page-sub-title">Most Recent Order: <span class="last-mod-date"><?= $meta['last_updated_date'] ?></span></h2>
    <?php
// do counts
$sem_q_count = 0;
$act_count = 1; // start at 1 to include the director
$car_total = 0 + $dir_orders[0]['dir_orders'];
$unit_total = $car_total;
for ($s=0; $s<count($members); $s++) {
    $mbr = $members[$s];
    if ($mbr['sem_qualified'] && $mbr['sem_qualified_able']) $sem_q_count++;
    
    $status = preg_replace("/^-/", '', $mbr['status']);
    if ('T4' == $status) $status = 'T';
    preg_match("/^([A-Z])/", $status, $status_letter_matches);
    $status_letter = $status_letter_matches[0];
    if ('A' == $status_letter) $act_count++;
    
    $unit_total += $mbr['orders_this_month'];
    if (NULL == $mbr['recruiter_id']) $car_total += $mbr['orders_this_month'];
}
$date = date('M') .' '. date('Y');

$needed_for_car = max(0, 5000 - $car_total);
?>
    <div class="page-summary">
        <span class="status act"><span
            class="q"><?= $act_count ?></span><span
            class="label">Active</span></span><span
        class="status sem"><span
            class="q"><?= $sem_q_count ?></span><span
            class="label">SEM-Q</span></span><span
        class="status car"><span
            class="q">-<?= $fmt->formatCurrency($needed_for_car, "USD") ?></span><span
            class="label">Car Production, <?= $date ?></span></span><span
        class="status unit-prod"><span
            class="q"><?= $fmt->formatCurrency($unit_total, "USD") ?></span><span
            class="label">Unit Production, <?= $date ?></span></span>
    </div>
    
    <div class="page-toolbar">
        <ul class="page-tools">
            <li class="tool"><button type="button" class="add-member">+ Unit Member</button>
                <form class="add-member-dialog dialog">
                    <h3 class="dialog-title">Add A New Unit Member</h3>
                    <p class="inline-group"><label for="add-member-firstname">First Name</label>
                        <input type="text" id="add-member-firstname" /></p>
                    <p class="inline-group"><label for="add-member-lastname">Last Name</label>
                        <input type="text" id="add-member-lastname" /></p>
                    <p class="inline-group"><label for="add-member-recruiter">Recruiter</label>
                        <select id="add-member-recruiter">
                            <option value="null"><?php print DIRECTOR_NAME ?></option><?php
for ($s=0; $s<count($members); $s++) {
    $mbr = $members[$s]; ?>
            <option value="<?= $mbr['member_id']; ?>"><?= $mbr['firstname'] ?> <?= $mbr['lastname'] ?></li><?php
}
                            ?>
                        </select></p>
                    <p class="inline-group"><label for="add-member-recruit-date">Recruitment Date</label>
                        <input type="text" id="add-member-recruit-date" placeholder="YYYY-MM-DD" /></p>
                    <p class="buttons"><button type="submit" class="submit">Add</button> <button type="reset" class="reset">Cancel</button></p>
                </form>
            </li>
            <li class="tool"><button type="button" class="add-order">+ Director Order</button></li>
        </ul>
    </div>
    
    <div class="page"><?php
if ($error) {
    print $error;
} ?>
        <ul class="members-list<?php if (!count($members)) { ?> empty<?php } ?>">
            <li class="unit-member empty">You currently have entered no unit members.</li>
            <li class="unit-member template">
                <div class="summary">
                    <span class="member-status"><span
                        class="a">N1</span><span
                        class="gap">-$<?= ACTIVE_MIN ?></span></span>
                    <span class="member-name"></span>
                    <span class="member-sq-status"><span
                        class="q">SEM</span><span
                        class="gap">-$<?= QUALIFIED_MIN ?></span></span>
                    <span class="member-checks">
                        <label><input type="checkbox" name="had_inv_talk" /> Inventory Talk</label>
                        <label><input type="checkbox" name="starting_inv" /> Starting Inventory</label>
                        <label><input type="checkbox" name="been_pinned" /> Been Pinned</label>
                        <label><input type="checkbox" name="had_debut" /> Had Debut</label>
                    </span>
                    <span class="member-actions"><button type="button" class="add-order">+ Order</button></span>
                    <span class="member-expando"></span>
                </div>
            </li><?php
// output members
$js_members = array();
for ($s=0; $s<count($members); $s++) {
    $mbr = $members[$s];
    $member_name = $mbr['firstname'] .' '. $mbr['lastname'];
    
    $status = preg_replace("/^-/", '', $mbr['status']);
    if ('T4' == $status) $status = 'T';
    preg_match("/^([A-Z])/", $status, $status_letter_matches);
    $status_letter = $status_letter_matches[0];
    
    $needed_for_active_raw = max(0, ACTIVE_MIN - $mbr['orders_this_month']);
    $needed_for_active = $fmt->formatCurrency($needed_for_active_raw, "USD");
    $needed_for_sem_q = $fmt->formatCurrency(max(0, QUALIFIED_MIN - $mbr['sem_qualified_orders']), "USD");
    $total_orders = $mbr['total_orders']; // <- currently unused
    $js_members[$mbr['member_id']] = array(
        'member_id'   =>$mbr['member_id'],
        'firstname'   =>$mbr['firstname'],
        'lastname'    =>$mbr['lastname'],
        'recruiter_id'=>$mbr['recruiter_id'],
        'had_inv_talk'=>$mbr['had_inv_talk'],
        'starting_inv'=>$mbr['starting_inv'],
        'been_pinned' =>$mbr['been_pinned'],
        'had_debut'   =>$mbr['had_debut']
    ); ?>
            <li id="member-<?= $mbr['member_id']; ?>" class="unit-member status-<?= $status_letter ?>" data-member_id="<?= $mbr['member_id']; ?>" data-member_name="<?= $member_name ?>">
                <div class="summary">
                    <span class="member-status"><span
                        class="a"><?= $status ?></span><span
                        class="gap"><?php
    if ($needed_for_active_raw) { ?><?= (($needed_for_active ? '-$' : '').$needed_for_active) ?><?php
    }
    else { ?>--<?php
    } ?></span>
                    </span>
                    <span class="member-name"><?= $member_name ?></span>
                    <span class="member-sq-status<?= ($mbr['sem_qualified'] ? ' qualified' : '') ?>"><?php
    if ($mbr['sem_qualified_able']) { ?><span
                        class="q">SEM</span><span
                        class="gap"><?php
        if (!$mbr['sem_qualified']) { ?><?= (($needed_for_sem_q ? '-$' : '').$needed_for_sem_q) ?><?php
        }
        else { ?>--<?php
        } ?></span><?php
    } ?></span>
                </div>
                <div class="details">
                    <span class="member-checks">
                        <label><input type="checkbox" name="had_inv_talk" <?= ($mbr['had_inv_talk'] ? 'checked="true"' : '') ?> /> Inventory Talk</label>
                        <label><input type="checkbox" name="starting_inv" <?= ($mbr['starting_inv'] ? 'checked="true"' : '') ?> /> Starting Inventory</label>
                        <label><input type="checkbox" name="been_pinned" <?= ($mbr['been_pinned'] ? 'checked="true"' : '') ?> /> Been Pinned</label>
                        <label><input type="checkbox" name="had_debut" <?= ($mbr['had_debut'] ? 'checked="true"' : '') ?> /> Had Debut</label>
                    </span>
                    <span class="member-actions"><button type="button" class="add-order">+ Order</button></span>
                </div>
            </li><?php
} ?>
        </ul>
        <form class="add-order-dialog dialog">
            <h3 class="dialog-title">Add An Order for <span class="name"></span></h3>
            <p class="inline-group"><label for="add-order-amount">Amount</label>
                <input type="text" id="add-order-amount" placeholder="xxx.xx" /></p>
            <p class="inline-group"><label for="add-order-date">Date</label>
                <input type="text" id="add-order-date" placeholder="YYYY-MM-DD" /></p>
            <p class="buttons"><button type="submit" class="submit">Add</button> <button type="reset" class="reset">Cancel</button></p>
        </form>
    </div>
</div>
<script type="text/javascript" language="javascript" src="zepto-1.0-a3cab6c8c8.custom.js"></script>
<script type="text/javascript" language="javascript" src="ut.js"></script>
<script type="text/javascript" language="javascript">
$(function ($) {
    ut.initUI({ 'token_id':'<?= $token_id ?>', 'token_value':'<?= $token_value ?>', 'director_name':'<?= DIRECTOR_NAME ?>', 'members':<?= json_encode($js_members) ?> });
});
</script>
</body>
</html>
