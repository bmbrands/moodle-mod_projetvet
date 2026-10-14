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

namespace mod_projetvet\local\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/archive_test_setup.php');

use mod_projetvet\local\persistent\form_field;
use mod_projetvet\output\chart_data;
use mod_projetvet\utils;
use moodle_exception;

/**
 * Tests for the archive of projects already validated with the previous system.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(archive::class)]
final class archive_test extends \advanced_testcase {
    use \archive_test_setup;

    /**
     * Set up a course with a projetvet instance, a student, a tutor and a manager.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_archive_scenario();
    }

    /**
     * The archive form sets are installed with the plugin.
     */
    public function test_formsets_installed(): void {
        $structure = entries::get_form_structure(archive::PROJECT_FORMSET);
        $idnumbers = [];
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                $idnumbers[] = $field->idnumber;
            }
        }
        foreach (
            ['archive_title', 'archive_year', 'archive_end_date', 'archive_category', 'archive_rang',
                'archive_hours', 'archive_ects', 'archive_mobility'] as $idnumber
        ) {
            $this->assertContains($idnumber, $idnumbers);
        }
        $this->assertNotEmpty(entries::get_form_structure(archive::FORMSET));
    }

    /**
     * Student access is one-shot and can be disabled by the administrators.
     */
    public function test_student_access(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;

        $this->assertTrue(archive::student_can_open($this->context, $projetvetid, $studentid, $studentid));

        // Disabled for students by the administrators.
        set_config('archive_enabled', 0, 'mod_projetvet');
        $this->assertFalse(archive::student_can_open($this->context, $projetvetid, $studentid, $studentid));
        set_config('archive_enabled', 1, 'mod_projetvet');

        // Another student cannot open it.
        $other = $this->getDataGenerator()->create_user();
        $this->assertFalse(archive::can_open($this->context, $projetvetid, $studentid, $other->id));

        // Once submitted, the student cannot open it anymore.
        $this->create_archive([$this->project(2, 1)], archive::STATUS_SUBMITTED);
        $this->assertFalse(archive::student_can_open($this->context, $projetvetid, $studentid, $studentid));
        $this->assertFalse(archive::can_open($this->context, $projetvetid, $studentid, $studentid));
    }

    /**
     * Tutors only see the archive once submitted, administrators always.
     */
    public function test_tutor_and_manager_access(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;
        set_config('archive_enabled', 0, 'mod_projetvet');

        $this->assertFalse(archive::can_open($this->context, $projetvetid, $studentid, $this->tutor->id));
        $this->assertTrue(archive::can_open($this->context, $projetvetid, $studentid, $this->manager->id));

        // The administrator can start the archive of a student even when disabled for students.
        $this->setUser($this->manager);
        $archive = archive::get_or_create_archive_entry($this->context, $projetvetid, $studentid, $this->manager->id);
        $this->assertEquals($studentid, $archive->get('studentid'));
        $this->assertEquals(archive::STATUS_DRAFT, $archive->get('entrystatus'));

        // The tutor cannot create it.
        $this->setUser($this->tutor);
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->projetvet->course), 'student');
        try {
            archive::get_or_create_archive_entry($this->context, $projetvetid, $other->id, $this->tutor->id);
            $this->fail('Tutors must not create archives');
        } catch (moodle_exception $e) {
            $this->assertEquals('archivenoaccess', $e->errorcode);
        }

        $archive->set('entrystatus', archive::STATUS_SUBMITTED);
        $archive->update();
        $this->assertTrue(archive::can_open($this->context, $projetvetid, $studentid, $this->tutor->id));
    }

    /**
     * Who can edit the archived projects, depending on the archive status.
     */
    public function test_can_edit_projects(): void {
        $archive = $this->create_archive([$this->project(2, 1)]);
        $this->assertTrue(archive::can_edit_projects($archive, $this->student->id));
        $this->assertFalse(archive::can_edit_projects($archive, $this->tutor->id));
        $this->assertTrue(archive::can_edit_projects($archive, $this->manager->id));

        $archive->set('entrystatus', archive::STATUS_SUBMITTED);
        $archive->update();
        $this->assertFalse(archive::can_edit_projects($archive, $this->student->id));
        $this->assertTrue(archive::can_edit_projects($archive, $this->tutor->id));
        $this->assertTrue(archive::can_edit_projects($archive, $this->manager->id));

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        $this->assertFalse(archive::can_edit_projects($archive, $this->student->id));
        $this->assertFalse(archive::can_edit_projects($archive, $this->tutor->id));
        $this->assertFalse(archive::can_edit_projects($archive, $this->manager->id));
    }

    /**
     * Archived projects can only be deleted while the archive can be edited, archives never.
     */
    public function test_can_delete(): void {
        $archive = $this->create_archive([$this->project(2, 1)]);
        $project = archive::get_project_entries($archive);
        $project = reset($project);

        $this->setUser($this->student);
        $this->assertTrue($project->can_delete());
        $this->assertFalse($archive->can_delete());

        $archive->set('entrystatus', archive::STATUS_SUBMITTED);
        $archive->update();
        $this->assertFalse($project->can_delete());

        $this->setUser($this->tutor);
        $this->assertTrue($project->can_delete());

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        $this->assertFalse($project->can_delete());
    }

    /**
     * Data provider for test_status_transition.
     *
     * @return array
     */
    public static function status_transition_provider(): array {
        return [
            'Student saves the draft' => ['student', archive::STATUS_DRAFT, archive::STATUS_DRAFT, true],
            'Student submits to the tutor' => ['student', archive::STATUS_DRAFT, archive::STATUS_SUBMITTED, true],
            'Student cannot validate' => ['student', archive::STATUS_DRAFT, archive::STATUS_VALIDATED, false],
            'Student cannot validate a submitted archive' =>
                ['student', archive::STATUS_SUBMITTED, archive::STATUS_VALIDATED, false],
            'Student cannot reopen' => ['student', archive::STATUS_VALIDATED, archive::STATUS_DRAFT, false],
            'Tutor cannot submit for the student' => ['tutor', archive::STATUS_DRAFT, archive::STATUS_SUBMITTED, false],
            'Tutor validates' => ['tutor', archive::STATUS_SUBMITTED, archive::STATUS_VALIDATED, true],
            'Tutor cannot return to draft' => ['tutor', archive::STATUS_SUBMITTED, archive::STATUS_DRAFT, false],
            'Tutor cannot reopen' => ['tutor', archive::STATUS_VALIDATED, archive::STATUS_DRAFT, false],
            'Manager submits' => ['manager', archive::STATUS_DRAFT, archive::STATUS_SUBMITTED, true],
            'Manager validates' => ['manager', archive::STATUS_SUBMITTED, archive::STATUS_VALIDATED, true],
            'Manager reopens' => ['manager', archive::STATUS_VALIDATED, archive::STATUS_DRAFT, true],
            'Manager cannot skip the tutor' => ['manager', archive::STATUS_DRAFT, archive::STATUS_VALIDATED, false],
        ];
    }

    /**
     * Test the allowed archive status transitions.
     *
     * @param string $user The user role in the test (student, tutor or manager)
     * @param int $from The current status
     * @param int $to The requested status
     * @param bool $allowed Whether the transition is allowed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('status_transition_provider')]
    public function test_status_transition(string $user, int $from, int $to, bool $allowed): void {
        $archive = $this->create_archive([$this->project(2, 1)], $from);
        if (!$allowed) {
            $this->expectException(moodle_exception::class);
        }
        archive::require_status_transition($archive, $to, $this->{$user}->id);
        $this->assertTrue($allowed);
    }

    /**
     * Archived projects follow the status of their archive.
     */
    public function test_sync_project_statuses(): void {
        $archive = $this->create_archive([$this->project(2, 1), $this->project(1, 2)]);

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        archive::sync_project_statuses($archive);

        $projects = archive::get_project_entries($archive);
        $this->assertCount(2, $projects);
        foreach ($projects as $project) {
            $this->assertEquals(archive::STATUS_VALIDATED, $project->get('entrystatus'));
        }
    }

    /**
     * Only validated archived projects count in the ECTS and Rank A/B counters.
     */
    public function test_validated_ects(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;
        $archive = $this->create_archive(
            [$this->project(2, 1), $this->project(3, 1), $this->project(1, 2)],
            archive::STATUS_SUBMITTED
        );

        $this->assertEquals(
            ['total' => 0, 'rank_a' => 0, 'rank_b' => 0],
            archive::get_validated_ects($projetvetid, $studentid)
        );
        $this->assertEquals(0, chart_data::get_total_ects($projetvetid, $studentid));

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        archive::sync_project_statuses($archive);

        $this->assertEquals(
            ['total' => 6, 'rank_a' => 5, 'rank_b' => 1],
            archive::get_validated_ects($projetvetid, $studentid)
        );
        $this->assertEquals(6, chart_data::get_total_ects($projetvetid, $studentid));
        $this->assertEquals(6, utils::get_student_total_ects($projetvetid, $studentid));
        $this->assertEquals(['rank_a' => 5, 'rank_b' => 1], chart_data::get_ects_by_rank($projetvetid, $studentid));
    }

    /**
     * Archived projects add up with standard ProjetVet activities.
     */
    public function test_validated_ects_with_activities(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_projetvet');

        $activity = $generator->create_form_entry([
            'projetvetid' => $projetvetid,
            'formsetid' => $generator->get_formset_by_idnumber('activities')->id,
            'studentid' => $studentid,
            'entrystatus' => 4,
        ]);
        $generator->create_form_data([
            'entryid' => $activity->id,
            'fieldid' => form_field::get_record(['idnumber' => 'final_ects'])->get('id'),
            'intvalue' => 4,
        ]);
        $generator->create_form_data([
            'entryid' => $activity->id,
            'fieldid' => form_field::get_record(['idnumber' => 'rang'])->get('id'),
            'intvalue' => 2,
        ]);

        $this->create_archive([$this->project(2, 1)], archive::STATUS_VALIDATED);

        $this->assertEquals(6, chart_data::get_total_ects($projetvetid, $studentid));
        $this->assertEquals(6, utils::get_student_total_ects($projetvetid, $studentid));
        $this->assertEquals(['rank_a' => 2, 'rank_b' => 4], chart_data::get_ects_by_rank($projetvetid, $studentid));
    }

    /**
     * A validated archived project with international mobility counts for the mobility indicator.
     */
    public function test_validated_mobility(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;
        $archive = $this->create_archive(
            [$this->project(2, 1, 2), $this->project(1, 1, archive::MOBILITY_YES)],
            archive::STATUS_SUBMITTED
        );

        $this->assertFalse(archive::has_validated_mobility($projetvetid, $studentid));

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        archive::sync_project_statuses($archive);
        $this->assertTrue(archive::has_validated_mobility($projetvetid, $studentid));
    }

    /**
     * Archive managers can edit the current status categories of the archive forms.
     */
    public function test_form_permissions(): void {
        $this->setUser($this->manager);
        $structure = entries::get_form_structure(archive::FORMSET, archive::STATUS_VALIDATED, $this->context);
        $editable = array_filter($structure, fn($category) => $category->canedit);
        $this->assertCount(1, $editable);
        $this->assertEquals('managearchive', reset($editable)->capability);

        $this->setUser($this->tutor);
        $structure = entries::get_form_structure(archive::FORMSET, archive::STATUS_VALIDATED, $this->context);
        $this->assertEmpty(array_filter($structure, fn($category) => $category->canedit));

        $structure = entries::get_form_structure(archive::PROJECT_FORMSET, archive::STATUS_SUBMITTED, $this->context);
        $editable = array_map(
            fn($category) => $category->capability,
            array_filter($structure, fn($category) => $category->canedit)
        );
        $this->assertEqualsCanonicalizing(['submit', 'approve'], array_values($editable));

        $this->setUser($this->student);
        $structure = entries::get_form_structure(archive::PROJECT_FORMSET, archive::STATUS_SUBMITTED, $this->context);
        $this->assertEmpty(array_filter($structure, fn($category) => $category->canedit));
    }

    /**
     * A submitted archive is flagged as requiring tutor action.
     */
    public function test_pending_teacher_action(): void {
        $projetvetid = $this->projetvet->id;
        $studentid = $this->student->id;
        $archive = $this->create_archive([$this->project(2, 1)]);
        $this->assertFalse(utils::student_has_pending_teacher_action($projetvetid, $studentid));

        $archive->set('entrystatus', archive::STATUS_SUBMITTED);
        $archive->update();
        $this->assertTrue(utils::student_has_pending_teacher_action($projetvetid, $studentid));

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        $this->assertFalse(utils::student_has_pending_teacher_action($projetvetid, $studentid));
    }
}
