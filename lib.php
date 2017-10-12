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

require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');
require_once($CFG->dirroot . '/lib/grade/grade_grade.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/mod/assignment/lib.php');

function block_fn_marking_assignment_count_ungraded($assignment, $graded, $students, $show='unmarked', $extra=false, $instance) {
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

function block_fn_marking_assign_count_ungraded($assign, $graded, $students,
                                                 $show='unmarked', $extra=false, $instance, $keepseparate=1) {
    global $DB;

    $var = array(
        'unmarked' => 0,
        'marked' => 0,
        'unsubmitted' => 0,
        'saved' => 0
    );

    $studentlist = implode(',', array_keys($students));

    $instance = $DB->get_record('assign', array('id' => $assign));

    if (empty($studentlist)) {
        return $var;
    }

    if (!$keepseparate) {
        $showdraft = false;
    } else if ($instance->submissiondrafts) {
        $showdraft = true;
    } else {
        $showdraft = false;
    }
    $comentjoin = '';
    $comentwhereunmarked = '';
    $comentwhernmarked = '';

    if ($instance->grade == 0) {
        $comentjoin = "LEFT JOIN {assignfeedback_comments} f ON g.assignment = f.assignment AND g.id = f.grade";
        $comentwhereunmarked = " AND (f.commenttext = '' OR f.commenttext IS NULL)";
        $comentwhernmarked = " AND f.commenttext <> ''";
    }
    //if (($show == 'unmarked') || ($show == 'all')) {

    // Unmarked or all.
    if ($showdraft) {
        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign_submission} s
             LEFT JOIN {assign_grades} g
                    ON (s.assignment=g.assignment 
                   AND s.userid=g.userid 
                   AND s.attemptnumber = g.attemptnumber)
                       $comentjoin
                 WHERE s.assignment=$assign
                   AND (s.userid in ($studentlist))
                   AND s.status='submitted'
                   AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)
                   AND s.latest = 1
                       $comentwhereunmarked";
    } else {
        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign_submission} s
             LEFT JOIN {assign_grades} g
                    ON (s.assignment=g.assignment 
                   AND s.userid=g.userid 
                   AND s.attemptnumber = g.attemptnumber)
                       $comentjoin
                 WHERE s.assignment=$assign
                   AND (s.userid IN ($studentlist))
                   AND s.status IN ('submitted', 'draft')
                   AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)
                   AND s.latest = 1
                       $comentwhereunmarked";
    }

    $var['unmarked'] = $DB->count_records_sql($sql);

    // Marked.
    if ($instance->grade != 0) {
        $sqlunmarked = "SELECT DISTINCT s.userid
                          FROM {assign_submission} s
                     LEFT JOIN {assign_grades} g 
                            ON (s.assignment=g.assignment
                           AND s.userid=g.userid 
                           AND s.attemptnumber = g.attemptnumber)
                         WHERE s.assignment=$assign
                           AND (s.userid IN ($studentlist))
                           AND s.status='submitted'
                           AND g.grade IS NULL";

        if ($unmarkedstus = $DB->get_records_sql($sqlunmarked)) {
            $students = explode(',', $studentlist);

            foreach ($unmarkedstus as $unmarkedstu) {
                $students = array_diff($students, array($unmarkedstu->userid));
            }
            $studentlistmarked = implode(',', $students);
        }
    }
    if (empty($studentlistmarked)) {
        $var['marked'] = 0;
    } else if ($instance->grade == 0) {
        $sql = "SELECT COUNT(DISTINCT s.userid)
              FROM {assign_submission} s
         LEFT JOIN {assign_grades} g 
                ON (s.assignment=g.assignment and s.userid=g.userid
               AND s.attemptnumber = g.attemptnumber)
                   $comentjoin
             WHERE s.assignment=$assign
               AND s.userid in ($studentlistmarked)
               AND s.status IN ('submitted', 'resub', 'new')                    
                   $comentwhernmarked";
    } else {
        $sql = "SELECT COUNT(DISTINCT s.userid)
              FROM {assign_submission} s
         LEFT JOIN {assign_grades} g 
                ON (s.assignment=g.assignment and s.userid=g.userid
               AND s.attemptnumber = g.attemptnumber)
             WHERE ((s.assignment=$assign
               AND (s.userid in ($studentlistmarked))
               AND s.status IN ('submitted', 'resub')
               AND g.grade is not null  AND g.grade <> -1)
                OR (s.assignment=$assign
               AND (s.userid in ($studentlistmarked))
               AND s.status='draft'
               AND g.grade is not null
               AND g.grade <> -1
               AND g.timemodified > s.timemodified))";
    }

    $var['marked'] = $DB->count_records_sql($sql);



    // Unsubmitted.
    $sql = "SELECT COUNT(DISTINCT userid)
              FROM {assign_submission}
             WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
    $subbed = $DB->count_records_sql($sql);
    $unsubbed = abs(count($students) - $subbed);
    $var['unsubmitted'] = $unsubbed;

    // Saved.
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

        $var['saved'] = $DB->count_records_sql($sql);
    }

    return $var;
}

function block_fn_marking_quiz_count_ungraded($quizid, $graded, $students, $show='unmarked',
                                               $extra=false, $instance, $keepseparate=1) {
    global $DB;

    $var = array(
        'unmarked' => 0,
        'marked' => 0,
        'unsubmitted' => 0,
        'saved' => 0
    );

    $studentlist = implode(',', array_keys($students));

    if (empty($studentlist)) {
        return $var;
    }

    // Unmarked.
    $sqlgradablequiz = "SELECT qs.id,
                               q.qtype
                          FROM {quiz_slots} qs
                          JOIN {question} q
                            ON qs.questionid = q.id
                         WHERE qs.quizid = ?
                           AND q.qtype = 'essay'";

    if ($DB->record_exists_sql($sqlgradablequiz, array($instance->id))) {
        $sql = "SELECT COUNT(DISTINCT qa.userid)
                  FROM {quiz_attempts} qa
                 WHERE qa.quiz = ?
                   AND qa.state = 'finished'
                   AND qa.userid IN ($studentlist)
                   AND qa.sumgrades IS NULL";

        $var['unmarked'] = $DB->count_records_sql($sql, array($quizid));
    }

    // Marked.
    $sql = "SELECT COUNT(DISTINCT qa.userid)
              FROM {quiz_attempts} qa
             WHERE qa.quiz = ?
               AND qa.state = 'finished'
               AND qa.userid IN ($studentlist)
               AND qa.sumgrades >= 0";

    $var['marked'] = $DB->count_records_sql($sql, array($quizid));

    // Unsubmitted.
    $sql = "SELECT DISTINCT qa.userid
              FROM {quiz_attempts} qa
             WHERE qa.quiz = ?
               AND qa.state = 'finished'
               AND qa.userid IN ($studentlist)
               AND qa.sumgrades >= 0";

    if ($attempts = $DB->get_records_sql($sql, array($quizid))) {
        $unsubmitted = array_diff(array_keys($students), array_keys($attempts));
        $var['unsubmitted'] = count($unsubmitted);
    } else {
        $var['unsubmitted'] = count($students);
    }
    return $var;
}

function block_fn_marking_journal_count_ungraded($journalid, $graded, $students, $show='unmarked',
                                               $extra=false, $instance, $keepseparate=1) {
    global $DB;

    $var = array(
        'unmarked' => 0,
        'marked' => 0,
        'unsubmitted' => 0,
        'saved' => 0
    );

    $studentlist = implode(',', array_keys($students));

    if (empty($studentlist)) {
        return $var;
    }

    // Unmarked.
    if ($instance->grade == 0) {
        $sql = "SELECT COUNT(1) 
                  FROM {journal_entries} j 
                 WHERE j.journal = ? 
                   AND j.entrycomment IS NULL 
                   AND j.userid IN ($studentlist)";
    } else {
        $sql = "SELECT COUNT(1) 
                  FROM {journal_entries} j 
                 WHERE j.journal = ? 
                   AND j.rating IS NULL 
                   AND j.userid IN ($studentlist)";
    }
    $var['unmarked'] = $DB->count_records_sql($sql, array($journalid));

    // Marked.
    if ($instance->grade == 0) {
        $sql = "SELECT COUNT(1) 
                  FROM {journal_entries} j 
                 WHERE j.journal = ? 
                   AND j.entrycomment IS NOT NULL 
                   AND j.userid IN ($studentlist)";
    } else {
        $sql = "SELECT COUNT(1) 
                  FROM {journal_entries} j 
                 WHERE j.journal = ? 
                   AND j.rating IS NOT NULL
                   AND j.userid IN ($studentlist)";
    }
    $var['marked'] = $DB->count_records_sql($sql, array($journalid));

    // Unsubmitted.
    if ($instance->grade == 0) {
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

    if ($attempts = $DB->get_records_sql($sql, array($journalid))) {
        $unsubmitted = array_diff(array_keys($students), array_keys($attempts));
        $var['unsubmitted'] = count($unsubmitted);
    } else {
        $var['unsubmitted'] = count($students);
    }

    return $var;
}

function block_fn_marking_assign_students_ungraded($assign, $graded, $students, $show='unmarked',
                                                    $extra=false, $instance, $sort=false, $keepseparate=1) {
    global $DB, $CFG;

    $studentlist = implode(',', array_keys($students));

    $instance = $DB->get_record('assign', array('id' => $assign));

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

    $comentjoin = '';
    $comentwhereunmarked = '';
    $comentwhernmarked = '';

    if ($instance->grade == 0) {
        $comentjoin = "LEFT JOIN {assignfeedback_comments} f ON g.assignment = f.assignment AND g.id = f.grade";
        $comentwhereunmarked = " AND (f.commenttext = '' OR f.commenttext IS NULL)";
        $comentwhernmarked = " AND f.commenttext <> ''";
    }

    $subtable = 'assign_submission';

    if (($show == 'unmarked') || ($show == 'all')) {
        if ($showdraft) {
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g 
                        ON (s.assignment=g.assignment
                       AND s.userid=g.userid 
                       AND s.attemptnumber = g.attemptnumber)
                           $comentjoin
                     WHERE s.assignment=$assign
                       AND (s.userid in ($studentlist))
                       AND s.status='submitted'
                       AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)
                       AND s.latest = 1
                       $comentwhereunmarked";
        } else {
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g 
                        ON (s.assignment=g.assignment
                       AND s.userid=g.userid 
                       AND s.attemptnumber = g.attemptnumber)
                           $comentjoin
                     WHERE s.assignment=$assign
                       AND (s.userid IN ($studentlist))
                       AND s.status  IN ('submitted', 'draft')
                       AND ((g.grade is null OR g.grade = -1) OR g.timemodified < s.timemodified)
                       AND s.latest = 1
                           $comentwhereunmarked";
        }

        if ($data = $DB->get_records_sql($sql)) {
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        } else {
            return false;
        }

    } else if ($show == 'marked') {

        $students = explode(',', $studentlist);
        if ($instance->grade != 0) {
            $sql = "SELECT s.userid
                     FROM {assign_submission} s
                LEFT JOIN {assign_grades} g 
                       ON (s.assignment=g.assignment
                      AND s.userid=g.userid 
                      AND  s.attemptnumber = g.attemptnumber)
                    WHERE s.assignment=$assign
                      AND (s.userid in ($studentlist))
                      AND s.status='submitted'
                      AND (g.grade is null  OR g.grade = -1)";

            if ($unmarkedstus = $DB->get_records_sql($sql)) {

                foreach ($unmarkedstus as $unmarkedstu) {
                    $students = array_diff($students, array($unmarkedstu->userid));
                }
            }

            $studentlist = implode(',', $students);
        }

        if ($instance->grade == 0) {
            $sql = "SELECT MAX(s.id) id,
                           s.userid
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g
                        ON (s.assignment=g.assignment AND s.userid=g.userid AND s.attemptnumber = g.attemptnumber)
                     WHERE s.assignment=$assign
                       AND s.userid IN ($studentlist)
                       AND g.grade = -1
                  GROUP BY s.userid";
        } else {
            $sql = "SELECT MAX(s.id) id,
                           s.userid
                      FROM {assign_submission} s
                 LEFT JOIN {assign_grades} g
                        ON (s.assignment=g.assignment AND s.userid=g.userid AND s.attemptnumber = g.attemptnumber)
                     WHERE s.assignment=$assign
                       AND (s.userid in ($studentlist))
                       AND g.grade is not null
                       AND g.grade <> -1
                  GROUP BY s.userid";
        }
        if ($data = $DB->get_records_sql($sql)) {
            if ($sort) {
                $arrids = array();
                $drafted = array();

                foreach ($data as $value) {
                    $arrids[] = $value->id;
                }

                // CHECK DRAFT is_Graded.
                $sqldraft = "SELECT s.id,
                                    s.timemodified  submissiontime,
                                    g.timemodified  gradetime
                               FROM {assign_submission} s
                          LEFT JOIN {assign_grades} g
                                 ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                              WHERE s.assignment = $assign
                                AND s.userid IN ($studentlist)
                                AND s.status = 'draft'";

                if ($draftgrades = $DB->get_records_sql($sqldraft)) {
                    foreach ($draftgrades as $draftgrade) {
                        if (($draftgrade == null) || ($draftgrade->submissiontime >= $draftgrade->gradetime)) {
                            $drafted[] = $draftgrade->id;
                        }
                    }
                    $arrids = array_diff($arrids, $drafted);
                }

                switch ($sort) {
                    case 'lowest':
                        $sqls = "SELECT s.userid
                                   FROM {assign_submission} s
                              LEFT JOIN {assign_grades} g
                                     ON (s.assignment = g.assignment AND s.userid = g.userid AND s.attemptnumber = g.attemptnumber)
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY g.grade ASC";
                        break;

                    case 'highest':
                        $sqls = "SELECT s.userid
                                   FROM {assign_submission} s
                              LEFT JOIN {assign_grades} g
                                     ON (s.assignment = g.assignment AND s.userid = g.userid AND s.attemptnumber = g.attemptnumber)
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY g.grade DESC";
                        break;

                    case 'date':
                        $sqls = "SELECT s.userid
                                   FROM {assign_submission} s
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY s.timemodified DESC";
                        break;

                    case 'alpha':
                        $sqls = "SELECT s.userid
                                   FROM {assign_submission} s
                             INNER JOIN {user AS u
                                     ON s.userid = u.id
                                  WHERE s.id IN (" . implode(',', $arrids) . ")
                               ORDER BY u.lastname ASC";
                        break;
                }

                if ($datas = $DB->get_records_sql($sqls)) {
                    $arr = array();
                    foreach ($datas as $value) {
                        $arr[] = $value->userid;
                    }

                    return $arr;
                } else {
                    return false;
                }
            }

            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }

            return $arr;
        } else {
            return false;
        }

    } else if ($show == 'unsubmitted') {
        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
        $subbed = $DB->get_records_sql($sql);

        $unsubmitted = array_diff(array_keys($students), array_keys($subbed));
        return $unsubmitted = array_values($unsubmitted);

    } else if ($show == 'saved') {
        if (!$showdraft) {
            return false;
        }
        // CHECK DRAFT is_Graded.
        $sqldraft = "SELECT s.userid,
                            s.timemodified AS submissiontime,
                            g.timemodified AS gradetime
                       FROM {assign_submission} s
                  LEFT JOIN {assign_grades} g
                         ON (s.assignment=g.assignment and s.userid=g.userid and s.attemptnumber = g.attemptnumber)
                      WHERE s.assignment = $assign
                        AND s.userid IN ($studentlist)
                        AND s.status = 'draft'
                        AND g.grade IS NOT NULL
                        AND g.timemodified > s.timemodified";

        $studentlist = explode(',', $studentlist);

        if ($draftgrades = $DB->get_records_sql($sqldraft)) {
            foreach ($draftgrades as $draftgrade) {
                $studentlist = array_diff($studentlist, array($draftgrade->userid));
            }
        }

        $studentlist = implode(',', $studentlist);

        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='draft'";

        if ($data = $DB->get_records_sql($sql)) {
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        } else {
            return false;
        }
    } else {
        return 0;
    }
}

