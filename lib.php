<?php

require_once('../../mod/facetoface/lib.php');

/**
 * Print the session dates in a nicely formatted table.
 */
function print_dates($dates, $includebookings, $includegrades=false, $includestatus=false, $includecourseid=false, $includetrainers=false) {
    global $sortbylink, $CFG, $USER;

    $courselink = $CFG->wwwroot.'/course/view.php?id=';
    $facetofacelink = $CFG->wwwroot.'/mod/facetoface/view.php?f=';
    $attendeelink = $CFG->wwwroot.'/mod/facetoface/attendees.php?s=';

    print '<table border="1" cellpadding="0" summary="'.get_string('sessiondatestable', 'block_facetoface').'"><tr>';

    // include the course id in the display
    if ($includecourseid) {
        print '<th><a href="'.$sortbylink.'courseid">'.get_string('idnumbercourse').'</a></th>';
    }

    print '<th><a href="'.$sortbylink.'coursename">'.get_string('course').'</a></th>';

    // include the course id in the display
    if ($includetrainers) {
        print '<th><a href="'.$sortbylink.'trainers">'.get_string('trainer','block_facetoface').'</a></th>';
    }

    print '<th><a href="'.$sortbylink.'name">'.get_string('name').'</a></th>';
    print '<th><a href="'.$sortbylink.'location">'.get_string('location').'</a></th>';
    print '<th><a href="'.$sortbylink.'timestart">'.get_string('date','block_facetoface').'</a></th>';
    print '<th>'.get_string('time', 'block_facetoface').'</th>';
    if ($includebookings) {
        print '<th><a href="'.$sortbylink.'nbbookings">'.get_string('nbbookings', 'block_facetoface').'</a></th>';
    }
    
    // include the grades in the display
    if ($includegrades) {
        print '<th><a href="'.$sortbylink.'grade">'.get_string('grade').'</a></th>';
    }

    // include the status (enrolled,cancelled) in the display
    if ($includestatus) {
        print '<th><a href="'.$sortbylink.'status">'.get_string('status').'</a></th>';
    }

    print '</tr>';
    $even = false; // used to colour rows
    foreach ($dates as $date) {
        // get the session dates
        $sessiondates = facetoface_get_session_dates($date->sessionid);

        // include the grades in the display
        if ($includegrades) {
            $grade = facetoface_get_grade($date->userid, $date->courseid, $date->facetofaceid);
        }

        // include the trainers in the display
        if ($includetrainers) {
            $cm = get_record('course_modules', 'course', $date->courseid, 'instance', $date->facetofaceid);
            $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
            $trainers = get_users_by_capability($context, 'mod/facetoface:viewattendees', 'u.id, u.firstname, u.lastname', '', '', '', '', '', false);
        }

        if ($even) {
            print '<tr style="background-color: #CCCCCC" valign="top">';
        }
        else {
            print '<tr valign="top">';
        }
        $even = !$even;
        if ($includecourseid) {
            print '<td>'.$date->cidnumber.'</td>';
        }
        print '<td><a href="'.$courselink.$date->courseid.'">'.format_string($date->coursename).'</a></td>';

        // include the trainer(s) in the display
        if ($includetrainers) {
            print '<td>';
            if ($trainers and count($trainers) > 0) {
                foreach ($trainers as $trainer) {
                    print '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$trainer->id.'&amp;course='.$date->courseid.'">'.fullname($trainer).'</a><br />';
                }
            }
            print '</td>';
        }

        print '<td><a href="'.$facetofacelink.$date->facetofaceid.'">'.format_string($date->name).'</a></td>';
        print '<td>'.format_string($date->location).'</td>';
        print '<td>';
        foreach ($sessiondates as $sessiondate) {
            print userdate($sessiondate->timestart, '%d %B %Y').'<br />';
        }
        print '</td>';
        print '<td>';
        foreach ($sessiondates as $sessiondate) {
            print userdate($sessiondate->timestart, '%I:%M %p').' - '.userdate($sessiondate->timefinish, '%I:%M %p').'<br />';
        }
        print '</td>';
        if ($includebookings) {
            print '<td><a href="'.$attendeelink.$date->sessionid.'">'.(isset($date->nbbookings)? format_string($date->nbbookings) : 0).'</a></td>';
        }
        
        // include the grades in the display
        if ($includegrades) {
            if ((int)$grade->grade > 0) {
                print '<td><a href="'.$CFG->wwwroot.'/blocks/facetoface/bookinghistory.php?session='.$date->sessionid.'&amp;userid='.$date->userid.'">'.format_string($grade->grade).'</a></td>';
            } else {
                print '<td><a href="'.$CFG->wwwroot.'/blocks/facetoface/bookinghistory.php?session='.$date->sessionid.'&amp;userid='.$date->userid.'">'.get_string('didntattend','block_facetoface').'</a></td>';
            }
        }

        if ($includestatus) {
            if ($date->status == 0) {
                print '<td><a href="'.$CFG->wwwroot.'/blocks/facetoface/bookinghistory.php?session='.$date->sessionid.'&amp;userid='.$date->userid.'">'.get_string('enrolled','block_facetoface').'</a></td>';
            } else {
                print '<td><a href="'.$CFG->wwwroot.'/blocks/facetoface/bookinghistory.php?session='.$date->sessionid.'&amp;userid='.$date->userid.'">'.get_string('cancelled','block_facetoface').'</a></td>';
            }
        }
        print '</tr>';
    }
    print '</table>';
}

