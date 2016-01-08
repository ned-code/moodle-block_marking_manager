<?php

// Displays all grades for a course
global $CFG, $DB, $OUTPUT, $course;
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/plagiarismlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/lib/outputrenderers.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/fn_marking/js/popup.js');
$PAGE->requires->js('/blocks/fn_marking/js/quiz.js');

if (isset($CFG->noblocks)){
    if ($CFG->noblocks){
        $PAGE->set_pagelayout('markingmanager');
    }else{
        $PAGE->set_pagelayout('course');
    }
}else{
    $PAGE->set_pagelayout('course');
}

$courseid = required_param('courseid', PARAM_INT);      // course id
$mid = optional_param('mid', 0, PARAM_INT); // mod id to look at
$cmid = 0;                                 // If no mid is specified, we'll select one in this variable.

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


$unsubmitted = optional_param('unsubmitted', '0', PARAM_INT);
$activity_type = optional_param('activity_type', '0', PARAM_RAW);
$participants = optional_param('participants', '0', PARAM_INT);

$include_orphaned = get_config('block_fn_marking','include_orphaned');

set_current_group($courseid, $group);

// KEEP SEPARATE CONFIG.
$keepseparate = 1;//Default value
if ($block_config = fn_get_block_config ($courseid)) {
    if (isset($block_config->keepseparate)) {
        $keepseparate = $block_config->keepseparate;
    }
}

$PAGE->set_url('/fn_gradebook.php', array(
    'courseid' => $courseid,
    'mid' => $mid,
    'view' => $view,
    'show' => $show,
    'dir' => $dir));

$pageparams = array('courseid'=>$courseid,
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
$context = context_course::instance($course->id);

$cobject = new stdClass();
$cobject->course = $course;

if (!$isteacher = has_capability('moodle/grade:viewall', $context)) {
    print_error("Only teachers can use this page!");
}

require_login($course->id);

/// Array of functions to call for grading purposes for modules.
$mod_grades_array = array(
    'assign' => 'assign.submissions.fn.php',
    'assignment' => 'assignment.submissions.fn.php',
    'quiz' => 'quiz.submissions.fn.php',
    'forum' => 'forum.submissions.fn.php',
);

//Filter modules
if ($activity_type) {
    foreach ($mod_grades_array as $key => $value) {
        if ($activity_type <> $key) {
            unset($mod_grades_array[$key]);
        }
    }
}

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

    $url = new moodle_url(
        '/fn_gradebook.php',
        array(
            'courseid' => $courseid,
            'mid' => $mid,
            'view' => $view,
            'show' => $show,
            'dir' => $dir
        )
    );

    $sortform = 'Sort: ' . $OUTPUT->single_select($url->out(), 'sort', $sortopts, $selected = $sort, '', $formid = 'fnsort');
} else {
    $sortform = '';
    $sort = 'date';
}

