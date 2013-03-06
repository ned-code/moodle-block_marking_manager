<?php

global $CFG;
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');
require_once($CFG->dirroot . '/lib/grade/grade_grade.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/blocks/fn_marking/locallib.php');

/**
 *
 * @return count the upgraded assignment
 */
function assignment_count_ungraded($assignment, $graded, $students, $show='unmarked', $extra=false, $instance) {
    global $DB;

    $studentlist = implode(',', array_keys($students));   
    if (empty($studentlist)) {
        return 0;
    }
    $subtable = 'assignment_submissions';
    if (($show == 'unmarked') || ($show == 'all')) {
        if ($instance->var4 == 1) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked < timemodified) AND (timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked < timemodified) AND (timemodified > 0)';
        }
        return $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
    } else if ($show == 'marked') {
        if ($instance->var4 == 1) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked >= timemodified) AND (timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemarked >= timemodified) AND (timemodified > 0)';
        }
        $marked = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
        return $marked;
    } else if ($show == 'unsubmitted') {
        if ($instance->var4) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0) AND data2="submitted"';
        } else {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0)';
        }

        $subbed = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
        $unsubbed = abs(count($students) - $subbed);
        return ($unsubbed);
    } else if ($show == 'saved') {
        if ($instance->var4) {
            $select = '(assignment = ' . $assignment . ') AND (userid in (' . $studentlist . ')) AND ' .
                    '(timemodified > 0) AND data2="" ';
            $saved = $DB->count_records_select($subtable, $select, array(), 'COUNT(DISTINCT userid)');
            return $saved;
        } else {
            return 0;
        }
    } else {
        return 0;
    }
}


/**
 *
 * @return count the upgraded assign
 */
function assign_count_ungraded($assign, $graded, $students, $show='unmarked', $extra=false, $instance, $resubmission=false) {
    global $DB;

    $studentlist = implode(',', array_keys($students));   
    
    if (empty($studentlist)) {
        return 0;
    }
    
    $subtable = 'assign_submission';
    
    if (($show == 'unmarked') || ($show == 'all')) { 
        if($resubmission){
            $sql = "SELECT COUNT(DISTINCT s.id)
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and  s.submissionnum = g.submissionnum)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null";
        }else{
            $sql = "SELECT COUNT(DISTINCT s.id)
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null";
        }
        return $DB->count_records_sql($sql);    
    
    } else if ($show == 'marked') {
        if($resubmission){
            $sqlunmarked = "SELECT s.userid
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and  s.submissionnum = g.submissionnum)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null";
            
            if($unmarkedstus =  $DB->get_records_sql($sqlunmarked)){
                $students = explode(',', $studentlist);
                
                foreach ($unmarkedstus as $unmarkedstu)
                {
                    $students = array_diff($students, array($unmarkedstu->userid));
                }
                $studentlist = implode(',', $students);
            }

                     
            $sql = "SELECT COUNT(DISTINCT s.userid)
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and s.submissionnum = g.submissionnum)
                     WHERE ((s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status IN ('submitted', 'resub') AND g.grade is not null) 
                        OR (s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='draft' AND g.grade is not null AND g.timemodified > s.timemodified))"; 
                     
           //  echo $sql;die;        
        }else{
            $sql = "SELECT COUNT(DISTINCT s.id)
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null";
        }
        return $DB->count_records_sql($sql);
    
    } else if ($show == 'unsubmitted') {
        $sql = "SELECT COUNT(DISTINCT userid)
                  FROM {assign_submission}
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
        $subbed = $DB->count_records_sql($sql);
        $unsubbed = abs(count($students) - $subbed);
        return ($unsubbed);
    
    } else if ($show == 'saved') {
        
        if($resubmission){
            $sql = "SELECT COUNT(DISTINCT s.id)
                    FROM
                    mdl_assign_submission AS s
                    LEFT JOIN mdl_assign_grades AS g ON s.assignment = g.assignment AND s.userid = g.userid AND s.submissionnum = g.submissionnum
                    WHERE
                    s.assignment = $assign AND
                    (s.userid IN ($studentlist)) AND
                    s.`status` = 'draft' AND
                    (s.timemodified >= g.timemodified OR
                    g.grade IS NULL)";
        }else{
            $sql = "SELECT COUNT(DISTINCT id)
                      FROM {assign_submission}
                     WHERE assignment=$assign AND (userid in ($studentlist)) AND status='draft'";            
        }
        

        return $DB->count_records_sql($sql);
    
    } else {
        return 0;
    }
}


/**
 *
 * @return count the upgraded assign
 */
function assign_students_ungraded($assign, $graded, $students, $show='unmarked', $extra=false, $instance,  $resubmission=false, $sort=false) {
    global $DB, $CFG;

    $studentlist = implode(',', array_keys($students));   
    
    if (empty($studentlist)) {
        return 0;
    }
    
    $subtable = 'assign_submission';
    
    if (($show == 'unmarked') || ($show == 'all')) {
        if($resubmission){
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and s.submissionnum = g.submissionnum)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null";                  
        }else{            
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null"; 
        }
        
        if($data = $DB->get_records_sql($sql)){
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        }else{
            return false;
        }    
    
    } else if ($show == 'marked') {
        

        if($resubmission){
            
            $students = explode(',', $studentlist);       
        
            $sqlunmarked = "SELECT s.userid
                      FROM {assign_submission} s
                      LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid and  s.submissionnum = g.submissionnum)
                     WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is null";
            
            if($unmarkedstus =  $DB->get_records_sql($sqlunmarked)){
                
                foreach ($unmarkedstus as $unmarkedstu)
                {
                    $students = array_diff($students, array($unmarkedstu->userid));
                }
            }

            
            $studentlist = implode(',', $students);            
            
                        
               
            $sql = "SELECT Max(s.id) AS id, 
                           s.userid
                      FROM {$CFG->prefix}assign_submission as s
                 LEFT JOIN {$CFG->prefix}assign_grades as g 
                        ON (s.assignment=g.assignment and s.userid=g.userid and s.submissionnum = g.submissionnum)
                     WHERE s.assignment=$assign 
                       AND (s.userid in ($studentlist)) 
                       AND g.grade is not null
                  GROUP BY s.userid";                  
         
         
                
            if($data = $DB->get_records_sql($sql)){
             
                if ($sort){
                    
                    $arrids = array();
                    $drafted = array();
                    
                    foreach ($data as $value) {
                        $arrids[] = $value->id;
                    }
                    
                    //CHECK DRAFT is_Graded
                    $sqlDraft = "SELECT s.id,
                                        s.timemodified AS submissiontime,
                                        g.timemodified AS gradetime
                                   FROM mdl_assign_submission as s 
                              LEFT JOIN mdl_assign_grades as g 
                                     ON (s.assignment=g.assignment and s.userid=g.userid and s.submissionnum = g.submissionnum)
                                  WHERE s.assignment = $assign 
                                    AND s.userid IN ($studentlist)
                                    AND s.`status` = 'draft'";
                        
                    if($draftGrades =  $DB->get_records_sql($sqlDraft)){
                        foreach ($draftGrades as $draftGrade){ 
                            if(($draftGrade == null) || ($draftGrade->submissiontime >= $draftGrade->gradetime)){
                                $drafted[] = $draftGrade->id; 
                            }                                                                   
                        } 
                        $arrids = array_diff($arrids, $drafted);                       
                    }                                   
                    
                    switch ($sort) {
                        case 'lowest':
                            $sqls = "SELECT s.userid
                                       FROM {$CFG->prefix}assign_submission AS s
                                  LEFT JOIN {$CFG->prefix}assign_grades AS g 
                                         ON (s.assignment = g.assignment AND s.userid = g.userid AND s.submissionnum = g.submissionnum)
                                      WHERE s.id IN (" . implode(',', $arrids) . ")
                                   ORDER BY g.grade ASC"; 
                            break;
                    
                        case 'highest':
                            $sqls = "SELECT s.userid
                                       FROM {$CFG->prefix}assign_submission AS s
                                  LEFT JOIN {$CFG->prefix}assign_grades AS g 
                                         ON (s.assignment = g.assignment AND s.userid = g.userid AND s.submissionnum = g.submissionnum)
                                      WHERE s.id IN (" . implode(',', $arrids) . ")
                                   ORDER BY g.grade DESC"; 
                            break;
                    
                        case 'date':
                            $sqls = "SELECT s.userid
                                       FROM {$CFG->prefix}assign_submission AS s
                                      WHERE s.id IN (" . implode(',', $arrids) . ")
                                   ORDER BY s.timemodified DESC"; 
                            break;
                    
                        case 'alpha':
                            $sqls = "SELECT s.userid
                                       FROM {$CFG->prefix}assign_submission AS s
                                 INNER JOIN {$CFG->prefix}user AS u 
                                         ON s.userid = u.id
                                      WHERE s.id IN (" . implode(',', $arrids) . ")
                                   ORDER BY u.lastname ASC"; 
                            break;
                    }
                    
                    
                    if($datas = $DB->get_records_sql($sqls)){
                        $arr = array();
                        foreach ($datas as $value) {
                            $arr[] = $value->userid;
                        }
                                                  
                        return $arr;                    
                    }else{
                        return false;
                    }                
                } //SORT

        
                
                
                $arr = array();
                foreach ($data as $value) {
                    $arr[] = $value->userid;
                }
                                          
                return $arr;
            }else{
                return false;
            }                             
        }else{
             
            if ($sort){
                
                //CHECK DRAFT is_Graded
                $sqlDraft = "SELECT s.userid,
                                    s.timemodified AS submissiontime,
                                    g.timemodified AS gradetime
                               FROM mdl_assign_submission as s 
                          LEFT JOIN mdl_assign_grades as g 
                                 ON (s.assignment=g.assignment and s.userid=g.userid)
                              WHERE s.assignment = $assign 
                                AND s.userid IN ($studentlist)
                                AND s.`status` = 'draft' 
                                AND g.grade IS NOT NULL
                                AND g.timemodified > s.timemodified";    
                
                $studentlist = explode(',', $studentlist);
                    
                if($draftGrades =  $DB->get_records_sql($sqlDraft)){                        
                    foreach ($draftGrades as $draftGrade){                             
                        if (! in_array($draftGrade->userid, $studentlist)) {
                            $studentlist[] = $draftGrade->userid;
                        }                                                       
                    }                        
                }                 
                
                $studentlist = implode(',', $studentlist);
                
                switch ($sort) {
                    case 'lowest':
                        $sql = "SELECT DISTINCT s.userid
                                  FROM {assign_submission} s
                                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null
                                 ORDER BY g.grade ASC";   
                        break;
                
                    case 'highest':
                        $sql = "SELECT DISTINCT s.userid
                                  FROM {assign_submission} s
                                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null
                                 ORDER BY g.grade DESC";  
                        break;
                
                    case 'date':
                        $sql = "SELECT DISTINCT s.userid
                                  FROM {assign_submission} s
                                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null
                                 ORDER BY s.timemodified DESC";  
                        break;
                
                    case 'alpha':
                        $sql = "SELECT DISTINCT s.userid
                                  FROM {assign_submission} s
                                  LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                                 WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null";  
                        break;
                }                 
                
                
            }else{
                $sql = "SELECT DISTINCT s.userid
                          FROM {assign_submission} s
                          LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
                         WHERE s.assignment=$assign AND (s.userid in ($studentlist)) AND s.status='submitted' AND g.grade is not null";                
            }             
            
            if($data = $DB->get_records_sql($sql)){
                $arr = array();
                foreach ($data as $value) {
                    $arr[] = $value->userid;
                }
                                          
                return $arr;                    
            }else{
                return false;
            }
           
                            

        }
        
     
    } else if ($show == 'unsubmitted') {
        $sql = "SELECT DISTINCT s.userid
                  FROM {assign_submission} s
                 WHERE assignment=$assign AND (userid in ($studentlist)) AND status='submitted'";
        $subbed = $DB->get_records_sql($sql); //print_r($subbed);print_r($students);
        
        $unsubmitted= array_diff(array_keys($students), array_keys($subbed)); //print_r($gradedarray);die;
        return $unsubmitted = array_values($unsubmitted);
                 
    
    } else if ($show == 'saved') {
      
        if($resubmission){
            //CHECK DRAFT is_Graded
            $sqlDraft = "SELECT s.userid,
                                s.timemodified AS submissiontime,
                                g.timemodified AS gradetime
                           FROM mdl_assign_submission as s 
                      LEFT JOIN mdl_assign_grades as g 
                             ON (s.assignment=g.assignment and s.userid=g.userid and s.submissionnum = g.submissionnum)
                          WHERE s.assignment = $assign 
                            AND s.userid IN ($studentlist)
                            AND s.`status` = 'draft' 
                            AND g.grade IS NOT NULL
                            AND g.timemodified > s.timemodified";    
            
            $studentlist = explode(',', $studentlist);
                
            if($draftGrades =  $DB->get_records_sql($sqlDraft)){                        
                foreach ($draftGrades as $draftGrade){                             
                    $studentlist = array_diff($studentlist, array($draftGrade->userid));
                }                        
            }                 
            
            $studentlist = implode(',', $studentlist);
        
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                     WHERE assignment=$assign AND (userid in ($studentlist)) AND status='draft'"; 
                             
        }else{
            $sql = "SELECT DISTINCT s.userid
                      FROM {assign_submission} s
                     WHERE assignment=$assign AND (userid in ($studentlist)) AND status='draft'";            
        }
                        

        if($data = $DB->get_records_sql($sql)){
            $arr = array();
            foreach ($data as $value) {
                $arr[] = $value->userid;
            }
            return $arr;
        }else{
            return false;
        } 
    
    } else {
        return 0;
    }
}


