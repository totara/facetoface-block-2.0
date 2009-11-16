<?php

require_once('../../mod/facetoface/lib.php');

/**
 * Print the session dates in a nicely formatted table.
 */
function print_dates($dates, $includebookings, $includegrades=false) {
    global $sortbylink, $CFG, $USER;

    $courselink = $CFG->wwwroot.'/course/view.php?id=';
    $facetofacelink = $CFG->wwwroot.'/mod/facetoface/view.php?f=';
    $attendeelink = $CFG->wwwroot.'/mod/facetoface/attendees.php?s=';

    print '<table border="1" cellpadding="0" summary="'.get_string('sessiondatestable', 'block_facetoface').'"><tr>';
    print '<th><a href="'.$sortbylink.'coursename">'.get_string('course').'</a></th>';
    print '<th><a href="'.$sortbylink.'name">'.get_string('name').'</a></th>';
    print '<th><a href="'.$sortbylink.'location">'.get_string('location').'</a></th>';
    print '<th><a href="'.$sortbylink.'timestart">'.get_string('startdate', 'block_facetoface').'</a></th>';
    print '<th><a href="'.$sortbylink.'timefinish">'.get_string('finishdate', 'block_facetoface').'</a></th>';
    print '<th>'.get_string('time').'</th>';
    if ($includebookings) {
        print '<th><a href="'.$sortbylink.'nbbookings">'.get_string('nbbookings', 'block_facetoface').'</a></th>';
    }
    
    // include the grades in the display
    if ($includegrades) {
        print '<th><a href="'.$sortbylink.'grade">'.get_string('grade').'</a></th>';
    }
    print '</tr>';
    $even = false; // used to colour rows
    foreach ($dates as $date) {
        
        // include the grades in the display
        if ($includegrades) {
            $grade = facetoface_get_grade($USER->id, $date->courseid, $date->facetofaceid);
        }

        if ($even) {
            print '<tr style="background-color: #CCCCCC">';
        }
        else {
            print '<tr>';
        }
        $even = !$even;
        print '<td><a href="'.$courselink.$date->courseid.'">'.format_string($date->coursename).'</a></td>';
        print '<td><a href="'.$facetofacelink.$date->facetofaceid.'">'.format_string($date->name).'</a></td>';
        print '<td>'.format_string($date->location).'</td>';
        print '<td>'.userdate($date->timestart, '%d %B %Y').'</td>';
        print '<td>'.userdate($date->timefinish, '%d %B %Y').'</td>';
        print '<td>'.userdate($date->timestart, '%I:%M %p').' - '.userdate($date->timefinish, '%I:%M %p').'</td>';
        if ($includebookings) {
            print '<td><a href="'.$attendeelink.$date->sessionid.'">'.(isset($date->nbbookings)? format_string($date->nbbookings) : 0).'</a></td>';
        }
        
        // include the grades in the display
        if ($includegrades) {
            print '<td>'.format_string($grade->grade).'</td>';
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
