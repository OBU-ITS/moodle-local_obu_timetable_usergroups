<?php

namespace local_obu_timetable_usergroups\task;

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
 * Adhoc task to restore users' usergroup enrolments
 *
 * @package    local_obu_timetable_usergroups
 * @author     Emir Kamel
 * @copyright  2026, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_timetable_usergroups/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');

class adhoc_restore_usergroups_for_enrolment extends \core\task\adhoc_task {

    public function execute() {
        global $DB;

        $customData = $this->get_custom_data();
        $userId = (int)$customData->userId;
        $courseId = (int)$customData->courseId;

        if (empty($userId) || empty($courseId)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseId], 'id,idnumber', IGNORE_MISSING);

        if (!$course || empty($course->idnumber)) {
            return;
        }

        $cachedmemberships = $DB->get_records('local_obu_ug_sync_user', [
            'courseidnumber' => $course->idnumber,
            'userid' => $userId,
        ]);

        if (empty($cachedmemberships)) {
            return;
        }

        $groupIds = [];

        foreach ($cachedmemberships as $membership) {
            $groupIds[] = (int)$membership->groupid;
        }

        $groupIds = array_unique($groupIds);

        if (empty($groupIds)) {
            return;
        }

        // Bulk fetch valid groups for this course.
        list($groupinsql, $groupparams) = $DB->get_in_or_equal($groupIds);

        $groupparams[] = $courseId;

        $groups = $DB->get_records_select(
            'groups',
            "id {$groupinsql} AND courseid = ?",
            $groupparams,
            '',
            'id,courseid'
        );

        if (empty($groups)) {
            return;
        }

        // Bulk fetch existing memberships for this user in those groups.
        $validGroupIds = array_keys($groups);

        list($memberinsql, $memberparams) = $DB->get_in_or_equal($validGroupIds);

        $memberparams[] = $userId;

        $existingmemberships = $DB->get_records_select(
            'groups_members',
            "groupid {$memberinsql} AND userid = ?",
            $memberparams,
            '',
            'id,groupid,userid'
        );

        $existingByGroupId = [];

        foreach ($existingmemberships as $membership) {
            $existingByGroupId[(int)$membership->groupid] = true;
        }

        foreach ($validGroupIds as $groupId) {
            $groupId = (int)$groupId;

            if (isset($existingByGroupId[$groupId])) {
                continue;
            }

            groups_add_member($groupId, $userId);
        }
    }
}