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
require_once($CFG->dirroot . '/lib/plagiarismlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/lib/outputrenderers.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/fn_marking/js/popup.js');
$PAGE->requires->js('/blocks/fn_marking/js/quiz.js');
$PAGE->requires->js('/blocks/fn_marking/js/fullscreen.js', true);
$PAGE->requires->css('/blocks/fn_marking/css/styles.css');

if ($layout = get_config('block_fn_marking', 'pagelayout')) {
    $PAGE->set_pagelayout($layout);
} else {
    $PAGE->set_pagelayout('course');
}

user_preference_allow_ajax_update('block_fn_marking_zoom', PARAM_TEXT);
user_preference_allow_ajax_update('block_fn_marking_onlineeditor', PARAM_TEXT);
$hideblocks = get_user_preferences('block_fn_marking_zoom',  'nozoom');
$PAGE->add_body_class($hideblocks);


$courseid = required_param('courseid', PARAM_INT); // Course id.
$id = optional_param('id', 0, PARAM_INT); // Mod id to look at.
$mid = optional_param('mid', 0, PARAM_INT); // Mod id to look at.
$cmid = 0;                                 // If no mid is specified, we'll select one in this variable.

// From mod grade files.
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$timenow = optional_param('timenow', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Paging options.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

// Filtering Options.
$menushow = optional_param('menushow', 'unmarked', PARAM_ALPHA);
$sort = optional_param('sort', 'date', PARAM_ALPHANUM);
$show = optional_param('show', 'unmarked', PARAM_ALPHA);
$view = optional_param('view', 'less', PARAM_ALPHA);

$userid = optional_param('userid', 0, PARAM_INT);
$group = optional_param('group', 0, PARAM_INT);
$expand = optional_param('expand', 0, PARAM_INT);
$rownum = optional_param('rownum', 0, PARAM_INT);


$unsubmitted = optional_param('unsubmitted', '0', PARAM_INT);
$activitytype = optional_param('activity_type', '0', PARAM_TEXT);
$participants = optional_param('participants', '0', PARAM_INT);

$includeorphaned = get_config('block_fn_marking', 'include_orphaned');

if (!$course = $DB->get_record("course", array("id" => $courseid))) {
    print_error("Course ID was incorrect");
}

require_login($course);

// Check teacher's group.
/*
 if (($course->groupmode == SEPARATEGROUPS) && ($course->groupmodeforce)) {
    if ($usergroups = groups_get_all_groups($courseid, $USER->id)) {
        if (!isset($usergroups[$group])) {
            $group = key($usergroups);
        }
    }
}*/
$SESSION->currentgroup[$courseid] = $group;

// KEEP SEPARATE CONFIG.
$keepseparate = 1; // Default value.
if ($blockconfig = block_fn_marking_get_block_config ($courseid)) {
    if (isset($blockconfig->keepseparate)) {
        $keepseparate = $blockconfig->keepseparate;
    }
}

$PAGE->set_url(
    new moodle_url('/blocks/fn_marking/fn_gradebook.php',
        array('courseid' => $courseid, 'mid' => $mid, 'view' => $view, 'show' => $show, 'dir' => $dir)
    )
);

$pageparams = array(
    'courseid' => $courseid,
    'userid' => $userid,
    'mid' => $mid,
    'dir' => $dir,
    'group' => $group,
    'timenow' => $timenow,
    'action' => $action,
    'expand' => $expand,
    'activity_type' => $activitytype,
    'rownum' => $rownum,
    'page' => $page,
    'perpage' => $perpage,
    'menushow' => $menushow,
    'sort' => $sort,
    'view' => $view,
    'show' => $show
);

// Grab course context.
$context = context_course::instance($course->id);

$cobject = new stdClass();
$cobject->course = $course;

if (!$isteacher = has_capability('moodle/grade:viewall', $context)) {
    print_error("Only teachers can use this page!");
}

// Array of functions to call for grading purposes for modules.
$modgradesarray = block_fn_marking_supported_mods();

$gradingonly = false;
if (($action == 'submitgrade') && ($id)
    && !(optional_param('nosaveandprevious', null, PARAM_RAW))
    && !(optional_param('nosaveandnext', null, PARAM_RAW)) ) {
    $gradingonly = true;
    // Grade activity first.
    if (! $cmfograding = get_coursemodule_from_id('', $id)) {
        print_error("Course Module ID was incorrect");
    }
    $selectedfunction = $modgradesarray[$cmfograding->modname];
    if (!empty($selectedfunction)) {
        $iid = $cmfograding->instance;
        include($selectedfunction);

        if (!$mid) {
            $mid = 0;
        }
    }
    $gradingonly = false;
    $action = 'submitgrade';
}

// Filter modules.
if ($activitytype) {
    foreach ($modgradesarray as $key => $value) {
        if ($activitytype <> $key) {
            unset($modgradesarray[$key]);
        }
    }
}

// Array of functions to call to display grades for modules.
$modgradedisparray = block_fn_marking_supported_mods();

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
        'activity_type' => $activitytype,
        'participants' => $participants,
        'group' => $group
    )
);

