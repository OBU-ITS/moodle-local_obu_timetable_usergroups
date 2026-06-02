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


    public static function add_usergroup_users_parameters() {
        return new external_function_parameters(
            array(
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseIdNumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                            'groups' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'groupName' => new external_value(PARAM_TEXT, 'Group name'),
                                        'instanceName' => new external_value(PARAM_TEXT, 'Semester instance name'),
                                        'usernames' => new external_multiple_structure(
                                            new external_value(PARAM_TEXT, 'Username')
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    public static function add_usergroup_users_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'General processing messages or warnings')
                ),
                'results' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseIdNumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                            'groupName' => new external_value(PARAM_TEXT, 'Group name'),
                            'instanceName' => new external_value(PARAM_TEXT, 'Semester instance name'),
                            'username' => new external_value(PARAM_TEXT, 'Username'),
                            'status' => new external_value(PARAM_BOOL, 'True if user was added successfully, false otherwise'),
                            'message' => new external_value(PARAM_TEXT, 'Optional message about the result', VALUE_OPTIONAL),
                            'groupId' => new external_value(PARAM_TEXT, 'Optional group ID when successful', VALUE_OPTIONAL)
                        )
                    )
                )
            )
        );
    }

    public static function add_usergroup_users($params) {
        global $DB;

        $params = self::validate_parameters(self::add_usergroup_users_parameters(), $params);

        $results = [];
        $messages = [];

        foreach ($params['courses'] as $courseData) {
            $courseIdNumber = $courseData['courseIdNumber'];

            // Find course by ID number
            $course = $DB->get_record('course', ['idnumber' => $courseIdNumber]);
            if (!$course) {
                $messages[] = "Course with ID number '{$courseIdNumber}' not found.";
                continue;
            }

            $courseContext = context_course::instance($course->id);

            foreach ($courseData['groups'] as $groupData) {
                $groupName = $groupData['groupName'];
                $instanceName = $groupData['instanceName'];
                $usernames = $groupData['usernames'];

                $group = ($groupName == '0' || $groupName == '')
                    ? local_obu_group_manager_create_system_group($course)
                    : local_obu_group_manager_create_system_group($course, null, null, $instanceName, $groupName);

                foreach ($usernames as $username) {
                    $userResult = [
                        'courseIdNumber' => $courseIdNumber,
                        'groupName' => $groupName,
                        'instanceName' => $instanceName,
                        'username' => $username,
                        'status' => false,
                        'groupId' => $group->id
                    ];

                    // Check if user exists
                    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], 'id');
                    if (!$user) {
                        $userResult['message'] = "User '{$username}' not found.";
                        $results[] = $userResult;
                        continue;
                    }

                    if (!is_enrolled($courseContext, $user->id, '', true)) {
                        $userResult['message'] = "User '{$username}' not enrolled on course '{$course->idnumber}'.";
                        $results[] = $userResult;
                        continue;
                    }

                    // Check if user is already in the group
                    if ($DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $user->id])) {
                        $userResult['message'] = "User already in group.";
                        $results[] = $userResult;
                        continue;
                    }

                    // Add user to group
                    try {
                        groups_add_member($group->id, $user->id);
                        $userResult['status'] = true;
                    } catch (Exception $e) {
                        $userResult['message'] = "Error adding user: " . $e->getMessage();
                    }

                    $results[] = $userResult;
                }
            }
        }

        return [
            'messages' => $messages,
            'results' => $results
        ];
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


    public static function remove_usergroup_users_parameters() {
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'groupId' => new external_value(PARAM_TEXT, 'Group ID'),
                            'usernames' => new external_multiple_structure(
                                new external_value(PARAM_TEXT, 'Username')
                            )
                        )
                    )
                )
            )
        );
    }

    public static function remove_usergroup_users_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'General processing messages or warnings')
                ),
                'results' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'groupId' => new external_value(PARAM_TEXT, 'Group ID'),
                            'username' => new external_value(PARAM_TEXT, 'Username'),
                            'status' => new external_value(PARAM_BOOL, 'True if user was removed successfully, false otherwise'),
                            'message' => new external_value(PARAM_TEXT, 'Optional message about the result', VALUE_OPTIONAL)
                        )
                    )
                )
            )
        );
    }

    public static function remove_usergroup_users($params) {
        global $DB;

        $params = self::validate_parameters(self::remove_usergroup_users_parameters(), $params);

        $results = [];
        $messages = [];

        foreach ($params['groups'] as $groupData) {
            $groupId = $groupData['groupId'];
            $usernames = $groupData['usernames'];

            if (!$DB->record_exists('groups', ['id' => $groupId])) {
                $messages[] = "Group ID '{$groupId}' does not exist.";
                continue;
            }

            foreach ($usernames as $username) {
                $userResult = [
                    'groupId' => $groupId,
                    'username' => $username,
                    'status' => false
                ];

                // Check if the user exists
                if (!$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], 'id')) {
                    $userResult['message'] = "User '{$username}' does not exist.";
                    $results[] = $userResult;
                    continue;
                }

                // Check if user is in the group
                if (!$DB->record_exists('groups_members', ['groupid' => $groupId, 'userid' => $user->id])) {
                    $userResult['message'] = "User '{$username}' is not a member of group '{$groupId}'.";
                    $results[] = $userResult;
                    continue;
                }

                // Remove the user from the group
                try {
                    groups_remove_member($groupId, $user->id);
                    $userResult['status'] = true;
                } catch (Exception $e) {
                    $userResult['message'] = "Error removing user: " . $e->getMessage();
                }

                $results[] = $userResult;
            }
        }

        return [
            'messages' => $messages,
            'results' => $results
        ];
    }


    public static function sync_usergroup_users_parameters() {
        return new external_function_parameters(
            array(
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseIdNumber' => new external_value(PARAM_TEXT, 'Course ID number'),
                            'groups' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'groupName' => new external_value(PARAM_TEXT, 'Group name'),
                                        'instanceName' => new external_value(PARAM_TEXT, 'Semester instance name'),
                                        'usernames' => new external_multiple_structure(
                                            new external_value(PARAM_TEXT, 'Username')
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    public static function sync_usergroup_users_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'Success to sync usergroup users.')
            )
        );
    }

    public static function sync_usergroup_users($courses) { //TODO:: rename this to _new so it can run side by side with the existing implementations
        global $DB;

        self::validate_context(context_system::instance());

        $params = self::validate_parameters(
            self::sync_usergroup_users_parameters(),
            ['courses' => $courses]
        );

        $currentTime = time();

        foreach ($params['courses'] as $course) {

            $courseidnumber = $course['courseIdNumber'];

            $payloadjson = json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadhash = sha1($payloadjson);

            $record = (object)[
                'courseidnumber' => $courseidnumber,
                'payloadjson' => $payloadjson,
                'payloadhash' => $payloadhash,
                'is_processed' => 0,
                'timecreated' => $currentTime,
                'timemodified' => $currentTime,
            ];

            $DB->insert_record('local_obu_tt_ug_sync', $record);
        }

        return [
            'success' => true
        ];
    }

    //TODO: This function will just store info in the tables, sched task goes through and runs deltas, also need event listener for users enrolled on courses
    public static function sync_usergroup_users_old($params) { //TODO:: rename this to what it was
        global $DB;

        $params = self::validate_parameters(self::sync_usergroup_users_parameters(), $params);

        $results = [];
        $messages = [];

        // TODO : implement sync functionality
        foreach ($params['courses'] as $courseData) {
            $courseIdNumber = $courseData['courseIdNumber'];

            // Check / remove course from requests list

            // Find course by ID number
            $course = $DB->get_record('course', ['idnumber' => $courseIdNumber]);
            if (!$course) {
                $messages[] = "Course with ID number '{$courseIdNumber}' not found.";
                continue;
            }

            $courseContext = context_course::instance($course->id);

            foreach ($courseData['groups'] as $groupData) {
                $groupName = $groupData['groupName'];
                $instanceName = $groupData['instanceName'];
                $usernames = $groupData['usernames'];

                $group = ($groupName == '0' || $groupName == '')
                    ? local_obu_group_manager_create_system_group($course)
                    : local_obu_group_manager_create_system_group($course, null, null, $instanceName, $groupName);

                foreach ($usernames as $username) {
                    $userResult = [
                        'courseIdNumber' => $courseIdNumber,
                        'groupName' => $groupName,
                        'instanceName' => $instanceName,
                        'username' => $username,
                        'status' => false,
                        'groupId' => $group->id
                    ];

                    // Check if user exists
                    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], 'id');
                    if (!$user) {
                        $userResult['message'] = "User '{$username}' not found.";
                        $results[] = $userResult;
                        continue;
                    }

                    if (!is_enrolled($courseContext, $user->id, '', true)) {
                        $userResult['message'] = "User '{$username}' not enrolled on course '{$course->idnumber}'.";
                        $results[] = $userResult;
                        continue;
                    }

                    // Check if user is already in the group
                    if ($DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $user->id])) {
                        $userResult['message'] = "User already in group.";
                        $results[] = $userResult;
                        continue;
                    }

                    // Add user to group
                    try {
                        groups_add_member($group->id, $user->id);
                        $userResult['status'] = true;
                    } catch (Exception $e) {
                        $userResult['message'] = "Error adding user: " . $e->getMessage();
                    }

                    $results[] = $userResult;
                }
            }
        }

        return [
            'messages' => $messages,
            'results' => $results
        ];
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