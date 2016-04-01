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
 * @package    block_ned_marking
 * @copyright  Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Simple  class for block ned_marking
 *
 * @copyright 2011 Moodlefn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ned_marking extends block_list {

    /**
     * Sets the block title
     *
     * @return none
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_ned_marking');
    }

    /**
     * Constrols the block title based on instance configuration
     *
     * @return bool
     */
    public function specialization() {

        if (! isset($this->config)) {
            $this->config = new stdClass;
        }

        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_ned_marking');
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

    public function has_config() {
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

        // Need the bigger course stdClass.
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
            $PAGE->requires->js('/blocks/ned_marking/js/collapse.js');
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

        // Default case: the block can be used in all course types.
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

        require_once($CFG->dirroot . '/blocks/ned_marking/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $days = $this->config->days;
        $percent = $this->config->percent;
        $context = context_course::instance($this->page->course->id);

        $isteacher = has_capability('moodle/grade:viewall', $context);

        if (!$isteacher) {
            return $this->content;
        }

        $sections = $DB->get_records('course_sections', array('course' => $this->page->course->id),
            'section ASC', 'section, sequence');

        // Course Teacher Menu.
        if (($this->page->course->id != SITEID)) {

            if (isset($this->config->showunmarked) && $this->config->showunmarked) {

                $numunmarked = block_ned_marking_count_unmarked_activities($this->page->course, 'unmarked');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/ned_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=unmarked' .
                    '&navlevel=top">'. $numunmarked.' ' .get_string('unmarked', 'block_ned_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/unmarked.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showmarked) && $this->config->showmarked) {

                $nummarked = block_ned_marking_count_unmarked_activities($this->page->course, 'marked');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/ned_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=marked' .
                    '&navlevel=top">' . $nummarked . ' ' .get_string('marked', 'block_ned_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/graded.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showunsubmitted) && $this->config->showunsubmitted) {

                $numunsubmitted = block_ned_marking_count_unmarked_activities($this->page->course, 'unsubmitted');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/ned_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=unsubmitted' .
                    '&navlevel=top">' . $numunsubmitted . ' '.get_string('unsubmitted', 'block_ned_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/not_submitted.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showsaved) && $this->config->showsaved) {

                $numsaved = block_ned_marking_count_unmarked_activities($this->page->course, 'saved');
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/ned_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=saved' .
                    '&navlevel=top">' . $numsaved . ' '.get_string('saved', 'block_ned_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/saved.gif"
                                                class="icon" alt="">';
            }

            $this->content->items[] = "<div style='width:156px;'><hr /></div>";
            $this->content->icons[] = '';

            if (isset($this->config->showgradeslink) && $this->config->showgradeslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/ned_marking/simple_gradebook.php?id=' .
                    $this->page->course->id .
                    '">' . get_string('simplegradebook', 'block_ned_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/grades') . "\" class=\"icon\" alt=\"\" />";
            }

            if (isset($this->config->showreportslink) && $this->config->showreportslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/user/index.php?contextid='.$context->id.
                    '&sifirst=&silast=&roleid=5">' .
                    get_string('studentlist', 'block_ned_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->pix_url('i/group') . "\" class=\"icon\" alt=\"\" />";
            }

            if (($this->config->shownotloggedinuser || $this->config->showstudentnotsubmittedassignment
                    || $this->config->showstudentmarkslessthanfiftypercent)) {
                $this->content->items[] = "<div style='width:156px;'><hr /></div>";
                $this->content->icons[] = '';
            }

            $strstudents = get_string('students');

            if (isset($this->config->shownotloggedinuser) && $this->config->shownotloggedinuser) {
                $numnotloggedin = block_ned_marking_count_notloggedin($this->page->course, $days);
                $this->content->items[] = '<span class="fn_summaries"><a href="'.
                    $CFG->wwwroot.'/blocks/ned_marking/fn_summaries.php?id='.$this->page->course->id.'&show=notloggedin'.
                    '&navlevel=top&days=' .$days. '">' . $numnotloggedin . ' '.$strstudents.' </a>'.
                    get_string('notloggedin', 'block_ned_marking').' ' . $days . ' days</span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/exclamation.png"
                    class="icon" alt=""><br><br>';
            }

            if ($this->config->showstudentnotsubmittedassignment) {
                $now = time();
                $lastweek = $now - (60 * 60 * 24 * $days);
                $numnotsubmittedany = block_ned_marking_get_notsubmittedany($this->page->course, $lastweek, true, $sections, null);
                $this->content->items[] = '<span class="fn_summaries"><a href="'.
                    $CFG->wwwroot.'/blocks/ned_marking/fn_summaries.php?id='.$this->page->course->id.'&show=notsubmittedany'.
                    '&navlevel=top&days=' .$days. '">' . $numnotsubmittedany . ' '.$strstudents.' </a>'.
                    get_string('notsubmittedany', 'block_ned_marking').''.$days.' days</span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/ned_marking/pix/exclamation.png"
                    class="icon" alt=""><br><br>';
            }

            if ($this->config->showstudentmarkslessthanfiftypercent) {
                $numfailing = block_ned_marking_count_failing($this->page->course, $percent);
                $this->content->items[] = '<span class="fn_summaries">
                    <a href="'.$CFG->wwwroot.'/blocks/ned_marking/fn_summaries.php?id='.$this->page->course->id.'&show=failing'.
                    '&navlevel=top&days=' .$days. '&percent=' .$percent. '">' . $numfailing . ' '.$strstudents.'</a>'.
                    get_string('overallfailinggrade', 'block_ned_marking').''.$percent. '% </span>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot .
                    '/blocks/ned_marking/pix/exclamation.png" class="icon" alt=""><br><br>';
            }
        }
        return $this->content;
    }

    private function get_frontpage_content() {

        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/blocks/ned_marking/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $modnames = get_module_types_names();
        $modnamesplural = get_module_types_names(true);

        $supportedmodules = array('assign', 'forum', 'quiz');

        $isadmin   = is_siteadmin($USER->id);
        $text = '';

        $showzeroungraded = isset($this->config->listcourseszeroungraded) ? $this->config->listcourseszeroungraded : 1;

        $getcourses = function($category, &$filtercourses){
            if ($category->courses) {
                foreach ($category->courses as $course) {
                    $filtercourses[] = $course->id;
                }
            }
            if ($category->categories) {
                foreach ($category->categories as $subcat) {
                    $getcourses($subcat, $course);
                }
            }
        };
        $filtercourses = array();
        // CATEGORY.
        if ($configcategory = get_config('block_ned_marking', 'category')) {

            $selectedcategories = explode(',', $configcategory);

            foreach ($selectedcategories as $categoryid) {

                if ($parentcatcourses = $DB->get_records('course', array('category' => $categoryid))) {
                    foreach ($parentcatcourses as $catcourse) {
                        $filtercourses[] = $catcourse->id;
                    }
                }
                if ($categorystructure = block_ned_marking_get_course_category_tree($categoryid)) {
                    foreach ($categorystructure as $category) {

                        if ($category->courses) {
                            foreach ($category->courses as $subcatcourse) {
                                $filtercourses[] = $subcatcourse->id;
                            }
                        }
                        if ($category->categories) {
                            foreach ($category->categories as $subcategory) {
                                $getcourses($subcategory, $filtercourses);
                            }
                        }
                    }
                }
            }
        }

        // COURSE.
        if ($configcourse = get_config('block_ned_marking', 'course')) {
            $selectedcourses = explode(',', $configcourse);
            $filtercourses = array_merge($filtercourses, $selectedcourses);
        }

        if ($filtercourses) {
            $filter = ' AND c.id IN ('.implode(',' , $filtercourses).')';
        } else {
            $filter = '';
        }

        // Courses - admin.
        if ($isadmin) {

            $sqlcourse = "SELECT c.*
                            FROM {course} c
                           WHERE c.id > ?
                             AND c.visible = ?
                             $filter";
            $totalcoursenumber = $DB->count_records_sql('SELECT COUNT(c.id)
                                                           FROM {course} c
                                                          WHERE c.id > ?
                                                            AND c.visible = ? '.$filter,
                array(1, 1));

            if ($totalcoursenumber > 6) {
                $classforhide = 'block_ned_marking_hide';
                $classfordl = '';
            } else {
                $classforhide = '';
                $classfordl = ' class="expanded"';
            }

            if ($courses = $DB->get_records_sql($sqlcourse, array(1, 1), 0, 10)) {

                $text = block_ned_marking_build_ungraded_tree ($courses, $supportedmodules, $classforhide, $showzeroungraded);

                if ($totalcoursenumber > 10) {
                    $text .= "<div class='fn-admin-warning' >".get_string('morethan10', 'block_ned_marking')."</div>";
                }
                $expand = '<div class="fn-expand-btn"><button class="btn btn-mini btn-default" type="button"
                    onclick="togglecollapseall();">Collapse/Expand</button></div>';
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

            if ($teachercourses = $DB->get_records_sql($sql, array($USER->id))) {
                $courses = array();
                foreach ($teachercourses as $teachercourse) {
                    if ($filtercourses) {
                        if (in_array($teachercourse->courseid, $filtercourses)) {
                            $course = $DB->get_record('course', array('id' => $teachercourse->courseid));
                            $courses[] = $course;
                        }
                    } else {
                        $course = $DB->get_record('course', array('id' => $teachercourse->courseid));
                        $courses[] = $course;
                    }
                }
                $text = block_ned_marking_build_ungraded_tree ($courses, $supportedmodules);
                $expand = '<div class="fn-expand-btn"><button class="btn btn-mini btn-default" type="button"
                    onclick="togglecollapseall();">Collapse/Expand</button></div>';
                $this->content->items[] = '<div class="fn-collapse-wrapper"><dl class="expanded">'.$expand.$text.'</dl></div>';
                $this->content->icons[] = '';
            }
        }

        return $this->content;
    }
}
