<?php

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
 * Attendance web service - external library
 *
 * @package    local_obu_timetable_usergroups
 * @author     Joe Souch
 * @category   local
 * @copyright  2017, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once($CFG->dirroot . "/group/lib.php");

const GROUP_PREFIX = 'TT';
const GROUPING_IDENTIFIER = 'Timetabling';

function local_obu_timetable_usergroups_get_group_name($set, $courseShortName, $semesterName) : string {
    return  "$courseShortName - $semesterName - $set";
}

function local_obu_timetable_usergroups_get_group_idnumber($set, $courseIdNumber, $semesterName) : string {
    $prefix = GROUP_PREFIX;
    return  "$prefix.$courseIdNumber.$semesterName.$set";
}

function local_obu_timetable_usergroups_get_group_description() : string {
    return  "To be done"; // TODO
}

function local_obu_timetable_usergroups_get_group($courseId, $courseIdNumber, $courseShortName, $set, $semesterName) : object {
    global $DB;

    $groupIdNumber = local_obu_timetable_usergroups_get_group_idnumber($set, $courseIdNumber, $semesterName);

    if (!($group = $DB->get_record('groups', array('courseid'=>$courseId, 'idnumber'=>$groupIdNumber)))) {
        $group = new stdClass();
        $group->name = local_obu_timetable_usergroups_get_group_name($set, $courseShortName, $semesterName);
        $group->description = local_obu_timetable_usergroups_get_group_description();
        $group->idnumber = $groupIdNumber;
        $group->description_editor = FORMAT_HTML;
        $group->enrolmentkey = '';
        $group->enablemessaging = '0';
        $group->courseid = $courseId;

        $group->id = groups_create_group($group);

        $grouping = local_obu_timetable_usergroups_get_grouping($courseId);

        groups_assign_grouping($grouping->id, $group->id);
    }

    return $group;
}

function local_obu_timetable_usergroups_get_grouping($courseId) : object {
    global $DB;

    if (!($grouping = $DB->get_record('groupings_groups', array('courseid'=>$courseId, 'idnumber'=>GROUPING_IDENTIFIER)))) {

        $grouping = new stdClass();
        $grouping->name = GROUPING_IDENTIFIER;
        $grouping->courseid = $courseId;
        $grouping->idnumber = GROUPING_IDENTIFIER;

        $grouping->id = groups_create_grouping($grouping);
    }

    return $grouping;
}