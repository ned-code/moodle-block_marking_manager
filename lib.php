<?php

global $CFG;
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');
require_once($CFG->dirroot . '/lib/grade/grade_grade.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/blocks/fn_marking/locallib.php');
require_once($CFG->dirroot . '/mod/assignment/lib.php');


function assignment_count_ungraded($assignment, $graded, $students, $show='unmarked', $extra=false, $instance) {
    global $DB;

    $studentlist = implode(',', array_keys($students));
    if (empty($studentlist)) {
        return 0;
    }
    $subtable = 'assignment_submissions';
    if (($show == 'unmarked') || ($show == 'all')) {
        if ($instance->var4 == 1) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked < timemodified) AND (timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked < timemodified) AND (timemodified > 0)';
        }
        return $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
    } else if ($show == 'marked') {
        if ($instance->var4 == 1) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked >= timemodified) AND (timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked >= timemodified) AND (timemodified > 0)';
        }
        $marked = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
        return $marked;
    } else if ($show == 'unsubmitted') {
        if ($instance->var4) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0)';
        }

        $subbed = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
        $unsubbed = abs(count($students) - $subbed);
        return ($unsubbed);
    } else if ($show == 'saved') {
        if ($instance->var4) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0) AND data2="" ';
            $saved = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
            return $saved;
        } else {
            return 0;
        }
    } else {
        return 0;
    }
}

function assign_count_ungraded($assign, $graded, $students, $show='unmarked', $extra=false, $instance, $keepseparate=1) {
    global $DB;

    $studentlist = implode(',', array_keys($students));

    if (empty($studentlist)) {
        return 0;
    }

    if (!$keepseparate) {
        $showdraft = false;
    } else if ($instance->submissiondrafts) {
        $showdraft = true;
    } else {
        $showdraft = false;
    }

    if (($show == 'unmarked') || ($show == 'all')) {
        if ($showdraft) {
            $sql = "SELECT COUNT(DISTINCT s.id)
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g
                        ON (s.assignment=g.assignment AND s.userid=g.userid AND s.attemptnumber = g.attemptnumber)
                     WHERE s.assignment=$assign
                       AND (s.userid in ($studentlist))
                       AND s.status='submitted'
                       AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)";
        } else {
            $sql = "SELECT COUNT(DISTINCT s.id)
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g
                        ON (s.assignment=g.assignment AND s.userid=g.userid AND s.attemptnumber = g.attemptnumber)
                     WHERE s.assignment=$assign
                       AND (s.userid in ($studentlist))
                       AND s.status IN ('submitted', 'draft')
                       AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)";
        }
        return $DB->count_records_sql($sql);

    } else if ($show == 'marked') {
        $sqlunmarked = "SELECT s.userid
                  FROM {assign_submission} s
                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and  s.attemptnumber = g.attemptnumber)
                 WHERE s.assignment=$assign
                   AND (s.userid in ($studentlist))
                   AND s.status='submitted'
                  AND g.grade is null";

        if($unmarkedstus =  $DB->get_records_sql($sqlunmarked)) {
            $students = explode(',', $studentlist);

            foreach ($unmarkedstus as $unmarkedstu) {
                $students = array_diff($students, array($unmarkedstu->userid));
            }
            $studentlist = implode(',', $students);
        }

        if (empty($studentlist)) {
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT s.userid)
                  FROM {assign_submission} s
                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                 WHERE ((s.assignment=$assign
                   AND (s.userid in ($studentlist))
                   AND s.status IN ('submitted', 'resub')
                   AND g.grade is not null  AND g.grade <> -1)
                    OR (s.assignment=$assign
                   AND (s.userid in ($studentlist))
                   AND s.status='draft'
                   AND g.grade is not null
                   AND g.grade <> -1
                   AND g.timemodified > s.timemodified))";


        return $DB->count_records_sql($sql);

    } else if ($show == 'unsubmitted') {
        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {assign_submission}
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
        $subbed = $DB->count_records_sql($sql);
        $unsubbed = abs(count($students) - $subbed);
        return ($unsubbed);

    } else if ($show == 'saved') {

        if ($showdraft) {
            $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign_submission} s
             LEFT JOIN {assign_grades} g
                    ON s.assignment = g.assignment
                   AND s.userid = g.userid
                   AND s.attemptnumber = g.attemptnumber
                 WHERE s.assignment = $assign
                   AND s.userid IN ($studentlist)
                   AND s.status = 'draft'
                   AND (s.timemodified >= g.timemodified OR g.grade IS NULL)";

            return $DB->count_records_sql($sql);
        } else {
            return 0;
        }
    } else {
        return 0;
    }
}

function quiz_count_ungraded($quizid, $graded, $students, $show='unmarked', $extra=false, $instance, $keepseparate=1) {
    global $DB;

    $studentlist = implode(',', array_keys($students));

    if (empty($studentlist)) {
        return 0;
    }

    if (($show == 'unmarked') || ($show == 'all')) {

        $sql_gradable_quiz = "SELECT qs.id,
                             q.qtype
                        FROM {quiz_slots} qs
                        JOIN {question} q
                          ON qs.questionid = q.id
                       WHERE qs.quizid = ?
                         AND q.qtype = 'essay'";

        if ($DB->record_exists_sql($sql_gradable_quiz, array($instance->id))) {
            $sql = "SELECT COUNT(DISTINCT qa.userid)
                  FROM {quiz_attempts} qa
                 WHERE qa.quiz = ?
                   AND qa.state = 'finished'
                   AND qa.sumgrades IS NULL";

            return $DB->count_records_sql($sql, array($quizid));
        } else {
            return 0;
        }

    } else if ($show == 'marked') {

        $sql = "SELECT COUNT(DISTINCT qa.userid)
                  FROM {quiz_attempts} qa
                 WHERE qa.quiz = ?
                   AND qa.state = 'finished'
                   AND qa.sumgrades >= 0";

        return $DB->count_records_sql($sql, array($quizid));

    } else if ($show == 'unsubmitted') {

        $sql = "SELECT DISTINCT qa.userid FROM {quiz_attempts} qa WHERE qa.quiz = ? AND qa.state = 'finished' AND qa.sumgrades >= 0";

        if ($attempts = $DB->get_records_sql($sql, array($quizid))) {
            $unsubmitted = array_diff(array_keys($students), array_keys($attempts));
            return sizeof($unsubmitted);
        } else {
            return sizeof($students);
        }

    } else {
        return 0;
    }

}

function assign_students_ungraded($assign, $graded, $students, $show='unmarked', $extra=false, $instance, $sort=false) {
    global $DB, $CFG;

    $studentlist = implode(',', array_keys($students));

    if (empty($studentlist)) {
        return 0;
    }

    $subtable = 'assign_submission';

    if (($show == 'unmarked') || ($show == 'all')) {

        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)";


        if($data = $DB->get_records_sql($sql)){
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        }else{
            return false;
        }

    } else if ($show == 'marked') {

        $students = explode(',', $studentlist);

        $sqlunmarked = "SELECT s.userid
                  FROM {assign_submission} s
                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and  s.attemptnumber = g.attemptnumber)
                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND (g.grade is null  OR g.grade = -1)";

        if($unmarkedstus =  $DB->get_records_sql($sqlunmarked)){

            foreach ($unmarkedstus as $unmarkedstu)
            {
                $students = array_diff($students, array($unmarkedstu->userid));
            }
        }


        $studentlist = implode(',', $students);



        $sql = "SELECT Max(s.id) AS id,
                       s.userid
                  FROM {$CFG->prefix}assign_submission as s
             LEFT JOIN {$CFG->prefix}assign_grades as g
                    ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                 WHERE s.assignment=$assign
                   AND (s.userid in ($studentlist))
                   AND g.grade is not null
                   AND g.grade <> -1
              GROUP BY s.userid";



        if($data = $DB->get_records_sql($sql)){

            if ($sort){

                $arrids = array();
                $drafted = array();

                foreach ($data as $value) {
                    $arrids[] = $value->id;
                }

                //CHECK DRAFT is_Graded
                $sqlDraft = "SELECT s.id,
                                    s.timemodified AS submissiontime,
                                    g.timemodified AS gradetime
                               FROM {$CFG->prefix}assign_submission as s
                          LEFT JOIN {$CFG->prefix}assign_grades as g
                                 ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                              WHERE s.assignment = $assign
                                AND s.userid IN ($studentlist)
                                AND s.status = 'draft'";

                if($draftGrades =  $DB->get_records_sql($sqlDraft)){
                    foreach ($draftGrades as $draftGrade){
                        if(($draftGrade == null) || ($draftGrade->submissiontime >= $draftGrade->gradetime)){
                            $drafted[] = $draftGrade->id;
                        }
                    }
                    $arrids = array_diff($arrids, $drafted);
                }

                switch ($sort) {
                    case 'lowest':
                        $sqls = "SELECT s.userid
                                   FROM {$CFG->prefix}assign_submission AS s
                              LEFT JOIN {$CFG->prefix}assign_grades AS g
                                     ON (s.assignment = g.assignment AND s.userid = g.userid AND s.attemptnumber = g.attemptnumber)
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY g.grade ASC";
                        break;

                    case 'highest':
                        $sqls = "SELECT s.userid
                                   FROM {$CFG->prefix}assign_submission AS s
                              LEFT JOIN {$CFG->prefix}assign_grades AS g
                                     ON (s.assignment = g.assignment AND s.userid = g.userid AND s.attemptnumber = g.attemptnumber)
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY g.grade DESC";
                        break;

                    case 'date':
                        $sqls = "SELECT s.userid
                                   FROM {$CFG->prefix}assign_submission AS s
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY s.timemodified DESC";
                        break;

                    case 'alpha':
                        $sqls = "SELECT s.userid
                                   FROM {$CFG->prefix}assign_submission AS s
                             INNER JOIN {$CFG->prefix}user AS u
                                     ON s.userid = u.id
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY u.lastname ASC";
                        break;
                }


                if($datas = $DB->get_records_sql($sqls)){
                    $arr = array();
                    foreach ($datas as $value) {
                        $arr[] = $value->userid;
                    }

                    return $arr;
                }else{
                    return false;
                }
            } //SORT




            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }

            return $arr;
        }else{
            return false;
        }



    } else if ($show == 'unsubmitted') {
        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
        $subbed = $DB->get_records_sql($sql); //print_r($subbed);print_r($students);

        $unsubmitted= array_diff(array_keys($students), array_keys($subbed)); //print_r($gradedarray);die;
        return $unsubmitted = array_values($unsubmitted);


    } else if ($show == 'saved') {

        //CHECK DRAFT is_Graded
        $sqlDraft = "SELECT s.userid,
                            s.timemodified AS submissiontime,
                            g.timemodified AS gradetime
                       FROM {$CFG->prefix}assign_submission as s
                  LEFT JOIN {$CFG->prefix}assign_grades as g
                         ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                      WHERE s.assignment = $assign
                        AND s.userid IN ($studentlist)
                        AND s.status = 'draft'
                        AND g.grade IS NOT NULL
                        AND g.timemodified > s.timemodified";

        $studentlist = explode(',', $studentlist);

        if($draftGrades =  $DB->get_records_sql($sqlDraft)){
            foreach ($draftGrades as $draftGrade){
                $studentlist = array_diff($studentlist, array($draftGrade->userid));
            }
        }

        $studentlist = implode(',', $studentlist);

        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='draft'";




        if($data = $DB->get_records_sql($sql)){
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        }else{
            return false;
        }

    } else {
        return 0;
    }
}

