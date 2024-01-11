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
 * Standard lib
 *
 * @package    obu_timetable_usergroups
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add("parent_section", new admin_externalpage('obutimetableusergroups', "OBU Timetable user groups Admin Component", "$CFG->wwwroot/$CFG->admin/obutimetableusergroups.php"));
    $settings = new admin_settingpage(get_string('pluginname', 'obu_timetable_usergroups'), get_string('plugintitle', 'obu_timetable_usergroups'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configcheckbox('obu_timetable_usergroups/enable', get_string('enable', 'obu_timetable_usergroups'), get_string('enabledescription', 'obu_timetable_usergroups'), ''));
    $settings->add(new admin_setting_configtextarea('obu_timetable_usergroups/module_list', get_string('modulelist', 'obu_timetable_usergroups'), get_string('modulelistsettingtext', 'obu_timetable_usergroups'), ''));
}