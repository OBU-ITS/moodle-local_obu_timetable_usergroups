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
 * OBU Timetable user groups- service functions
 * @package   obu_timetable_usergroups
 * @author    Emir Kamel
 * @copyright 2024, Oxford Brookes University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define the web service functions to install.
$functions = array(
    'local_obu_timetable_usergroups_add_usergroup_user' => array(
        'classname'   => 'obu_timetable_usergroups_external',
        'methodname'  => 'add_usergroup_user',
        'classpath'   => 'local/obu_timetable_usergroups/externallib.php',
        'description' => 'Adds a user to a user group with the given details. Returns a result code.',
        'type'        => 'write',
        'capabilities'=> ''
    ),
    'local_obu_timetable_usergroups_remove_usergroup_user' => array(
        'classname'   => 'obu_timetable_usergroups_external',
        'methodname'  => 'remove_usergroup_user',
        'classpath'   => 'local/obu_timetable_usergroups/externallib.php',
        'description' => 'Removes a user from a user group with the given details. Returns a result code.',
        'type'        => 'write',
        'capabilities'=> ''
    ),
    'local_obu_timetable_usergroups_create_usergroup' => array(
        'classname'   => 'obu_timetable_usergroups_external',
        'methodname'  => 'create_usergroup',
        'classpath'   => 'local/obu_timetable_usergroups/externallib.php',
        'description' => 'Creates a user group within Moodle with the given details. Returns a result code.',
        'type'        => 'write',
        'capabilities'=> ''
    ),
    'local_obu_timetable_usergroups_get_settings' => array(
        'classname'   => 'obu_timetable_usergroups_external',
        'methodname'  => 'get_settings',
        'classpath'   => 'local/obu_timetable_usergroups/externallib.php',
        'description' => 'Gets the settings and gives them to the API caller.',
        'type'        => 'read',
        'capabilities'=> ''
    )
);

// Define the services to install as pre-build services.
$services = array(
    'OBU Timetable user groups' => array(
        'shortname' => 'obu_timetable_usergroups',
        'functions' => array(
            'local_obu_timetable_usergroups_add_usergroup_user',
            'local_obu_timetable_usergroups_remove_usergroup_user',
            'local_obu_timetable_usergroups_create_usergroup',
            'local_obu_timetable_usergroups_get_settings'
        ),
        'restrictedusers' => 1,
        'enabled' => 1
    )
);
