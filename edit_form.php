<?php

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/../../config.php');

/**
 * Simple FN_Marking block config form definition
 *
 * @package    contrib
 * @subpackage block_FN_Marking
 * @copyright  2011 MoodleFN
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Simple FN_Marking block config form class
 *
 * @copyright 2011 MoodleFN
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_fn_marking_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        // Section header title according to language file.
        $mform->addElement('header', 'configheader',
                            get_string('blocksettings', 'block_fn_marking'));

        //Config title for the block.
        $mform->addElement('text', 'config_title',
                            get_string('setblocktitle', 'block_fn_marking'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('plugintitle', 'block_fn_marking'));
        $mform->addHelpButton('config_title', 'config_title', 'block_fn_marking');

        //Section Titles
        $mform->addElement('text', 'config_sectiontitles', get_string('sectiontitles', 'block_fn_marking'));
        $mform->setType('config_sectiontitles', PARAM_TEXT);
        $mform->addHelpButton('config_sectiontitles', 'config_sectiontitles', 'block_fn_marking');

        $hideshow = array(0 => get_string('hide'), 1 => get_string('show'));
        $yesno = array(0 => get_string('no'), 1 => get_string('yes'));

        // control the visibility of the unmarked activities
        $mform->addElement('select', 'config_showunmarked',
                            get_string('showunmarked', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_showunmarked', 1);
        $mform->addHelpButton('config_showunmarked', 'config_showunmarked', 'block_fn_marking');

        // control the visibility of the marked activities
        $mform->addElement('select', 'config_showmarked',
                            get_string('showmarked', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_showunmarked', 1);
        $mform->addHelpButton('config_showmarked', 'config_showmarked', 'block_fn_marking');

        // control the visibility of the notsubmitted activities
        $mform->addElement('select', 'config_showunsubmitted',
                            get_string('showunsubmitted', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_showunmarked', 1);
        $mform->addHelpButton('config_showunsubmitted', 'config_unsubmitted', 'block_fn_marking');

        // control the visibility of the saved activities
        $mform->addElement('select', 'config_keepseparate',
                            get_string('keepseparate', 'block_fn_marking'), $yesno);
        $mform->setDefault('config_keepseparate', 1);

        // control the visibility of the grade link activities
        $mform->addElement('select', 'config_showgradeslink',
                            get_string('showgradeslink', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_showunmarked', 0);
        $mform->addHelpButton('config_showgradeslink', 'config_showgradeslink', 'block_fn_marking');

        // control the visibility of the report link activities
        $mform->addElement('select', 'config_showreportslink',
                            get_string('showreportslink', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_showreportslink', 0);
        $mform->addHelpButton('config_showreportslink', 'config_showreportlink',
                                'block_fn_marking');

        // control the visibility of the show not loggedin user
        $mform->addElement('select', 'config_shownotloggedinuser',
                            get_string('shownotloggedinuser', 'block_fn_marking'), $hideshow);
        $mform->setDefault('config_shownotloggedinuser', 1);
        $mform->addHelpButton('config_shownotloggedinuser', 'config_shownotloggedinuser',
                                'block_fn_marking');

        // control the visibility of the "show student not submitted assignment in last x days"
        $mform->addElement('select', 'config_showstudentnotsubmittedassignment',
                            get_string('showstudentnotsubmittedassignment', 'block_fn_marking')
                            , $hideshow);
        $mform->addHelpButton('config_showstudentnotsubmittedassignment',
                                'config_showstudentnotsubmittedassignment', 'block_fn_marking');

        // control the visibility of the "show student marks less than fifty percent"
        $mform->addElement('select', 'config_showstudentmarkslessthanfiftypercent',
                            get_string('showstudentmarkslessthanfiftypercent', 'block_fn_marking')
                            , $hideshow);
        $mform->addHelpButton('config_showstudentmarkslessthanfiftypercent',
                            'config_showstudentmarkslessthanfiftypercent', 'block_fn_marking');

        $numberofdays = array();
        for ($i = 1; $i <= 100; $i++) {
            $numberofdays[$i] = $i;
        }
        //set the number of days
        $mform->addElement('select', 'config_days',
                            get_string('setnumberofdays', 'block_fn_marking'), $numberofdays);
        $mform->setDefault('config_days', $numberofdays[7]);
        $mform->addHelpButton('config_days', 'config_days', 'block_fn_marking');

        $numberofpercent = array();
        for ($i = 1; $i <= 100; $i++) {
            $numberofpercent[$i] = $i;
        }

        //set the percent of marks
        $mform->addElement('select', 'config_percent',
                            get_string('setpercentmarks', 'block_fn_marking'), $numberofpercent);
        $mform->setDefault('config_percent', $numberofpercent[50]);
        $mform->addHelpButton('config_percent', 'config_percent', 'block_fn_marking');

          //set the number of days
        $mform->addElement('select', 'config_showtopmessage',
                            get_string('showtopmessage', 'block_fn_marking'), array('0'=>'No', '1'=>'Yes'));
        $mform->setDefault('config_showtopmessage', 0);

        $mform->addElement('select', 'config_listcourseszeroungraded',
                            get_string('listcourseszeroungraded', 'block_fn_marking'), array('0'=>'No', '1'=>'Yes'));
        $mform->setDefault('config_listcourseszeroungraded', 0);

        $mform->addElement('editor', 'config_topmessage', get_string('topmessage', 'block_fn_marking'));
        $mform->setType('config_topmessage', PARAM_RAW);

    }

}
