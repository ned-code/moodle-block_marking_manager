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
            'manual' => get_string('manual', 'block_fn_marking')
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
            'manual' => get_string('manual', 'block_fn_marking')
        )
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/adminfrontpage',
        get_string('adminfrontpage', 'block_fn_marking'),
        '',
        'enrolled',
        array(
            'enrolled' => get_string('enrolledcourses', 'block_fn_marking'),
            'all' => get_string('allcourses', 'block_fn_marking')
        )
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showtopmessage',
        get_string('showtopmessage', 'block_fn_marking'),
        '',
        0,
        array('0' => get_string('no', 'block_fn_marking'), '1' => get_string('yes', 'block_fn_marking'))
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
        array('0' => get_string('no', 'block_fn_marking'), '1' => get_string('yes', 'block_fn_marking'))
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/allcourseswithblock',
        get_string('allcourseswithblock', 'block_fn_marking'),
        '',
        1,
        array('0' => get_string('no', 'block_fn_marking'), '1' => get_string('yes', 'block_fn_marking'))
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/includehiddencourses',
        get_string('includehiddencourses', 'block_fn_marking'),
        '',
        0,
        array('0' => get_string('no', 'block_fn_marking'), '1' => get_string('yes', 'block_fn_marking'))
    )
);

$coursecaturl = new moodle_url('/blocks/fn_marking/coursecategories.php');
$settings->add( new admin_setting_configempty('block_fn_marking/courseselection',
    get_string('coursecategoriesincluded', 'block_fn_marking'),
    '<a class="btn" href="'.$coursecaturl->out().'">'.get_string('selectcategories', 'block_fn_marking').'</a>')
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/editortoggle',
        get_string('editortoggle', 'block_fn_marking'),
        get_string('experimental', 'block_fn_marking'),
        0,
        array('0' => get_string('no', 'block_fn_marking'), '1' => get_string('yes', 'block_fn_marking'))
    )
);