<?PHP
require_once($CFG->dirroot.'/rating/lib.php');

if (! $forum = $DB->get_record("forum", array("id"=>$iid))) {
    print_error("Forum ID was incorrect or no longer exists");
}

$context        = context_course::instance($course->id);
$isteacheredit  = has_capability('moodle/course:update', $context);


if ($cm = get_coursemodule_from_instance("forum", $forum->id, $course->id)) {
    $buttontext = "";
} else {
    $cm->id = NULL;
    $buttontext = "";
}

require_course_login($course, false, $cm);
$modcontext     = context_module::instance($cm->id);
$isteacher      = has_capability('mod/forum:rate', $modcontext);

/// find out current groups mode
$currentgroup = groups_get_activity_group($cm, true);
$groupmode = groups_get_activity_groupmode($cm);

if ($forum->type == "teacher") {
    if (!$isteacheredit  || !$isteacher) {
        print_error("You must be a course teacher to view this forum");
    }
}

if ($groupmode && ($currentgroup === false) && !$isteacheredit) {
    $OUTPUT->heading(get_string("notingroup", "forum"));
    exit;
}

/// Print settings and things in a table across the top
$forum->intro = trim($forum->intro);
/*
if ((!empty($forum->intro)) && ($show !=='saved' )) {
    echo $OUTPUT->box(format_text($forum->intro), 'box generalbox generalboxcontent boxaligncenter', 'intro');
}
echo '<br />';
*/
$student_id_list = implode(',', array_keys($students));

//Get students from forum_posts
$st_posts = $DB->get_records_sql("SELECT DISTINCT u.*, (
                                    SELECT COUNT(r.rating) AS rawgrade
                                                                 FROM mdl_user us
                                                            LEFT JOIN mdl_forum_posts fp
                                                                   ON us.id=fp.userid
                                                            LEFT JOIN mdl_rating r
                                                                   ON r.itemid=fp.id
                                                                WHERE r.contextid = 227
                                                                  AND r.component = 'mod_forum'
                                                                  AND r.ratingarea = 'post'
                                                                  AND fp.userid = u.id
                                    ) AS numofrating
                                 FROM {forum_discussions} d
                                 INNER JOIN {forum_posts} p ON p.discussion = d.id
                                 INNER JOIN {user} u ON u.id = p.userid
                                 WHERE d.forum = $forum->id AND
                                 u.id in ($student_id_list)
                                 ORDER BY numofrating ASC");


// show all the unsubmitted users
if($show == 'unsubmitted'){
    if($students){

        $image = "<A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\"> <IMG BORDER=0 VALIGN=absmiddle SRC=\"$CFG->wwwroot/mod/$cm->modname/pix/icon.gif\" " .
            "HEIGHT=16 WIDTH=16 ALT=\"$cm->modname\"></A>";

        echo '<div class="unsubmitted_header">' . $image .
            " Forum: <A HREF=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\"  TITLE=\"$cm->modname\">" . $forum->name . '</a></div>';


        echo '<p class="unsubmitted_msg">The following students have not yet posted to this forum:</p>';

        foreach ($students as $student) {
            if (!is_array($st_posts) || !array_key_exists($student->id, $st_posts)) {

                echo "\n".'<table border="0" cellspacing="0" valign="top" cellpadding="0" class="not-submitted">';
                echo "\n<tr>";
                echo "\n<td width=\"40\" valign=\"top\" class=\"marking_rightBRD\">";
                echo $OUTPUT->user_picture($student);
                echo "</td>";
                echo "<td width=\"100%\" class=\"rightName\"><strong>".fullname($student)."</strong></td>\n";
                echo "</tr></table>\n";
            }
        }
    } else{
        echo $OUTPUT->box("No Students", 'box generalbox generalboxcontent boxaligncenter', 'intro');
    }
}