function block_fn_marking_assignment_oldest_ungraded($assignment) {
    global $CFG, $DB;

    $sql = 'SELECT MIN(timemodified) FROM ' . $CFG->prefix . 'assignment_submissions ' .
        'WHERE (assignment = ' . $assignment . ') AND (timemarked < timemodified) AND (timemodified > 0)';
    return $DB->get_field_sql($sql);
}

function block_fn_marking_assign_oldest_ungraded($assign) {
    global $CFG, $DB;

    $sql = "SELECT MIN(s.timemodified)
              FROM {assign_submission} s
              LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
             WHERE s.assignment=$assign AND s.status='submitted' AND g.grade is null";
    return $DB->get_field_sql($sql);
}

function block_fn_marking_forum_count_ungraded($forumid, $graded, $students, $show='unmarked') {
    global $CFG, $DB;

    $var = array(
        'unmarked' => 0,
        'marked' => 0,
        'unsubmitted' => 0,
        'saved' => 0
    );

    // Get students from forum_posts.
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

    // Unmarked.
    if (empty($graded) && !empty($fusers)) {
        $var['unmarked'] = count($fusers);
    } else if (empty($fusers)) {
        $var['unmarked'] = 0;
    } else {
        $var['unmarked'] = (count($fusers) - count($graded));
    }

    // Marked.
    $var['marked'] = count($graded);

    // Unsubmitted
    $numuns = count($students) - count($fusers);
    $var['marked'] = max(0, $numuns);

    return $var;
}

function block_fn_marking_count_unmarked_students(&$course, $mod, $info='unmarked', $sort=false) {

    global $CFG, $DB;

    $keepseparate = 1; // Default value.
    if ($blockconfig = block_fn_marking_get_block_config ($course->id)) {
        if (isset($blockconfig->keepseparate)) {
            $keepseparate = $blockconfig->keepseparate;
        }
    }

    $context = context_course::instance($course->id);

    $currentgroup = groups_get_activity_group($mod, true);
    $students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');

    // Array of functions to call for grading purposes for modules.
    $modgradesarray = block_fn_marking_supported_mods();
    unset($modgradesarray['quiz']);

    // Don't count it if you can't see it.
    $mcontext = context_module::instance($mod->id);
    if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
        return 0;
    }
    $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
    $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
    if (file_exists($libfile)) {
        require_once($libfile);
        $gradefunction = $mod->modname . "_get_user_grades";
        if (function_exists($gradefunction) &&
            isset($modgradesarray[$mod->modname])) {
            // Use the object function for fnassignments.
            if (($mod->modname == 'forum') &&
                (($instance->assessed <= 0) || !has_capability('mod/forum:rate', $mcontext))) {
                $modgrades = false;
            } else {
                $modgrades = new stdClass();
                if (!($modgrades->grades = $gradefunction($instance))) {
                    $modgrades->grades = array();
                }
            }
            if ($modgrades) {
                // Store the number of ungraded entries for this group.
                if (is_array($modgrades->grades) && is_array($students)) {
                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                    $numgraded = count($gradedarray);
                    $numstudents = count($students);
                    $ungradedfunction = 'block_fn_marking_' . $mod->modname . '_students_ungraded';
                    if (function_exists($ungradedfunction)) {
                        $extra = false;
                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance, $sort, $keepseparate);
                        return $ung;
                    } else {
                        $ung = $numstudents - $numgraded;
                    }
                }
            }
        }
    }
}

