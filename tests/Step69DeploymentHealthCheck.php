<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$checks=[];

$checks['php_version']=version_compare(PHP_VERSION,'8.1.0','>=');
$checks['bootstrap']=is_file($root.'/config/bootstrap.php');
$checks['services']=is_file($root.'/config/timetable_services.php');
$checks['pdo_extension']=extension_loaded('pdo');
$checks['pdo_mysql_extension']=extension_loaded('pdo_mysql');

$required=[
 'ajax/update_entry.php','ajax/delete_entry.php','ajax/mark_paid.php',
 'ajax/toggle_lock.php','ajax/bulk_action.php','api/timetable.php',
 'timetable/add.php','timetable/edit.php'
];
$checks['required_endpoints']=true;
foreach($required as $f) {
    if(!is_file($root.'/'.$f)) {$checks['required_endpoints']=false; break;}
}

foreach($checks as $name=>$ok) {
    echo sprintf("%-28s %s\n",$name,$ok?'PASS':'FAIL');
}

if(!$checks['php_version'] || !$checks['bootstrap'] ||
   !$checks['services'] || !$checks['pdo_extension'] ||
   !$checks['required_endpoints']) {
    exit(2);
}
exit(0);
