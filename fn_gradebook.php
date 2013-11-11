<?php

// Displays all grades for a course
global $CFG;
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/plagiarismlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/lib/outputrenderers.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
$PAGE->requires->js('/mod/assignment/assignment.js');

if (isset($CFG->noblocks)){
    if ($CFG->noblocks){
        $PAGE->set_pagelayout('markingmanager');
    }else{
        $PAGE->set_pagelayout('course');
    }
}else{
    $PAGE->set_pagelayout('course');
}



global $DB, $OUTPUT, $course;

$courseid = required_param('courseid', PARAM_INT);      // course id
$mid = optional_param('mid', 0, PARAM_INT); // mod id to look at
$cmid = 0;                                 // If no mid is specified, we'll select one in this variable.

//Check sesubmission plugin
if ($assignChecks = $DB->get_records_sql("SELECT * FROM {$CFG->prefix}assign")){
    foreach ($assignChecks as $assignCheck) {
        if(isset($assignCheck->resubmission)){
            $resubmission = true;
            break;
        }else{
            $resubmission = false;
        }
    }
}else{
    $resubmission = false;
}

/// From mod grade files:
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$timenow = optional_param('timenow', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

//  Paging options:
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

//  Filtering Options
$menushow = optional_param('menushow', 'unmarked', PARAM_ALPHA);
$sort = optional_param('sort', 'date', PARAM_ALPHANUM);
$show = optional_param('show', 'unmarked', PARAM_ALPHA);
$view = optional_param('view', 'less', PARAM_ALPHA);

$userid = optional_param('userid', 0, PARAM_INT);
$group = optional_param('group', 0, PARAM_INT);
$expand = optional_param('expand', 0, PARAM_INT);
$rownum = optional_param('rownum', 0, PARAM_INT);

set_current_group($courseid, $group);

$PAGE->set_url('/fn_gradebook.php', array(
    'courseid' => $courseid,
    'mid' => $mid,
    'view' => $view,
    'show' => $show,
    'dir' => $dir));

$pageparams = array('courseid'=>$courseid,
                    'resubmission' => $resubmission,
                    'userid' => $userid,
                    'mid' => $mid,
                    'dir' => $dir,
                    'group' => $group,
                    'timenow' => $timenow,
                    'action' => $action,
                    'expand' => $expand,
                    'rownum' => $rownum,
                    'page' => $page,
                    'perpage' => $perpage,
                    'menushow' => $menushow,
                    'sort' => $sort,
                    'view' => $view,
                    'show' => $show,
                    'dir' => $dir);

if (!$course = $DB->get_record("course", array("id" => $courseid))) {
    print_error("Course ID was incorrect");
}

require_login($course);

//grab course context
$context = get_context_instance(CONTEXT_COURSE, $course->id);
$isteacher = has_capability('moodle/grade:viewall', $context);

$cobject = new Object();
$cobject->course = $course;

if (!$isteacher) {
    print_error("Only teachers can use this page!");
}

require_login($course->id);

/// Array of functions to call for grading purposes for modules.
$mod_grades_array = array(
    'assign' => 'assign.submissions.fn.php',
    'assignment' => 'assignment.submissions.fn.php',
    'forum' => 'forum.submissions.fn.php',
);

/// Array of functions to call to display grades for modules.
$mod_gradedisp_array = array(
    'assignment' => 'grades.fn.html',
    'forum' => 'grades.fn.html'
);

$strgrades = get_string("headertitle", 'block_fn_marking');
$strgrade = get_string("grade");
$strmax = get_string("maximumshort");


$isteacheredit = has_capability('moodle/course:update', $context);
$viewallgroups = has_capability('moodle/site:accessallgroups', $context);


// The sort options.
if ($show == 'marked') {
    $sortopts = array('lowest' => 'Lowest mark',
        'highest' => 'Highest mark',
        'date' => 'Submission date',
        'alpha' => 'Alphabetically');

    $url = new moodle_url('/fn_gradebook.php', array('courseid' => $courseid,
                'mid' => $mid,
                'view' => $view,
                'show' => $show,
                'dir' => $dir));

    $sortform = 'Sort: ' . $OUTPUT->single_select(new moodle_url('fn_gradebook.php?courseid=' . $courseid . '&mid=' . $mid . '&view=' . $view . '&show=' . $show . '&dir=' . $dir ), 'sort', $sortopts, $selected = $sort, '', $formid = 'fnsort');
} else {
    $sortform = '';
    $sort = 'date';
}

// The view options.
$viewopts = array('less' => 'Less', 'more' => 'More');
$urlview = new moodle_url('fn_gradebook.php', array('courseid' => $courseid, 'mid' => $mid, 'dir' => $dir, 'sort' => $sort, 'show' => $show));
$select = new single_select($urlview, 'view', $viewopts, $selected = $view, '');
$select->formid = 'fnview';

$viewform = 'View:' . $OUTPUT->render($select);

// The show options.
if (($view == 'less') || ($view == 'more')) {
    if ($mid) {
        $cm_module = $DB->get_record('course_modules', array('id' => $mid));
        $module_name = $DB->get_field('modules', 'name', array('id' => $cm_module->module));

        if ($module_name == 'forum') {
            $showopts = array('unmarked' => 'Requires Grading', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
        } else if ($module_name == 'assignment' || $module_name == 'assign') {
            $showopts = array('unmarked' => 'Requires Grading',  'saved' => 'Draft', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
        } else {
            $showopts = array();
        }
    } else {
        $showopts = array('unmarked' => 'Requires Grading',  'saved' => 'Draft', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
    }

    $urlshow = new moodle_url('fn_gradebook.php', array('courseid' => $courseid, 'mid' => $mid, 'dir' => $dir, 'sort' => $sort, 'view' => $view));
    $showform = $OUTPUT->single_select($urlshow, 'show', $showopts, $selected = $show, '', $formid = 'fnshow');
}



if ($mid) {
    if (!$course_module = $DB->get_record('course_modules', array('id' => $mid))) {
        print_error('invalidcoursemodule');
    }

    if (!$module = $DB->get_record('modules', array('id' => $course_module->module))) {
        print_error('invalidcoursemodule');
    }

    if (!$cmm = get_coursemodule_from_id($module->name, $mid)) {
        print_error('invalidcoursemodule');
    }

    $modcontext = get_context_instance(CONTEXT_MODULE, $cmm->id);
    $groupmode = groups_get_activity_groupmode($cmm);
    $currentgroup = groups_get_activity_group($cmm, true);
    $users = get_enrolled_users($modcontext, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
} else {
    // if comes from course page
    $currentgroup = get_current_group($course->id);
}


$students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');

// Get a list of all students
if (!$students) {
    $students = array();
    $PAGE->set_title(get_string('course') . ': ' . $course->fullname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer($course);
    exit;
}

$columnhtml = array();  // Accumulate column html in this array.
$columnungraded = array(); // Accumulate column graded totals in this array.
$totungraded = 0;

/// Collect modules data
//get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
$modnames = get_module_types_names();
$modnamesplural = get_module_types_names(true);
$modinfo = get_fast_modinfo($course->id);
$mods = $modinfo->get_cms();
$modnamesused = $modinfo->get_used_module_names();

$mod_array = array($mods, $modnames, $modnamesplural, $modnamesused);

$cobject->mods = &$mods;
$cobject->modnames = &$modnames;
$cobject->modnamesplural = &$modnamesplural;
$cobject->modnamesused = &$modnamesused;
$cobject->sections = &$sections;


  //FIND CURRENT WEEK
    $courseformatoptions = course_get_format($course)->get_format_options();
    $course_numsections = $courseformatoptions['numsections'];

    $timenow = time();
    $weekdate = $course->startdate;    // this should be 0:00 Monday of that week
    $weekdate += 7200;                 // Add two hours to avoid possible DST problems

    $weekofseconds = 604800;
    $course_enddate = $course->startdate + ($weekofseconds * $course_numsections);

    //  Calculate the current week based on today's date and the starting date of the course.
    $currentweek = ($timenow > $course->startdate) ? (int) ((($timenow - $course->startdate) / $weekofseconds) + 1) : 0;
    $currentweek = min($currentweek, $course_numsections);


/// Search through all the modules, pulling out grade data
//$sections = get_all_sections($course->id); // Sort everything the same as the course
$sections = get_fast_modinfo($course->id)->get_section_info_all();

if ($view == "less"){
    $upto = min($currentweek+1, sizeof($sections));
}else{
    $upto = sizeof($sections);
}

for ($i = 0; $i < $upto; $i++) {

    if (isset($sections[$i])) {   // should always be true
        $section = $sections[$i];
        if ($section->sequence) {
            $sectionmods = explode(",", $section->sequence);
            foreach ($sectionmods as $sectionmod) {
                if (empty($mods[$sectionmod])) {
                    continue;
                }
                $mod = $mods[$sectionmod];

                $currentgroup = groups_get_activity_group($mod, true); //print_r($currentgroup);die;
                $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');

                /// Don't count it if you can't see it.
                $mcontext = get_context_instance(CONTEXT_MODULE, $mod->id);
                if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
                    continue;
                }
                global $DB;
                $instance = $DB->get_record($mod->modname, array("id" => $mod->instance));
                $libfile = $CFG->dirroot . '/mod/' . $mod->modname . '/lib.php';
                if (file_exists($libfile)) {
                    require_once($libfile);
                    $gradefunction = $mod->modname . "_get_user_grades";
                    if ((($mod->modname != 'forum') || (($instance->assessed > 0) && has_capability('mod/forum:rate', $mcontext))) && // Only include forums that are assessed only by teachers.
                            isset($mod_grades_array[$mod->modname])) {
                        $modgrades = new Object();
                        if (!function_exists($gradefunction) || !($modgrades->grades = $gradefunction($instance))) {
                            $modgrades->grades = array();
                        }

                        if (!empty($modgrades)) {
                            /// Store the number of ungraded entries for this group.
                            if (is_array($modgrades->grades)) {
                                $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                                $numgraded = count($gradedarray);
                                $numstudents = count($students);
                                $ungradedfunction = $mod->modname . '_count_ungraded';
                                if (function_exists($ungradedfunction)) {
                                    $extra = false;
                                    if($resubmission){
                                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $show, $extra, $instance, $resubmission);
                                    }else{
                                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $show, $extra, $instance);
                                    }

                                } else if ($show == 'unmarked') {
                                    $ung = $numstudents - $numgraded;
                                } else if ($show == 'marked') {
                                    $ung = $numgraded;
                                } else {
                                    $ung = $numstudents - $numgraded;
                                }

                                $columnungraded[] = $ung;
                                $totungraded += $ung;
                            } else {
                                $columnungraded[] = 0;
                            }

                            /// If we haven't specifically selected a mid, look for the oldest ungraded one.

                            if (($mid == 0) && !empty($ung)) {
                                $oldestfunc = $mod->modname . '_oldest_ungraded';
                                if (function_exists($oldestfunc)) {
                                    $told = $oldestfunc($mod->instance);
                                    if (empty($cold) || ($told < $cold)) {
                                        $cold = $told;
                                        $cmid = $mod->id;
                                        $mid = $mod->id;
                                        $selectedmod = $instance;
                                        $selectedfunction = $mod_grades_array[$mod->modname];
                                        $cm = $mod;
                                    }
                                }
                            }

                            /// Get the function for the selected mod.
                            if ($mid == $mod->id) {
                                $selectedmod = $instance;
                                $selectedfunction = $mod_grades_array[$mod->modname];
                                $cm = $mod;
                            }

                            if (!empty($modgrades->maxgrade)) {
                                if ($mod->visible) {
                                    $maxgrade = "$strmax: $modgrades->maxgrade";
                                    $maxgradehtml = "<BR>$strmax: $modgrades->maxgrade";
                                } else {
                                    $maxgrade = "$strmax: $modgrades->maxgrade";
                                    $maxgradehtml = "<BR><FONT class=\"dimmed_text\">$strmax: $modgrades->maxgrade</FONT>";
                                }
                            } else {
                                $maxgrade = "";
                                $maxgradehtml = "";
                            }

                            $image = "<A HREF=\"$CFG->wwwroot/mod/$mod->modname/view.php?id=$mod->id\"" .
                                    "   TITLE=\"$mod->modfullname\">" .
                                    "<IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/$mod->modname/pix/icon.gif\" " .
                                    "HEIGHT=16 WIDTH=16 ALT=\"$mod->modfullname\"></A>";
                            if (($view == 'less') && (strlen($instance->name) > 16)) {
                                $name = substr($instance->name, 0, 16) . '&hellip;';
                            } else {
                                $name = $instance->name;
                            }
                            if ($mod->visible) {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="assignmentlist" href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                                        $course->id . '&show=' . $show . '&sort=' . $sort . '&view=' . $view . '&mid=' . $mod->id . '">' . $name . '</a></div>';
                            } else {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="dimmed assignmentlist" href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                                        $course->id . '&show=' . $show . '&sort=' . $sort . '&view=' . $view . '&mid=' . $mod->id . '">' . $name . '</a></div>';
                            }
                        }
                    }
                }
            }
        }
    }

} // a new Moodle nesting record? ;-)
/// Set mid to cmid if there wasn't a mid and there is a cmid.
if (empty($mid) && !empty($cmid)) {
    $mid = $cmid;
}

/// Setup selection options.
$button = '';

/// Check to see if groups are being used in this assignment
if (!empty($cm)) {
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
        //$currentgroup = groups_get_activity_group($cm, true);
        $groupform = groups_print_activity_menu($cm, $CFG->wwwroot . '/blocks/fn_marking/' . "fn_gradebook.php?courseid=$courseid&mid=$mid&show=$show&sort=$sort&dir=$dir&mode=single&view=$view", true);
    } else {
        $currentgroup = false;
        $groupform = '';
    }
} else {
    $groupform = '';
}

/// Print header
global $PAGE;
$navlinks = array(array('name' => $strgrades, 'link' => '', 'type' => 'misc'));
$navigation = build_navigation($navlinks);
$button = '<tr><td>' . $groupform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $sortform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $viewform . '</td>' .
        '</tr>';
print_header_simple($strgrades, $course->fullname . ': ' . $strgrades, $navigation, '', '', true, $button, '');

echo '<table border="0" cellpadding="5" cellspacing="0" style="margin: auto;"><tr><td>';
include_once('fn_gradebook.html');
echo '</td></tr></table>';


echo $OUTPUT->footer($course);
?>