/**
 *
 * @return assignment oldest ungraded
 */
function assignment_oldest_ungraded($assignment) {
    global $CFG, $DB;

    $sql = 'SELECT MIN(timemodified) FROM ' . $CFG->prefix . 'assignment_submissions ' .
            'WHERE (assignment = ' . $assignment . ') AND (timemarked < timemodified) AND (timemodified > 0)';
    return $DB->get_field_sql($sql);
}


/**
 *
 * @return assignment oldest ungraded
 */
function assign_oldest_ungraded($assign) {
    global $CFG, $DB;

    $sql = "SELECT MIN(s.timemodified)
              FROM {assign_submission} s
              LEFT JOIN {assign_grades} g ON (s.assignment=g.assignment and s.userid=g.userid)
             WHERE s.assignment=$assign AND s.status='submitted' AND g.grade is null";
    return $DB->get_field_sql($sql);
}


function forum_count_ungraded($forumid, $graded, $students, $show='unmarked') {
    global $CFG, $DB;

    //Get students from forum_posts 
    $fusers = $DB->get_records_sql("SELECT DISTINCT u.*
                               FROM {forum_discussions} d
                               INNER JOIN {forum_posts} p ON p.discussion = d.id
                               INNER JOIN {user} u ON u.id = p.userid
                               WHERE d.forum = $forumid");

    if (is_array($fusers)) {
        foreach ($fusers as $key => $user) {
            if (!array_key_exists($key, $students)) {
                unset($fusers[$key]);
            }
        }
    }

    if (($show == 'unmarked') || ($show == 'all')) {
        if (empty($graded) && !empty($fusers)) {
            return count($fusers);
        } else if (empty($fusers)) {
            return 0;
        } else {
            return (count($fusers) - count($graded));
        }
    } else if ($show == 'marked') {
        return count($graded);
    } else if ($show == 'unsubmitted') {
        $numuns = count($students) - count($fusers);
        return max(0, $numuns);
    }
}


/**
 *
 * @return count the unmarked activities
 * course object
 * info marked ir saved
 */
function count_unmarked_students(&$course, $mod, $info='unmarked', $resubmission=false, $sort=false) {

    global $CFG, $DB;
    
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    $isteacheredit = has_capability('moodle/course:update', $context);
    $marker = has_capability('moodle/grade:viewall', $context);

    //$currentgroup = get_current_group($course->id); 
    $currentgroup = groups_get_activity_group($mod, true); //print_r($currentgroup);die;
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    
        
    $totungraded = 0;

/// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assign' => '/mod/assign/submissions.g8.html',
        'assignment' => '/mod/assignment/submissions.g8.html',
        'forum' => '/mod/forum/submissions.g8.html'
    );


    ////////////////////////////////
    /// Don't count it if you can't see it.
    $mcontext = get_context_instance(CONTEXT_MODULE, $mod->id);
    if (!$mod->visible && !has_capability('moodle/course:viewhiddenactivities', $mcontext)) {
        continue;
    }
    $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
    $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
    if (file_exists($libfile)) {
        require_once($libfile);
        //$gradefunction = $mod->modname . "_grades";
        $gradefunction = $mod->modname . "_get_user_grades";
        if (function_exists($gradefunction) &&
    //                            (($mod->modname != 'forum') || ($instance->assessed == 2)) && // Only include forums that are assessed only by teachers.
                isset($mod_grades_array[$mod->modname])) {
            /// Use the object function for fnassignments.
            if (($mod->modname == 'forum') &&
                    (($instance->assessed <= 0) || !has_capability('mod/forum:rate', $mcontext))) {
                $modgrades = false;
            } else {
                $modgrades = new Object();
                if (!($modgrades->grades = $gradefunction($instance))) {
                    $modgrades->grades = array();
                }
            }
            if ($modgrades) {
                /// Store the number of ungraded entries for this group.
                if (is_array($modgrades->grades) && is_array($students)) {
                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades));
                    $numgraded = count($gradedarray);
                    $numstudents = count($students);
                    $ungradedfunction = $mod->modname . '_students_ungraded';
                    if (function_exists($ungradedfunction)) {
                        $extra = false;
                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance, $resubmission, $sort);
                        return $ung;
                    } else {
                        $ung = $numstudents - $numgraded;
                    }
                }
            }
        }
    }
    
    
}  


/**
 *
 * @return count the unmarked activities
 * course object
 * info marked ir saved
 */
function count_unmarked_activities(&$course, $info='unmarked', $resubmission=false) {

    global $CFG, $DB, $SESSION;   //print_r($SESSION);die;
    global $mods, $modnames, $modnamesplural, $modnamesused, $sections;

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    $isteacheredit = has_capability('moodle/course:update', $context);
    $marker = has_capability('moodle/grade:viewall', $context);



    
    $totungraded = 0;

/// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assign' => '/mod/assign/submissions.g8.html',
        'assignment' => '/mod/assignment/submissions.g8.html',
        'forum' => '/mod/forum/submissions.g8.html'
    );

