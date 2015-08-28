<?php
/*
 * This file is part of Spark LMS
 *
 * Copyright (C) 2010 onwards Spark Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mustafa Bahcaci <mbahcaci@charterresources.us>
 * @package spark
 * @subpackage spark_grade
 */

defined('MOODLE_INTERNAL') || die();

$observers = array (
    array(
        'eventname'   => '\mod_assign\event\submission_graded',
        'includefile' => '/blocks/fn_marking/lib.php',
        'callback' => '\block_fn_marking\fn_marking_observers::fn_update_submission_status',
    ),
    array(
        'eventname'   => '\assignsubmission_file\event\submission_created',
        'includefile' => '/blocks/fn_marking/lib.php',
        'callback' => '\block_fn_marking\fn_marking_observers::fn_update_submission_status',
    ),
    array(
        'eventname'   => '\assignsubmission_file\event\submission_updated',
        'includefile' => '/blocks/fn_marking/lib.php',
        'callback' => '\block_fn_marking\fn_marking_observers::fn_update_submission_status',
    )
);