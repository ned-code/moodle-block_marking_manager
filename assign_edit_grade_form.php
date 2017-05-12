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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir."/formslib.php");
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot."/repository/lib.php");
require_once($CFG->dirroot."/grade/grading/lib.php");

class mod_assign_grading_form_fn extends moodleform {

    /** @var stores the advaned grading instance (if used in grading) */
    private $advancegradinginstance;

    function definition() {

        global $OUTPUT, $CFG, $DB, $PAGE, $USER, $ME;

        $mform = & $this->_form;
        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }

        list($assignment, $data, $params) = $this->_customdata;

        $editortoggle = get_config('block_fn_marking', 'editortoggle');
        $onlineeditor = get_user_preferences('block_fn_marking_onlineeditor',  'hide');

        $rownum = $params['rownum'];
        $last = $params['last'];
        $useridlist = $params['useridlist'];
        $userid = $useridlist[$rownum];

        $attemptnumber = $params['attemptnumber'];
        $maxattemptnumber = isset($params['maxattemptnumber']) ? $params['maxattemptnumber'] : $params['attemptnumber'];

        $user = $DB->get_record('user', array('id' => $userid));

        // Overriden grade check.
        $sql = "SELECT gg.*
                  FROM {grade_items} gi 
            INNER JOIN {grade_grades} gg 
                    ON gi.id = gg.itemid 
                 WHERE gi.itemtype = 'mod' 
                   AND gi.itemmodule = 'assign' 
                   AND gi.iteminstance = ? 
                   AND gg.userid = ?";
        $overriden = false;
        if ($gradegrade = $DB->get_record_sql($sql, array($assignment->get_instance()->id, $userid))) {
            if ($gradegrade->overridden > 0) {
                $overriden = $gradegrade;
            }
        }

        $submission = block_fn_marking_get_user_submission($assignment, $userid, false);
        $submissiongroup = null;
        $submissiongroupmemberswhohavenotsubmitted = array();
        $teamsubmission = null;
        $notsubmitted = array();
        if ($assignment->get_instance()->teamsubmission) {
            $teamsubmission = $assignment->get_group_submission($userid, 0, false);
            $submissiongroup = $assignment->get_submission_group($userid);
            $groupid = 0;
            if ($submissiongroup) {
                $groupid = $submissiongroup->id;
            }
            $notsubmitted = $assignment->get_submission_group_members_who_have_not_submitted($groupid, false);
            if (isset($submissiongroup->name)) {
                $groupname = ' ('.$submissiongroup->name.')';
            } else {
                $groupname = ' (Default group)';
            }
        } else {
            $groupname = '';
        }

        // Start the table.
        $gradeviewclass = 'default-grade-view-nobtn';
        if (count($useridlist) > 1) {
            $gradeviewclass = 'default-grade-view';
            $mform->addElement('html', '<div style="text-align:center; font-size:11px; margin-bottom:3px;">');

            $strprevious = get_string('previous');
            $strnext = get_string('next');

            if ($rownum > 0) {
                $mform->addElement('html', ' <input type="submit" id="id_nosaveandprevious" value="' .
                    $strprevious . '" name="nosaveandprevious"> ');
            } else {
                $mform->addElement('html', ' <input type="submit" id="id_nosaveandprevious" value="' .
                    $strprevious . '" name="nosaveandprevious" disabled="disabled"> ');
            }
            $mform->addElement('html', get_string('gradingstudentprogress', 'block_fn_marking',
                array('index' => $rownum + 1, 'count' => count($useridlist))));

            if (!$last) {
                $mform->addElement('html', ' <input type="submit" id="id_nosaveandnext" value="' .
                    $strnext . '" name="nosaveandnext"> ');
            } else {
                $mform->addElement('html', ' <input type="submit" id="id_nosaveandnext" value="' .
                    $strnext . '" name="nosaveandnext" disabled="disabled"> ');
            }

            $mform->addElement('html', '</div>');
        }
        $mform->addElement('html', '<div class="'.$gradeviewclass.'">');
        $cm = get_coursemodule_from_instance("assign", $assignment->get_instance()->id, $assignment->get_course()->id);
        $mform->addElement('html', '<a href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id.'"><img src="'.
            $OUTPUT->pix_url('popup', 'scorm').'"> '.
            get_string('moodledefaultview', 'block_fn_marking').'</a>');
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '<table border="0" cellpadding="0" cellspacing="0" border="1" width="100%"
            class="saprate-table">');

