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

require_once("$CFG->libdir/formslib.php");
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once("$CFG->dirroot/repository/lib.php");
require_once("$CFG->dirroot/grade/grading/lib.php");

class mod_assignment_grading_form_fn extends moodleform {

    /** @var stores the advaned grading instance (if used in grading) */
    private $advancegradinginstance;

    public function definition() {
        global $OUTPUT, $CFG;
        $mform = & $this->_form;
        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }
        $formattr = $mform->getAttributes();
        $id = $this->_customdata->id;
        $mid = $this->_customdata->mid;
        $formattr['action'] = "fn_gradebook.php?id=$id&mid=$mid";

        $mform->setAttributes($formattr);
        // Start the table.
        $mform->addElement('html', '<table border="0" cellpadding="0" cellspacing="0"	 width="100%" class="saprate-table">');

        // Pprint the marking header in first tr.
        $mform->addElement('html', '<tr>');
        $this->add_marking_header();
        $mform->addElement('html', '</tr>');

        // Pprint the marking submission in the second tr.
        $mform->addElement('html', '<tr>');
        $this->add_marking_submissions();
        $mform->addElement('html', '</tr>');

        // Print the marking feedback in the second tr.
        $mform->addElement('html', '<tr>');
        $this->add_marking_feedback();
        $mform->addElement('html', '</tr>');

        // Close the table.
        $mform->addElement('html', '</table>');

