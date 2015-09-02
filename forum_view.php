<?php
require_once('../../config.php');
$version = explode('.', $CFG->version);
$version = reset($version);

if ($version >= 2015051100) {
    //MOODLE 2.9
    require_once($CFG->dirroot.'/blocks/fn_marking/forum_view_29.php');
} else if($version >= 2014111000) {
    //MOODLE 2.8
    require_once($CFG->dirroot.'/blocks/fn_marking/forum_view_28.php');
} else {
    //MOODLE 2.7
    require_once($CFG->dirroot.'/blocks/fn_marking/forum_view_27.php');
}