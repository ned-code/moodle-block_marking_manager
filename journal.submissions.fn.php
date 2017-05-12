<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    block_fn_marking
 * @copyright  Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Get the assignment.
if (! $journal = $DB->get_record("journal", array("id" => $iid))) {
    print_error("Course module is incorrect");
}

// Get the course module entry.
if (! $cm = get_coursemodule_from_instance("journal", $journal->id, $course->id)) {
    print_error("Course Module ID was incorrect");
}

$ctx = context_module::instance($cm->id);

require_once($CFG->dirroot.'/lib/gradelib.php');
require_once($CFG->dirroot.'/mod/journal/lib.php');

$o = '';

if (($show == 'unmarked') || ($show == 'saved') || ($expand)) {
    if ($gradingonly) {
        $context = context_module::instance($cm->id);
        require_capability('mod/journal:manageentries', $context);
        // make some easy ways to access the entries.
        if ($eee = $DB->get_records("journal_entries", array("journal" => $journal->id))) {
            foreach ($eee as $ee) {
                $entrybyuser[$ee->userid] = $ee;
                $entrybyentry[$ee->id] = $ee;
            }
        } else {
            $entrybyuser = array();
            $entrybyentry = array();
        }

        // Process incoming data if there is any
        if ($data = data_submitted()) {
            confirm_sesskey();

            $feedback = array();
            $data = (array)$data;

            // Peel out all the data from variable names.
            $searcharray = array('ownum', 'ourseid');
            foreach ($data as $key => $val) {
                if (strpos($key, 'r') === 0 || strpos($key, 'c') === 0) {
                    $type = substr($key, 0, 1);
                    $num  = substr($key, 1);
                    if (!in_array($num, $searcharray) && is_number($num)) {
                        $feedback[$num][$type] = $val;
                    }
                }
            }
            $timenow = time();
            $count = 0;
            foreach ($feedback as $num => $vals) {
                $entry = $entrybyentry[$num];
                // Only update entries where feedback has actually changed.
                $ratingchanged = false;

                $studentrating = clean_param($vals['r'], PARAM_INT);
                $studentcomment = clean_text($vals['c'], FORMAT_PLAIN);

                if ($studentrating != $entry->rating && !($studentrating == '' && $entry->rating == "0")) {
                    $ratingchanged = true;
                }

                if ($ratingchanged || $studentcomment != $entry->entrycomment) {
                    $newentry = new StdClass();
                    $newentry->rating       = $studentrating;
                    $newentry->entrycomment = $studentcomment;
                    $newentry->teacher      = $USER->id;
                    $newentry->timemarked   = $timenow;
                    $newentry->mailed       = 0;           // Make sure mail goes out (again, even)
                    $newentry->id           = $num;
                    if (!$DB->update_record("journal_entries", $newentry)) {
                        //echo $OUTPUT->notification("Failed to update the journal feedback for user $entry->userid");
                    } else {
                        $count++;
                    }
                    $entrybyuser[$entry->userid]->rating     = $studentrating;
                    $entrybyuser[$entry->userid]->entrycomment    = $studentcomment;
                    $entrybyuser[$entry->userid]->teacher    = $USER->id;
                    $entrybyuser[$entry->userid]->timemarked = $timenow;

                    $journal = $DB->get_record("journal", array("id" => $entrybyuser[$entry->userid]->journal));
                    $journal->cmidnumber = $cm->idnumber;

                    journal_update_grades($journal, $entry->userid);
                }
            }

            // Trigger module feedback updated event.
            $event = \mod_journal\event\feedback_updated::create(array(
                'objectid' => $journal->id,
                'context' => $context
            ));
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('journal', $journal);
            $event->trigger();

            //echo $OUTPUT->notification(get_string("feedbackupdated", "journal", "$count"), "notifysuccess");
        }
    } else {
        $studentlist = implode(',', array_keys($students));
        if ($journal->grade == 0) {
            $sql = "SELECT j.userid 
                      FROM {journal_entries} j 
                     WHERE j.journal = ? 
                       AND j.entrycomment IS NULL 
                       AND j.userid IN ($studentlist)";
        } else {
            if ($expand) {
                if ($journal->grade == 0) {
                    $sql = "SELECT j.userid 
                              FROM {journal_entries} j 
                             WHERE j.journal = ? 
                               AND j.entrycomment IS NOT NULL 
                               AND j.userid IN ($studentlist)";
                } else {
                    $sql = "SELECT j.userid 
                              FROM {journal_entries} j 
                             WHERE j.journal = ? 
                               AND j.rating IS NOT NULL 
                               AND j.userid IN ($studentlist)";
                }
            } else {
                $sql = "SELECT j.userid 
                          FROM {journal_entries} j 
                         WHERE j.journal = ? 
                           AND j.rating IS NULL 
                           AND j.userid IN ($studentlist)";
            }
        }

        if ($attempts = $DB->get_records_sql($sql, array($journal->id))) {
            $attempts = array_keys($attempts);
        }
        if (optional_param('nosaveandprevious', null, PARAM_RAW)) {
            $offset = -1;
        } else if (optional_param('nosaveandnext', null, PARAM_RAW)) {
            $offset = 1;
        } else {
            $offset = 0;
        }
        $last = false;
        if ($userid) {
            $rownum = array_search($userid, array_keys($students));
        } else {
            $rownum = $pageparams['rownum'] + $offset;
            $userid = $attempts[$rownum];
        }
        if ($rownum == count($attempts) - 1) {
            $last = true;
        }

        $context = context_module::instance($cm->id);
        require_capability('mod/journal:manageentries', $context);
        // make some easy ways to access the entries.
        if ($eee = $DB->get_records("journal_entries", array("journal" => $journal->id))) {
            foreach ($eee as $ee) {
                $entrybyuser[$ee->userid] = $ee;
                $entrybyentry[$ee->id] = $ee;
            }
        } else {
            $entrybyuser = array();
            $entrybyentry = array();
        }

        // Group mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);

        // Print out the journal entries
        if ($currentgroup) {
            $groups = $currentgroup;
        } else {
            $groups = '';
        }

        //groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/journal/report.php?id=$cm->id");

        $grades = make_grades_menu($journal->grade);
        if (!$teachers = get_users_by_capability($context, 'mod/journal:manageentries')) {
            print_error('noentriesmanagers', 'journal');
        }

        $o .= '<form action="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php" method="post" class="gradeform mform" autocomplete="off">';

        if ($user = $DB->get_record('user', array('id' => $userid))) {

            // Overriden grade check.
            $sql = "SELECT gg.*
                  FROM {grade_items} gi 
            INNER JOIN {grade_grades} gg 
                    ON gi.id = gg.itemid 
                 WHERE gi.itemtype = 'mod' 
                   AND gi.itemmodule = 'journal' 
                   AND gi.iteminstance = ? 
                   AND gg.userid = ?";
            $overriden = false;
            if ($gradegrade = $DB->get_record_sql($sql, array($journal->id, $userid))) {
                if ($gradegrade->overridden > 0) {
                    $overriden = $gradegrade;
                }
            }

            // Start the table.
            $gradeviewclass = 'default-grade-view-nobtn';
            if (count($attempts) > 1) {
                $gradeviewclass = 'default-grade-view';
                $o .= '<div style="text-align:center; font-size:11px; margin-bottom:3px;">';
                $strprevious = get_string('previous');
                $strnext = get_string('next');

                if ($rownum > 0) {
                    $o .= ' <input type="submit" id="id_nosaveandprevious" value="' .
                        $strprevious . '" name="nosaveandprevious"> ';
                } else {
                    $o .= ' <input type="submit" id="id_nosaveandprevious" value="' .
                        $strprevious . '" name="nosaveandprevious" disabled="disabled"> ';
                }
                $o .= get_string('gradingstudentprogress', 'block_fn_marking',
                    array('index' => $rownum + 1, 'count' => count($attempts)));

                if (!$last) {
                    $o .= ' <input type="submit" id="id_nosaveandnext" value="' .
                        $strnext . '" name="nosaveandnext"> ';
                } else {
                    $o .= ' <input type="submit" id="id_nosaveandnext" value="' .
                        $strnext . '" name="nosaveandnext" disabled="disabled"> ';
                }
                $o .= '</div>';
            }


            $o .= '<div class="'.$gradeviewclass.'">';
            $o .= '<a href="'.$CFG->wwwroot.'/mod/journal/report.php?id='.$cm->id.'"><img src="'.
                $OUTPUT->pix_url('popup', 'scorm').'"> '.
                get_string('moodledefaultview', 'block_fn_marking').'</a>';
            $o .= '</div>';

            if ($overriden) {
                if ($overriden->finalgrade) {
                    $headlass = 'markingmanager-head-orange';
                } else {
                    $headlass = 'markingmanager-head-red';
                }
            } else {
                $headlass = 'markingmanager-head';
            }

            $o .= '<table border="0" cellpadding="0" cellspacing="0" border="1" width="100%" class="saprate-table">';
            $o .= '<tr>';
            $o .= '<td width="40" valign="top" align="center" class="'.$headlass.' marking_rightBRD">';
            $o .= $OUTPUT->user_picture($user);
            $o .= '</td>';
            $o .= '<td width="100%" valign="top" align="left" class="'.$headlass.'">';
            $o .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="name-date">';
            $o .= '<tr>';
            $o .= '<td valign="middle" width="65%" class="leftSide">';
            $o .= '<a target="_blank" class="marking_header_link" href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$courseid.'">' . fullname($user, true) . '</a>';
            $o .= '<br / ><span class="marking_header_link">Journal: </span><a target="_blank"';
            $o .= 'class="marking_header_link" title="Journal" href="'.$CFG->wwwroot.'/mod/journal/view.php?id='.$cm->id.'">';
            $o .= $journal->name.'</a>';
            $o .= '</td>';
            $o .= '<td width="35%" align="right" class="rightSide">';
            if ($overriden) {
                $locked = '<img class="ned-locked-icon" width="16" height="16" alt="Locked" src="'.$OUTPUT->pix_url('t/locked', '').'">';
                $o .= get_string('gradeoverridedetected', 'block_fn_marking').' '.$locked;
            } else {
                $o .= "<input type=\"submit\" value=\"" . get_string('save', 'block_fn_marking') . "\" />";
            }
            $o .= '</td>';
            $o .= '</tr>';
            $o .= '</table>';
            $o .= '</td>';
            $o .= '</tr>';
            $o .= '<tr>';
            if ($overriden) {
                $removeoverride = html_writer::link('#', get_string('removeoverride', 'block_fn_marking'),
                    array(
                        'id' => 'ned-override-remover',
                        'userid' => $userid,
                        'mod' => 'journal',
                        'instance' => $journal->id,
                        'action' => 'remove',
                        'sesskey' => sesskey(),
                    )
                );

                $gradebookurl = new moodle_url('/grade/report/singleview/index.php',
                    array(
                        'id' => $journal->course,
                        'item' => 'grade',
                        'group' => '',
                        'itemid' => $gradegrade->itemid
                    )
                );
                $opengradereport = html_writer::link($gradebookurl, get_string('opengradereport', 'block_fn_marking'), array('id' => 'open-grade-report-link'));
                $checkagain = html_writer::link('#', get_string('checkagain', 'block_fn_marking'), array('id' => 'open-grade-report-link-check'));
                $help = html_writer::link($ME, get_string('help', 'block_fn_marking'));
                $o .= '<tr>';
                $o .= '<td class="overriden-grade-menu" colspan="2">';
                $o .= $removeoverride.'&nbsp;&nbsp;&nbsp;';
                $o .= $opengradereport.'&nbsp;&nbsp;&nbsp;';
                $o .= $help;
                $o .= $opengradereport.'&nbsp;&nbsp;&nbsp;';
                $o .= '<div class="right-align">'.$checkagain.'</div>';
                $o .= '</td>';
                $o .= '</tr>';
            }
            $o .= '<td class="yellowcell" colspan="2">';





            $entry = $entrybyuser[$user->id];

            $o .= "\n<table class=\"journaluserentry\">";

            if ($entry) {
                $o .= "\n<tr>";
                $o .= "<td width=\"50%\" align=\"left\">";
                if (!$entry->teacher) {
                    $entry->teacher = $USER->id;
                }
                if (empty($teachers[$entry->teacher])) {
                    $teachers[$entry->teacher] = $DB->get_record('user', array('id' => $entry->teacher));
                }

                $o .= '<b>'.get_string('teachersfeedback', 'block_fn_marking').'</b><br /><span class="teacher_feedback_info">'.
                    $teachers[$entry->teacher]->firstname.' '.$teachers[$entry->teacher]->lastname.' <br /> '.userdate(time()).'</span>';

                $o .= "</td>";
                $o .= "<td width=\"50%\" align=\"right\">" . get_string("grade") . ":";

                $attrs = array();
                $hiddengradestr = '';
                $gradebookgradestr = '';
                $feedbackdisabledstr = '';
                $feedbacktext = $entry->entrycomment;

                // If the grade was modified from the gradebook disable edition also skip if journal is not graded.
                $gradinginfo = grade_get_grades($course->id, 'mod', 'journal', $entry->journal, array($user->id));
                if (!empty($gradinginfo->items[0]->grades[$entry->userid]->str_long_grade)) {
                    if ($gradingdisabled = $gradinginfo->items[0]->grades[$user->id]->locked || $gradinginfo->items[0]->grades[$user->id]->overridden) {
                        $attrs['disabled'] = 'disabled';
                        $hiddengradestr = '<input type="hidden" name="r' . $entry->id . '" value="' . $entry->rating . '"/>';
                        $gradebooklink = '<a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">';
                        $gradebooklink .= $gradinginfo->items[0]->grades[$user->id]->str_long_grade . '</a>';
                        $gradebookgradestr = '<br/>' . get_string("gradeingradebook", "journal") . ':&nbsp;' . $gradebooklink;

                        $feedbackdisabledstr = 'disabled="disabled"';
                        $feedbacktext = $gradinginfo->items[0]->grades[$user->id]->str_feedback;
                    }
                }

                // Grade selector
                $attrs['id'] = 'r' . $entry->id;
                $o .= html_writer::label(fullname($user) . " " . get_string('grade'), 'r' . $entry->id, true, array('class' => 'accesshide'));
                $o .= html_writer::select($grades, 'r' . $entry->id, $entry->rating, get_string("nograde") . '...', $attrs);
                $o .= $hiddengradestr;
                // Rewrote next three lines to show entry needs to be regraded due to resubmission.
                //if (!empty($entry->timemarked) && $entry->modified > $entry->timemarked) {
                //    $o .= " <span class=\"lastedit\">" . get_string("needsregrade", "journal") . "</span>";
                //} else if ($entry->timemarked) {
                //    $o .= " <span class=\"lastedit\">" . userdate($entry->timemarked) . "</span>";
                //}
                $o .= $gradebookgradestr;

                $o .= "</td></tr>";
                $o .= "<tr>";
                $o .= "<td colspan='2'>";
                // Feedback text
                $o .= html_writer::label(fullname($user) . " " . get_string('feedback'), 'c' . $entry->id, true, array('class' => 'accesshide'));
                $o .= "<p><textarea id=\"c$entry->id\" name=\"c$entry->id\" rows=\"6\" cols=\"60\" $feedbackdisabledstr>";
                $o .= $feedbacktext;
                $o .= "</textarea></p>";

                if ($feedbackdisabledstr != '') {
                    //$o .= '<input type="hidden" name="c' . $entry->id . '" value="' . $feedbacktext . '"/>';
                }
                $o .= "</td>";
                $o .= "</tr>";
            }
            $o .= "</table>\n";

            $o .= "<p class=\"feedbacksave\">";
            $o .= "<input type=\"hidden\" name=\"rownum\" value=\"$rownum\" />";
            $o .= "<input type=\"hidden\" name=\"action\" value=\"submitgrade\" />";
            $o .= "<input type=\"hidden\" name=\"courseid\" value=\"$course->id\" />";
            $o .= "<input type=\"hidden\" name=\"show\" value=\"$show\" />";
            $o .= "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
            $o .= "<input type=\"hidden\" name=\"mid\" value=\"$cm->id\" />";
            $o .= "<input type=\"hidden\" name=\"expand\" value=\"$expand\" />";
            $o .= "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" />";
            $o .= "</p>";
            $o .= "</form>";


            $o .= '</td>';
            $o .= '</tr>';
            $o .= '<tr>';
            $o .= '<td class="bluecell" colspan="2">';
            if ($entry) {
                $o .= " <div class=\"lastedit\"><strong>" . get_string('studentssubmission', 'block_fn_marking') . ": </strong>" . userdate($entry->modified) . "</div>";
            }

            if ($entry) {
                $o .= '<div class="entry-text">';
                $o .= journal_format_entry_text($entry, $course);
                $o .= '</div>';
            }


            $o .= "</td>";
            $o .= '</tr>';
            $o .= '</table>';
        }
    }
} else if ($show == 'marked') {
    $o .= block_fn_marking_view_journal_submissions($journal, $students, $cm, $course, $pageparams, $show);
} else if ($show == 'unsubmitted') {
    $o .= block_fn_marking_view_journal_submissions($journal, $students, $cm, $course, $pageparams, $show);
}