/// Collect modules data
/// Search through all the modules, pulling out grade data
    //get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
    $modnames = get_module_types_names();
    $modnamesplural = get_module_types_names(true);
    $modinfo = get_fast_modinfo($course->id);
    $mods = $modinfo->get_cms();
    $modnamesused = $modinfo->get_used_module_names();
       
    //$sections = get_all_sections($course->id); // Sort everything the same as the course
    $sections = get_fast_modinfo($course->id)->get_section_info_all();

    //for ($i = 0; $i <= $course->numsections; $i++) {
    for ($i = 0; $i < sizeof($sections); $i++) {
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
                    $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
                    $libfile = "$CFG->dirroot/mod/$mod->modname/lib.php";
                    if (file_exists($libfile)) {
                        require_once($libfile);
                        //$gradefunction = $mod->modname . "_grades";
                        $gradefunction = $mod->modname . "_get_user_grades";
                        
                        if (function_exists($gradefunction) &&
//                            (($mod->modname != 'forum') || ($instance->assessed == 2)) && // Only include forums that are assessed only by teachers.
                                isset($mod_grades_array[$mod->modname])) {
                            /// Use the object function for fnassignments.
                            if (($mod->modname == 'forum') &&
                                    (($instance->assessed <= 0) || !has_capability('mod/forum:rate', $mcontext))) {
                                $modgrades = false;
                            } else {
                                $modgrades = new Object();
                                if (!($modgrades->grades = $gradefunction($instance))) {
                                    $modgrades->grades = array();
                                }
                                //////////////////////////////////
                                if ($resubmission){
                                    $sql = "SELECT asub.id, 
                                                   asub.userid, 
                                                   ag.grade 
                                              FROM {$CFG->prefix}assign_submission AS asub 
                                         LEFT JOIN {$CFG->prefix}assign_grades AS ag 
                                                ON asub.userid = ag.userid 
                                               AND asub.assignment = ag.assignment 
                                               AND asub.submissionnum = ag.submissionnum 
                                             WHERE asub.assignment = {$instance->id}
                                               AND asub.`status` = 'submitted'";
                                             
                                    if($gradedSunmissions = $DB->get_records_sql($sql)){
                                        foreach ($gradedSunmissions as $gradedSunmission) {
                                            if(! $gradedSunmission->grade){
                                                if(isset($modgrades->grades[$gradedSunmission->userid])){
                                                    unset($modgrades->grades[$gradedSunmission->userid]);
                                                }
                                            }
                                        }
                                    }                      
                                }
                                //////////////////////////////////
                            }
                            if ($modgrades) { 
                                /// Store the number of ungraded entries for this group.
                                if (is_array($modgrades->grades) && is_array($students)) {
                                    $gradedarray = array_intersect(array_keys($students), array_keys($modgrades->grades)); 
                                    $numgraded = count($gradedarray);
                                    $numstudents = count($students);
                                    $ungradedfunction = $mod->modname . '_count_ungraded';
                                    if (function_exists($ungradedfunction)) {
                                        $extra = false;
                                        $ung = $ungradedfunction($instance->id, $gradedarray, $students, $info, $extra, $instance, $resubmission);
                                    } else {
                                        $ung = $numstudents - $numgraded;
                                    }
                                    if ($marker) {
                                        $totungraded += $ung;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    } // a new Moodle nesting record? ;-)

    return $totungraded;
}


/**
 * Count the number of students who haven't logged in.
 */
function fn_count_notloggedin($course, $days) {

    $truants = fn_get_notloggedin($course, $days);
    return count($truants);
}


/**
 * @return array of students
 */                                             
function fn_get_notloggedin($course, $days) {
    global $CFG, $DB;

    // grab context
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    
    //grab current group
    $currentgroup = get_current_group($course->id); 
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    // calculate a the before
    $now = time();
    $lastweek = $now - (60 * 60 * 24 * $days);

    // students who haven't logged in
    $truants = array();

    // iterate
    foreach ($students as $student) {

        // possible fields: lastaccess, lastlogin, currentlogin
        $lastaccess = $student->lastaccess;        
        if ($lastaccess < $lastweek) {
            $truants[] = $student;
        }
    }

    return $truants;
}


/**
 *
 * @param $course 
 * @param x-percent percent to calculate percent below x percent
 * @return array of students with grade less than x percent
 */
function fn_get_failing($course, $percent) {
    global $CFG, $DB;

    //grab context
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

   
    $student_ids = array();    

    // grab  current group
    $currentgroup = get_current_group($course->id);    
    $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    
    // students array is keyed on id
    if ($students) {
        foreach ($students as $student) {
            $student_ids[] = $student->id;
        }
    }

    // grab all grades; keyed by userid
    // students array is keyed on id
    $all_grades = grade_get_course_grades($course->id, $student_ids);
    $grades = $all_grades->grades;

    //
    $failing = array();

    // create array of all failing students
    // keyed on studentid
    foreach ($grades as $studentid => $grade_obj) {

        // grab grade and convert to int (NULL -> 0)
        $grade = (int) $grade_obj->grade;

        if ($grade < $percent) {
            $failing[$studentid] = $students[$studentid];
        }
    }

    return $failing;
}


/**
 *
 * @param $get student not submitted assingment
 */
function fn_count_failing($course, $percent) {
    return count(fn_get_failing($course, $percent));
}


/**
 * @return array of students or integer of total count
 */
function fn_get_notsubmittedany($course, $since = 0, $count = false, $sections, $mod_array, $students) {

    global $CFG, $DB;

    // grab context
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    // grab modules
    if (!isset($mod_array)) {
        get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
    } else {
        $mods = $mod_array[0];
        $modnames = $mod_array[1];
        $modnamesplural = $mod_array[2];
        $modnamesused = $mod_array[3];
    }

    // get current group
    $currentgroup = get_current_group($course->id);   

    // grab modgradesarry
    $mod_grades_array = fn_get_active_mods();

    if (!isset($students)) {
        $students = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.*', 'u.id');
    }

    //for ($i = 0; $i <= $course->numsections; $i++) {
    for ($i = 0; $i < sizeof($sections); $i++) {
        if (isset($sections[$i])) {   // should always be true
            $section = $sections[$i];
            if ($section->sequence) {
                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    if (empty($mods[$sectionmod])) {
                        continue;
                    }
                    $mod = $mods[$sectionmod];
                    if (isset($mod_grades_array[$mod->modname])) {
                        require_once('locallib.php');
                        // build mod method
                        $f = $mod->modname . '_get_notsubmittedany';
                        // make sure function exists
                        if (!function_exists($f)) {
                            continue;
                        }

                        // grab list of students with submissions for this activity
                        $students_with_submissions = $f($course->id, $mod->instance, $students, $since);
                        if ($students_with_submissions) {
                            $student_ids = array_keys($students);
                            $sws_ids = array_keys($students_with_submissions);
                            foreach ($sws_ids as $id) {
                                unset($students[$id]);
                            }
                        }

                        // if all students have a submission, return null
                        if (empty($students)) {
                            if ($count) {
                                return 0;
                            } else {
                                return;
                            }
                        }
                    } // wrong activity type
                } // move onto next sectionmod
            } // $section has mods in it
        } // should always be true?
    } // next section

    if ($count) {
        return count($students);
    } else {
        return $students;
    }
}


/**
 *
 * @param $which can be 'grades', 'display', or 'activities'
 * @return array
 */
function fn_get_active_mods($which = 'grades') {

    /// Array of functions to call for grading purposes for modules.
    $mod_grades_array = array(
        'assignment' => 'assignment.submissions.fn.html',
        'forum' => 'forum.submissions.fn.html'
    );

    /// Array of functions to call to display grades for modules.
    $mod_gradedisp_array = array(
        'assignment' => 'grades.fn.html',
        'forum' => 'grades.fn.html'
    );

    $mod_array = array(
        'assignment',
        'forum'
    );

    switch ($which) {
        case 'grades':
            return $mod_grades_array;
        case 'display':
            return $mod_gradedisp_array;
        case 'activities':
            return $mod_array;
        default:
            return $mod_array;
    }
}


/**
* See if this assignment has a grade yet
*
* @param int $userid
* @param obj $assign
* @return bool
*/
function fn_is_graded($userid, $assign) {
    $grade = $assign->get_user_grade($userid, false);
    if ($grade) {
        return ($grade->grade !== NULL && $grade->grade >= 0);
    }
    return false;
}


function fn_get_grading_instance($userid, $gradingdisabled, $assign) {
    global $CFG, $USER;

    $grade = $assign->get_user_grade($userid, false);
    $grademenu = make_grades_menu($assign->get_instance()->grade);

    $advancedgradingwarning = false;
    $gradingmanager = get_grading_manager($assign->get_context(), 'mod_assign', 'submissions');
    $gradinginstance = null;
    if ($gradingmethod = $gradingmanager->get_active_method()) {
        $controller = $gradingmanager->get_controller($gradingmethod);
        if ($controller->is_form_available()) {
            $itemid = null;
            if ($grade) {
                $itemid = $grade->id;
            }
            if ($gradingdisabled && $itemid) {
                $gradinginstance = ($controller->get_current_instance($USER->id, $itemid));
            } else if (!$gradingdisabled) {
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                $gradinginstance = ($controller->get_or_create_instance($instanceid, $USER->id, $itemid));
            }
        } else {
            $advancedgradingwarning = $controller->form_unavailable_notification();
        }
    }
    if ($gradinginstance) {
        $gradinginstance->get_controller()->set_grade_range($grademenu);
    }
    return $gradinginstance;
}


/**
* Load the plugins from the sub folders under subtype
* @param string $subtype - either submission or feedback
* @return array - The sorted list of plugins
*/
function fn_load_plugins($subtype, $assign) {
   global $CFG;
   $result = array();

   $names = get_plugin_list($subtype);

   foreach ($names as $name => $path) {
       if (file_exists($path . '/locallib.php')) {
           require_once ($path . '/locallib.php');

           $shortsubtype = substr($subtype, strlen('assign'));
           $pluginclass = 'assign_' . $shortsubtype . '_' . $name;

           $plugin = new $pluginclass($assign, $name);

           if ($plugin instanceof assign_plugin) {
               $idx = $plugin->get_sort_order();
               while (array_key_exists($idx, $result)) $idx +=1;
               $result[$idx] = $plugin;
           }
       }
   }
   ksort($result);
   return $result;
}


/**
 * Apply a grade from a grading form to a user (may be called multiple times for a group submission)
 *
 * @param stdClass $formdata - the data from the form
 * @param int $userid - the user to apply the grade to
 * @return void
 */
function fn_apply_grade_to_user($formdata, $userid, $assign) {
    global $USER, $CFG, $DB, $pageparams;

    if($pageparams['resubmission']){
        $submissionnum = $formdata->submissionnum;
        $grade = $assign->get_user_grade($userid, true, $submissionnum);
    }else{
        $grade = $assign->get_user_grade($userid, true);
    }
    $gradingdisabled = $assign->grading_disabled($userid);
    $gradinginstance = fn_get_grading_instance($userid, $gradingdisabled, $assign);
    if (!$gradingdisabled) {
        if ($gradinginstance) {
            $grade->grade = $gradinginstance->submit_and_get_grade($formdata->advancedgrading, $grade->id);
        } else {
            // Handle the case when grade is set to No Grade.
            if (isset($formdata->grade)) {
                $grade->grade= grade_floatval(unformat_float($formdata->grade));
            }
        }
    }
    $grade->grader= $USER->id;

    $adminconfig = $assign->get_admin_config();
    $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

    // Call save in plugins.
    $feedbackplugins = fn_load_plugins('assignfeedback', $assign);
    
    foreach ($feedbackplugins as $plugin) {
        if ($plugin->is_enabled() && $plugin->is_visible()) {
            if (!$plugin->save($grade, $formdata)) {
                $result = false;
                print_error($plugin->get_error());
            }
            if (('assignfeedback_' . $plugin->get_type()) == $gradebookplugin) {
                // This is the feedback plugin chose to push comments to the gradebook.
                $grade->feedbacktext = $plugin->text_for_gradebook($grade);
                $grade->feedbackformat = $plugin->format_for_gradebook($grade);
            }
        }
    }
    $assign->update_grade($grade);
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    $assign->add_to_log('grade submission', $assign->format_grade_for_log($grade));
    
    //////////////////////
    if (isset($formdata->submissionid)){
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', array('submission'=>$formdata->submissionid));
        $onlinetext->onlinetext = $formdata->onlinetext['text'];
        $DB->update_record('assignsubmission_onlinetext', $onlinetext);       
    }
    
                
}     


/**
 * save outcomes submitted from grading form
 *
 * @param int $userid
 * @param stdClass $formdata
 */
function fn_process_outcomes($userid, $formdata, $assign) {
    global $CFG, $USER;

    if (empty($CFG->enableoutcomes)) {
        return;
    }
    if ($assign->grading_disabled($userid)) {
        return;
    }

    require_once($CFG->libdir.'/gradelib.php');

    $data = array();
    $gradinginfo = grade_get_grades($assign->get_course()->id,
                                    'mod',
                                    'assign',
                                    $assign->get_instance()->id,
                                    $userid);

    if (!empty($gradinginfo->outcomes)) {
        foreach($gradinginfo->outcomes as $index=>$oldoutcome) {
            $name = 'outcome_'.$index;
            if (isset($formdata->{$name}[$userid]) and $oldoutcome->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                $data[$index] = $formdata->{$name}[$userid];
            }
        }
    }
    if (count($data) > 0) {
        grade_update_outcomes('mod/assign', $assign->course->id, 'mod', 'assign', $assign->get_instance()->id, $userid, $data);
    }

}


/**
 * save grade
 *
 * @param  moodleform $mform
 * @return bool - was the grade saved
 */
function fn_process_save_grade(&$mform, $assign, $context, $course, $pageparams) {
    global $CFG;
    // Include grade form
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment
    require_capability('mod/assign:grade', $context);
    require_sesskey();

    $rownum = required_param('rownum', PARAM_INT);
    $useridlist = optional_param('useridlist', '', PARAM_TEXT);
    if ($useridlist) {
        $useridlist = explode(',', $useridlist);
    } else {
        $useridlist = $assign->get_grading_userid_list();
    }
    $last = false;
    $userid = $useridlist[$rownum];
    if ($rownum == count($useridlist) - 1) {
        $last = true;
    }
    if($pageparams['resubmission']){
        $submissionnum = optional_param('submissionnum', null, PARAM_INT);
        $pageparams['submissionnum']  = $submissionnum;
    }
    $data = new stdClass();
    
    $pageparams['rownum']     = $rownum;
    $pageparams['useridlist'] = $useridlist;
    $pageparams['last']       = $last;
    $pageparams['savegrade']  = true;
    
    if($pageparams['resubmission']){
        $pageparams['submissionnum'] = optional_param('submissionnum', null, PARAM_INT);
        //$pageparams['maxsubmissionnum'] = $maxsubmissionnum;
    }
    
    $formparams = array($assign, $data, $pageparams); 
    
    $mform = new mod_assign_grading_form_fn(null, $formparams, 'post', '', array('class'=>'gradeform'));
    
    if ($formdata = $mform->get_data()) { //print_r($formdata);die; 
        if ($assign->get_instance()->teamsubmission && $formdata->applytoall) {
            $groupid = 0;
            if ($assign->get_submission_group($userid)) {
                $group = $assign->get_submission_group($userid);
                if ($group) {
                    $groupid = $group->id;
                }
            }
            $members = $assign->get_submission_group_members($groupid, true);
            foreach ($members as $member) {
                // User may exist in multple groups (which should put them in the default group).
                fn_apply_grade_to_user($formdata, $member->id, $assign);
                fn_process_outcomes($member->id, $formdata, $assign);
            }
        } else {
            fn_apply_grade_to_user($formdata, $userid, $assign);
            fn_process_outcomes($userid, $formdata, $assign);
        }
        if($pageparams['resubmission']){
            if ($assign->get_instance()->resubmission == assign::RESUBMISSION_MANUAL) {
                if (isset($formdata->resubmission)) {
                    if ($formdata->resubmission) {
                        fn_add_resubmission($userid, $assign);
                    } else {
                        fn_remove_resubmission($userid, $assign);
                    }
                }
            }
        }        
    } else {
        return false;
    }
    return true;
}


/**
 * Print the grading page for a single user submission
 *
 * @param moodleform $mform
 * @param int $offset
 * @return string
 */
function fn_view_single_grade_page($mform, $offset=0, $assign, $context, $cm, $course, $pageparams, $showsubmissionnum=null) {
    global $DB, $CFG;

    $o = '';
    $instance = $assign->get_instance();

    // Include grade form
    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment
    $readonly = false;
    if(! has_capability('mod/assign:grade', $context)){
        if(has_capability('block/fn_marking:viewreadonly',$context)){
            $readonly = true;
        }else{
            require_capability('mod/assign:grade', $context);    
        }
    }
    

    $rownum = $pageparams['rownum'] + $offset;
    
    
    if($pageparams['userid']){         
        $userid = $pageparams['userid'];        
        
        $arruser = count_unmarked_students($course, $cm, $pageparams['show'], $pageparams['resubmission']);
        $useridlist = $arruser;
        $last = false;
        
        $rownum = array_search($userid, $useridlist); 
        if ($rownum == count($useridlist) - 1) {
            $last = true;
        }
       
    }else{    
        $arruser = count_unmarked_students($course, $cm, $pageparams['show'], $pageparams['resubmission']);
        $useridlist = optional_param('useridlist', '', PARAM_TEXT);
        if ($useridlist) {
            $useridlist = explode(',', $useridlist);
        } else {
            $useridlist = get_grading_userid_list($assign);
        }
        //
        $useridlist = $arruser;
        $last = false;
        $userid = $useridlist[$rownum];
        if ($rownum == count($useridlist) - 1) {
            $last = true;
        }
        if (!$userid) {
            $o = "There is no record";
            return $o;
            //throw new coding_exception('Row is out of bounds for the current grading table: ' . $rownum);
        }
    }
    
    $user = $DB->get_record('user', array('id' => $userid));

    if($pageparams['resubmission']){
        $submission = $assign->get_user_submission($userid, false, $showsubmissionnum);
    }else{
        $submission = $assign->get_user_submission($userid, false);
    }
    
    $submissiongroup = null;
    $submissiongroupmemberswhohavenotsubmitted = array();
    $teamsubmission = null;
    $notsubmitted = array();
    if ($assign->get_instance()->teamsubmission) {
        if($pageparams['resubmission']){
            $teamsubmission = $assign->get_group_submission($userid, 0, false, $showsubmissionnum);
        }else{
            $teamsubmission = $assign->get_group_submission($userid, 0, false);
        }
        $submissiongroup = $assign->get_submission_group($userid);
        $groupid = 0;
        if ($submissiongroup) {
            $groupid = $submissiongroup->id;
        }
        $notsubmitted = $assign->get_submission_group_members_who_have_not_submitted($groupid, false);

    }

    // get the current grade
    if($pageparams['resubmission']){
        $grade = $assign->get_user_grade($userid, false, $showsubmissionnum);

        // Get all the submissions (for the history view).
        list($allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum) =
            fn_get_submission_history($submission, $grade, $user, $showsubmissionnum, $assign);        
    }else{
        $grade = $assign->get_user_grade($userid, false);
    }
    
    if ($grade) {
        $data = new stdClass();
        if ($grade->grade !== NULL && $grade->grade >= 0) {
            $data->grade = format_float($grade->grade,2);
        }
    } else {
        $data = new stdClass();
        $data->grade = '-1';
    }

    // now show the grading form
    if($pageparams['resubmission']){ 
        if ($submissionnum != $maxsubmissionnum) {
            $o .= $assign->get_renderer()->edit_previous_feedback_warning($submissionnum, $maxsubmissionnum);
        }
    }
    if (!$mform) {
        $pageparams['rownum']     = $rownum;
        $pageparams['useridlist'] = $useridlist;
        $pageparams['last']       = $last;
        $pageparams['readonly']   = $readonly;        
         if($pageparams['resubmission']){
             $pageparams['submissionnum'] = $submissionnum;
             $pageparams['maxsubmissionnum'] = $maxsubmissionnum;
         }
        
        $formparams = array($assign, $data, $pageparams); 
        
        $mform = new mod_assign_grading_form_fn(null,
                                               $formparams,
                                               'post',
                                               '',
                                               array('class'=>'gradeform'));
    }
    $o .= $assign->get_renderer()->render(new assign_form('gradingform', $mform));
    
    if($pageparams['resubmission']){
        $o .= $assign->get_renderer()->render(new assign_submission_history($allsubmissions, $allgrades, $submissionnum,
                                                                          $maxsubmissionnum, $assign->get_submission_plugins(),
                                                                          $assign->get_feedback_plugins(),
                                                                          $assign->get_course_module()->id,
                                                                          $assign->get_return_action(),
                                                                          $assign->get_return_params(),
                                                                          true));
    }

    $msg = get_string('viewgradingformforstudent', 
                      'assign', 
                      array('id'=>$user->id, 'fullname'=>fullname($user)));
    $assign->add_to_log('view grading form', $msg);


    return $o;
}



/**
 * Print the grading page for a single user submission.
 *
 * @param moodleform $mform
 * @param int $offset
 * @param int $showsubmissionnum optional the submission to show (default = the most recent)
 * @return string
 */
function fn_view_submissions($mform, $offset=0, $showsubmissionnum=null, $assign, $ctx, $cm, $course, $pageparams) {
    global $DB, $CFG, $OUTPUT;

    $o = '';
    $instance = $assign->get_instance();

    require_once($CFG->dirroot . '/mod/assign/gradeform.php');

    // Need submit permission to submit an assignment.
        $readonly = false;
        if(! has_capability('mod/assign:grade', $ctx)){
            if(has_capability('block/fn_marking:viewreadonly',$ctx)){
                $readonly = true;
            }else{
                require_capability('mod/assign:grade', $ctx);    
            }
        }

    $rownum = optional_param('rownum', 0, PARAM_INT) + $offset;
    $arruser = count_unmarked_students($course, $cm, $pageparams['show'], $pageparams['resubmission'], $pageparams['sort']);
    
    $useridlist = optional_param('useridlist', '', PARAM_TEXT);
    
    if ($useridlist) {
        $useridlist = explode(',', $useridlist);
    } else {
        $useridlist = get_grading_userid_list($assign);
    }
    $useridlist = $arruser;
    $last = false;
    $userid = $useridlist[$rownum];
    if ($rownum == count($useridlist) - 1) {
        $last = true;
    }
    if (!$userid) {
        return 'There is no user.';
        //throw new coding_exception('Row is out of bounds for the current grading table: ' . $rownum);
    }
    
    if ($pageparams['show']=='unsubmitted'){
        
        $unsubmitted = array();
        
        foreach ($useridlist as $key => $userid) {     
           
            //$user = $DB->get_record('user', array('id' => $userid));
   
            if($submission = $assign->get_user_submission($userid, false, $showsubmissionnum)){
                if ($submission->status == 'draft'){
                    $unsubmitted[$userid] = $userid;
                }
            }else{
                $unsubmitted[$userid] = $userid;
            }
        }
        
        if(count($unsubmitted)>0){
                
            $image = "<A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\"> <IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/$cm->modname/pix/icon.gif\" " .
                    "HEIGHT=16 WIDTH=16 ALT=\"$cm->modname\"></A>";
                                             
            $o .= '<div class="unsubmitted_header">' . $image .
                                        " Assignment: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" . $assign->get_instance()->name . '</a></div>';            
            
                                 
            $o .= '<p class="unsubmitted_msg">The following students have not submitted this assignment:</p>';           
            
            foreach ($unsubmitted as $userid) {
            /// Check that this user hasn't submitted before.             
                
                $o .= "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                $o .= "\n<tr>";
                $o .= "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";                 
                $user = $DB->get_record('user',array('id'=>$userid));
                $o .= $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                $o .= "</td>";
                $o .= "<td width=\"100%\" class=\"rightName\"><strong>".fullname($user, true)."</strong></td>\n";
                $o .= "</tr></table>\n";
                
            }
        }
        else if(count($unsubmitted)==0){
                 $o .= '<center><p>The are currently no <b>users</b>  to display.</p></center>';
        }        
        
    }else{      
    
        foreach ($useridlist as $key => $userid) {     
           
            $user = $DB->get_record('user', array('id' => $userid));
            /*
            if ($user) {
                $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
                $usersummary = new assign_user_summary($user,
                                                       $assign->get_course()->id,
                                                       $viewfullnames,
                                                       $assign->is_blind_marking(),
                                                       $assign->get_uniqueid_for_user($user->id));
                $o .= $assign->get_renderer()->render($usersummary);
            }
            */
            $submission = $assign->get_user_submission($userid, false, $showsubmissionnum);
            $submissiongroup = null;
            $submissiongroupmemberswhohavenotsubmitted = array();
            $teamsubmission = null;
            $notsubmitted = array();
            if ($instance->teamsubmission) {
                $teamsubmission = $assign->get_group_submission($userid, 0, false, $showsubmissionnum);
                $submissiongroup = $assign->get_submission_group($userid);
                $groupid = 0;
                if ($submissiongroup) {
                    $groupid = $submissiongroup->id;
                }
                $notsubmitted = $assign->get_submission_group_members_who_have_not_submitted($groupid, false);
            }

            // Get the current grade.
            $grade = $assign->get_user_grade($userid, false, $showsubmissionnum);

            
            if($pageparams['resubmission']){
                // Get all the submissions (for the history view).
                list($allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum) =
                    fn_get_submission_history_view($submission, $grade, $user, $showsubmissionnum, $assign);
                }                                        
            if ($grade) {
                $data = new stdClass();
                if ($grade->grade !== null && $grade->grade >= 0) {
                    $data->grade = format_float($grade->grade, 2);
                }
            } else {
                $data = new stdClass();
                $data->grade = '';
            }
            
            if($pageparams['resubmission']){
                // Now show the grading form.
                if ($submissionnum != $maxsubmissionnum) {
                    $o .= $assign->get_renderer()->edit_previous_feedback_warning($submissionnum, $maxsubmissionnum);
                } 

                //$o .= $assign->get_renderer()->render(new assign_submission_history($allsubmissions, $allgrades, $submissionnum,
                $o .= fn_render_assign_submission_history_summary(new assign_submission_history($allsubmissions, $allgrades, $submissionnum,
                                                                                  $maxsubmissionnum, $assign->get_submission_plugins(),
                                                                                  $assign->get_feedback_plugins(),
                                                                                  $assign->get_course_module()->id,
                                                                                  $assign->get_return_action(),
                                                                                  $assign->get_return_params(),
                                                                                  true), 
                                                                                  $assign->get_renderer(),
                                                                                  $user,
                                                                                  $assign
                                                                                  );
            }else{
                
                $gradelocked = ($grade && $grade->locked) || $assign->grading_disabled($userid);
                $extensionduedate = null;
                if ($grade) {
                    $extensionduedate = $grade->extensionduedate;
                }
                $showedit = $assign->submissions_open($userid) && ($assign->is_any_submission_plugin_enabled());

                if ($teamsubmission) {
                    $showsubmit = $showedit && $teamsubmission && ($teamsubmission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
                } else {
                    $showsubmit = $showedit && $submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
                }
                if (!$assign->get_instance()->submissiondrafts) {
                    $showsubmit = false;
                }
                $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());

                
                $o .= fn_render_assign_submission_status(new assign_submission_status($assign->get_instance()->allowsubmissionsfromdate,
                                                                  $assign->get_instance()->alwaysshowdescription,
                                                                  $submission,
                                                                  $assign->get_instance()->teamsubmission,
                                                                  $teamsubmission,
                                                                  $submissiongroup,
                                                                  $notsubmitted,
                                                                  $assign->is_any_submission_plugin_enabled(),
                                                                  $gradelocked,
                                                                  is_graded($assign, $userid),
                                                                  $assign->get_instance()->duedate,
                                                                  $assign->get_instance()->cutoffdate,
                                                                  $assign->get_submission_plugins(),
                                                                  $assign->get_return_action(),
                                                                  $assign->get_return_params(),
                                                                  $assign->get_course_module()->id,
                                                                  $assign->get_course()->id,
                                                                  assign_submission_status::GRADER_VIEW,
                                                                  $showedit,
                                                                  $showsubmit,
                                                                  $viewfullnames,
                                                                  $extensionduedate,
                                                                  $assign->get_context(),
                                                                  $assign->is_blind_marking(),
                                                                  ''),
                                                                  $assign, 
                                                                  $user,
                                                                  $grade,
                                                                  $assign->get_renderer());            
            }
            
            
            $msg = get_string('viewgradingformforstudent',
                              'assign',
                              array('id'=>$user->id, 'fullname'=>fullname($user)));
            $assign->add_to_log('view grading form', $msg);
            
            
        }
    }
    

    return $o;
}

         

/**
 * Get the submission and grading details for all previous submissions
 * @param stdClass $submission the assign_submission record for the current submission
 * @param stdClass $grade the assign_grade record for the current submission (may be empty)
 * @param stdClass $user the user record for the user who has submitted the assignment
 * @param int $showsubmissionnum the submission number requested to be displayed
 * @return array [$allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum]
 */
function fn_get_submission_history($submission, $grade, $user, $showsubmissionnum, $assign) {
    global $DB;

    $submissionnum = ($submission) ? $submission->submissionnum : 1;
    $allsubmissions = array($submissionnum => $submission);
    $allgrades = array($submissionnum => $grade);
    $graders = array();
    if (is_null($showsubmissionnum)) {
        // If submissionnum was not set, then we already have the most recent submission.
        $maxsubmissionnum = $submissionnum;
    } else {
        // Get the most recent submission.
        if ($maxsub = $assign->get_user_submission($user->id, false)) {
            $maxsubmissionnum = $maxsub->submissionnum;
            $allsubmissions[$maxsub->submissionnum] = $maxsub;
        } else {
            $maxsubmissionnum = 0;
        }
    }
    for ($i=1; $i<=$maxsubmissionnum; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id'=>$allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }

    return array($allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum);
}


/**
 * Get the submission and grading details for all previous submissions
 * @param stdClass $submission the assign_submission record for the current submission
 * @param stdClass $grade the assign_grade record for the current submission (may be empty)
 * @param stdClass $user the user record for the user who has submitted the assignment
 * @param int $showsubmissionnum the submission number requested to be displayed
 * @return array [$allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum]
 */
function fn_get_submission_history_view($submission, $grade, $user, $showsubmissionnum, $assign) {
    global $DB;
    
    $submissionnum = ($submission) ? $submission->submissionnum : 1;
    $allsubmissions = array();
    $allgrades = array();
    //$allsubmissions = array($submissionnum => $submission);
    //$allgrades = array($submissionnum => $grade);
    $graders = array();
    if (is_null($showsubmissionnum)) {
        // If submissionnum was not set, then we already have the most recent submission.
        $maxsubmissionnum = $submissionnum;
    } else {
        // Get the most recent submission.
        if ($maxsub = $assign->get_user_submission($user->id, false)) {
            $maxsubmissionnum = $maxsub->submissionnum;
            $allsubmissions[$maxsub->submissionnum] = $maxsub;
        } else {
            $maxsubmissionnum = 0;
        }
    }
    for ($i=1; $i<=$maxsubmissionnum; $i++) {
        // Retrieve any submissions / grades we haven't already retrieved.
        if (!array_key_exists($i, $allsubmissions)) {
            $allsubmissions[$i] = $assign->get_user_submission($user->id, false, $i);
        }
        if (!array_key_exists($i, $allgrades)) {
            $allgrades[$i] = $assign->get_user_grade($user->id, false, $i);
            if ($allgrades[$i]) {
                $allgrades[$i]->gradefordisplay = $assign->display_grade($allgrades[$i]->grade, false);
                if (!array_key_exists($allgrades[$i]->grader, $graders)) {
                    $graders[$allgrades[$i]->grader] = $DB->get_record('user', array('id'=>$allgrades[$i]->grader));
                }
                $allgrades[$i]->grader = $graders[$allgrades[$i]->grader];
            }
        }
    }

    return array($allsubmissions, $allgrades, $submissionnum, $maxsubmissionnum);
}


/**
 * Directly add a new resubmission (without checking the current user has permission to do so)
 * @param $userid
 */
function fn_add_resubmission($userid, $assign) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    $currentgrade = $assign->get_user_grade($userid, false);
    if (!$currentgrade) {
        return; // If the most recent submission is not graded, then resubmissions are not allowed.
    }
    if ($assign->reached_resubmission_limit($currentgrade->submissionnum)) {
        return; // Already reached the resubmission limit.
    }
    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, true); // Create the submission, if it doesn't already exist.
    } else {
        $submission = $assign->get_user_submission($userid, true); // Create the submissoin, if it doesn't already exist.
    }

    // Set the submission's status to resubmission.
    $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_RESUBMISSION, array('id' => $submission->id));

    $assign->add_to_log('add resubmission', get_string('addresubmissionforstudent', 'assign',
                                                     array('id'=>$user->id, 'fullname'=>fullname($user))));
}

