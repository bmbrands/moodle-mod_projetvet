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

use moodleform;

/**
 * Form to close, extend or make definitive the open substitutions of a principal tutor's students.
 *
 * Custom data:
 * - cmid, tutorid: the activity and the principal tutor;
 * - substitutions: the open substitutions (substitution persistents).
 *
 * Substitutions are submitted as substitutions[id] => 0|1, the action is the submit button used.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitution_manage_form extends moodleform {
    /** @var string Close the selected substitutions */
    public const ACTION_CLOSE = 'closebutton';

    /** @var string Change the planned end date of the selected substitutions */
    public const ACTION_EXTEND = 'extendbutton';

    /** @var string Make the selected substitutions definitive */
    public const ACTION_DEFINITIVE = 'definitivebutton';

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

        $mform->addElement('header', 'manageheader', get_string('substitution_open_heading', 'mod_projetvet'));
        $mform->setExpanded('manageheader');

        if (empty($customdata['substitutions'])) {
            $mform->addElement('static', 'noopen', '', get_string('substitution_noopen', 'mod_projetvet'));
            return;
        }

        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        foreach ($customdata['substitutions'] as $substitution) {
            $a = (object) [
                'student' => fullname(\core_user::get_user($substitution->get('studentid'))),
                'substitute' => fullname(\core_user::get_user($substitution->get('substitutetutorid'))),
                'startdate' => userdate($substitution->get('startdate'), $dateformat),
            ];
            $label = get_string('substitution_open_label', 'mod_projetvet', $a);
            $description = [];
            if ($substitution->get('enddate')) {
                $enddate = userdate($substitution->get('enddate'), $dateformat);
                $description[] = $substitution->is_overdue()
                    ? get_string('substitution_overdue', 'mod_projetvet', $enddate)
                    : get_string('substitution_plannedend', 'mod_projetvet', $enddate);
            }
            if (!$substitution->is_in_effect()) {
                $description[] = get_string('substitution_planned', 'mod_projetvet');
            }
            $mform->addElement(
                'advcheckbox',
                'substitutions[' . $substitution->get('id') . ']',
                $label,
                implode(' – ', $description),
                ['group' => 2]
            );
            $mform->setType('substitutions[' . $substitution->get('id') . ']', PARAM_INT);
        }
        $this->add_checkbox_controller(2, get_string('substitution_selectall', 'mod_projetvet'), null, 0);

        $mform->addElement(
            'date_selector',
            'newenddate',
            get_string('substitution_newenddate', 'mod_projetvet'),
            ['optional' => true]
        );
        $mform->addHelpButton('newenddate', 'substitution_newenddate', 'mod_projetvet');

        $buttons = [
            $mform->createElement('submit', self::ACTION_CLOSE, get_string('substitution_close', 'mod_projetvet')),
            $mform->createElement(
                'submit',
                self::ACTION_EXTEND,
                get_string('substitution_extend', 'mod_projetvet'),
                [],
                false,
                ['customclassoverride' => 'btn-secondary']
            ),
            $mform->createElement(
                'submit',
                self::ACTION_DEFINITIVE,
                get_string('substitution_makedefinitive', 'mod_projetvet'),
                [],
                false,
                ['customclassoverride' => 'btn-secondary']
            ),
        ];
        $mform->addGroup($buttons, 'actionbuttons', '', ' ', false);
    }

    /**
     * Get the ids of the selected substitutions.
     *
     * @param array|\stdClass $data Submitted data
     * @return int[]
     */
    public static function get_selected_substitutions($data): array {
        $data = (array) $data;
        $selected = [];
        foreach ((array) ($data['substitutions'] ?? []) as $substitutionid => $checked) {
            if ($checked) {
                $selected[] = (int) $substitutionid;
            }
        }
        return $selected;
    }

    /**
     * Get the action of the submitted data.
     *
     * @param array|\stdClass $data Submitted data
     * @return string One of the ACTION_ constants, '' when none
     */
    public static function get_action($data): string {
        $data = (array) $data;
        foreach ([self::ACTION_CLOSE, self::ACTION_EXTEND, self::ACTION_DEFINITIVE] as $action) {
            if (!empty($data[$action])) {
                return $action;
            }
        }
        return '';
    }

    /**
     * Validate the selection and the new planned end date.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $selected = self::get_selected_substitutions($data);
        if (empty($selected)) {
            $first = reset($this->_customdata['substitutions']);
            $errors['substitutions[' . $first->get('id') . ']'] = get_string('substitution_error_noselection', 'mod_projetvet');
            return $errors;
        }

        if (self::get_action($data) === self::ACTION_EXTEND) {
            if (empty($data['newenddate'])) {
                $errors['newenddate'] = get_string('substitution_error_newenddate', 'mod_projetvet');
                return $errors;
            }
            foreach ($this->_customdata['substitutions'] as $substitution) {
                if (
                    in_array((int) $substitution->get('id'), $selected, true)
                        && $data['newenddate'] < (int) $substitution->get('startdate')
                ) {
                    $errors['newenddate'] = get_string('substitution_error_dates', 'mod_projetvet');
                }
            }
        }

        return $errors;
    }
}