function block_fn_marking_count_unmarked_activities(&$course, $info='unmarked', $module='', $userid=0) {

    global $CFG, $DB, $sections;

    $var = array(
        'unmarked' => 0,
        'marked' => 0,
        'unsubmitted' => 0,
        'saved' => 0
    );

    $context = context_course::instance($course->id);
    $isteacheredit = has_capability('moodle/course:update', $context);
    $marker = has_capability('moodle/grade:viewall', $context);

    $includeorphaned = get_config('block_fn_marking', 'include_orphaned');

    // FIND CURRENT WEEK.
    $courseformatoptions = course_get_format($course)->get_format_options();
    $courseformat = course_get_format($course)->get_format();
    if (isset($courseformatoptions['numsections'])) {
        $coursenumsections = $courseformatoptions['numsections'];
    } else {
        if (!$coursenumsections = $DB->count_records('course_sections', array('course' => $course->id))) {
            $coursenumsections = 10; // Default section number.
        }
    }

    if ($courseformat == 'weeks') {
        $timenow = time();
        $weekdate = $course->startdate;    // This should be 0:00 Monday of that week.
        $weekdate += 7200;                 // Add two hours to avoid possible DST problems.

        $weekofseconds = 604800;
        $courseenddate = $course->startdate + ($weekofseconds * $coursenumsections);

        // Calculate the current week based on today's date and the starting date of the course.
        $currentweek = ($timenow > $course->startdate) ? (int)((($timenow - $course->startdate) / $weekofseconds) + 1) : 0;
        $currentweek = min($currentweek, $coursenumsections);

        $upto = min($currentweek, $coursenumsections);
    } else {
        $upto = $coursenumsections;
    }

    // Array of functions to call for grading purposes for modules.
    $modgradesarray = block_fn_marking_supported_mods();

    $sections = $DB->get_records('course_sections', array('course' => $course->id), 'section ASC', 'section, sequence');

    $selectedsection = array();
    for ($i = 0; $i <= $upto; $i++) {
        $selectedsection[] = $i;
    }
    if ($includeorphaned && (count($sections) > ($coursenumsections + 1))) {
        for ($i = ($coursenumsections + 1); $i < count($sections); $i++) {
            $selectedsection[] = $i;
        }
    }

    $groupstudents = false;
    if ($userid) {
        $groupstudents = block_fn_marking_mygroup_members($course->id, $userid);
    }
    if ($groupstudents === false) {
        $students = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', 'u.id');
    } else {
        $students = $groupstudents;
    }
    if (!$students) {
        return 0;
    }

    foreach ($selectedsection as $sectionnum) {
        $i = $sectionnum;
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = get_coursemodule_from_id('', $sectionmod, $course->id);
                    if (empty($mod)) {
                        continue;
                    }
                    if (!isset($modgradesarray[$mod->modname])) {
                        continue;
                    }
                    if ($module) {
                        if ($module <> $mod->modname) {
                            continue;
                        }
                    }
                    // Don't count it if you can't see it.
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
                            // Use the object function for fnassignments.
                            if (($mod->modname == 'forum') && (($instance->assessed <= 0)
                                    || !has_capability('mod/forum:rate', $mcontext))) {
                                $modgrades = false;
                            } else {
                                $modgrades = new stdClass();
                                if (!($modgrades->grades = $gradefunction($instance))) {
                                    $modgrades->grades = array();
                                }
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

                                if ($gradedsubmissions = $DB->get_records_sql($sql, array($instance->id))) {
                                    foreach ($gradedsubmissions as $gradedsubmission) {
                                        if (! $gradedsubmission->grade) {
                                            if (isset($modgrades->grades[$gradedsubmission->userid])) {
                                                unset($modgrades->grades[$gradedsubmission->userid]);
                                            }
                                        }
                                    }
                                }
                            }
                            if ($modgrades) {
                                // Store the number of ungraded entries for this group.
                                if (is_array($modgrades->grades) && is_array($students)) {
                                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                                    $numgraded = count($gradedarray);
                                    $numstudents = count($students);
                                    $ungradedfunction = 'block_fn_marking_' . $mod->modname . '_count_ungraded';
                                    if (function_exists($ungradedfunction)) {
                                        $extra = false;
                                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance);
                                    }
                                    if ($marker) {
                                        $var['unmarked'] += $ung['unmarked'];
                                        $var['marked'] += $ung['marked'];
                                        $var['unsubmitted'] += $ung['unsubmitted'];
                                        $var['saved'] += $ung['saved'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $var;
}

function block_fn_marking_count_notloggedin($course, $days) {
    $truants = block_fn_marking_get_notloggedin($course, $days);
    return count($truants);
}

function block_fn_marking_get_notloggedin($course, $days) {
    global $USER;

    // Grab context.
    $context = context_course::instance($course->id);

    $groupstudents = block_fn_marking_mygroup_members($course->id, $USER->id);

    if ($groupstudents === false) {
        // Grab current group.
        $currentgroup = groups_get_course_group($course, true);
        $students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');
    } else {
        $students = $groupstudents;
    }

    // Calculate a the before.
    $now = time();
    $lastweek = $now - (60 * 60 * 24 * $days);

    // Students who haven't logged in.
    $truants = array();

    // Iterate.
    foreach ($students as $student) {

        // Possible fields: lastaccess, lastlogin, currentlogin.
        $lastaccess = $student->lastaccess;
        if ($lastaccess < $lastweek) {
            $truants[] = $student;
        }
    }

    return $truants;
}

function block_fn_marking_get_failing($course, $percent) {
    global $DB;
    $blockconfig = block_fn_marking_get_block_config($course->id);

    $context = context_course::instance($course->id);
    $studentids = array();
    $currentgroup = groups_get_course_group($course, true);;
    $students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');

    if (!empty($blockconfig->days)) {
        $lastweek = time() - (60 * 60 * 24 * $blockconfig->days);

        $sql = "SELECT ue.id, 
                       ue.userid 
                  FROM {user_enrolments} ue 
                  JOIN {enrol} e 
                    ON ue.enrolid = e.id 
                 WHERE e.courseid = ? 
                   AND ue.status = ? 
                   AND ue.timestart > ?";
        if ($newenrollments = $DB->get_records_sql($sql, array($course->id, 0, $lastweek))) {
            foreach ($newenrollments as $newenrollment) {
                if (isset($students[$newenrollment->userid])) {
                    unset($students[$newenrollment->userid]);
                }
            }
        }
    }

    // Students array is keyed on id.
    if ($students) {
        foreach ($students as $student) {
            $studentids[] = $student->id;
        }
    }

    $allgrades = grade_get_course_grades($course->id, $studentids);
    $grades = $allgrades->grades;

    $failing = array();

    foreach ($grades as $studentid => $gradeobj) {

        // Grab grade and convert to int (NULL -> 0).
        $grade = (int) $gradeobj->grade;

        if ($grade < $percent) {
            $failing[$studentid] = $students[$studentid];
        }
    }

    return $failing;
}

function block_fn_marking_count_failing($course, $percent) {
    return count(block_fn_marking_get_failing($course, $percent));
}

function block_fn_marking_get_notsubmittedany($course, $since = 0, $count = false, $sections, $students) {
    global $DB;

    // Grab context.
    $context = context_course::instance($course->id);

    // Get current group.
    $currentgroup = groups_get_course_group($course, true);

    // Grab modgradesarry.
    $modgradesarray = block_fn_marking_supported_mods();

    if (!isset($students)) {
        $students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');
    }

    if ($since) {
        $sql = "SELECT ue.id,
                       ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON ue.enrolid = e.id
                 WHERE e.courseid = ?
                   AND ue.status = ?
                   AND ue.timestart > ?";
        if ($newenrollments = $DB->get_records_sql($sql, array($course->id, 0, $since))) {
            foreach ($newenrollments as $newenrollment) {
                if (isset($students[$newenrollment->userid])) {
                    unset($students[$newenrollment->userid]);
                }
            }
        }
    }

    for ($i = 0; $i < count($sections); $i++) {
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    $mod = get_coursemodule_from_id('', $sectionmod, $course->id);
                    if (isset($modgradesarray[$mod->modname])) {
                        // Build mod method.
                        $f = 'block_fn_marking_' . $mod->modname . '_get_notsubmittedany';
                        // Make sure function exists.
                        if (!function_exists($f)) {
                            continue;
                        }

                        // Grab list of students with submissions for this activity.
                        $studentswithsubmissions = $f($course->id, $mod->instance, $students, $since);
                        if ($studentswithsubmissions) {
                            $studentids = array_keys($students);
                            $swsids = array_keys($studentswithsubmissions);
                            foreach ($swsids as $id) {
                                unset($students[$id]);
                            }
                        }

                        // If all students have a submission, return null.
                        if (empty($students)) {
                            if ($count) {
                                return 0;
                            } else {
                                return;
                            }
                        }
                    } // Wrong activity type.
                } // Move onto next sectionmod.
            } // Section has mods in it.
        } // Should always be true?.
    } // Next section.

    if ($count) {
        return count($students);
    } else {
        return $students;
    }
}

function block_fn_marking_is_graded($userid, $assign) {
    $grade = $assign->get_user_grade($userid, false);
    if ($grade) {
        return ($grade->grade !== null && $grade->grade >= 0);
    }
    return false;
}

function block_fn_marking_get_grading_instance($userid, $grade, $gradingdisabled, $assign) {
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

function block_fn_marking_apply_grade_to_user($formdata, $userid, $attemptnumber, $assign) {
    global $USER, $CFG, $DB;

    $grade = $assign->get_user_grade($userid, true, $attemptnumber);
    $gradingdisabled = $assign->grading_disabled($userid);
    $gradinginstance = block_fn_marking_get_grading_instance($userid, $grade, $gradingdisabled, $assign);
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
    $grade->grader = $USER->id;

    $adminconfig = $assign->get_admin_config();
    $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

    $feedbackplugins = block_fn_marking_load_plugins('assignfeedback', $assign);

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

function block_fn_marking_load_plugins($subtype, $assign) {
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
                    $idx += 1;
                }
                $result[$idx] = $plugin;
            }
        }
    }
    ksort($result);
    return $result;
}

function block_fn_marking_process_outcomes($userid, $formdata, $assign) {
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
        foreach ($gradinginfo->outcomes as $index => $oldoutcome) {
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

function block_fn_marking_process_save_grade(&$mform, $assign, $context, $course, $pageparams, $gradingonly = true) {
    global $CFG;
    // Include grade form.
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    if (!$gradingonly) {
        return true;
    }
    // Need submit permission to submit an assignment.
    require_capability('mod/assign:grade', $context);
    require_sesskey();

    $rownum = required_param('rownum', PARAM_INT);
    $useridlist = optional_param('useridlist', '', PARAM_TEXT);
    $attemptnumber = optional_param('attemptnumber', -1, PARAM_INT);
    $useridlistid = optional_param('useridlistid', time(), PARAM_INT);
    $userid = optional_param('userid', 0, PARAM_INT);
    $activitytype = optional_param('activity_type', 0, PARAM_TEXT);
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
    $pageparams['activity_type']  = $activitytype;
    $pageparams['group']  = optional_param('group', 0, PARAM_INT);;
    $pageparams['participants']  = $participants;

    $formparams = array($assign, $data, $pageparams);

    $mform = new mod_assign_grading_form_fn(null, $formparams, 'post', '', array('class' => 'gradeform unresponsive'));

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
                $realgroup = $assign->get_submission_group($userid);
                if ($group) {
                    $groupid = $realgroup->id;
                }
            }
            $members = $assign->get_submission_group_members($groupid, true);
            foreach ($members as $member) {
                // User may exist in multple groups (which should put them in the default group).
                block_fn_marking_apply_grade_to_user($formdata, $member->id, $attemptnumber. $assign);
                block_fn_marking_process_outcomes($member->id, $formdata, $assign);
            }
        } else {
            block_fn_marking_apply_grade_to_user($formdata, $userid, $attemptnumber, $assign);

            block_fn_marking_process_outcomes($userid, $formdata, $assign);
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
            block_fn_marking_process_add_attempt($userid, $assign);
        }
    } else {
        return false;
    }
    return true;
}