        $mform->addElement('hidden', 'id', $this->_customdata->cm->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'submissionid', $this->_customdata->submission->id);
        $mform->setType('submissionid', PARAM_INT);

    }

    /**
     * print the marking header section
     *
     */
    public function add_marking_header() {
        global $DB, $OUTPUT;

        $mform = & $this->_form;
        $mform->addElement('html', '<td width="40" valign="top" align="center" rowspan="2"
                class="marking-head marking_rightBRD">' . "\n");

        $mform->addElement('html', $OUTPUT->user_picture($this->_customdata->user));
        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="100%" valign="top" align="left" nowrap="nowrap" class="marking-head">');

        $mform->addElement('html', '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="name-date">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="middle" width="40%" class="leftSide">');
        $mform->addElement('html', fullname($this->_customdata->user, true));
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '<td width="60%" align="right" class="rightSide">');
        $mform->addElement('html', userdate($this->_customdata->submission->timemodified).'<br/>'.($this->_customdata->lateness));
        $mform->addElement('html', '&nbsp;</td>');
        $mform->addElement('html', '</tr>');
        $mform->addElement('html', '</table>');

        $mform->addElement('html', '</td>');
    }

    /**
     * add the marking submission section
     *
     */
    public function add_marking_submissions() {
        global $CFG, $mid, $OUTPUT, $DB;
        $mform = & $this->_form;
        $mform->addElement('html', '<td class="marking-headB">');
        if ($this->_customdata->submission->timemodified) {
            $icon = $CFG->wwwroot . '/pix/f/text.gif';
            $icon2 = $CFG->wwwroot . '/blocks/fn_marking/pix/fullscreen_maximize.gif';
            $icon3 = $CFG->wwwroot . '/blocks/fn_marking/pix/completed.gif';
            $mform->addElement('html', '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="resourse-tab">');

            if (!isset($this->_customdata->submissions)) {
                $this->_customdata->submissions[] = $this->_customdata->submission;
            }
            $subcount = count($this->_customdata->submissions);
            $currcount = 0;
            foreach ($this->_customdata->submissions as $submission) {
                $currcount++;
                if ($submission->timemodified <= 0) {
                    $mform->addElement('static', 'notsubmittedyet', '', print_string('notsubmittedyet', 'assignment'));
                } else if ($submission->timemarked <= 0) {
                    // Saved section.
                    $mform->addElement('html', '<tr><td valign="top" align="left">');
                    if (($subcount > 1) && ($currcount == $subcount)) {
                        $mform->addElement('hidden', 'sub_id', $submission->id);
                        $mform->setType('sub_id', PARAM_INT);
                        $mform->addElement('hidden', 'sesskey', sesskey());
                        $mform->setType('sesskey', PARAM_ALPHANUM);
                        $mform->addElement('hidden', 'offset', '2');
                        $mform->setType('offset', PARAM_INT);
                    }
                    $this->add_submission_content();
                    $mform->addElement('html', '</td></tr>');
                } else {
                    // Marked section.
                    $mform->addElement('html', '<tr>');
                    $mform->addElement('html', '<td>');
                    if (($subcount > 1) && ($currcount == $subcount)) {
                        $mform->addElement('hidden', 'sub_id', $submission->id);
                    }
                    // Print student response files.
                    $this->add_submission_content();
                    $mform->addElement('html', '</td></tr>');
                }
            }
            $mform->addElement('html', '</table>');
        } else {
            $mform->addElement('static', 'notsubmitted', '', print_string('notsubmittedyet', 'assignment'));
        }

        $mform->addElement('html', '</td>');
    }

    /**
     * print the marking submission section
     *
     */
    public function add_marking_feedback() {
        global $CFG, $DB, $OUTPUT, $USER;
        $mform = & $this->_form;
        $timenow = time();
        $mform->addElement('html', '<td  colspan="2" align="center" >');
        $mform->addElement('hidden', 'timenow', $timenow);
        $mform->addElement('hidden', 'sub_id', $this->_customdata->submission->id);
        $mform->setType('sub_id', PARAM_INT);
        $mform->addElement('hidden', 'userid', $this->_customdata->submission->userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'saveuserid', "-1");
        $mform->setType('saveuserid', PARAM_INT);
        $mform->addElement('hidden', 'format', $this->_customdata->submissioncommentformat);
        $mform->setType('format', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);

        $svalue = 'Save feedback';
        if ($this->_customdata->submission->timemarked > $this->_customdata->submission->timemodified) {
            $style = ' style="background:#DDDDDD; color:#444444;"';
            $svalue = 'Update feedback';
        } else {
            $style = '';
        }
        $mform->addElement('html', '<table cellpadding="0" cellspacing="0" width="97%" border="0" class="grade-filter"><tr><td>');
        // Add grade section.
        $this->add_grades_section(); // Still some worked required.
        $mform->addElement('html', '</td><td align="right" valign="middle">');
        $mform->addElement('html', '</td></tr></table>');
        $mform->addElement('html', '<table width="96%" cellpadding="0" cellspacing="0" border="0" class="teacher-comments"><tr>');
        $mform->addElement('html', '<td valign="top" width="30%" align="left" style="font-size:15px;
                font-weight:bold; color:#fff; padding-bottom:0;">');

        $mform->addElement('html', '<strong>Teacher\'s Comment:</strong>');
        $mform->addElement('html', '</td><td align="right" width="66%" style="font-size:11px;
                font-weight:bold; color:#fff; padding-bottom:0;">');
        $timemarked = empty($this->_customdata->submission->timemarked) ? userdate(time()) : userdate(
            $this->_customdata->submission->timemarked);
        $mform->addElement('html', fullname($this->_customdata->teacher) . '&nbsp;&nbsp;' . $timemarked);
        $mform->addElement('html', '</td></tr>');
        $mform->addElement('html', '<tr><td colspan="2" class="comment-boxs">');
        $this->add_editor_section();
        $mform->addElement('html', '</td></tr></table>');

        // Add response file section.
        if (!$this->_customdata->gradingdisabled) {
            // Add responce file section.
            $mform->addElement('html', '<table cellpadding="0" cellspacing="0" width="97%" border="0" class=""><tr><td>');
            $this->add_response_file_section(); // Still some worked required.
            $mform->addElement('html', '</td></tr></table>');

            // Add mail notification section.
            $mform->addElement('html', '<table cellpadding="0" cellspacing="0" width="97%" border="0" class=""><tr><td>');
            $this->add_mail_notification_section(); // Still some worked required.
            $mform->addElement('html', '</td></tr></table>');
        }

        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);

        $mform->addElement('hidden', 'offset', '1');
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('html', '<div align="right" style="font:25%; padding-right:10px;">');
        // Submit button without cancel button.
        $this->add_action_buttons(false, $svalue);

        $mform->addElement('html', '</font></div>');
    }

    /**
     * Gets or sets the instance for advanced grading
     *
     * @param gradingform_instance $gradinginstance
     */
    public function use_advanced_grading($gradinginstance = false) {
        if ($gradinginstance !== false) {
            $this->advancegradinginstance = $gradinginstance;
        }
        return $this->advancegradinginstance;
    }

    public function add_grades_section() {

        global $CFG;
        $mform = & $this->_form;
        $attributes = array();
        if ($this->_customdata->gradingdisabled) {
            $attributes['disabled'] = 'disabled';
        }

        $mform->addElement('header', 'Grades', get_string('grades', 'grades'));

        $grademenu = make_grades_menu($this->_customdata->grade);
        if ($gradinginstance = $this->use_advanced_grading()) {
            $gradinginstance->get_controller()->set_grade_range($grademenu);
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade') . ':',
                array('gradinginstance' => $gradinginstance));
            if ($this->_customdata->gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
            }
        } else {
            // Use simple direct grading.
            $grademenu['-1'] = get_string('nograde');
            $mform->addElement('select', 'xgrade', get_string('grade') . ':', $grademenu, $attributes);
            $mform->setDefault('xgrade', $this->_customdata->submission->grade);
            $mform->setType('xgrade', PARAM_INT);
        }

        if (!empty($this->_customdata->enableoutcomes)) {
            foreach ($this->_customdata->grading_info->outcomes as $n => $outcome) {
                $options = make_grades_menu(-$outcome->scaleid);
                if ($outcome->grades[$this->_customdata->submission->userid]->locked) {
                    $options[0] = get_string('nooutcome', 'grades');
                    $mform->addElement('static', 'outcome_' . $n . '[' . $this->_customdata->userid . ']',
                        $outcome->name . ':', $options[$outcome->grades[$this->_customdata->submission->userid]->grade]);
                } else {
                    $options[''] = get_string('nooutcome', 'grades');
                    $attributes = array('id' => 'menuoutcome_' . $n);
                    $mform->addElement('select', 'outcome_' . $n . '[' . $this->_customdata->userid . ']',
                        $outcome->name . ':', $options, $attributes);
                    $mform->setType('outcome_' . $n . '[' . $this->_customdata->userid . ']', PARAM_INT);
                    $mform->setDefault('outcome_' . $n . '[' . $this->_customdata->userid . ']',
                        $outcome->grades[$this->_customdata->submission->userid]->grade);
                }
            }
        }

        $modulecontext = context_module::instance($this->_customdata->cm->id);
        if (has_capability('gradereport/grader:view', $modulecontext) && has_capability('moodle/grade:viewall', $modulecontext)) {
            $grade = '<a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $this->_customdata->courseid . '" >' .
                    $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade . '</a>';
        } else {
            $grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;
        }
        $mform->addElement('static', 'finalgrade', get_string('currentgrade', 'assignment') . ':', $grade);
        $mform->setType('finalgrade', PARAM_INT);
    }


    /**
     *
     * @global core_renderer $OUTPUT
     */
    public function add_editor_section() {
        $mform = & $this->_form;

        if ($this->_customdata->gradingdisabled) {
            $mform->addElement('static', 'disabledfeedback',
                $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_feedback);
        } else {
            // Visible elements.
            $mform->addElement('editor', 'submissioncomment_editor', '', array('id' => 'submissioncomment_' .
                $this->_customdata->submission->id . '_editor'), $this->get_editor_options());
            $mform->setType('submissioncomment_editor', PARAM_RAW); // To be cleaned before display.
            $mform->setDefault('submissioncomment_editor', $this->_customdata->submission->submissioncomment);
        }
    }

    /**
     *
     * @global add response file section
     */
    public function add_response_file_section() {
        $mform = & $this->_form;
        // Visible elements.
        switch ($this->_customdata->assignment->assignmenttype) {
            case 'upload' :
            case 'uploadsingle' :
                $mform->addElement('filemanager', 'files_filemanager',
                    get_string('responsefiles', 'assignment') . ':',
                    array('id' => 'files_' . $this->_customdata->submission->id . '_filemanager'),
                    $this->_customdata->fileui_options);
                break;
            default :
                break;
        }
    }

    /**
     *
     * @global add mail notification section
     */
    public function add_mail_notification_section() {
        global $OUTPUT;
        $mform = & $this->_form;
        $mform->addElement('hidden', 'mailinfo_h', "0");
        $mform->setType('mailinfo_h', PARAM_INT);
        $mform->addElement('checkbox', 'mailinfo', get_string('enablenotification', 'assignment') .
                $OUTPUT->help_icon('enablenotification', 'assignment') . ':');
        $mform->setType('mailinfo', PARAM_INT);
    }

    public function add_submission_content() {
        $mform = & $this->_form;
        $mform->addElement('header', 'Submission', get_string('submission', 'assignment'));
        $mform->addElement('static', '', '', $this->_customdata->submission_content);
    }

    protected function get_editor_options() {
        $editoroptions = array();
        $editoroptions['component'] = 'mod_assignment';
        $editoroptions['filearea'] = 'feedback';
        $editoroptions['noclean'] = false;
        $editoroptions['maxfiles'] = 0;
        $editoroptions['maxbytes'] = $this->_customdata->maxbytes;
        $editoroptions['context'] = $this->_customdata->context;
        return $editoroptions;
    }

    public function set_data($data) {
        $editoroptions = $this->get_editor_options();
        if (!isset($data->text)) {
            $data->text = '';
        }
        if (!isset($data->format)) {
            $data->textformat = FORMAT_HTML;
        } else {
            $data->textformat = $data->format;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null;
        }

        switch ($this->_customdata->assignment->assignmenttype) {
            case 'upload' :
            case 'uploadsingle' :
                $data = file_prepare_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context,
                    'mod_assignment', 'response', $itemid);
                break;
            default :
                break;
        }

        $data = file_prepare_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context,
            $editoroptions['component'], $editoroptions['filearea'], $itemid);
        return parent::set_data($data);
    }

    public function get_data() {
        $data = parent::get_data();

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null;
        }

        if ($data) {
            $editoroptions = $this->get_editor_options();
            switch ($this->_customdata->assignment->assignmenttype) {
                case 'upload' :
                case 'uploadsingle' :
                    $data = file_postupdate_standard_filemanager($data, 'files', $editoroptions,
                        $this->_customdata->context, 'mod_assignment', 'response', $itemid);
                    break;
                default :
                    break;
            }
            $data = file_postupdate_standard_editor($data, 'submissioncomment', $editoroptions,
                $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
        }

        if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
            $data->advancedgrading = null;
        }

        return $data;
    }

}