        // Print the marking header in first tr.
        $mform->addElement('html', '<tr>');

        $this->add_marking_header($user,
            $assignment->get_instance()->name,
            $assignment->is_blind_marking(),
            $assignment->get_uniqueid_for_user($userid),
            $assignment->get_course()->id,
            has_capability('moodle/site:viewfullnames', $assignment->get_course_context()),
            $rownum ,
            $last,
            $groupname,
            $assignment->get_course_module(),
            $params,
            $overriden
        );

        $mform->addElement('html', '</tr>');

        // Override menu.
        $urlparams = $params;
        if ($urlparams['useridlist']) {
            unset($urlparams['useridlist']);
        }
        if ($overriden) {
            $removeoverride = html_writer::link('#', get_string('removeoverride', 'block_fn_marking'),
                array(
                    'id' => 'ned-override-remover',
                    'userid' => $userid,
                    'mod' => 'assign',
                    'instance' => $assignment->get_instance()->id,
                    'action' => 'remove',
                    'sesskey' => sesskey(),
                )
            );

            $gradebookurl = new moodle_url('/grade/report/singleview/index.php',
                array(
                    'id' => $assignment->get_instance()->course,
                    'item' => 'grade',
                    'group' => '',
                    'itemid' => $gradegrade->itemid
                )
            );
            $opengradereport = html_writer::link($gradebookurl, get_string('opengradereport', 'block_fn_marking'), array('id' => 'open-grade-report-link'));
            $checkagain = html_writer::link('#', get_string('checkagain', 'block_fn_marking'), array('id' => 'open-grade-report-link-check'));
            $help = html_writer::link($ME, get_string('help', 'block_fn_marking'));
            $mform->addElement('html', '<tr>');
            $mform->addElement('html', '<td class="overriden-grade-menu" colspan="2">');
            //$mform->addElement('html', $acceptoverride.'&nbsp;&nbsp;&nbsp;');
            $mform->addElement('html', $removeoverride.'&nbsp;&nbsp;&nbsp;');
            $mform->addElement('html', $opengradereport.'&nbsp;&nbsp;&nbsp;');
            $mform->addElement('html', $help);
            $mform->addElement('html', '<div class="right-align">'.$checkagain.'</div>');
            $mform->addElement('html', '</td>');
            $mform->addElement('html', '</tr>');
        }

        // Grading.
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td class="yellowcell" colspan="2">');

        $grade = $assignment->get_user_grade($userid, false, $attemptnumber);
        $flags = $assignment->get_user_flags($userid, false);

        // Add advanced grading.
        $gradingdisabled = $assignment->grading_disabled($userid);
        $gradinginstance = block_fn_marking_get_grading_instance($userid, $grade,  $gradingdisabled, $assignment);

        $gradinginfo = grade_get_grades($assignment->get_course()->id,
            'mod',
            'assign',
            $assignment->get_instance()->id,
            $userid);

        // Fix grade string for select form.
        if ($gradinginfo->items[0]->grades[$userid]->str_grade == "-") {
            $stugrade = '';
        } else {
            $stugrade = $gradinginfo->items[0]->grades[$userid]->str_grade;
        }

        $mform->addElement('html', '<table class="teacherfeedback" border="0" cellpadding="0" cellspacing="0" width="100%">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td width="50%" align="left">');
        $mform->addElement('html', '<b>Teacher\'s Feedback </b> <br /> <span class="teacher_feedback_info">'.
            $USER->firstname.' '.$USER->lastname.' <br /> '.userdate(time()));
        $mform->addElement('html', '</span>');
        $mform->addElement('html', '</td>');

