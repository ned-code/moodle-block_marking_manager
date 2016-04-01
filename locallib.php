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
 * @package    block_ned_marking
 * @copyright  Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * @return the student who have submissted the assignment
 * course id
 * id of  assignmnet
 * $user to look in to
 *  how previous to look for
 */
function assignment_get_notsubmittedany($courseid, $id="0", $users = null, $timestart) {
    global $CFG, $DB;
    // Split out users array.
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND u.id IN (' . implode(',', $userids) . ')';
        $studentswithsubmissions = $DB->get_records_sql("SELECT DISTINCT u.id as userid,
                                                                  u.firstname,
                                                                  u.lastname,
                                                                  u.email,
                                                                  u.picture,
                                                                  u.imagealt
                                                             FROM {assignment_submissions} asb
                                                             JOIN {assignment} a
                                                               ON a.id = asb.assignment
                                                            JOIN {user} u ON u.id = asb.userid
                                          WHERE asb.timemodified > $timestart AND a.id = $id
                                                $userselect");
        return $studentswithsubmissions;
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
function assign_get_notsubmittedany($courseid, $id="0", $users = null, $timestart) {
    global $CFG, $DB;
    // Split out users array.
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND asub.userid IN (' . implode(',', $userids) . ')';
        $studentswithsubmissions = $DB->get_records_sql("SELECT DISTINCT asub.userid
                                                                      FROM {assign_submission} asub
                                                                     WHERE asub.assignment = $id
                                                                           $userselect
                                                                       AND asub.timemodified > $timestart");

        return $studentswithsubmissions;
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
function forum_get_notsubmittedany($courseid, $forumid="0", $users = null, $timestart) {
    global $CFG, $DB;
    // Split out users array.
    if ($users) {
        $userids = array_keys($users);
        $userselect = ' AND u.id IN (' . implode(',', $userids) . ')';
        $studentswithposts = $DB->get_records_sql("SELECT DISTINCT u.id as userid,
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

        return $studentswithposts;
    }
}

