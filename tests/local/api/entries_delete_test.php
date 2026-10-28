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

use mod_projetvet\external\delete_entry;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use moodle_exception;
use stdClass;

/**
 * Tests for deleting projects (entries).
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(entries::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(form_entry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(delete_entry::class)]
final class entries_delete_test extends \advanced_testcase {
    /** @var stdClass The projetvet instance (with cmid) */
    private stdClass $projetvet;

    /** @var stdClass[] Users by role name */
    private array $users = [];

    /**
     * Set up a course with a student, another student, teachers and a manager.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        foreach (
            ['student' => 'student', 'otherstudent' => 'student', 'editingteacher' => 'editingteacher',
                'teacher' => 'teacher', 'manager' => 'manager'] as $name => $role
        ) {
            $this->users[$name] = $generator->create_and_enrol($course, $role);
        }

        // A teacher of another course has no access to this activity.
        $othercourse = $generator->create_course();
        $this->users['outsider'] = $generator->create_and_enrol($othercourse, 'editingteacher');
    }

    /**
     * Create a project (activities entry) of the student.
     *
     * @param int $status
     * @param string $title
     * @return form_entry
     */
    private function create_project(int $status, string $title = 'Présidence du Club Comédie Musicale'): form_entry {
        $titlefield = form_field::get_record(['idnumber' => 'activity_title']);
        $entryid = entries::create_entry(
            $this->projetvet->id,
            $this->users['student']->id,
            [$titlefield->get('id') => $title],
            $status
        );
        return new form_entry($entryid);
    }

    /**
     * Data provider for test_can_delete.
     *
     * @return array
     */
    public static function can_delete_provider(): array {
        return [
            'Student, draft' => ['student', 0, true],
            'Student, submitted' => ['student', 1, true],
            'Student, accepted' => ['student', 2, true],
            'Student, report submitted' => ['student', 3, true],
            'Student, validated' => ['student', 4, true],
            'Other student, draft' => ['otherstudent', 0, false],
            'Editing teacher, draft' => ['editingteacher', 0, true],
            'Editing teacher, validated' => ['editingteacher', 4, true],
            'Teacher, validated' => ['teacher', 4, true],
            'Manager, validated' => ['manager', 4, true],
            'Teacher of another course, draft' => ['outsider', 0, false],
        ];
    }

    /**
     * The student, their teachers and administrators delete a project at any status.
     *
     * @param string $user The user name in the scenario
     * @param int $status The project status
     * @param bool $expected Whether the user can delete the project
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('can_delete_provider')]
    public function test_can_delete(string $user, int $status, bool $expected): void {
        $project = $this->create_project($status);
        $this->assertSame($expected, $project->can_delete($this->users[$user]->id));

        $this->setUser($this->users[$user]);
        $this->assertSame($expected, $project->can_delete());
    }

    /**
     * The student deletes their other entries (face-to-face sessions) only while draft or submitted.
     */
    public function test_can_delete_other_entries(): void {
        $student = $this->users['student'];
        foreach ([0 => true, 1 => true, 2 => false] as $status => $expected) {
            $entry = new form_entry(entries::create_entry($this->projetvet->id, $student->id, [], $status, 'facetoface'));
            $this->assertSame($expected, $entry->can_delete($student->id), "Status $status");
        }
    }

    /**
     * Deleting a project deletes its data, its subset entries and its uploaded files.
     */
    public function test_delete_entry_deletes_everything(): void {
        global $DB;

        $project = $this->create_project(2);
        $projectid = $project->get('id');

        // A case notebook entry (subset) of the project.
        $casefield = form_field::get_record(['idnumber' => 'case_animal_name']);
        $caseid = entries::create_entry(
            $this->projetvet->id,
            $this->users['student']->id,
            [$casefield->get('id') => 'Rex'],
            0,
            'carnet_cas',
            $projectid
        );

        // An uploaded document of the project.
        $documentsfield = form_field::get_record(['idnumber' => 'documents']);
        $itemid = $projectid * 1000 + $documentsfield->get('id');
        $context = \context_module::instance($this->projetvet->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_projetvet',
            'filearea' => 'entry_files',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'report.txt',
        ], 'Report');
        $data = new form_data();
        $data->set('fieldid', $documentsfield->get('id'));
        $data->set('entryid', $projectid);
        $data->set_value($itemid);
        $data->create();

        $this->setUser($this->users['editingteacher']);
        $this->assertTrue(entries::delete_entry($projectid));

        $this->assertFalse($DB->record_exists(form_entry::TABLE, ['id' => $projectid]));
        $this->assertFalse($DB->record_exists(form_entry::TABLE, ['id' => $caseid]));
        $this->assertEquals(0, $DB->count_records_select(
            form_data::TABLE,
            'entryid IN (:project, :case)',
            ['project' => $projectid, 'case' => $caseid]
        ));
        $this->assertTrue(get_file_storage()->is_area_empty($context->id, 'mod_projetvet', 'entry_files', $itemid));
    }

    /**
     * Through the web service, another student cannot delete a validated project, the student and a teacher can.
     */
    public function test_delete_entry_web_service(): void {
        global $DB;

        $project = $this->create_project(4);

        $this->setUser($this->users['otherstudent']);
        try {
            delete_entry::execute($project->get('id'));
            $this->fail('Another student must not delete the project');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotdeleteactivity', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists(form_entry::TABLE, ['id' => $project->get('id')]));

        foreach (['student', 'teacher'] as $user) {
            $project = $this->create_project(4);
            $this->setUser($this->users[$user]);
            $this->assertTrue(delete_entry::execute($project->get('id')), $user);
            $this->assertFalse($DB->record_exists(form_entry::TABLE, ['id' => $project->get('id')]), $user);
        }
    }
}