function assignment_oldest_ungraded($assignment) {
    global $CFG, $DB;

    $sql = 'SELECT MIN(timemodified) FROM ' . $CFG->prefix . 'assignment_submissions ' .
            'WHERE (assignment = ' . $assignment . ') AND (timemarked < timemodified) AND (timemodified > 0)';
    return $DB->get_field_sql($sql);
}

function assign_oldest_ungraded($assign) {
    global $CFG, $DB;

    $sql = "SELECT MIN(s.timemodified)
              FROM {assign_submission} s
              LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
             WHERE s.assignment=$assign AND s.status='submitted' AND g.grade is null";
    return $DB->get_field_sql($sql);
}

function forum_count_ungraded($forumid, $graded, $students, $show='unmarked') {
    global $CFG, $DB;

    //Get students from forum_posts
    $fusers = $DB->get_records_sql("SELECT DISTINCT u.id
                               FROM {forum_discussions} d
                               INNER JOIN {forum_posts} p ON p.discussion = d.id
                               INNER JOIN {user} u ON u.id = p.userid
                               WHERE d.forum = $forumid");

    if (is_array($fusers)) {
        foreach ($fusers as $key => $user) {
            if (!array_key_exists($key, $students)) {
                unset($fusers[$key]);
            }
        }
    }

    if (($show == 'unmarked') || ($show == 'all')) {
        if (empty($graded) && !empty($fusers)) {
            return count($fusers);
        } else if (empty($fusers)) {
            return 0;
        } else {
            return (count($fusers) - count($graded));
        }
    } else if ($show == 'marked') {
        return count($graded);
    } else if ($show == 'unsubmitted') {
        $numuns = count($students) - count($fusers);
        return max(0, $numuns);
    }
}

function count_unmarked_students(&$course, $mod, $info='unmarked', $sort=false) {

    global $CFG, $DB;

    $context = context_course::instance($course->id);
    //$isteacheredit = has_capability('moodle/course:update', $context);
    //$marker = has_capability('moodle/grade:viewall', $context);

    $currentgroup = groups_get_activity_group($mod, true);
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    //$totungraded = 0;

/// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assign' => '/mod/assign/submissions.g8.html',
        'assignment' => '/mod/assignment/submissions.g8.html',
        'forum' => '/mod/forum/submissions.g8.html'
    );


    ////////////////////////////////
    /// Don't count it if you can't see it.
    $mcontext = context_module::instance($mod->id);
    if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
        return 0;
    }
    $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
    $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
    if (file_exists($libfile)) {
        require_once($libfile);
        //$gradefunction = $mod->modname . "_grades";
        $gradefunction = $mod->modname . "_get_user_grades";
        if (function_exists($gradefunction) &&
    //                            (($mod->modname != 'forum') || ($instance->assessed == 2)) && // Only include forums that are assessed only by teachers.
                isset($mod_grades_array[$mod->modname])) {
            /// Use the object function for fnassignments.
            if (($mod->modname == 'forum') &&
                    (($instance->assessed <= 0) || !has_capability('mod/forum:rate', $mcontext))) {
                $modgrades = false;
            } else {
                $modgrades = new Object();
                if (!($modgrades->grades = $gradefunction($instance))) {
                    $modgrades->grades = array();
                }
            }
            if ($modgrades) {
                /// Store the number of ungraded entries for this group.
                if (is_array($modgrades->grades) && is_array($students)) {
                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                    $numgraded = count($gradedarray);
                    $numstudents = count($students);
                    $ungradedfunction = $mod->modname . '_students_ungraded';
                    if (function_exists($ungradedfunction)) {
                        $extra = false;
                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance, $sort);
                        return $ung;
                    } else {
                        $ung = $numstudents - $numgraded;
                    }
                }
            }
        }
    }


}

function count_unmarked_activities(&$course, $info='unmarked', $module='') {

    global $CFG, $DB;
    global $mods, $modnames, $modnamesplural, $modnamesused, $sections;

    $context = context_course::instance($course->id);
    $isteacheredit = has_capability('moodle/course:update', $context);
    $marker = has_capability('moodle/grade:viewall', $context);

    $include_orphaned = get_config('block_fn_marking','include_orphaned');

  //FIND CURRENT WEEK
    $courseformatoptions = course_get_format($course)->get_format_options();
    $courseformat = course_get_format($course)->get_format();
    if( isset($courseformatoptions['numsections'])){
        $course_numsections = $courseformatoptions['numsections'];
    } else {
        $course_numsections = 10; //Default section number
    }

    if ($courseformat == 'weeks') {
        $timenow = time();
        $weekdate = $course->startdate;    // this should be 0:00 Monday of that week
        $weekdate += 7200;                 // Add two hours to avoid possible DST problems

        $weekofseconds = 604800;
        $course_enddate = $course->startdate + ($weekofseconds * $course_numsections);

        //  Calculate the current week based on today's date and the starting date of the course.
        $currentweek = ($timenow > $course->startdate) ? (int)((($timenow - $course->startdate) / $weekofseconds) + 1) : 0;
        $currentweek = min($currentweek, $course_numsections);

        $upto = min($currentweek, $course_numsections);
    } else {
        $upto = $course_numsections;
    }

    $totungraded = 0;

    /// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assign' => '/mod/assign/submissions.g8.html',
        'assignment' => '/mod/assignment/submissions.g8.html',
        'quiz' => '/mod/quiz/submissions.g8.html',
        'forum' => '/mod/forum/submissions.g8.html'
    );

    $sections = $DB->get_records('course_sections', array('course' => $course->id), 'section ASC', 'section, sequence');

    $selected_section = array();
    for ($i = 0; $i <= $upto; $i++) {
        $selected_section[] = $i;
    }
    if ($include_orphaned && (sizeof($sections) > ($course_numsections+1))) {
        for ($i = ($course_numsections+1); $i < sizeof($sections); $i++) {
            $selected_section[] = $i;
        }
    }

    if(!$students = get_enrolled_users($context, 'mod/assignment:submit', 0, 'u.*', 'u.id')) {
        return 0;
    }

    foreach ($selected_section as $section_num) {
        $i = $section_num;
        if (isset($sections[$i])) {   // should always be true
            $section = $sections[$i];
            if ($section->sequence) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = get_coursemodule_from_id('',$sectionmod, $course->id);

                    if (!isset($mod_grades_array[$mod->modname])) {
                        continue;
                    }

                    if ($module) {
                        if ($module <> $mod->modname) {
                            continue;
                        }
                    }
                    /////////Changed in order to performance issue
                    //$currentgroup = groups_get_activity_group($mod, true);
                    //$students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');

                    /// Don't count it if you can't see it.
                    $mcontext = context_module::instance($mod->id);
                    if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
                        continue;
                    }
                    $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
                    $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
                    if (file_exists($libfile)) {
                        require_once($libfile);
                        $gradefunction = $mod->modname . "_get_user_grades";

                        if (function_exists($gradefunction)) {
                            /// Use the object function for fnassignments.
                            if (($mod->modname == 'forum') && (($instance->assessed <= 0) || !has_capability('mod/forum:rate', $mcontext))) {
                                $modgrades = false;
                            } else {
                                $modgrades = new stdClass();
                                if (!($modgrades->grades = $gradefunction($instance))) {
                                    $modgrades->grades = array();
                                }
                                //////////////////////////////////
                                $sql = "SELECT asub.id,
                                               asub.userid,
                                               ag.grade
                                          FROM {assign_submission} asub
                                     LEFT JOIN {assign_grades} ag
                                            ON asub.userid = ag.userid
                                           AND asub.assignment = ag.assignment
                                           AND asub.attemptnumber = ag.attemptnumber
                                         WHERE asub.assignment = ?
                                           AND asub.status = 'submitted'";

                                if($gradedSunmissions = $DB->get_records_sql($sql, array($instance->id))){
                                    foreach ($gradedSunmissions as $gradedSunmission) {
                                        if(! $gradedSunmission->grade){
                                            if(isset($modgrades->grades[$gradedSunmission->userid])){
                                                unset($modgrades->grades[$gradedSunmission->userid]);
                                            }
                                        }
                                    }
                                }
                                //////////////////////////////////
                            }
                            if ($modgrades) {
                                /// Store the number of ungraded entries for this group.
                                if (is_array($modgrades->grades) && is_array($students)) {
                                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                                    $numgraded = count($gradedarray);
                                    $numstudents = count($students);
                                    $ungradedfunction = $mod->modname . '_count_ungraded';
                                    if (function_exists($ungradedfunction)) {
                                        $extra = false;
                                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance);
                                    } else {
                                        $ung = $numstudents - $numgraded;
                                    }
                                    if ($marker) {
                                        $totungraded += $ung;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $totungraded;
}

function fn_count_notloggedin($course, $days) {
    $truants = fn_get_notloggedin($course, $days);
    return count($truants);
}

function fn_get_notloggedin($course, $days) {
    global $CFG, $DB;

    // grab context
    $context = context_course::instance($course->id);

    //grab current group
    $currentgroup = groups_get_course_group($course, true);;
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    // calculate a the before
    $now = time();
    $lastweek = $now - (60 * 60 * 24 * $days);

    // students who haven't logged in
    $truants = array();

    // iterate
    foreach ($students as $student) {

        // possible fields: lastaccess, lastlogin, currentlogin
        $lastaccess = $student->lastaccess;
        if ($lastaccess < $lastweek) {
            $truants[] = $student;
        }
    }

    return $truants;
}

function fn_get_failing($course, $percent) {
      //grab context
    $context = context_course::instance($course->id);

    $student_ids = array();
    // grab  current group
    $currentgroup = groups_get_course_group($course, true);;
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');

    // students array is keyed on id
    if ($students) {
        foreach ($students as $student) {
            $student_ids[] = $student->id;
        }
    }

    // grab all grades; keyed by userid
    // students array is keyed on id
    $all_grades = grade_get_course_grades($course->id, $student_ids);
    $grades = $all_grades->grades;

    //
    $failing = array();

    // create array of all failing students
    // keyed on studentid
    foreach ($grades as $studentid => $grade_obj) {

        // grab grade and convert to int (NULL -> 0)
        $grade = (int) $grade_obj->grade;

        if ($grade < $percent) {
            $failing[$studentid] = $students[$studentid];
        }
    }

    return $failing;
}

function fn_count_failing($course, $percent) {
    return count(fn_get_failing($course, $percent));
}

function fn_get_notsubmittedany($course, $since = 0, $count = false, $sections, $students) {

    // grab context
    $context = context_course::instance($course->id);

    // get current group
    $currentgroup = groups_get_course_group($course, true);

    // grab modgradesarry
    $mod_grades_array = fn_get_active_mods();

    if (!isset($students)) {
        $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    }

    for ($i = 0; $i < sizeof($sections); $i++) {
        if (isset($sections[$i])) {   // should always be true
            $section = $sections[$i];
            if ($section->sequence) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = get_coursemodule_from_id('', $sectionmod, $course->id);
                    if (isset($mod_grades_array[$mod->modname])) {
                        require_once('locallib.php');
                        // build mod method
                        $f = $mod->modname . '_get_notsubmittedany';
                        // make sure function exists
                        if (!function_exists($f)) {
                            continue;
                        }

                        // grab list of students with submissions for this activity
                        $students_with_submissions = $f($course->id, $mod->instance, $students, $since);
                        if ($students_with_submissions) {
                            $student_ids = array_keys($students);
                            $sws_ids = array_keys($students_with_submissions);
                            foreach ($sws_ids as $id) {
                                unset($students[$id]);
                            }
                        }

                        // if all students have a submission, return null
                        if (empty($students)) {
                            if ($count) {
                                return 0;
                            } else {
                                return;
                            }
                        }
                    } // wrong activity type
                } // move onto next sectionmod
            } // $section has mods in it
        } // should always be true?
    } // next section

    if ($count) {
        return count($students);
    } else {
        return $students;
    }
}

function fn_get_active_mods($which = 'grades') {

    /// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assignment' => 'assignment.submissions.fn.html',
        'forum' => 'forum.submissions.fn.html'
    );

    /// Array of functions to call to display grades for modules.
    $mod_gradedisp_array = array(
        'assignment' => 'grades.fn.html',
        'forum' => 'grades.fn.html'
    );

    $mod_array = array(
        'assignment',
        'forum'
    );

    switch ($which) {
        case 'grades':
            return $mod_grades_array;
        case 'display':
            return $mod_gradedisp_array;
        case 'activities':
            return $mod_array;
        default:
            return $mod_array;
    }
}

function fn_is_graded($userid, $assign) {
    $grade = $assign->get_user_grade($userid, false);
    if ($grade) {
        return ($grade->grade !== NULL && $grade->grade >= 0);
    }
    return false;
}

function fn_get_grading_instance($userid, $grade, $gradingdisabled, $assign) {
    global $CFG, $USER;

    $grademenu = make_grades_menu($assign->get_instance()->grade);

    $advancedgradingwarning = false;
    $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
    $gradinginstance = null;
    if ($gradingmethod = $gradingmanager->get_active_method()) {
        $controller = $gradingmanager->get_controller($gradingmethod);
        if ($controller->is_form_available()) {
            $itemid = null;
            if ($grade) {
                $itemid = $grade->id;
            }
            if ($gradingdisabled && $itemid) {
                $gradinginstance = $controller->get_current_instance($USER->id, $itemid);
            } else if (!$gradingdisabled) {
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                $gradinginstance = $controller->get_or_create_instance($instanceid,
                                                                       $USER->id,
                                                                       $itemid);
            }
        } else {
            $advancedgradingwarning = $controller->form_unavailable_notification();
        }
    }
    if ($gradinginstance) {
        $gradinginstance->get_controller()->set_grade_range($grademenu);
    }
    return $gradinginstance;
}


function fn_apply_grade_to_user($formdata, $userid, $attemptnumber, $assign) {
    global $USER, $CFG, $DB;

    $grade = $assign->get_user_grade($userid, true, $attemptnumber);
    $gradingdisabled = $assign->grading_disabled($userid);
    $gradinginstance = fn_get_grading_instance($userid, $grade, $gradingdisabled, $assign);
    if (!$gradingdisabled) {
        if ($gradinginstance) {
            $grade->grade = $gradinginstance->submit_and_get_grade($formdata->advancedgrading,
                $grade->id);
        } else {
            // Handle the case when grade is set to No Grade.
            if (isset($formdata->grade)) {
                $grade->grade = grade_floatval(unformat_float($formdata->grade));
            }
        }
        if (isset($formdata->workflowstate) || isset($formdata->allocatedmarker)) {
            $flags = $assign->get_user_flags($userid, true);
            $oldworkflowstate = $flags->workflowstate;
            $flags->workflowstate = isset($formdata->workflowstate) ? $formdata->workflowstate : $flags->workflowstate;
            $flags->allocatedmarker = isset($formdata->allocatedmarker) ? $formdata->allocatedmarker : $flags->allocatedmarker;
            if ($assign->update_user_flags($flags) &&
                isset($formdata->workflowstate) &&
                $formdata->workflowstate !== $oldworkflowstate) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                \mod_assign\event\workflow_state_updated::create_from_user($assign, $user, $formdata->workflowstate)->trigger();
            }
        }
    }
    $grade->grader= $USER->id;

    $adminconfig = $assign->get_admin_config();
    $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

    //$submissionplugins = $assign->load_plugins('assignsubmission');
    $feedbackplugins = fn_load_plugins('assignfeedback', $assign);

    // Call save in plugins.
    foreach ($feedbackplugins as $plugin) {
        if ($plugin->is_enabled() && $plugin->is_visible()) {
            if (!$plugin->save($grade, $formdata)) {
                $result = false;
                print_error($plugin->get_error());
            }
            if (('assignfeedback_' . $plugin->get_type()) == $gradebookplugin) {
                // This is the feedback plugin chose to push comments to the gradebook.
                $grade->feedbacktext = $plugin->text_for_gradebook($grade);
                $grade->feedbackformat = $plugin->format_for_gradebook($grade);
            }
        }
    }
    $assign->update_grade($grade, !empty($formdata->addattempt));
    // Note the default if not provided for this option is true (e.g. webservices).
    // This is for backwards compatibility.
    if (!isset($formdata->sendstudentnotifications) || $formdata->sendstudentnotifications) {
        $assign->notify_grade_modified($grade, true);
    }


}

