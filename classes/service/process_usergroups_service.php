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
    public function processUsergroups(\progress_trace $trace, $unprocessedUsergroupsCourses) : void {
        global $DB;
        //TODO:: code here

    }
}