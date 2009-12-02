<?php

// Displays booking history for the current user
// ToDo: Add capability testing to limit non-admin user from seeing all booking histories

require_once '../../config.php';
require_once('lib.php');

require_login();

$sid        = required_param('session', PARAM_INT);
$userid     = optional_param('userid', PARAM_INT);

    // get all the required records
    if (!isset($userid)) {
        $userid = $USER->id;
    }

    if (! $user = get_record('user','id',$userid)) {
        error('Invalid user id');
    }
    if (! $session = facetoface_get_session($sid)) {
        error('Invalid session id');
    }

    if(! $facetoface = get_record('facetoface','id', $session->facetoface)) {
        error('Invalid facetoface id');
    }

    if (! $course = get_record('course','id',$facetoface->course)) {
        error('Invalid course id');
    }

$pagetitle = format_string(get_string('bookinghistory', 'block_facetoface'));
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);
print_header_simple($pagetitle, '', $navigation);

// Get signups from the DB
$bookings = get_records_sql("SELECT su.timecreated, su.timecancelled as status, su.grade, su.timegraded, su.cancelreason,
                                   c.id as courseid, c.fullname AS coursename,
                                   f.name, f.id as facetofaceid, s.id as sessionid,
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

    // print the session information
    facetoface_print_session($session, false);

    // print the booking history
    if ($bookings and count($bookings) > 0) {

        $table = new object();
        $table->summary = get_string('sessionsdetailstablesummary', 'facetoface');
        $table->class = 'f2fsession';
        $table->width = '50%';
        $table->align = array('right', 'left');

        foreach ($bookings as $booking) {
            if (isset($booking->status) and $booking->status == 0) {
                $table->data[] = array(get_string('enrolled', 'block_facetoface'), userdate($booking->timecreated, get_string('strftimedatetime')));
            } else {
                // if the booking status is cancelled print out the original enrollment date (timecreated) too
                $table->data[] = array(get_string('enrolled', 'block_facetoface'),userdate($booking->timecreated, get_string('strftimedatetime')));
                $table->data[] = array(get_string('cancelled', 'block_facetoface'),userdate($booking->status, get_string('strftimedatetime')), $booking->cancelreason);
            }
        }

        // if the grade is 100 mark the user as 'attended'
        if ($grade = facetoface_get_grade($user->id, $course->id, $facetoface->id) and $grade->grade == 100) {
            // just use the first session time of the multi-session facetoface
            if ($sessiontimes and count($sessiontimes) > 0) {
                $firstsession = current(array_values($sessiontimes));
            }
            $table->data[] = array(get_string('attended', 'block_facetoface'),userdate($firstsession->timestart, get_string('strftimedate'))) ;
        }

    } else {
        $table = new object();
        $table->summary = get_string('sessionsdetailstablesummary', 'facetoface');
        $table->class = 'f2fsession';
        $table->width = '50%';
        $table->align = array('center');

        if ($user->id != $USER->id) {
           $table->data[] = array(get_string('nobookinghistoryfor','block_facetoface',fullname($user)));
        } else {
           $table->data[] = array(get_string('nobookinghistory','block_facetoface'));
        }
    }

    print_table($table);
}
print_footer();
?>
