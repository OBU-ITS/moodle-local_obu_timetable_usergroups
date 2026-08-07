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

require_once($CFG->dirroot . "/local/obu_group_manager/lib.php");

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
            try {
                $payload = json_decode($unprocessedUsergroupsCoursesRow->payloadjson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $trace->output("Invalid JSON for sync row {$unprocessedUsergroupsCoursesRow->id}: " . $e->getMessage());

                $rowsToMarkProcessed[$unprocessedUsergroupsCoursesRow->id] = [
                    'id' => $unprocessedUsergroupsCoursesRow->id,
                    'payloadhash' => $unprocessedUsergroupsCoursesRow->payloadhash,
                ];
                continue;
            }

            if (!isset($payload['groups']) || !is_array($payload['groups'])) {
                $trace->output("Invalid payload structure for sync row {$unprocessedUsergroupsCoursesRow->id}.");

                $rowsToMarkProcessed[$unprocessedUsergroupsCoursesRow->id] = [
                    'id' => $unprocessedUsergroupsCoursesRow->id,
                    'payloadhash' => $unprocessedUsergroupsCoursesRow->payloadhash,
                ];
                continue;
            }

            $courseIdNumber = $unprocessedUsergroupsCoursesRow->courseidnumber;

            if (!isset($coursesByIdNumber[$courseIdNumber])) {
                $trace->output("Course with ID number '{$courseIdNumber}' not found.");

                $rowsToMarkProcessed[$unprocessedUsergroupsCoursesRow->id] = [
                    'id' => $unprocessedUsergroupsCoursesRow->id,
                    'payloadhash' => $unprocessedUsergroupsCoursesRow->payloadhash,
                ];
                continue;
            }

            $course = $coursesByIdNumber[$courseIdNumber];

            $newCourseUserGroupEnrolments = $this->getNewCourseUsergroupEnrolments($trace, $courseIdNumber, $payload);
            $oldCourseUserGroupEnrolments = $oldCourseUserGroupEnrolmentsByCourse[$courseIdNumber] ?? [];

            $deletekeys = array_diff(array_keys($oldCourseUserGroupEnrolments), array_keys($newCourseUserGroupEnrolments));
            $createkeys = array_diff(array_keys($newCourseUserGroupEnrolments), array_keys($oldCourseUserGroupEnrolments));

            $hasfailures = false;

            $this->processDeletes($trace, $deletekeys, $oldCourseUserGroupEnrolments, $hasfailures);
            $this->processCreates($trace, $createkeys, $newCourseUserGroupEnrolments, $course, $hasfailures);

            if ($hasfailures) {
                $trace->output("Sync row {$unprocessedUsergroupsCoursesRow->id} completed with failures.");
            }

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

        $sql = "SELECT gm.id AS membershipid,
                   c.idnumber AS courseidnumber,
                   g.id AS groupid,
                   g.name AS groupname,
                   g.idnumber AS groupidnumber,
                   u.id AS userid,
                   u.username
              FROM {course} c
              JOIN {groups} g ON g.courseid = c.id
              JOIN {groups_members} gm ON gm.groupid = g.id
              JOIN {user} u ON u.id = gm.userid
             WHERE c.idnumber $insql
               AND " . $DB->sql_like('g.idnumber', '?', false);

        $params[] = SYSTEM_IDENTIFIER . '%';

        $rows = $DB->get_records_sql($sql, $params);

        $results = [];

        foreach ($rows as $row) {
            $groupDetails = $this->parseGroupDetailsFromGroupIdnumber($row->groupidnumber);

            $key = $this->buildUsergroupKey(
                $row->courseidnumber,
                $groupDetails['groupname'],
                $groupDetails['instancename'],
                $row->username
            );

            $results[$row->courseidnumber][$key] = [
                'membershipid' => $row->membershipid,
                'courseidnumber' => $row->courseidnumber,
                'groupname' => $groupDetails['groupname'],
                'instancename' => $groupDetails['instancename'],
                'username' => $row->username,
                'userid' => $row->userid,
                'groupid' => $row->groupid,
            ];
        }

        return $results;
    }

    private function parseGroupDetailsFromGroupIdnumber(string $groupidnumber): array {
        $parts = explode('.', $groupidnumber);

        // Format: obuSys.2025.ACFI4006_S1_1.S1.Set6
        // Parts:  0      1    2              3  4

        return [
            'instancename' => $parts[3] ?? null,
            'groupname' => $parts[4] ?? null,
        ];
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

        $cacheRowsToDelete = [];

        foreach ($deletekeys as $key) {
            $oldCourseUserGroupEnrolment = $oldCourseUserGroupEnrolments[$key];

            try {
                $removed = groups_remove_member(
                    (int)$oldCourseUserGroupEnrolment['groupid'],
                    (int)$oldCourseUserGroupEnrolment['userid']
                );

                if (!$removed) {
                    $hasfailures = true;
                    $trace->output("Failed removing {$key}");
                    continue;
                }

                $cacheRowsToDelete[] = [
                    'courseidnumber' => $oldCourseUserGroupEnrolment['courseidnumber'],
                    'groupname' => $oldCourseUserGroupEnrolment['groupname'],
                    'instancename' => $oldCourseUserGroupEnrolment['instancename'],
                    'username' => $oldCourseUserGroupEnrolment['username'],
                ];

            } catch (\Throwable $e) {
                $hasfailures = true;
                $trace->output("Failed removing {$key}: " . $e->getMessage());
            }
        }

        if (!empty($cacheRowsToDelete)) {
            $conditions = [];
            $params = [];

            foreach ($cacheRowsToDelete as $i => $row) {
                $conditions[] = "(courseidnumber = :course{$i}
                AND groupname = :group{$i}
                AND instancename = :instance{$i}
                AND username = :username{$i})";

                $params["course{$i}"] = $row['courseidnumber'];
                $params["group{$i}"] = $row['groupname'];
                $params["instance{$i}"] = $row['instancename'];
                $params["username{$i}"] = $row['username'];
            }

            $DB->delete_records_select(
                'local_obu_ug_sync_user',
                implode(' OR ', $conditions),
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

            if (!$DB->record_exists('local_obu_ug_sync_user', [
                'courseidnumber' => $new['courseidnumber'],
                'groupname' => $new['groupname'],
                'instancename' => $new['instancename'],
                'username' => $new['username'],
            ])) {
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
        }

        if (!empty($recordsToInsert)) {
            $DB->insert_records('local_obu_ug_sync_user', $recordsToInsert);
        }
    }

    private function markProcessedRows(\progress_trace $trace, array $rowsToMarkProcessed) : void {
        global $DB;

        if (empty($rowsToMarkProcessed)) {
            return;
        }

        $ids = array_keys($rowsToMarkProcessed);

        list($insql, $params) = $DB->get_in_or_equal($ids);

        $currentrows = $DB->get_records_select(
            'local_obu_tt_ug_sync',
            "id {$insql}",
            $params,
            '',
            'id, payloadhash'
        );

        $currenttime = time();
        $safeids = [];

        foreach ($rowsToMarkProcessed as $id => $original) {
            if (
                isset($currentrows[$id]) &&
                $currentrows[$id]->payloadhash === $original['payloadhash']
            ) {
                $safeids[] = $id;
            } else {
                $trace->output("Sync row {$id} changed during processing, leaving unprocessed.");
            }
        }

        if (empty($safeids)) {
            return;
        }

        list($safeinsql, $safeparams) = $DB->get_in_or_equal($safeids);

        $params = array_merge([$currenttime], $safeparams);

        $sql = "UPDATE {local_obu_tt_ug_sync}
               SET is_processed = 1,
                   timemodified = ?
             WHERE id {$safeinsql}";

        $DB->execute($sql, $params);
    }
}