function block_fn_marking_view_single_grade_page($mform, $offset=0, $assign, $context,
                                                  $cm, $course, $pageparams, $showattemptnumber=null) {
    global $DB, $CFG;

    $o = '';
    $instance = $assign->get_instance();

    // Include grade form.
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment.
    $readonly = false;
    if (! has_capability('mod/assign:grade', $context)) {
        if (has_capability('block/fn_marking:viewreadonly', $context)) {
            $readonly = true;
        } else {
            require_capability('mod/assign:grade', $context);
        }
    }

    $rownum = $pageparams['rownum'] + $offset;
    $useridlistid = optional_param('useridlistid', time(), PARAM_INT);
    $userid = optional_param('userid', 0, PARAM_INT);
    $attemptnumber = optional_param('attemptnumber', -1, PARAM_INT);
    $activitytype = $pageparams['activity_type'];
    $group = $pageparams['group'];
    $participants = optional_param('participants', 0, PARAM_INT);

    if ($participants) {
        $userid = $participants;

        $arruser = block_fn_marking_count_unmarked_students($course, $cm, $pageparams['show']);
        $useridlist = $arruser;
        $last = false;

        $rownum = array_search($userid, $useridlist);
        if ($rownum == count($useridlist) - 1) {
            $last = true;
        }

    } else if ($pageparams['userid']) {
        $userid = $pageparams['userid'];

        $arruser = block_fn_marking_count_unmarked_students($course, $cm, $pageparams['show']);
        $useridlist = $arruser;
        $last = false;

        $rownum = array_search($userid, $useridlist);
        if ($rownum == count($useridlist) - 1) {
            $last = true;
        }

    } else {
        $arruser = block_fn_marking_count_unmarked_students($course, $cm, $pageparams['show']);
        $useridlist = optional_param('useridlist', '', PARAM_TEXT);
        if ($useridlist) {
            $useridlist = explode(',', $useridlist);
        } else {
            $useridlist = block_fn_marking_get_grading_userid_list($assign);
        }
        $useridlist = $arruser;
        $last = false;

        // BIG ROW NUMBER FIXER.
        $numofuser = count($useridlist);
        if ($numofuser > 0) {
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

    $grade = $assign->get_user_grade($userid, false, $showattemptnumber);
    $flags = $assign->get_user_flags($userid, false);

    if ($grade) {
        $data = new stdClass();
        if ($grade->grade !== null && $grade->grade >= 0) {
            $data->grade = format_float($grade->grade, 2);
        }
    } else {
        $data = new stdClass();
    }

    block_fn_marking_get_all_submissions_fix($userid, $assign);
    $allsubmissions = block_fn_marking_get_all_submissions($userid, $assign);

    if ($attemptnumber != -1) {
        $params = array('attemptnumber' => $attemptnumber + 1,
            'totalattempts' => count($allsubmissions));
        $message = get_string('editingpreviousfeedbackwarning', 'assign', $params);
        $o .= $assign->get_renderer()->notification($message);
    }
    $maxattemptnumber = $assign->get_instance()->maxattempts;
    // Now show the grading form.
    if (!$mform) {
        $pageparams['rownum']     = $rownum;
        $pageparams['useridlist'] = $useridlist;
        $pageparams['last']       = $last;
        $pageparams['userid']     = optional_param('userid', 0, PARAM_INT);
        $pageparams['readonly']   = $readonly;
        $pageparams['attemptnumber'] = $attemptnumber;
        $pageparams['maxattemptnumber'] = $maxattemptnumber;
        $pageparams['activity_type'] = $activitytype;
        $pageparams['participants'] = $participants;

        $formparams = array($assign, $data, $pageparams);

        $mform = new mod_assign_grading_form_fn(null,
            $formparams,
            'post',
            '',
            array('class' => 'gradeform unresponsive'));
    }
    $o .= $assign->get_renderer()->render(new assign_form('gradingform', $mform));
    $version = explode('.', $CFG->version);
    $version = reset($version);

    if (count($allsubmissions) > 1 && $attemptnumber == -1) {
        $allgrades = block_fn_marking_get_all_grades($userid, $assign);

        if ($version >= 2013051405) {
            $history = new assign_attempt_history($allsubmissions,
                $allgrades,
                $assign->get_submission_plugins(),
                $assign->get_feedback_plugins(),
                $assign->get_course_module()->id,
                $assign->get_return_action(),
                $assign->get_return_params(),
                true, null, null);
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

function block_fn_marking_view_submissions($mform, $offset=0, $showattemptnumber=null, $assign, $ctx, $cm, $course, $pageparams) {
    global $DB, $CFG, $OUTPUT;

    $o = '';
    $instance = $assign->get_instance();

    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment.
    $readonly = false;
    if (! has_capability('mod/assign:grade', $ctx)) {
        if (has_capability('block/fn_marking:viewreadonly', $ctx)) {
            $readonly = true;
        } else {
            require_capability('mod/assign:grade', $ctx);
        }
    }

    $rownum = optional_param('rownum', 0, PARAM_INT) + $offset;
    $participants = optional_param('participants', '0', PARAM_INT);
    $arruser = block_fn_marking_count_unmarked_students($course, $cm, $pageparams['show'], $pageparams['sort']);

    $useridlist = optional_param('useridlist', '', PARAM_TEXT);

    if ($useridlist) {
        $useridlist = explode(',', $useridlist);
    } else {
        $useridlist = block_fn_marking_get_grading_userid_list($assign);
    }
    $useridlist = $arruser;
    if (in_array($participants, $useridlist)) {
        $useridlist = array($participants);
    }
    $last = false;
    $userid = (isset($useridlist[$rownum])) ? $useridlist[$rownum] : null;
    if ($rownum == count($useridlist) - 1) {
        $last = true;
    }
    if (!$userid) {
        return 'There is no user.';
    }

    if ($pageparams['show'] == 'unsubmitted') {

        $unsubmitted = array();

        foreach ($useridlist as $key => $userid) {

            if ($submission = $assign->get_user_submission($userid, false)) {
                if ($submission->status == 'draft') {
                    $unsubmitted[$userid] = $userid;
                }
            } else {
                $unsubmitted[$userid] = $userid;
            }
        }

        if (count($unsubmitted) > 0) {
            $url = new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
            $image = '<a href="'.$url->out().'"><img width="16" height="16" alt="'.
                $cm->modname.'" src="'.$OUTPUT->pix_url('icon', $cm->modname).'"></a>';

            $o .= '<div class="unsubmitted_header">' . $image .
                " Assignment: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" .
                $assign->get_instance()->name . '</a></div>';

            $o .= '<p class="unsubmitted_msg">The following students have not submitted this assignment:</p>';

            foreach ($unsubmitted as $userid) {
                // Check that this user hasn't submitted before.

                $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                $o .= "\n<tr>";
                $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
                $user = $DB->get_record('user', array('id' => $userid));
                $o .= $OUTPUT->user_picture($user, array('courseid' => $course->id, 'size' => 20));
                $o .= "</td>";
                $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
                $o .= "</tr></table>\n";

            }
        } else if (count($unsubmitted) == 0) {
            $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
        }
    } else {
        foreach ($useridlist as $key => $userid) {
            $user = $DB->get_record('user', array('id' => $userid));
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
            list($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber)
                = block_fn_marking_get_submission_history_view($submission, $grade, $user, $showattemptnumber, $assign);

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

            $o .= block_fn_marking_render_assign_submission_history_summary(
                new assign_submission_history($allsubmissions, $allgrades, $attemptnumber,
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

            $msg = get_string('viewgradingformforstudent',
                'assign',
                array('id' => $user->id, 'fullname' => fullname($user)));
            block_fn_marking_add_to_log_legacy($assign, 'view grading form', $msg);

        }
    }
    return $o;
}

function block_fn_marking_get_submission_history($submission, $grade, $user, $showattemptnumber, $assign) {
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
    for ($i = 1; $i <= $maxattemptnumber; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id' => $allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }

    return array($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber);
}

function block_fn_marking_get_submission_history_view($submission, $grade, $user, $showattemptnumber, $assign) {
    global $DB;

    $attemptnumber = ($submission) ? $submission->attemptnumber : 1;
    $allsubmissions = array();
    $allgrades = array();
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
    for ($i = 0; $i <= $maxattemptnumber; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id' => $allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }

    return array($allsubmissions, $allgrades, $attemptnumber, $maxattemptnumber);
}

function block_fn_marking_add_resubmission($userid, $assign) {
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

    block_fn_marking_add_to_log_legacy($assign, 'add resubmission', get_string('addresubmissionforstudent', 'assign',
        array('id' => $user->id, 'fullname' => fullname($user))));
}

function block_fn_marking_remove_resubmission($userid, $assign) {
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
    block_fn_marking_add_to_log_legacy($assign, 'remove resubmission', get_string('removeresubmissionforstudent', 'assign',
        array('id' => $user->id, 'fullname' => fullname($user))));
}

function block_fn_marking_get_grading_userid_list($assign) {
    global $CFG;

    require_once($CFG->dirroot.'/mod/assign/gradingtable.php');

    $filter = get_user_preferences('assign_filter', '');
    $table = new assign_grading_table($assign, 0, $filter, 0, false);

    $useridlist = $table->get_column_data('userid');

    return $useridlist;
}

function block_fn_marking_get_user_submission($assign, $userid, $create, $attemptnumber = null) {
    global $DB, $USER, $pageparams;

    if (!$userid) {
        $userid = $USER->id;
    }

    // If the userid is not null then use userid.
    $params = array('assignment' => $assign->get_instance()->id, 'userid' => $userid, 'groupid' => 0);
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

function block_fn_marking_get_user_grade($assign, $userid) {
    global $DB, $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $grade = $DB->get_record('assign_grades', array('assignment' => $assign->get_instance()->id, 'userid' => $userid));

    if ($grade) {
        return $grade;
    }
    return false;
}

function block_fn_marking_is_graded_($assign, $userid) {
    $grade = block_fn_marking_get_user_grade($assign, $userid);
    if ($grade) {
        return ($grade->grade !== null && $grade->grade >= 0);
    }
    return false;
}

function block_fn_marking_render_assign_submission_history(assign_submission_history $history, $assignrenderer) {
    global $OUTPUT, $DB;
    $historyout = '';
    for ($i = $history->maxattemptnumber; $i > 0; $i--) {
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
        $cell = new html_table_cell(get_string('attemptnumber', 'assign', $i).' '.$editbtn);
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
                    $cell2 = new html_table_cell($assignrenderer->render($pluginsubmission));

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
            $cell2 = new html_table_cell($OUTPUT->user_picture(
                    is_object($grade->grader) ? $grade->grader : $DB->get_record('user', array('id' => $grade->grader))) .
                $OUTPUT->spacer(array('width' => 30)) . fullname($grade->grader));
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
                    $cell2 = new html_table_cell($assignrenderer->render($pluginfeedback));
                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }

            }

        }

        $historyout .= html_writer::table($t);
    }

    $o = '';
    if ($historyout) {
        $o .= $assignrenderer->box_start('generalbox submissionhistory');
        $o .= $assignrenderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assignrenderer->box_end();
    }

    return $o;
}

function block_fn_marking_render_assign_submission_history_summary(assign_submission_history $history,
                                                                    $assignrenderer, $user, $assign) {
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

        $gradeitem = $DB->get_record('grade_items', array('itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->get_instance()->id));

        $maxattemptnumber = isset(
            $pageparams['maxattemptnumber']) ? $pageparams['maxattemptnumber'] : count($history->allsubmissions);

        $resubstatus = '';

        $resubtype = $assign->get_instance()->attemptreopenmethod;
        if ($resubtype != ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
            if (block_fn_marking_reached_resubmission_limit($maxattemptnumber, $assign)) {
                $resubstatus = get_string('atmaxresubmission', 'block_fn_marking');
            } else if ($resubtype == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL) {

                if ($history->allsubmissions[(count($history->allsubmissions) - 1)]->status == 'reopened') {
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1"
                        checked="checked" disabled="disabled" />';
                } else {
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1"
                        disabled="disabled" />';
                }

            } else if ($resubtype == ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS) {
                $gradepass = $gradeitem->gradepass;
                if ($gradeitem->gradepass > 0) {
                    $resubstatus = get_string('attemptreopenmethod_untilpass', 'assign');
                }
            }
        }

        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)) {
                $groupname = ' ('.$submissiongroup->name.')';
            } else {
                $groupname = ' (Default group)';
            }
        } else {
            $groupname = '';
        }

        $header = '<table class="headertable"><tr>';

        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            $urlparams = array('id' => $summary->user->id, 'course' => $summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);

            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($summary->user,
                    $summary->viewfullnames),
                    null, array('target' => '_blank', 'class' => 'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link"
                title="Assignment" href="'.
                $CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .
                $assign->get_instance()->name.'</a></div></td>';
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

    $submittedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $markedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $savedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    if ($gradeitem->gradepass > 0) {
        $markediconincomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.
            $CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
    } else {
        $markediconincomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.
            $CFG->wwwroot.'/blocks/fn_marking/pix/graded.gif" valign="absmiddle"> ';
    }

    for ($i = $history->maxsubmissionnum; $i >= 0; $i--) {

        $submission = $history->allsubmissions[$i];
        $grade = $history->allgrades[$i];

        if (($i == $history->maxsubmissionnum) && (isset($grade->grade))) {
            if ($gradeitem->gradepass > 0) {
                $lastsubmissionclass = ($grade->grade >= $gradeitem->gradepass) ? 'bg_green' : 'bg_orange';
            } else {
                $lastsubmissionclass = 'bg_white';
            }
        } else {
            $lastsubmissionclass = '';
        }

        if ($grade) {
            if ($grade->grade == -1) {
                $cell1 = new html_table_cell(get_string('nograde', 'block_fn_marking'));
            } else {
                $cell1 = new html_table_cell($grade->gradefordisplay);
            }

            $cell1->rowspan = 2;
            if ($i == $history->maxsubmissionnum) {
                $cell1->attributes['class'] = $lastsubmissionclass;
            }

            if ($submission->status == 'draft') {
                $cell2 = new html_table_cell($savedicon . 'Draft');
            } else {
                $cell2 = new html_table_cell($submittedicon . get_string('submitted', 'assign'));
            }

            $cell3 = new html_table_cell(userdate($submission->timemodified));
            if ($i == $history->maxsubmissionnum) {
                $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.
                                $pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.
                                $pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].
                                '&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.
                                $CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';

                $cell2->attributes['class'] = $lastsubmissionclass;
                $cell3->attributes['class'] = $lastsubmissionclass;
            }

            if ($grade->grade == -1) {
                $cell1->attributes['class'] = 'bg_grey';
                $cell2->attributes['class'] = 'bg_grey';
                $cell3->attributes['class'] = 'bg_grey';
            }
            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));

            if ($grade->grade == -1) {
                $cell1 = new html_table_cell('<img width="16" height="16" border="0" alt="Assignment" src="'.
                    $CFG->wwwroot.'/blocks/fn_marking/pix/graded.gif" valign="absmiddle"> Marked');
            } else {
                $cell1 = new html_table_cell(((($gradeitem->gradepass > 0)
                        && ($grade->grade >= $gradeitem->gradepass)) ? $markedicon : $markediconincomplete) . 'Marked');
            }
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            if ($grade->grade == -1) {
                $cell1->attributes['class'] = 'bg_grey';
                $cell2->attributes['class'] = 'bg_grey';
            } elseif ($i == $history->maxsubmissionnum) {
                $cell1->attributes['class'] = $lastsubmissionclass;
                $cell2->attributes['class'] = $lastsubmissionclass;
            }
            $t->data[] = new html_table_row(array($cell1, $cell2));

        }

    }

    $historyout .= html_writer::table($t);

    $o = '';
    if ($historyout) {
        $o .= $assignrenderer->box_start('generalbox submissionhistory_summary');
        $o .= $historyout;
        $o .= $assignrenderer->box_end();
    }

    return $o;
}

function block_fn_marking_render_assign_submission_status(assign_submission_status $status, $assign, $user,
                                                           $grade, $assignrenderer) {
    global $OUTPUT, $DB, $CFG, $pageparams;
    $o = '';

    if ($user) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
        $summary = new assign_user_summary($user,
            $assign->get_course()->id,
            $viewfullnames,
            $assign->is_blind_marking(),
            $assign->get_uniqueid_for_user($user->id),
            null);

        $gradeitem = $DB->get_record('grade_items', array('itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->get_instance()->id));

        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)) {
                $groupname = ' ('.$submissiongroup->name.')';
            } else {
                $groupname = ' (Default group)';
            }
        } else {
            $groupname = '';
        }

        $header = '<table class="headertable"><tr>';

        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            $urlparams = array('id' => $summary->user->id, 'course' => $summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);

            $header .= '<td><div style="color:white;">'.
                $OUTPUT->action_link($url, fullname($summary->user, $summary->viewfullnames),
                    null, array('target' => '_blank', 'class' => 'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link"
                title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .
                $assign->get_instance()->name.'</a></div></td>';
        }
        $header .= '</tr></table>';
    }

    $time = time();

    $t = new html_table();
    $t->attributes['class'] = 'generaltable historytable';
    $cell = new html_table_cell($header);
    $cell->attributes['class'] = 'historyheader';
    $cell->colspan = 3;
    $t->data[] = new html_table_row(array($cell));

    $submittedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $markedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $savedicon = '<img width="16" height="16" border="0" alt="Assignment" src="'.
        $CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    if ($gradeitem->gradepass > 0) {
        $markediconincomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.
            $CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
    } else {
        $markediconincomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.
            $CFG->wwwroot.'/blocks/fn_marking/pix/graded.gif" valign="absmiddle"> ';
    }

    $grade->gradefordisplay = $assign->display_grade($grade->grade, false);

    $submission = $assign->get_user_submission($user->id, false);

    if ($grade) {

        $cell1 = new html_table_cell($grade->gradefordisplay);
        $cell1->rowspan = 2;

        if ($submission->status == 'draft') {
            $cell2 = new html_table_cell($savedicon . 'Draft');
        } else {
            $cell2 = new html_table_cell($submittedicon . get_string('submitted', 'assign'));
        }

        $cell3 = new html_table_cell(userdate($submission->timemodified));
        $lastsubmissionclass = '';
        if (true) {
            $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.
                $pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.
                $pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].
                '&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.
                $CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';
            $cell2->attributes['class'] = $lastsubmissionclass;
            $cell3->attributes['class'] = $lastsubmissionclass;
        }

        $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));

        $cell1 = new html_table_cell(((($gradeitem->gradepass > 0)
                && ($grade->grade >= $gradeitem->gradepass)) ? $markedicon : $markediconincomplete) . 'Marked');
        $cell2 = new html_table_cell(userdate($grade->timemodified));
        if (true) {
            $cell1->attributes['class'] = $lastsubmissionclass;
            $cell2->attributes['class'] = $lastsubmissionclass;
        }
        $t->data[] = new html_table_row(array($cell1, $cell2));

    }

    $historyout = html_writer::table($t);

    $o = '';
    if ($historyout) {
        $o .= $assignrenderer->box_start('generalbox submissionhistory_summary');
        $o .= $historyout;
        $o .= $assignrenderer->box_end();
    }

    return $o;
}

