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
if (! $assign = $DB->get_record("assign", array("id" => $iid))) {
    print_error("Course module is incorrect");
}

// Get the course module entry.
if (! $cm = get_coursemodule_from_instance("assign", $assign->id, $course->id)) {
    print_error("Course Module ID was incorrect");
}

$ctx = context_module::instance($cm->id);

require_once("$CFG->dirroot/repository/lib.php");
require_once("$CFG->dirroot/grade/grading/lib.php");
require_once($CFG->dirroot.'/mod/assign/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot.'/blocks/fn_marking/assign_edit_grade_form.php');

$assign = new assign($ctx, $cm, $course);

$o = '';
$mform = null;
$notices = array();

if (($show == 'unmarked') || ($show == 'saved')) {

    if ($action == 'submitgrade') {
        if (optional_param('saveandshownext', null, PARAM_RAW)) {
            // Save and show next.
            $action = 'grade';
            if (block_fn_marking_process_save_grade($mform, $assign, $ctx, $course, $pageparams, $gradingonly)) {
                $action = 'nextgrade';
            }
        } else if (optional_param('nosaveandprevious', null, PARAM_RAW)) {
            $action = 'previousgrade';
        } else if (optional_param('nosaveandnext', null, PARAM_RAW)) {
            // Show next button.
            $action = 'nextgrade';
        } else if (optional_param('savegrade', null, PARAM_RAW)) {
            // Save changes button.

            $action = 'grade';
            if (block_fn_marking_process_save_grade($mform, $assign, $ctx, $course, $pageparams, $gradingonly)) {
                $action = 'grade';
            }
        } else {
            // Cancel button.
            $action = 'grading';
        }
    } else {
        $action = 'grade';
    }

    if (!$gradingonly) {
        $returnparams = array('rownum' => optional_param('rownum', 0, PARAM_INT));
        $assign->register_return_link($action, $returnparams);

        if (isset($_POST['onlinetext'])) {
            unset($_POST['onlinetext']);
        }
        // Now show the right view page.
        if ($action == 'previousgrade') {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, -1, $assign, $ctx, $cm, $course, $pageparams);
        } else if ($action == 'nextgrade') {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, 1, $assign, $ctx, $cm, $course, $pageparams);
        } else if ($action == 'grade') {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, 0, $assign, $ctx, $cm, $course, $pageparams);
        }
    }

} else if ($show == 'marked') {
    if ($expand) {
        if (($action == 'submitgrade')  && (optional_param('savegrade', null, PARAM_RAW))) {
            block_fn_marking_process_save_grade($mform, $assign, $ctx, $course, $pageparams, $gradingonly);
        }
        if (optional_param('nosaveandprevious', null, PARAM_RAW)) {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, -1, $assign, $ctx, $cm, $course, $pageparams);
        } else if (optional_param('nosaveandnext', null, PARAM_RAW)) {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, 1, $assign, $ctx, $cm, $course, $pageparams);
        } else {
            $mform = null;
            $_POST = null;
            $o .= block_fn_marking_view_single_grade_page($mform, 0, $assign, $ctx, $cm, $course, $pageparams);
        }

    } else {
        $o .= block_fn_marking_view_submissions($mform, $offset = 0, $showattemptnumber = null,
            $assign, $ctx, $cm, $course, $pageparams);
    }

    // echo $o;
} else if ($show == 'unsubmitted') {
    $o .= block_fn_marking_view_submissions($mform, $offset = 0, $showattemptnumber = null,
        $assign, $ctx, $cm, $course, $pageparams);
    // echo $o;
}