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

// Block Info.
$settings->add( new admin_setting_configempty('block_fn_marking/blockinfo',
        get_string('blockinfo', 'block_fn_marking'),
        '<a target="_blank" href="http://ned.ca/marking-manager">http://ned.ca/marking-manager</a>'
    )
);

$showhideoptions = array(
    '1' => get_string('show', 'block_fn_marking'),
    '0' => get_string('hide', 'block_fn_marking')
);
$yesnooptions = array(
    '1' => get_string('yes', 'block_fn_marking'),
    '0' => get_string('no', 'block_fn_marking')
);

$numberofdays = array();
for ($i = 1; $i <= 100; $i++) {
    $numberofdays[$i] = $i;
}

$numberofpercent = array();
for ($i = 1; $i <= 100; $i++) {
    $numberofpercent[$i] = $i;
}


// General Settings.
$settings->add(
    new admin_setting_heading(
        'generalsettings',
        get_string('generalsettings', 'block_fn_marking'),
        ''
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
    new admin_setting_configtext(
        'block_fn_marking/minsbeforerefreshrequired',
        get_string('minsbeforerefreshrequired', 'block_fn_marking'),
        '',
        '60',
        PARAM_INT,
        10
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
        'block_fn_marking/listcourseszeroungraded',
        get_string('listcourseszeroungraded', 'block_fn_marking'),
        '',
        0,
        $yesnooptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/include_orphaned',
        get_string('include_orphaned', 'block_fn_marking'),
        '',
        0,
        $yesnooptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/allcourseswithblock',
        get_string('allcourseswithblock', 'block_fn_marking'),
        '',
        1,
        $yesnooptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/includehiddencourses',
        get_string('includehiddencourses', 'block_fn_marking'),
        '',
        0,
        $yesnooptions
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
        $showhideoptions
    )
);

// Layout and format.
$settings->add(
    new admin_setting_heading(
        'layoutandformat',
        get_string('layoutandformat', 'block_fn_marking'),
        ''
    )
);
$settings->add(
    new admin_setting_configtext(
        'block_fn_marking/blocktitlesitelevel',
        get_string('blocktitlesitelevel', 'block_fn_marking'),
        '',
        get_string('markingmanager', 'block_fn_marking'),
        PARAM_TEXT
    )
);
$settings->add(
    new admin_setting_configtext(
        'block_fn_marking/blocktitlecourselevel',
        get_string('blocktitlecourselevel', 'block_fn_marking'),
        '',
        get_string('teachertools', 'block_fn_marking'),
        PARAM_TEXT
    )
);

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
        'block_fn_marking/showcourselink',
        get_string('showcourselink', 'block_fn_marking'),
        '',
        0,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showtitles',
        get_string('titlesforlinkclusters', 'block_fn_marking'),
        '',
        0,
        $showhideoptions
    )
);

// Marking Manager.
$settings->add(
    new admin_setting_heading(
        'markingmanager',
        get_string('markingmanager', 'block_fn_marking'),
        ''
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showunmarked',
        get_string('showunmarked', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showmarked',
        get_string('showmarked', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showunsubmitted',
        get_string('showunsubmitted', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);

// Quick links.
$settings->add(
    new admin_setting_heading(
        'quicklinks',
        get_string('quicklinks', 'block_fn_marking'),
        ''
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showgradeslink',
        get_string('showgradeslink', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showgradebook',
        get_string('showgradebook', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showreportslink',
        get_string('showreportslink', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);

// Notices.
$settings->add(
    new admin_setting_heading(
        'notices',
        get_string('notices', 'block_fn_marking'),
        ''
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/shownotloggedinuser',
        get_string('config_shownotloggedinuser', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/daysnotlogged',
        get_string('setnumberofdays', 'block_fn_marking'),
        '',
        7,
        $numberofdays
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showstudentnotsubmittedassignment',
        get_string('config_showstudentnotsubmittedassignment', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/daysnotsubmited',
        get_string('setnumberofdays', 'block_fn_marking'),
        '',
        7,
        $numberofdays
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showstudentmarkslessthanfiftypercent',
        get_string('config_showstudentmarkslessthanfiftypercent', 'block_fn_marking'),
        '',
        1,
        $showhideoptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/percent',
        get_string('setpercentmarks', 'block_fn_marking'),
        '',
        50,
        $numberofpercent
    )
);

// Other setting.
$settings->add(
    new admin_setting_heading(
        'othersettings',
        get_string('othersettings', 'block_fn_marking'),
        ''
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/keepseparate',
        get_string('keepseparate', 'block_fn_marking'),
        '',
        1,
        $yesnooptions
    )
);
$settings->add(
    new admin_setting_configselect(
        'block_fn_marking/showtopmessage',
        get_string('showtopmessage', 'block_fn_marking'),
        '',
        0,
        $yesnooptions
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