/**
 * Directly remove a resubmission (without checking the current user has permission to do so)
 * @param $userid
 */
function fn_remove_resubmission($userid, $assign) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

    if ($assign->get_instance()->teamsubmission) {
        $submission = $assign->get_group_submission($userid, 0, false);
    } else {
        $submission = $assign->get_user_submission($userid, false);
    }

    if (!$submission || $submission->status != ASSIGN_SUBMISSION_STATUS_RESUBMISSION) {
        return; // No resubmission currently open.
    }

    $DB->set_field('assign_submission', 'status', ASSIGN_SUBMISSION_STATUS_SUBMITTED, array('id' => $submission->id));

    // Set the submission's status to resubmission.
    $assign->add_to_log('remove resubmission', get_string('removeresubmissionforstudent', 'assign',
                                                        array('id'=>$user->id, 'fullname'=>fullname($user))));
}


/**
 * Utility function to get the userid for every row in the grading table
 * so the order can be frozen while we iterate it
 *
 * @param assign $assign
 * @return array An array of userids
 */
function get_grading_userid_list($assign){
    global $CFG;

    require_once($CFG->dirroot.'/mod/assign/gradingtable.php');

    $filter = get_user_preferences('assign_filter', '');
    $table = new assign_grading_table($assign, 0, $filter, 0, false);

    $useridlist = $table->get_column_data('userid');

    return $useridlist;
}

