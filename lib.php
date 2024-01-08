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

/**
 * Attendance web service - external library
 *
 * @package    local_obu_timetable_usergroups
 * @author     Joe Souch
 * @category   local
 * @copyright  2017, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

function get_timetable_usergroup_name($setName, $setId, $semesterName) : string {

    return  'Timetable (' . $semesterName . ') - ' . $setName . ' ' . $setId;
}

function get_timetable_usergroup_id($setName, $setId, $semesterInstance) : string {
    return  'TT.' . $semesterInstance . '.' . $setName . '.' . $setId;
}