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

use mod_projetvet\local\api\archive;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;

/**
 * Shared setup for the tests of the archive of projects already validated with the previous system.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait archive_test_setup {
    /** @var stdClass The projetvet instance */
    private stdClass $projetvet;

    /** @var context_module The module context */
    private context_module $context;

    /** @var stdClass The student */
    private stdClass $student;

    /** @var stdClass The tutor */
    private stdClass $tutor;

    /** @var stdClass The manager (archive administrator) */
    private stdClass $manager;

    /**
     * Create a course with a projetvet instance, a student, a tutor and a manager.
     *
     * @return void
     */
    private function setup_archive_scenario(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $this->context = context_module::instance($this->projetvet->cmid);

        $this->student = $generator->create_and_enrol($course, 'student');
        $this->tutor = $generator->create_and_enrol($course, 'editingteacher');
        $this->manager = $generator->create_and_enrol($course, 'manager');

        set_config('archive_enabled', 1, 'mod_projetvet');
    }

    /**
     * Create the student's archive with archived projects.
     *
     * @param array $projects List of field values keyed by archived project field idnumber
     * @param int $status The archive status
     * @return form_entry The archive entry
     */
    private function create_archive(array $projects, int $status = archive::STATUS_DRAFT): form_entry {
        global $USER;

        $currentuser = $USER;
        $this->setUser($this->student);
        $archive = archive::get_or_create_archive_entry(
            $this->context,
            $this->projetvet->id,
            $this->student->id,
            $this->student->id
        );

        foreach ($projects as $values) {
            $fields = [];
            foreach ($values as $idnumber => $value) {
                $fields[form_field::get_record(['idnumber' => $idnumber])->get('id')] = $value;
            }
            entries::create_entry(
                $this->projetvet->id,
                $this->student->id,
                $fields,
                archive::STATUS_DRAFT,
                archive::PROJECT_FORMSET,
                $archive->get('id')
            );
        }

        $archive->set('entrystatus', $status);
        $archive->update();
        archive::sync_project_statuses($archive);
        $this->setUser($currentuser);

        return $archive;
    }

    /**
     * Archived project values.
     *
     * @param int $ects
     * @param int $rang 1 = A, 2 = B
     * @param int $mobility 1 = yes, 2 = no
     * @return array
     */
    private function project(int $ects, int $rang, int $mobility = 2): array {
        return [
            'archive_title' => 'Project ' . $ects . '/' . $rang,
            'archive_year' => 3,
            'archive_end_date' => mktime(0, 0, 0, 6, 30, 2024),
            'archive_category' => '[1]',
            'archive_rang' => $rang,
            'archive_hours' => $ects * 30,
            'archive_ects' => $ects,
            'archive_mobility' => $mobility,
        ];
    }
}