// show all the marked or unmarked users
if(($show == 'unmarked') || ($show == 'marked')){
    if($st_posts){

        $forumtypes = forum_get_forum_types();

        $permission=CAP_ALLOW;
        $rolenamestring = null;

        $rolenames = get_role_names_with_caps_in_context($modcontext, array('moodle/rating:rate', 'mod/'.$cm->modname.':rate'));
        $rolenamestring = implode(', ', $rolenames);

        $rm = new rating_manager();
        $aggregationstring    = $rm->get_aggregation_method($forum->assessed);

        echo '<div class="fn_forum_header">';

        echo '<table id="forum-header-tbl">
                      <tr>
                        <td valign="middle" width="50%">
                              <table width="100%" border="0">
                                <tr>
                                  <td valign="top" nowrap style="text-align: right">'.get_string('forum', 'block_fn_marking').': </td>
                                  <td valign="top" class="forum-header-data">'.$forum->name.'</td>
                                </tr>
                                <tr>
                                  <td valign="top" nowrap style="text-align: right">'.get_string('type', 'block_fn_marking').': </td>
                                  <td valign="top" class="forum-header-data">'.$forumtypes[$forum->type].'</td>
                                </tr>
                              </table>
                        </td>
                        <td valign="middle" width="50%">
                              <table width="100%" border="0"">
                                <tr>
                                  <td valign="top" nowrap style="text-align: right">'.get_string('scale', 'block_fn_marking').': </td>
                                  <td valign="top" class="forum-header-data">/'.$forum->scale.'</td>
                                </tr>
                                <tr>
                                  <td valign="top" nowrap style="text-align: right">'.get_string('whocanrate', 'block_fn_marking').': </td>
                                  <td valign="top" class="forum-header-data">'.$rolenamestring.'</td>
                                </tr>
                                <tr>
                                  <td valign="top" nowrap style="text-align: right">'.get_string('aggregatetype', 'block_fn_marking').': </td>
                                  <td valign="top" class="forum-header-data">'. $rm->get_aggregate_types()[$forum->assessed].'</td>
                                </tr>
                              </table>
                        </td>
                      </tr>
                    </table>';
        echo'</div>';

        echo '<div style="overflow: hidden; margin: 10px 0px 0px;">';
        echo '<div style="float: left;">'.get_string('student_have_posted', 'block_fn_marking').'</div>';
        if(($show =='marked') && $st_posts){
            echo '<div style="float: right;" ><button id="showForum">Click to see all  rated discussion.</button></div>';
        } elseif(($show =='unmarked') && $st_posts){
            echo '<div style="float: right;"><button id="showForum">Open Forum</button></div>';
        }
        echo '</div>';


        echo '<table width="100%" border="0" cellspacing="0" valign="top" cellpadding="0" id="fn-forum-grading" class="generaltable">';
        echo "<tr>";
        echo "<th colspan='2' class='fn-student'>".get_string('student', 'block_fn_marking')." </th>
              <th class='fn-posts'>".get_string('posts', 'block_fn_marking')."</th>
              <th class='fn-replies'>".get_string('replies', 'block_fn_marking')."</th>
              <th class='fn-rating'>".get_string('rating', 'block_fn_marking')."</th>";
        echo "</tr>";

        foreach ($st_posts as $st_post) {

            echo "\n<tr>";
            echo "\n<td width=\"40\" valign=\"top\">";

            $sql_posts = "SELECT COUNT(fd.id) FROM {forum_discussions} fd WHERE fd.userid = ? AND fd.forum = ?";
            $numberofposts = $DB->count_records_sql($sql_posts, array($st_post->id, $forum->id));

            $sql_reply = "SELECT Count(fp.id) FROM {forum_posts} fp INNER JOIN {forum_discussions} fd ON fp.discussion = fd.id WHERE fp.userid = ? AND fd.forum = ?";
            $numberofreplies = $DB->count_records_sql($sql_reply, array($st_post->id, $forum->id));

            $sql_rating = "SELECT u.id as id, u.id AS userid, $aggregationstring(r.rating) AS rawgrade
                             FROM {user} u
                        LEFT JOIN {forum_posts} i
                               ON u.id=i.userid
                        LEFT JOIN {rating} r
                               ON r.itemid=i.id
                            WHERE r.contextid = :contextid
                              AND r.component = :component
                              AND r.ratingarea = :ratingarea
                              AND i.userid = :userid";

            $grade = $DB->get_record_sql($sql_rating, array('contextid'=>$modcontext->id, 'component'=>'mod_forum', 'ratingarea'=>'post', 'userid'=>$st_post->id));

            $rating_str = ($st_post->numofrating == 0) ? '-' : round($grade->rawgrade, 0).'/'.$forum->scale;

            $cell_class='';
            if ($rating_str == '-')
                $cell_class = ' fn-highlighted';

            echo $OUTPUT->user_picture($st_post)."</td>";

            echo '<td class="fn-student"><a href="' . $CFG->wwwroot . '/user/view.php?id=' . $st_post->id . '&amp;course=' . $forum->course . '">' . fullname($st_post). '</a></td>';
            echo '<td class="fn-posts">'.$numberofposts.'</td>';
            echo '<td class="fn-replies">'.$numberofreplies.'</td>';
            echo '<td class="fn-rating'.$cell_class.'">'.$rating_str.'</td>';
            echo "</tr>";
        }
        echo "</table>";

    } else {
        echo $OUTPUT->box("No Student with post in this group or course", 'box generalbox generalboxcontent boxaligncenter', 'intro');
    }
}

$vars = array('mid' => $mid,'spinner' => $OUTPUT->pix_url('i/loading').'');




$jsmodule1 = array(
    'name' => 'M.show_forum_panel',
    'fullpath' => '/blocks/fn_marking/module1.js',
    'requires' => array('base', 'node','event-base','event', 'panel')
);

$PAGE->requires->js_init_call('M.show_forum_panel.init', array($vars), true, $jsmodule1);
