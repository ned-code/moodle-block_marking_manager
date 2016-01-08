<?php

//$Id: block_fn_marking.php,v 1.4 2013/01/12 14:22:06 mchurch Exp $

/**
 * Simple  class for block FN_MArking
 *
 * @copyright 2011 Moodlefn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_fn_marking extends block_list {

    /**
     * Sets the block title
     *
     * @return none
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_fn_marking');
    }

    /**
     * Constrols the block title based on instance configuration
     *
     * @return bool
     */
    public function specialization() {

        if (! isset($this->config)){
            $this->config = new stdClass;
        }

        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_fn_marking');
        } else {
            $this->title = $this->config->title;
        }

        if (!isset($this->config->showunmarked)) {
            $this->config->showunmarked = 1;
        }

        if (!isset($this->config->showmarked)) {
            $this->config->showmarked = 1;
        }

        if (!isset($this->config->showunsubmitted)) {
            $this->config->showunsubmitted = 1;
        }

        if (!isset($this->config->keepseparate)) {
            $this->config->keepseparate = 1;
        }

        if (!isset($this->config->showreportslink)) {
            $this->config->showreportslink = 1;
        }

        if (!isset($this->config->showgradeslink)) {
            $this->config->showgradeslink = 1;
        }

        if (!isset($this->config->shownotloggedinuser)) {
            $this->config->shownotloggedinuser = 1;
        }

        if (!isset($this->config->showstudentnotsubmittedassignment)) {
            $this->config->showstudentnotsubmittedassignment = 1;
        }

        if (!isset($this->config->showstudentmarkslessthanfiftypercent)) {
            $this->config->showstudentmarkslessthanfiftypercent = 1;
        }

        if (!isset($this->config->percent)) {
            $this->config->percent = 50;
        }

        if (!isset($this->config->days)) {
            $this->config->days = 7;
        }
    }

    function has_config() {
        return true;
    }

    /**
     * Constrols the block title based on instance configuration
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Creates the blocks main content
     *
     * @return string
     */
    public function get_content() {

        global $PAGE;
        
        /// Need the bigger course stdClass.
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->text = '';
        $this->content->footer = '';

        if (($this->page->course->id == SITEID) || ($this->instance->pagetypepattern == 'my-index') ) {
            $PAGE->requires->jquery();
            $PAGE->requires->js('/blocks/fn_marking/js/collapse.js');
            $this->get_frontpage_content();
        } else {
            $this->get_standard_content();
        }

        return $this->content;
    }

    /**
     * Defines where the block can be added
     *
     * @return array
     */
    public function applicable_formats() {

        // Default case: the block can be used in all course types
        return array(
            'all' => false,
            'site' => true,
            'course-*' => true,
            'my' => true
        );
    }

    /**
     * Function to return the standard content, used in all versions.
     *
     */
    private function get_standard_content() {

        global $CFG, $OUTPUT, $DB;

        require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $days = $this->config->days;
        $percent = $this->config->percent;
        $context = context_course::instance($this->page->course->id);

        $isteacher = has_capability('moodle/grade:viewall', $context);

        if (!$isteacher) {
            return $this->content;
        }

        $sections = $DB->get_records('course_sections', array('course'=>$this->page->course->id), 'section ASC', 'section, sequence');

        ///Course Teacher Menu:
        if (($this->page->course->id != SITEID)) {

            if (isset($this->config->showunmarked) && $this->config->showunmarked) {

                $numunmarked = count_unmarked_activities($this->page->course, 'unmarked');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->page->course->id . '&show=unmarked' .
                         '&navlevel=top">'. $numunmarked.' ' .get_string('unmarked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/unmarked.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showmarked) && $this->config->showmarked) {

                $nummarked = count_unmarked_activities($this->page->course, 'marked');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->page->course->id . '&show=marked' .
                        '&navlevel=top">' . $nummarked . ' ' .get_string('marked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/graded.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showunsubmitted) && $this->config->showunsubmitted) {

                $numunsubmitted = count_unmarked_activities($this->page->course, 'unsubmitted');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->page->course->id . '&show=unsubmitted' .
                        '&navlevel=top">' . $numunsubmitted . ' '.get_string('unsubmitted', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/not_submitted.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showsaved) && $this->config->showsaved) {

                $numsaved= count_unmarked_activities($this->page->course, 'saved');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->page->course->id . '&show=saved' .
                        '&navlevel=top">' . $numsaved . ' '.get_string('saved', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/saved.gif"
                                                class="icon" alt="">';
            }

            $this->content->items[] = "<div style='width:156px;'><hr /></div>";
            $this->content->icons[] = '';

            if (isset($this->config->showgradeslink) && $this->config->showgradeslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/simple_gradebook.php?id=' . $this->page->course->id .
                        '">' . get_string('simplegradebook', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/grades') . "\" class=\"icon\" alt=\"\" />";
            }

            if (isset($this->config->showreportslink) && $this->config->showreportslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/user/index.php?contextid='.$context->id.'&sifirst=&silast=&roleid=5">' .
                             get_string('studentlist', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/group') . "\" class=\"icon\" alt=\"\" />";
            }

            if (($this->config->shownotloggedinuser || $this->config->showstudentnotsubmittedassignment
                    || $this->config->showstudentmarkslessthanfiftypercent)) {
                $this->content->items[] = "<div style='width:156px;'><hr /></div>";
                $this->content->icons[] = '';
            }

            $strstudents = get_string('students');

            if (isset($this->config->shownotloggedinuser) && $this->config->shownotloggedinuser) {
                $numnotloggedin = fn_count_notloggedin($this->page->course, $days);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->page->course->id.'&show=notloggedin'.
                                        '&navlevel=top&days=' .$days. '">' . $numnotloggedin . ' '.$strstudents.' </a>'.get_string('notloggedin', 'block_fn_marking').' ' . $days . ' days</span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/exclamation.png" class="icon" alt=""><br><br>';
            }

            if ($this->config->showstudentnotsubmittedassignment) {
                $now = time();
                $lastweek = $now - (60*60*24*$days);
                $numnotsubmittedany = fn_get_notsubmittedany($this->page->course, $lastweek, true, $sections, NULL);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->page->course->id.'&show=notsubmittedany'.
                                        '&navlevel=top&days=' .$days. '">' . $numnotsubmittedany . ' '.$strstudents.' </a>'.get_string('notsubmittedany', 'block_fn_marking').''.$days.' days</span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/exclamation.png" class="icon" alt=""><br><br>';
            }

            if ($this->config->showstudentmarkslessthanfiftypercent) {

                $numfailing = fn_count_failing($this->page->course,$percent);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->page->course->id.'&show=failing'.
                                        '&navlevel=top&days=' .$days. '&percent=' .$percent. '">' . $numfailing . ' '.$strstudents.'</a>'.get_string('overallfailinggrade', 'block_fn_marking').''.$percent. '% </span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/exclamation.png" class="icon" alt=""><br><br>';
            }
        }
        return $this->content;
    }

    private function get_frontpage_content() {

        global $DB, $USER, $CFG, $OUTPUT;

        require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $modnames = get_module_types_names();
        $modnamesplural = get_module_types_names(true);

        $supported_modules = array('assign', 'forum', 'quiz');

        $isadmin   = is_siteadmin($USER->id);
        $text = '';

        $showzeroungraded = isset($this->config->listcourseszeroungraded) ? $this->config->listcourseszeroungraded : 1;

        // COURSES - ADMIN
        if ($isadmin) {
            $sqlCourse = "SELECT c.*
                            FROM {course} c
                           WHERE c.id > ?
                             AND c.visible = ?";
            $total_course_number = $DB->count_records_sql('SELECT COUNT(c.id) FROM {course} c WHERE c.id > ? AND c.visible = ?', array(1, 1));

            if ($total_course_number > 6) {
                $class_for_hide = 'block_fn_marking_hide';
                $class_for_dl = '';
            } else {
                $class_for_hide = '';
                $class_for_dl = ' class="expanded"';
            }

            if ($courses = $DB->get_records_sql($sqlCourse, array(1, 1), 0, 10)) {

                $text = fn_build_ungraded_tree ($courses, $supported_modules, $class_for_hide, $showzeroungraded);

                if ($total_course_number > 10) {
                    $text .= "<div class='fn-admin-warning' >".get_string('morethan10', 'block_fn_marking')."</div>";
                }
                $expand = '<div class="fn-expand-btn"><button class="btn btn-mini btn-default" type="button" onclick="togglecollapseall();">Collapse/Expand</button></div>';
                $this->content->items[] = '<div class="fn-collapse-wrapper"><dl class="expanded">'.$expand.$text.'</dl></div>';
                $this->content->icons[] = '';
            }
        } else {

            $sql = "SELECT ctx.id,
                       ctx.instanceid AS courseid
                  FROM {context} ctx
            INNER JOIN {role_assignments} ra
                    ON ctx.id = ra.contextid
                 WHERE ctx.contextlevel = 50
                   AND ra.roleid = 3
                   AND ra.userid = ?";

            if ($teacher_courses = $DB->get_records_sql($sql, array($USER->id))) {
                $courses = array();
                foreach ($teacher_courses as $teacher_course) {
                    $course = $DB->get_record('course', array('id'=>$teacher_course->courseid));
                    $courses[] = $course;
                }
                $text = fn_build_ungraded_tree ($courses, $supported_modules);
                $expand = '<div class="fn-expand-btn"><button class="btn btn-mini btn-default" type="button" onclick="togglecollapseall();">Collapse/Expand</button></div>';
                $this->content->items[] = '<div class="fn-collapse-wrapper"><dl class="expanded">'.$expand.$text.'</dl></div>';
                $this->content->icons[] = '';
            }
        }


        return $this->content;
    }
}
