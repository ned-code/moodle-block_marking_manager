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
if (! $assignment = $DB->get_record("assignment", array("id" => $iid))) {
    print_error("Course module is incorrect");
}


// Get the course module entry.
if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
    print_error("Course Module ID was incorrect");
}

require_once("$CFG->dirroot/repository/lib.php");
require_once("$CFG->dirroot/grade/grading/lib.php");
require_once($CFG->dirroot.'/mod/assignment/lib.php');
require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
$assignmentclass = 'assignment_'.$assignment->assignmenttype;
$assignment = new $assignmentclass($cm->id, $assignment, $cm, $course);

$mformdata = new stdClass();
if ($assignment->assignment->assignmenttype == 'upload') {
    $mformdata->fileui_options = array('subdirs' => 1, 'maxbytes' => $assignment->assignment->maxbytes,
        'maxfiles' => $assignment->assignment->var1, 'accepted_types' => '*', 'return_types' => FILE_INTERNAL);
} else if ($assignment->assignment->assignmenttype == 'uploadsingle') {
    $mformdata->fileui_options = array('subdirs' => 0, 'maxbytes' => $CFG->userquota, 'maxfiles' => 1,
        'accepted_types' => '*', 'return_types' => FILE_INTERNAL);
}

$modcontext = context_module::instance($cm->id);
require_login($course->id, false, $cm);

require_capability('mod/assignment:grade', $modcontext);

// Get all teachers and students.
$teachers = get_users_by_capability($modcontext, 'mod/assignment:grade');

// Show any specific group of users requested.
$users = get_enrolled_users($modcontext, 'mod/assignment:submit', $currentgroup, 'u.*');

// Print if no student exist in.
if (!$users) {
    $OUTPUT->heading(get_string("nostudentsyet"));
    exit;
}

// Make some easy ways to reference submissions.
if ($submissions = $assignment->get_submissions()) {
    foreach ($submissions as $submission) {
        if (isset($submission->version)) {
            $subid = $submission->version;
        } else {
            $subid = 0;
        }
        $submissionbyuser[$submission->userid][$subid] = $submission;
    }
}

// Get all existing submissions and check for missing ones.
foreach ($users as $user) {
    if (!isset($submissionbyuser[$user->id])) {  // Need to create empty entry.
        $newsubmission->assignment = $assignment->assignment->id;
        $newsubmission->userid = $user->id;
        $newsubmission->timecreated = time();
        $newsubmission->data1 = "";
        $newsubmission->data2 = "";
        $newsubmission->grade = '-1';
        $newsubmission->submissioncomment = "";
        if (! $DB->insert_record("assignment_submissions", $newsubmission)) {
            print_error("Could not insert a new empty submission");
        }
    }
}


if (isset($newsubmission)) {   // Get them all out again to be sure.
    if (!($submissions = $assignment->get_submissions())) {
        $submissions = array();
    }
}


$allowedtograde = has_capability('mod/assignment:grade', $modcontext);
$assignment->grades = make_grades_menu($assignment->assignment->grade);

// Add a 'Wrong file' indicator to the grades list, if using numeric grading (not custom scale).
if ($assignment->assignment->grade > 0) {
    $assignment->grades[-1] = 'Wrong file';
}

// If data is being submitted, then process it.
$done = false;

if ($data = data_submitted()) {
    if ($assignment->assignment->assignmenttype) {
        $assignment->process_feedback();
        if (!is_null($data)) {
            if ($assignment->assignment->assignmenttype == 'upload' || $assignment->assignment->assignmenttype == 'uploadsingle') {
                $mformdata = file_postupdate_standard_filemanager($data, 'files', $mformdata->fileui_options,
                    $modcontext, 'mod_assignment', 'response', $data->submissionid);
            }
        }
        redirect($FULLME, 'Feedback sent to student');
    }

    $assignment->submission = $submissions[$data->sub_id];

    // Only update entries where feedback has actually changed.
    if (($data->grade <> $assignment->submission->grade) ||
        ($data->submissioncomment <> (
            !empty($assignment->submission->submissioncomment) ? addslashes($assignment->submission->submissioncomment) : '')) ||
        ($data->content <> addslashes($assignment->submission->data2->content->text)) ||
        $uplfile || ($canresubmit != $assignment->submission->canresubmit)) {
        $o .= "You came in right section";

        $assignment->submission->id         = $data->sub_id;
        $assignment->submission->grade      = $data->grade;
        $assignment->submission->submissioncomment    = $data->submissioncomment;
        $assignment->submission->data1->content->text = addslashes($assignment->submission->data1->content->text);

        if (!$assignment->submission->data2->content->timemodified ||
            ($assignment->submission->data2->content->text != $data->content)) {
            $assignment->submission->data2->content->timemodified = $assignment->timenow;
        }

        $assignment->submission->data2->content->text = $data->content;

        if ($uplfile) {
            $assignment->upload_marked_files($data);
        }

        $assignment->submission->teacher    = $USER->id;
        $assignment->submission->timemarked = $assignment->timenow;
        $assignment->submission->mailed     = 0;           // Make sure mail goes out (again, even).
        $assignment->submission->canresubmit = $canresubmit;

        if (empty($assignment->submission->timemodified)) {   // Eg for offline assignments.
            $assignment->submission->timemodified = $assignment->timenow;
        }

        if (! $assignment->update_submission()) {
            $o .= $OUTPUT->notification(get_string("failedupdatefeedback", "assignment", $assignment->submission->userid));
        }
        redirect($FULLME);
    }

    if (!$done) {
        if (!($submissions = $assignment->get_submissions())) {
            $submissions = array();
        }

        $manager = get_log_manager();
        if (method_exists($manager, 'legacy_add_to_log')) {
            $manager->legacy_add_to_log($course->id, "assignment", "update grades",
                "submissions.php?id={$assignment->assignment->id}", "$count users", $cm->id);
        }

        $o .= $OUTPUT->notification('Submissions feedback updated for '.fullname($users[$assignment->submission->userid]).'.');
    }
} else {
    $manager = get_log_manager();
    if (method_exists($manager, 'legacy_add_to_log')) {
        $manager->legacy_add_to_log($course->id, "assignment", "view submission",
            "submissions.php?id={$assignment->assignment->id}", "{$assignment->assignment->id}", $cm->id);
    }
}

