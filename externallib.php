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

/*
 * Attendance web service - external library
 *
 * @package    obu_timetable_usergroups
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/local/obu_group_manager/lib.php");
require_once($CFG->dirroot . '/group/lib.php');

class local_obu_timetable_usergroups_external extends external_api {

    public static function add_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'courseIdNumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                'groupName' => new external_value(PARAM_TEXT, 'Group ID number'),
                'instanceName' => new external_value(PARAM_TEXT, 'Semester instance name'),
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    public static function add_usergroup_user_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'Result')
            )
        );
    }

    public static function add_usergroup_user($courseIdNumber, $groupName, $instanceName, $username) {
        global $DB;

        self::validate_context(context_system::instance());
        self::validate_parameters(
            self::add_usergroup_user_parameters(), array(
                'courseIdNumber' => $courseIdNumber,
                'groupName' => $groupName,
                'instanceName' => $instanceName,
                'username' => $username,
            )
        );

        if (strlen($username) == 0) {
            return array('result' => -1);
        }

        if (!($userRecord = $DB->get_record('user', array('username' => $username)))) {
            return array('result' => -2);
        }

        if (!($courseRecord = $DB->get_record('course', array('idnumber' => $courseIdNumber)))) {
            return array('result' => -3);
        }

        $context = context_course::instance($courseRecord->id);
        if(!is_enrolled($context, $userRecord->id, '', true)) {
            return array('result' => -4);
        }

        $group = ($groupName == '0' || $groupName == '')
            ? local_obu_group_manager_create_system_group($courseRecord)
            : local_obu_group_manager_create_system_group($courseRecord, null, null, $instanceName, $groupName);

        if(groups_add_member($group, $userRecord))
        {
            return array('result' => $group->id);
        }

        return array('result' => -9);
    }


    public static function remove_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'groupId' => new external_value(PARAM_TEXT, 'Group ID'),
                'username' => new external_value(PARAM_TEXT, 'Username'),
            )
        );
    }

    public static function remove_usergroup_user_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'Result')
            )
        );
    }

    public static function remove_usergroup_user($groupid, $username) {
        global $DB;

        self::validate_context(context_system::instance());
        self::validate_parameters(
            self::remove_usergroup_user_parameters(), array(
                'groupId' => $groupid,
                'username' => $username,
            )
        );

        if ($groupid == 0) {
            return array('result' => -1);
        }

        if (strlen($username) == 0) {
            return array('result' => -2);
        }

        if (!($userRecord = $DB->get_record('user', array('username' => $username)))) {
            return array('result' => -3);
        }

        if(groups_remove_member($groupid, $userRecord->id)) {
            return array('result' => 1);
        }

        return array('result' => -9);
    }


    public static function get_settings_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }

    public static function get_settings_returns() {
        return new external_single_structure(
            array(
                'enabled' => new external_value(PARAM_BOOL, 'Enabled'),
                'modulelist' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Module List'))
            )
        );
    }

    public static function get_settings(){
        $enabled = get_config('local_obu_timetable_usergroups', 'enable');
        $modulelist = get_config('local_obu_timetable_usergroups', 'module_list');
        $modulesarray = array_filter(explode(",", str_replace(" ", "", $modulelist)));

        return array('enabled' => $enabled, 'modulelist' => $modulesarray);
    }
}