<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal functions for timetable usergroup synchronisation.
 *
 * @package    local_obu_timetable_usergroups
 * @copyright  2026 Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Queue validated course usergroup payloads for processing.
 *
 * The caller is responsible for access checks and payload validation.
 *
 * @param array $courses Validated courses containing courseIdNumber and groups.
 * @return void
 */
function local_obu_timetable_usergroups_queue_usergroup_users(array $courses): void {
    global $DB;

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

    foreach ($courses as $course) {

        $courseidnumber = $course['courseIdNumber'];

        $payloadjson = json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadhash = sha1($payloadjson);

        if (isset($existingbycourse[$courseidnumber])) {
            $existing = $existingbycourse[$courseidnumber];

            if ($existing->payloadhash === $payloadhash) {
                continue;
            }

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
}
