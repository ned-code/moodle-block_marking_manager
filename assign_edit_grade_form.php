<?php

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

        global $OUTPUT, $CFG, $DB, $PAGE, $USER;

        $mform = & $this->_form;
        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }

        list($assignment, $data, $params) = $this->_customdata;

        $rownum = $params['rownum'];
        $last = $params['last'];
        $useridlist = $params['useridlist']; //echo $rownum; print_r($useridlist);
        $userid = $useridlist[$rownum];

        $attemptnumber = $params['attemptnumber'];
        $maxattemptnumber = isset($params['maxattemptnumber']) ? $params['maxattemptnumber'] : $params['attemptnumber'];


        $user = $DB->get_record('user', array('id' => $userid));


        $submission = get_user_submission($assignment, $userid, false); //print_r($submission);
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
            if(isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)'; //
            }


        }else{
            $groupname = '';
        }



        ///  start the table
        $mform->addElement('html', '<div style="text-align:center; font-size:11px; margin-bottom:3px;">');

        $strprevious = get_string('previous');
        $strnext = get_string('next');

        if ($rownum > 0) {
            $mform->addElement('html', ' <input type="submit" id="id_nosaveandprevious" value="'.$strprevious.'" name="nosaveandprevious"> ');
        }else{
            $mform->addElement('html', ' <input type="submit" id="id_nosaveandprevious" value="'.$strprevious.'" name="nosaveandprevious" disabled="disabled"> ');
        }
        $mform->addElement('html', get_string('gradingstudentprogress', 'block_fn_marking', array('index'=>$rownum+1, 'count'=>count($useridlist))));
        //$mform->addElement('static', 'progress', '', get_string('gradingstudentprogress', 'block_fn_marking', array('index'=>$rownum+1, 'count'=>count($useridlist))));

        if (!$last) {
            $mform->addElement('html', ' <input type="submit" id="id_nosaveandnext" value="'.$strnext.'" name="nosaveandnext"> ');
        }else{
            $mform->addElement('html', ' <input type="submit" id="id_nosaveandnext" value="'.$strnext.'" name="nosaveandnext" disabled="disabled"> ');

        }

        $mform->addElement('html', '</div>');

        $mform->addElement('html', '<table border="0" cellpadding="0" cellspacing="0" border="1" width="100%" class="saprate-table">');

        //print the marking header in first tr
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
            $params);

        $mform->addElement('html', '</tr>');

        //GRADING
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td class="yellowcell" colspan="2">');


        $grade = $assignment->get_user_grade($userid, false, $attemptnumber);
        $flags = $assignment->get_user_flags($userid, false);


        // add advanced grading
        $gradingdisabled = $assignment->grading_disabled($userid);
        $gradinginstance = fn_get_grading_instance($userid, $grade,  $gradingdisabled, $assignment);

        $gradinginfo = grade_get_grades($assignment->get_course()->id,
            'mod',
            'assign',
            $assignment->get_instance()->id,
            $userid);

        //Fix grade string for select form
        if ($gradinginfo->items[0]->grades[$userid]->str_grade == "-"){
            $stu_grade = '-1';
        }else{
            $stu_grade = $gradinginfo->items[0]->grades[$userid]->str_grade;
        }

        ############
        $mform->addElement('html', '<table class="teacherfeedback" border="0" cellpadding="0" cellspacing="0" width="100%">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td width="50%" align="left">');
        $mform->addElement('html', '<b>Teacher\'s Feedback </b> <br /> <span class="teacher_feedback_info">'.$USER->firstname.' '.$USER->lastname.' <br /> '.userdate(time()));
        $mform->addElement('html', '</span>');
        $mform->addElement('html', '</td>');


        if ($gradinginstance) {
            //RUBRIC
            $mform->addElement('html', '</tr>');
            $mform->addElement('html', '<tr>');
            $mform->addElement('html', '<td>');
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
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
            // use simple direct grading
            if ($assignment->get_instance()->grade > 0) {

                $attributes = array();
                if ($gradingdisabled) {
                    $attributes['disabled'] = 'disabled';
                }

                $grademenu = make_grades_menu($assignment->get_instance()->grade);
                $grademenu['-1'] = 'Select';
                $gradingelement = $mform->addElement('select', 'grade', get_string('grade', 'block_fn_marking'), $grademenu, $attributes);
                $mform->setDefault('grade', $stu_grade); //@fixme some bug when element called 'grade' makes it break
                $mform->setType('grade', PARAM_INT);

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
                }
            }
            $mform->addElement('html', '</td>');
            $mform->addElement('html', '</tr>');
        }

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
            //$mform->addelement('static', 'maxattemptslabel', get_string('maxattempts', 'assign'), $maxattempts);
            //$mform->addelement('static', 'attemptnumberlabel', get_string('attemptnumber', 'assign'), $attemptnumber + 1);

            $ismanual = $assignment->get_instance()->attemptreopenmethod == ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL;
            $issubmission = !empty($submission);
            $isunlimited = $assignment->get_instance()->maxattempts == ASSIGN_UNLIMITED_ATTEMPTS;
            $islessthanmaxattempts = $issubmission && ($submission->attemptnumber < ($assignment->get_instance()->maxattempts-1));

            if ($ismanual && (!$issubmission || $isunlimited || $islessthanmaxattempts)) {
                //$mform->addElement('selectyesno', 'addattempt', get_string('addattempt', 'assign'));
                //$mform->setDefault('addattempt', 0);
                $mform->addElement('checkbox', 'addattempt', 'Allow student to resubmit');
            }
        }

        //$mform->addElement('html', 'Submission comments (3)');
        $mform->addElement('html', '</table>');

        ############


        // Let feedback plugins add elements to the grading form.
        //fn_add_plugin_grade_elements($grade, $mform, $data, $userid, $assignment);

        $feedbackplugins = fn_load_plugins('assignfeedback', $assignment);

        foreach ($feedbackplugins as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {

                if($plugin->get_type() == 'file'){
                    $mform->addElement('html', '<br /><div style="text-align: left; font-weight: bold;">Feedback files </div>');
                }

                if ($plugin->get_form_elements_for_user($grade, $mform, $data, $userid)) {
                    //print_r($data);die;
                }
            }
        }


        // hidden params
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
            //$gradelocked = ($grade && $grade->locked) || $assignment->grading_disabled($userid);

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

            //Moodle version check
            $version = explode('.', $CFG->version);
            $version = reset($version);
            // Moodle 2.9 and greater.
            if ($version >= 2015051100) {
                $status = new assign_submission_status($assignment->get_instance()->allowsubmissionsfromdate,
                    $assignment->get_instance()->alwaysshowdescription,
                    $submission,
                    $assignment->get_instance()->teamsubmission,
                    $teamsubmission,
                    $submissiongroup,
                    $notsubmitted,
                    $assignment->is_any_submission_plugin_enabled(),
                    $gradelocked,
                    fn_is_graded($userid, $assignment),//$assignment->is_graded($userid),
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
                    fn_is_graded($userid, $assignment),//$assignment->is_graded($userid),
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
            //$row = new html_table_row();
            //$cell1 = new html_table_cell(get_string('editingstatus', 'assign'));
            if ($status->canedit) {
                $editingstatus = get_string('submissioneditable', 'assign');

            } else {
                $editingstatus = get_string('submissionnoteditable', 'assign');

            }

        }





        // Last modified.
        $tsubmission = $status->teamsubmission ? $status->teamsubmission : $status->submission;

        if ($tsubmission) {
            $submissiontime = userdate($tsubmission->timemodified);
        }else{
            $submissiontime = '-';
        }

        $mform->addElement('html', '<b>Student\'s Submission </b> <br /> <span class="editingstatus">'.$submissiontime.'</span>');
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '<td valign="top" width="50%" align="right">');
        $mform->addElement('html', '<span class="editingstatus">Editing Status: <span class="editingstatus_msg">' . $editingstatus.'</span></span><br />');
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
                    //$mform->addElement('html', $pluginname.'<br />');

                    $submissionplugin = new assign_submission_plugin_submission($plugin,
                        $tsubmission,
                        assign_submission_plugin_submission::SUMMARY,
                        $status->coursemoduleid,
                        $status->returnaction,
                        $status->returnparams);

                    if ($plugin->get_name() == 'Online text'){
                        $onlinetext = $DB->get_record('assignsubmission_onlinetext', array('submission'=>$submission->id));//print_r($onlinetext);

                        $mform->addElement('hidden', 'submissionid', $submission->id);
                        $mform->setType('submissionid', PARAM_INT);


                        $options = array('cols'=>'82'
                            //'subdirs'=>0,
                            //'maxbytes'=>0,
                            //'maxfiles'=>0,
                            //'changeformat'=>0,
                            //'context'=>null,
                            //'noclean'=>0,
                            //'trusttext'=>0
                        );

                        $mform->addElement('editor', 'onlinetext');
                        $mform->setType('onlinetext', PARAM_RAW);
                        $mform->setDefault('onlinetext', array('text'=>$onlinetext->onlinetext,'format'=>FORMAT_HTML));

                    } else {
                        if ((! isset($params['savegrade'])) && ((! $params['readonly']) || ($plugin->get_name() != 'Submission comments'))){
                            $mform->addElement('html', '<div class="fn_plugin_wrapper">'.$pluginname.'<br />');
                            //$o = $plugin->view($submissionplugin->submission);
                            $o = $assignment->get_renderer()->render($submissionplugin);
                            $mform->addElement('html', $o.'</div>');
                        }
                    }









                    //$//row->cells = array($cell1, $cell2);
                    //$t->data[] = $row;
                }
            }

        }

        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');
        ///close the table
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
    public function add_marking_header($user, $name, $blindmarking, $uniqueidforuser, $courseid, $viewfullnames, $rownum , $last, $groupname, $cm, $params) {
        global $CFG, $DB, $OUTPUT;


        $mform = & $this->_form;
        $mform->addElement('html', '<td width="40" valign="top" align="center" class="markingmanager-head marking_rightBRD">' . "\n");


        $o = '';
        if ($blindmarking) {
            $o .= get_string('hiddenuser', 'assign') . $uniqueidforuser;
        } else {
            $o .= $OUTPUT->user_picture($user);
            //$o .= $OUTPUT->spacer(array('width'=>30));
            //$o .= $OUTPUT->action_link(new moodle_url('/user/view.php', array('id' => $user->id, 'course'=>$courseid)), fullname($user, $viewfullnames));
        }
        $mform->addElement('html', $o);


        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="100%" valign="top" align="left" class="markingmanager-head">');

        $mform->addElement('html', '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="name-date">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="middle" width="65%" class="leftSide">');
        $mform->addElement('html', '<a target="_blank" class="marking_header_link" href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$courseid.'">' . fullname($user, true) . '</a>'. $groupname);
        $mform->addElement('html', '<br / ><span class="marking_header_link">Assignment: </span><a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id.'">' .$name.'</a>');
        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="35%" align="right" class="rightSide">');

        $buttonarray=array();
        if (isset( $params['readonly'])){
            if (! $params['readonly']){
                $buttonarray[] = $mform->createElement('submit', 'savegrade', 'Save');
            }
        }else{
            $buttonarray[] = $mform->createElement('submit', 'savegrade', 'Save');
        }

        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'navar', '', array(' '), false);
            $mform->disabledIf('navar', 'grade', 'eq', -1);
        }
        $mform->addElement('html', '&nbsp;</td>');


        $mform->addElement('html', '</tr>');
        $mform->addElement('html', '</table>');

        $mform->addElement('html', '</td>');
    }

}