/**
 * Load the submission object for a particular user, optionally creating it if required
 *
 * @param assign $assign
 * @param int $userid The id of the user whose submission we want or 0 in which case USER->id is used
 * @param bool $create optional Defaults to false. If set to true a new submission object will be created in the database
 * @return stdClass The submission
 */
function get_user_submission($assign, $userid, $create, $submissionnum = null) {
    global $DB, $USER, $pageparams;

    if (!$userid) {
        $userid = $USER->id;
    }
    
    if($pageparams['resubmission']){            
        // If the userid is not null then use userid.
        $params = array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid, 'groupid'=>0);
        if (!is_null($submissionnum)) {
            $params['submissionnum'] = $submissionnum;
        }
        $submission = $DB->get_records('assign_submission', $params, 'submissionnum DESC', '*', 0, 1);
    }else{
        // if the userid is not null then use userid
        $submission = $DB->get_record('assign_submission', array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid));            
    }


    if ($submission) {
        if($pageparams['resubmission']){
            return reset($submission);
        }else{
            return $submission;
        }
    }
    if ($create) {
        $submission = new stdClass();
        $submission->assignment   = $assign->get_instance()->id;
        $submission->userid       = $userid;
        $submission->timecreated = time();
        $submission->timemodified = $submission->timecreated;

        if ($assign->get_instance()->submissiondrafts) {
            $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        } else {
            $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        }
        if($pageparams['resubmission']){
            $submission->submissionnum = is_null($submissionnum) ? 1 : $submissionnum;
        }
        $sid = $DB->insert_record('assign_submission', $submission);
        $submission->id = $sid;
        return $submission;
    }
    return false;
}