/**
 * Load the plugins from the sub folders under subtype.
 *
 * @param string $subtype - either submission or feedback
 * @return array - The sorted list of plugins
 */
function fn_load_plugins($subtype, $assign) {
    global $CFG;
    $result = array();

    $names = core_component::get_plugin_list($subtype);

    foreach ($names as $name => $path) {
        if (file_exists($path . '/locallib.php')) {
            require_once($path . '/locallib.php');

            $shortsubtype = substr($subtype, strlen('assign'));
            $pluginclass = 'assign_' . $shortsubtype . '_' . $name;

            $plugin = new $pluginclass($assign, $name);

            if ($plugin instanceof assign_plugin) {
                $idx = $plugin->get_sort_order();
                while (array_key_exists($idx, $result)) {
                    $idx +=1;
                }
                $result[$idx] = $plugin;
            }
        }
    }
    ksort($result);
    return $result;
}

function fn_process_outcomes($userid, $formdata, $assign) {
    global $CFG, $USER;

    if (empty($CFG->enableoutcomes)) {
        return;
    }
    if ($assign->grading_disabled($userid)) {
        return;
    }

    require_once($CFG->libdir.'/gradelib.php');

    $data = array();
    $gradinginfo = grade_get_grades($assign->get_course()->id,
                                    'mod',
                                    'assign',
                                    $assign->get_instance()->id,
                                    $userid);

    if (!empty($gradinginfo->outcomes)) {
        foreach($gradinginfo->outcomes as $index=>$oldoutcome) {
            $name = 'outcome_'.$index;
            if (isset($formdata->{$name}[$userid]) and $oldoutcome->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                $data[$index] = $formdata->{$name}[$userid];
            }
        }
    }
    if (count($data) > 0) {
        grade_update_outcomes('mod/assign', $assign->course->id, 'mod', 'assign', $assign->get_instance()->id, $userid, $data);
    }

}

function fn_process_save_grade(&$mform, $assign, $context, $course, $pageparams) {
    global $CFG;
    // Include grade form
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment
    require_capability('mod/assign:grade', $context);
    require_sesskey();

    $rownum = required_param('rownum', PARAM_INT);
    $useridlist = optional_param('useridlist', '', PARAM_TEXT);
    $attemptnumber = optional_param('attemptnumber', -1, PARAM_INT);
    $useridlistid = optional_param('useridlistid', time(), PARAM_INT);
    $userid = optional_param('userid', 0, PARAM_INT);
    $activity_type = optional_param('activity_type', 0, PARAM_TEXT);
    $group = optional_param('group', 0, PARAM_INT);
    $participants = optional_param('participants', 0, PARAM_INT);


    if ($useridlist) {
        $useridlist = explode(',', $useridlist);
    } else {
        $useridlist = $assign->get_grading_userid_list();
    }
    $last = false;
    $userid = $useridlist[$rownum];
    if ($rownum == count($useridlist) - 1) {
        $last = true;
    }


    $data = new stdClass();

    $pageparams['rownum']     = $rownum;
    $pageparams['useridlist'] = $useridlist;
    $pageparams['useridlistid'] = $useridlistid;
    $pageparams['last']       = $last;
    $pageparams['savegrade']  = true;
    $pageparams['attemptnumber']  = $attemptnumber;
    $pageparams['activity_type']  = $activity_type;
    $pageparams['group']  = $group;
    $pageparams['participants']  = $participants;


    $formparams = array($assign, $data, $pageparams);

    $mform = new mod_assign_grading_form_fn(null, $formparams, 'post', '', array('class'=>'gradeform'));

    if ($formdata = $mform->get_data()) {
        $submission = null;

        $instance = $assign->get_instance();

        if ($instance->teamsubmission) {
            $submission = $assign->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $assign->get_user_submission($userid, false, $attemptnumber);
        }
        if ($instance->teamsubmission && $formdata->applytoall) {
            $groupid = 0;
            if ($assign->get_submission_group($userid)) {
                $group = $assign->get_submission_group($userid);
                if ($group) {
                    $groupid = $group->id;
                }
            }
            $members = $assign->get_submission_group_members($groupid, true);
            foreach ($members as $member) {
                // User may exist in multple groups (which should put them in the default group).
                fn_apply_grade_to_user($formdata, $member->id, $attemptnumber. $assign);
                fn_process_outcomes($member->id, $formdata, $assign);
            }
        } else {
            fn_apply_grade_to_user($formdata, $userid, $attemptnumber, $assign);

            fn_process_outcomes($userid, $formdata, $assign);
        }
        $maxattemptsreached = !empty($submission) &&
                              $submission->attemptnumber >= ($instance->maxattempts - 1) &&
                              $instance->maxattempts != ASSIGN_UNLIMITED_ATTEMPTS;
        $shouldreopen = false;
        if ($instance->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS) {
            // Check the gradetopass from the gradebook.
            $gradinginfo = grade_get_grades($assign->get_course()->id,
                                            'mod',
                                            'assign',
                                            $instance->id,
                                            $userid);

            // What do we do if the grade has not been added to the gradebook (e.g. blind marking)?
            $gradingitem = null;
            $gradebookgrade = null;
            if (isset($gradinginfo->items[0])) {
                $gradingitem = $gradinginfo->items[0];
                $gradebookgrade = $gradingitem->grades[$userid];
            }

            if ($gradebookgrade) {
                // TODO: This code should call grade_grade->is_passed().
                $shouldreopen = true;
                if (is_null($gradebookgrade->grade)) {
                    $shouldreopen = false;
                }
                if (empty($gradingitem->gradepass) || $gradingitem->gradepass == $gradingitem->grademin) {
                    $shouldreopen = false;
                }
                if ($gradebookgrade->grade >= $gradingitem->gradepass) {
                    $shouldreopen = false;
                }
            }
        }
        if ($instance->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL &&
                !empty($formdata->addattempt)) {
            $shouldreopen = true;
        }
        // Never reopen if we are editing a previous attempt.
        if ($attemptnumber != -1) {
            $shouldreopen = false;
        }
        if ($shouldreopen && !$maxattemptsreached) {
            fn_process_add_attempt($userid, $assign);
        }
    } else {
        return false;
    }
    return true;
}

