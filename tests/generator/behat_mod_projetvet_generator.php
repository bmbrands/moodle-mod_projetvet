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
 * Behat data generator for mod_projetvet.
 *
 * @package     mod_projetvet
 * @copyright   2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_projetvet_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'form_entries' => [
                'singular' => 'form_entry',
                'datagenerator' => 'form_entry',
                'required' => ['projetvet', 'formset', 'student'],
                'switchids' => ['projetvet' => 'projetvetid', 'formset' => 'formsetid', 'student' => 'studentid'],
            ],
            'form_data' => [
                'singular' => 'form_data',
                'datagenerator' => 'form_data',
                'required' => ['field', 'entry'],
                'switchids' => ['field' => 'fieldid', 'entry' => 'entryid'],
            ],
            'projects' => [
                'singular' => 'project',
                'datagenerator' => 'project',
                'required' => ['projetvet', 'student', 'title'],
                'switchids' => ['projetvet' => 'projetvetid', 'student' => 'studentid'],
            ],
            'projetvet groups' => [
                'singular' => 'projetvet group',
                'datagenerator' => 'projetvet_group',
                'required' => ['name', 'teacher', 'projetvetidnumber'],
                'switchids' => [],
            ],
            'projetvet group members' => [
                'singular' => 'projetvet group member',
                'datagenerator' => 'projetvet_group_member',
                'required' => ['user', 'group'],
                'switchids' => [],
            ],
        ];
    }

    /**
     * Gets the projetvet activity id from its idnumber.
     *
     * @param string $idnumber
     * @return int The activity id
     */
    protected function get_projetvet_id(string $idnumber): int {
        // The entries reference the projetvet instance, not the course module.
        $cm = get_coursemodule_from_id('projetvet', $this->get_activity_id($idnumber), 0, false, MUST_EXIST);
        return (int) $cm->instance;
    }

    /**
     * Gets the formset id from its idnumber.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_formset_id(string $idnumber): int {
        global $DB;
        $formset = $DB->get_record('projetvet_form_set', ['idnumber' => $idnumber]);
        if (!$formset) {
            throw new Exception("Form set with idnumber '$idnumber' not found");
        }
        return $formset->id;
    }

    /**
     * Gets the field id from its idnumber.
     *
     * @param string $idnumber
     * @return int
     */
    protected function get_field_id(string $idnumber): int {
        global $DB;
        $field = $DB->get_record('projetvet_form_field', ['idnumber' => $idnumber]);
        if (!$field) {
            throw new Exception("Form field with idnumber '$idnumber' not found");
        }
        return $field->id;
    }

    /**
     * Gets the entry id from its identifier.
     *
     * @param string $identifier
     * @return int
     */
    protected function get_entry_id(string $identifier): int {
        global $DB;
        // For simplicity, assume identifier is the entry id itself.
        $entry = $DB->get_record('projetvet_form_entry', ['id' => intval($identifier)]);
        if (!$entry) {
            throw new Exception("Form entry with id '$identifier' not found");
        }
        return $entry->id;
    }

    /**
     * Gets the student user id from its username.
     *
     * @param string $username
     * @return int
     */
    protected function get_student_id(string $username): int {
        return $this->get_user_id($username);
    }

    /**
     * Gets the teacher user id from its username.
     *
     * @param string $username
     * @return int
     */
    protected function get_teacher_id(string $username): int {
        return $this->get_user_id($username);
    }

    /**
     * Gets the secondary teacher user id from its username.
     *
     * @param string $username
     * @return int
     */
    protected function get_secondaryteacher_id(string $username): int {
        return $this->get_user_id($username);
    }

    /**
     * Gets the projetvet group id from its name.
     *
     * @param string $groupname
     * @return int
     */
    protected function get_projetvetgroup_id(string $groupname): int {
        global $DB;
        $group = $DB->get_record('projetvet_group', ['name' => $groupname]);
        if (!$group) {
            throw new Exception("Projetvet group with name '$groupname' not found");
        }
        return $group->id;
    }
}