function block_fn_marking_get_all_submissions_fix($userid, $assign) {
    global $DB, $USER;

    // If the userid is not null then use userid.
    if (!$userid) {
        $userid = $USER->id;
    }

    $params = array();

    $params = array('assignment' => $assign->get_instance()->id, 'userid' => $userid, 'status' => 'reopened');

    if ($submissions = $DB->get_records('assign_submission', $params, 'attemptnumber DESC')) {
        array_shift($submissions);
        if ($submissions) {
            foreach ($submissions as $submission) {
                if ($submission->status == 'reopened') {
                    $rec = new stdClass();
                    $rec->id = $submission->id;
                    $rec->status = 'submitted';
                    $DB->update_record('assign_submission', $rec);
                }
            }
        }
    }
    return true;
}
function block_fn_marking_get_all_submissions($userid, $assign) {
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
        $params = array('assignment' => $assign->get_instance()->id, 'groupid' => $groupid, 'userid' => 0);
    } else {
        // Params to get the user submissions.
        $params = array('assignment' => $assign->get_instance()->id, 'userid' => $userid);
    }

    // Return the submissions ordered by attempt.
    $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber ASC');

    return $submissions;
}

function block_fn_marking_get_all_grades($userid, $assign) {
    global $DB, $USER, $PAGE;

    // If the userid is not null then use userid.
    if (!$userid) {
        $userid = $USER->id;
    }

    $params = array('assignment' => $assign->get_instance()->id, 'userid' => $userid);

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
            $grade->grader = $DB->get_record('user', array('id' => $grade->grader));
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

function block_fn_marking_process_add_attempt($userid, $assign) {
    require_capability('mod/assign:grade', $assign->get_context());
    require_sesskey();

    if ($assign->get_instance()->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
        return false;
    }

    if ($assign->get_instance()->teamsubmission) {
        $oldsubmission = $assign->get_group_submission($userid, 0, false);
    } else {
        $oldsubmission = $assign->get_user_submission($userid, false);
    }

    if ($oldsubmission->status == ASSIGN_SUBMISSION_STATUS_REOPENED) {
        return true;
    }
    if (!$oldsubmission) {
        return false;
    }

    // No more than max attempts allowed.
    if ($assign->get_instance()->maxattempts != ASSIGN_UNLIMITED_ATTEMPTS &&
        $oldsubmission->attemptnumber >= ($assign->get_instance()->maxattempts - 1)) {
        return false;
    }

    // Create the new submission record for the group/user.
    if ($assign->get_instance()->teamsubmission) {
        $newsubmission = $assign->get_group_submission($userid, 0, true, $oldsubmission->attemptnumber + 1);
    } else {
        $newsubmission = $assign->get_user_submission($userid, true, $oldsubmission->attemptnumber + 1);
    }

    // Set the status of the new attempt to reopened.
    $newsubmission->status = ASSIGN_SUBMISSION_STATUS_REOPENED;

    // Give each submission plugin a chance to process the add_attempt.
    $plugins = $assign->get_submission_plugins();
    foreach ($plugins as $plugin) {
        if ($plugin->is_enabled() && $plugin->is_visible()) {
            $plugin->add_attempt($oldsubmission, $newsubmission);
        }
    }

    block_fn_marking_update_submission($newsubmission, $userid, false, $assign->get_instance()->teamsubmission, $assign);
    return true;
}

function block_fn_marking_update_submission(stdClass $submission, $userid, $updatetime, $teamsubmission, $assign) {
    global $DB;

    if ($teamsubmission) {
        return $assign->update_team_submission($submission, $userid, $updatetime);
    }

    if ($updatetime) {
        $submission->timemodified = time();
    }
    $result = $DB->update_record('assign_submission', $submission);
    if ($result) {
        block_fn_marking_gradebook_item_update($submission, null, $assign);
    }
    return $result;
}

function block_fn_marking_gradebook_item_update($submission=null, $grade=null, $assign) {

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

        $gradebookgrade = block_fn_marking_convert_submission_for_gradebook($submission);

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

function block_fn_marking_convert_submission_for_gradebook(stdClass $submission) {
    $gradebookgrade = array();

    $gradebookgrade['userid'] = $submission->userid;
    $gradebookgrade['usermodified'] = $submission->userid;
    $gradebookgrade['datesubmitted'] = $submission->timemodified;

    return $gradebookgrade;
}

function block_fn_marking_render_assign_attempt_history(assign_attempt_history $history) {
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

        $attemptsummaryparams = array('attemptnumber' => $submission->attemptnumber + 1,
            'submissionsummary' => $submissionsummary);
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
            $title .= $this->output->spacer(array('width' => 10));
            if ($history->cangrade) {
                // Edit previous feedback.
                $returnparams = http_build_query($history->returnparams);
                $urlparams = array('id' => $history->coursemoduleid,
                    'userid' => $grade->userid,
                    'attemptnumber' => $grade->attemptnumber,
                    'action' => 'grade',
                    'rownum' => 0,
                    'returnaction' => $history->returnaction,
                    'returnparams' => $returnparams);
                $url = new moodle_url('/mod/assign/view.php', $urlparams);
                $icon = new pix_icon('gradefeedback',
                    get_string('editattemptfeedback', 'assign', $grade->attemptnumber + 1),
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
                $this->output->spacer(array('width' => 30)) . fullname($grade->grader));
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

class assign_submission_history implements renderable {

    public $allsubmissions = array();
    public $allgrades = array();
    public $submissionnum = 1;
    public $maxsubmissionnum = 1;
    public $submissionplugins = array();
    public $feedbackplugins = array();
    public $coursemoduleid = 0;
    public $returnaction = '';
    public $returnparams = array();

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

function block_fn_marking_reached_resubmission_limit($submissionnum, $assign) {
    $maxresub = $assign->get_instance()->maxattempts;
    if ($maxresub == ASSIGN_UNLIMITED_ATTEMPTS) {
        return false;
    }
    return ($submissionnum >= $maxresub);
}

function block_fn_marking_assignment_status($mod, $userid) {
    global $CFG, $DB, $USER, $SESSION;

    if (isset($SESSION->completioncache)) {
        unset($SESSION->completioncache);
    }

    if ($mod->modname == 'assignment') {
        if (!($assignment = $DB->get_record('assignment', array('id' => $mod->instance)))) {
            return false;
        }
        require_once($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
        $assignmentclass = "assignment_$assignment->assignmenttype";
        $assignmentinstance = new $assignmentclass($mod->id, $assignment, $mod);

        if (!($submission = $assignmentinstance->get_submission($userid)) || empty($submission->timemodified)) {
            return false;
        }

        switch ($assignment->assignmenttype) {
            case "upload":
                if ($assignment->var4) {
                    if (!empty($submission->timemodified)
                        && (empty($submission->data2))
                        && (empty($submission->timemarked))) {
                        return 'saved';

                    } else if (!empty($submission->timemodified)
                        && ($submission->data2 = 'submitted')
                        && empty($submission->timemarked)) {
                        return 'submitted';
                    } else if (!empty($submission->timemodified)
                        && ($submission->data2 = 'submitted')
                        && ($submission->grade == -1)) {
                        return 'submitted';
                    }
                } else if (empty($submission->timemarked)) {
                    return 'submitted';
                }
                break;
            case "uploadsingle":
                if (empty($submission->timemarked)) {
                    return 'submitted';
                }
                break;
            case "online":
                if (empty($submission->timemarked)) {
                    return 'submitted';
                }
                break;
            case "offline":
                if (empty($submission->timemarked)) {
                    return 'submitted';
                }
                break;
        }
    } else if ($mod->modname == 'assign') {
        if (!($assignment = $DB->get_record('assign', array('id' => $mod->instance)))) {
            return false;
        }

        if (!$submission = $DB->get_records('assign_submission', array('assignment' => $assignment->id,
            'userid' => $userid), 'attemptnumber DESC', '*', 0, 1)) {
            return false;
        } else {
            $submission = reset($submission);
        }

        $attemptnumber = $submission->attemptnumber;

        if (($submission->status == 'reopened') && ($submission->attemptnumber > 0)) {
            $attemptnumber = $submission->attemptnumber - 1;
        }

        if ($submissionisgraded = $DB->get_records('assign_grades', array('assignment' => $assignment->id, 'userid' => $userid,
            'attemptnumber' => $attemptnumber), 'attemptnumber DESC', '*', 0, 1)) {
            $submissionisgraded = reset($submissionisgraded);
            if ($submissionisgraded->grade > -1) {
                if (($submission->timemodified > $submissionisgraded->timemodified)
                    || ($submission->attemptnumber > $submissionisgraded->attemptnumber)) {
                    $graded = false;
                } else {
                    $graded = true;
                }
            } else {
                $graded = false;
            }
        } else {
            $graded = false;
        }

        if ($submission->status == 'draft') {
            if ($graded) {
                return 'submitted';
            } else {
                return 'saved';
            }
        }
        if ($submission->status == 'reopened') {
            return 'submitted';
        }
        if ($submission->status == 'submitted') {
            if ($graded) {
                return 'submitted';
            } else {
                return 'waitinggrade';
            }
        }
    } else {
        return false;
    }
}

function block_fn_marking_add_to_log_legacy_ ($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
    $manager = get_log_manager();
    if (method_exists($manager, 'legacy_add_to_log')) {
        $manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    }
}

function block_fn_marking_add_to_log_legacy($assign, $action = '', $info = '', $url='', $return = false) {
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
        return $args;
    }
    call_user_func_array('block_fn_marking_add_to_log_legacy_', $args);
}

function block_fn_marking_get_block_config ($courseid, $blockname='fn_marking') {
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
        $blockconfig = unserialize(base64_decode($block->configdata));
        return $blockconfig;
    } else {
        return false;
    }
}

function block_fn_marking_build_ungraded_tree ($courses, $supportedmodules, $classforhide='', $showzeroungraded=0, $maxcourse=10) {
    global $CFG, $DB, $OUTPUT, $USER;

    $refreshmodefrontpage = get_config('block_fn_marking', 'refreshmodefrontpage');

    $text = '';
    $counter = 0;
    $courseitems = array();
    if (is_array($courses) && !empty($courses)) {

        $modnamesplural = get_module_types_names(true);

        foreach ($courses as $course) {
            if (($counter >= $maxcourse) && ($refreshmodefrontpage != 'manual')) {
                continue;
            }
            $isingroup = block_fn_marking_isinagroup($course->id, $USER->id);
            if ($isingroup) {
                $userid = $USER->id;
            } else {
                $userid = 0;
            }

            $courselink = $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                $course->id . '&show=unmarked' . '&navlevel=top&mid=0';

            $totalungraded = 0;
            $moduletext = '';

            foreach ($supportedmodules as $supportedmodule) {
                if ($refreshmodefrontpage == 'pageload') {
                    $summary = block_fn_marking_count_unmarked_activities($course, 'unmarked', $supportedmodule, $USER->id);
                    $numunmarked = $summary['unmarked'];
                    $nummarked = $summary['marked'];
                    $numunsubmitted = $summary['unsubmitted'];
                    $numsaved = $summary['saved'];
                } else if ($refreshmodefrontpage == 'manual') {
                    if ($modcache = $DB->get_record('block_fn_marking_mod_cache',
                        array('courseid' => $course->id, 'modname' => $supportedmodule, 'userid' => $userid, 'expired' => 0)
                    )) {
                        $numunmarked = $modcache->unmarked;
                    } else {
                        $numunmarked = 0;
                    }
                }
                $totalungraded += $numunmarked;
                $gradelink = $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                    $course->id . '&show=unmarked' . '&navlevel=top&mid=0&activity_type=' . $supportedmodule;
                $moduleicon = '<img src="' . $OUTPUT->pix_url('icon', $supportedmodule) . '" class="icon" alt="">';

                if ($numunmarked) {
                    $moduletext .= '<dd id="cmid' . $supportedmodule . '" class="module ' . $classforhide . '">' . "\n";
                    $moduletext .= '<div class="bullet" onclick="$(\'dd#cmid' . $supportedmodule .
                        ' > div.toggle\').toggleClass(\'open\');$(\'dd#cmid' .
                        $supportedmodule . ' > ul\').toggleClass(\'block_fn_marking_hide\');"></div>';
                    $moduletext .= '<a href="' . $gradelink . '">' . $moduleicon . '</a>';
                    $moduletext .= '<a href="' . $gradelink . '" >' . $modnamesplural[$supportedmodule] . '</a>' .
                        ' <span class="fn-ungraded-num">(' . $numunmarked . ')</span>';
                    $moduletext .= '</dd>';
                }
            }

            if (($totalungraded == 0) && !$showzeroungraded) {
                $done;
            } else {
                if ($totalungraded == 0) {
                    $coursetext = '<dt id="courseid' . $course->id . '" class="cmod">
                                 <div class="togglezero"></div> 
                                 <a href="' . $courselink . '">' . $course->shortname . '</a> (' . $totalungraded . ')
                            </dt>';
                } else {
                    $coursetext = '<dt id="courseid' . $course->id . '" class="cmod">
                                 <div class="toggle open" onclick="$(\'dt#courseid' . $course->id .
                        ' > div.toggle\').toggleClass(\'open\');$(\'dt#courseid' .
                        $course->id . ' ~ dd\').toggleClass(\'block_fn_marking_hide\');"></div> 
                                 <a href="' . $courselink . '">' . $course->shortname . '</a> (' . $totalungraded . ')
                            </dt>';
                }
                $counter++;
                $courseitems[] = array(
                    'ungraded' => $totalungraded,
                    'item' => '<div>'.$coursetext.$moduletext.'</div>'
                );
            }
        }
        if ($courseitems) {
            usort($courseitems, function ($b, $a) {
                return $a['ungraded'] - $b['ungraded'];
            });
            foreach ($courseitems as $index => $courseitem) {
                $text .= $courseitem['item'];
            }
        }
    }

    if (($counter >= $maxcourse) && ($refreshmodefrontpage != 'manual')) {
        $text .= "<div class='fn-admin-warning' >".get_string('morethan10', 'block_fn_marking')."</div>";
    }

    return $text;
}

function  block_fn_marking_get_course_category_tree($id = 0, $depth = 0) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/coursecatlib.php');

    $viewhiddencats = has_capability('moodle/category:viewhiddencategories', context_system::instance());
    $categories = block_fn_marking_get_child_categories($id);
    $categoryids = array();
    foreach ($categories as $key => &$category) {
        if (!$category->visible && !$viewhiddencats) {
            unset($categories[$key]);
            continue;
        }
        $categoryids[$category->id] = $category;
        if (empty($CFG->maxcategorydepth) || $depth <= $CFG->maxcategorydepth) {
            list($category->categories, $subcategories) = block_fn_marking_get_course_category_tree_($category->id, $depth + 1);

            foreach ($subcategories as $subid => $subcat) {
                $categoryids[$subid] = $subcat;
            }
            $category->courses = array();
        }
    }

    if ($depth > 0) {
        // This is a recursive call so return the required array.
        return array($categories, $categoryids);
    }

    if (empty($categoryids)) {
        // No categories available (probably all hidden).
        return array();
    }

    // The depth is 0 this function has just been called so we can finish it off.
    $ccselect = ", " . context_helper::get_preload_record_columns_sql('ctx');
    $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = ".CONTEXT_COURSE.")";

    list($catsql, $catparams) = $DB->get_in_or_equal(array_keys($categoryids));
    $sql = "SELECT
            c.id,c.sortorder,c.visible,c.fullname,c.shortname,c.summary,c.category
            $ccselect
            FROM {course} c
            $ccjoin
            WHERE c.category $catsql ORDER BY c.sortorder ASC";
    if ($courses = $DB->get_records_sql($sql, $catparams)) {
        // Loop throught them.
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            context_helper::preload_from_record($course);
            if (!empty($course->visible)
                || has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
                $categoryids[$course->category]->courses[$course->id] = $course;
            }
        }
    }
    return $categories;
}

function  block_fn_marking_get_child_categories($parentid) {
    global $DB;

    $rv = array();
    $sql = context_helper::get_preload_record_columns_sql('ctx');
    $records = $DB->get_records_sql("SELECT c.*, $sql FROM {course_categories} c ".
        "JOIN {context} ctx on ctx.instanceid = c.id AND ctx.contextlevel = ? WHERE c.parent = ? ORDER BY c.sortorder",
        array(CONTEXT_COURSECAT, $parentid));
    foreach ($records as $category) {
        context_helper::preload_from_record($category);
        if (!$category->visible
            && !has_capability('moodle/category:viewhiddencategories', context_coursecat::instance($category->id))) {
            continue;
        }
        $rv[] = $category;
    }
    return $rv;
}

function  block_fn_marking_category_tree_form($structures, $categoryids='', $courseids='') {
    if ($categoryids == '0') {
        $rootcategorychecked = 'checked="checked"';
    } else {
        if ($categoryids || $courseids) {
            $rootcategorychecked = '';
        } else {
            $rootcategorychecked = 'checked="checked"';
        }
    }

    $categoryids = explode(',', $categoryids);
    $courseids = explode(',', $courseids);

    $content = '<ul id="course-category-tree" class="course-category-tree">
               <li>
               <input id="category_0" class="_checkbox" type="checkbox" '.$rootcategorychecked.' name="category_0" value="0">
               <span class="ned-form-course-category">'.get_string('allcategories', 'block_fn_marking').'</span>';
    $content .= '<ul>';
    foreach ($structures as $structure) {
        $content .= '<li>';
        if (in_array($structure->id, $categoryids)) {
            $content .= block_fn_marking_checkbox_checked('category_'.$structure->id, 'category_'.$structure->id,
                    '_checkbox', $structure->id) . ' <span class="ned-form-course-category">'. $structure->name . '</span>';
        } else {
            $content .= block_fn_marking_checkbox('category_'.$structure->id, 'category_'.$structure->id,
                    '_checkbox', $structure->id) . ' <span class="ned-form-course-category">'. $structure->name . '</span>';
        }

        if ($structure->courses) {
            $content .= '<ul>';
            foreach ($structure->courses as $course) {
                if (in_array($course->id, $courseids)) {
                    $content .= html_writer::tag('li',  block_fn_marking_checkbox_checked('course_'.$course->id,
                            'course_'.$course->id, '_checkbox', $course->id) . ' <span class="ned-form-course">'.
                        $course->fullname.'</span>');
                } else {
                    $content .= html_writer::tag('li',  block_fn_marking_checkbox('course_'.$course->id,
                            'course_'.$course->id, '_checkbox', $course->id) . ' <span class="ned-form-course">'.
                        $course->fullname.'</span>');
                }
            }
            $content .= '</ul>';
        }
        $content .= block_fn_marking_sub_category_tree_form($structure, $categoryids, $courseids);
        $content .= '</li>';
    }
    $content .= '</ul>';
    $content .= '</il>';
    $content .= '</ul>';
    return $content;
}

function  block_fn_marking_sub_category_tree_form($structure, $categoryids=null, $courseids=null) {
    $content = "<ul>";
    if ($structure->categories) {
        foreach ($structure->categories as $category) {
            $content .= '<li>';
            if (in_array($category->id, $categoryids)) {
                $content .= block_fn_marking_checkbox_checked('category_'.$category->id, 'category_'.$category->id,
                        '_checkbox', $category->id) . ' <span class="ned-form-course-category"">'. $category->name.'</span>';
            } else {
                $content .= block_fn_marking_checkbox('category_'.$category->id, 'category_'.$category->id,
                        '_checkbox', $category->id) . ' <span class="ned-form-course-category"">'. $category->name.'</span>';
            }
            if ($category->courses) {
                $content .= '<ul>';
                foreach ($category->courses as $course) {
                    if (in_array($course->id, $courseids)) {
                        $content .= html_writer::tag('li',  block_fn_marking_checkbox_checked('course_'.$course->id,
                                'course_'.$course->id, '_checkbox', $course->id) . ' <span class="ned-form-course">'.
                            $course->fullname.'</span>');
                    } else {
                        $content .= html_writer::tag('li',  block_fn_marking_checkbox('course_'.$course->id,
                                'course_'.$course->id, '_checkbox', $course->id) . ' <span class="ned-form-course">'.
                            $course->fullname.'</span>');
                    }
                }
                $content .= '</ul>';
            }
            $content .= block_fn_marking_sub_category_tree_form($category, $categoryids, $courseids);
            $content .= '</li>';
        }
    }
    $content .= "</ul>";
    return $content;
}

function block_fn_marking_get_course_category_tree_($id = 0, $depth = 0) {
    global $DB, $CFG;
    $categories = array();
    $categoryids = array();
    $sql = context_helper::get_preload_record_columns_sql('ctx');
    $records = $DB->get_records_sql("SELECT c.*, $sql FROM {course_categories} c ".
        "JOIN {context} ctx on ctx.instanceid = c.id AND ctx.contextlevel = ? WHERE c.parent = ? ORDER BY c.sortorder",
        array(CONTEXT_COURSECAT, $id));
    foreach ($records as $category) {
        context_helper::preload_from_record($category);
        if (!$category->visible && !has_capability('moodle/category:viewhiddencategories',
                context_coursecat::instance($category->id))) {
            continue;
        }
        $categories[] = $category;
        $categoryids[$category->id] = $category;
        if (empty($CFG->maxcategorydepth) || $depth <= $CFG->maxcategorydepth) {
            list($category->categories, $subcategories) = block_fn_marking_get_course_category_tree_(
                $category->id, $depth + 1);
            foreach ($subcategories as $subid => $subcat) {
                $categoryids[$subid] = $subcat;
            }
            $category->courses = array();
        }
    }

    if ($depth > 0) {
        // This is a recursive call so return the required array.
        return array($categories, $categoryids);
    }

    if (empty($categoryids)) {
        // No categories available (probably all hidden).
        return array();
    }

    // The depth is 0 this function has just been called so we can finish it off.

    list($ccselect, $ccjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
    list($catsql, $catparams) = $DB->get_in_or_equal(array_keys($categoryids));
    $sql = "SELECT
            c.id,c.sortorder,c.visible,c.fullname,c.shortname,c.summary,c.category
            $ccselect
            FROM {course} c
            $ccjoin
            WHERE c.category $catsql ORDER BY c.sortorder ASC";
    if ($courses = $DB->get_records_sql($sql, $catparams)) {
        // Loop throught them.
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            context_helper::preload_from_record($course);
            if (!empty($course->visible) || has_capability('moodle/course:viewhiddencourses',
                    context_course::instance($course->id))) {
                $categoryids[$course->category]->courses[$course->id] = $course;
            }
        }
    }
    return $categories;
}

function block_fn_marking_checkbox($name, $id , $class, $value) {
    return html_writer::empty_tag('input', array(
            'value' => $value, 'type' => 'checkbox', 'id' => $id, 'name' => $name, 'class' => $class
        )
    );
}

function block_fn_marking_checkbox_checked($name, $id , $class, $value) {
    return html_writer::empty_tag('input', array(
            'value' => $value, 'type' => 'checkbox', 'id' => $id, 'name' => $name, 'class' => $class, 'checked' => 'checked'
        )
    );
}
function block_fn_marking_footer() {
    global $OUTPUT;

    $output = '';

    $pluginman = core_plugin_manager::instance();
    $pluginfo = $pluginman->get_plugin_info('block_fn_marking');

    $output = html_writer::div(
        html_writer::div(
            html_writer::link(
                'http://ned.ca/marking-manager',
                get_string('pluginname', 'block_fn_marking'),
                array('target' => '_blank')
            ),
            'markingmanagercontainer-footer-left'
        ) .
        html_writer::div(
            get_string('version', 'block_fn_marking') . ': ' .
            html_writer::span($pluginfo->versiondb, 'markingmanager-version'),
            'markingmanagercontainer-footer-center'
        ) .
        html_writer::div(
            html_writer::link(
                'http://ned.ca',
                html_writer::img($OUTPUT->pix_url('ned_26', 'block_fn_marking'), 'NED'),
                array('target' => '_blank')
            ),
            'markingmanagercontainer-footer-right'
        ),
        'markingmanagercontainer-footer'
    );
    return $output;
}

function block_fn_marking_get_selected_courses($category, &$filtercourses) {
    if ($category->courses) {
        foreach ($category->courses as $course) {
            $filtercourses[] = $course->id;
        }
    }
    if ($category->categories) {
        foreach ($category->categories as $subcat) {
            block_fn_marking_get_selected_courses($subcat, $course);
        }
    }
};

function block_fn_marking_get_setting_courses() {
    global $DB;

    $filtercourses = array();
    $coursewithblockinstances = array();
    $allcourseswithblock = get_config('block_fn_marking', 'allcourseswithblock');
    $includehiddencourses = get_config('block_fn_marking', 'includehiddencourses');

    if ($allcourseswithblock) {
        $sql = "SELECT ctx.instanceid  
                  FROM {context} ctx 
                 WHERE ctx.id IN (SELECT bi.parentcontextid 
                                    FROM {block_instances} bi 
                                   WHERE bi.blockname = 'fn_marking') 
                   AND ctx.contextlevel = ?
                   AND ctx.instanceid > ?";
        if ($courses = $DB->get_records_sql($sql, array(CONTEXT_COURSE, SITEID))) {
            foreach ($courses as $subcatcourse) {
                $coursewithblockinstances[] = $subcatcourse->instanceid;
            }
        }
    }

    $configcategory = get_config('block_fn_marking', 'category');
    $configcourse = get_config('block_fn_marking', 'course');


    if (empty($configcategory) && empty($configcourse)) {
        $sql = "SELECT c.id FROM {course} c WHERE c.id <> ?";
        if ($courses = $DB->get_records_sql($sql, array(SITEID))) {
            foreach ($courses as $subcatcourse) {
                $filtercourses[] = $subcatcourse->id;
            }
        }
    } else if ($configcategory) {
        $selectedcategories = explode(',', $configcategory);
        foreach ($selectedcategories as $categoryid) {

            if ($parentcatcourses = $DB->get_records('course', array('category' => $categoryid))) {
                foreach ($parentcatcourses as $catcourse) {
                    if (!$includehiddencourses && !$catcourse->visible) {
                        continue;
                    }
                    $filtercourses[] = $catcourse->id;
                }
            }
            if ($categorystructure = block_fn_marking_get_course_category_tree($categoryid)) {
                foreach ($categorystructure as $category) {

                    if ($category->courses) {
                        foreach ($category->courses as $subcatcourse) {
                            if (!$includehiddencourses && !$subcatcourse->visible) {
                                continue;
                            }
                            $filtercourses[] = $subcatcourse->id;
                        }
                    }
                    if ($category->categories) {
                        foreach ($category->categories as $subcategory) {
                            block_fn_marking_get_selected_courses($subcategory, $filtercourses);
                        }
                    }
                }
            }
        }
    }

    if ($configcourse) {
        $selectedcourses = explode(',', $configcourse);
        $filtercourses = array_merge($filtercourses, $selectedcourses);
    }

    if ($coursewithblockinstances) {
        $filtercourses = array_intersect($filtercourses, $coursewithblockinstances);
    }

    return $filtercourses;
}

function block_fn_marking_cache_course_data ($courseid, progress_bar $progressbar = null) {
    global $DB, $USER, $CFG;

    require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');

    $supportedmodules = block_fn_marking_supported_mods();

    $filtercourses = block_fn_marking_get_setting_courses();
    $cachecourses = array();

    if (in_array($courseid, $filtercourses)) {
        $cachecourses[] = $courseid;
    } else if ($courseid == SITEID) {
        if ($teachercourses = block_fn_marking_teacher_courses($USER->id)) {
            foreach ($teachercourses as $teachercourse) {
                if (in_array($teachercourse->courseid, $filtercourses)) {
                    $cachecourses[] = $teachercourse->courseid;
                }
            }
        } else if (is_siteadmin()) {
            $cachecourses = $filtercourses;
        }
    }

    if (!$numberofitems = count($cachecourses)) {
       return true;
    }
    $numberomodules = count($supportedmodules);
    $counter = 0;
    if (!is_null($progressbar)) {
        $donepercent = floor($counter / $numberofitems * 100);
        $progressbar->update_full($donepercent, "$counter of $numberofitems");
    }
    foreach ($cachecourses as $filtercourse) {
        if ($course = $DB->get_record('course', array('id' => $filtercourse))) {
            if (get_config('block_fn_marking', 'cachedatalast_'.$course->id) === 0) {
                continue;
            }
            set_config('cachedatalast_'.$course->id, 0, 'block_fn_marking');
            $DB->execute("UPDATE {block_fn_marking_mod_cache} SET expired = ? WHERE courseid = ?",
                array(time(), $course->id)
            );

            $context = context_course::instance($course->id);
            $teachers = get_users_by_capability($context, 'moodle/grade:viewall');
            $countermodule = 0;
            foreach ($supportedmodules as $supportedmodule => $file) {
                $summary = block_fn_marking_count_unmarked_activities($course, 'unmarked', $supportedmodule);
                $numunmarked = $summary['unmarked'];
                $nummarked = $summary['marked'];
                $numunsubmitted = $summary['unsubmitted'];
                $numsaved = $summary['saved'];

                $rec = new stdClass();
                $rec->courseid = $course->id;
                $rec->modname = $supportedmodule;
                $rec->unmarked = $numunmarked;
                $rec->marked = $nummarked;
                $rec->unsubmitted = $numunsubmitted;
                $rec->saved = $numsaved;
                $rec->timecreated = time();
                $rec->expired = 0;

                if ($modcache = $DB->get_record('block_fn_marking_mod_cache',
                    array('courseid' => $course->id, 'modname' => $supportedmodule, 'userid' => 0))) {
                    $rec->id = $modcache->id;
                    $DB->update_record('block_fn_marking_mod_cache', $rec);
                } else {
                    $DB->insert_record('block_fn_marking_mod_cache', $rec);
                }

                // Teachers in a group.
                if ($teachers) {
                    foreach ($teachers as $teacher) {
                        if ($groupstudents = block_fn_marking_mygroup_members($course->id, $teacher->id)) {
                            $summary = block_fn_marking_count_unmarked_activities($course, 'unmarked', $supportedmodule, $teacher->id);
                            $numunmarked = $summary['unmarked'];
                            $nummarked = $summary['marked'];
                            $numunsubmitted = $summary['unsubmitted'];
                            $numsaved = $summary['saved'];

                            $rec = new stdClass();
                            $rec->courseid = $course->id;
                            $rec->modname = $supportedmodule;
                            $rec->unmarked = $numunmarked;
                            $rec->marked = $nummarked;
                            $rec->unsubmitted = $numunsubmitted;
                            $rec->saved = $numsaved;
                            $rec->timecreated = time();
                            $rec->expired = 0;
                            $rec->userid = $teacher->id;

                            if ($modcache = $DB->get_record('block_fn_marking_mod_cache',
                                array('courseid' => $course->id, 'modname' => $supportedmodule, 'userid' => $teacher->id))) {
                                $rec->id = $modcache->id;
                                $DB->update_record('block_fn_marking_mod_cache', $rec);
                            } else {
                                $DB->insert_record('block_fn_marking_mod_cache', $rec);
                            }
                        }
                    }
                }

                $countermodule++;
                if (!is_null($progressbar)) {
                    $donepercent = floor(($counter + ($countermodule / $numberomodules)) / $numberofitems * 100);
                    $progressbar->update_full($donepercent, "$counter of $numberofitems");
                }
            }

            set_config('cachedatalast_'.$course->id, time(), 'block_fn_marking');
        }
        $counter++;
        if (!is_null($progressbar)) {
            $donepercent = floor($counter / $numberofitems * 100);
            $progressbar->update_full($donepercent, "$counter of $numberofitems");
        }
    }

    return true;
}

function block_fn_marking_human_timing ($time) {
    $time = time() - $time;
    $time = ($time < 1) ? 1 : $time;
    $tokens = array (
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) {
            continue;
        }
        $numberofunits = floor($time / $unit);
        return $numberofunits.' '.$text.(($numberofunits > 1) ? 's' : '');
    }
}