/**
 * This will retrieve a grade object from the db
 *
 * @param assign $assign
 * @param int $userid The user we are grading
 * @return stdClass The grade record
 */
function get_user_grade($assign, $userid) {
    global $DB, $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    // if the userid is not null then use userid
    $grade = $DB->get_record('assign_grades', array('assignment'=>$assign->get_instance()->id, 'userid'=>$userid));

    if ($grade) {
        return $grade;
    }
    return false;
}

/**
 * See if this assignment has a grade yet
 *
 * @param assign $assign
 * @param int $userid
 * @return bool
 */
function is_graded($assign, $userid) {
    $grade = get_user_grade($assign, $userid);
    if ($grade) {
        return ($grade->grade !== NULL && $grade->grade >= 0);
    }
    return false;
}


/**
 * Output the submission / grading history for this assignment
 *
 * @param assign_submission_history $history
 * @return string
 */
function fn_render_assign_submission_history(assign_submission_history $history, $assign_renderer) {
    global $OUTPUT, $DB;
    $historyout = '';
    for ($i=$history->maxsubmissionnum; $i>0; $i--) {
        /*
        if ($i == $history->submissionnum) {
            // Do not show the currently-selected submission in the submission history.
            if ($i != $history->maxsubmissionnum) {
                $historyout .= html_writer::tag('div', get_string('submissionnum', 'assign', $i),
                                                array('class' => 'currentsubmission'));
            }
            continue;
        }
        if (!array_key_exists($i, $history->allsubmissions)) {
            continue;
        }
        */
 
        $submission = $history->allsubmissions[$i];
        $grade = $history->allgrades[$i];

        $editbtn = '';
        if ($history->grading) {
            $params = array(
                'id' => $history->coursemoduleid,
                'action' => $history->returnaction,
                'showsubmissionnum' => $submission->submissionnum
            );
            $params = array_merge($params, $history->returnparams);
            $editurl = new moodle_url('/mod/assign/view.php', $params);
            $editbtn = $OUTPUT->single_button($editurl, get_string('editfeedback', 'mod_assign'), 'get');
        }

        $t = new html_table();
        $cell = new html_table_cell(get_string('submissionnum', 'assign', $i).' '.$editbtn);//Submission # and button row
        $cell->attributes['class'] = 'historytitle';
        $cell->colspan = 2;
        $t->data[] = new html_table_row(array($cell));

        if ($submission) {
            $cell1 = get_string('submitted', 'assign');
            $cell2 = userdate($submission->timemodified);  
            $t->data[] = new html_table_row(array($cell1, $cell2));                 
            foreach ($history->submissionplugins as $plugin) {
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !$plugin->is_empty($submission)) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginsubmission = new assign_submission_plugin_submission($plugin,
                                                                                $submission,
                                                                                assign_submission_plugin_submission::SUMMARY,
                                                                                $history->coursemoduleid,
                                                                                $history->returnaction,
                                                                                $history->returnparams);
                    $cell2 = new html_table_cell($assign_renderer->render($pluginsubmission));

                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }
            }
        }

        if ($grade) { 
            // Heading 'feedback'.
            $cell = new html_table_cell(get_string('feedback', 'assign', $i));
            $cell->attributes['class'] = 'historytitle';
            $cell->colspan = 2;
            $t->data[] = new html_table_row(array($cell));

            // Grade.
            $cell1 = new html_table_cell(get_string('grade'));
            $cell2 = $grade->gradefordisplay;
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded on.
            $cell1 = new html_table_cell(get_string('gradedon', 'assign'));
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Graded by.
            $cell1 = new html_table_cell(get_string('gradedby', 'assign'));
            $cell2 = new html_table_cell($OUTPUT->user_picture(is_object($grade->grader) ? $grade->grader : $DB->get_record('user', array('id'=>$grade->grader))) .
                                             $OUTPUT->spacer(array('width'=>30)) . fullname($grade->grader));
            $t->data[] = new html_table_row(array($cell1, $cell2));

            // Feedback from plugins.
            foreach ($history->feedbackplugins as $plugin) {
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !$plugin->is_empty($grade)) {

                    $cell1 = new html_table_cell($plugin->get_name());
                    $pluginfeedback = new assign_feedback_plugin_feedback(
                        $plugin, $grade, assign_feedback_plugin_feedback::SUMMARY, $history->coursemoduleid,
                        $history->returnaction, $history->returnparams
                    );
                    $cell2 = new html_table_cell($assign_renderer->render($pluginfeedback));
                    $t->data[] = new html_table_row(array($cell1, $cell2));
                }

            }

        }

        $historyout .= html_writer::table($t);
    }

    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory');
        $o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;
}


