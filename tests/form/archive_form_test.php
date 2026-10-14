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
require_once($CFG->dirroot . '/mod/projetvet/tests/archive_test_setup.php');

use core_form\external\dynamic_form;
use mod_projetvet\local\api\archive;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use moodle_exception;

/**
 * Tests for submitting the archive forms through the dynamic form web service.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(projetvet_form::class)]
final class archive_form_test extends \advanced_testcase {
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
     * Submit the archive form as the current user.
     *
     * @param form_entry $archive The archive entry
     * @param int $buttonstatus The status requested by the clicked button
     * @return array The dynamic form web service result
     */
    private function submit_archive(form_entry $archive, int $buttonstatus): array {
        $formdata = projetvet_form::mock_ajax_submit([
            'cmid' => $this->projetvet->cmid,
            'projetvetid' => $this->projetvet->id,
            'studentid' => $this->student->id,
            'entryid' => $archive->get('id'),
            'formsetidnumber' => archive::FORMSET,
            'entrystatus' => $archive->get('entrystatus'),
            'button_entrystatus' => $buttonstatus,
        ]);
        return dynamic_form::execute(projetvet_form::class, http_build_query($formdata, '', '&'));
    }

    /**
     * Submit an archived project form as the current user.
     *
     * @param form_entry $archive The archive entry
     * @param array $values Field values keyed by archived project field idnumber
     * @param int $entryid The archived project entry to update, 0 to add one
     * @return array The dynamic form web service result
     */
    private function submit_project(form_entry $archive, array $values, int $entryid = 0): array {
        $formdata = [
            'cmid' => $this->projetvet->cmid,
            'projetvetid' => $this->projetvet->id,
            'studentid' => $this->student->id,
            'entryid' => $entryid,
            'parententryid' => $archive->get('id'),
            'formsetidnumber' => archive::PROJECT_FORMSET,
            'entrystatus' => $archive->get('entrystatus'),
            'button_entrystatus' => 0,
        ];
        foreach ($values as $idnumber => $value) {
            $formdata['field_' . $idnumber] = $value;
        }
        $formdata = projetvet_form::mock_ajax_submit($formdata);
        return dynamic_form::execute(projetvet_form::class, http_build_query($formdata, '', '&'));
    }

    /**
     * Form values of an archived project.
     *
     * @param int $ects
     * @return array
     */
    private function project_formvalues(int $ects): array {
        return [
            'archive_title' => 'Stage rural',
            'archive_year' => 3,
            'archive_end_date' => ['enabled' => 1, 'day' => 30, 'month' => 6, 'year' => 2024],
            'archive_category' => [1],
            'archive_rang' => 1,
            'archive_hours' => 60,
            'archive_ects' => $ects,
            'archive_mobility' => archive::MOBILITY_YES,
        ];
    }

    /**
     * Get the stored value of an archived project field.
     *
     * @param int $entryid
     * @param string $idnumber
     * @return mixed
     */
    private function get_value(int $entryid, string $idnumber) {
        $field = form_field::get_record(['idnumber' => $idnumber]);
        return form_data::get_record(['entryid' => $entryid, 'fieldid' => $field->get('id')])->get_value();
    }

    /**
     * The complete workflow: the student adds projects and submits, the tutor corrects and validates.
     */
    public function test_workflow(): void {
        $archive = $this->create_archive([]);

        // The student adds a project.
        $this->setUser($this->student);
        $result = $this->submit_project($archive, $this->project_formvalues(2));
        $this->assertTrue($result['submitted']);
        $projectid = json_decode($result['data'])->entryid;
        $this->assertEquals(2, $this->get_value($projectid, 'archive_ects'));

        // The student submits the archive to the tutor.
        $result = $this->submit_archive($archive, archive::STATUS_SUBMITTED);
        $this->assertTrue($result['submitted']);
        $archive->read();
        $this->assertEquals(archive::STATUS_SUBMITTED, $archive->get('entrystatus'));
        $this->assertEquals(archive::STATUS_SUBMITTED, (new form_entry($projectid))->get('entrystatus'));

        // The student can no longer change the projects.
        try {
            $this->submit_project($archive, $this->project_formvalues(5), $projectid);
            $this->fail('The student must not edit a submitted archive');
        } catch (moodle_exception $e) {
            $this->assertEquals('archivenoaccess', $e->errorcode);
        }

        // The tutor corrects the credits and adds a missing project, then validates.
        $this->setUser($this->tutor);
        $result = $this->submit_project($archive, $this->project_formvalues(3), $projectid);
        $this->assertTrue($result['submitted']);
        $this->assertEquals(3, $this->get_value($projectid, 'archive_ects'));
        $this->assertEquals(archive::STATUS_SUBMITTED, (new form_entry($projectid))->get('entrystatus'));

        $result = $this->submit_project($archive, $this->project_formvalues(1));
        $this->assertTrue($result['submitted']);
        $addedid = json_decode($result['data'])->entryid;
        $this->assertEquals(archive::STATUS_SUBMITTED, (new form_entry($addedid))->get('entrystatus'));

        $result = $this->submit_archive($archive, archive::STATUS_VALIDATED);
        $this->assertTrue($result['submitted']);
        $archive->read();
        $this->assertEquals(archive::STATUS_VALIDATED, $archive->get('entrystatus'));
        $this->assertEquals($this->tutor->id, $this->get_value($archive->get('id'), 'archive_validation_user'));
        $this->assertEquals(
            ['total' => 4, 'rank_a' => 4, 'rank_b' => 0],
            archive::get_validated_ects($this->projetvet->id, $this->student->id)
        );

        // Nobody but an administrator can change a validated archive.
        try {
            $this->submit_project($archive, $this->project_formvalues(5), $projectid);
            $this->fail('The tutor must not edit a validated archive');
        } catch (moodle_exception $e) {
            $this->assertEquals('archivenoaccess', $e->errorcode);
        }

        // The administrator reopens it.
        $this->setUser($this->manager);
        $result = $this->submit_archive($archive, archive::STATUS_DRAFT);
        $this->assertTrue($result['submitted']);
        $archive->read();
        $this->assertEquals(archive::STATUS_DRAFT, $archive->get('entrystatus'));
        $this->assertEquals(archive::STATUS_DRAFT, (new form_entry($projectid))->get('entrystatus'));
    }

    /**
     * A student cannot skip the tutor validation.
     */
    public function test_student_cannot_validate(): void {
        $archive = $this->create_archive([$this->project(2, 1)]);
        $this->setUser($this->student);

        $this->expectException(moodle_exception::class);
        $this->submit_archive($archive, archive::STATUS_VALIDATED);
    }

    /**
     * An empty archive cannot be submitted to the tutor.
     */
    public function test_empty_archive_cannot_be_submitted(): void {
        $archive = $this->create_archive([]);
        $this->setUser($this->student);

        $result = $this->submit_archive($archive, archive::STATUS_SUBMITTED);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('archivenoprojects', 'mod_projetvet'), $result['html']);
        $archive->read();
        $this->assertEquals(archive::STATUS_DRAFT, $archive->get('entrystatus'));
    }

    /**
     * Archived project fields are all mandatory.
     */
    public function test_project_fields_required(): void {
        $archive = $this->create_archive([]);
        $this->setUser($this->student);

        $values = $this->project_formvalues(2);
        unset($values['archive_mobility']);
        $result = $this->submit_project($archive, $values);
        $this->assertFalse($result['submitted']);
        $this->assertEmpty(archive::get_project_entries($archive));
    }

    /**
     * Another student cannot open or change someone else's archive.
     */
    public function test_other_student_has_no_access(): void {
        $archive = $this->create_archive([$this->project(2, 1)]);
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->projetvet->course), 'student');
        $this->setUser($other);

        $this->expectException(moodle_exception::class);
        $this->submit_project($archive, $this->project_formvalues(5));
    }
}