function block_fn_marking_gradebook_grade ($itemid, $userid) {
    global $DB;

    if ($grade = $DB->get_record('grade_grades', array('itemid' => $itemid, 'userid' => $userid))) {
        return $grade->finalgrade;
    } else {
        return false;
    }
}

function block_fn_marking_isinagroup($courseid, $userid) {
    global $DB;

    $sql = "SELECT g.* 
              FROM {groups_members} gm 
              JOIN {groups} g 
                ON gm.groupid = g.id 
             WHERE g.courseid = ? 
               AND gm.userid = ?";

    return $DB->record_exists_sql($sql, array($courseid, $userid));
}

function block_fn_marking_mygroup_members($courseid, $userid) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/group/lib.php');

    $members = array();
    $sql = "SELECT g.* 
              FROM {groups_members} gm 
              JOIN {groups} g 
                ON gm.groupid = g.id 
             WHERE g.courseid = ? 
               AND gm.userid = ?";

    if ($groups = $DB->get_records_sql($sql, array($courseid, $userid))) {
        foreach ($groups as $group) {
            if($groupmembers = groups_get_members_by_role($group->id, $courseid)) {
                foreach ($groupmembers as $groupmember) {
                    if ((!empty($groupmember->shortname)) && ($groupmember->shortname == 'student')) {
                        $members +=$groupmember->users;
                    }
                }
            }
        }
        return $members;
    } else  {
        return false;
    }
}