$select = new single_select($urlview, 'view', $viewopts, $selected = $view, '');
$select->label =  html_writer::img($OUTPUT->pix_url('i/hide'), '');
$select->formid = 'fnview';
$viewform = '<div class="groupselector">'.$OUTPUT->render($select).'</div>';

// The show options.
if (($view == 'less') || ($view == 'more')) {
    if ($mid) {
        $cmmodule = $DB->get_record('course_modules', array('id' => $mid));
        $modulename = $DB->get_field('modules', 'name', array('id' => $cmmodule->module));

        if ($modulename == 'forum') {
            $showopts = array(
                'unmarked' => get_string('unmarked', 'block_fn_marking'),
                'marked' => get_string('marked', 'block_fn_marking'),
                'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
            );
        } else if ($modulename == 'assignment') {
            $showopts = array(
                'unmarked' => get_string('unmarked', 'block_fn_marking'),
                'saved' => get_string('saved', 'block_fn_marking'),
                'marked' => get_string('marked', 'block_fn_marking'),
                'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
            );
        } else if ($modulename == 'journal') {
            $showopts = array(
                'unmarked' => get_string('unmarked', 'block_fn_marking'),
                'marked' => get_string('marked', 'block_fn_marking'),
                'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
            );
        } else if ($modulename == 'assign') {
            $assign = $DB->get_record('assign', array('id' => $cmmodule->instance));
            if ($assign->submissiondrafts) {
                $showopts = array(
                    'unmarked' => get_string('unmarked', 'block_fn_marking'),
                    'saved' => get_string('saved', 'block_fn_marking'),
                    'marked' => get_string('marked', 'block_fn_marking'),
                    'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
                );
            } else {
                $showopts = array(
                    'unmarked' => get_string('unmarked', 'block_fn_marking'),
                    'marked' => get_string('marked', 'block_fn_marking'),
                    'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
                );
            }
        } else if ($modulename == 'quiz') {
            $showopts = array(
                'unmarked' => get_string('unmarked', 'block_fn_marking'),
                'marked' => get_string('marked', 'block_fn_marking'),
                'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
            );
        } else {
            $showopts = array();
        }
    } else {
        $showopts = array(
            'unmarked' => get_string('unmarked', 'block_fn_marking'),
            'saved' => get_string('saved', 'block_fn_marking'),
            'marked' => get_string('marked', 'block_fn_marking'),
            'unsubmitted' => get_string('unsubmitted', 'block_fn_marking')
        );
    }

    if (!$keepseparate) {
        if (isset($showopts['saved'])) {
            unset($showopts['saved']);
        }
    }

    $urlshow = new moodle_url(
        'fn_gradebook.php',
        array(
            'courseid' => $courseid,
            'dir' => $dir,
            'sort' => $sort,
            'view' => $view,
            'mid' => $mid,
            'activity_type' => $activitytype,
            'view' => $view,
            'group' => $group,
            'participants' => $participants,
        )
    );
    $showform = $OUTPUT->single_select($urlshow, 'show', $showopts, $selected = $show, '', $formid = 'fnshow');
}

if ($mid) {
    if (!$coursemodule = $DB->get_record('course_modules', array('id' => $mid))) {
        print_error('invalidcoursemodule');
    }

    if (!$module = $DB->get_record('modules', array('id' => $coursemodule->module))) {
        print_error('invalidcoursemodule');
    }

    $modcontext = context_module::instance($coursemodule->id);
    $groupmode = groups_get_activity_groupmode($coursemodule);
    $currentgroup = groups_get_activity_group($coursemodule, true);
} else {
    // If comes from course page.
    $currentgroup = groups_get_course_group($course, true);
}
// Get current group members.
$groupmembers = groups_get_members_by_role($group, $courseid);

