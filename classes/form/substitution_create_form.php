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

namespace mod_projetvet\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use mod_projetvet\local\api\substitutions;
use moodleform;

/**
 * Form to assign a temporary substitute tutor to some students of a principal tutor.
 *
 * Custom data:
 * - cmid, projetvetid, tutorid: the activity and the principal tutor;
 * - students: the students of the principal tutor without open substitution (user records);
 * - candidates: the possible substitutes, see {@see substitutions::get_candidate_tutors()}.
 *
 * Students are submitted as students[id] => 0|1. Every selected student is checked against the
 * control rules in validation(), and the conflicts are shown next to each student.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitution_create_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('hidden', 'id', $customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tutorid', $customdata['tutorid']);
        $mform->setType('tutorid', PARAM_INT);

        $mform->addElement('header', 'createheader', get_string('substitution_create_heading', 'mod_projetvet'));
        $mform->setExpanded('createheader');

        if (empty($customdata['students'])) {
            $mform->addElement('static', 'nostudents', '', get_string('substitution_nostudents', 'mod_projetvet'));
            return;
        }

        foreach ($customdata['students'] as $student) {
            $label = fullname($student);
            $promotion = \mod_projetvet\utils::get_user_profile_field($student->id, \mod_projetvet\utils::PROMOTION_FIELD);
            if ($promotion !== '') {
                $label .= ' (' . s($promotion) . ')';
            }
            $mform->addElement('advcheckbox', 'students[' . $student->id . ']', $label, null, ['group' => 1]);
            $mform->setType('students[' . $student->id . ']', PARAM_INT);
        }
        $this->add_checkbox_controller(1, get_string('substitution_selectall', 'mod_projetvet'), null, 0);

        $options = ['' => get_string('choose')];
        foreach ($customdata['candidates'] as $candidate) {
            $options[$candidate['id']] = $candidate['label'];
        }
        $mform->addElement('select', 'substitutetutorid', get_string('substitution_substitute', 'mod_projetvet'), $options);
        $mform->addRule('substitutetutorid', null, 'required', null, 'client');
        $mform->addHelpButton('substitutetutorid', 'substitution_substitute', 'mod_projetvet');

        $mform->addElement('date_selector', 'startdate', get_string('substitution_startdate', 'mod_projetvet'));
        $mform->setDefault('startdate', usergetmidnight(time()));

        $mform->addElement(
            'date_selector',
            'enddate',
            get_string('substitution_enddate', 'mod_projetvet'),
            ['optional' => true]
        );
        $mform->addHelpButton('enddate', 'substitution_enddate', 'mod_projetvet');

        $mform->addElement('textarea', 'reason', get_string('substitution_reason', 'mod_projetvet'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $mform->addElement(
            'advcheckbox',
            'notifyprimary',
            get_string('substitution_notifyprimary', 'mod_projetvet'),
            get_string('substitution_notifyprimary_label', 'mod_projetvet')
        );
        $mform->addHelpButton('notifyprimary', 'substitution_notifyprimary', 'mod_projetvet');

        $mform->addElement(
            'advcheckbox',
            'allowovercapacity',
            get_string('substitution_allowovercapacity', 'mod_projetvet'),
            get_string('substitution_allowovercapacity_label', 'mod_projetvet')
        );

        $this->add_action_buttons(false, get_string('substitution_create', 'mod_projetvet'));
    }

    /**
     * Get the ids of the selected students.
     *
     * @param array|\stdClass $data Submitted data
     * @return int[]
     */
    public static function get_selected_students($data): array {
        $data = (array) $data;
        $selected = [];
        foreach ((array) ($data['students'] ?? []) as $studentid => $checked) {
            if ($checked) {
                $selected[] = (int) $studentid;
            }
        }
        return $selected;
    }

    /**
     * Validate the dates and check the selected students against the control rules.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $customdata = $this->_customdata;

        $studentids = self::get_selected_students($data);
        if (empty($studentids)) {
            $first = reset($customdata['students']);
            $errors['students[' . $first->id . ']'] = get_string('substitution_error_nostudent', 'mod_projetvet');
            return $errors;
        }

        if (!empty($data['enddate']) && $data['enddate'] < $data['startdate']) {
            $errors['enddate'] = get_string('substitution_error_dates', 'mod_projetvet');
        }

        if (empty($data['substitutetutorid'])) {
            return $errors;
        }

        $check = substitutions::check_batch(
            $customdata['cmid'],
            $customdata['projetvetid'],
            $studentids,
            (int) $data['substitutetutorid'],
            !empty($data['allowovercapacity'])
        );
        if (!empty($check['tutor'])) {
            $errors['substitutetutorid'] = implode(' ', $check['tutor']);
        }
        foreach ($check['students'] as $studentid => $problems) {
            $errors['students[' . $studentid . ']'] = implode(' ', $problems);
        }
        if ($check['capacity'] !== '') {
            $errors['allowovercapacity'] = $check['capacity'];
        }

        return $errors;
    }
}