/**
 * Group the Session dates together instead of having separate sessions
 * when it spans multiple days
 * */
function group_session_dates($sessions) {

    $retarray = array();

    foreach ($sessions as $session) {
        if (!array_key_exists($session->sessionid,$retarray)) {
            // clone the session object so we don't override the existing object
            $newsession = clone($session);
            $newsession->timestart = $newsession->timestart;
            $newsession->timefinish = $newsession->timefinish;
            $retarray[$newsession->sessionid] = $newsession;
        } else {
            if ($session->timestart < $retarray[$session->sessionid]->timestart) {
                $retarray[$session->sessionid]->timestart = $session->timestart;
            }

            if ($session->timefinish > $retarray[$session->sessionid]->timefinish) {
                $retarray[$session->sessionid]->timefinish = $session->timefinish;
            }
        }

        // ensure that we have the correct status (enrolled, cancelled) for the submission
        if (isset($session->status) and $session->status == 0) {
           $retarray[$session->sessionid]->status = $session->status;
        }
    }
    return $retarray;
}

/**
 * Separate out the dates from $sessions that finished before the current time
 * */
function past_session_dates($sessions) {

    $retarray = array();
    $timenow = time();

    if (!empty($sessions)) {
        foreach ($sessions as $session) {
            // check if the finish time is before the current time
            if ($session->timefinish < $timenow) {
                $retarray[$session->sessionid] = clone($session);
            }
        }
    }
    return $retarray;
}

/**
 * Separate out the dates from $sessions that finish after the current time
 * */
function future_session_dates($sessions) {

    $retarray = array();
    $timenow = time();

    if (!empty($sessions)) {
        foreach ($sessions as $session) {
            // check if the finish time is after the current time
            if ($session->timefinish >= $timenow) {
                $retarray[$session->sessionid] = clone($session);
            }
        }
    }
    return $retarray;
}

/**
 * Export the given session dates into an ODF/Excel spreadsheet
 */
