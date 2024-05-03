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

class local_obu_timetable_usergroups_external extends external_api {

    public static function add_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_TEXT, 'User ID number'),
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

        // Context validation
        self::validate_context(context_system::instance());

        // Parameter validation
        $params = self::validate_parameters(
            self::add_session_parameters(), array(
                'courseIdNumber' => $courseIdNumber,
                'groupName' => $groupName,
                'instanceName' => $instanceName,
                'username' => $username,
            )
        );

        if (strlen($params['user']) == 0) {
            return array('result' => -1);
        }

        if (!($courseRecord = $DB->get_record('course', array('idnumber' => $params['courseIdNumber'])))) {
            return array('result' => -2);
        }

        if (!($userRecord = $DB->get_record('user', array('username' => $params['username'])))) {
            return array('result' => -3);
        }
        //TODO: Add user to given user group here
    }

    public static function remove_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_TEXT, 'User ID number'),
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

    public static function remove_usergroup_user($course, $group, $user) {

        //check if userid is not equal to 8 characters in length or contains a letter from the alphabet and return error code if so
        if (strlen($params['user']) != 8 || re.search('[a-zA-Z]', $params['user'])) {
            return array('result' => -1);
        }

        if (!($courseRecord = $DB->get_record('course', array('course' => $params['course'])))) {
            return array('result' => -2);
        }

        if (!($userRecord = $DB->get_record('user', array('user' => $params['user'])))) {
            return array('result' => -3);
        }

        //TODO: Remove user from given user group here
    }

    public static function create_usergroup_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_TEXT, 'User ID number'),
            )
        );
    }

    public static function create_usergroup_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'Result')
            )
        );
    }

    public static function create_usergroup($course, $group, $user) {

        //check if userid is not equal to 8 characters in length or contains a letter from the alphabet and return error code if so
        if (strlen($params['user']) != 8 || re.search('[a-zA-Z]', $params['user'])) {
            return array('result' => -1);
        }

        if (!($courseRecord = $DB->get_record('course', array('course' => $params['course'])))) {
            return array('result' => -2);
        }

        if (!($userRecord = $DB->get_record('user', array('user' => $params['user'])))) {
            return array('result' => -3);
        }

        //TODO: Create user group from given information here
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
        $enabled = get_config('local_attendance_ws', 'enable');
        $modulelist = get_config('local_attendance_ws', 'module_list');
        $modulesarray = array_filter(explode(",", str_replace(" ", "", $modulelist)));

        return array('enabled' => $enabled, 'modulelist' => $modulesarray);
    }
}