<?php

namespace local_obu_timetable_usergroups\handlers;

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

use local_obu_timetable_usergroups\service\process_usergroups_service;
use progress_trace;
class process_usergroups_handler {
    private process_usergroups_service $processUsergroupsService;

    private progress_trace $trace;

    public function __construct($trace) {
        $this->processUsergroupsService = process_usergroups_service::getInstance();
        $this->trace = $trace;
    }

    public function handle_process_usergroups() {
        $unprocessedUsergroupsCourses = $this->processUsergroupsService->getUnprocessedUsergroupsCourses();

        if (count($unprocessedUsergroupsCourses) == 0) {
            $this->trace->output("No courses with unprocessed usergroups found.");
        } else {
            $this->processUsergroupsService->processUsergroups($this->trace, $unprocessedUsergroupsCourses);
            $this->trace->output("Processed " . count($unprocessedUsergroupsCourses) . " courses with usergroups records.");
        }
    }
}