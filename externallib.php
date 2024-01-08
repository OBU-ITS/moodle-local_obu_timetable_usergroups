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

class obu_timetable_usergroups_external extends external_api {

    public static function add_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_INT, 'User ID number'),
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

    public static function add_usergroup_user($course, $group, $user) {
        //TODO: Add user to given user group here
    }

    public static function remove_usergroup_user_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_INT, 'User ID number'),
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
        //TODO: Remove user from given user group here
    }

    public static function create_usergroup_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_TEXT, 'Course ID number'),
                'group' => new external_value(PARAM_TEXT, 'Group ID number'),
                'user' => new external_value(PARAM_INT, 'User ID number'),
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
        //TODO: Create user group from given information here
    }

}