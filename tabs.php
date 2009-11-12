<?php


if (!isset($currenttab)) {
    $currenttab = 'attending';
}

$tabs = array();
$row = array();
$activated = array();
$inactive = array();

$urlparams  = "startyear=$startyear&amp;startmonth=$startmonth&amp;startday=$startday&amp;";
$urlparams .= "endyear=$endyear&amp;endmonth=$endmonth&amp;endday=$endday";
$url = "?{$urlparams}&amp;sortby=$sortby";

$row[] = new tabobject('attending',$CFG->wwwroot.'/blocks/facetoface/mysignups.php'.$url,get_string('sign-ups','facetoface'));
$row[] = new tabobject('attendees',$CFG->wwwroot.'/blocks/facetoface/mysessions.php'.$url,get_string('attendees','facetoface'));

$tabs[] = $row;
$activated[] = $currenttab;

/// Print out the tabs and continue!
print_tabs($tabs, $currenttab, $inactive, $activated);
?>
