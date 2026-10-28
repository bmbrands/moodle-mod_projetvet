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

namespace mod_projetvet\reportbuilder\local\systemreports;

use core_reportbuilder\system_report_factory;
use mod_projetvet\local\api\entries as entries_api;
use mod_projetvet\local\persistent\form_field;
use stdClass;

/**
 * Tests for the delete action of the Projects (entries) system report.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(entries::class)]
final class entries_delete_action_test extends \advanced_testcase {
    /** @var stdClass The projetvet instance (with cmid) */
    private stdClass $projetvet;

    /** @var stdClass The student */
    private stdClass $student;

    /** @var int[] Project ids indexed by status */
    private array $projects = [];

    /**
     * Set up a student with a submitted and a validated project.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $this->student = $generator->create_and_enrol($course, 'student');

        $titlefield = form_field::get_record(['idnumber' => 'activity_title']);
        foreach ([1 => 'Club "Comédie" & co', 4 => 'Validated project'] as $status => $title) {
            $this->projects[$status] = entries_api::create_entry(
                $this->projetvet->id,
                $this->student->id,
                [$titlefield->get('id') => $title],
                $status
            );
        }
    }

    /**
     * Render the Projects report of the student as the current user.
     *
     * @return string
     */
    private function render_report(): string {
        global $PAGE;

        $context = \context_module::instance($this->projetvet->cmid);
        $PAGE->set_url(new \moodle_url('/mod/projetvet/view.php', ['id' => $this->projetvet->cmid]));
        $PAGE->set_context($context);
        $report = system_report_factory::create(entries::class, $context, 'mod_projetvet', 'entries', 1, [
            'cmid' => $this->projetvet->cmid,
            'projetvetid' => $this->projetvet->id,
            'studentid' => $this->student->id,
            'formsetidnumber' => 'activities',
        ]);
        return $report->output();
    }

    /**
     * Get the ids of the entries with a delete action in the report HTML.
     *
     * @param string $html
     * @return int[]
     */
    private function get_deletable_ids(string $html): array {
        preg_match_all('/data-action="delete-activity"[^>]*data-entryid="(\d+)"/', $html, $matches);
        return array_map('intval', array_unique($matches[1]));
    }

    /**
     * The student only gets the delete action on their projects up to submitted.
     */
    public function test_student(): void {
        $this->setUser($this->student);
        $html = $this->render_report();

        $this->assertEquals([$this->projects[1]], $this->get_deletable_ids($html));
        // The title is passed (escaped) for the confirmation.
        $this->assertStringContainsString('data-entrytitle="Club &quot;Comédie&quot; &amp; co"', $html);
    }

    /**
     * Teachers and administrators get the delete action on every project.
     */
    public function test_teacher_and_manager(): void {
        $course = get_course($this->projetvet->course);
        foreach (['teacher', 'editingteacher', 'manager'] as $role) {
            $this->setUser($this->getDataGenerator()->create_and_enrol($course, $role));
            $ids = $this->get_deletable_ids($this->render_report());
            sort($ids);
            $this->assertEquals(array_values($this->projects), $ids, $role);
        }
    }
}
