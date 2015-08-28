<?php
global $DB, $OUTPUT, $FULLME;

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
//require_once($CFG->dirroot . '/blocks/fn_marking/quiz_report/default.php');

/// Get the quizm
if (! $quiz = $DB->get_record("quiz", array("id"=>$iid))) {
    print_error("Course module is incorrect");
}

/// Get the course module entry
if (! $cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
    print_error("Course Module ID was incorrect");
}

$ctx = context_module::instance($cm->id);

$currentgroup = groups_get_activity_group($cm, true);
$students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
$student_ids = implode(',', array_keys($students));



//  Paging options:
$qsort      = optional_param('qsort', 'firstname', PARAM_ALPHANUM);
$qdir       = optional_param('qdir', 'ASC', PARAM_ALPHA);

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

    $quiz_slots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), 'slot ASC');

// use paging
    $sql_quiz_attempts_count = "SELECT COUNT(1) FROM {user} u LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = ? WHERE u.id IN ($student_ids) AND quiza.preview = 0 AND quiza.id IS NOT NULL $filter";

    $totalcount = $DB->count_records_sql($sql_quiz_attempts_count, array($quiz->id));

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
//Build Headers
    foreach ($quiz_slots as $quiz_slot) {
        $columns [] = 'qsgrade' . $quiz_slot->slot;
        $headers['qsgrade' . $quiz_slot->slot] = 'Q.' . $quiz_slot->slot . '<br>/' . round($quiz_slot->maxmark * ($quiz->grade / $quiz->sumgrades));
    }

    if ($qsort) {
        $sort_sql = " ORDER BY $qsort $qdir";
    }

    $sql_quiz_attempts = "SELECT CONCAT(u.id, '#', COALESCE(quiza.attempt, 0)) AS uniqueid,
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
							quiza.userid = u.id AND quiza.quiz = ? WHERE u.id IN ($student_ids) AND quiza.preview = 0 AND quiza.id IS NOT NULL $filter $sort_sql";


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
            $qsorting_params = array(
                'view' => $view,
                'show' => $show,
                'mid' => $mid,
                'courseid' => $courseid,
                'perpage' => $perpage,
                'qsort' => $column,
                'qdir' => $columndir
            );

            $sortingURL = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $qsorting_params);
            $$column = "<a href=\"" . $sortingURL->out() . "\">" . $headers[$column] . "</a>$columnicon";
        }
    }

    $table = new html_table();
    $table->id = 'fn-quiz-grading';
    $table->head = $headers;

    $table->align = array('left', 'left', 'left');
    $table->wrap = array('', 'nowrap', '');


    $tableRows = $DB->get_records_sql($sql_quiz_attempts, array($quiz->id), $page * $perpage, $perpage);

    $counter = ($page * $perpage);

    foreach ($tableRows as $tableRow) {
        $row = new html_table_row();
        $cell_rowcount = ++$counter;
        $cell_array = array();

        //USER PICTURE
        $user = $DB->get_record('user', array('id' => $tableRow->userid));
        $cell_array[] = new html_table_cell($OUTPUT->user_picture($user, array('size' => 35, 'class' => 'welcome_userpicture')));

        //NAME
        $cell_name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $tableRow->userid . '&amp;course=' . $quiz->course . '">' . $tableRow->firstname . ' ' . $tableRow->lastname . '</a><br>
                      <a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $tableRow->attempt . '" class="reviewlink">Review attempt</a></td>';
        $cell_array[] = new html_table_cell($cell_name);



        //SUMGRADES
        if ($tableRow->sumgrades) {
            $attempt_grade = round($tableRow->sumgrades * ($quiz->grade / $quiz->sumgrades), 2);
            $cell_sumgardes = '<a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $tableRow->attempt . '" title="Review attempt">' . $attempt_grade . '</a>';
        } else {
            $cell_sumgardes = '<a href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $tableRow->attempt . '" title="Review attempt">Not yet graded</a>';
        }
        $cell_array[] = $cell_sumgardes;

        foreach ($quiz_slots as $quiz_slot) {
            $table->wrap[] = 'nowrap';
            $table->align[] = 'center';
            $columns [] = 'qsgrade' . $quiz_slot->slot;

            $sql_usages = "SELECT qasd.id,
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
                            WHERE qa.slot=?
                              AND quiza.id = ?
                         ORDER BY qas.id DESC";

            $question_step = $DB->get_records_sql($sql_usages, array($quiz_slot->slot, $tableRow->attempt));
            $question_step = reset($question_step);
            $quiz->sumgrades;
            $quiz->grade;
            $question_step_grade = round(($question_step->fraction * $question_step->maxmark) * ($quiz->grade / $quiz->sumgrades), 2);
            //print_object($question_step);

            if ($question_step->state == 'gradedwrong') {
                $cell_array[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' . $tableRow->attempt . '&amp;slot=' . $quiz_slot->slot . '" title="Review response">
                                <span class="que">
                                    <img src="' . $OUTPUT->pix_url('i/grade_incorrect', 'core') . '" title="Incorrect" alt="Incorrect" class="icon fn-icon">
                                    <span class="incorrect">' . $question_step_grade . '</span>
                                </span>
                             </a>';
            } elseif (($question_step->state == 'gradedright') || ($question_step->state == 'mangrright')) {
                $cell_array[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' . $tableRow->attempt . '&amp;slot=' . $quiz_slot->slot . '" title="Review response">
                                <span class="que">
                                    <img src="' . $OUTPUT->pix_url('i/grade_correct', 'core') . '" title="Incorrect" alt="Incorrect" class="icon fn-icon">
                                    <span class="correct">' . $question_step_grade . '</span>
                                </span>
                             </a>';
            } elseif ($question_step->state == 'needsgrading') {
                $cell = new html_table_cell('<a target="_blank" href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' . $tableRow->attempt . '&amp;slot=' . $quiz_slot->slot . '" title="Review response">
                                    <span class="que">
                                        <img src="' . $OUTPUT->pix_url('i/edit', 'core') . '" title="Edit" alt="Editt" class="icon fn-icon">
                                    </span></a>');
                $cell->attributes = array('class'=> 'fn-highlighted');
                $cell_array[] = $cell;
            } elseif ($question_step->state == 'mangrpartial') {
                $cell_array[] = '<a href="' . $CFG->wwwroot . '/mod/quiz/reviewquestion.php?attempt=' . $tableRow->attempt . '&amp;slot=' . $quiz_slot->slot . '" title="Review response">
                                    <span class="que">
                                        <img src="' . $OUTPUT->pix_url('i/grade_partiallycorrect', 'core') . '" title="Partially correct" alt="Partially correct" class="icon fn-icon">
                                        <span class="partiallycorrect">' . $question_step_grade . '</span>
                                    </span></a>';
            }
        }

        $row->cells = $cell_array;
        $table->data[] = $row;
    }

    $target_on_params = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => 1
    );
    $target_off_params = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => 0
    );
    $target_on_url = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $target_on_params);
    $target_off_url = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $target_off_params);

    echo '<div class="quiz-top-menu"><div class="qdefault-view">';
    echo '<a href="' . $CFG->wwwroot . '/mod/quiz/report.php?id=' . $cm->id . '&mode=overview"><img src="' . $OUTPUT->pix_url('popup', 'scorm') . '"> Moodle default view</a>';
    echo '</div><div class="qall-participants">';
    if ($qallparticipants) {
        echo '<input checked="checked" id="qall-participants-chk" type="checkbox" name="qallparticipants" data-target="' . $target_on_url->out() . '" data-target-off="' . $target_off_url->out() . '"> Show all participants';
    } else {
        echo '<input id="qall-participants-chk" type="checkbox" name="qallparticipants" data-target="' . $target_on_url->out() . '" data-target-off="' . $target_off_url->out() . '"> Show all participants';
    }
    echo '</div></div>';

    echo '<div class="fn_quiz_header"><img src="' . $OUTPUT->pix_url('icon', 'quiz') . '">' . $quiz->name . '</div>';

    $qpaging_params = array(
        'courseid' => $courseid,
        'show' => $show,
        'view' => $view,
        'mid' => $mid,
        'perpage' => $perpage,
        'sort' => $qsort,
        'dir' => $qdir,
        'qallparticipants' => $qallparticipants
    );
    $pagingURL = new moodle_url('/blocks/fn_marking/fn_gradebook.php?', $qpaging_params);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, $pagingURL, 'page');
    echo $OUTPUT->render($pagingbar);
    echo html_writer::table($table);

} elseif ($show == 'unsubmitted') {

    $sql_quiz_attempts = "SELECT CONCAT(u.id, '#', COALESCE(quiza.attempt, 0)) AS uniqueid,
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
							quiza.userid = u.id AND quiza.quiz = ? WHERE u.id IN ($student_ids) AND quiza.preview = 0 AND quiza.id IS NOT NULL AND quiza.sumgrades > 0";

    if ($quiz_attempts = $DB->get_records_sql($sql_quiz_attempts, array($quiz->id))) {
        foreach ($quiz_attempts as $quiz_attempt) {
            if (isset($students[$quiz_attempt->userid])) {
                unset($students[$quiz_attempt->userid]);
            }
        }
    }

    if(count($students)>0){

        $image = "<a href=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\"> <img border=0 valign=absmiddle src=\"".$OUTPUT->pix_url('icon', 'quiz')."\" " .
            "height=16 width=16 ALT=\"$cm->modname\"></a>";

        echo '<div class="unsubmitted_header">' . $image .
            " Quiz: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" . $quiz->name . '</a></div>';


        echo '<p class="unsubmitted_msg">The following students have not submitted this assignment:</p>';

        foreach ($students as $student) {

            echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
            echo "\n<tr>";
            echo "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
            $user = $DB->get_record('user',array('id'=>$student->id));
            echo $OUTPUT->user_picture($user, array('courseid'=>$course->id, 'size'=>20));
            echo "</td>";
            echo "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
            echo "</tr></table>\n";

        }
    }
    else if(count($students)==0){
        echo '<center><p>The are currently no <b>users</b>  to display.</p></center>';
    }
}