// Get a list of all students.
if (!$students = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id')) {
    $students = array();
    $PAGE->set_title(get_string('course') . ': ' . $course->fullname);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string("nostudentsyet"));
    echo $OUTPUT->footer($course);
    exit;
}

if ($group === 0) {
    $groupstudents = block_fn_marking_mygroup_members($course->id, $USER->id);
    if ($groupstudents !== false) {
        $students = $groupstudents;
    }
}

$columnhtml = array();  // Accumulate column html in this array.
$columnungraded = array(); // Accumulate column graded totals in this array.
$totungraded = 0;

// Collect modules data.
$modnames = get_module_types_names();
$modnamesplural = get_module_types_names(true);

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

    if ($view == "less") {
        $upto = min($currentweek, $coursenumsections);
    } else {
        $upto = $coursenumsections;
    }
} else {
    $upto = $coursenumsections;
}

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

foreach ($selectedsection as $sectionnum) {
    $i = $sectionnum;
    if (isset($sections[$i])) {   // Should always be true.
        $section = $sections[$i];
        if ($section->sequence) {
            $sectionmods = explode(",", $section->sequence);
            foreach ($sectionmods as $sectionmod) {
                $mod = get_coursemodule_from_id('', $sectionmod, $course->id);

                // Filter if individual user selected.
                if ($participants && $group) {
                    // $participantsarr = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id');
                    if (isset($groupmembers[5]->users[$participants])) {
                        $students = array();
                        $students[$participants] = $DB->get_record('user', array('id' => $participants));
                    } else {
                        $participants = 0;
                        //$students = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id');
                    }
                } else if ($participants && !$group) {
                    $students = array();
                    $students[$participants] = $DB->get_record('user', array('id' => $participants));
                    //$participantsarr = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id');
                } else {
                    //$students = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id');
                    //$participantsarr = get_enrolled_users($context, 'mod/assign:submit', $group, 'u.*', 'u.id');
                }

                // Don't count it if you can't see it.
                $mcontext = context_module::instance($mod->id);
                if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
                    continue;
                }
                $instance = $DB->get_record($mod->modname, array("id" => $mod->instance));
                $libfile = $CFG->dirroot . '/mod/' . $mod->modname . '/lib.php';
                if (file_exists($libfile)) {
                    require_once($libfile);
                    $gradefunction = $mod->modname . "_get_user_grades";
                    if ((($mod->modname != 'forum') || (($instance->assessed > 0) && has_capability('mod/forum:rate', $mcontext)))
                        && isset($modgradesarray[$mod->modname])) {
                        $modgrades = new stdClass();
                        if (!function_exists($gradefunction) || !($modgrades->grades = $gradefunction($instance))) {
                            $modgrades->grades = array();
                        }
                        if (!empty($modgrades)) {
                            // Store the number of ungraded entries for this group.
                            if (is_array($modgrades->grades)) {
                                $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                                $numgraded = count($gradedarray);
                                $numstudents = count($students);
                                $ungradedfunction = 'block_fn_marking_' . $mod->modname . '_count_ungraded';
                                if (function_exists($ungradedfunction)) {
                                    $extra = false;
                                    $summary = $ungradedfunction($instance->id, $gradedarray, $students, $show,
                                        $extra, $instance, $keepseparate);
                                    $ung = $summary[$show];
                                } else if ($show == 'unmarked') {
                                    $ung = $numstudents - $numgraded;
                                    if (($action == 'submitgrade') && ($mid == $mod->id)) {
                                        --$ung;
                                    }
                                } else if ($show == 'marked') {
                                    $ung = $numgraded;
                                    if (($action == 'submitgrade') && ($mid == $mod->id)) {
                                        ++$ung;
                                    }
                                } else {
                                    $ung = $numstudents - $numgraded;
                                    if (($action == 'submitgrade') && ($mid == $mod->id)) {
                                        --$ung;
                                    }
                                }

                                $columnungraded[] = $ung;
                                $totungraded += $ung;
                            } else {
                                $columnungraded[] = 0;
                            }

                            if (($mid == $mod->id) && (end($columnungraded) == 0)) {
                                $mid = 0;
                            }

                            // If we haven't specifically selected a mid, look for the oldest ungraded one.
                            if (($mid == 0) && !empty($ung)) {
                                $oldestfunc = 'block_fn_marking_' . $mod->modname . '_oldest_ungraded';
                                if (function_exists($oldestfunc)) {
                                    $told = $oldestfunc($mod->instance);
                                    if (empty($cold) || ($told < $cold)) {
                                        $cold = $told;
                                        $cmid = $mod->id;
                                        $mid = $mod->id;
                                        $selectedmod = $instance;
                                        $selectedfunction = $modgradesarray[$mod->modname];
                                        $cm = $mod;
                                    }
                                } else {
                                    $mid = $mod->id;
                                }
                            }

                            // Get the function for the selected mod.
                            if ($mid == $mod->id) {
                                $selectedmod = $instance;
                                $selectedfunction = $modgradesarray[$mod->modname];
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
                                '<img class="actionicon" width="16" height="16" alt="'.$mod->name.'" src="'.$OUTPUT->pix_url('icon', $mod->modname).'">'.
                                "</a>";


                            if (($view == 'less') && (strlen($instance->name) > 16)) {
                                $name = substr($instance->name, 0, 30) . '&hellip;';
                            } else {
                                $name = $instance->name;
                            }
                            $modurl = new moodle_url('/blocks/fn_marking/fn_gradebook.php', array(
                                'courseid' => $course->id,
                                'show' => $show,
                                'sort' => $sort,
                                'view' => $view,
                                'mid' => $mod->id,
                                'activity_type' => $activitytype,
                                'group' => $group,
                                'participants' => $participants
                            ));

                            if ($mod->visible) {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="assignmentlist" href="' . $modurl->out() . '">' . $name . '</a></div>';
                            } else {
                                $columnhtml[] = '<div style="font-size: 85%">' . $image . ' ' .
                                        '<a class="dimmed assignmentlist" href="' . $modurl->out() . '">' . $name . '</a></div>';
                            }
                        }
                    }
                }
            }
        }
    }

}
// Set mid to cmid if there wasn't a mid and there is a cmid.
if (empty($mid) && !empty($cmid)) {
    $mid = $cmid;
}

