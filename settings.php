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

defined('MOODLE_INTERNAL') || die();

$themeconfig = theme_config::load($CFG->theme);
$layouts = array();
foreach (array_keys($themeconfig->layouts) as $layout) {
    $layouts[$layout] = $layout;
}

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/pagelayout',
        get_string('pagelayout', 'block_fn_marking'),
        '',
        'course',
        $layouts
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/refreshmodefrontpage',
        get_string('refreshmodefrontpage', 'block_fn_marking'),
        '',
        'pageload',
        array(
            'pageload' => get_string('pageload', 'block_fn_marking'),
            'cron' => get_string('cron', 'block_fn_marking')
        )
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/refreshmodecourse',
        get_string('refreshmodecourse', 'block_fn_marking'),
        '',
        'pageload',
        array(
            'pageload' => get_string('pageload', 'block_fn_marking'),
            'cron' => get_string('cron', 'block_fn_marking')
        )
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showtopmessage',
        get_string('showtopmessage', 'block_fn_marking'),
        '',
        0,
        array('0' => 'No', '1' => 'Yes')
    )
);
$settings->add(
    new admin_setting_confightmleditor(
        'block_fn_marking/topmessage',
        get_string('topmessage', 'block_fn_marking'),
        '',
        ''
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/include_orphaned',
        get_string('include_orphaned', 'block_fn_marking'),
        '',
        0,
        array('0' => 'No', '1' => 'Yes')
    )
);
$coursecaturl = new moodle_url('/blocks/fn_marking/coursecategories.php');
$settings->add( new admin_setting_configempty('block_fn_marking/teamsubmissiongroupingid',
    get_string('coursecategoriesincluded', 'block_fn_marking'),
    '<a class="btn" href="'.$coursecaturl->out().'">'.get_string('selectcategories', 'block_fn_marking').'</a>')
);