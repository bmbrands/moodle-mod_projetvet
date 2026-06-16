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

namespace mod_projetvet;

/**
 * Tests for utils helpers used by dashboard report.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_projetvet\utils
 */
final class utils_test extends \advanced_testcase {
    /**
     * Test dashboard-related student metrics from utils.
     *
     * @return void
     */
    public function test_dashboard_student_metrics(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        $course = $generator->create_course();
        $student = $generator->create_user(['username' => 'student_metrics']);
        $student2 = $generator->create_user(['username' => 'student_no_data']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        $activitiesformsetid = $this->ensure_formset_exists('activities');
        $facetofaceformsetid = $this->ensure_formset_exists('facetoface');
        $finalectsfieldid = $this->ensure_final_ects_field_exists($activitiesformsetid);

        // Activities entries for student 1.
        $entry0 = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 0,
        ]);
        $entry1 = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 1,
        ]);
        $entry2 = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 2,
        ]);
        $entry3 = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 3,
        ]);
        $entry4 = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $activitiesformsetid,
            'studentid' => $student->id,
            'entrystatus' => 4,
        ]);

        // Non-activities entry (must not affect project metrics).
        $facetofaceentry = $projetvetgenerator->create_form_entry([
            'projetvetid' => $projetvet->id,
            'formsetid' => $facetofaceformsetid,
            'studentid' => $student->id,
            'entrystatus' => 3,
        ]);

        // Final_ects values.
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $entry0->id, 99); // Draft: excluded.
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $entry1->id, 2);
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $entry2->id, 4);
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $entry3->id, 8);
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $entry4->id, 10);
        $this->add_ects_value($projetvetgenerator, $finalectsfieldid, $facetofaceentry->id, 20); // Ignored.

        $this->assertEquals(4, utils::get_student_project_count($projetvet->id, $student->id));
        $this->assertEquals(2, utils::get_student_projects_to_validate_count($projetvet->id, $student->id));
        $this->assertEquals(6.0, utils::get_student_median_ects($projetvet->id, $student->id));

        // Student with no data should return 0 metrics.
        $this->assertEquals(0, utils::get_student_project_count($projetvet->id, $student2->id));
        $this->assertEquals(0, utils::get_student_projects_to_validate_count($projetvet->id, $student2->id));
        $this->assertEquals(0.0, utils::get_student_median_ects($projetvet->id, $student2->id));
    }

    /**
     * Ensure a formset exists and return its ID.
     *
     * @param string $idnumber
     * @return int
     */
    private function ensure_formset_exists(string $idnumber): int {
        global $DB;

        $existing = $DB->get_record('projetvet_form_set', ['idnumber' => $idnumber]);
        if ($existing) {
            return (int)$existing->id;
        }

        $record = (object)[
            'idnumber' => $idnumber,
            'name' => ucfirst($idnumber),
            'description' => '',
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ];

        return (int)$DB->insert_record('projetvet_form_set', $record);
    }

    /**
     * Ensure final_ects field exists and return its ID.
     *
     * @param int $activitiesformsetid
     * @return int
     */
    private function ensure_final_ects_field_exists(int $activitiesformsetid): int {
        global $DB;

        $existing = $DB->get_record('projetvet_form_field', ['idnumber' => 'final_ects']);
        if ($existing) {
            return (int)$existing->id;
        }

        $category = $DB->get_record('projetvet_form_cat', ['idnumber' => 'phpunit_dashboard_cat']);
        if (!$category) {
            $categoryid = $DB->insert_record('projetvet_form_cat', (object)[
                'formsetid' => $activitiesformsetid,
                'idnumber' => 'phpunit_dashboard_cat',
                'name' => 'PHPUnit dashboard category',
                'description' => '',
                'capability' => 'submit',
                'entrystatus' => 0,
                'statusmsg' => 'draft',
                'sortorder' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => 2,
            ]);
        } else {
            $categoryid = $category->id;
        }

        return (int)$DB->insert_record('projetvet_form_field', (object)[
            'categoryid' => $categoryid,
            'idnumber' => 'final_ects',
            'name' => 'Final ECTS',
            'type' => 'number',
            'description' => '',
            'sortorder' => 0,
            'configdata' => null,
            'capability' => 'approve',
            'entrystatus' => 3,
            'listorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
    }

    /**
     * Add ECTS value for one entry.
     *
     * @param object $projetvetgenerator
     * @param int $fieldid
     * @param int $entryid
     * @param int $value
     * @return void
     */
    private function add_ects_value(object $projetvetgenerator, int $fieldid, int $entryid, int $value): void {
        $projetvetgenerator->create_form_data([
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'intvalue' => $value,
        ]);
    }
}