// Setup selection options.
$button = '';

// Check to see if groups are being used in this assignment.
if (!empty($cm)) {
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used.
        $groupform = groups_print_activity_menu($cm, $CFG->wwwroot . '/blocks/fn_marking/' .
            "fn_gradebook.php?courseid=$courseid&mid=$mid&show=$show&sort=$sort&dir=$dir&mode=single&view=$view", true);
    } else {
        $currentgroup = false;
        $groupform = '';
    }
} else {
    $groupform = '';
}

// Print header.
$PAGE->navbar->add($strgrades);
$button = '<tr><td>' . $groupform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $sortform . '&nbsp;&nbsp;</td>' .
        '<td style="padding-left:10em;">' . $viewform . '</td>' .
        '</tr>';

$PAGE->set_title($strgrades);
$PAGE->set_heading($course->fullname . ': ' . $strgrades);

echo $OUTPUT->header();

// ACTIVITY TYPES.
$activitytypeopts = array(
    '0' => get_string('allactivitytypes', 'block_fn_marking'),
    'assign' => 'Assignments',
    'forum' => 'Forums',
    'journal' => 'Journal',
    'quiz' => 'Quizzes',
);
$activitytypeurl = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => 0,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'participants' => $participants,
        'view' => $view,
        'group' => $group
    )
);
$activitytypeselect = new single_select($activitytypeurl, 'activity_type', $activitytypeopts, $activitytype, '');
$activitytypeselect->formid = 'fn_activity_type';
$activitytypeselect->label =  html_writer::img($OUTPUT->pix_url('i/preview'), '');
$activitytypeform = '<div class="groupselector">'.$OUTPUT->render($activitytypeselect).'</div>';

