<?php
    global $DB, $OUTPUT, $FULLME;
        
/// Get the assignment
    if (! $assign = $DB->get_record("assign", array("id"=>$iid))) {
        print_error("Course module is incorrect");
    }


/// Get the course module entry
    if (! $cm = get_coursemodule_from_instance("assign", $assign->id, $course->id)) {
        print_error("Course Module ID was incorrect");
    }

    $ctx = context_module::instance($cm->id);

    require_once("$CFG->dirroot/repository/lib.php");
    require_once("$CFG->dirroot/grade/grading/lib.php");
    require_once($CFG->dirroot.'/mod/assign/lib.php');
    require_once($CFG->dirroot.'/mod/assign/locallib.php');
    require_once($CFG->dirroot.'/blocks/fn_marking/assign_edit_grade_form.php'); 

    $assign = new assign($ctx, $cm, $course);
         

    $o = '';
    $mform = null;
    $notices = array();
    
    if (($show == 'unmarked')||($show == 'saved')){
        
        if ($action == 'submitgrade') {
            if (optional_param('saveandshownext', null, PARAM_RAW)) {
                //save and show next
                $action = 'grade';
                if (fn_process_save_grade($mform, $assign, $ctx, $course, $pageparams)) {
                    $action = 'nextgrade';
                }
            } else if (optional_param('nosaveandprevious', null, PARAM_RAW)) {
                $action = 'previousgrade';
            } else if (optional_param('nosaveandnext', null, PARAM_RAW)) {
                //show next button
                $action = 'nextgrade';
            } else if (optional_param('savegrade', null, PARAM_RAW)) {
                //save changes button
                
                $action = 'grade';
                if (fn_process_save_grade($mform, $assign, $ctx, $course, $pageparams)) {
                    $action = 'grade';
                    /* $redirect = new moodle_url('fn_gradebook.php', $pageparams);
       
                       redirect($redirect);
                       break;*/                
                }
            } else {
                //cancel button
                $action = 'grading';
                //return to mainpage
            }
        }else{
            $action = 'grade';
        }

        $returnparams = array('rownum'=>optional_param('rownum', 0, PARAM_INT));
        $assign->register_return_link($action, $returnparams);

        if (isset($_POST['onlinetext'])){
            unset($_POST['onlinetext']);
        }
        
        // Now show the right view page.
        if ($action == 'previousgrade') {  
            $mform = null;
            $o .= fn_view_single_grade_page($mform, -1, $assign, $ctx, $cm, $course, $pageparams);
        } else if ($action == 'nextgrade') {   
            $mform = null;
            $o .= fn_view_single_grade_page($mform, 1, $assign, $ctx, $cm, $course, $pageparams);
        } else if ($action == 'grade') { 
            $mform = null;  
            $_POST = null;  
            $o .= fn_view_single_grade_page($mform, 0, $assign, $ctx, $cm, $course, $pageparams);
        } 
        echo $o;
        
    }elseif ($show == 'marked'){
        if($userid){
            $mform = null;  
            $_POST = null;  
            $o .= fn_view_single_grade_page($mform, 0, $assign, $ctx, $cm, $course, $pageparams);            
        }else{
            if ($action == 'submitgrade') {
                fn_process_save_grade($mform, $assign, $ctx, $course, $pageparams);
            }
            $o .= fn_view_submissions($mform, $offset=0, $showsubmissionnum=null, $assign, $ctx, $cm, $course, $pageparams);
        }                                                                                                                          
        
        echo $o;
    }elseif ($show == 'unsubmitted'){
      

        $o .= fn_view_submissions($mform, $offset=0, $showsubmissionnum=null, $assign, $ctx, $cm, $course, $pageparams);
                                                                                                                              
    
        echo $o;
    }


                                                                                                               
    
