<?php

//class for assignment marking

class assignment_marking {

    public function assignment_marking($assignment) {
        $this->assignment = $assignment;
    }

    public function print_submission() {
        /// If there are multiple submissions, set the current one to the last one in the array.
        if (isset($this->assignment->submissions)) {
            $this->assignment->submission = end($this->assignment->submissions);
            $this->assignment->first = reset($this->assignment->submissions);
        }
        $this->print_marking_overview();
    }

    public function print_marking_overview() {       
        global $CFG, $PAGE, $OUTPUT, $DB, $mid, $id, $USER; 
        /// Marking Overview Container:        
        $this->print_marking_activity_name();
        require_once($CFG->libdir.'/gradelib.php');        
        require_once("$CFG->dirroot/repository/lib.php");
        require_once("$CFG->dirroot/grade/grading/lib.php");
        require_once($CFG->dirroot.'/blocks/fn_marking/edit_grade_form.php');  
        
        $course     = $this->assignment->course;
        $assignment = $this->assignment->assignment;
        $cm         = $this->assignment->cm;          
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
        $submission = $this->assignment->submission; 
        $user = $this->assignment->user;
        
        if ($submission->teacher) {
            $teacher = $DB->get_record('user', array('id'=>$submission->teacher));
        } else {            
            $teacher = $USER;
        }
        
        
        $grading_info = grade_get_grades($course->id, 'mod', 'assignment', $assignment->id, array($user->id));
        $gradingdisabled = $grading_info->items[0]->grades[$user->id]->locked || $grading_info->items[0]->grades[$user->id]->overridden;
        $offset =1;
        $mformdata =  new stdClass(); 
        $mformdata->id = $id;
        $mformdata->mid = $mid;
        $mformdata->context = $context;
        $mformdata->maxbytes = $course->maxbytes;
        $mformdata->courseid = $course->id;
        $mformdata->teacher = $teacher;        
        $mformdata->assignment = $assignment;        
        //$mformdata->assignment = $this->assignment;
        $mformdata->submission = $submission;
        $mformdata->lateness = $this->assignment->display_lateness($submission->timemodified);        
        $mformdata->user = $user;
       // $mformdata->offset = $offset;
        $mformdata->userid = $user->id;
        $mformdata->cm = $cm;
        $mformdata->grading_info = $grading_info;
        $mformdata->enableoutcomes = $CFG->enableoutcomes;
        $mformdata->grade = $assignment->grade;        
        $mformdata->gradingdisabled = $gradingdisabled;
        //$mformdata->nextid = $nextid;
        $mformdata->submissioncomment= $submission->submissioncomment;
        $mformdata->submissioncommentformat= FORMAT_HTML;
        $mformdata->submission_content = "";
        // only during grading        
        if($assignment->assignmenttype=='upload'){
            if ($this->assignment->drafts_tracked() and $this->assignment->isopen() and !$this->assignment->is_finalized($submission)) {          
                $mformdata->submission_content .= '<strong>'.get_string('draft', 'assignment').':</strong><br />';        

            }
            if ($this->assignment->notes_allowed() and !empty($submission->data1)) { // only during grading
                $link = new moodle_url("/mod/assignment/type/upload/notes.php", array('id' => $cm->id, 'userid'=>$user->id, 'offset'=>$offset, 'mode'=>'single'));
                $action = new popup_action('click', $link, 'post', array('height' => 500, 'width' => 780));
                $mformdata->submission_content .= $OUTPUT->action_link($link, get_string('notes', 'assignment'), $action, array());
            }
        }
        $mformdata->submission_content .= $this->assignment->print_user_files($user->id, true);   
        //$mformdata->filter = $filter;
        $mformdata->mailinfo = get_user_preferences('assignment_mailinfo', 0);
         if ($assignment->assignmenttype == 'upload') {
            $mformdata->fileui_options = array('subdirs'=>1, 'maxbytes'=>$assignment->maxbytes, 'maxfiles'=>$assignment->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        } elseif ($assignment->assignmenttype == 'uploadsingle') {
            $mformdata->fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        }
        
        $advancedgradingwarning = false;
        require_once($CFG->dirroot.'/grade/grading/lib.php');
        $gradingmanager = get_grading_manager($context, 'mod_assignment', 'submission');        
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }
        
        
        $submitform = new mod_assignment_grading_form_fn(null, $mformdata);
        $submitform->set_data($mformdata);        
        $submitform->display();
    }

    public function print_marking_activity_name() {
        global $OUTPUT, $CFG;

        $asstypes = assignment_types();        
        $asstitle = $asstypes[$this->assignment->assignment->assignmenttype];        
        $assmtimage = "<IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/assignment/pix/icon.gif\" HEIGHT=\"16\" WIDTH=\"16\" >";

        echo '<div class="activity-name">' . $assmtimage . '' . $asstitle . ': ';
        $link = new moodle_url("/mod/assignment/view.php", array('a' => $this->assignment->assignment->id));
        $action = new popup_action('click', $link, 'post', array('height' => 500, 'width' => 780));
        echo $OUTPUT->action_link($link, $this->assignment->assignment->name, $action, array('title' => $this->assignment->assignment->name));
        echo '</div>';
    }  
}