function fn_view_single_grade_page($mform, $offset=0, $assign, $context, $cm, $course, $pageparams, $showattemptnumber=null) {
    global $DB, $CFG;

    $o = '';
    $instance = $assign->get_instance();

    // Include grade form
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment
    $readonly = false;
    if(! has_capability('mod/assign:grade', $context)){
        if(has_capability('block/fn_marking:viewreadonly',$context)){
            $readonly = true;
        }else{
            require_capability('mod/assign:grade', $context);
        }
    }


    $rownum = $pageparams['rownum'] + $offset;
    $useridlistid = optional_param('useridlistid', time(), PARAM_INT);
    $userid = optional_param('userid', 0, PARAM_INT);
    $attemptnumber = optional_param('attemptnumber', -1, PARAM_INT);
    $activity_type = optional_param('activity_type', 0, PARAM_TEXT);
    $group = optional_param('group', 0, PARAM_INT);
    $participants = optional_param('participants', 0, PARAM_INT);

    if($pageparams['userid']){
        $userid = $pageparams['userid'];

        $arruser = count_unmarked_students($course, $cm, $pageparams['show']);
        $useridlist = $arruser;
        $last = false;

        $rownum = array_search($userid, $useridlist);
        if ($rownum == count($useridlist) - 1) {
            $last = true;
        }

    }else{
        $arruser = count_unmarked_students($course, $cm, $pageparams['show']);
        $useridlist = optional_param('useridlist', '', PARAM_TEXT);
        if ($useridlist) {
            $useridlist = explode(',', $useridlist);
        } else {
            $useridlist = get_grading_userid_list($assign);
        }
        //
        $useridlist = $arruser;
        $last = false;

        //BIG ROW NUMBER FIXER
        $numofuser = count($useridlist);
        if ($numofuser > 0){
            if ($rownum > $numofuser - 1) {
                $rownum = $numofuser - 1;
            }
        }
        $userid = $useridlist[$rownum];

        if ($rownum == $numofuser - 1) {
            $last = true;
        }
        if (!$userid) {
            $o = "There is no record";
            return $o;
            //throw new coding_exception('Row is out of bounds for the current grading table: ' . $rownum);
        }
    }

    $user = $DB->get_record('user', array('id' => $userid));


    $submission = $assign->get_user_submission($userid, false, $showattemptnumber);
    $submissiongroup = null;
    $teamsubmission = null;
    $notsubmitted = array();
    if ($assign->get_instance()->teamsubmission) {
        $teamsubmission = $assign->get_group_submission($userid, 0, false, $showattemptnumber);
        $submissiongroup = $assign->get_submission_group($userid);
        $groupid = 0;
        if ($submissiongroup) {
            $groupid = $submissiongroup->id;
        }
        $notsubmitted = $assign->get_submission_group_members_who_have_not_submitted($groupid, false);

    }

    // get the current grade
    $grade = $assign->get_user_grade($userid, false, $showattemptnumber);
    $flags = $assign->get_user_flags($userid, false);

    // Get all the submissions (for the history view).
    //list($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber) =  fn_get_submission_history($submission, $grade, $user, $showattemptnumber, $assign);

    if ($grade) {
        $data = new stdClass();
        if ($grade->grade !== NULL && $grade->grade >= 0) {
            $data->grade = format_float($grade->grade, 2);
        }
    } else {
        $data = new stdClass();
        //$data->grade = '-1';
    }
    //print_r($data);
    // Warning if required.
    $allsubmissions = fn_get_all_submissions($userid, $assign);

    if ($attemptnumber != -1) {
        $params = array('attemptnumber'=>$attemptnumber + 1,
                        'totalattempts'=>count($allsubmissions));
        $message = get_string('editingpreviousfeedbackwarning', 'assign', $params);
        $o .= $assign->get_renderer()->notification($message);
    }
    $maxattemptnumber = $assign->get_instance()->maxattempts;
    // now show the grading form
    if (!$mform) {
        $pageparams['rownum']     = $rownum;
        $pageparams['useridlist'] = $useridlist;
        $pageparams['last']       = $last;
        $pageparams['userid']     = optional_param('userid', 0, PARAM_INT);
        $pageparams['readonly']   = $readonly;
        $pageparams['attemptnumber'] = $attemptnumber;
        $pageparams['maxattemptnumber'] = $maxattemptnumber;
        $pageparams['activity_type'] = $activity_type;
        $pageparams['group'] = $group;
        $pageparams['participants'] = $participants;


        $formparams = array($assign, $data, $pageparams);

        $mform = new mod_assign_grading_form_fn(null,
                                               $formparams,
                                               'post',
                                               '',
                                               array('class'=>'gradeform'));
    }
    $o .= $assign->get_renderer()->render(new assign_form('gradingform', $mform));
    $version = explode('.', $CFG->version);
    $version = reset($version);

    if (count($allsubmissions) > 1 && $attemptnumber == -1) {
        $allgrades = fn_get_all_grades($userid, $assign);

        if ($version >= 2013051405) {
            $history = new assign_attempt_history($allsubmissions,
                                                  $allgrades,
                                                  $assign->get_submission_plugins(),
                                                  $assign->get_feedback_plugins(),
                                                  $assign->get_course_module()->id,
                                                  $assign->get_return_action(),
                                                  $assign->get_return_params(),
                                                  true,null,null);
        } else {
            $history = new assign_attempt_history($allsubmissions,
                                                  $allgrades,
                                                  $assign->get_submission_plugins(),
                                                  $assign->get_feedback_plugins(),
                                                  $assign->get_course_module()->id,
                                                  $assign->get_return_action(),
                                                  $assign->get_return_params(),
                                                  true);
        }

        $o .= $assign->get_renderer()->render($history);
    }

    \mod_assign\event\grading_form_viewed::create_from_user($assign, $user)->trigger();


    return $o;
}

function fn_view_submissions($mform, $offset=0, $showattemptnumber=null, $assign, $ctx, $cm, $course, $pageparams) {
    global $DB, $CFG, $OUTPUT;

    $o = '';
    $instance = $assign->get_instance();

    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment.
        $readonly = false;
        if(! has_capability('mod/assign:grade', $ctx)){
            if(has_capability('block/fn_marking:viewreadonly',$ctx)){
                $readonly = true;
            }else{
                require_capability('mod/assign:grade', $ctx);
            }
        }

    $rownum = optional_param('rownum', 0, PARAM_INT) + $offset;
    $arruser = count_unmarked_students($course, $cm, $pageparams['show'], $pageparams['sort']);

    $useridlist = optional_param('useridlist', '', PARAM_TEXT);

    if ($useridlist) {
        $useridlist = explode(',', $useridlist);
    } else {
        $useridlist = get_grading_userid_list($assign);
    }
    $useridlist = $arruser;
    $last = false;
    $userid = (isset($useridlist[$rownum])) ? $useridlist[$rownum] : NULL;
    if ($rownum == count($useridlist) - 1) {
        $last = true;
    }
    if (!$userid) {
        return 'There is no user.';
        //throw new coding_exception('Row is out of bounds for the current grading table: ' . $rownum);
    }

    if ($pageparams['show']=='unsubmitted'){

        $unsubmitted = array();

        foreach ($useridlist as $key => $userid) {

            //$user = $DB->get_record('user', array('id' => $userid));

            if($submission = $assign->get_user_submission($userid, false)){
                if ($submission->status == 'draft'){
                    $unsubmitted[$userid] = $userid;
                }
            }else{
                $unsubmitted[$userid] = $userid;
            }
        }

        if(count($unsubmitted)>0){

            $image = "<A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\"> <IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/$cm->modname/pix/icon.gif\" " .
                    "HEIGHT=16 WIDTH=16 ALT=\"$cm->modname\"></A>";

            $o .= '<div class="unsubmitted_header">' . $image .
                                        " Assignment: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" . $assign->get_instance()->name . '</a></div>';


            $o .= '<p class="unsubmitted_msg">The following students have not submitted this assignment:</p>';

            foreach ($unsubmitted as $userid) {
            /// Check that this user hasn't submitted before.

                $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                $o .= "\n<tr>";
                $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
                $user = $DB->get_record('user',array('id'=>$userid));
                $o .= $OUTPUT->user_picture($user, array('courseid'=>$course->id, 'size'=>20));
                $o .= "</td>";
                $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
                $o .= "</tr></table>\n";

            }
        }
        else if(count($unsubmitted)==0){
                 $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
        }

    }else{

        foreach ($useridlist as $key => $userid) {

            $user = $DB->get_record('user', array('id' => $userid));
            /*
            if ($user) {
                $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
                $usersummary = new assign_user_summary($user,
                                                       $assign->get_course()->id,
                                                       $viewfullnames,
                                                       $assign->is_blind_marking(),
                                                       $assign->get_uniqueid_for_user($user->id));
                $o .= $assign->get_renderer()->render($usersummary);
            }
            */
            $submission = $assign->get_user_submission($userid, false);
            $submissiongroup = null;
            $submissiongroupmemberswhohavenotsubmitted = array();
            $teamsubmission = null;
            $notsubmitted = array();
            if ($instance->teamsubmission) {
                $teamsubmission = $assign->get_group_submission($userid, 0, false, $showattemptnumber);
                $submissiongroup = $assign->get_submission_group($userid);
                $groupid = 0;
                if ($submissiongroup) {
                    $groupid = $submissiongroup->id;
                }
                $notsubmitted = $assign->get_submission_group_members_who_have_not_submitted($groupid, false);
            }

            // Get the current grade.
            $grade = $assign->get_user_grade($userid, false, $showattemptnumber);



            // Get all the submissions (for the history view).
            list($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber) =
                fn_get_submission_history_view($submission, $grade, $user, $showattemptnumber, $assign);

            if ($grade) {
                $data = new stdClass();
                if ($grade->grade !== null && $grade->grade >= 0) {
                    $data->grade = format_float($grade->grade, 2);
                }
            } else {
                $data = new stdClass();
                $data->grade = '';
            }


            // Now show the grading form.
            if ($attemptnumber != $maxattemptnumber) {
                $o .= $assign->get_renderer()->edit_previous_feedback_warning($attemptnumber, $maxattemptnumber);
            }

            $o .= fn_render_assign_submission_history_summary(new assign_submission_history($allsubmissions, $allgrades, $attemptnumber,
                                                                              $maxattemptnumber, $assign->get_submission_plugins(),
                                                                              $assign->get_feedback_plugins(),
                                                                              $assign->get_course_module()->id,
                                                                              $assign->get_return_action(),
                                                                              $assign->get_return_params(),
                                                                              true),
                                                                              $assign->get_renderer(),
                                                                              $user,
                                                                              $assign
                                                                              );




    /*
    $allsubmissions = fn_get_all_submissions($userid, $assign);


    if (count($allsubmissions) > 1) {
        $allgrades = fn_get_all_grades($userid, $assign);
        $history = new assign_attempt_history($allsubmissions,
                                              $allgrades,
                                              $assign->get_submission_plugins(),
                                              $assign->get_feedback_plugins(),
                                              $assign->get_course_module()->id,
                                              $assign->get_return_action(),
                                              $assign->get_return_params(),
                                              true);

        $o .= $assign->get_renderer()->render($history);
    }
    */








            $msg = get_string('viewgradingformforstudent',
                              'assign',
                              array('id'=>$user->id, 'fullname'=>fullname($user)));
            fn_add_to_log_legacy($assign, 'view grading form', $msg);

        }
    }


    return $o;
}