if (!$done) {
    // Rebuild submissions array, ordered by unmarked status.
    $unmarked = array();
    $marked = array();
    $unsubmitted = array();
    $saved = array();
    foreach ($submissions as $submission) {
        // If the submission isn't from one of the users we care about then ignore.
        if (!isset($users[$submission->userid])) {
            continue;
        }

        if ($assignment->assignment->var4 == 1) {
            if (($submission->timemodified > 0) && ($submission->data2 == "")) {
                $saved[''.$submission->id.''] = $submission;
            } else if (($submission->timemodified > 0)
                && ($submission->timemarked < $submission->timemodified) && ($submission->data2 == "submitted")) {
                $unmarked[''.$submission->id.''] = $submission;
            } else if (($submission->timemarked >= $submission->timemodified) && ($submission->timemodified > 0)) {
                $marked[''.$submission->id.''] = $submission;
            } else {
                $unsubmitted[''.$submission->id.''] = $submission;
            }
        } else {
            if (($submission->timemodified > 0) && ($submission->timemarked < $submission->timemodified)) {
                $unmarked[''.$submission->id.''] = $submission;
            } else if ((($submission->timemarked >= $submission->timemodified)) && ($submission->timemodified > 0)) {
                $marked[''.$submission->id.''] = $submission;
            } else if ($submission->timemodified == '0') {
                $unsubmitted[''.$submission->id.''] = $submission;
            } else {
                $saved[''.$submission->id.''] = "";
            }
        }
    }

    // Group the saved by user and version.
    $sgrouped = array();
    foreach ($saved as $sub) {
        $sgrouped[$sub->userid][0] = $sub;
    }

    // Group the unmarked by user and version.
    $ugrouped = array();
    foreach ($unmarked as $sub) {
        $ugrouped[$sub->userid][0] = $sub;
    }


    // Group the marked by user and version if not already grouped.
    $mgrouped = array();
    foreach ($marked as $sub) {
        if (!isset($ugrouped[$sub->userid])) {
            if (isset($sub->version)) {
                $subidx = (int)$sub->version;
            } else {
                $subidx = 0;
            }
            $mgrouped[$sub->userid][$subidx] = $sub;
            foreach ($submissionbyuser[$sub->userid] as $version) {
                // Don't count the same version or unsubmitted.
                if ((empty($version->version) || ($version->version != $subidx)) && ($version->timemodified > 0)) {
                    $vidx = empty($version->version) ? 0 : (int)$version->version;
                    $mgrouped[$sub->userid][$vidx] = $version;
                }
            }
        }
    }
    $sgrouped = array_values($sgrouped);
    $ugrouped = array_values($ugrouped);
    $mgrouped = array_values($mgrouped);
    $grouped  = array_merge($ugrouped, $mgrouped);

    $unsubmitted = array_merge($unsubmitted, $saved);
    $baseurl = 'fn_gradebook.php?courseid='.$course->id.'&show='.$show.'&sort='.$sort.'&view='.$view.'&mid='.$mid.'&';

    // Show all unmarked assignment.
    if ($show == 'unmarked') {
        $totsubs = count($ugrouped);
        if ($totsubs > 0) {
            $o .= '<center><p><b>Following students submissions are still  <b>unmarked</b>:</b></p></center>';
            $pagingbar = new paging_bar($totsubs, $page, $perpage, $baseurl, 'page');
            $o .= $OUTPUT->render($pagingbar);
            for ($i = ($page * $perpage); ($i < ($page * $perpage) + $perpage) && ($i < $totsubs); $i++) {
                $usersubs = $ugrouped[$i];
                ksort($usersubs);
                $temp = end($usersubs);
                $assignment->submissions = $usersubs;
                $assignment->user = $users[$temp->userid];
                fn_print_submission($assignment);
            }
            $o .= '<br />';
        } else if ($totsubs == 0) {
            $o .= '<center><p>There are currently no <b>Unmarked</b> activities to display.</p></center>';
            $o .= "<br/>";
        }
    }

    // Show all saved assignment.
    if ($show == 'saved') {
        if ($assignment->assignment->var4) {
            $totsubs = count($sgrouped);
            if ($totsubs > 0) {
                $o .= '<center><p><strong>Following students have kept this assignment as saved:</strong></p></center>';
                $pagingbar = new paging_bar($totsubs, $page, $perpage, $baseurl, 'page');
                $o .= $OUTPUT->render($pagingbar);
                for ($i = ($page * $perpage); ($i < ($page * $perpage) + $perpage) && ($i < $totsubs); $i++) {
                    $usersubs = $sgrouped[$i];
                    ksort($usersubs);
                    $temp = end($usersubs);
                    $assignment->submissions = $usersubs;
                    $assignment->user = $users[$temp->userid];
                    fn_print_submission($assignment);
                }
                $o .= '<br />';
            } else {
                $o .= '<center><p>There are currently no <b>Saved</b> activities to display.</p></center>';
                $o .= "<br/>";
            }
        } else {
            $o .= '<center><p>There are currently no <b>Saved</b> activities to display.</p></center>';
            $o .= "<br/>";
        }
    }
}