function block_fn_marking_groups_print_course_menu($course, $urlroot, $return=false, $activegroup=false) {
    global $USER, $OUTPUT;

    if (!$groupmode = $course->groupmode) {
        if ($return) {
            return '';
        } else {
            return;
        }
    }

    $context = context_course::instance($course->id);
    $aag = has_capability('moodle/site:accessallgroups', $context);

    $usergroups = array();
    if ($groupmode == VISIBLEGROUPS or $aag) {
        $allowedgroups = groups_get_all_groups($course->id, 0, $course->defaultgroupingid);
        // Get user's own groups and put to the top.
        $usergroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);
    } else {
        $allowedgroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);
    }

    if ($activegroup === false) {
        $activegroup = groups_get_course_group($course, true, $allowedgroups);
    }

    $groupsmenu = array();
    $groupsmenuoptions = groups_sort_menu_options($allowedgroups, $usergroups);

    if ((!$allowedgroups or $groupmode == VISIBLEGROUPS or $groupmode == SEPARATEGROUPS or $aag) && (count($groupsmenuoptions) > 1)) {
        $groupsmenu[0] = get_string('allgroups', 'block_fn_marking');
    }

    $groupsmenu += groups_sort_menu_options($allowedgroups, $usergroups);

    if ($groupmode == VISIBLEGROUPS) {
        $grouplabel = get_string('groupsvisible');
    } else {
        $grouplabel = get_string('groupsseparate');
    }

    if ($aag and $course->defaultgroupingid) {
        if ($grouping = groups_get_grouping($course->defaultgroupingid)) {
            $grouplabel = $grouplabel . ' (' . format_string($grouping->name) . ')';
        }
    }

    if (count($groupsmenu) == 1) {
        $groupname = reset($groupsmenu);
        $output = html_writer::img($OUTPUT->pix_url('i/users'), '').' '.$groupname;
    } else {
        $select = new single_select(new moodle_url($urlroot), 'group', $groupsmenu, $activegroup, null, 'selectgroup');
        $select->label = html_writer::img($OUTPUT->pix_url('i/users'), '');
        $output = $OUTPUT->render($select);
    }

    $output = '<div class="groupselector">'.$output.'</div>';

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

