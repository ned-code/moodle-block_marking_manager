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

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

// Get the quizm.
if (! $quiz = $DB->get_record("quiz", array("id" => $iid))) {
    print_error("Course module is incorrect");
}

// Get the course module entry.
if (! $cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
    print_error("Course Module ID was incorrect");
}

$ctx = context_module::instance($cm->id);

$currentgroup = groups_get_activity_group($cm, true);
$students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');
$studentids = implode(',', array_keys($students));

// Paging options.
$qsort      = optional_param('qsort', 'firstname', PARAM_ALPHANUM);
$qdir       = optional_param('qdir', 'ASC', PARAM_ALPHA);

$o = '';
$qallparticipants = optional_param('qallparticipants', 0, PARAM_INT);

if (($show == 'marked') || ($show == 'unmarked')) {
    $filter = '';
    if ($show == 'marked') {
        if (!$qallparticipants) {
            $filter = ' AND quiza.sumgrades > 0';
        }
    }
    if ($show == 'unmarked') {
        if (!$qallparticipants) {
            $filter = ' AND quiza.sumgrades IS NULL';
        }
    }

    $quizslots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), 'slot ASC');

    // Use paging.
    $sqlquizattemptscount = "SELECT COUNT(1)
                               FROM {user} u
                          LEFT JOIN {quiz_attempts} quiza
                                 ON quiza.userid = u.id
                                AND quiza.quiz = ?
                              WHERE u.id IN ($studentids)
                                AND quiza.preview = 0
                                AND quiza.id IS NOT NULL $filter";

    $totalcount = $DB->count_records_sql($sqlquizattemptscount, array($quiz->id));

    $columns = array(
        'userpicture',
        'name',
        'sumgrades',
    );
    $headers = array(
        'userpicture' => '',
        'name' => get_string('student', 'block_fn_marking'),
        'sumgrades' => 'Grades<br>/' . round($quiz->grade),
    );
    // Build Headers.
    foreach ($quizslots as $quizslot) {
        $columns [] = 'qsgrade' . $quizslot->slot;
        $headers['qsgrade' . $quizslot->slot] = 'Q.' . $quizslot->slot . '<br>/' .
            round($quizslot->maxmark * ($quiz->grade / $quiz->sumgrades));
    }

    if ($qsort) {
        $sortsql = " ORDER BY $qsort $qdir";
    }

    $sqlquizattempts = "SELECT CONCAT(u.id, '#', COALESCE(quiza.attempt, 0)) AS uniqueid,
							(CASE WHEN (quiza.state = 'finished' AND NOT EXISTS (
							SELECT 1 FROM {quiz_attempts} qa2
							WHERE qa2.quiz = quiza.quiz AND
							qa2.userid = quiza.userid AND
							qa2.state = 'finished' AND (
							COALESCE(qa2.sumgrades, 0) > COALESCE(quiza.sumgrades, 0) OR
							(COALESCE(qa2.sumgrades, 0) = COALESCE(quiza.sumgrades, 0) AND qa2.attempt < quiza.attempt)
							))) THEN 1 ELSE 0 END) AS gradedattempt,

							quiza.uniqueid AS usageid,
							quiza.id AS attempt,
							u.id AS userid,
							u.idnumber, u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename,u.firstname,u.lastname,
							u.picture,
							u.imagealt,
							u.institution,
							u.department,
							u.email,
							quiza.state,
							quiza.sumgrades,
							quiza.timefinish,
							quiza.timestart,
							CASE WHEN quiza.timefinish = 0 THEN null
							WHEN quiza.timefinish > quiza.timestart THEN quiza.timefinish - quiza.timestart
							ELSE 0 END AS duration, COALESCE((
							SELECT MAX(qqr.regraded)
							FROM {quiz_overview_regrades} qqr
							WHERE qqr.questionusageid = quiza.uniqueid
							), -1) AS regraded
							FROM
							{user} u
							LEFT JOIN {quiz_attempts} quiza ON
							quiza.userid = u.id AND quiza.quiz = ? WHERE u.id IN ($studentids)
							AND quiza.preview = 0 AND quiza.id IS NOT NULL $filter $sortsql";


    foreach ($columns as $column) {

        if ($qsort != $column) {
            $columnicon = "";
            if ($column == "firstname") {
                $columndir = "DESC";
            } else {
                $columndir = "ASC";
            }
        } else {
            $columndir = $qdir == "ASC" ? "DESC" : "ASC";
            if ($column == "firstname") {
                $columnicon = ($qdir == "ASC") ? "sort_desc" : "sort_asc";
            } else {
                $columnicon = ($qdir == "ASC") ? "sort_asc" : "sort_desc";
            }
            $columnicon = "<img class='iconsort' src=\"" . $OUTPUT->pix_url('t/' . $columnicon) . "\" alt=\"\" />";

        }
        if (($column == 'userpicture') || ($column == 'edit')) {
            $$column = $headers[$column];
        } else {
            $qsortingparams = array(
                'view' => $view,
                'show' => $show,
                'mid' => $mid,
                'courseid' => $courseid,
                'perpage' => $perpage,
                'qsort' => $column,
                'qdir' => $columndir
            );

            $sortingurl = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $qsortingparams);
            $$column = "<a href=\"" . $sortingurl->out() . "\">" . $headers[$column] . "</a>$columnicon";
        }
    }

    $table = new html_table();
    $table->id = 'fn-quiz-grading';
    $table->head = $headers;

    $table->align = array('left', 'left', 'left');
    $table->wrap = array('', 'nowrap', '');


    $tablerows = $DB->get_records_sql($sqlquizattempts, array($quiz->id), $page * $perpage, $perpage);

    $counter = ($page * $perpage);

    foreach ($tablerows as $tablerow) {
        $row = new html_table_row();
        $cellrowcount = ++$counter;
        $cellarray = array();

        // USER PICTURE.
        $user = $DB->get_record('user', array('id' => $tablerow->userid));
        $cellarray[] = new html_table_cell($OUTPUT->user_picture($user, array('size' => 35, 'class' => 'welcome_userpicture')));

        // NAME.
        $cellname = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $tablerow->userid . '&amp;course=' .
            $quiz->course . '">' . $tablerow->firstname . ' ' . $tablerow->lastname . '</a><br>
                      <a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' .
            $tablerow->attempt . '" class="reviewlink">Review attempt</a></td>';
        $cellarray[] = new html_table_cell($cellname);



        // SUMGRADES.
        if ($tablerow->sumgrades) {
            $attemptgrade = round($tablerow->sumgrades * ($quiz->grade / $quiz->sumgrades), 2);
            $cellsumgardes = '<a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' .
                $tablerow->attempt . '" title="Review attempt">' . $attemptgrade . '</a>';
        } else {
            $cellsumgardes = '<a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' .
                $tablerow->attempt . '" title="Review attempt">Not yet graded</a>';
        }
        $cellarray[] = $cellsumgardes;

        foreach ($quizslots as $quizslot) {
            $table->wrap[] = 'nowrap';
            $table->align[] = 'center';
            $columns [] = 'qsgrade' . $quizslot->slot;

            $sqlusages = "SELECT qasd.id,
                                  quiza.sumgrades,
                                  qu.preferredbehaviour,
                                  qa.slot,
                                  qa.variant,
                                  qa.maxmark,
                                  qa.minfraction,
                                  qa.flagged,
                                  qas.sequencenumber,
                                  qas.state,
                                  qas.fraction,
                                  qasd.value
                             FROM {quiz_attempts} quiza
                             JOIN {question_usages} qu
                               ON qu.id = quiza.uniqueid
                             JOIN {question_attempts} qa
                               ON qa.questionusageid = qu.id
                             JOIN {question_attempt_steps} qas
                               ON qas.questionattemptid = qa.id
                        LEFT JOIN {question_attempt_step_data} qasd
                               ON qasd.attemptstepid = qas.id
                            WHERE qa.slot = ?
                              AND quiza.id = ?
                         ORDER BY qas.id DESC";

            $questionstep = $DB->get_records_sql($sqlusages, array($quizslot->slot, $tablerow->attempt));
            $questionstep = reset($questionstep);
            $quiz->sumgrades;
            $quiz->grade;
            $questionstepgrade = round(($questionstep->fraction * $questionstep->maxmark) * ($quiz->grade / $quiz->sumgrades), 2);

            if ($questionstep->state == 'gradedwrong') {
                $cellarray[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                    $tablerow->attempt . '&amp;slot=' .
                    $quizslot->slot . '" title="Review response">
                                <span class="que">
                                    <img src="' . $OUTPUT->pix_url('i/grade_incorrect', 'core') .
                                    '" title="Incorrect" alt="Incorrect" class="icon fn-icon">
                                    <span class="incorrect">' . $questionstepgrade . '</span>
                                </span>
                             </a>';
            } else if (($questionstep->state == 'gradedright') || ($questionstep->state == 'mangrright')) {
                $cellarray[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                    $tablerow->attempt . '&amp;slot=' .
                    $quizslot->slot . '" title="Review response">
                                <span class="que">
                                    <img src="' . $OUTPUT->pix_url('i/grade_correct', 'core') .
                                    '" title="Incorrect" alt="Incorrect" class="icon fn-icon">
                                    <span class="correct">' . $questionstepgrade . '</span>
                                </span>
                             </a>';
            } else if ($questionstep->state == 'needsgrading') {
                $cell = new html_table_cell('<a target="_blank" href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                    $tablerow->attempt . '&amp;slot=' . $quizslot->slot . '" title="Review response">
                                    <span class="que">
                                        <img src="' . $OUTPUT->pix_url('i/edit', 'core') .
                                        '" title="Edit" alt="Editt" class="icon fn-icon">
                                    </span></a>');
                $cell->attributes = array('class' => 'fn-highlighted');
                $cellarray[] = $cell;
            } else if (($questionstep->state == 'mangrpartial') || ($questionstep->state == 'gradedpartial')) {
                $cellarray[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' .
                    $tablerow->attempt . '&amp;slot=' . $quizslot->slot . '" title="Review response">
                                    <span class="que">
                                        <img src="' . $OUTPUT->pix_url('i/grade_partiallycorrect', 'core') .
                                        '" title="Partially correct" alt="Partially correct" class="icon fn-icon">
                                        <span class="partiallycorrect">' . $questionstepgrade . '</span>
                                    </span></a>';
            }
        }

        $row->cells = $cellarray;
        $table->data[] = $row;
    }

    $targetonparams = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => 1
    );
    $targetoffparams = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => 0
    );
    $targetonurl = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $targetonparams);
    $targetoffurl = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $targetoffparams);

    $o .= '<div class="quiz-top-menu"><div class="qdefault-view">';

    $o .= '<a href="' . $CFG->wwwroot . '/mod/quiz/report.php?id=' . $cm->id .
        '&mode=overview"><img src="' . $OUTPUT->pix_url('popup', 'scorm') . '"> '.
        get_string('moodledefaultview', 'block_fn_marking').'</a>';

    $o .= '&nbsp;&nbsp;&nbsp;<a href="' . $CFG->wwwroot . '/mod/quiz/report.php?id=' . $cm->id .
        '&mode=grading"><img src="' . $OUTPUT->pix_url('popup', 'scorm') . '"> '.
        get_string('manualgrading', 'block_fn_marking').'</a>';

    $o .= '</div><div class="qall-participants">';
    if ($qallparticipants) {
        $o .= '<input checked="checked" id="qall-participants-chk" type="checkbox" name="qallparticipants" data-target="' .
            $targetonurl->out() . '" data-target-off="' . $targetoffurl->out() . '"> Show all participants';
    } else {
        $o .= '<input id="qall-participants-chk" type="checkbox" name="qallparticipants" data-target="' .
            $targetonurl->out() . '" data-target-off="' . $targetoffurl->out() . '"> Show all participants';
    }
    $o .= '</div></div>';

    $o .= '<div class="fn_quiz_header"><img src="' . $OUTPUT->pix_url('icon', 'quiz') . '">' . $quiz->name . '</div>';

    $qpagingparams = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => $qallparticipants
    );
    $pagingurl = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $qpagingparams);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, $pagingurl, 'page');
    $o .= $OUTPUT->render($pagingbar);
    $o .= html_writer::table($table);

} else if ($show == 'unsubmitted') {

    $sqlquizattempts = "SELECT CONCAT(u.id, '#', COALESCE(quiza.attempt, 0)) AS uniqueid,
							(CASE WHEN (quiza.state = 'finished' AND NOT EXISTS (
							SELECT 1 FROM {quiz_attempts} qa2
							WHERE qa2.quiz = quiza.quiz AND
							qa2.userid = quiza.userid AND
							qa2.state = 'finished' AND (
							COALESCE(qa2.sumgrades, 0) > COALESCE(quiza.sumgrades, 0) OR
							(COALESCE(qa2.sumgrades, 0) = COALESCE(quiza.sumgrades, 0) AND qa2.attempt < quiza.attempt)
							))) THEN 1 ELSE 0 END) AS gradedattempt,

							quiza.uniqueid AS usageid,
							quiza.id AS attempt,
							u.id AS userid,
							u.idnumber, u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename,u.firstname,u.lastname,
							u.picture,
							u.imagealt,
							u.institution,
							u.department,
							u.email,
							quiza.state,
							quiza.sumgrades,
							quiza.timefinish,
							quiza.timestart,
							CASE WHEN quiza.timefinish = 0 THEN null
							WHEN quiza.timefinish > quiza.timestart THEN quiza.timefinish - quiza.timestart
							ELSE 0 END AS duration, COALESCE((
							SELECT MAX(qqr.regraded)
							FROM {quiz_overview_regrades} qqr
							WHERE qqr.questionusageid = quiza.uniqueid
							), -1) AS regraded
							FROM
							{user} u
							LEFT JOIN {quiz_attempts} quiza ON
							quiza.userid = u.id AND quiza.quiz = ?
							WHERE u.id IN ($studentids) AND quiza.preview = 0
							AND quiza.id IS NOT NULL AND quiza.sumgrades > 0";

    if ($quizattempts = $DB->get_records_sql($sqlquizattempts, array($quiz->id))) {
        foreach ($quizattempts as $quizattempt) {
            if (isset($students[$quizattempt->userid])) {
                unset($students[$quizattempt->userid]);
            }
        }
    }

    if (count($students) > 0) {

        $image = "<a href=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">
            <img border=0 valign=absmiddle src=\"".$OUTPUT->pix_url('icon', 'quiz')."\" " .
            "height=16 width=16 ALT=\"$cm->modname\"></a>";

        $o .= '<div class="unsubmitted_header">' . $image .
            " Quiz: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" .
            $quiz->name . '</a></div>';


        $o .= '<p class="unsubmitted_msg">The following students have not submitted this assignment:</p>';

        foreach ($students as $student) {

            $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
            $o .= "\n<tr>";
            $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
            $user = $DB->get_record('user', array('id' => $student->id));
            $o .= $OUTPUT->user_picture($user, array('courseid' => $course->id, 'size' => 20));
            $o .= "</td>";
            $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
            $o .= "</tr></table>\n";

        }
    } else if (count($students) == 0) {
        $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
    }
}
