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

define('NO_OUTPUT_BUFFERING', true); // Progress bar is used here.

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/fn_marking/lib.php');

set_time_limit(0);

$id = required_param('id', PARAM_INT);
$process = optional_param('process', 0, PARAM_INT);

require_login(null, false);


$PAGE->set_url('/blocks/fn_marking/update_cache.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('course');
$PAGE->set_cacheable(false);    // Progress bar is used here.
$PAGE->requires->css('/blocks/fn_marking/css/styles.css');

$title = get_string('updatecache', 'block_fn_marking');
$heading = $SITE->fullname;

$PAGE->set_title($heading);
$PAGE->set_heading($heading);


$PAGE->navbar->add(get_string('pluginname', 'block_fn_marking'));
$PAGE->navbar->add($title);

if ($process) {
    echo $OUTPUT->header();
    require_sesskey();
    $progressbar = new progress_bar();
    $progressbar->create();
    core_php_time_limit::raise(HOURSECS);
    raise_memory_limit(MEMORY_EXTRA);
    block_fn_marking_cache_course_data($id, $progressbar);
    if ($id) {
        echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $id)), 'get');
    } else {
        echo $OUTPUT->continue_button(new moodle_url('/my'), 'get');
    }
    echo $OUTPUT->footer();
    die;
} else {
    echo $OUTPUT->header();
    echo html_writer::tag('h1', $title, array('class' => 'page-title'));
    echo $OUTPUT->confirm(
        html_writer::div(get_string('updatecachewarning', 'block_fn_marking'), 'alert alert-block alert-danger'),
        new moodle_url('/blocks/fn_marking/update_cache.php', array('id' => $id, 'process' => 1)),
        new moodle_url('/my')
    );
    echo $OUTPUT->footer();
}