/**
 * Output the submission / grading history for this assignment
 *
 * @param assign_submission_history $history
 * @return string
 */
function fn_render_assign_submission_history_summary(assign_submission_history $history, $assign_renderer, $user, $assign) {
    global $OUTPUT, $DB, $CFG, $pageparams;
    $historyout = '';
    
    if ($user) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
        $summary = new assign_user_summary($user,
                                               $assign->get_course()->id,
                                               $viewfullnames,
                                               $assign->is_blind_marking(),
                                               $assign->get_uniqueid_for_user($user->id));
        
        //$modulename =  $assign->get_course_module()->modname;
        $gradeitem = $DB->get_record('grade_items', array('itemtype'=>'mod', 'itemmodule'=>'assign', 'iteminstance'=>$assign->get_instance()->id));
       
        
        
        
        $maxsubmissionnum = isset($pageparams['maxsubmissionnum']) ? $pageparams['maxsubmissionnum'] : sizeof($history->allsubmissions);
                                                      
        $resubstatus = '';
        
        //$maxsubmissionnum = isset($params['maxsubmissionnum']) ? $params['maxsubmissionnum'] : $params['submissionnum'];
        $resubtype = $assign->get_instance()->resubmission;
        if ($resubtype != assign::RESUBMISSION_NONE) {
            if ($assign->reached_resubmission_limit($maxsubmissionnum)) {
                $resubstatus = get_string('atmaxresubmission', 'assign');
            } else if ($resubtype == assign::RESUBMISSION_MANUAL) {  
                if ($history->allsubmissions[sizeof($history->allsubmissions)]->status == 'resub'){
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1" checked="checked" disabled="disabled" />';
                }else{
                    $resubstatus = 'Allow resubmit: <input name="checkbox" type="checkbox" id="checkbox" value="1"  disabled="disabled" />';
                }
                
            } else if ($resubtype == assign::RESUBMISSION_FAILEDGRADE) {
                $gradepass = $gradeitem->gradepass;
                if ($gradeitem->gradepass > 0) {
                    $resubstatus = get_string('resubmissiononfailedgrade', 'assign', round($gradepass,1));
                }
            }
        }
        
        
        
        
        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)';
            }
            

        }else{
            $groupname = '';
        }        
           
           
           
           
           
           
                                                          
      
        $header = '<table class="headertable"><tr>';
   
        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            //$header .= $OUTPUT->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);
            
            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($summary->user, $summary->viewfullnames), null, array('target'=>'_blank', 'class'=>'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .$assign->get_instance()->name.'</a></div></td>';
            $header .= '<td align="right" style="color:white;">'.$resubstatus.'</td>';
        }
        $header .= '</tr></table>';
                                                       
        
    }
    
    $t = new html_table();               
    $t->attributes['class'] = 'generaltable historytable';
    $cell = new html_table_cell($header);
    $cell->attributes['class'] = 'historyheader';
    $cell->colspan = 3;
    $t->data[] = new html_table_row(array($cell));        
    

    
    $submitted_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $marked_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $saved_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    $marked_icon_incomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
     
    for ($i=$history->maxsubmissionnum; $i>0; $i--) {
        /*
        if ($i == $history->submissionnum) {
            // Do not show the currently-selected submission in the submission history.
            if ($i != $history->maxsubmissionnum) {
                $historyout .= html_writer::tag('div', get_string('submissionnum', 'assign', $i),
                                                array('class' => 'currentsubmission'));
            }
            continue;
        }
        if (!array_key_exists($i, $history->allsubmissions)) {
            continue;
        }
        */
        
        $submission = $history->allsubmissions[$i]; 
        $grade = $history->allgrades[$i];


        if (($i == $history->maxsubmissionnum) && (isset($grade->grade))){
            $lastsubmission_class = (($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? 'bg_green' : 'bg_orange';
        }else{
            $lastsubmission_class = '';
        }
                
        
        $editbtn = '';
        if ($history->grading) {
            $params = array(
                'id' => $history->coursemoduleid,
                'action' => $history->returnaction,
                'showsubmissionnum' => $submission->submissionnum
            );
            $params = array_merge($params, $history->returnparams);
            $editurl = new moodle_url('/mod/assign/view.php', $params);
            $editbtn = $OUTPUT->single_button($editurl, get_string('editfeedback', 'mod_assign'), 'get');
        } 

        if ($grade) { 
            
            $cell1 = new html_table_cell($grade->gradefordisplay);
            $cell1->rowspan = 2;
            if ($i == $history->maxsubmissionnum){
                $cell1->attributes['class'] = $lastsubmission_class;
            }
            
            
            if ($submission->status == 'draft'){
                $cell2 = new html_table_cell($saved_icon . 'Draft');
            }else{
                $cell2 = new html_table_cell($submitted_icon . get_string('submitted', 'assign'));
            }
            
            $cell3 = new html_table_cell(userdate($submission->timemodified));
            if ($i == $history->maxsubmissionnum){
                $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.$pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.$pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].'&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';
                $cell2->attributes['class'] = $lastsubmission_class;
                $cell3->attributes['class'] = $lastsubmission_class;
            }
                        
            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));

            
            
            $cell1 = new html_table_cell(((($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? $marked_icon : $marked_icon_incomplete) . 'Marked');
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            if ($i == $history->maxsubmissionnum){
                $cell1->attributes['class'] = $lastsubmission_class;
                $cell2->attributes['class'] = $lastsubmission_class;
            }            
            $t->data[] = new html_table_row(array($cell1, $cell2));



        }

        
    }
    
    $historyout .= html_writer::table($t);
    
    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory_summary');
        //$o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;
}



/**
 * render a table containing the current status of the submission
 *
 * @param assign_submission_status $status
 * @return string
 */
