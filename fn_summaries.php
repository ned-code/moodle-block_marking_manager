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

require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

$id = required_param('id', PARAM_INT);      // Course id.
$show = optional_param('show', 'notloggedin', PARAM_ALPHA);
$days = required_param('days', PARAM_INT); // Days to look back.
$percent = optional_param('percent', 0, PARAM_INT);

$datestring = new stdClass();
$datestring->year = get_string('year');
$datestring->years = get_string('years');
$datestring->day = get_string('day');
$datestring->days = get_string('days');
$datestring->hour = get_string('hour');
$datestring->hours = get_string('hours');
$datestring->min = get_string('min');
$datestring->mins = get_string('mins');
$datestring->sec = get_string('sec');
$datestring->secs = get_string('secs');

// Paging options.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$PAGE->requires->css('/blocks/fn_marking/css/styles.css');
$PAGE->set_url('/blocks/fn_marking/fn_summaries.php', array('id' => $id, 'show' => $show, 'navlevel' => 'top'));

if (!$course = $DB->get_record("course", array("id" => $id))) {
    print_error("Course ID was incorrect");
}

require_login($course);

// Grab context.
$context = context_course::instance($course->id);
$cobject = new stdClass();
$cobject->course = $course;

$isteacher = has_capability('moodle/grade:viewall', $context);

// Only teachers should see this.
if (!$isteacher) {
    print_error("Only teachers can use this page!");
}

$isteacheredit = has_capability('moodle/course:update', $context);
$context = context_course::instance($course->id);
$viewallgroups = has_capability('moodle/site:accessallgroups', $context);

$groupstudents = block_fn_marking_mygroup_members($course->id, $USER->id);

if ($groupstudents === false) {
    $currentgroup = groups_get_course_group($course, true);
    $students = get_enrolled_users($context, 'mod/assign:submit', $currentgroup, 'u.*', 'u.id');
} else {
    $students = $groupstudents;
}

// Get a list of all students.
if (!$students) {
    $students = array();
}

$modnames = get_module_types_names();
$modnamesplural = get_module_types_names(true);
$modinfo = get_fast_modinfo($course->id);
$mods = $modinfo->get_cms();
$modnamesused = $modinfo->get_used_module_names();

$sections = get_fast_modinfo($course->id)->get_section_info_all();
$modarray = array($mods, $modnames, $modnamesplural, $modnamesused);

// Grab list of students.
switch ($show) {
    case 'notloggedin':
        $studentsresult = block_fn_marking_get_notloggedin($course, $days);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:notlogin', 'block_fn_marking') . " $days days";
        break;

    case 'notsubmittedany':
        $lastweek = time() - (60 * 60 * 24 * $days);
        $studentsresult = block_fn_marking_get_notsubmittedany($course, $lastweek, false, $sections, $students);
        // Students array is indexed by studentid; paging needs it to be sequential.
        $studentsresult = array_values($studentsresult);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:notsubmittedanyactivity', 'block_fn_marking') . " $days days";
        break;

    case 'failing':
        $studentsresult = block_fn_marking_get_failing($course, $percent);
        // Comes back indexed by studentid; reindex.
        $studentsresult = array_values($studentsresult);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:failingwithgradelessthanxpercent', 'block_fn_marking') . " $percent%";
        break;
    default:
        break;
}

$heading = $course->fullname;
$PAGE->navbar->add($name);
$PAGE->set_title($title);
$PAGE->set_heading($heading);
echo $OUTPUT->header();

echo '<div class="fn-menuwrapper"><a class="btn" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.
    get_string('close', 'block_fn_marking').'</a></div>';
echo "<div id='marking-interface'>";
echo "<h4 class='head-title'>$title</h4>\n";

// Use paging.
$totalcount = count($studentsresult);
$baseurl = 'fn_summaries.php?id=' . $id . '&show=' . $show . '&navlevel=top&days=' . $days . '&percent=' . $percent . '';
$pagingbar = new paging_bar($totalcount, $page, $perpage, $baseurl, 'page');
echo $OUTPUT->render($pagingbar);


echo '<table width="96%" class="markingmanagercontainerList" border="0" cellpadding="0" cellspacing="0" align="center">' .
    '<tr><td class="intd">';

echo '<table  width="100%" border="0" cellpadding="0" cellspacing="0">';

if ($show == 'notloggedin' || $show == 'notsubmittedany') {
    echo "<tr>";
    echo "<th>Student</th>";
    echo "<th style='text-align: center;'>Last access</th>";
    echo "</tr>";
}

if ($show == 'failing') {
    echo "<tr>";
    echo "<th>Student</th>";
    echo "<th>Last access</th>";
    echo "</tr>";
}

// Iterate.
for ($i = ($page * $perpage); ($i < ($page * $perpage) + $perpage) && ($i < $totalcount); $i++) {
    // Grab student.
    $student = $studentsresult[$i];
    if ($show == 'failing') {
        $gradeobj = grade_get_course_grade($student->id, $course->id);
        $grade = (int) $gradeobj->grade;
        echo "<tr>\n";
        $user = $DB->get_record('user', array('id' => $student->id));
        $fullname = fullname($student, true);
        echo "<td align='left'>".$OUTPUT->user_picture($user, array('courseid' => $course->id))." <a href='" . $CFG->wwwroot .
            "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a></td>\n";
        echo "<td align='center'>$grade%</td></tr>\n";
    } else if ($show == 'notsubmittedany') {
        echo("<tr>");
        $user = $DB->get_record('user', array('id' => $student->id));
        $fullname = fullname($student, true);
        $lastaccess = format_time(time() - $student->lastaccess, $datestring);
        if (!$student->lastaccess) {
            $minlastaccess = "Never";
        } else {
            $lastaccessstring = $lastaccess;
            $lastaccessincourse = $DB->get_field_sql('SELECT min(timeaccess)
							FROM {user_lastaccess}
							WHERE courseid = ?
							AND userid=?
							AND timeaccess != 0', array($course->id, $student->id));
            $minlastaccess = userdate($lastaccessincourse) . "&nbsp;(" .
                format_time(time() - $lastaccessincourse, $datestring) . ")";
        }
        echo "<td align='left'>".$OUTPUT->user_picture($user, array('courseid' => $course->id))."<a href='" . $CFG->wwwroot .
            "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a></td>";
        echo "<td align='center'>" . $minlastaccess . "</td></tr>\n";
    } else {
        echo "<tr>";
        $user = $DB->get_record('user', array('id' => $student->id));
        $fullname = fullname($student, true);
        $lastaccess = format_time(time() - $student->lastaccess, $datestring);
        if (!$student->lastaccess) {
            $minlastaccess = "Never";
        } else {
            $lastaccessstring = $lastaccess;
            $lastaccessincourse = $DB->get_field_sql('SELECT min(timeaccess)
								FROM {user_lastaccess}
								WHERE courseid = ?
								AND userid=?
								AND timeaccess != 0', array($course->id, $student->id));
            $minlastaccess = userdate($lastaccessincourse) . "&nbsp;(" .
                format_time(time() - $lastaccessincourse, $datestring) . ")";
        }
        echo"<td align='left'>".$OUTPUT->user_picture($user, array('courseid' => $course->id)).
            "<a href='" . $CFG->wwwroot . "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a></td>";
        echo "<td align='center'>" . $minlastaccess . "</td></tr>\n";
    }
}
echo"</table>\n";

echo '</td></tr></table>';

echo "</div>";

echo block_fn_marking_footer();

echo $OUTPUT->footer($course);