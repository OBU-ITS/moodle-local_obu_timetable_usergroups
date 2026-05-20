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
require_once($CFG->dirroot . '/local/obu_timetable_usergroups/locallib.php');

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
        global $DB;

        $courseIdNumbers = $this->buildCourseIdNumbers($unprocessedUsergroupsCoursesRows);
        $oldCourseUserGroupEnrolmentsByCourse = $this->getOldCourseUsergroupEnrolments($trace, $courseIdNumbers);

        $coursesByIdNumber = $this->getCoursesByIdNumber($courseIdNumbers);

        foreach ($unprocessedUsergroupsCoursesRows as $unprocessedUsergroupsCoursesRow) {
            $payload = json_decode($unprocessedUsergroupsCoursesRow->payloadjson, true);
            $courseIdNumber = $unprocessedUsergroupsCoursesRow->courseidnumber;

            if (!isset($coursesByIdNumber[$courseIdNumber])) {
                $trace->output("Course with ID number '{$courseIdNumber}' not found.");
                continue;
            }

            $newCourseUserGroupEnrolments = $this->getNewCourseUsergroupEnrolments($trace, $courseIdNumber, $payload);
            $oldCourseUserGroupEnrolments = $oldCourseUserGroupEnrolmentsByCourse[$courseIdNumber] ?? [];

            $deletekeys = array_diff(array_keys($oldCourseUserGroupEnrolments), array_keys($newCourseUserGroupEnrolments));
            $createkeys = array_diff(array_keys($newCourseUserGroupEnrolments), array_keys($oldCourseUserGroupEnrolments));

            //TODO:: loop through deletes and creates and action them
        }
    }

    private function getCoursesByIdNumber(array $courseIdNumbers) : array {
        global $DB;

        if (empty($courseIdNumbers)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseIdNumbers, SQL_PARAMS_NAMED);

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
}