<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is distributed under the GNU GPL v3 or later.

namespace local_obu_timetable_usergroups;

defined('MOODLE_INTERNAL') || die();

/**
 * Convert module group CSV files to per-course JSON.
 *
 * @package local_obu_timetable_usergroups
 * @copyright 2026 Oxford Brookes University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_processor {
    /**
     * Read a CSV with a header and relation, group, instance and members columns.
     *
     * @param string $csvfilepath Path to the uploaded file.
     * @return array JSON strings keyed by course ID number.
     */
    public static function process_csv_file(string $csvfilepath): array {
        if (!is_file($csvfilepath) || !is_readable($csvfilepath)) {
            throw new \moodle_exception('csvcannotread', 'local_obu_timetable_usergroups');
        }
        $handle = fopen($csvfilepath, 'r');
        if ($handle === false) {
            throw new \moodle_exception('csvcannotread', 'local_obu_timetable_usergroups');
        }

        $groupeddata = [];
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header === false || count($header) < 3) {
                throw new \moodle_exception('csvinvalidheader', 'local_obu_timetable_usergroups');
            }

            $rownumber = 1;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rownumber++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) < 3) {
                    throw new \moodle_exception('csvinvalidrow', 'local_obu_timetable_usergroups', '', $rownumber);
                }

                $rawcourseidnumber = trim($row[0]);
                $groupname = trim($row[1]);
                $rawmembers = trim($row[2]);

                $courseidnumber = self::format_course_id_number($rawcourseidnumber);
                $instancenumber = self::format_instance_number($rawcourseidnumber);

                $usernames = !empty($rawmembers) ? explode('|', $rawmembers) : [];

                if (!isset($groupeddata[$courseidnumber])) {
                    $groupeddata[$courseidnumber] = [
                        'courseIdNumber' => $courseidnumber,
                        'groups' => [],
                    ];
                }
                $groupeddata[$courseidnumber]['groups'][] = [
                    'groupName' => $groupname,
                    'instanceName' => $instancenumber,
                    'usernames' => $usernames,
                ];
            }
        } finally {
            fclose($handle);
        }

        $results = [];
        foreach ($groupeddata as $courseid => $data) {
            try {
                $results[$courseid] = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \moodle_exception('csvinvalidencoding', 'local_obu_timetable_usergroups');
            }
        }
        return $results;
    }

    private static function format_course_id_number(string $relation): string {
        if (preg_match('/^([A-Z0-9_]+)_(\d{4})\d{2}_[a-zA-Z0-9]+_([A-Z0-9_]+)$/', $relation, $matches)) {
            return $matches[2] . '.' . $matches[1];
        }
        return $relation;
    }

    private static function format_instance_number(string $relation): string {
        if (preg_match('/^([A-Z0-9_]+)_(\d{4})\d{2}_Whole Cohort$/', $relation, $matches)) {
            return "WC";
        }
        if (preg_match('/^([A-Z0-9_]+)_(\d{4})\d{2}_[a-zA-Z0-9]+_([A-Z0-9]+)$/', $relation, $matches)) {
            return $matches[3];
        }
        return $relation;
    }
}