function export_spreadsheet($dates, $format, $includebookings) {
    global $CFG;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate'));
    $downloadfilename = clean_filename('facetoface_'.userdate($timenow, $timeformat));

    if ('ods' === $format) {
        // OpenDocument format (ISO/IEC 26300)
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    }
    else {
        // Excel format
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
    }

    $workbook->send($downloadfilename);
    $worksheet =& $workbook->add_worksheet(get_string('sessionlist', 'block_facetoface'));

    // Heading (first row)
    $worksheet->write_string(0, 0, get_string('course'));
    $worksheet->write_string(0, 1, get_string('name'));
    $worksheet->write_string(0, 2, get_string('location'));
    $worksheet->write_string(0, 3, get_string('timestart', 'facetoface'));
    $worksheet->write_string(0, 4, get_string('timefinish', 'facetoface'));
    if ($includebookings) {
        $worksheet->write_string(0, 5, get_string('nbbookings', 'block_facetoface'));
    }

    if (!empty($dates)) {
        $i = 0;
        foreach ($dates as $date) {
            $i++;

            $worksheet->write_string($i, 0, $date->coursename);
            $worksheet->write_string($i, 1, $date->name);
            $worksheet->write_string($i, 2, $date->location);
            if ('ods' == $format) {
                $worksheet->write_date($i, 3, $date->timestart);
                $worksheet->write_date($i, 4, $date->timefinish);
            }
            else {
                $worksheet->write_string($i, 3, trim(userdate($date->timestart, get_string('strftimedatetime'))));
                $worksheet->write_string($i, 4, trim(userdate($date->timefinish, get_string('strftimedatetime'))));
            }
            if ($includebookings) {
                $worksheet->write_number($i, 5, isset($date->nbbookings) ? $date->nbbookings : 0);
            }
        }
    }

    $workbook->close();
}

/**
 *  Return a list of users who match the given search
 *  Fields searched are:
 *  - username,
 *  - firstname, lastname as fullname,
 *  - email
 */
function get_users_search($search) {
    global $CFG;

    //to allow case-insensitive search for postgesql
    if ($CFG->dbfamily == 'postgres') {
        $LIKE = 'ILIKE';
    } else {
        $LIKE = 'LIKE';
    }

    $usernamesearch = '';
    $emailsearch = '';
    $fullnamesearch = '';
    $firstnamesearch = '';
    $lastnamesearch = '';

    $searchvalues = split(' ',trim($search));
    $sort='firstname, lastname, username, email ASC';

    foreach ($searchvalues as $searchterm) {

        if ($usernamesearch) {
            $usernamesearch .= ' AND ';
        }
        if ($emailsearch) {
            $emailsearch .= ' AND ';
        }
        if (count($searchvalues) >= 2) {
            if ($fullnamesearch) {
                $fullnamesearch .= " $searchterm";
            } else {
                $fullnamesearch .= sql_fullname() ." $LIKE '%$searchterm";
            }
        }
        if (count($searchvalues) < 2) {
            $firstnamesearch .= ' firstname ' . $LIKE .' \'%'. $searchterm .'%\' ';
            $lastnamesearch .= ' lastname ' . $LIKE .' \'%'. $searchterm .'%\' ';
        }

        $usernamesearch .= ' username ' . $LIKE .' \'%'. $searchterm .'%\' ';
        $emailsearch .= ' email ' . $LIKE .' \'%'. $searchterm .'%\' ';
    }

    // if fullnamesearch append the end for the string
    if ($fullnamesearch) {
        $fullnamesearch .= '%\'';
    }

    $sql = "SELECT u.*
            FROM {$CFG->prefix}user u
            WHERE (( $usernamesearch ) OR ( $emailsearch )) ";

    if ($fullnamesearch) {
        $sql .= " OR ( $fullnamesearch ) ";
    }

    if ($firstnamesearch) {
        $sql .= " OR ( $firstnamesearch ) ";
    }

    if ($lastnamesearch) {
        $sql .= " OR ( $lastnamesearch ) ";
    }

    $sql .= " ORDER BY " . $sort;

    if ($records = get_records_sql($sql)) {
        return $records;
    } else {
        return array();
    }
}

/**
 * Add the location info
 */
function add_location_info(&$sessions)
{
    global $CFG;

    if (!$sessions) {
        return;
    }

    $alllocations = get_records_sql("SELECT d.sessionid, d.data
                                       FROM {$CFG->prefix}facetoface_session_data d
                                       JOIN {$CFG->prefix}facetoface_session_field f ON f.id = d.fieldid
                                      WHERE f.shortname = 'location'");

    foreach ($sessions as $session) {
        if (!empty($alllocations[$session->sessionid])) {
            $session->location = $alllocations[$session->sessionid]->data;
        }
        else {
            $session->location = '';
        }
    }
}
