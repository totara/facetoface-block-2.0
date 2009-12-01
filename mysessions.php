<?php

// Displays sessions for which the current user is a "teacher" (can see attendees' list)
// as well as the ones where the user is signed up (i.e. a "student")

require_once '../../config.php';
require_once 'lib.php';

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

$search = optional_param('search', PARAM_ALPHA); // search string

$startdate = make_timestamp($startyear, $startmonth, $startday);
$enddate = make_timestamp($endyear, $endmonth, $endday);

$urlparams = "startyear=$startyear&amp;startmonth=$startmonth&amp;startday=$startday&amp;";
$urlparams .= "endyear=$endyear&amp;endmonth=$endmonth&amp;endday=$endday";
$sortbylink = "mysessions.php?{$urlparams}&amp;sortby=";

$records = '';
$users = '';

if ($search) {
    $users = get_users_search($search);
} else {
    // Get all Face-to-face session dates from the DB
    $records = get_records_sql("SELECT d.id, cm.id AS cmid, c.id AS courseid, c.fullname AS coursename,
                                       c.idnumber as cidnumber, f.name, f.id as facetofaceid, s.id as sessionid,
                                       d.timestart, d.timefinish, su.nbbookings
                                  FROM {$CFG->prefix}facetoface_sessions_dates d
                                  JOIN {$CFG->prefix}facetoface_sessions s ON s.id = d.sessionid
                                  JOIN {$CFG->prefix}facetoface f ON f.id = s.facetoface
                       LEFT OUTER JOIN (SELECT sessionid, count(sessionid) AS nbbookings
                                          FROM {$CFG->prefix}facetoface_submissions su
                                         WHERE su.timecancelled = 0
                                      GROUP BY sessionid) su ON su.sessionid = d.sessionid
                                  JOIN {$CFG->prefix}course c ON f.course = c.id

                                  JOIN {$CFG->prefix}course_modules cm ON cm.course = f.course
                                       AND cm.instance = f.id
                                  JOIN {$CFG->prefix}modules m ON m.id = cm.module

                                 WHERE d.timestart >= $startdate AND d.timefinish <= $enddate
                                       AND m.name = 'facetoface'
                              ORDER BY $sortby");

    add_location_info($records);
}

// Only keep the sessions for which this user can see attendees
$dates = array();
if ($records) {
    $capability = 'mod/facetoface:viewattendees';

    // Check the system context first
    $contextsystem = get_context_instance(CONTEXT_SYSTEM);
    if (has_capability($capability, $contextsystem)) {
        $dates = $records;
    }
    else {
        foreach($records as $record) {
            // Check at course level first
            $contextcourse = get_context_instance(CONTEXT_COURSE, $record->courseid);
            if (has_capability($capability, $contextcourse)) {
                $dates[] = $record;
                continue;
            }

            // Check at module level if the first check failed
            $contextmodule = get_context_instance(CONTEXT_MODULE, $record->cmid);
            if (has_capability($capability, $contextmodule)) {
                $dates[] = $record;
            }
        }
    }
}
$nbdates = count($dates);

// Process actions if any
if ('export' == $action) {
    export_spreadsheet($dates, $format, true);
    exit;
}

// format the session and dates to only show one booking where they span multiple dates
// i.e. multiple days startdate = firstday, finishdate = last day
$groupeddates = group_session_dates($dates);

$pagetitle = format_string(get_string('listsessiondates', 'block_facetoface'));
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);
print_header_simple($pagetitle, '', $navigation);
print_box_start();

// show tabs
$currenttab = 'attendees';
include_once('tabs.php');

if (empty($users)) {
    // Date range form
    print '<h2>'.get_string('daterange', 'block_facetoface').'</h2>';
    print '<form method="get" action=""><p>';
    print_date_selector('startday', 'startmonth', 'startyear', $startdate);
    print ' to ';
    print_date_selector('endday', 'endmonth', 'endyear', $enddate);
    print ' <input type="submit" value="'.get_string('apply', 'block_facetoface').'" /></p></form>';
}

// Show all session dates
if ($nbdates > 0) {
    print '<h2>'.get_string('sessiondatesview', 'block_facetoface').'</h2>';
    print_dates($groupeddates, true, false, false, true, true);

    // Export form
    print '<h3>'.get_string('exportsessiondates', 'block_facetoface').'</h3>';
    print '<form method="post" action=""><p>';
    print '<input type="hidden" name="startyear" value="'.$startyear.'" />';
    print '<input type="hidden" name="startmonth" value="'.$startmonth.'" />';
    print '<input type="hidden" name="startday" value="'.$startday.'" />';
    print '<input type="hidden" name="endyear" value="'.$endyear.'" />';
    print '<input type="hidden" name="endmonth" value="'.$endmonth.'" />';
    print '<input type="hidden" name="endday" value="'.$endday.'" />';
    print '<input type="hidden" name="sortby" value="'.$sortby.'" />';
    print '<input type="hidden" name="action" value="export" />';

    print get_string('format', 'facetoface').':&nbsp;';
    print '<select name="format">';
    print '<option value="excel" selected="selected">'.get_string('excelformat', 'facetoface').'</option>';
    print '<option value="ods">'.get_string('odsformat', 'facetoface').'</option>';
    print '</select>';

    print ' <input type="submit" value="'.get_string('exporttofile', 'facetoface').'" /></p></form>';
} else if ($users) {
    if (count($users) > 0) {
        print '<h3>'.get_string('searchedusers','block_facetoface', count($users)).'</h3>';
        foreach ($users as $u) {
            print '<a href="'.$CFG->wwwroot.'/blocks/facetoface/mysignups.php?'.$urlparams.'&amp;userid='.$u->id.'">'.fullname($u).'</a><br />';
        }
    }
} else {
    print '<h2>'.get_string('sessiondatesview', 'block_facetoface').'</h2>';
    print '<p>'.get_string('sessiondatesviewattendeeszero', 'block_facetoface').'</p>';
}

echo '<div class="usersearch">';
    print '<h3>'.get_string('searchusers', 'block_facetoface').'</h3>';
    echo '<form class="learnersearch" id="searchquery" method="post" action="'.$CFG->wwwroot.'/blocks/facetoface/mysessions.php">';
    print '<input type="hidden" name="startyear" value="'.$startyear.'" />';
    print '<input type="hidden" name="startmonth" value="'.$startmonth.'" />';
    print '<input type="hidden" name="startday" value="'.$startday.'" />';
    print '<input type="hidden" name="endyear" value="'.$endyear.'" />';
    print '<input type="hidden" name="endmonth" value="'.$endmonth.'" />';
    print '<input type="hidden" name="endday" value="'.$endday.'" />';
    print '<input type="hidden" name="sortby" value="'.$sortby.'" />';
    echo '<input class="searchform" type="text" name="search" size="35" maxlength="255" value="'.$search.'"/>';
    echo '<input type="submit" value="Search" />';
echo '</form>';
echo '</div>';

print_box_end();
print_footer();
