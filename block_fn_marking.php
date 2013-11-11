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

        global $course;

        if (! isset($this->config)){
            $this->config = new object;
        }

        /// Need the bigger course object.
        $this->course = $course;

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
        if (!isset($this->config->showsaved)) {
            $this->config->showsaved = 1;
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

        global $course, $CFG, $USER, $DB, $OUTPUT, $SESSION;
//        if(isset($SESSION->currentgroup)){
//            unset($SESSION->currentgroup);
//        }
        /// Need the bigger course object.
        $this->course = $course;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';
        $context = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $isteacher = has_capability('moodle/grade:viewall', $context);

        if (!$isteacher) {
            return $this->content;
        }

        $this->get_standard_content();
        return $this->content;
    }

    /**
     * Defines where the block can be added
     *
     * @return array
     */
    public function applicable_formats() {

        // Default case: the block can be used in all course types
        return array('all' => false,
            'course-*' => true);
    }

    /**
     * Function to return the standard content, used in all versions.
     *
     */
    private function get_standard_content() {

        global $course, $DB, $USER, $CFG, $THEME, $SESSION, $PAGE,$OUTPUT;

        //Check sesubmission plugin
        if ($assignChecks = $DB->get_records_sql("SELECT * FROM {$CFG->prefix}assign")){
            foreach ($assignChecks as $assignCheck) {
                if(isset($assignCheck->resubmission)){
                    $resubmission = true;
                    break;
                }else{
                    $resubmission = false;
                }
            }
        }else{
            $resubmission = false;
        }
        /// Need the bigger course object.
        $this->course = $course;
        require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $days = $this->config->days;
        $percent = $this->config->percent;
        $context = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $isteacheredit = has_capability('moodle/course:update', $context);

        // preload mods and sections
        // grab modules
        $modnames = get_module_types_names();
        $modnamesplural = get_module_types_names(true);
        $modinfo = get_fast_modinfo($this->course->id);
        $mods = $modinfo->get_cms();
        $modnamesused = $modinfo->get_used_module_names();

        //$sections = get_all_sections($this->course->id);
        $sections = get_fast_modinfo($this->course->id)->get_section_info_all();

        $mod_array = array ($mods, $modnames, $modnamesplural, $modnamesused);

        ///Course Teacher Menu:
        if (($this->course->id != SITEID)) {

            if (isset($this->config->showunmarked) && $this->config->showunmarked) {

                $numunmarked = count_unmarked_activities($this->course, 'unmarked', $resubmission);
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->course->id . '&show=unmarked' .
                         '&navlevel=top">'. $numunmarked.' ' .get_string('unmarked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/unmarked.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showmarked) && $this->config->showmarked) {

                $nummarked = count_unmarked_activities($this->course, 'marked', $resubmission);
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->course->id . '&show=marked' .
                        '&navlevel=top">' . $nummarked . ' ' .get_string('marked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/graded.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showunsubmitted) && $this->config->showunsubmitted) {

                $numunsubmitted = count_unmarked_activities($this->course, 'unsubmitted', $resubmission);
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->course->id . '&show=unsubmitted' .
                        '&navlevel=top">' . $numunsubmitted . ' '.get_string('unsubmitted', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/not_submitted.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showsaved) && $this->config->showsaved) {

                $numsaved= count_unmarked_activities($this->course, 'saved', $resubmission);
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' . $this->course->id . '&show=saved' .
                        '&navlevel=top">' . $numsaved . ' '.get_string('saved', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/saved.gif"
                                                class="icon" alt="">';
            }

            $this->content->items[] = "<div style='width:156px;'><hr /></div>";
            $this->content->icons[] = '';


            if (isset($this->config->showgradeslink) && $this->config->showgradeslink) {
                /*
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/grade/report/index.php?id=' . $this->course->id .
                        '&navlevel=top">' . get_string('gradeslink', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/grades') . "\" class=\"icon\" alt=\"\" />";
                */
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/simple_gradebook.php?id=' . $this->course->id .
                        '">' . get_string('simplegradebook', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/grades') . "\" class=\"icon\" alt=\"\" />";
            }


            if (isset($this->config->showreportslink) && $this->config->showreportslink) {

                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/course/report.php?id=' . $this->course->id .
                        '&navlevel=top">' . get_string('reportslink', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/log') . "\" class=\"icon\" alt=\"\" />";
            }




            if (($this->config->shownotloggedinuser || $this->config->showstudentnotsubmittedassignment
                    || $this->config->showstudentmarkslessthanfiftypercent)) {
                $this->content->items[] = "<div style='width:156px;'><hr /></div>";
            }

            $strstudents = get_string('students');

            if (isset($this->config->shownotloggedinuser) && $this->config->shownotloggedinuser) {

                $numnotloggedin = fn_count_notloggedin($this->course, $days);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->course->id.'&show=notloggedin'.
                                        '&navlevel=top&days=' .$days. '">' . $numnotloggedin . ' '.$strstudents.' </a>'.get_string('notloggedin', 'block_fn_marking').' ' . $days . ' days</span>';
            }

            if ($this->config->showstudentnotsubmittedassignment) {
                $now = time();
                $lastweek = $now - (60*60*24*$days);
                $numnotsubmittedany = fn_get_notsubmittedany($this->course, $lastweek, true, $sections, $mod_array, NULL);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->course->id.'&show=notsubmittedany'.
                                        '&navlevel=top&days=' .$days. '">' . $numnotsubmittedany . ' '.$strstudents.' </a>'.get_string('notsubmittedany', 'block_fn_marking').''.$days.' days</span>';
            }

            if ($this->config->showstudentmarkslessthanfiftypercent) {

                $numfailing = fn_count_failing($this->course,$percent);
                $this->content->items[]='<span class="fn_summaries"><a href="'.$CFG->wwwroot.'/blocks/fn_marking/fn_summaries.php?id='.$this->course->id.'&show=failing'.
                                        '&navlevel=top&days=' .$days. '&percent=' .$percent. '">' . $numfailing . ' '.$strstudents.'</a>'.get_string('overallfailinggrade', 'block_fn_marking').''.$percent. '% </span>';
                $this->content->icons[] = '';
            }
        }
        return $this->content;
    }
}
