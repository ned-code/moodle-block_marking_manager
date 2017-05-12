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

/**
 * Simple  class for block fn_marking
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

        if (! isset($this->config)) {
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

        global $CFG, $USER, $OUTPUT, $DB;

        require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');
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

            // CACHE.
            $cachedatalast = get_config('block_fn_marking', 'cachedatalast');
            $refreshmodecourse = get_config('block_fn_marking', 'refreshmodecourse');
            $isingroup = block_fn_marking_isinagroup($this->page->course->id, $USER->id);

            $supportedmodules = array_keys(block_fn_marking_supported_mods());
            list($insql, $params) = $DB->get_in_or_equal($supportedmodules);
            $params = array_merge(array($this->page->course->id), $params);
            if ($isingroup) {
                $params[] = $USER->id;
            } else {
                $params[] = 0;
            }

            if (isset($this->config->showunmarked) && $this->config->showunmarked) {
                if ($refreshmodecourse == 'pageload') {
                    $numunmarked = block_fn_marking_count_unmarked_activities($this->page->course, 'unmarked', '', $USER->id);
                } else if ($refreshmodecourse == 'cron') {
                    $sql = "SELECT SUM(m.unmarked) unmarked
                              FROM {block_fn_marking_mod_cache} m
                             WHERE m.courseid = ?
                               AND m.modname {$insql}
                               AND m.userid = ?
                               AND m.expired = 0";
                    $modcache = $DB->get_record_sql($sql, $params);
                    $numunmarked = $modcache->unmarked;
                }
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=unmarked' .
                    '&navlevel=top">'. $numunmarked.' ' .get_string('unmarked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/unmarked.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showmarked) && $this->config->showmarked) {
                if ($refreshmodecourse == 'pageload') {
                    $nummarked = block_fn_marking_count_unmarked_activities($this->page->course, 'marked', '', $USER->id);
                } else if ($refreshmodecourse == 'cron') {
                    $sql = "SELECT SUM(m.marked) marked
                              FROM {block_fn_marking_mod_cache} m
                             WHERE m.courseid = ?
                               AND m.modname {$insql}
                               AND m.userid = ?
                               AND m.expired = 0";
                    $modcache = $DB->get_record_sql($sql, $params);
                    $nummarked = $modcache->marked;
                }
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=marked' .
                    '&navlevel=top">' . $nummarked . ' ' .get_string('marked', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/graded.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showunsubmitted) && $this->config->showunsubmitted) {
                if ($refreshmodecourse == 'pageload') {
                    $numunsubmitted = block_fn_marking_count_unmarked_activities($this->page->course, 'unsubmitted', '', $USER->id);
                } else if ($refreshmodecourse == 'cron') {
                    $sql = "SELECT SUM(m.unsubmitted) unsubmitted
                              FROM {block_fn_marking_mod_cache} m
                             WHERE m.courseid = ?
                               AND m.modname {$insql}
                               AND m.userid = ?
                               AND m.expired = 0";
                    $modcache = $DB->get_record_sql($sql, $params);
                    $numunsubmitted = $modcache->unsubmitted;
                }
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=unsubmitted' .
                    '&navlevel=top">' . $numunsubmitted . ' '.get_string('unsubmitted', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/not_submitted.gif"
                                                class="icon" alt="">';
            }

            if (isset($this->config->showsaved) && $this->config->showsaved) {
                if ($refreshmodecourse == 'pageload') {
                    $numsaved = block_fn_marking_count_unmarked_activities($this->page->course, 'saved', '', $USER->id);
                } else if ($refreshmodecourse == 'cron') {
                    $sql = "SELECT SUM(m.saved) saved
                              FROM {block_fn_marking_mod_cache} m
                             WHERE m.courseid = ?
                               AND m.modname {$insql}
                               AND m.userid = ?
                               AND m.expired = 0";
                    $modcache = $DB->get_record_sql($sql, $params);
                    $numsaved = $modcache->saved;
                }
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_gradebook.php?courseid=' .
                    $this->page->course->id . '&show=saved' .
                    '&navlevel=top">' . $numsaved . ' '.get_string('saved', 'block_fn_marking').'</a>';
                $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/saved.gif"
                                                class="icon" alt="">';
            }

            $this->content->items[] = "<div style='width:156px;'><hr /></div>";
            $this->content->icons[] = '';

            if (isset($this->config->showgradeslink) && $this->config->showgradeslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/blocks/fn_marking/progress_report.php?id=' .
                    $this->page->course->id .
                    '">' . get_string('simplegradebook', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->image_url('i/grades') . "\" class=\"icon\" alt=\"\" />";
            }

            if (isset($this->config->showreportslink) && $this->config->showreportslink) {
                $this->content->items[] = '<a href="' . $CFG->wwwroot . '/user/index.php?contextid='.$context->id.
                    '&sifirst=&silast=&roleid=5">' .
                    get_string('studentlist', 'block_fn_marking') . '</a>';
                $this->content->icons[] = "<img src=\"" . $OUTPUT->image_url('i/group') . "\" class=\"icon\" alt=\"\" />";
            }

            if (($this->config->shownotloggedinuser || $this->config->showstudentnotsubmittedassignment
                    || $this->config->showstudentmarkslessthanfiftypercent)) {
                $this->content->items[] = "<div style='width:156px;'><hr /></div>";
                $this->content->icons[] = '';
            }

            $strstudents = get_string('students');

            if (isset($this->config->shownotloggedinuser) && $this->config->shownotloggedinuser) {
                if ($numnotloggedin = block_fn_marking_count_notloggedin($this->page->course, $days)) {
                    $this->content->items[] = '<span class="fn_summaries"><a href="' .
                        $CFG->wwwroot . '/blocks/fn_marking/fn_summaries.php?id=' . $this->page->course->id . '&show=notloggedin' .
                        '&navlevel=top&days=' . $days . '">' . $numnotloggedin . ' ' . $strstudents . ' </a>' .
                        get_string('notloggedin', 'block_fn_marking') . ' ' . $days . ' days</span>';
                    $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/exclamation.png"
                    class="icon" alt="">';
                }
            }

            if ($this->config->showstudentnotsubmittedassignment) {
                $now = time();
                $lastweek = $now - (60 * 60 * 24 * $days);
                if ($numnotsubmittedany = block_fn_marking_get_notsubmittedany($this->page->course, $lastweek, true, $sections, null)) {
                    $this->content->items[] = '<span class="fn_summaries"><a href="' .
                        $CFG->wwwroot . '/blocks/fn_marking/fn_summaries.php?id=' . $this->page->course->id . '&show=notsubmittedany' .
                        '&navlevel=top&days=' . $days . '">' . $numnotsubmittedany . ' ' . $strstudents . ' </a>' .
                        get_string('notsubmittedany', 'block_fn_marking') . '' . $days . ' days</span>';
                    $this->content->icons[] = '<img src="' . $CFG->wwwroot . '/blocks/fn_marking/pix/exclamation.png"
                    class="icon" alt="">';
                }
            }

            if ($this->config->showstudentmarkslessthanfiftypercent) {
                if ($numfailing = block_fn_marking_count_failing($this->page->course, $percent)) {
                    $this->content->items[] = '<span class="fn_summaries">
                    <a href="' . $CFG->wwwroot . '/blocks/fn_marking/fn_summaries.php?id=' . $this->page->course->id . '&show=failing' .
                        '&navlevel=top&days=' . $days . '&percent=' . $percent . '">' . $numfailing . ' ' . $strstudents . '</a>' .
                        get_string('overallfailinggrade', 'block_fn_marking') . '' . $percent . '% </span>';
                    $this->content->icons[] = '<img src="' . $CFG->wwwroot .
                        '/blocks/fn_marking/pix/exclamation.png" class="icon" alt="">';
                }
            }

            if ($refreshmodecourse == 'cron') {
                if ($cachedatalast > 0) {
                    $humantime = get_string('lastrefreshtime', 'block_fn_marking', block_fn_marking_human_timing($cachedatalast));
                } else {
                    $humantime = get_string('lastrefreshupdating', 'block_fn_marking');
                }

                $refreshicon = html_writer::img($OUTPUT->image_url('refresh_button', 'block_fn_marking'), '', null);
                $refreshbutton = html_writer::link(
                    new moodle_url('/blocks/fn_marking/update_cache.php'),
                    $refreshicon,
                    array('class' => 'fn_refresh_btn')
                );
                $refresh = html_writer::div(
                    $humantime . $refreshbutton,
                    'fn_refresh_wrapper_footer'
                );

                $this->content->items[] = "<div style='width:156px;'><hr /></div>";
                $this->content->icons[] = '';

                $this->content->footer = $refresh;
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

        $supportedmodules = array_keys(block_fn_marking_supported_mods());

        $isadmin   = is_siteadmin($USER->id);
        $text = '';

        $showzeroungraded = isset($this->config->listcourseszeroungraded) ? $this->config->listcourseszeroungraded : 0;

        $filtercourses = block_fn_marking_get_setting_courses();

        if ($filtercourses) {
            $filter = ' AND c.id IN ('.implode(',' , $filtercourses).')';
        } else {
            $filter = '';
        }

        // CACHE.
        $cachedatalast = get_config('block_fn_marking', 'cachedatalast');
        $refreshmodefrontpage = get_config('block_fn_marking', 'refreshmodefrontpage');
        $refresh = '';

        if ($refreshmodefrontpage == 'cron') {
            if ($cachedatalast > 0) {
                $humantime = get_string('lastrefreshtime', 'block_fn_marking', block_fn_marking_human_timing($cachedatalast));
            } else {
                $humantime = get_string('lastrefreshupdating', 'block_fn_marking');
            }

            $refreshicon = html_writer::img($OUTPUT->image_url('refresh_button', 'block_fn_marking'), '', null);
            $refreshbutton = html_writer::link(
                new moodle_url('/blocks/fn_marking/update_cache.php'),
                $refreshicon,
                array('class' => 'fn_refresh_btn')
            );
            $refresh = html_writer::div(
                $humantime . $refreshbutton,
                'fn_refresh_wrapper'
            );
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
                $classforhide = 'block_fn_marking_hide';
                $classfordl = '';
            } else {
                $classforhide = '';
                $classfordl = ' class="expanded"';
            }

            if ($courses = $DB->get_records_sql($sqlcourse, array(1, 1))) {

                $text = block_fn_marking_build_ungraded_tree ($courses, $supportedmodules, $classforhide, $showzeroungraded, 10);

                if ($text) {
                    $this->content->items[] = '<div class="fn-collapse-wrapper"><dl class="expanded">' . $refresh . $text . '</dl></div>';
                    $this->content->icons[] = '';
                }
            }
        } else {
            $teacherroles = get_roles_with_capability('moodle/grade:edit', CAP_ALLOW);

            list($sqlin, $params) = $DB->get_in_or_equal(array_keys($teacherroles));
            $params[] = $USER->id;

            $sql = "SELECT ctx.id,
                           ctx.instanceid courseid
                      FROM {context} ctx
                INNER JOIN {role_assignments} ra
                        ON ctx.id = ra.contextid
                     WHERE ctx.contextlevel = 50
                       AND ra.roleid {$sqlin}
                       AND ra.userid = ?";

            if ($teachercourses = $DB->get_records_sql($sql, $params)) {
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
                $text = block_fn_marking_build_ungraded_tree ($courses, $supportedmodules);

                if ($text) {
                    $this->content->items[] = '<div class="fn-collapse-wrapper"><dl class="expanded">' . $refresh . $text . '</dl></div>';
                    $this->content->icons[] = '';
                }
            }
        }

        return $this->content;
    }
}