function fn_get_submission_history($submission, $grade, $user, $showattemptnumber, $assign) {
    global $DB;

    $attemptnumber = ($submission) ? $submission->attemptnumber : 1;
    $allsubmissions = array($attemptnumber => $submission);
    $allgrades = array($attemptnumber => $grade);
    $graders = array();
    if (is_null($showattemptnumber)) {
        // If attemptnumber was not set, then we already have the most recent submission.
        $maxattemptnumber = $attemptnumber;
    } else {
        // Get the most recent submission.
        if ($maxsub = $assign->get_user_submission($user->id, false)) {
            $maxattemptnumber = $maxsub->attemptnumber;
            $allsubmissions[$maxsub->attemptnumber] = $maxsub;
        } else {
            $maxattemptnumber = 0;
        }
    }
    for ($i=1; $i<=$maxattemptnumber; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id'=>$allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }

    return array($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber);
}

function fn_get_submission_history_view($submission, $grade, $user, $showattemptnumber, $assign) {
    global $DB;

    $attemptnumber = ($submission) ? $submission->attemptnumber : 1;
    $allsubmissions = array();
    $allgrades = array();
    //$allsubmissions = array($attemptnumber => $submission);
    //$allgrades = array($attemptnumber => $grade);
    $graders = array();
    if (is_null($showattemptnumber)) {
        // If attemptnumber was not set, then we already have the most recent submission.
        $maxattemptnumber = $attemptnumber;
    } else {
        // Get the most recent submission.
        if ($maxsub = $assign->get_user_submission($user->id, false)) {
            $maxattemptnumber = $maxsub->attemptnumber;
            $allsubmissions[$maxsub->attemptnumber] = $maxsub;
        } else {
            $maxattemptnumber = 0;
        }
    }
    for ($i=0; $i<=$maxattemptnumber; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id'=>$allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }


    return array($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber);
}

function fn_add_resubmission($userid, $assign) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    $currentgrade = $assign->get_user_grade($userid, false);
    if (!$currentgrade) {
        return; // If the most recent submission is not graded, then resubmissions are not allowed.
    }
    if ($assign->reached_resubmission_limit($currentgrade->attemptnumber)) {
        return; // Already reached the resubmission limit.
    }
    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, true); // Create the submission, if it doesn't already exist.
    } else {
        $submission = $assign->get_user_submission($userid, true); // Create the submissoin, if it doesn't already exist.
    }

    // Set the submission's status to resubmission.
    $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_RESUBMISSION, array('id' => $submission->id));

    fn_add_to_log_legacy($assign,'add resubmission', get_string('addresubmissionforstudent', 'assign',
                                                     array('id'=>$user->id, 'fullname'=>fullname($user))));
}

function fn_remove_resubmission($userid, $assign) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, false);
    } else {
        $submission = $assign->get_user_submission($userid, false);
    }

    if (!$submission || $submission->status != ASSIGN_SUBMISSION_STATUS_RESUBMISSION) {
        return; // No resubmission currently open.
    }

    $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_SUBMITTED, array('id' => $submission->id));

    // Set the submission's status to resubmission.
    fn_add_to_log_legacy($assign,'remove resubmission', get_string('removeresubmissionforstudent', 'assign',
                                                        array('id'=>$user->id, 'fullname'=>fullname($user))));
}

function get_grading_userid_list($assign){
    global $CFG;

    require_once($CFG->dirroot.'/mod/assign/gradingtable.php');

    $filter = get_user_preferences('assign_filter', '');
    $table = new assign_grading_table($assign, 0, $filter, 0, false);

    $useridlist = $table->get_column_data('userid');

    return $useridlist;
}

function get_user_submission($assign, $userid, $create, $attemptnumber = null) {
    global $DB, $USER, $pageparams;

    if (!$userid) {
        $userid = $USER->id;
    }


    // If the userid is not null then use userid.
    $params = array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid, 'groupid'=>0);
    if (!is_null($attemptnumber)) {
        $params['attemptnumber'] = $attemptnumber;
    }
    $submission = $DB->get_records('assign_submission', $params, 'attemptnumber DESC', '*', 0, 1);



    if ($submission) {
        return reset($submission);
    }
    if ($create) {
        $submission = new stdClass();
        $submission->assignment   = $assign->get_instance()->id;
        $submission->userid       = $userid;
        $submission->timecreated = time();
        $submission->timemodified = $submission->timecreated;

        if ($assign->get_instance()->submissiondrafts) {
            $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        } else {
            $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        }

        $submission->attemptnumber = is_null($attemptnumber) ? 1 : $attemptnumber;

        $sid = $DB->insert_record('assign_submission', $submission);
        $submission->id = $sid;
        return $submission;
    }
    return false;
}

function get_user_grade($assign, $userid) {
    global $DB, $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    // if the userid is not null then use userid
    $grade = $DB->get_record('assign_grades', array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid));

    if ($grade) {
        return $grade;
    }
    return false;
}

function is_graded($assign, $userid) {
    $grade = get_user_grade($assign, $userid);
    if ($grade) {
        return ($grade->grade !== NULL && $grade->grade >= 0);
    }
    return false;
}

function fn_render_assign_submission_history(assign_submission_history $history, $assign_renderer) {
    global $OUTPUT, $DB;
    $historyout = '';
    for ($i=$history->maxattemptnumber; $i>0; $i--) {
        /*
        if ($i == $history->attemptnumber) {
            // Do not show the currently-selected submission in the submission history.
            if ($i != $history->maxattemptnumber) {
                $historyout .= html_writer::tag('div', get_string('attemptnumber', 'assign', $i),
                                                array('class' => 'currentsubmission'));
            }
            continue;
        }
        if (!array_key_exists($i, $history->allsubmissions)) {
            continue;
        }
        */

        $submission = $history->allsubmissions[$i];
        $grade = $history->allgrades[$i];

        $editbtn = '';
        if ($history->grading) {
            $params = array(
                'id' => $history->coursemoduleid,
                'action' => $history->returnaction,
                'showattemptnumber' => $submission->attemptnumber
            );
            $params = array_merge($params, $history->returnparams);
            $editurl = new moodle_url('/mod/assign/view.php', $params);
            $editbtn = $OUTPUT->single_button($editurl, get_string('editfeedback', 'mod_assign'), 'get');
        }

        $t = new html_table();
        $cell = new html_table_cell(get_string('attemptnumber', 'assign', $i).' '.$editbtn);//Submission # and button row
        $cell->attributes['class'] = 'historytitle';
        $cell->colspan = 2;
        $t->data[] = new html_table_row(array($cell));

        if ($submission) {
            $cell1 = get_string('submitted', 'assign');
            $cell2 = userdate($submission->timemodified);
            $t->data[] = new html_table_row(array($cell1, $cell2));
            foreach ($history->submissionplugins as $plugin) {
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !$plugin->is_empty($submission)) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginsubmission = new assign_submission_plugin_submission($plugin,
                                                                                $submission,
                                                                                assign_submission_plugin_submission::SUMMARY,
                                                                                $history->coursemoduleid,
                                                                                $history->returnaction,
                                                                                $history->returnparams);
                    $cell2 = new html_table_cell($assign_renderer->render($pluginsubmission));

                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }
            }
        }

        if ($grade) {
            // Heading 'feedback'.
            $cell = new html_table_cell(get_string('feedback', 'assign', $i));
            $cell->attributes['class'] = 'historytitle';
            $cell->colspan = 2;
            $t->data[] = new html_table_row(array($cell));

            // Grade.
            $cell1 = new html_table_cell(get_string('grade'));
            $cell2 = $grade->gradefordisplay;
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded on.
            $cell1 = new html_table_cell(get_string('gradedon', 'assign'));
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded by.
            $cell1 = new html_table_cell(get_string('gradedby', 'assign'));
            $cell2 = new html_table_cell($OUTPUT->user_picture(is_object($grade->grader) ? $grade->grader : $DB->get_record('user', array('id'=>$grade->grader))) .
                                             $OUTPUT->spacer(array('width'=>30)) . fullname($grade->grader));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Feedback from plugins.
            foreach ($history->feedbackplugins as $plugin) {
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !$plugin->is_empty($grade)) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginfeedback = new assign_feedback_plugin_feedback(
                        $plugin, $grade, assign_feedback_plugin_feedback::SUMMARY, $history->coursemoduleid,
                        $history->returnaction, $history->returnparams
                    );
                    $cell2 = new html_table_cell($assign_renderer->render($pluginfeedback));
                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }

            }

        }

        $historyout .= html_writer::table($t);
    }

    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory');
        $o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;
}

