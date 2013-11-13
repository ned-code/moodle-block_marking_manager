<?php

/**
 *
 * @return the student who have submissted the assignment
 * course id
 * id of  assignmnet
 * $user to look in to
 *  how previous to look for
 */
function assignment_get_notsubmittedany($courseid, $id="0", $users = NULL, $timestart) {
    global $CFG, $DB;
    // split out users array
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND u.id IN (' . implode(',', $userids) . ')';
        $students_with_submissions = $DB->get_records_sql("SELECT DISTINCT  u.id as userid, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                                           FROM {assignment_submissions} asb
                                                JOIN {assignment} a      ON a.id = asb.assignment
                                                JOIN {user} u            ON u.id = asb.userid
                                          WHERE asb.timemodified > $timestart AND a.id = $id
                                                $userselect");
        return $students_with_submissions;
    }
}
/**
 *
 * @return the student who have submissted the assignment
 * course id
 * id of  assignmnet
 * $user to look in to
 *  how previous to look for
 */
function assign_get_notsubmittedany($courseid, $id="0", $users = NULL, $timestart) {
    global $CFG, $DB;
    // split out users array
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND asub.userid IN (' . implode(',', $userids) . ')';
        $students_with_submissions = $DB->get_records_sql("SELECT DISTINCT asub.userid
                                                                      FROM {assign_submission} asub
                                                                     WHERE asub.assignment = $id
                                                                           $userselect
                                                                       AND asub.timemodified > $timestart");

        return $students_with_submissions;
    }
}

/**
 *
 * @return the student who have posted in the assignment
 * course id
 * id of  forum
 * $user to look into
 *  how previous to look for
 */
function forum_get_notsubmittedany($courseid, $forumid="0", $users = NULL, $timestart) {
    global $CFG, $DB;
    // split out users array
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND u.id IN (' . implode(',', $userids) . ')';
        $students_with_posts = $DB->get_records_sql("SELECT DISTINCT u.id as userid,
                                                                     u.firstname,
                                                                     u.lastname,
                                                                     u.email,
                                                                     u.picture,
                                                                     u.imagealt
                                                                FROM {forum_posts} p
                                                                JOIN {forum_discussions} d
                                                                  ON d.id = p.discussion
                                                                JOIN {forum} f
                                                                  ON f.id = d.forum
                                                                JOIN {user} u
                                                                  ON u.id = p.userid
                                                               WHERE p.created > $timestart
                                                                 AND f.id = $forumid
                                                                     $userselect");

        return $students_with_posts;
    }
}