// Show all marked assignment.
if ($show == 'marked') {
    usort($mgrouped, 'marksort'.$sort);
    $totsubs = count($mgrouped);
    if ($totsubs > 0) {
        $o .= '<center><p><strong>Following students submissions have been marked:</strong></p></center>';
        $pagingbar = new paging_bar($totsubs, $page, $perpage, $baseurl, 'page');
        $o .= $OUTPUT->render($pagingbar);
        for ($i = ($page * $perpage); ($i < ($page * $perpage) + $perpage) && ($i < $totsubs); $i++) {
            $usersubs = $mgrouped[$i];
            ksort($usersubs);
            $temp = end($usersubs);
            $assignment->submissions = $usersubs;
            $assignment->user = $users[$temp->userid];
            fn_print_submission($assignment, ($view != 'less'));
        }
        $o .= '<br />';
    } else if ($totsubs == 0) {
        $o .= '<center><p>There are currently no <strong>Marked</strong> activities to display.</p></center>';
        $o .= "<br/>";
    }
}
// Show all unsubmitted assignment.
$unsubmittedusers = array();
if ($show == 'unsubmitted') {
    if (count($unsubmitted) > 0) {
        $o .= '<p class="headTxt"><strong>The following students have not submitted this assignment:</strong></p>';
        count($unsubmitted);
        foreach ($unsubmitted as $submission) {
            // Check that this user hasn't submitted before.
            if (isset($grouped[$submission->userid])) {
                continue;
            } else if (isset($users[$submission->userid])) {
                $user = $users[$submission->userid];
                $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                $o .= "\n<tr>";
                $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
                $user = $DB->get_record('user', array('id' => $user->id));
                $o .= $OUTPUT->user_picture($user, array('courseid' => $assignment->course->id));
                $o .= "</td>";
                $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
                $o .= "</tr></table>\n";
            }
        }
    } else if (count($unsubmitted) == 0) {
        $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
    }
}


function get_user_submissions() {
    global $CFG;
    $select = 'SELECT u.id, u.id, u.firstname, u.lastname, u.picture, s.id AS submissionid, s.grade, s.submissioncomment, '.
        's.timemodified, s.timemarked, ((s.timemarked > 0) AND (s.timemarked >= s.timemodified)) AS status ';
    $sql = 'FROM {user} u '.
        'LEFT JOIN {assignment_submissions} s ON u.id = s.userid AND s.assignment = '.$this->assignment->id.' '.
        'WHERE '.$where.'u.id IN ('.implode(',', array_keys($users)).') ';
}

function fn_print_submission(&$assignment) {
    global $CFG;
    // Use a general assignment marking class....
    require_once($CFG->dirroot.'/blocks/fn_marking/assignment_marking.class.php');
    $marker = new assignment_marking($assignment);
    $marker->print_submission();

}

function marksortlowest($a, $b) {
    $alatest = reset($a);
    $blatest = reset($b);
    return (($alatest->grade < $blatest->grade) ? -1 : ($alatest->grade == $blatest->grade ? 0 : + 1));
}

function marksorthighest($a, $b) {
    $alatest = reset($a);
    $blatest = reset($b);

    return (($alatest->grade < $blatest->grade) ? + 1 : ($alatest->grade == $blatest->grade ? 0 : -1));
}

function marksortdate($a, $b) {
    $alatest = reset($a);
    $blatest = reset($b);

    return (($alatest->timemodified < $blatest->timemodified) ? -1 : ($alatest->timemodified == $blatest->timemodified ? 0 : + 1));
}

function marksortalpha($a, $b) {
    global $users;
    $alatest = reset($a);
    $blatest = reset($b);

    return (
    ($users[$alatest->userid] < $users[$blatest->userid]) ? -1 : ($users[$alatest->userid] == $users[$blatest->userid] ? 0 : + 1));
}