// PARTICIPANTS.
$participantsopts = array('0' => get_string('allparticipants', 'block_fn_marking'),);
if ($groupmembers) {
    foreach ($groupmembers[5]->users as $groupmember) {
        $participantsopts[$groupmember->id] = fullname($groupmember);
    }
} else {
    foreach ($students as $groupmember) {
        $participantsopts[$groupmember->id] = fullname($groupmember);
    }
}
$participantsurl = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => $mid,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'activity_type' => $activitytype,
        'view' => $view,
        'group' => $group
    )
);
$participantsselect = new single_select($participantsurl, 'participants', $participantsopts, $participants, '');
$participantsselect->formid = 'fn_participants';
$participantsselect->label = html_writer::img($OUTPUT->pix_url('i/user'), '');
$participantsform = '<div class="groupselector">'.$OUTPUT->render($participantsselect).'</div>';

echo '<div class="fn-menuwrapper">';
echo $activitytypeform . "&nbsp;&nbsp;";

$groupurl = new moodle_url(
    'fn_gradebook.php',
    array(
        'courseid' => $courseid,
        'mid' => $mid,
        'dir' => $dir,
        'sort' => $sort,
        'show' => $show,
        'unsubmitted' => $unsubmitted,
        'activity_type' => $activitytype,
        'participants' => $participants,
        'view' => $view
    )
);
block_fn_marking_groups_print_course_menu($course, $groupurl->out(), false, $group);
echo "&nbsp;&nbsp;";
echo $participantsform . "&nbsp;&nbsp;";
echo $viewform . " ";

echo html_writer::link('#',
    html_writer::img($OUTPUT->pix_url('fullscreen', 'block_fn_marking'), ''),
    array('class' => 'ned-hide-blocks')
);

echo '</div>';

echo '<table class="block" border="0" cellpadding="5" cellspacing="0" style="margin: auto;"><tr><td>';

$showtopmessage = get_config('block_fn_marking', 'showtopmessage');
$topmessage     = get_config('block_fn_marking', 'topmessage');

$blockconfig = new stdClass();

if ($blockinstance = $DB->get_record('block_instances', array('blockname' => 'fn_marking', 'parentcontextid' => $context->id))) {
    if (!empty($blockinstance->configdata)) {
        $blockconfig = unserialize(base64_decode($blockinstance->configdata));
    }
}

if (isset($blockconfig->showtopmessage) && isset($blockconfig->topmessage['text'])) {
    if ($blockconfig->showtopmessage && $blockconfig->topmessage['text']) {
        echo '<div id="marking-topmessage">'.$blockconfig->topmessage['text'].'</div>';

    } else if ($showtopmessage && $topmessage) {
        echo '<div id="marking-topmessage"><?php echo $topmessage; ?></div>';
    }
} else if ($showtopmessage && $topmessage) {
    echo '<div id="marking-topmessage">'.$topmessage.'</div>';
}

// No course average calculation.
$nocorseaveragemsg = '';
if ($gradeitem = $DB->get_record('grade_items', array('courseid' => $courseid, 'itemtype' => 'course'))) {
    if ($gradeitem->gradetype == GRADE_TYPE_NONE) {
        $nocorseaveragemsg = '<div class="course-average-warning"><img class="actionicon" width="16" height="16" alt="" src="'.
            $OUTPUT->pix_url('i/risk_xss', '').'"> '.get_string('nocoursetotal', 'block_fn_mentor').'<div>';
    }
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

    if ((strstr($column, 'mid='.$mid.'"')) && ($action == 'submitgrade')
        && (! @isset($_POST['nosaveandnext']))
        && (! @isset($_POST['nosaveandprevious']))) {
        if ($show <> 'marked') {
            $columnungraded[$index] -= 1;
            $totungraded -= 1;
        }
    }

    if (($columnungraded[$index] < 0.1) && ($view == 'less')) {
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
                '. $nocorseaveragemsg .'
            </td>
            <td align="left" valign="top" class="right-sec">';

if (!isset($selectedmod)) {
    $selectedfunction = null;
}
if (!empty($selectedfunction)) {
    $iid = $selectedmod->id;
    include($selectedfunction);
    echo $o;
} else {
    echo '<div class="no-assign">No selected assignment</div>';
}
$pluginman = core_plugin_manager::instance();
$pluginfo = $pluginman->get_plugin_info('block_fn_marking');
echo '</td>
        </tr>
        <tr>
        <td colspan="2">
            '.block_fn_marking_footer().'
        <td>
        </tr>
    </table>
</div>';

echo '</td></tr></table>';
echo $OUTPUT->footer($course);