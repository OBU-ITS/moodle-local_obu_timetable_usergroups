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

    private const MAX_GROUPS_PER_COURSE = 50;
    private const MAX_USERS_PER_GROUP = 500;

    private const MAX_COURSE_IDNUMBER_LENGTH = 19;
    private const MAX_GROUP_NAME_LENGTH = 12;
    private const MAX_INSTANCE_NAME_LENGTH = 3;
    private const USERNAME_LENGTH = 8;

    private const INSTANCE_NAME_PATTERN = '/^S(?:[1-3]|1[23])$/';
    private const COURSE_IDNUMBER_PATTERN = '/^\d{4}\.[A-Z]{3,4}\d{4}_S(?:[1-3]|1[23])_\d$/';
    private const USERNAME_PATTERN = '/^\d{8}$/';
    private const SET_GROUP_NAME_PATTERN = '/^Set([1-9]|[1-4][0-9]|50)$/';

    private static function require_manage_access(): void {
        $context = context_system::instance();

        self::validate_context($context);

        require_capability(
            'local/obu_timetable_usergroups:manageusergroups',
            $context
        );
    }

    private static function require_sync_access(): void {
        $context = context_system::instance();

        self::validate_context($context);

        require_capability(
            'local/obu_timetable_usergroups:syncusergroups',
            $context
        );
    }

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

        self::require_manage_access();

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

        self::require_manage_access();

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

    // The following functions are used to validate calls made to this endpoint for security purposes
    private static function validate_sync_payload(array $courses): void {
        if (empty($courses)) {
            self::throw_sync_validation_error('invalidsyncpayload');
        }

        foreach ($courses as $course) {
            if (!isset($course['courseIdNumber'], $course['groups']) || !is_array($course['groups'])) {
                self::throw_sync_validation_error('invalidcoursestructure');
            }

            $courseidnumber = $course['courseIdNumber'];

            if ($courseidnumber === '') {
                self::throw_sync_validation_error('emptycourseidnumber');
            }

            if (\core_text::strlen($courseidnumber) > self::MAX_COURSE_IDNUMBER_LENGTH) {
                self::throw_sync_validation_error('courseidnumbertoolong');
            }

            if (!preg_match(self::COURSE_IDNUMBER_PATTERN, $courseidnumber)) {
                self::throw_sync_validation_error('invalidcourseidnumber');
            }

            if (count($course['groups']) > self::MAX_GROUPS_PER_COURSE) {
                self::throw_sync_validation_error('toomanygroups');
            }

            $courseinstance = self::get_instance_name_from_course_idnumber($courseidnumber);

            foreach ($course['groups'] as $group) {
                self::validate_sync_group($group, $courseidnumber, $courseinstance);
            }
        }
    }

    private static function validate_sync_group(array $group, string $courseidnumber, ?string $courseinstance): void {
        if (
            !isset($group['groupName'], $group['instanceName'], $group['usernames'])
            || !is_array($group['usernames'])
        ) {
            self::throw_sync_validation_error('invalidgroupstructure');
        }

        $groupname = $group['groupName'];
        $instancename = $group['instanceName'];

        if (\core_text::strlen($groupname) > self::MAX_GROUP_NAME_LENGTH) {
            self::throw_sync_validation_error('groupnametoolong');
        }

        if (!self::is_valid_group_name($groupname)) {
            throw new \moodle_exception(
                'invalidgroupname',
                'local_obu_timetable_usergroups',
                '',
                null,
                'Course: ' . $courseidnumber
                . '; groupName: ' . var_export($groupname, true)
                . '; hex: ' . bin2hex($groupname)
            );
        }

        if ($instancename === '') {
            self::throw_sync_validation_error('emptyinstancename');
        }

        if (\core_text::strlen($instancename) > self::MAX_INSTANCE_NAME_LENGTH) {
            self::throw_sync_validation_error('instancenametoolong');
        }

        if (!preg_match(self::INSTANCE_NAME_PATTERN, $instancename)) {
            self::throw_sync_validation_error('invalidinstancename');
        }

        if ($courseinstance !== null && $instancename !== $courseinstance) {
            self::throw_sync_validation_error('instancemismatch');
        }

        if (count($group['usernames']) > self::MAX_USERS_PER_GROUP) {
            self::throw_sync_validation_error('toomanyusersingroup');
        }

        foreach ($group['usernames'] as $username) {
            self::validate_sync_username($username);
        }
    }

    private static function validate_sync_username(string $username): void {
        if (\core_text::strlen($username) !== self::USERNAME_LENGTH) {
            self::throw_sync_validation_error('invalidusernamelength');
        }

        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            self::throw_sync_validation_error('invalidusername');
        }
    }

    private static function is_valid_group_name(string $groupname): bool {
        if ($groupname === '') {
            return false;
        }

        if ($groupname === 'Whole Cohort') {
            return true;
        }

        return preg_match(self::SET_GROUP_NAME_PATTERN, $groupname) === 1;
    }

    private static function get_instance_name_from_course_idnumber(string $courseidnumber): ?string {
        if (!preg_match('/^\d{4}\.[A-Z]{3,4}\d{4}_(S(?:[1-3]|1[23]))_\d$/', $courseidnumber, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private static function throw_sync_validation_error(string $errorcode): void {
        throw new \moodle_exception(
            $errorcode,
            'local_obu_timetable_usergroups'
        );
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

    public static function sync_usergroup_users($courses) {
        global $DB;

        $params = self::validate_parameters(
            self::sync_usergroup_users_parameters(),
            ['courses' => $courses]
        );

        self::require_sync_access();
        self::validate_sync_payload($params['courses']);

        $courses = $params['courses'];
        $currentTime = time();

        $courseidnumbers = array_column($courses, 'courseIdNumber');

        // One query retrieves all existing queue table records.
        $existingrecords = $DB->get_records_list(
            'local_obu_tt_ug_sync',
            'courseidnumber',
            $courseidnumbers
        );

        // Re-index by course ID number instead of database record ID.
        $existingbycourse = [];
        foreach ($existingrecords as $existingrecord) {
            $existingbycourse[$existingrecord->courseidnumber] = $existingrecord;
        }

        $newrecords = [];

        foreach ($params['courses'] as $course) {

            $courseidnumber = $course['courseIdNumber'];

            $payloadjson = json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadhash = sha1($payloadjson);

            if (isset($existingbycourse[$courseidnumber])) {
                $existing = $existingbycourse[$courseidnumber];

                $existing->payloadjson = $payloadjson;
                $existing->payloadhash = $payloadhash;
                $existing->is_processed = 0;
                $existing->timemodified = $currentTime;

                $DB->update_record('local_obu_tt_ug_sync', $existing);
            } else {
                $newrecords[] = (object) [
                    'courseidnumber' => $courseidnumber,
                    'payloadjson' => $payloadjson,
                    'payloadhash' => $payloadhash,
                    'is_processed' => 0,
                    'timecreated' => $currentTime,
                    'timemodified' => $currentTime,
                ];
            }
        }

        if ($newrecords) {
            $DB->insert_records('local_obu_tt_ug_sync', $newrecords);
        }

        return [
            'success' => true
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