// The view options.
$viewopts = array('less' => 'Less', 'more' => 'More');
$urlview = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => $mid,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'activity_type' => $activity_type,
        'participants' => $participants,
        //'view' => $view,
        'group'=>$group
    )
);
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
        } else if ($module_name == 'assignment') {
            $showopts = array('unmarked' => 'Requires Grading',  'saved' => 'Draft', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
        } else if ($module_name == 'assign') {
            $assign = $DB->get_record('assign', array('id'=>$cm_module->instance));
            if ($assign->submissiondrafts) {
                $showopts = array('unmarked' => 'Requires Grading',  'saved' => 'Draft', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
            } else {
                $showopts = array('unmarked' => 'Requires Grading', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
            }
        } else if ($module_name == 'quiz') {
            $showopts = array('unmarked' => 'Requires Grading', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
        } else {
            $showopts = array();
        }
    } else {
        $showopts = array('unmarked' => 'Requires Grading',  'saved' => 'Draft', 'marked' => 'Graded', 'unsubmitted' => 'Not submitted');
    }

    if (!$keepseparate) {
        if (isset($showopts['saved'])) {
            unset($showopts['saved']);
        }
    }
    $urlshow = new moodle_url('fn_gradebook.php', array('courseid' => $courseid, 'dir' => $dir, 'sort' => $sort, 'view' => $view));
    $showform = $OUTPUT->single_select($urlshow, 'show', $showopts, $selected = $show, '', $formid = 'fnshow');
}



if ($mid) {
    if (!$course_module = $DB->get_record('course_modules', array('id' => $mid))) {
        print_error('invalidcoursemodule');
    }

    if (!$module = $DB->get_record('modules', array('id' => $course_module->module))) {
        print_error('invalidcoursemodule');
    }

    $modcontext = context_module::instance($course_module->id);
    $groupmode = groups_get_activity_groupmode($course_module);
    $currentgroup = groups_get_activity_group($course_module, true);
    //$users = get_enrolled_users($modcontext, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
} else {
    // if comes from course page
    $currentgroup = groups_get_course_group($course, true);
}

//get current group members
$group_members = groups_get_members_by_role($group, $courseid);

// Get a list of all students
if (!$students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id')) {
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
$modnames = get_module_types_names();
$modnamesplural = get_module_types_names(true);
//$modinfo = get_fast_modinfo($course->id);
//$mods = $modinfo->get_cms();
//$modnamesused = $modinfo->get_used_module_names();

//$mod_array = array($mods, $modnames, $modnamesplural, $modnamesused);

//$cobject->mods = &$mods;
//$cobject->modnames = &$modnames;
//$cobject->modnamesplural = &$modnamesplural;
//$cobject->modnamesused = &$modnamesused;
//$cobject->sections = &$sections;

//FIND CURRENT WEEK
$courseformatoptions = course_get_format($course)->get_format_options();
$courseformat = course_get_format($course)->get_format();
$course_numsections = $courseformatoptions['numsections'];

if ($courseformat == 'weeks') {
    $timenow = time();
    $weekdate = $course->startdate;    // this should be 0:00 Monday of that week
    $weekdate += 7200;                 // Add two hours to avoid possible DST problems

    $weekofseconds = 604800;
    $course_enddate = $course->startdate + ($weekofseconds * $course_numsections);

    //  Calculate the current week based on today's date and the starting date of the course.
    $currentweek = ($timenow > $course->startdate) ? (int)((($timenow - $course->startdate) / $weekofseconds) + 1) : 0;
    $currentweek = min($currentweek, $course_numsections);

    if ($view == "less") {
        $upto = min($currentweek, $course_numsections);
    } else {
        $upto = $course_numsections;
    }
} else {
    $upto = $course_numsections;
}

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
//print_object($selected_section);
foreach ($selected_section as $section_num) {
    $i = $section_num;
    if (isset($sections[$i])) {   // should always be true
        $section = $sections[$i];
        if ($section->sequence) {
            $sectionmods = explode(",", $section->sequence);
            foreach ($sectionmods as $sectionmod) {
                $mod = get_coursemodule_from_id('',$sectionmod, $course->id);
                $currentgroup = groups_get_activity_group($mod, true);
                //Filter if individual user selected

                if ($participants && $group) {
                    $participants_arr = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
                    if (isset($group_members[5]->users[$participants])) {
                        $students = array();
                        $students[$participants] = $DB->get_record('user', array('id'=>$participants));
                    } else {
                        $participants = 0;
                        $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
                    }
                } elseif ($participants && !$group) {
                    $students = array();
                    $students[$participants] = $DB->get_record('user', array('id'=>$participants));
                    $participants_arr = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
                } else {
                    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
                    $participants_arr = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
                }

                /// Don't count it if you can't see it.
                $mcontext = context_module::instance($mod->id);
                if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
                    continue;
                }

                $instance = $DB->get_record($mod->modname, array("id" => $mod->instance));
                $libfile = $CFG->dirroot . '/mod/' . $mod->modname . '/lib.php';
                if (file_exists($libfile)) {
                    require_once($libfile);
                    $gradefunction = $mod->modname . "_get_user_grades";
                    if ((($mod->modname != 'forum') || (($instance->assessed > 0) && has_capability('mod/forum:rate', $mcontext))) && // Only include forums that are assessed only by teachers.
                            isset($mod_grades_array[$mod->modname])) {
                        $modgrades = new stdClass();
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
                                    $ung = $ungradedfunction($instance->id, $gradedarray, $students, $show, $extra, $instance, $keepseparate);
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
                                } else {
                                    $mid = $mod->id;
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

                            $image = "<a href=\"$CFG->wwwroot/mod/$mod->modname/view.php?id=$mod->id\"" .
                                    "   title=\"$mod->name\">" .
                                    "<IMG border=0 VALIGN=absmiddle src=\"$CFG->wwwroot/mod/$mod->modname/pix/icon.png\" " .
                                    "height=16 width=16 alt=\"$mod->name\"></a>";
                            if (($view == 'less') && (strlen($instance->name) > 16)) {
                                $name = substr($instance->name, 0, 16) . '&hellip;';
                            } else {
                                $name = $instance->name;
                            }
                            $mod_url = new moodle_url('/blocks/fn_marking/fn_gradebook.php', array(
                                'courseid'=> $course->id,
                                'show'=> $show,
                                'sort'=> $sort,
                                'view'=> $view,
                                'mid'=> $mod->id,
                                'activity_type'=>$activity_type,
                                'group'=>$group,
                                'participants' => $participants
                            ));

                            if ($mod->visible) {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="assignmentlist" href="' . $mod_url->out() . '">' . $name . '</a></div>';
                            } else {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="dimmed assignmentlist" href="' . $mod_url->out() . '">' . $name . '</a></div>';
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
$PAGE->navbar->add($strgrades);
$button = '<tr><td>' . $groupform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $sortform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $viewform . '</td>' .
        '</tr>';

$PAGE->set_title($strgrades);
$PAGE->set_heading($course->fullname . ': ' . $strgrades);

echo $OUTPUT->header();

//ACTIVITY TYPES
$activity_type_opts = array(
    '0' => 'All types',
    'assign' => 'Assignments',
    'forum' => 'Forums',
    'quiz' => 'Quizzes',
);
$activity_type_url = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => 0,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        //'activity_type' => $activity_type,
        'participants' => $participants,
        'view' => $view,
        'group'=>$group
    )
);
$activity_type_select = new single_select($activity_type_url, 'activity_type', $activity_type_opts, $activity_type, '');
$activity_type_select->formid = 'fn_activity_type';
$activity_type_select->label = 'Activity Type';
$activity_type_form = '<div class="groupselector">'.$OUTPUT->render($activity_type_select).'</div>';

//PARTICIPANTS
$participants_opts = array('0' => 'All participants');
if ($group_members) {
    foreach ($group_members[5]->users as $group_member) {
        $participants_opts[$group_member->id] = fullname($group_member);
    }
} else {
    foreach ($participants_arr as $group_member) {
        $participants_opts[$group_member->id] = fullname($group_member);
    }
}
$participants_url = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => $mid,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'activity_type' => $activity_type,
        //'participants' => $participants,
        'view' => $view,
        'group'=>$group
    )
);
$participants_select = new single_select($participants_url, 'participants', $participants_opts, $participants, '');
$participants_select->formid = 'fn_participants';
$participants_select->label = 'Participants';
$participants_form = '<div class="groupselector">'.$OUTPUT->render($participants_select).'</div>';


echo '<div class="fn-menuwrapper">';
echo $activity_type_form . "&nbsp;&nbsp;";

$group_url = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => $mid,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'activity_type' => $activity_type,
        'participants' => $participants,
        'view' => $view
    )
);
groups_print_course_menu($course, $group_url->out());
echo "&nbsp;&nbsp;";
echo $participants_form . "&nbsp;&nbsp;";
echo $viewform . " ";
echo '</div>';

echo '<table border="0" cellpadding="5" cellspacing="0" style="margin: auto;"><tr><td>';

$showtopmessage = get_config('block_fn_marking', 'showtopmessage');
$topmessage     = get_config('block_fn_marking', 'topmessage');

$block_config = new stdClass();

if ($block_instance = $DB->get_record('block_instances', array('blockname'=>'fn_marking','parentcontextid'=>$context->id))){
    if (!empty($block_instance->configdata)) {
        $block_config = unserialize(base64_decode($block_instance->configdata));
    }
}

if(isset($block_config->showtopmessage) && isset($block_config->topmessage['text'])){
    if ($block_config->showtopmessage && $block_config->topmessage['text']){
        echo '<div id="marking-topmessage">'.$block_config->topmessage['text'].'</div>';

    } elseif($showtopmessage && $topmessage) {
        echo '<div id="marking-topmessage"><?php echo $topmessage; ?></div>';
    }
} elseif ($showtopmessage && $topmessage){
    echo '<div id="marking-topmessage">'.$topmessage.'</div>';
}
echo '
<div id="marking-interface">
    <table width="100%" class="markingmanagercontainer" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td valign="top" align="left" class="left-sec">
                <table width="100%" border="0" valign="top" align="center" class="fnmarkingblock">
                    <thead><tr>
                        <th valign="top" align="LEFT" nowrap="nowrap" class="header topline" colspan="2">
                            Marking Status
                        </th>
                    </tr>';

                    if (!empty($showform)) {
                        echo '<tr><th valign="top" align="LEFT" nowrap="nowrap" class="header bottomline" colspan="2">';
                        echo $showform.'</th></tr>';
                    }

                    echo '</thead><tbody>';

                    foreach ($columnhtml as $index => $column) {
                        if (strstr($column, 'mid='.$mid.'&')) {
                            $extra = ' class="highlight"';
                        } else {
                            $extra = ' class="normal"';
                        }

                        if ((strstr($column, 'mid='.$mid.'"')) && ($action == 'submitgrade') && (! @isset($_POST['nosaveandnext'])) && (! @isset($_POST['nosaveandprevious']))) {
                            if($show <> 'marked'){
                                $columnungraded[$index] -= 1;
                                $totungraded -= 1;
                            }
                        }

                        if (($columnungraded[$index] < 0.1) && ($view == 'less')){
                            continue;
                        } else {
                            echo "<tr $extra>" .
                                '<td style="color: red">' . $columnungraded[$index] . '</td>' .
                                "<td>$column</td>" .
                                '</tr>';
                        }
                    };

                    echo '<tr class="marking-total">
                        <td style="font-weight: bold;">'.$totungraded.'</td>
                        <td>Total '.$showopts[$show].'</td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <td align="left" valign="top" class="right-sec">';

                if (!empty($selectedfunction)) {
                    $iid = $selectedmod->id;
                    include $selectedfunction;
                }
                else {
                    echo '<div class="no-assign">No selected assignment</div>';
                }
                echo '
            </td>
        </tr>
        <tr>
        <td class="markingmanagercontainer-footer" colspan="2">
            <div><span class="markingmanagercontainer-footer-title">Plug-in name: <span>
                <a target="_blank" class="markingmanagercontainer-footer-link" href="https://moodle.org/plugins/view/block_fn_marking">FN Marking Manager</a> |
                <a target="_blank" class="markingmanagercontainer-footer-link" href="http://northernlinks.ca/docs/Marking_Manager_Manual.pdf">Download manual for this plug-in</a> |
                <a target="_blank" class="markingmanagercontainer-footer-link" href="https://github.com/fernandooliveira/moodle-block_marking_manager/issues">Report a problem with this plugin</a></div>
        <td>
        </tr>
    </table>
</div>';

echo '</td></tr></table>';


echo $OUTPUT->footer($course);