function fn_render_assign_submission_history_summary(assign_submission_history $history, $assign_renderer, $user, $assign) {
    global $OUTPUT, $DB, $CFG, $pageparams;
    $historyout = '';

    if ($user) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
        $summary = new assign_user_summary($user,
                                               $assign->get_course()->id,
                                               $viewfullnames,
                                               $assign->is_blind_marking(),
                                               $assign->get_uniqueid_for_user($user->id),
                                               get_extra_user_fields($assign->get_context()));

        //$modulename =  $assign->get_course_module()->modname;
        $gradeitem = $DB->get_record('grade_items', array('itemtype'=>'mod', 'itemmodule'=>'assign', 'iteminstance'=>$assign->get_instance()->id));




        $maxattemptnumber = isset($pageparams['maxattemptnumber']) ? $pageparams['maxattemptnumber'] : sizeof($history->allsubmissions);

        $resubstatus = '';

        //$maxattemptnumber = isset($params['maxattemptnumber']) ? $params['maxattemptnumber'] : $params['attemptnumber'];
        $resubtype = $assign->get_instance()->attemptreopenmethod;
        if ($resubtype != ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
            if (fn_reached_resubmission_limit($maxattemptnumber, $assign)) {
                $resubstatus = get_string('atmaxresubmission', 'assign');
            } else if ($resubtype == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL) {

                if ($history->allsubmissions[(sizeof($history->allsubmissions)-1)]->status == 'reopened'){
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1" checked="checked" disabled="disabled" />';
                }else{
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1"  disabled="disabled" />';
                }

            } else if ($resubtype == ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS) {
                $gradepass = $gradeitem->gradepass;
                if ($gradeitem->gradepass > 0) {
                    //$resubstatus = get_string('resubmissiononfailedgrade', 'assign', round($gradepass,1));
                    $resubstatus = get_string('attemptreopenmethod_untilpass', 'assign');
                }
            }
        }




        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)';
            }


        }else{
            $groupname = '';
        }








        $header = '<table class="headertable"><tr>';

        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            //$header .= $OUTPUT->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);

            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($summary->user, $summary->viewfullnames), null, array('target'=>'_blank', 'class'=>'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .$assign->get_instance()->name.'</a></div></td>';
            $header .= '<td align="right" style="color:white;">'.$resubstatus.'</td>';
        }
        $header .= '</tr></table>';


    }

    $t = new html_table();
    $t->attributes['class'] = 'generaltable historytable';
    $cell = new html_table_cell($header);
    $cell->attributes['class'] = 'historyheader';
    $cell->colspan = 3;
    $t->data[] = new html_table_row(array($cell));



    $submitted_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $marked_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $saved_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    $marked_icon_incomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
    // print_r($history);die;
    for ($i=$history->maxsubmissionnum; $i>=0; $i--) {
        /*
        if ($i == $history->attemptnumber) {
            // Do not show the currently-selected submission in the submission history.
            if ($i != $history->maxattemptnumber) {
                $historyout .= html_writer::tag('div', get_string('attemptnumber', 'assign', $i),
                                                array('class' => 'currentsubmission'));
            }
            continue;
        }
        if (!array_key_exists($i, $history->allsubmissions)) {
            continue;
        }
        */

        $submission = $history->allsubmissions[$i];
        $grade = $history->allgrades[$i];


        if (($i == $history->maxsubmissionnum) && (isset($grade->grade))){
            $lastsubmission_class = (($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? 'bg_green' : 'bg_orange';
        }else{
            $lastsubmission_class = '';
        }


        $editbtn = ''; /*
        if ($history->grading) {
            $params = array(
                'id' => $history->coursemoduleid,
                'action' => $history->returnaction,
                'showattemptnumber' => $submission->attemptnumber
            );
            $params = array_merge($params, $history->returnparams);
            $editurl = new moodle_url('/mod/assign/view.php', $params);
            $editbtn = $OUTPUT->single_button($editurl, get_string('editfeedback', 'mod_assign'), 'get');
        }
       */
        if ($grade) {

            $cell1 = new html_table_cell($grade->gradefordisplay);
            $cell1->rowspan = 2;
            if ($i == $history->maxsubmissionnum){
                $cell1->attributes['class'] = $lastsubmission_class;
            }


            if ($submission->status == 'draft'){
                $cell2 = new html_table_cell($saved_icon . 'Draft');
            }else{
                $cell2 = new html_table_cell($submitted_icon . get_string('submitted', 'assign'));
            }

            $cell3 = new html_table_cell(userdate($submission->timemodified));
            if ($i == $history->maxsubmissionnum){
                $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.$pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.$pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].'&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';
                $cell2->attributes['class'] = $lastsubmission_class;
                $cell3->attributes['class'] = $lastsubmission_class;
            }

            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));



            $cell1 = new html_table_cell(((($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? $marked_icon : $marked_icon_incomplete) . 'Marked');
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            if ($i == $history->maxsubmissionnum){
                $cell1->attributes['class'] = $lastsubmission_class;
                $cell2->attributes['class'] = $lastsubmission_class;
            }
            $t->data[] = new html_table_row(array($cell1, $cell2));



        }


    }

    $historyout .= html_writer::table($t);

    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory_summary');
        //$o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;
}

function fn_render_assign_submission_status(assign_submission_status $status, $assign, $user, $grade, $assign_renderer) {
    global $OUTPUT, $DB, $CFG, $pageparams;
    $o = '';


    if ($user) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
        $summary = new assign_user_summary($user,
                                               $assign->get_course()->id,
                                               $viewfullnames,
                                               $assign->is_blind_marking(),
                                               $assign->get_uniqueid_for_user($user->id),
                                               NULL);

        //$modulename =  $assign->get_course_module()->modname;
        $gradeitem = $DB->get_record('grade_items', array('itemtype'=>'mod', 'itemmodule'=>'assign', 'iteminstance'=>$assign->get_instance()->id));






        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)';
            }


        }else{
            $groupname = '';
        }








        $header = '<table class="headertable"><tr>';

        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            //$header .= $OUTPUT->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);

            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($summary->user, $summary->viewfullnames), null, array('target'=>'_blank', 'class'=>'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .$assign->get_instance()->name.'</a></div></td>';
        }
        $header .= '</tr></table>';


    }


     //echo $header;die;







    //$o .= $OUTPUT->container_start('submissionstatustable');
    //$o .= $OUTPUT->heading(get_string('submissionstatusheading', 'assign'), 3);
    $time = time();
    /*
    if ($status->allowsubmissionsfromdate &&
            $time <= $status->allowsubmissionsfromdate) {
        $o .= $OUTPUT->box_start('generalbox boxaligncenter submissionsalloweddates');
        if ($status->alwaysshowdescription) {
            $o .= get_string('allowsubmissionsfromdatesummary', 'assign', userdate($status->allowsubmissionsfromdate));
        } else {
            $o .= get_string('allowsubmissionsanddescriptionfromdatesummary', 'assign', userdate($status->allowsubmissionsfromdate));
        }
        $o .= $OUTPUT->box_end();
    }
    */
    //$o .= $OUTPUT->box_start('boxaligncenter submissionsummarytable');

    $t = new html_table();
    $t->attributes['class'] = 'generaltable historytable';
    $cell = new html_table_cell($header);
    $cell->attributes['class'] = 'historyheader';
    $cell->colspan = 3;
    $t->data[] = new html_table_row(array($cell));



    $submitted_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $marked_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $saved_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    $marked_icon_incomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
    /*
    if ($status->teamsubmissionenabled) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionteam', 'assign'));
        $group = $status->submissiongroup;
        if ($group) {
            $cell2 = new html_table_cell(format_string($group->name, false, $status->context));
        } else {
            $cell2 = new html_table_cell(get_string('defaultteam', 'assign'));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    $row = new html_table_row();
    $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
    if (!$status->teamsubmissionenabled) {
        if ($status->submission) {
            $cell2 = new html_table_cell(get_string('submissionstatus_' . $status->submission->status, 'assign'));
            $cell2->attributes = array('class'=>'submissionstatus' . $status->submission->status);
        } else {
            if (!$status->submissionsenabled) {
                $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
            } else {
                $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            }
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    } else {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
        if ($status->teamsubmission) {
            $submissionsummary = get_string('submissionstatus_' . $status->teamsubmission->status, 'assign');
            $groupid = 0;
            if ($status->submissiongroup) {
                $groupid = $status->submissiongroup->id;
            }

            $members = $status->submissiongroupmemberswhoneedtosubmit;
            $userslist = array();
            foreach ($members as $member) {
                $url = new moodle_url('/user/view.php', array('id' => $member->id, 'course'=>$status->courseid));
                if ($status->view == assign_submission_status::GRADER_VIEW && $status->blindmarking) {
                    $userslist[] = $member->alias;
                } else {
                    $userslist[] = $OUTPUT->action_link($url, fullname($member, $status->canviewfullnames));
                }
            }
            if (count($userslist) > 0) {
                $userstr = join(', ', $userslist);
                $submissionsummary .= $OUTPUT->container(get_string('userswhoneedtosubmit', 'assign', $userstr));
            }

            $cell2 = new html_table_cell($submissionsummary);
            $cell2->attributes = array('class'=>'submissionstatus' . $status->teamsubmission->status);
        } else {
            $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            if (!$status->submissionsenabled) {
                $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
            } else {
                $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            }
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // status
    if ($status->locked) {
        $row = new html_table_row();
        $cell1 = new html_table_cell();
        $cell2 = new html_table_cell(get_string('submissionslocked', 'assign'));
        $cell2->attributes = array('class'=>'submissionlocked');
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // grading status
    $row = new html_table_row();
    $cell1 = new html_table_cell(get_string('gradingstatus', 'assign'));

    if ($status->graded) {
        $cell2 = new html_table_cell(get_string('graded', 'assign'));
        $cell2->attributes = array('class'=>'submissiongraded');
    } else {
        $cell2 = new html_table_cell(get_string('notgraded', 'assign'));
        $cell2->attributes = array('class'=>'submissionnotgraded');
    }
    $row->cells = array($cell1, $cell2);
    $t->data[] = $row;


    $duedate = $status->duedate;
    if ($duedate > 0) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('duedate', 'assign'));
        $cell2 = new html_table_cell(userdate($duedate));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        if ($status->view == assign_submission_status::GRADER_VIEW) {
            if ($status->cutoffdate) {
                $row = new html_table_row();
                $cell1 = new html_table_cell(get_string('cutoffdate', 'assign'));
                $cell2 = new html_table_cell(userdate($status->cutoffdate));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }

        if ($status->extensionduedate) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('extensionduedate', 'assign'));
            $cell2 = new html_table_cell(userdate($status->extensionduedate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
            $duedate = $status->extensionduedate;
        }

        // Time remaining.
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('timeremaining', 'assign'));
        if ($duedate - $time <= 0) {
            if (!$status->submission || $status->submission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                if ($status->submissionsenabled) {
                    $cell2 = new html_table_cell(get_string('overdue', 'assign', format_time($time - $duedate)));
                    $cell2->attributes = array('class'=>'overdue');
                } else {
                    $cell2 = new html_table_cell(get_string('duedatereached', 'assign'));
                }
            } else {
                if ($status->submission->timemodified > $duedate) {
                    $cell2 = new html_table_cell(get_string('submittedlate', 'assign', format_time($status->submission->timemodified - $duedate)));
                    $cell2->attributes = array('class'=>'latesubmission');
                } else {
                    $cell2 = new html_table_cell(get_string('submittedearly', 'assign', format_time($status->submission->timemodified - $duedate)));
                    $cell2->attributes = array('class'=>'earlysubmission');
                }
            }
        } else {
            $cell2 = new html_table_cell(format_time($duedate - $time));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Show graders whether this submission is editable by students.
    if ($status->view == assign_submission_status::GRADER_VIEW) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('editingstatus', 'assign'));
        if ($status->canedit) {
            $cell2 = new html_table_cell(get_string('submissioneditable', 'assign'));
            $cell2->attributes = array('class'=>'submissioneditable');
        } else {
            $cell2 = new html_table_cell(get_string('submissionnoteditable', 'assign'));
            $cell2->attributes = array('class'=>'submissionnoteditable');
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Grading criteria preview.
    if (!empty($status->gradingcontrollerpreview)) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingmethodpreview', 'assign'));
        $cell2 = new html_table_cell($status->gradingcontrollerpreview);
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Last modified.
    $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
    if ($submission) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('timemodified', 'assign'));
        $cell2 = new html_table_cell(userdate($submission->timemodified));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        foreach ($status->submissionplugins as $plugin) {
            $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
            if ($plugin->is_enabled() &&
                $plugin->is_visible() &&
                $plugin->has_user_summary() &&
                $pluginshowsummary) {

                $row = new html_table_row();
                $cell1 = new html_table_cell($plugin->get_name());
                $pluginsubmission = new assign_submission_plugin_submission($plugin,
                                                                            $submission,
                                                                            assign_submission_plugin_submission::SUMMARY,
                                                                            $status->coursemoduleid,
                                                                            $status->returnaction,
                                                                            $status->returnparams);
                $cell2 = new html_table_cell($assign->get_renderer()->render($pluginsubmission));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }
    }
    */



        $grade->gradefordisplay = $assign->display_grade($grade->grade, false);

        $submission = $assign->get_user_submission($user->id, false);

        if ($grade) {

            $cell1 = new html_table_cell($grade->gradefordisplay);
            $cell1->rowspan = 2;



            if ($submission->status == 'draft'){
                $cell2 = new html_table_cell($saved_icon . 'Draft');
            }else{
                $cell2 = new html_table_cell($submitted_icon . get_string('submitted', 'assign'));
            }

            $cell3 = new html_table_cell(userdate($submission->timemodified));
            $lastsubmission_class = '';
            if (true){
                $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.$pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.$pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].'&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';
                $cell2->attributes['class'] = $lastsubmission_class;
                $cell3->attributes['class'] = $lastsubmission_class;
            }

            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));



            $cell1 = new html_table_cell(((($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? $marked_icon : $marked_icon_incomplete) . 'Marked');
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            if (true){
                $cell1->attributes['class'] = $lastsubmission_class;
                $cell2->attributes['class'] = $lastsubmission_class;
            }
            $t->data[] = new html_table_row(array($cell1, $cell2));



        }

    $historyout = html_writer::table($t);
    //$o .= $OUTPUT->box_end();
    /*
    // Links.
    if ($status->view == assign_submission_status::STUDENT_VIEW) {
        if ($status->canedit) {
            if (!$submission) {
                $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                                   get_string('addsubmission', 'assign'), 'get');
            } else {
                $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                                   get_string('editsubmission', 'assign'), 'get');
            }
        }

        if ($status->cansubmit) {
            $urlparams = array('id' => $status->coursemoduleid, 'action'=>'submit');
            $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                               get_string('submitassignment', 'assign'), 'get');
            $o .= $OUTPUT->box_start('boxaligncenter submithelp');
            $o .= get_string('submitassignment_help', 'assign');
            $o .= $OUTPUT->box_end();
        }
    }

    $o .= $OUTPUT->container_end();
    */


    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory_summary');
        //$o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;
}

