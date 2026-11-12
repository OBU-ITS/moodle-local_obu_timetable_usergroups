<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is distributed under the GNU GPL v3 or later.

namespace local_obu_timetable_usergroups\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * CSV upload form.
 *
 * @package local_obu_timetable_usergroups
 * @copyright 2026 Oxford Brookes University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_upload_form extends \moodleform {
    /** Define the upload controls. */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'local_obu_timetable_usergroups'), null, [
            'accepted_types' => ['.csv'],
            'maxbytes' => 5 * 1024 * 1024,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('csvprocess', 'local_obu_timetable_usergroups'));
    }
}
