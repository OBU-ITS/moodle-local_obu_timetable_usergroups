<?php
namespace local_obu_timetable_usergroups\service;

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
 * @package    local_obu_timetable_usergroups
 * @author     Emir Kamel
 * @copyright  2026, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class process_usergroups_service {
    private static ?process_usergroups_service $instance = null;
    public static function getInstance() : process_usergroups_service {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieves all unprocessed sync_usergroup_users API calls from the database.
     *
     * @return array An array of unprocessed courses with usergroups records.
     */
    public function getUnprocessedUsergroupsCourses() : array {
        global $DB;

        $sql = "SELECT *
                FROM {local_obu_tt_ug_sync} 
                WHERE is_processed = 0 
                ORDER BY id ASC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Main function for processing sync usergroup users API calls.
     */
    public function processUsergroups(\progress_trace $trace, $unprocessedUsergroupsCoursesRows) : void {

        $courseIdNumbers = $this->buildCourseIdNumbers($unprocessedUsergroupsCoursesRows);
        $oldCourseUserGroupEnrolmentsByCourse = $this->getOldCourseUsergroupEnrolments($trace, $courseIdNumbers);

        $coursesByIdNumber = $this->getCoursesByIdNumber($courseIdNumbers);

        $rowsToMarkProcessed = [];

        foreach ($unprocessedUsergroupsCoursesRows as $unprocessedUsergroupsCoursesRow) {
            $payload = json_decode($unprocessedUsergroupsCoursesRow->payloadjson, true);
            $courseIdNumber = $unprocessedUsergroupsCoursesRow->courseidnumber;
            $course = $coursesByIdNumber[$courseIdNumber];

            if (!isset($course)) {
                $trace->output("Course with ID number '{$courseIdNumber}' not found.");
                continue;
            }


            $newCourseUserGroupEnrolments = $this->getNewCourseUsergroupEnrolments($trace, $courseIdNumber, $payload);
            $oldCourseUserGroupEnrolments = $oldCourseUserGroupEnrolmentsByCourse[$courseIdNumber] ?? [];

            $deletekeys = array_diff(array_keys($oldCourseUserGroupEnrolments), array_keys($newCourseUserGroupEnrolments));
            $createkeys = array_diff(array_keys($newCourseUserGroupEnrolments), array_keys($oldCourseUserGroupEnrolments));

            //TODO:: loop through deletes and creates and action them
            $hasfailures = false;

            $this->processDeletes($trace, $deletekeys, $oldCourseUserGroupEnrolments, $hasfailures);
            $this->processCreates($trace, $createkeys, $newCourseUserGroupEnrolments, $course, $hasfailures);

            $rowsToMarkProcessed[$unprocessedUsergroupsCoursesRow->id] = [
                'id' => $unprocessedUsergroupsCoursesRow->id,
                'payloadhash' => $unprocessedUsergroupsCoursesRow->payloadhash,
            ];
        }

        $this->markProcessedRows($trace, $rowsToMarkProcessed);
    }

    private function getCoursesByIdNumber(array $courseIdNumbers) : array {
        global $DB;

        if (empty($courseIdNumbers)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseIdNumbers);

        $sql = "SELECT *
              FROM {course}
             WHERE idnumber $insql";

        $courses = $DB->get_records_sql($sql, $params);

        $coursesByIdNumber = [];

        foreach ($courses as $course) {
            $coursesByIdNumber[$course->idnumber] = $course;
        }

        return $coursesByIdNumber;
    }

    private function buildCourseIdNumbers($unprocessedUsergroupsCoursesRows) : array {
        $courseIdNumbers = [];

        foreach ($unprocessedUsergroupsCoursesRows as $row) {
            $courseIdNumbers[] = $row->courseidnumber;
        }

        return array_unique($courseIdNumbers);
    }

    private function getNewCourseUsergroupEnrolments($trace, $courseIdNumber, $payload) : array {
        $newCourseUserGroupEnrolments = [];

        foreach ($payload['groups'] as $group) {
            $groupName = $group['groupName'];
            $instanceName = $group['instanceName'];

            foreach ($group['usernames'] as $username) {
                $key = $this->buildUsergroupKey($courseIdNumber, $groupName, $instanceName, $username);

                $newCourseUserGroupEnrolments[$key] = [
                    'courseidnumber' => $courseIdNumber,
                    'groupname' => $groupName,
                    'instancename' => $instanceName,
                    'username' => $username,
                ];
            }
        }

        return $newCourseUserGroupEnrolments;
    }

    private function getOldCourseUsergroupEnrolments($trace, $courseIdNumbers) : array {
        global $DB;

        if (empty($courseIdNumbers)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseIdNumbers);

        $sql = "SELECT *
              FROM {local_obu_ug_sync_user}
             WHERE courseidnumber $insql";

        $rows = $DB->get_records_sql($sql, $params);

        $results = [];

        foreach ($rows as $row) {

            $key = $this->buildUsergroupKey(
                $row->courseidnumber,
                $row->groupname,
                $row->instancename,
                $row->username
            );

            $results[$row->courseidnumber][$key] = [
                'id' => $row->id,
                'courseidnumber' => $row->courseidnumber,
                'groupname' => $row->groupname,
                'instancename' => $row->instancename,
                'username' => $row->username,
                'userid' => $row->userid,
                'groupid' => $row->groupid,
            ];
        }

        return $results;
    }

    private function buildUsergroupKey($courseIdNumber, $groupName, $instanceName, $username) : string {
        return trim($courseIdNumber) . '|' . trim($groupName ?? '') . '|' . trim($instanceName) . '|' . trim($username);
    }

    private function buildGroupKey($courseIdNumber, $groupName, $instanceName) : string {
        return trim($courseIdNumber) . '|' .
            trim($groupName ?? '') . '|' .
            trim($instanceName);
    }

    private function buildUsernamesForCreates(array $createkeys, array $newCourseUserGroupEnrolments) : array {
        $usernames = [];

        foreach ($createkeys as $key) {
            $usernames[] = $newCourseUserGroupEnrolments[$key]['username'];
        }

        return array_unique($usernames);
    }

    private function getUsersByUsername(array $usernames) : array {
        global $DB;

        if (empty($usernames)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($usernames);

        $sql = "SELECT *
              FROM {user}
             WHERE username $insql
               AND deleted = 0";

        $users = $DB->get_records_sql($sql, $params);

        $usersByUsername = [];

        foreach ($users as $user) {
            $usersByUsername[$user->username] = $user;
        }

        return $usersByUsername;
    }

    private function processDeletes(\progress_trace $trace, $deletekeys, $oldCourseUserGroupEnrolments, &$hasfailures) : void {
        global $DB;

        $lookupidsToDelete = [];

        foreach ($deletekeys as $key) {
            $oldCourseUserGroupEnrolment = $oldCourseUserGroupEnrolments[$key];

            try {
                $removed = groups_remove_member(
                    (int)$oldCourseUserGroupEnrolment['groupid'],
                    (int)$oldCourseUserGroupEnrolment['userid']
                );

                if ($removed) {
                    $lookupidsToDelete[] = (int)$oldCourseUserGroupEnrolment['id'];
                } else {
                    $hasfailures = true;
                    $trace->output("Failed removing {$key}");
                }

            } catch (\Throwable $e) {
                $hasfailures = true;
                $trace->output("Failed removing {$key}: " . $e->getMessage());
            }
        }

        if (!empty($lookupidsToDelete)) {
            list($insql, $params) = $DB->get_in_or_equal($lookupidsToDelete);

            $DB->delete_records_select(
                'local_obu_ug_sync_user',
                "id $insql",
                $params
            );
        }
    }

    private function processCreates(\progress_trace $trace, array $createkeys, array $newCourseUserGroupEnrolments, \stdClass $course, bool &$hasfailures) : void {
        global $DB;

        if (empty($createkeys)) {
            return;
        }

        $usernames = $this->buildUsernamesForCreates($createkeys, $newCourseUserGroupEnrolments);
        $usersByUsername = $this->getUsersByUsername($usernames);

        $courseContext = \context_course::instance($course->id);
        $groupCache = [];
        $recordsToInsert = [];
        $currenttime = time();

        foreach ($createkeys as $key) {
            $new = $newCourseUserGroupEnrolments[$key];

            if (!isset($usersByUsername[$new['username']])) {
                $hasfailures = true;
                $trace->output("User '{$new['username']}' not found.");
                continue;
            }

            $user = $usersByUsername[$new['username']];

            if (!is_enrolled($courseContext, $user->id, '', true)) {
                $hasfailures = true;
                $trace->output("User '{$new['username']}' not enrolled on course '{$course->idnumber}'.");
                continue;
            }

            $groupkey = $this->buildGroupKey(
                $new['courseidnumber'],
                $new['groupname'],
                $new['instancename']
            );

            if (!isset($groupCache[$groupkey])) {
                $groupCache[$groupkey] = ($new['groupname'] === '0' || $new['groupname'] === '')
                    ? local_obu_group_manager_create_system_group($course)
                    : local_obu_group_manager_create_system_group(
                        $course,
                        null,
                        null,
                        $new['instancename'],
                        $new['groupname']
                    );
            }

            $group = $groupCache[$groupkey];

            if (!$DB->record_exists('groups_members', [
                'groupid' => $group->id,
                'userid' => $user->id,
            ])) {
                $added = groups_add_member($group->id, $user->id);

                if (!$added) {
                    $hasfailures = true;
                    $trace->output("Failed adding {$new['username']} to group {$group->id}.");
                    continue;
                }
            }

            $recordsToInsert[] = (object)[
                'courseidnumber' => $new['courseidnumber'],
                'groupname' => $new['groupname'],
                'instancename' => $new['instancename'],
                'username' => $new['username'],
                'userid' => $user->id,
                'groupid' => $group->id,
                'timecreated' => $currenttime,
                'timemodified' => $currenttime,
            ];
        }

        if (!empty($recordsToInsert)) {
            $DB->insert_records('local_obu_ug_sync_user', $recordsToInsert);
        }
    }
}