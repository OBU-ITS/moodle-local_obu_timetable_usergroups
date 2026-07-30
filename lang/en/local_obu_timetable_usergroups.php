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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OBU Application - Language strings
 * @package    local_obu_timetable_usergroups
 * @category   local
 * @author     Joe Souch
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

$string['privacy:metadata'] = 'The Timetable user groups Service plugin does not store any personal data.';

$string['pluginname'] = 'obu_timetable_usergroups';
$string['plugintitle'] = 'OBU Timetable user groups';
$string['header'] = 'You are using OBU Timetable user groups Plugin version {$a->version}.';
$string['livesettings'] = 'Live Import Settings';
$string['enable'] = 'Is enabled?';
$string['enabledescription'] = 'Toggle to enable the plugin or disable the plugin in Moodle.';
$string['modulelist'] = 'Module List:';
$string['modulelistsettingtext'] = 'Section to provide list of modules to include in OBU Timetable user groups plugin.';

$string['invalidsyncpayload'] = 'Invalid sync payload.';
$string['invalidcoursestructure'] = 'Invalid course structure.';
$string['invalidgroupstructure'] = 'Invalid group structure.';
$string['emptycourseidnumber'] = 'Course ID number cannot be empty.';
$string['invalidcourseidnumber'] = 'Invalid course ID number format.';
$string['courseidnumbertoolong'] = 'Course ID number is too long.';
$string['toomanygroups'] = 'Too many groups supplied for a course.';
$string['groupnametoolong'] = 'Group name is too long.';
$string['invalidgroupname'] = 'Invalid or empty group name.';
$string['emptyinstancename'] = 'Instance name cannot be empty.';
$string['instancenametoolong'] = 'Instance name is too long.';
$string['invalidinstancename'] = 'Invalid instance name.';
$string['instancemismatch'] = 'Instance name does not match the course ID number.';
$string['toomanyusersingroup'] = 'Too many users supplied for a group.';
$string['invalidusernamelength'] = 'Username has invalid length.';
$string['invalidusername'] = 'Invalid username format.';
$string['payloadencodefailed'] = 'Unable to encode sync payload.';