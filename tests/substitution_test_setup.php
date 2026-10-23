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

use mod_projetvet\local\api\groups;
use mod_projetvet\local\api\substitutions;
use mod_projetvet\local\persistent\substitution;

/**
 * Shared setup for the tests of the temporary substitute tutors.
 *
 * The scenario has a principal tutor with four students, three possible substitutes, an
 * unassigned student and a manager.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait substitution_test_setup {
    /** @var stdClass The course */
    private stdClass $course;

    /** @var stdClass The projetvet instance (with cmid) */
    private stdClass $projetvet;

    /** @var stdClass The principal tutor */
    private stdClass $primary;

    /** @var stdClass[] The possible substitutes, indexed 1 to 3 */
    private array $substitutes = [];

    /** @var stdClass[] The students of the principal tutor, indexed 1 to 4 */
    private array $students = [];

    /** @var stdClass A student without tutor */
    private stdClass $unassigned;

    /** @var stdClass The manager */
    private stdClass $manager;

    /**
     * Create the substitution scenario.
     *
     * @return void
     */
    private function setup_substitution_scenario(): void {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $this->course->id]);

        $this->primary = $generator->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Primary']);
        for ($i = 1; $i <= 3; $i++) {
            $this->substitutes[$i] = $generator->create_and_enrol(
                $this->course,
                'editingteacher',
                ['firstname' => 'Substitute' . $i]
            );
        }
        for ($i = 1; $i <= 4; $i++) {
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'Student' . $i]);
        }
        $this->unassigned = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'Unassigned']);
        $this->manager = $generator->create_and_enrol($this->course, 'manager');

        groups::assign_students_to_teacher(
            $this->primary->id,
            array_column($this->students, 'id'),
            $this->projetvet->id
        );
    }

    /**
     * Create substitutions for students, as the manager.
     *
     * @param stdClass[] $students
     * @param stdClass $substitute
     * @param array $options Overridden create options (startdate, enddate, reason, notifyprimary, allowovercapacity)
     * @return substitution[] Indexed by student id
     */
    private function substitute(array $students, stdClass $substitute, array $options = []): array {
        global $USER;

        $currentuser = $USER;
        $this->setUser($this->manager);
        $options += [
            'startdate' => time() - DAYSECS,
            'enddate' => 0,
            'reason' => 'Sick leave',
            'notifyprimary' => false,
            'allowovercapacity' => false,
        ];
        $result = substitutions::create_substitutions(
            $this->projetvet->cmid,
            $this->projetvet->id,
            array_column($students, 'id'),
            $substitute->id,
            $options['startdate'],
            $options['enddate'],
            $options['reason'],
            $options['notifyprimary'],
            $options['allowovercapacity']
        );
        $this->setUser($currentuser);
        return $result;
    }

    /**
     * Set a custom profile field value, creating the field when needed.
     *
     * @param stdClass $user
     * @param string $shortname
     * @param string $value
     * @return void
     */
    private function set_profile_field(stdClass $user, string $shortname, string $value): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            $field = (object) [
                'shortname' => $shortname,
                'name' => $shortname,
                'datatype' => 'text',
                'categoryid' => 0,
                'sortorder' => 0,
                'visible' => 1,
            ];
            $field->id = $DB->insert_record('user_info_field', $field);
        }

        $DB->delete_records('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $field->id,
            'data' => $value,
        ]);
    }
}
