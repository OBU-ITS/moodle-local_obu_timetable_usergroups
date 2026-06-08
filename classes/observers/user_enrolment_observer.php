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
 * Plugin user enrolled on course observer
 *
 * @package    local_obu_timetable_usergroups
 * @author     Emir Kamel
 * @copyright  2026, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_obu_timetable_usergroups\observers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_timetable_usergroups/locallib.php');
class user_enrolment_observer {
    public static function user_enrolled_on_course(\core\event\user_enrolment_created $event) {
        $courseId = $event->courseid;
        $userId = $event->relateduserid;

        $task = new \local_obu_timetable_usergroups\task\adhoc_restore_usergroups_for_enrolment();

        $task->set_custom_data([
            'userid' => $userId,
            'courseid' => $courseId,
        ]);

        \core\task\manager::queue_adhoc_task($task);
    }
}