<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

global $CFG, $DB, $OUTPUT, $PAGE, $FULLME;


$id = required_param('id', PARAM_INT);      // course id
$show = optional_param('show', 'notloggedin', PARAM_ALPHA);
$days = required_param('days', PARAM_INT); //days to look back
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

//  Paging options:
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$PAGE->set_url('/blocks/fn_marking/fn_summaries.php', array('id' => $id, 'show' => $show, 'navlevel' => 'top'));

if (!$course = $DB->get_record("course", array("id" => $id))) {
    print_error("Course ID was incorrect");
}

require_login($course);

//grab context
$context = get_context_instance(CONTEXT_COURSE, $course->id);
$cobject = new Object();
$cobject->course = $course;

$isteacher = has_capability('moodle/grade:viewall', $context);

// only teachers should see this!
if (!$isteacher) {
    print_error("Only teachers can use this page!");
}

$isteacheredit = has_capability('moodle/course:update', $context);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
$viewallgroups = has_capability('moodle/site:accessallgroups', $context);

$currentgroup = get_current_group($course->id);
$students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');

/// Get a list of all students
if (!$students) {
    $students = array();
}


// grab modules
//get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
//$sections = get_all_sections($course->id);

$modnames = get_module_types_names(); //print_r($modnames);die;
$modnamesplural = get_module_types_names(true); //print_r($modnamesplural);die;
$modinfo = get_fast_modinfo($course->id); //print_r($modinfo);die;
$mods = $modinfo->get_cms(); //print_r($mods);die;
$modnamesused = $modinfo->get_used_module_names(); //print_r($modnamesused);die;

$sections = get_fast_modinfo($course->id)->get_section_info_all();    
$mod_array = array($mods, $modnames, $modnamesplural, $modnamesused);

// grab list of students
switch ($show) {
    case 'notloggedin':
        $students_result = fn_get_notloggedin($course, $days);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:notlogin', 'block_fn_marking') . " $days days";
        break;

    case 'notsubmittedany':
        $lastweek = time() - (60 * 60 * 24 * $days);
        $students_result = fn_get_notsubmittedany($course, $lastweek, false, $sections, $mod_array, $students);
        // students array is indexed by studentid; paging needs it to be sequential
        $students_result = array_values($students_result);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:notsubmittedanyactivity', 'block_fn_marking') . " $days days";
        break;

    case 'failing':
        $students_result = fn_get_failing($course, $percent);
        // comes back indexed by studentid; reindex
        $students_result = array_values($students_result);
        $name = get_string('blocktitle', 'block_fn_marking');
        $title = "" . get_string('title:failingwithgradelessthanxpercent', 'block_fn_marking') . " $percent%";
        break;
    default:
        break;
}

/// Print header
$navlinks = array(array('name' => $name, 'link' => '', 'type' => 'misc'));
$navigation = build_navigation($navlinks);
$heading = $course->fullname;
print_header_simple($title, $heading, $navigation, '', '', true, '', '');

echo "<div id='marking-interface'>";
echo "<h4 class='head-title'>$title</h4>\n";

// use paging
$totalcount = count($students_result);
$baseurl = 'fn_summaries.php?id=' . $id . '&show=' . $show . '&navlevel=top&days=' . $days . '&percent=' . $percent . '';
$pagingbar = new paging_bar($totalcount, $page, $perpage, $baseurl, 'page');
echo $OUTPUT->render($pagingbar);

echo '<table width="96%" class="markingmanagercontainerList" border="0" cellpadding="0" cellspacing="0" align="center">' . '<tr><td class="intd">';

echo '<table  width="100%" border="0" cellpadding="0" cellspacing="0">';
// iterate through students
if ($show == 'notloggedin' || $show == 'notsubmittedany') {
    echo "<tr>";
    echo "<th align='center' width='15%'><strong>User Picture </strong></th>";
    echo "<th align='left' width='67%' style='text-align:left;'><strong>Name </strong></th>";
    echo "<th align='center' width='18%'><strong>Last access <strong></th>";
    echo "</tr>";
}

if ($show == 'failing') {
    echo "<tr>";
    echo "<th align='center' width='15%'><strong>User picture </strong></th>";
    echo "<th align='left' width='67%' style='text-align:left;'><strong>Name </strong></th>";
    echo "<th align='center' width='18%'><strong>Overall grade <strong></th>";
    echo "</tr>";
}

// iterate
for ($i = ($page * $perpage); ($i < ($page * $perpage) + $perpage) && ($i < $totalcount); $i++) {
    // grab student
    $student = $students_result[$i];
    //foreach($students as $student) {
    if ($show == 'failing') {
        $grade_obj = grade_get_course_grade($student->id, $course->id);
        // convert grade to int
        // does this round up/down?
        $grade = (int) $grade_obj->grade;
        echo "<tr><td align='center'>\n";
        $user = $DB->get_record('user', array('id' => $student->id));
        echo $OUTPUT->user_picture($user, array('courseid' => $course->id));
        $fullname = fullname($student, true);

        echo "</td>\n";
        echo "<td align='left'><strong><a href='" . $CFG->wwwroot . "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a></strong></td>\n";
        echo "<td align='center'>$grade%</td></tr>\n";
    } else if ($show == 'notsubmittedany') {
        echo("<tr><td align='center'>");
        $user = $DB->get_record('user', array('id' => $student->id));
        echo $OUTPUT->user_picture($user, array('courseid' => $course->id));
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
            $minlastaccess = userdate($lastaccessincourse) . "&nbsp;<br /> (" . format_time(time() - $lastaccessincourse, $datestring) . ")";
        }
        echo "</td><td align='left'><strong><a href='" . $CFG->wwwroot . "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a><strong></td>";
        echo "<td align='center'>" . $minlastaccess . "</td></tr>\n";
    } else {
        echo "<tr><td align='center'>";
        $user = $DB->get_record('user', array('id' => $student->id));
        echo $OUTPUT->user_picture($user, array('courseid' => $course->id));
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
            $minlastaccess = userdate($lastaccessincourse) . "&nbsp;<br /> (" . format_time(time() - $lastaccessincourse, $datestring) . ")";
        }
        echo"</td><td align='left'><strong><a href='" . $CFG->wwwroot . "/user/view.php?id=$user->id&course=$COURSE->id'>" . $fullname . "</a></strong></td>";
        echo "<td align='center'>" . $minlastaccess . "</td></tr>\n";
    }
}
echo"</table>\n";

echo '</td></tr></table>';

echo "</div>";


echo $OUTPUT->footer($course);
