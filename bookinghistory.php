<?php

// Displays booking history for the current user

require_once '../../config.php';
require_once('lib.php');

require_login();

$sid        = required_param('session', PARAM_INT);
$userid     = optional_param('userid', PARAM_INT);

if (!isset($userid)) {
    $userid = $USER->id;
}
$user = get_record('user','id',$userid);

// get all the required records
$session = get_record('facetoface_sessions','id',$sid);
$facetoface = get_record('facetoface','id', $session->facetoface);
$course = get_record('course','id',$facetoface->course);

$pagetitle = format_string(get_string('bookinghistory', 'block_facetoface'));
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);
print_header_simple($pagetitle, '', $navigation);

// Get signups from the DB
$bookings = get_records_sql("SELECT su.timecreated, su.timecancelled as status, su.grade, su.timegraded,
                                   c.id as courseid, c.fullname AS coursename,
                                   f.name, f.id as facetofaceid, s.id as sessionid, s.location,
                                   d.id, d.timestart, d.timefinish
                              FROM {$CFG->prefix}facetoface_sessions_dates d
                              JOIN {$CFG->prefix}facetoface_sessions s ON s.id = d.sessionid
                              JOIN {$CFG->prefix}facetoface f ON f.id = s.facetoface
                              JOIN {$CFG->prefix}facetoface_submissions su ON su.sessionid = s.id
                              JOIN {$CFG->prefix}course c ON f.course = c.id
                              WHERE su.userid = $user->id AND su.sessionid = $session->id AND f.id = $session->facetoface
                              ORDER BY su.timecreated ASC");

// Get session times from the DB
$sessiontimes = get_records_sql("SELECT id, timestart, timefinish FROM {$CFG->prefix}facetoface_sessions_dates WHERE sessionid = $session->id ORDER BY timestart ASC");

// as long as the session, facetoface activity and course exists 
if (isset($session) and isset($facetoface) and isset($course)) {

    if ($user->id != $USER->id) {
        $fullname = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.fullname($user).'</a>';
        $heading = get_string('bookinghistoryfor', 'block_facetoface', $fullname);
        print_heading($heading, 'center');
    } else {
        echo "<br />";
    }

    // print the booking information
    $table = '';
    $table .= '<table align="center" cellpadding="3" cellspacing="0" width="600" style="border-color:#DDDDDD; border-width:1px 1px 1px 1px; border-style:solid;">';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('user').'</th>';
    $table .= '<td><a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.fullname($user).'</a></td>';
    $table .= '</tr>';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('course').'</th>';
    $table .= '<td>'.format_string($course->fullname).'</td>';
    $table .= '</tr>';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('name').'</th>';
    $table .= '<td>'.format_string($facetoface->name).'</td>';
    $table .= '</tr>';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('location').'</th>';
    $table .= '<td>'.format_string($session->location).'</td>';
    $table .= '</tr>';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('date','block_facetoface').'</th>';
    $table .= '<td>';
    foreach ($sessiontimes as $session) {
        $table .= userdate($session->timestart, '%d %B %Y').'<br />';
    }
    $table .= '</td>';
    $table .= '</tr>';
    $table .= '<tr>';
    $table .= '<th class="header" align="left">&nbsp;'.get_string('time','block_facetoface').'</th>';
    $table .= '<td>';
    foreach ($sessiontimes as $session) {
        $table .= userdate($session->timestart, '%I:%M %p').' - '.userdate($session->timefinish, '%I:%M %p').'<br />';
    }
    $table .= '</td>';
    $table .= '</tr>';

    $table .= '</table>';

    echo $table;
    echo '<br />';

    // print the booking history
    if ($bookings and count($bookings) > 0) {

        $table = '<table align="center" cellpadding="3" cellspacing="0" width="600" style="border-color:#DDDDDD; border-width:1px 1px 1px 1px; border-style:solid;
                          summary="'.get_string('bookinghistorytable', 'block_facetoface').'">';
        foreach ($bookings as $booking) {
            $table .= '<tr>';
            if (isset($booking->status) and $booking->status == 0) {
                $table .= '<td>'.get_string('enrolled', 'block_facetoface').'</td>';
                $table .= '<td>'.userdate($booking->timecreated, get_string('strftimedatetime')).'</td>';
            } else {
                // if the booking status is cancelled print out the original enrollment date (timecreated) too
                $table .= '<tr>';
                    $table .= '<td>'.get_string('enrolled', 'block_facetoface').'</td>';
                    $table .= '<td>'.userdate($booking->timecreated, get_string('strftimedatetime')).'</td>';
                $table .= '</tr>';
                $table .= '<tr>';
                    $table .= '<td>'.get_string('cancelled', 'block_facetoface').'</td>';
                $table .= '<td>'.userdate($booking->status, get_string('strftimedatetime')).'</td>';
            }
            /* placeholder for the reason for cancellation field
             * $table .= '<td>'.get_string('reason', 'block_facetoface').'</td>';*/
            $table .= '</tr>';
        }

        // if the grade is 100 mark the user as 'attended'
        if ($grade = facetoface_get_grade($user->id, $course->id, $facetoface->id) and $grade->grade == 100) {
            $table .= '<tr>';
            $table .= '<td>'.get_string('attended', 'block_facetoface').'</td>';

            // just use the first session time of the multi-session facetoface
            if ($sessiontimes and count($sessiontimes) > 0) {
                $firstsession = current(array_values($sessiontimes));
                $table .= '<td>'.userdate($firstsession->timestart, get_string('strftimedate')).'</td>';
            }

            $table .= '<tr>';
        }
        $table .= '</table>';

        echo $table;
    } else {
        $table = '<table align="center" cellpadding="3" cellspacing="0" width="600" style="border-color:#DDDDDD; border-width:1px 1px 1px 1px; border-style:solid;
                          summary="'.get_string('bookinghistorytable', 'block_facetoface').'">';
        if ($user->id != $USER->id) {
           $table .= '<tr><td>'.get_string('nobookinghistoryfor','block_facetoface',fullname($user)).'</td></tr>';
        } else {
           $table .= '<tr><td>'.get_string('nobookinghistory','block_facetoface').'</td></tr>';
        }
        $table .= '</table>';

        echo $table;
    }
}
print_footer();
?>
