<?php

//$Id: block_fn_admin.php,v 1.4 2009/08/19 20:58:16 mchurch Exp $
$string['showsaved'] = 'Show draft activities';
$string['keepseparate'] = 'Keep draft assignments separate';
$string['setnumberofdays'] = 'Set number of days';
$string['setpercentmarks'] = 'Set percent of marks';
$string['shownotloggedinuser'] = 'Show not logged in user';
$string['setblocktitle'] = 'Set block title';
$string['shownotloggedinuser'] = 'Show not logged in user';
$string['shownotloggedinuser'] = 'Show not logged in user';
$string['showstudentnotsubmittedassignment'] = 'Show no of student not submitted assignment';
$string['showstudentmarkslessthanfiftypercent'] = 'Show no of student marks less than 50 percent';
$string['blocksettings'] = 'Configuring a FN Marking block';
$string['pluginname'] = 'FN Marking Manager';
$string['plugintitle'] = 'Marking Manager';
$string['headertitle']='FN Marking Manager';
$string['blocktitle'] = 'FN-Marking Manager';
$string['cfgdisplaytitle'] = 'Display title';
$string['displaytitle'] = 'Activities Submitted';
$string['gradeslink'] = 'Grades';
$string['show'] = 'Show';
$string['sort'] = 'Sort';
$string['view'] = 'View';
$string['marked'] = ' Marked Activities';
$string['reportslink'] = 'Reports';
$string['showgradeslink'] = 'Progress Report';
$string['showmarked'] = 'Marked Activities';
$string['showreportslink'] = 'Student List';
$string['showunmarked'] = 'Requires Grading';
$string['showunsubmitted'] = 'Unsubmitted Activities';
$string['ttmarking'] = 'Marking Interface';
$string['unmarked'] = 'Requires Grading';
$string['marked'] = 'Graded';
$string['saved'] = 'Draft';
$string['unsubmitted'] = ' Not Submitted';
$string['notloggedin'] = ' have not logged in<br>for at least';
$string['title:failingwithgradelessthanxpercent'] = 'The following students have an overall grade less than ';
$string['title:notlogin'] = 'The Following Students Have Not Logged in For ';
$string['title:notsubmittedanyactivity'] = 'The Following Students Have Not Submitted Any Activities For ';
$string['title:markslessthanxpercent'] = 'The Following Students Have Not Submitted Any Activities For ';
$string['title:saved'] = 'The Following Students Have Draft Activities';
$string['notsubmittedany'] = ' have not submitted<br>any activities for ';
$string['overallfailinggrade'] = ' have an overall grade<br>less than ';
//$string['unmarked'] = '$a Unmarked Activities';


$string['gradingstudentprogress'] = 'Showing {$a->index} of {$a->count}';
$string['grade'] = '<b>Grade: </b>';

$string['config_title'] = 'Instance title';
$string['config_title_help'] = '<p>This setting allows the block title to be changed.</p>
<p>If the block header is hidden, the title will not appear.</p>';

$string['config_showunmarked'] = 'Show unmarked activities';
$string['config_showunmarked_help'] = '<p>This setting allows whether to show .</p>
<p> or hide the unmarked activities in block.</p>';

$string['config_showmarked'] = 'Show marked activities';
$string['config_showmarked_help'] = '<p>This setting allows whether to show .</p>
<p> or hide the marked activities in block.</p>';

$string['config_showsaved'] = 'Show draft activities';
$string['config_showsaved_help'] = '<p>This setting allows whether to show .</p>
<p> or hide the student draft activities in block.</p>';

$string['config_unsubmitted'] = 'Show unsubmitted activities';
$string['config_unsubmitted_help'] = '<p>This setting allows whether to show </p>
<p> or hide the not submitted activities in block.</p>';


$string['config_showgradeslink'] = 'Show grade link';
$string['config_showgradeslink_help'] = '<p>This setting allows whether to show </p>
<p> or hide the grade link in block.</p>';

$string['config_showreportlink'] = 'Show report link';
$string['config_showreportlink_help'] = '<p>This setting allows whether to show </p>
<p> or hide the report link in block.</p>';



$string['config_shownotloggedinuser'] = 'Show not logged in user';
$string['config_shownotloggedinuser_help'] = '<p>This setting allows whether to show </p>
<p> or hide the number of student not loggedin in previous week.</p>';


$string['config_showstudentnotsubmittedassignment'] = 'Show student not submitted assignment';
$string['config_showstudentnotsubmittedassignment_help'] = '<p>This setting allows whether to show </p>
<p> or hide the number of student not submitted assignment last week .</p>';



$string['config_showstudentmarkslessthanfiftypercent'] = 'Show student marks less than 50%';
$string['config_showstudentmarkslessthanfiftypercent_help'] = '<p>This setting allows whether to show </p>
<p> or hide the number of student marks less that 50%.</p>';

$string['config_days'] = 'Set the number of student not logged in x days';
$string['config_days_help'] = '<p>This setting allows to set  </p>
<p>the number of days that student have not logged in course.</p>';

$string['config_percent'] = 'Set the percent of marks';
$string['config_percent_help'] = '<p>This setting allows to set  </p>
<p>the percent of marks and after setting the percent you will see the number of student marks below x percent.</p>';
$string['fn_marking:addinstance'] = 'Add instance';
$string['fn_marking:viewblock'] = 'View block';
$string['fn_marking:viewreadonly'] = 'View readonly';
$string['simplegradebook'] = 'Progress Report';
$string['studentlist'] = 'Student List';
$string['moodlegradebook'] = 'Open Moodle Gradebook';

$string['descconfig'] = '<p>Activate this option to hide all blocks when viewing the Marking Manager interface
and provide a less cluttered look. Note that before activating this option, you will need to add this code
to <b><em>yourmoodlesite/theme/base/config.php</em>.</b></p>
<p></p>
<pre><code style="font-size:12px; color:#FF7600;">
// Hide left and right block columns when viewing the Marking Manager
\'markingmanager\' => array(
      \'file\' => \'general.php\',
      \'regions\' => array(),
      \'options\' => array(\'noblocks\'=>true),
),
</code></pre>
After you add the above code, your file should look like the image <a href="http://moodlefn.com/docs/marking_manager_no_blocks.png">shown here</a>.  ';

$string['labelnoblocks'] = 'Hide all blocks';
$string['showtopmessage'] = 'Show message above interface';
$string['topmessage'] = 'Message to show';
$string['include_orphaned'] = 'Include orphaned activities';
$string['forum'] = 'Forum';
$string['quiz'] = 'Quiz';
$string['assign'] = 'Assignment';
$string['type'] = 'Type';
$string['scale'] = 'Scale';
$string['whocanrate'] = 'Who can rate';
$string['aggregatetype'] = 'Aggregate type';
$string['student_have_posted'] = 'The following students have posted to this forum:';
$string['student'] = 'Student';
$string['posts'] = 'Posts';
$string['replies'] = 'Replies';
$string['rating'] = 'Rating';
$string['morethan10'] = 'There are more than 10 courses with ungraded work.';
$string['student'] = 'Student';
$string['close'] = 'Close';
$string['sectiontitles'] = 'Section titles';
$string['config_sectiontitles'] = 'Section titles';
$string['config_sectiontitles_help'] = '<p>blank=course default.</p>';
$string['fn_marking:myaddinstance'] = 'Add a new Marking Manager block to Dashboard';
$string['listcourseszeroungraded'] = 'List courses with zero ungraded activities';