function fn_get_all_submissions($userid, $assign) {
    global $DB, $USER;

    // If the userid is not null then use userid.
    if (!$userid) {
        $userid = $USER->id;
    }

    $params = array();

    if ($assign->get_instance()->teamsubmission) {
        $groupid = 0;
        $group = $assign->get_submission_group($userid);
        if ($group) {
            $groupid = $group->id;
        }

        // Params to get the group submissions.
        $params = array('assignment'=>$assign->get_instance()->id, 'groupid'=>$groupid, 'userid'=>0);
    } else {
        // Params to get the user submissions.
        $params = array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid);
    }

    // Return the submissions ordered by attempt.
    $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber ASC');

    return $submissions;
}

function fn_get_all_grades($userid, $assign) {
    global $DB, $USER, $PAGE;

    // If the userid is not null then use userid.
    if (!$userid) {
        $userid = $USER->id;
    }

    $params = array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid);

    $grades = $DB->get_records('assign_grades', $params, 'attemptnumber ASC');

    $gradercache = array();
    $cangrade = has_capability('mod/assign:grade', $assign->get_context());

    // Need gradingitem and gradingmanager.
    $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
    $controller = $gradingmanager->get_active_controller();

    $gradinginfo = grade_get_grades($assign->get_course()->id,
                                    'mod',
                                    'assign',
                                    $assign->get_instance()->id,
                                    $userid);

    $gradingitem = null;
    if (isset($gradinginfo->items[0])) {
        $gradingitem = $gradinginfo->items[0];
    }

    foreach ($grades as $grade) {
        // First lookup the grader info.
        if (isset($gradercache[$grade->grader])) {
            $grade->grader = $gradercache[$grade->grader];
        } else {
            // Not in cache - need to load the grader record.
            $grade->grader = $DB->get_record('user', array('id'=>$grade->grader));
            $gradercache[$grade->grader->id] = $grade->grader;
        }

        // Now get the gradefordisplay.
        if ($controller) {
            $controller->set_grade_range(make_grades_menu($assign->get_instance()->grade));
            $grade->gradefordisplay = $controller->render_grade($PAGE,
                                                                 $grade->id,
                                                                 $gradingitem,
                                                                 $grade->grade,
                                                                 $cangrade);
        } else {
            $grade->gradefordisplay = $assign->display_grade($grade->grade, false);
        }

    }

    return $grades;
}

function fn_process_add_attempt($userid, $assign) {
    require_capability('mod/assign:grade', $assign->get_context());
    require_sesskey();

    if ($assign->get_instance()->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
        return false;
    }

    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, false);
    } else {
        $submission = $assign->get_user_submission($userid, false);
    }

    if (!$submission) {
        return false;
    }

    // No more than max attempts allowed.
    if ($assign->get_instance()->maxattempts != ASSIGN_UNLIMITED_ATTEMPTS &&
        $submission->attemptnumber >= ($assign->get_instance()->maxattempts - 1)) {
        return false;
    }

    // Create the new submission record for the group/user.
    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, true, $submission->attemptnumber+1);
    } else {
        $submission = $assign->get_user_submission($userid, true, $submission->attemptnumber+1);
    }

    // Set the status of the new attempt to reopened.
    $submission->status = ASSIGN_SUBMISSION_STATUS_REOPENED;
    fn_update_submission($submission, $userid, false, $assign->get_instance()->teamsubmission, $assign);
    return true;
}

function fn_update_submission(stdClass $submission, $userid, $updatetime, $teamsubmission, $assign) {
    global $DB;

    if ($teamsubmission) {
        return $assign->update_team_submission($submission, $userid, $updatetime);
    }

    if ($updatetime) {
        $submission->timemodified = time();
    }
    $result= $DB->update_record('assign_submission', $submission);
    if ($result) {
        fn_gradebook_item_update($submission,null,$assign);
    }
    return $result;
}

function fn_gradebook_item_update($submission=null, $grade=null, $assign) {

    // Do not push grade to gradebook if blind marking is active as
    // the gradebook would reveal the students.
    if ($assign->is_blind_marking()) {
        return false;
    }
    if ($submission != null) {
        if ($submission->userid == 0) {
            // This is a group submission update.
            $team = groups_get_members($submission->groupid, 'u.id');

            foreach ($team as $member) {
                $submission->groupid = 0;
                $submission->userid = $member->id;
                $this($submission, null, $assign);
            }
            return;
        }

        $gradebookgrade = fn_convert_submission_for_gradebook($submission);

    } else {
        $gradebookgrade = $assign->convert_grade_for_gradebook($grade);
    }
    // Grading is disabled, return.
    if ($assign->grading_disabled($gradebookgrade['userid'])) {
        return false;
    }
    $assignx = clone $assign->get_instance();
    $assignx->cmidnumber = $assign->get_course_module()->id;

    return assign_grade_item_update($assignx, $gradebookgrade);
}

function fn_convert_submission_for_gradebook(stdClass $submission) {
    $gradebookgrade = array();

    $gradebookgrade['userid'] = $submission->userid;
    $gradebookgrade['usermodified'] = $submission->userid;
    $gradebookgrade['datesubmitted'] = $submission->timemodified;

    return $gradebookgrade;
}

