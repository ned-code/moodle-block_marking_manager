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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/gradelib.php');

$userid = required_param('userid', PARAM_INT);
$mod = required_param('mod', PARAM_TEXT);
$action = required_param('action', PARAM_TEXT);
$instance = required_param('instance', PARAM_INT);

$data = array();
$data['success'] = false;

confirm_sesskey();

list ($course, $cm) = get_course_and_cm_from_instance($instance, $mod);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

if ($mod == 'assign') {
    require_capability('mod/assign:grade', $context);
} elseif ($mod == 'quiz') {
    require_capability('mod/quiz:grade', $context);
} elseif ($mod == 'journal') {
    require_capability('mod/journal:manageentries', $context);
} elseif ($mod == 'forum') {
    require_capability('mod/forum:rate', $context);
} else {
    require_capability('moodle/site:config', $context);
}

$grade_item = grade_item::fetch(
    array(
        'itemtype' => 'mod',
        'iteminstance' => $instance,
        'itemmodule' => $mod
    )
);

$mod_grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
$grade_grades = $DB->get_record('grade_grades', array('id' => $mod_grade_grade->id));

if ($grade_grades->overridden > 0) {
    $time = time();
    $rec = new stdClass();
    $rec->id = $grade_grades->id;
    $rec->overridden = 0;

    $DB->update_record('grade_grades', $rec);
    $parent = $grade_item->get_parent_category();
    $parent->force_regrading();

}
$data['success'] = true;
$data['message'] = "OK";
echo json_encode($data);