        if ($gradinginstance) {
            // Rubric.
            $mform->addElement('html', '</tr>');
            $mform->addElement('html', '<tr>');
            $mform->addElement('html', '<td>');

            // Do not show if we are editing a previous attempt.
            if ($attemptnumber == -1 && $assignment->get_instance()->attemptreopenmethod != ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
                $attemptnumber = 0;
                if ($submission) {
                    $attemptnumber = $submission->attemptnumber;
                }
                $maxattempts = $assignment->get_instance()->maxattempts;
                if ($maxattempts == ASSIGN_UNLIMITED_ATTEMPTS) {
                    $maxattempts = get_string('unlimitedattempts', 'assign');
                }

                $ismanual = $assignment->get_instance()->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL;
                $issubmission = !empty($submission);
                $isunlimited = $assignment->get_instance()->maxattempts == ASSIGN_UNLIMITED_ATTEMPTS;
                $islessthanmaxattempts = $issubmission && ($submission->attemptnumber < ($assignment->get_instance()->maxattempts - 1));

                if ($ismanual && (!$issubmission || $isunlimited || $islessthanmaxattempts)) {
                    $mform->addElement('checkbox', 'addattempt', 'Allow student to resubmit');
                }
            }

            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':',
                array('gradinginstance' => $gradinginstance));
            if ($gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
                $mform->setType('advancedgradinginstanceid', PARAM_INT);
            }
            $mform->addElement('html', '</td>');
            $mform->addElement('html', '</tr>');
        } else {
            $mform->addElement('html', '<td width="50%" align="right">');

            // Do not show if we are editing a previous attempt.
            if ($attemptnumber == -1 && $assignment->get_instance()->attemptreopenmethod != ASSIGN_ATTEMPT_REOPEN_METHOD_NONE) {
                $attemptnumber = 0;
                if ($submission) {
                    $attemptnumber = $submission->attemptnumber;
                }
                $maxattempts = $assignment->get_instance()->maxattempts;
                if ($maxattempts == ASSIGN_UNLIMITED_ATTEMPTS) {
                    $maxattempts = get_string('unlimitedattempts', 'assign');
                }

                $ismanual = $assignment->get_instance()->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL;
                $issubmission = !empty($submission);
                $isunlimited = $assignment->get_instance()->maxattempts == ASSIGN_UNLIMITED_ATTEMPTS;
                $islessthanmaxattempts = $issubmission && ($submission->attemptnumber < ($assignment->get_instance()->maxattempts - 1));

                if ($ismanual && (!$issubmission || $isunlimited || $islessthanmaxattempts)) {
                    $mform->addElement('checkbox', 'addattempt', 'Allow student to resubmit');
                }
            }

            // Use simple direct grading.
            if ($assignment->get_instance()->grade > 0) {

                $attributes = array();
                $attributes['size'] = 6;
                if ($gradingdisabled) {
                    $attributes['disabled'] = 'disabled';
                }

                $label = get_string('gradeoutof', 'assign', $assignment->get_instance()->grade).':';
                $gradingelement = $mform->addElement('text', 'grade', $label, $attributes);
                $mform->setDefault('grade', $stugrade);
                $mform->setType('grade', PARAM_FLOAT);

                if ($gradingdisabled) {
                    $gradingelement->freeze();
                }
            } else {
                $grademenu = make_grades_menu($assignment->get_instance()->grade);
                if (count($grademenu) > 0) {
                    $gradingelement = $mform->addElement('select', 'grade', get_string('grade').':', $grademenu);
                    $mform->setType('grade', PARAM_INT);
                    if ($gradingdisabled) {
                        $gradingelement->freeze();
                    }
                } else {
                    $mform->addElement('html', html_writer::div(get_string('nograde', 'block_fn_marking'), 'nograde-wrapper'));
                }
            }
            if ($editortoggle) {
                if ($onlineeditor == 'hide') {
                    $editordisablebuttontxt = 'showonlineeditor';
                } else {
                    $editordisablebuttontxt = 'hideonlineeditor';
                }
                $editordisablebutton = html_writer::div(
                    html_writer::link('#',
                        get_string($editordisablebuttontxt, 'block_fn_marking'),
                        array(
                            'class' => 'ned-change-html-editor',
                            'data-status' => $editordisablebuttontxt
                        )
                    ), 'ned-change-html-editor-wrapper'
                );
                $mform->addElement('html', $editordisablebutton);
            }

            $mform->addElement('html', '</td>');
            $mform->addElement('html', '</tr>');
        }

        $mform->addElement('html', '</table>');
        if ($editortoggle) {
            if ($onlineeditor == 'hide') {
                $USER->preference['htmleditor'] = 'textarea';
            }
        }
        // Let feedback plugins add elements to the grading form.
        $feedbackplugins = block_fn_marking_load_plugins('assignfeedback', $assignment);

        foreach ($feedbackplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {

                if ($plugin->get_type() == 'file') {
                    $mform->addElement('html', '<br />');
                }

                $plugin->get_form_elements_for_user($grade, $mform, $data, $userid);
            }
        }

        // Hidden params.
        $mform->addElement('hidden', 'id', $assignment->get_course_module()->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'rownum', $rownum);
        $mform->setType('rownum', PARAM_INT);
        $mform->setConstant('rownum', $rownum);

        $mform->addElement('hidden', 'useridlist', implode(',', $useridlist));
        $mform->setType('useridlist', PARAM_TEXT);

        $mform->addElement('hidden', 'ajax', optional_param('ajax', 0, PARAM_INT));
        $mform->setType('ajax', PARAM_INT);

        if ($assignment->get_instance()->teamsubmission) {
            $mform->addElement('selectyesno', 'applytoall', get_string('applytoteam', 'assign'));
            $mform->setDefault('applytoall', 1);
        }
        $mform->addElement('hidden', 'action', 'submitgrade');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');

        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td class="bluecell" colspan="2">');

        $mform->addElement('html', '<table class="studentsubmission" border="0" cellpadding="0" cellspacing="0" width="100%">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="top" width="50%" align="left">');

        if (($assignment->can_view_submission($userid)) || ($params['readonly'])) {

            $gradelocked = ($flags && $flags->locked) || $assignment->grading_disabled($userid);
            $extensionduedate = null;
            if ($flags) {
                $extensionduedate = $flags->extensionduedate;
            }
            $showedit = $assignment->submissions_open($userid) && ($assignment->is_any_submission_plugin_enabled());

            if ($teamsubmission) {
                $showsubmit = $showedit && $teamsubmission && ($teamsubmission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
            } else {
                $showsubmit = $showedit && $submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
            }
            if (!$assignment->get_instance()->submissiondrafts) {
                $showsubmit = false;
            }
            $viewfullnames = has_capability('moodle/site:viewfullnames', $assignment->get_course_context());

            // Moodle version check.
            $version = explode('.', $CFG->version);
            $version = reset($version);
            // Moodle 3.0 and greater.
            if ($version >= 2015111600) {
                $usergroups = $assignment->get_all_groups($user->id);

                $status = new assign_submission_status($assignment->get_instance()->allowsubmissionsfromdate,
                    $assignment->get_instance()->alwaysshowdescription,
                    $submission,
                    $assignment->get_instance()->teamsubmission,
                    $teamsubmission,
                    $submissiongroup,
                    $notsubmitted,
                    $assignment->is_any_submission_plugin_enabled(),
                    $gradelocked,
                    block_fn_marking_is_graded($userid, $assignment),
                    $assignment->get_instance()->duedate,
                    $assignment->get_instance()->cutoffdate,
                    $assignment->get_submission_plugins(),
                    $assignment->get_return_action(),
                    $assignment->get_return_params(),
                    $assignment->get_course_module()->id,
                    $assignment->get_course()->id,
                    assign_submission_status::GRADER_VIEW,
                    $showedit,
                    $showsubmit,
                    $viewfullnames,
                    $extensionduedate,
                    $assignment->get_context(),
                    $assignment->is_blind_marking(),
                    '',
                    $assignment->get_instance()->attemptreopenmethod,
                    $assignment->get_instance()->maxattempts,
                    $assignment->get_grading_status($userid),
                    $assignment->get_instance()->preventsubmissionnotingroup,
                    $usergroups);
                // Moodle 2.9 and greater.
            } else if ($version >= 2015051100) {
                $status = new assign_submission_status($assignment->get_instance()->allowsubmissionsfromdate,
                    $assignment->get_instance()->alwaysshowdescription,
                    $submission,
                    $assignment->get_instance()->teamsubmission,
                    $teamsubmission,
                    $submissiongroup,
                    $notsubmitted,
                    $assignment->is_any_submission_plugin_enabled(),
                    $gradelocked,
                    block_fn_marking_is_graded($userid, $assignment),
                    $assignment->get_instance()->duedate,
                    $assignment->get_instance()->cutoffdate,
                    $assignment->get_submission_plugins(),
                    $assignment->get_return_action(),
                    $assignment->get_return_params(),
                    $assignment->get_course_module()->id,
                    $assignment->get_course()->id,
                    assign_submission_status::GRADER_VIEW,
                    $showedit,
                    $showsubmit,
                    $viewfullnames,
                    $extensionduedate,
                    $assignment->get_context(),
                    $assignment->is_blind_marking(),
                    '',
                    $assignment->get_instance()->attemptreopenmethod,
                    $assignment->get_instance()->maxattempts,
                    $assignment->get_grading_status($userid),
                    $assignment->get_instance()->preventsubmissionnotingroup);
            } else {
                $status = new assign_submission_status($assignment->get_instance()->allowsubmissionsfromdate,
                    $assignment->get_instance()->alwaysshowdescription,
                    $submission,
                    $assignment->get_instance()->teamsubmission,
                    $teamsubmission,
                    $submissiongroup,
                    $notsubmitted,
                    $assignment->is_any_submission_plugin_enabled(),
                    $gradelocked,
                    block_fn_marking_is_graded($userid, $assignment),
                    $assignment->get_instance()->duedate,
                    $assignment->get_instance()->cutoffdate,
                    $assignment->get_submission_plugins(),
                    $assignment->get_return_action(),
                    $assignment->get_return_params(),
                    $assignment->get_course_module()->id,
                    $assignment->get_course()->id,
                    assign_submission_status::GRADER_VIEW,
                    $showedit,
                    $showsubmit,
                    $viewfullnames,
                    $extensionduedate,
                    $assignment->get_context(),
                    $assignment->is_blind_marking(),
                    '',
                    $assignment->get_instance()->attemptreopenmethod,
                    $assignment->get_instance()->maxattempts,
                    $assignment->get_grading_status($userid));
            }
        }

        // Show graders whether this submission is editable by students.
        if ($status->view == assign_submission_status::GRADER_VIEW) {
            if ($status->canedit) {
                $editingstatus = get_string('submissioneditable', 'assign');
            } else {
                $editingstatus = get_string('submissionnoteditable', 'assign');
            }
        }

        // Last modified.
        $tsubmission = $status->teamsubmission ? $status->teamsubmission : $status->submission;

        if ($tsubmission) {
            $submissiontime = userdate($tsubmission->timemodified, "%d %B %Y, %I:%M %p");
        } else {
            $submissiontime = '-';
        }

        $mform->addElement('html', '<b>Student\'s Submission </b> <span class="editingstatus">'.$submissiontime.'</span>');
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '<td valign="top" width="50%" align="right">');
        $mform->addElement('html', '<span class="editingstatus">Editing Status: <span class="editingstatus_msg">' .
            $editingstatus.'</span></span><br />');
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');
        $mform->addElement('html', '</table>');

        if ($tsubmission) {
            foreach ($status->submissionplugins as $plugin) {
                $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    $pluginshowsummary) {

                    $pluginname = $plugin->get_name();

                    $submissionplugin = new assign_submission_plugin_submission($plugin,
                        $tsubmission,
                        assign_submission_plugin_submission::SUMMARY,
                        $status->coursemoduleid,
                        $status->returnaction,
                        $status->returnparams);

                    if ($plugin->get_name() == 'Online text') {
                        $onlinetext = $DB->get_record('assignsubmission_onlinetext',
                            array('submission' => $submission->id));

                        $mform->addElement('hidden', 'submissionid', $submission->id);
                        $mform->setType('submissionid', PARAM_INT);

                        $options = array('cols' => '82');

                        $mform->addElement('html', '<div class="online-submission-wrapper">'.$onlinetext->onlinetext.'</div>');
                    } else {
                        if ((! isset($params['savegrade'])) && ((! $params['readonly'])
                                || ($plugin->get_name() != 'Submission comments'))) {
                            $mform->addElement('html', '<div class="fn_plugin_wrapper_outer">');
                            $mform->addElement('html', '<div class="fn_plugin_wrapper">'.$pluginname.'<br />');
                            $o = $assignment->get_renderer()->render($submissionplugin);
                            $mform->addElement('html', $o.'</div>');
                            $mform->addElement('html', '</div>');
                        }
                    }
                }
            }

        }

        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');
        // Close the table.
        $mform->addElement('html', '</table>');

        $mform->addElement('hidden', 'courseid', $params['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'show', $params['show']);
        $mform->setType('show', PARAM_RAW);

        $mform->addElement('hidden', 'mid', $params['mid']);
        $mform->setType('mid', PARAM_INT);

        $mform->addElement('hidden', 'dir', $params['dir']);
        $mform->setType('dir', PARAM_RAW);

        $mform->addElement('hidden', 'timenow', $params['timenow']);
        $mform->setType('timenow', PARAM_INT);

        $mform->addElement('hidden', 'sort', $params['sort']);
        $mform->setType('sort', PARAM_RAW);

        $mform->addElement('hidden', 'view', $params['view']);
        $mform->setType('view', PARAM_RAW);

        $mform->addElement('hidden', 'activity_type', $params['activity_type']);
        $mform->setType('activity_type', PARAM_TEXT);

        $mform->addElement('hidden', 'group', $params['group']);
        $mform->setType('group', PARAM_INT);

        $mform->addElement('hidden', 'participants', $params['participants']);
        $mform->setType('participants', PARAM_INT);

        $mform->addElement('hidden', 'expand', $params['expand']);
        $mform->setType('expand', PARAM_INT);

        $mform->addElement('hidden', 'attemptnumber', $params['attemptnumber']);
        $mform->setType('attemptnumber', PARAM_INT);

        if ($data) {
            $this->set_data($data);
        }

    }

    /**
     * print the marking header section
     *
     */
    public function add_marking_header($user, $name, $blindmarking, $uniqueidforuser, $courseid, $viewfullnames,
                                       $rownum , $last, $groupname, $cm, $params, $overriden=false) {
        global $CFG, $DB, $OUTPUT;

        $mform = & $this->_form;
        if ($overriden) {
            if ($overriden->finalgrade) {
                $headlass = 'markingmanager-head-orange';
            } else {
                $headlass = 'markingmanager-head-red';
            }
        } else {
            $headlass = 'markingmanager-head';
        }
        $mform->addElement('html', '<td width="40" valign="top" align="center"
            class="'.$headlass.' marking_rightBRD">' . "\n");

        $o = '';
        if ($blindmarking) {
            $o .= get_string('hiddenuser', 'assign') . $uniqueidforuser;
        } else {
            $o .= $OUTPUT->user_picture($user);
        }
        $mform->addElement('html', $o);

        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="100%" valign="top" align="left" class="'.$headlass.'">');

        $mform->addElement('html', '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="name-date">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="middle" width="65%" class="leftSide">');
        $mform->addElement('html', '<a target="_blank" class="marking_header_link"
            href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$courseid.'">' .
            fullname($user, true) . '</a>'. $groupname);
        $mform->addElement('html', '<br / ><span class="marking_header_link">Assignment: </span><a target="_blank"
            class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id.'">'.
            $name.'</a>');
        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="35%" align="right" class="rightSide">');

        if ($overriden) {
            $locked = '<img class="ned-locked-icon" width="16" height="16" alt="Locked" src="'.$OUTPUT->pix_url('t/locked', '').'">';
            $mform->addElement('html', get_string('gradeoverridedetected', 'block_fn_marking').' '.$locked);
        } else {
            $buttonarray = array();
            if (isset($params['readonly'])) {
                if (!$params['readonly']) {
                    $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('save', 'block_fn_marking'));
                }
            } else {
                $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('save', 'block_fn_marking'));
            }

            if (!empty($buttonarray)) {
                $mform->addGroup($buttonarray, 'navar', '', array(' '), false);
                $mform->disabledIf('navar', 'grade', 'eq', -1);
            }
        }
        $mform->addElement('html', '&nbsp;</td>');

        $mform->addElement('html', '</tr>');
        $mform->addElement('html', '</table>');

        $mform->addElement('html', '</td>');
    }

}