function render_assign_attempt_history(assign_attempt_history $history) {
    $o = '';

    $submittedstr = get_string('submitted', 'assign');
    $gradestr = get_string('grade');
    $gradedonstr = get_string('gradedon', 'assign');
    $gradedbystr = get_string('gradedby', 'assign');

    // Don't show the last one because it is the current submission.
    array_pop($history->submissions);

    // Show newest to oldest.
    $history->submissions = array_reverse($history->submissions);

    if (empty($history->submissions)) {
        return '';
    }

    $containerid = 'attempthistory' . uniqid();
    $o .= $this->heading(get_string('attempthistory', 'assign'), 3);
    $o .= $this->box_start('attempthistory', $containerid);

    foreach ($history->submissions as $i => $submission) {
        $grade = null;
        foreach ($history->grades as $onegrade) {
            if ($onegrade->attemptnumber == $submission->attemptnumber) {
                $grade = $onegrade;
                break;
            }
        }

        $editbtn = '';

        if ($submission) {
            $submissionsummary = userdate($submission->timemodified);
        } else {
            $submissionsummary = get_string('nosubmission', 'assign');
        }

        $attemptsummaryparams = array('attemptnumber'=>$submission->attemptnumber+1,
                                      'submissionsummary'=>$submissionsummary);
        $o .= $this->heading(get_string('attemptheading', 'assign', $attemptsummaryparams), 4);

        $t = new html_table();

        if ($submission) {
            $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
            $cell2 = new html_table_cell(get_string('submissionstatus_' . $submission->status, 'assign'));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            foreach ($history->submissionplugins as $plugin) {
                $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                if ($plugin->is_enabled() &&
                        $plugin->is_visible() &&
                        $plugin->has_user_summary() &&
                        $pluginshowsummary) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginsubmission = new assign_submission_plugin_submission($plugin,
                                                                                $submission,
                                                                                assign_submission_plugin_submission::SUMMARY,
                                                                                $history->coursemoduleid,
                                                                                $history->returnaction,
                                                                                $history->returnparams);
                    $cell2 = new html_table_cell($this->render($pluginsubmission));

                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }
            }
        }

        if ($grade) {
            // Heading 'feedback'.
            $title = get_string('feedback', 'assign', $i);
            $title .= $this->output->spacer(array('width'=>10));
            if ($history->cangrade) {
                // Edit previous feedback.
                $returnparams = http_build_query($history->returnparams);
                $urlparams = array('id' => $history->coursemoduleid,
                               'userid'=>$grade->userid,
                               'attemptnumber'=>$grade->attemptnumber,
                               'action'=>'grade',
                               'rownum'=>0,
                               'returnaction'=>$history->returnaction,
                               'returnparams'=>$returnparams);
                $url = new moodle_url('/mod/assign/view.php', $urlparams);
                $icon = new pix_icon('gradefeedback',
                                        get_string('editattemptfeedback', 'assign', $grade->attemptnumber+1),
                                        'mod_assign');
                $title .= $this->output->action_icon($url, $icon);
            }
            $cell = new html_table_cell($title);
            $cell->attributes['class'] = 'feedbacktitle';
            $cell->colspan = 2;
            $t->data[] = new html_table_row(array($cell));

            // Grade.
            $cell1 = new html_table_cell($gradestr);
            $cell2 = $grade->gradefordisplay;
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded on.
            $cell1 = new html_table_cell($gradedonstr);
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded by.
            $cell1 = new html_table_cell($gradedbystr);
            $cell2 = new html_table_cell($this->output->user_picture($grade->grader) .
                                         $this->output->spacer(array('width'=>30)) . fullname($grade->grader));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Feedback from plugins.
            foreach ($history->feedbackplugins as $plugin) {
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !$plugin->is_empty($grade)) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginfeedback = new assign_feedback_plugin_feedback(
                        $plugin, $grade, assign_feedback_plugin_feedback::SUMMARY, $history->coursemoduleid,
                        $history->returnaction, $history->returnparams
                    );
                    $cell2 = new html_table_cell($this->render($pluginfeedback));
                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }

            }

        }

        $o .= html_writer::table($t);
    }
    $o .= $this->box_end();
    $jsparams = array($containerid);

    $this->page->requires->yui_module('moodle-mod_assign-history', 'Y.one("#' . $containerid . '").history');

    return $o;
}
/**
 * Used to output the submission & grading history for a particular assignment
 * @package mod_assign
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_history implements renderable {

    public $allsubmissions = array();
    public $allgrades = array();
    public $submissionnum = 1;
    public $maxsubmissionnum = 1;
    public $submissionplugins = array();
    public $feedbackplugins = array();
    /** @var int coursemoduleid */
    var $coursemoduleid = 0;
    /** @var string returnaction */
    var $returnaction = '';
    /** @var string returnparams */
    var $returnparams = array();

    /**
     * @param $allsubmissions
     * @param $allgrades
     * @param $submissionnum
     * @param $maxsubmissionnum
     * @param $submissionplugins
     * @param $feedbackplugins
     * @param $coursemoduleid
     * @param $returnaction
     * @param $returnparams
     */
    public function __construct($allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum, $submissionplugins,
                                $feedbackplugins, $coursemoduleid, $returnaction, $returnparams) {
        $this->allsubmissions = $allsubmissions;
        $this->allgrades = $allgrades;
        $this->submissionnum = $submissionnum;
        $this->maxsubmissionnum = $maxsubmissionnum;
        $this->submissionplugins = $submissionplugins;
        $this->feedbackplugins = $feedbackplugins;
        $this->coursemoduleid = $coursemoduleid;
        $this->returnaction = $returnaction;
        $this->returnparams = $returnparams;
    }
}

function fn_reached_resubmission_limit($submissionnum, $assign) {
    $maxresub = $assign->get_instance()->maxattempts;
    if ($maxresub == ASSIGN_UNLIMITED_ATTEMPTS) {
        return false;
    }
    return ($submissionnum >= $maxresub);
}

function _assignment_status($mod, $userid) {
    global $CFG, $DB, $USER, $SESSION;

    if(isset($SESSION->completioncache)){
        unset($SESSION->completioncache);
    }

    if ($mod->modname == 'assignment') {
        if  (!($assignment = $DB->get_record('assignment', array('id' => $mod->instance)))) {

            return false;   // Doesn't exist... wtf?
        }
        require_once ($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
        $assignmentclass = "assignment_$assignment->assignmenttype";
        $assignmentinstance = new $assignmentclass($mod->id, $assignment, $mod);

        if (!($submission = $assignmentinstance->get_submission($userid)) || empty($submission->timemodified)) {
            return false;
        }

        switch ($assignment->assignmenttype) {
            case "upload":
                if($assignment->var4){ //if var4 enable then assignment can be saved
                    if(!empty($submission->timemodified)
                            && (empty($submission->data2))
                            && (empty($submission->timemarked))){
                        return 'saved';

                    }
                    else if(!empty($submission->timemodified)
                            && ($submission->data2='submitted')
                            && empty($submission->timemarked)){
                        return 'submitted';
                    }
                    else if(!empty($submission->timemodified)
                            && ($submission->data2='submitted')
                            && ($submission->grade==-1)){
                        return 'submitted';

                    }
                }
                else if(empty($submission->timemarked)){
                    return 'submitted';
                }
                break;
            case "uploadsingle":
                if(empty($submission->timemarked)){
                     return 'submitted';
                }
                break;
            case "online":
                if(empty($submission->timemarked)){
                     return 'submitted';
                }
                break;
            case "offline":
                if(empty($submission->timemarked)){
                     return 'submitted';
                }
                break;
        }
    } else if ($mod->modname == 'assign') {
        if  (!($assignment = $DB->get_record('assign', array('id' => $mod->instance)))) {
            return false; // Doesn't exist
        }

        if (!$submission = $DB->get_records('assign_submission', array('assignment'=>$assignment->id, 'userid'=>$userid), 'attemptnumber DESC', '*', 0, 1)) {
            return false;
        }else{
            $submission = reset($submission);
        }

        $attemptnumber = $submission->attemptnumber;

        if (($submission->status == 'reopened') && ($submission->attemptnumber > 0)){
            $attemptnumber = $submission->attemptnumber - 1;
        }

        if ($submissionisgraded = $DB->get_records('assign_grades', array('assignment'=>$assignment->id, 'userid'=>$userid, 'attemptnumber' => $attemptnumber), 'attemptnumber DESC', '*', 0, 1)) {
            $submissionisgraded = reset($submissionisgraded);
            if ($submissionisgraded->grade > -1){
              if ($submission->timemodified > $submissionisgraded->timemodified) {
                    $graded = false;
                }else{
                    $graded = true;
                }
            }else{
                $graded = false;
            }
        }else {
            $graded = false;
        }


        if ($submission->status == 'draft') {
            if($graded){
                return 'submitted';
            }else{
                return 'saved';
            }
        }
        if ($submission->status == 'reopened') {
            if($graded){
                return 'submitted';
            }else{
                return 'waitinggrade';
            }
        }
        if ($submission->status == 'submitted') {
            if($graded){
                return 'submitted';
            }else{
                return 'waitinggrade';
            }
        }
    } else {
        return false;
    }
}

function _fn_add_to_log_legacy ($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
    $manager = get_log_manager();
    if (method_exists($manager, 'legacy_add_to_log')) {
        $manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    }
}

function fn_add_to_log_legacy($assign, $action = '', $info = '', $url='', $return = false) {
    global $USER;

    $fullurl = 'view.php?id=' . $assign->get_course_module()->id;
    if ($url != '') {
        $fullurl .= '&' . $url;
    }

    $args = array(
        $assign->get_course()->id,
        'assign',
        $action,
        $fullurl,
        $info,
        $assign->get_course_module()->id
    );

    if ($return) {
        // We only need to call debugging when returning a value. This is because the call to
        // call_user_func_array('add_to_log', $args) will trigger a debugging message of it's own.
        //debugging('The mod_assign add_to_log() function is now deprecated.', DEBUG_DEVELOPER);
        return $args;
    }
    call_user_func_array('_fn_add_to_log_legacy', $args);
}

function fn_get_block_config ($courseid, $blockname='fn_marking') {
    global $DB;

    $sql = "SELECT bi.id,
                   bi.configdata
              FROM {block_instances} bi
        INNER JOIN {context} ctx
                ON bi.parentcontextid = ctx.id
             WHERE bi.blockname = ?
               AND ctx.contextlevel = 50
               AND ctx.instanceid = ?";

    if ($block = $DB->get_record_sql($sql, array($blockname, $courseid))) {
        $block_config = unserialize(base64_decode($block->configdata));
        return $block_config;
    } else {
        return false;
    }
}

function fn_build_ungraded_tree ($courses, $supported_modules, $class_for_hide='', $showzeroungraded=0) {
    global $CFG, $OUTPUT;

    $text = '';

    if (is_array($courses) && !empty($courses)) {

        $modnamesplural = get_module_types_names(true);

        foreach ($courses as $course) {
            $courseicon = $OUTPUT->pix_icon('i/course', '', null, array('class' => 'gm_icon'));
            $courselink = $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $course->id . '&show=unmarked' . '&navlevel=top&mid=0';

            $total_ungraded = 0;
            $module_text = '';
            foreach ($supported_modules as $supported_module) {
                $numunmarked = count_unmarked_activities($course, 'unmarked', $supported_module);
                $total_ungraded += $numunmarked;
                $gradelink = $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $course->id . '&show=unmarked' . '&navlevel=top&mid=0&activity_type=' . $supported_module;
                $moduleicon = '<img src="' . $CFG->wwwroot . '/mod/' . $supported_module . '/pix/icon.png" class="icon" alt="">';

                if ($numunmarked) {
                    $module_text .= '<dd id="cmid' . $supported_module . '" class="module ' . $class_for_hide . '">' . "\n";
                    $module_text .= '<div class="bullet" onclick="$(\'dd#cmid' . $supported_module . ' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid' . $supported_module . ' > ul\').toggleClass(\'block_fn_marking_hide\');"></div>';
                    $module_text .= '<a href="' . $gradelink . '">' . $moduleicon . '</a>';
                    $module_text .= '<a href="' . $gradelink . '" >' . $modnamesplural[$supported_module] . '</a>' . ' <span class="fn-ungraded-num">(' . $numunmarked . ')</span>';
                    $module_text .= '</dd>';
                }
            }

            if (($total_ungraded == 0) && !$showzeroungraded) {} else {
                $course_text = '<dt id="courseid' . $course->id . '" class="cmod">
                                 <div class="toggle open" onclick="$(\'dt#courseid' . $course->id . ' > div.toggle\').toggleClass(\'open\');$(\'dt#courseid' . $course->id . ' ~ dd\').toggleClass(\'block_fn_marking_hide\');"></div>
                                 ' . $courseicon . '
                                 <a href="' . $courselink . '">' . $course->shortname . '</a> (' . $total_ungraded . ')
                            </dt>';
                $text .= '<div>'.$course_text.$module_text.'</div>';
            }
        }
    }

    return $text;
}