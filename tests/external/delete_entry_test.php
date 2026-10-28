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

namespace mod_projetvet\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/test_data_definition.php');

use advanced_testcase;
use core_user;
use mod_projetvet\local\persistent\form_entry;
use test_data_definition;

/**
 * Delete entry external API test
 *
 * @package     mod_projetvet
 * @copyright   2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(delete_entry::class)]
final class delete_entry_test extends advanced_testcase {
    use test_data_definition;

    /**
     * Setup test data.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_scenario('set_1');
    }

    /**
     * Test successful deletion by student owner.
     */
    public function test_delete_entry_by_owner(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get an entry for student1.
        $entries = form_entry::get_records(['studentid' => $student->id, 'projetvetid' => $module->id]);
        $this->assertNotEmpty($entries, 'Should have entries for student1');
        $entry = reset($entries);
        $entryid = $entry->get('id');

        // Set as the student owner.
        $this->setUser($student);

        // Delete the entry.
        $result = delete_entry::execute($entryid);

        $this->assertTrue($result, 'Deletion should return true');

        // Verify entry is deleted.
        $deletedentry = form_entry::get_record(['id' => $entryid]);
        $this->assertFalse($deletedentry, 'Entry should be deleted from database');
    }

    /**
     * Test deletion fails with invalid entry ID.
     */
    public function test_delete_entry_invalid_id(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Activity entry not found');

        delete_entry::execute(99999);
    }

    /**
     * Test deletion requires proper context validation.
     */
    public function test_delete_entry_validates_context(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get an entry for student1.
        $entries = form_entry::get_records(['studentid' => $student->id, 'projetvetid' => $module->id]);
        $entry = reset($entries);
        $entryid = $entry->get('id');

        // Create a user with no access to the course.
        $nonaccessuser = $this->getDataGenerator()->create_user();
        $this->setUser($nonaccessuser);

        $this->expectException(\moodle_exception::class);
        delete_entry::execute($entryid);
    }

    /**
     * Test deletion respects can_delete permission.
     */
    public function test_delete_entry_respects_permissions(): void {
        global $DB;

        $student1 = core_user::get_user_by_username('student1');
        $student2 = core_user::get_user_by_username('student2');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get an entry belonging to student1.
        $entries = form_entry::get_records(['studentid' => $student1->id, 'projetvetid' => $module->id]);
        $entry = reset($entries);
        $entryid = $entry->get('id');

        // Try to delete as student2 (different user).
        $this->setUser($student2);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('You cannot delete this activity');

        delete_entry::execute($entryid);
    }

    /**
     * Test deletion by teacher with proper capability.
     */
    public function test_delete_entry_by_teacher(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $teacher = core_user::get_user_by_username('teacher1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get an entry for student1.
        $entries = form_entry::get_records(['studentid' => $student->id, 'projetvetid' => $module->id]);
        $entry = reset($entries);
        $entryid = $entry->get('id');

        // Set as teacher.
        $this->setUser($teacher);

        // Teachers can delete the projects of the students they can view, at any status.
        $this->assertTrue(delete_entry::execute($entryid));
        $this->assertFalse(form_entry::get_record(['id' => $entryid]));
    }

    /**
     * Test return value structure.
     */
    public function test_delete_entry_return_structure(): void {
        global $DB;

        $student = core_user::get_user_by_username('student1');
        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);

        // Get an entry for student1.
        $entries = form_entry::get_records(['studentid' => $student->id, 'projetvetid' => $module->id]);
        $entry = reset($entries);
        $entryid = $entry->get('id');

        $this->setUser($student);

        $result = delete_entry::execute($entryid);

        // Verify return type.
        $this->assertIsBool($result, 'Result should be boolean');
    }
}