function block_fn_marking_supported_mods() {
    return array(
        'assign' => 'assign.submissions.fn.php',
        'assignment' => 'assignment.submissions.fn.php',
        'quiz' => 'quiz.submissions.fn.php',
        'forum' => 'forum.submissions.fn.php',
        'journal' => 'journal.submissions.fn.php'
    );
}
function block_fn_marking_view_journal_submissions($journal, $students, $cm, $course, $pageparams, $show = 'marked') {
    global $DB, $CFG, $OUTPUT;

    $o = '';

    $studentlist = implode(',', array_keys($students));

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

    if ($attempts = $DB->get_records_sql($sql, array($journal->id))) {
        $attempts = array_keys($attempts);
    }

    if ($show == 'unsubmitted') {
        $attempts = array_diff(array_keys($students), array_keys($attempts));
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

    if ($show == 'unsubmitted') {

        $unsubmitted = array();

        if (count($attempts) > 0) {
            $url = new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
            $image = '<a href="'.$url->out().'"><img width="16" height="16" alt="'.
                $cm->modname.'" src="'.$OUTPUT->pix_url('icon', $cm->modname).'"></a>';

            $o .= '<div class="unsubmitted_header">' . $image .
                " Journal: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" .
                $journal->name . '</a></div>';

            $o .= '<p class="unsubmitted_msg">The following students have not submitted this journal:</p>';

            foreach ($attempts as $studentid) {
                $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                $o .= "\n<tr>";
                $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
                $user = $DB->get_record('user', array('id' => $studentid));
                $o .= $OUTPUT->user_picture($user, array('courseid' => $cm->course, 'size' => 20));
                $o .= "</td>";
                $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
                $o .= "</tr></table>\n";

            }
        } else if (count($attempts) == 0) {
            $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
        }
    } else {
        foreach ($attempts as $studentid) {
            $historyout = '';

            $item = $entrybyuser[$studentid];
            $student = $DB->get_record('user', array('id' => $item->userid));
            $item->modified;
            $item->text;
            $item->rating;
            $item->entrycomment;
            $item->teacher;
            $item->timemarked;

            $groupname = '';

            if (!$gradeitem = $DB->get_record('grade_items', array('itemtype' => 'mod', 'itemmodule' => 'journal',
                'iteminstance' => $journal->id))) {
                $gradeitem = new stdClass();
                $gradeitem->gradepass = 0;
            }

            $header = '<table class="headertable"><tr>';

            $header .= '<td width="35px">'.$OUTPUT->user_picture($student).'</td>';
            $urlparams = array('id' => $item->userid, 'course' => $cm->course);
            $url = new moodle_url('/user/view.php', $urlparams);

            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($student),
                    null, array('target' => '_blank', 'class' => 'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Journal: <a target="_blank" class="marking_header_link"
            title="Journal" href="'.
                $CFG->wwwroot.'/mod/journal/view.php?id='.$journal->id.'">' .
                $journal->name.'</a></div></td>';
            $header .= '<td align="right" style="color:white;"></td>';

            $header .= '</tr></table>';


            $t = new html_table();
            $t->attributes['class'] = 'generaltable historytable';
            $cell = new html_table_cell($header);
            $cell->attributes['class'] = 'historyheader';
            $cell->colspan = 3;
            $t->data[] = new html_table_row(array($cell));

            $submittedicon = '<img width="16" height="16" border="0" alt="Journal" src="'.
                $OUTPUT->pix_url('text', 'block_fn_marking').'" valign="absmiddle"> ';
            $markedicon = '<img width="16" height="16" border="0" alt="Journal" src="'.
                $OUTPUT->pix_url('completed', 'block_fn_marking').'" valign="absmiddle"> ';
            $savedicon = '<img width="16" height="16" border="0" alt="Journal" src="'.
                $OUTPUT->pix_url('saved', 'block_fn_marking').'" valign="absmiddle"> ';
            if ($gradeitem->gradepass > 0) {
                $markediconincomplete = '<img width="16" height="16" border="0" alt="Journal" src="'.
                    $OUTPUT->pix_url('incomplete', 'block_fn_marking').'" valign="absmiddle"> ';
            } else {
                $markediconincomplete = '<img width="16" height="16" border="0" alt="Journal" src="'.
                    $OUTPUT->pix_url('graded', 'block_fn_marking').'" valign="absmiddle"> ';
            }

            $lastsubmissionclass = '';


            if ($journal->grade == 0) {
                $cell1 = new html_table_cell(get_string('nograde', 'block_fn_marking'));
            } else {
                if ($gradeitem->gradepass > 0) {
                    $background = 'bg_white';
                    $background = ($item->rating  >= $gradeitem->gradepass) ? 'bg_green' : 'bg_orange';
                } else {
                    $background = 'bg_white';
                }
                $cell1 = new html_table_cell($item->rating . '/' . $journal->grade);
            }

            $cell1->rowspan = 2;
            $cell1->attributes['class'] = $lastsubmissionclass;
            $cell2 = new html_table_cell($submittedicon . get_string('submitted', 'assign'));

            $cell3 = new html_table_cell(userdate($item->modified));

            $cell3->text = '<div style="float:left;">'.$cell3->text.'</div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.
                $pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.
                $pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].
                '&expand=1&userid='.$studentid.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.
                $CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';

            if ($journal->grade == 0) {
                $cell1->attributes['class'] = 'bg_grey';
                $cell2->attributes['class'] = 'bg_grey';
                $cell3->attributes['class'] = 'bg_grey';
            } else {
                $cell1->attributes['class'] = $background;
                $cell2->attributes['class'] = $background;
                $cell3->attributes['class'] = $background;
            }
            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));

            if ($journal->grade == 0) {
                $cell1 = new html_table_cell('<img width="16" height="16" border="0" alt="Journal" src="'.
                    $OUTPUT->pix_url('graded', 'block_fn_marking').'" valign="absmiddle"> Marked');
            } else {
                $cell1 = new html_table_cell(((($gradeitem->gradepass > 0)
                        && ($item->rating >= $gradeitem->gradepass)) ? $markedicon : $markediconincomplete) . 'Marked');
            }


            $cell2 = new html_table_cell(userdate($item->timemarked));
            if ($journal->grade == 0) {
                $cell1->attributes['class'] = 'bg_grey';
                $cell2->attributes['class'] = 'bg_grey';
            } else {
                $cell1->attributes['class'] = $background;
                $cell2->attributes['class'] = $background;
            }
            $t->data[] = new html_table_row(array($cell1, $cell2));


            $historyout .= html_writer::table($t);

            if ($historyout) {
                $o .= $OUTPUT->box_start('generalbox submissionhistory_summary');
                $o .= $historyout;
                $o .= $OUTPUT->box_end();
            }
        }
    }

    return $o;
}

function block_fn_marking_assignment_get_notsubmittedany($courseid, $id = "0", $users = null, $timestart) {
    global $DB;
    // Split out users array.
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND u.id IN (' . implode(',', $userids) . ')';
        $studentswithsubmissions = $DB->get_records_sql("SELECT DISTINCT u.id as userid,
                                                                  u.firstname,
                                                                  u.lastname,
                                                                  u.email,
                                                                  u.picture,
                                                                  u.imagealt
                                                             FROM {assignment_submissions} asb
                                                             JOIN {assignment} a
                                                               ON a.id = asb.assignment
                                                            JOIN {user} u ON u.id = asb.userid
                                          WHERE asb.timemodified > $timestart AND a.id = $id
                                                $userselect");
        return $studentswithsubmissions;
    }
}

function block_fn_marking_assign_get_notsubmittedany($courseid, $instanceid, $users, $timestart) {
    global $DB;

    if (empty($users)) {
        return array();
    }
    list($insql, $params) = $DB->get_in_or_equal(array_keys($users));
    $params[] = $instanceid;
    $params[] = $timestart;

    $sql = "SELECT DISTINCT asub.userid
              FROM {assign_submission} asub
             WHERE asub.userid {$insql}
               AND asub.assignment = ?
               AND asub.timemodified > ?";
    return $DB->get_records_sql($sql, $params);
}

function block_fn_marking_forum_get_notsubmittedany($courseid, $instanceid, $users, $timestart) {
    global $DB;

    if (empty($users)) {
        return array();
    }
    list($insql, $params) = $DB->get_in_or_equal(array_keys($users));
    $params[] = $timestart;
    $params[] = $instanceid;

    $sql = "SELECT DISTINCT u.id userid,
                   u.firstname,
                   u.lastname,
                   u.email,
                   u.picture,
                   u.imagealt
              FROM {forum_posts} p
              JOIN {forum_discussions} d
                ON d.id = p.discussion
              JOIN {forum} f
                ON f.id = d.forum
              JOIN {user} u
                ON u.id = p.userid
             WHERE u.id {$insql}
               AND p.created > ?
               AND f.id = ?";
    return $DB->get_records_sql($sql, $params);
}

function block_fn_marking_quiz_get_notsubmittedany($courseid, $instanceid, $users, $timestart) {
    global $DB;

    if (empty($users)) {
        return array();
    }
    list($insql, $params) = $DB->get_in_or_equal(array_keys($users));
    $params[] = $instanceid;
    $params[] = $timestart;

    $sql = "SELECT DISTINCT qa.userid 
              FROM {quiz_attempts} qa
             WHERE qa.userid {$insql}
               AND qa.quiz = ?
               AND qa.timemodified > ?";
    return $DB->get_records_sql($sql, $params);
}

function block_fn_marking_teacher_courses($userid) {
    global $DB;
    $adminfrontpage = get_config('block_fn_marking', 'adminfrontpage');
    if (is_siteadmin($userid) && $adminfrontpage == 'all') {
        return $DB->get_records_select('course', 'id > ? AND visible = ?', array(SITEID, 1), '', 'id courseid');
    } else {
        $teacherroles = get_roles_with_capability('moodle/grade:edit', CAP_ALLOW);

        list($sqlin, $params) = $DB->get_in_or_equal(array_keys($teacherroles));
        $params[] = $userid;

        $sql = "SELECT DISTINCT ctx.id,
                   ctx.instanceid courseid
              FROM {context} ctx
        INNER JOIN {role_assignments} ra
                ON ctx.id = ra.contextid
             WHERE ctx.contextlevel = 50
               AND ra.roleid {$sqlin}
               AND ra.userid = ?";

        return $DB->get_records_sql($sql, $params);
    }
}

function block_fn_marking_frontapage_cache_update_time($userid) {
    $time = false;

    $filtercourses = block_fn_marking_get_setting_courses();

    if ($teachercourses = block_fn_marking_teacher_courses($userid)) {
        $counter = 0;
        foreach ($teachercourses as $teachercourse) {
            if (in_array($teachercourse->courseid, $filtercourses)) {
                $counter++;
                $coursecache = get_config('block_fn_marking', 'cachedatalast_'.$teachercourse->courseid);

                if ($counter == 1) {
                    $time = $coursecache;
                }
                if ($coursecache === false) {
                    return false;
                } else if ($time > $coursecache) {
                    $time = $coursecache;
                }
            }
        }

    }
    return $time;
}