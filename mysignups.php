<?php

// Displays sessions for which the current user is a "teacher" (can see attendees' list)
// as well as the ones where the user is signed up (i.e. a "student")

require_once '../../config.php';
require_once('lib.php');

require_login();

$timenow = time();
$timelater = $timenow + 3 * WEEKSECS;

$startyear  = optional_param('startyear',  strftime('%Y', $timenow), PARAM_INT);
$startmonth = optional_param('startmonth', strftime('%m', $timenow), PARAM_INT);
$startday   = optional_param('startday',   strftime('%d', $timenow), PARAM_INT);
$endyear    = optional_param('endyear',    strftime('%Y', $timelater), PARAM_INT);
$endmonth   = optional_param('endmonth',   strftime('%m', $timelater), PARAM_INT);
$endday     = optional_param('endday',     strftime('%d', $timelater), PARAM_INT);

$sortby = optional_param('sortby', 'timestart', PARAM_ALPHA); // column to sort by
$action = optional_param('action',          '', PARAM_ALPHA); // one of: '', export
$format = optional_param('format',       'ods', PARAM_ALPHA); // one of: ods, xls

$startdate = make_timestamp($startyear, $startmonth, $startday);
$enddate = make_timestamp($endyear, $endmonth, $endday);

$urlparams = "startyear=$startyear&amp;startmonth=$startmonth&amp;startday=$startday&amp;";
$urlparams .= "endyear=$endyear&amp;endmonth=$endmonth&amp;endday=$endday";
$sortbylink = "mysignups.php?{$urlparams}&amp;sortby=";

// Process actions if any
if ('export' == $action) {
    export_spreadsheet($dates, $format, true);
    exit;
}

// Get all Face-to-face signups from the DB
$signups = get_records_sql("SELECT d.id, c.id as courseid, c.fullname AS coursename, f.name,
                                   f.id as facetofaceid, s.id as sessionid, s.location,
                                   d.timestart, d.timefinish, su.timecancelled as status
                              FROM {$CFG->prefix}facetoface_sessions_dates d
                              JOIN {$CFG->prefix}facetoface_sessions s ON s.id = d.sessionid
                              JOIN {$CFG->prefix}facetoface f ON f.id = s.facetoface
                              JOIN {$CFG->prefix}facetoface_submissions su ON su.sessionid = s.id
                              JOIN {$CFG->prefix}course c ON f.course = c.id
                             WHERE d.timestart >= $startdate AND d.timefinish <= $enddate AND
                                   su.userid = $USER->id
                          ORDER BY $sortby");

// Get all previous to the current time Face-to-face signups from the DB
$pastsignups = get_records_sql("SELECT d.id, c.id as courseid, c.fullname AS coursename, f.name,
                                   f.id as facetofaceid, s.id as sessionid, s.location,
                                   d.timestart, d.timefinish
                              FROM {$CFG->prefix}facetoface_sessions_dates d
                              JOIN {$CFG->prefix}facetoface_sessions s ON s.id = d.sessionid
                              JOIN {$CFG->prefix}facetoface f ON f.id = s.facetoface
                              JOIN {$CFG->prefix}facetoface_submissions su ON su.sessionid = s.id
                              JOIN {$CFG->prefix}course c ON f.course = c.id
                             WHERE d.timefinish <= $timenow AND
                                   su.userid = $USER->id AND su.timecancelled = 0
                          ORDER BY $sortby");
$nbsignups = 0;
if ($signups and count($signups) > 0) {
    $nbsignups = count($signups);
}

// include past bookings
$nbpastsignups = 0;
if ($pastsignups and count($pastsignups) > 0) {
    $nbpastsignups = count($pastsignups);
}

// format the session and dates to only show one booking where they span multiple dates
// i.e. multiple days startdate = firstday, finishdate = last day
$groupeddates = group_session_dates($signups);
$pastgroupeddates = group_session_dates($pastsignups);

$pagetitle = format_string(get_string('listsessiondates', 'block_facetoface'));
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);
print_header_simple($pagetitle, '', $navigation);

// show tabs
$currenttab = 'attending';
include_once('tabs.php');

// Date range form
print '<h2>'.get_string('daterange', 'block_facetoface').'</h2>';
print '<form method="get" action=""><p>';
print_date_selector('startday', 'startmonth', 'startyear', $startdate);
print ' to ';
print_date_selector('endday', 'endmonth', 'endyear', $enddate);
print ' <input type="submit" value="'.get_string('apply', 'block_facetoface').'" /></p></form>';

// Show sign-ups
print '<h2>'.get_string('futurebookings', 'block_facetoface').'</h2>';
if ($nbsignups > 0) {
    print_dates($groupeddates, false, false, true);
}
else{
    print '<p>'.get_string('signedupinzero', 'block_facetoface').'</p>';
}

// Show past bookings
print '<h2>'.get_string('pastbookings', 'block_facetoface').'</h2>';
if ($nbpastsignups > 0) {
    print_dates($pastgroupeddates, false, true);
}
else{
    print '<p>'.get_string('signedupinzero', 'block_facetoface').'</p>';
}
print_footer();

?>