function fn_render_assign_submission_status(assign_submission_status $status, $assign, $user, $grade, $assign_renderer) {
    global $OUTPUT, $DB, $CFG, $pageparams;
    $o = '';
    
    
    if ($user) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $assign->get_course_context());
        $summary = new assign_user_summary($user,
                                               $assign->get_course()->id,
                                               $viewfullnames,
                                               $assign->is_blind_marking(),
                                               $assign->get_uniqueid_for_user($user->id),
                                               NULL);
        
        //$modulename =  $assign->get_course_module()->modname;
        $gradeitem = $DB->get_record('grade_items', array('itemtype'=>'mod', 'itemmodule'=>'assign', 'iteminstance'=>$assign->get_instance()->id));
       
        
        
     
        
        
        if ($assign->get_instance()->teamsubmission) {

            $submissiongroup = $assign->get_submission_group($user->id);
            if (isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)';
            }
            

        }else{
            $groupname = '';
        }        
           
           
           
           
           
           
                                                          
      
        $header = '<table class="headertable"><tr>';
   
        if ($summary->blindmarking) {
            $header .= '<td>'.get_string('hiddenuser', 'assign') . $summary->uniqueidforuser;
            $header .= '<br />Assignment ' .$assign->get_instance()->name.'</td>';
        } else {
            $header .= '<td width="35px">'.$OUTPUT->user_picture($summary->user).'</td>';
            //$header .= $OUTPUT->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);
            
            $header .= '<td><div style="color:white;">'.$OUTPUT->action_link($url, fullname($summary->user, $summary->viewfullnames), null, array('target'=>'_blank', 'class'=>'userlink')). $groupname. '</div>';
            $header .= '<div style="margin-top:5px; color:white;">Assignment: <a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$assign->get_course_module()->id.'">' .$assign->get_instance()->name.'</a></div></td>';
        }
        $header .= '</tr></table>';
                                                       
        
    }
 
    
     //echo $header;die;
    
    
    
    
    
    
    
    //$o .= $OUTPUT->container_start('submissionstatustable');
    //$o .= $OUTPUT->heading(get_string('submissionstatusheading', 'assign'), 3);
    $time = time();
    /*
    if ($status->allowsubmissionsfromdate &&
            $time <= $status->allowsubmissionsfromdate) {
        $o .= $OUTPUT->box_start('generalbox boxaligncenter submissionsalloweddates');
        if ($status->alwaysshowdescription) {
            $o .= get_string('allowsubmissionsfromdatesummary', 'assign', userdate($status->allowsubmissionsfromdate));
        } else {
            $o .= get_string('allowsubmissionsanddescriptionfromdatesummary', 'assign', userdate($status->allowsubmissionsfromdate));
        }
        $o .= $OUTPUT->box_end();
    }
    */
    //$o .= $OUTPUT->box_start('boxaligncenter submissionsummarytable');

    $t = new html_table();
    $t->attributes['class'] = 'generaltable historytable';
    $cell = new html_table_cell($header);
    $cell->attributes['class'] = 'historyheader';
    $cell->colspan = 3;
    $t->data[] = new html_table_row(array($cell));        
    

    
    $submitted_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/text.gif" valign="absmiddle"> ';
    $marked_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/completed.gif" valign="absmiddle"> ';
    $saved_icon = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/saved.gif" valign="absmiddle"> ';
    $marked_icon_incomplete = '<img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/incomplete.gif" valign="absmiddle"> ';
    /* 
    if ($status->teamsubmissionenabled) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionteam', 'assign'));
        $group = $status->submissiongroup;
        if ($group) {
            $cell2 = new html_table_cell(format_string($group->name, false, $status->context));
        } else {
            $cell2 = new html_table_cell(get_string('defaultteam', 'assign'));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }
    
    $row = new html_table_row();
    $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
    if (!$status->teamsubmissionenabled) {
        if ($status->submission) {
            $cell2 = new html_table_cell(get_string('submissionstatus_' . $status->submission->status, 'assign'));
            $cell2->attributes = array('class'=>'submissionstatus' . $status->submission->status);
        } else {
            if (!$status->submissionsenabled) {
                $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
            } else {
                $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            }
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    } else {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
        if ($status->teamsubmission) {
            $submissionsummary = get_string('submissionstatus_' . $status->teamsubmission->status, 'assign');
            $groupid = 0;
            if ($status->submissiongroup) {
                $groupid = $status->submissiongroup->id;
            }

            $members = $status->submissiongroupmemberswhoneedtosubmit;
            $userslist = array();
            foreach ($members as $member) {
                $url = new moodle_url('/user/view.php', array('id' => $member->id, 'course'=>$status->courseid));
                if ($status->view == assign_submission_status::GRADER_VIEW && $status->blindmarking) {
                    $userslist[] = $member->alias;
                } else {
                    $userslist[] = $OUTPUT->action_link($url, fullname($member, $status->canviewfullnames));
                }
            }
            if (count($userslist) > 0) {
                $userstr = join(', ', $userslist);
                $submissionsummary .= $OUTPUT->container(get_string('userswhoneedtosubmit', 'assign', $userstr));
            }

            $cell2 = new html_table_cell($submissionsummary);
            $cell2->attributes = array('class'=>'submissionstatus' . $status->teamsubmission->status);
        } else {
            $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            if (!$status->submissionsenabled) {
                $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'assign'));
            } else {
                $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
            }
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // status
    if ($status->locked) {
        $row = new html_table_row();
        $cell1 = new html_table_cell();
        $cell2 = new html_table_cell(get_string('submissionslocked', 'assign'));
        $cell2->attributes = array('class'=>'submissionlocked');
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // grading status
    $row = new html_table_row();
    $cell1 = new html_table_cell(get_string('gradingstatus', 'assign'));

    if ($status->graded) {
        $cell2 = new html_table_cell(get_string('graded', 'assign'));
        $cell2->attributes = array('class'=>'submissiongraded');
    } else {
        $cell2 = new html_table_cell(get_string('notgraded', 'assign'));
        $cell2->attributes = array('class'=>'submissionnotgraded');
    }
    $row->cells = array($cell1, $cell2);
    $t->data[] = $row;


    $duedate = $status->duedate;
    if ($duedate > 0) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('duedate', 'assign'));
        $cell2 = new html_table_cell(userdate($duedate));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        if ($status->view == assign_submission_status::GRADER_VIEW) {
            if ($status->cutoffdate) {
                $row = new html_table_row();
                $cell1 = new html_table_cell(get_string('cutoffdate', 'assign'));
                $cell2 = new html_table_cell(userdate($status->cutoffdate));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }

        if ($status->extensionduedate) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('extensionduedate', 'assign'));
            $cell2 = new html_table_cell(userdate($status->extensionduedate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
            $duedate = $status->extensionduedate;
        }

        // Time remaining.
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('timeremaining', 'assign'));
        if ($duedate - $time <= 0) {
            if (!$status->submission || $status->submission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                if ($status->submissionsenabled) {
                    $cell2 = new html_table_cell(get_string('overdue', 'assign', format_time($time - $duedate)));
                    $cell2->attributes = array('class'=>'overdue');
                } else {
                    $cell2 = new html_table_cell(get_string('duedatereached', 'assign'));
                }
            } else {
                if ($status->submission->timemodified > $duedate) {
                    $cell2 = new html_table_cell(get_string('submittedlate', 'assign', format_time($status->submission->timemodified - $duedate)));
                    $cell2->attributes = array('class'=>'latesubmission');
                } else {
                    $cell2 = new html_table_cell(get_string('submittedearly', 'assign', format_time($status->submission->timemodified - $duedate)));
                    $cell2->attributes = array('class'=>'earlysubmission');
                }
            }
        } else {
            $cell2 = new html_table_cell(format_time($duedate - $time));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Show graders whether this submission is editable by students.
    if ($status->view == assign_submission_status::GRADER_VIEW) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('editingstatus', 'assign'));
        if ($status->canedit) {
            $cell2 = new html_table_cell(get_string('submissioneditable', 'assign'));
            $cell2->attributes = array('class'=>'submissioneditable');
        } else {
            $cell2 = new html_table_cell(get_string('submissionnoteditable', 'assign'));
            $cell2->attributes = array('class'=>'submissionnoteditable');
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Grading criteria preview.
    if (!empty($status->gradingcontrollerpreview)) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingmethodpreview', 'assign'));
        $cell2 = new html_table_cell($status->gradingcontrollerpreview);
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }

    // Last modified.
    $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
    if ($submission) {
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('timemodified', 'assign'));
        $cell2 = new html_table_cell(userdate($submission->timemodified));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        foreach ($status->submissionplugins as $plugin) {
            $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
            if ($plugin->is_enabled() &&
                $plugin->is_visible() &&
                $plugin->has_user_summary() &&
                $pluginshowsummary) {

                $row = new html_table_row();
                $cell1 = new html_table_cell($plugin->get_name());
                $pluginsubmission = new assign_submission_plugin_submission($plugin,
                                                                            $submission,
                                                                            assign_submission_plugin_submission::SUMMARY,
                                                                            $status->coursemoduleid,
                                                                            $status->returnaction,
                                                                            $status->returnparams);
                $cell2 = new html_table_cell($assign->get_renderer()->render($pluginsubmission));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }
    }
    */
    
    
    
        $grade->gradefordisplay = $assign->display_grade($grade->grade, false);
        
        $submission = $assign->get_user_submission($user->id, false); 
    
        if ($grade) { 
            
            $cell1 = new html_table_cell($grade->gradefordisplay);
            $cell1->rowspan = 2;
          
            
            
            if ($submission->status == 'draft'){
                $cell2 = new html_table_cell($saved_icon . 'Draft');
            }else{
                $cell2 = new html_table_cell($submitted_icon . get_string('submitted', 'assign'));
            }
            
            $cell3 = new html_table_cell(userdate($submission->timemodified));
            $lastsubmission_class = '';
            if (true){
                $cell3->text = '<div style="float:left;">'.$cell3->text.'
                                </div>
                                <div style="float:right;">
                                <a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_gradebook.php?courseid='.$pageparams['courseid'].'&mid='.$pageparams['mid'].'&dir='.$pageparams['dir'].'&sort='.$pageparams['sort'].'&view='.$pageparams['view'].'&show='.$pageparams['show'].'&expand=1&userid='.$user->id.'">
                                <img width="16" height="16" border="0" alt="Assignment" src="'.$CFG->wwwroot.'/blocks/fn_marking/pix/fullscreen_maximize.gif" valign="absmiddle">
                                </a>
                                </div>';
                $cell2->attributes['class'] = $lastsubmission_class;
                $cell3->attributes['class'] = $lastsubmission_class;
            }
                        
            $t->data[] = new html_table_row(array($cell1, $cell2, $cell3));

            
            
            $cell1 = new html_table_cell(((($gradeitem->gradepass > 0) && ($grade->grade >= $gradeitem->gradepass)) ? $marked_icon : $marked_icon_incomplete) . 'Marked');
            $cell2 = new html_table_cell(userdate($grade->timemodified));
            if (true){
                $cell1->attributes['class'] = $lastsubmission_class;
                $cell2->attributes['class'] = $lastsubmission_class;
            }            
            $t->data[] = new html_table_row(array($cell1, $cell2));



        }       

    $historyout = html_writer::table($t);
    //$o .= $OUTPUT->box_end();
    /*
    // Links.
    if ($status->view == assign_submission_status::STUDENT_VIEW) {
        if ($status->canedit) {
            if (!$submission) {
                $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                                   get_string('addsubmission', 'assign'), 'get');
            } else {
                $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                                   get_string('editsubmission', 'assign'), 'get');
            }
        }

        if ($status->cansubmit) {
            $urlparams = array('id' => $status->coursemoduleid, 'action'=>'submit');
            $o .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', $urlparams),
                                               get_string('submitassignment', 'assign'), 'get');
            $o .= $OUTPUT->box_start('boxaligncenter submithelp');
            $o .= get_string('submitassignment_help', 'assign');
            $o .= $OUTPUT->box_end();
        }
    }

    $o .= $OUTPUT->container_end();
    */
    
            
    $o = '';
    if ($historyout) {
        $o .= $assign_renderer->box_start('generalbox submissionhistory_summary');
        //$o .= $assign_renderer->heading(get_string('submissionhistory', 'assign'), 3);

        $o .= $historyout;

        $o .= $assign_renderer->box_end();
    }

    return $o;    
}

