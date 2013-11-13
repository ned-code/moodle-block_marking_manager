<?PHP

    global $DB, $OUTPUT, $PAGE;

    if (! $forum = $DB->get_record("forum", array("id"=>$iid))) {
        print_error("Forum ID was incorrect or no longer exists");
    }

    $context        = get_context_instance(CONTEXT_COURSE, $course->id);
    $isteacheredit  = has_capability('moodle/course:update', $context);

    
    if ($cm = get_coursemodule_from_instance("forum", $forum->id, $course->id)) {
        $buttontext = "";
    } else {
        $cm->id = NULL;
        $buttontext = "";
    }

    require_course_login($course, false, $cm);
    $modcontext     = get_context_instance(CONTEXT_MODULE, $cm->id);
    $isteacher      = has_capability('mod/forum:rate', $modcontext);

    /// find out current groups mode
    $currentgroup = groups_get_activity_group($cm, true);
    $groupmode = groups_get_activity_groupmode($cm);    

    if ($forum->type == "teacher") {
            if (!$isteacheredit  || !$isteacher) {
                print_error("You must be a course teacher to view this forum");
            }
    }

    if ($groupmode && ($currentgroup === false) && !$isteacheredit) {     
        $OUTPUT->heading(get_string("notingroup", "forum"));
        exit;
    }
    
    /// Print settings and things in a table across the top
    $forum->intro = trim($forum->intro);
    /*
    if ((!empty($forum->intro)) && ($show !=='saved' )) {
        echo $OUTPUT->box(format_text($forum->intro), 'box generalbox generalboxcontent boxaligncenter', 'intro');
    }
    echo '<br />';
    */
    $student_id_list = implode(',', array_keys($students));

    //Get students from forum_posts
    $st_posts = $DB->get_records_sql("SELECT DISTINCT u.id, u.firstname, u.lastname, u.picture, u.imagealt, u.email
                                 FROM {forum_discussions} d
                                 INNER JOIN {forum_posts} p ON p.discussion = d.id
                                 INNER JOIN {user} u ON u.id = p.userid
                                 WHERE d.forum = $forum->id AND 
                                 u.id in ($student_id_list)"); 

                      
     // show all the unsubmitted users                            
    if($show == 'unsubmitted'){
        if($students){    
            
            $image = "<A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\"> <IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/$cm->modname/pix/icon.gif\" " .
                    "HEIGHT=16 WIDTH=16 ALT=\"$cm->modname\"></A>";
                                             
            echo '<div class="unsubmitted_header">' . $image .
                                        " Forum: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" . $forum->name . '</a></div>';            
            
                                 
            echo '<p class="unsubmitted_msg">Following students have not yet posted to this forum:</p>';         
             
            /* 
            echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted" width="100%">';
                                echo "\n<tr>";
                                echo "<td width=\"100%\" class=\"rightName\"><strong>Following students have not yet posted to this forum</strong></td>\n";              
                                echo "</tr></table>\n";
             */                   
                                
            foreach ($students as $student) {
                if (!is_array($st_posts) || !array_key_exists($student->id, $st_posts)) {
                    
                    echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                                echo "\n<tr>";
                                echo "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";                        
                                echo $OUTPUT->user_picture($student);
                                echo "</td>";
                                echo "<td width=\"100%\" class=\"rightName\"><strong>".fullname($student)."</strong></td>\n";
                                echo "</tr></table>\n";
                  }
            }
        } else{
            echo $OUTPUT->box("No Students", 'box generalbox generalboxcontent boxaligncenter', 'intro');
        }
  }

  // show all the marked or unmarked users 
  if(($show == 'unmarked') || ($show == 'marked')){
        if($st_posts){        
            echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted" width="100%">';
                                echo "\n<tr>";
                                echo "<td width=\"100%\" class=\"rightName\"><strong>Following students have posted to this forum</strong></td>\n";              
                                echo "</tr></table>\n";
            foreach ($st_posts as $st_post) {                
                    echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                                echo "\n<tr>";
                                echo "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";                        
                                echo $OUTPUT->user_picture($st_post);
                                echo "</td>";
                                echo "<td width=\"100%\" class=\"rightName\"><strong>".fullname($st_post)."</strong></td>\n";
                                echo "</tr></table>\n";
                  
            }
        } else {
            echo $OUTPUT->box("No Student with post in this group or course", 'box generalbox generalboxcontent boxaligncenter', 'intro');
        }
  }
    
    $vars = array('mid' => $mid,'spinner' => $OUTPUT->pix_url('i/loading').'');

    if(($show =='marked') && $st_posts){
            echo '<p  align ="center"><button id="showForum">Click to see all  rated discussion.</button></p>';
    } elseif(($show =='unmarked') && $st_posts){
           echo '<p  align ="center"><button id="showForum">Click to go for rating area</button></p>';
    }



    $jsmodule1 = array(
    'name' => 'M.show_forum_panel',
    'fullpath' => '/blocks/fn_marking/module1.js',
    'requires' => array('base', 'node','event-base','event', 'panel')
    );

    $PAGE->requires->js_init_call('M.show_forum_panel.init', array($vars), true, $jsmodule1);
