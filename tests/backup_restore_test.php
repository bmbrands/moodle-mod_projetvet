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

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Test backup and restore of a projetvet activity.
 *
 * @package mod_projetvet
 * @category backup
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_projetvet_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_projetvet_activity_structure_step::class)]
final class backup_restore_test extends advanced_testcase {
    /**
     * Test backup and restore of a projetvet activity.
     */
    public function test_backup_restore(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

                // Create a course with ProjetVet activity.
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('projetvet', [
            'course' => $course->id,
            'name' => 'Test ProjetVet Activity',
        ]);

        // Create a student and enroll them.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // Get the generator for the projetvet module.
        $projetvetgenerator = $this->getDataGenerator()->get_plugin_generator('mod_projetvet');

        // Add some form entries (you'll need to adapt this based on your data structure).
        // For now, let's just check if the basic structure is backed up and restored.

        // Count records before backup.
        $originalprojetvets = $DB->count_records('projetvet');
        $originalformsets = $DB->count_records('projetvet_form_set');
        $originalformcats = $DB->count_records('projetvet_form_cat');

        // Perform backup.
        $backupid = $this->backup_course($course);

        // Create new course and restore.
        $newcourseid = restore_dbops::create_new_course(
            $course->fullname . ' RESTORED',
            $course->shortname . '_RESTORED',
            $course->category
        );

        $this->restore_course($backupid, $newcourseid);

        // Verify the restoration.
        $restoredprojetvets = $DB->count_records('projetvet');
        $restoredformsets = $DB->count_records('projetvet_form_set');
        $restoredformcats = $DB->count_records('projetvet_form_cat');

        // Check that we have the expected number of records after restore.
        $this->assertEquals($originalprojetvets + 1, $restoredprojetvets, 'ProjetVet instances should be restored');

        // Check that configuration data wasn't duplicated (form sets should exist only once).
        $this->assertEquals($originalformsets, $restoredformsets, 'Form sets should not be duplicated');
        $this->assertEquals($originalformcats, $restoredformcats, 'Form categories should not be duplicated');

        // Check the restored projetvet instance.
        $restoredprojetvet = $DB->get_record('projetvet', ['course' => $newcourseid]);
        $this->assertNotEmpty($restoredprojetvet);
        $this->assertEquals('Test ProjetVet Activity', $restoredprojetvet->name);
    }

    /**
     * Test backup and restore with user data.
     */
    public function test_backup_restore_with_userdata(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create test course and module.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $projetvet = $generator->create_module('projetvet', [
            'course' => $course->id,
            'name' => 'ProjetVet with User Data',
        ]);

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        // Get the ProjetVet generator.
        $projetvetgenerator = $generator->get_plugin_generator('mod_projetvet');

        // Create some test data using the generator if formsets exist.
        $formsets = $DB->get_records('projetvet_form_set', [], '', '*', 0, 1);
        if (!empty($formsets)) {
            $formset = reset($formsets);

            // Create form entry for student1.
            $entry = $projetvetgenerator->create_form_entry([
                'projetvetid' => $projetvet->id,
                'formsetid' => $formset->id,
                'studentid' => $student1->id,
                'entrystatus' => 1, // Completed.
            ]);

            if ($entry) {
                // Get a field from this formset.
                $fields = $projetvetgenerator->get_form_fields($formset->id);
                if (!empty($fields)) {
                    $field = reset($fields);
                    $projetvetgenerator->create_form_data([
                        'fieldid' => $field->id,
                        'entryid' => $entry->id,
                        'textvalue' => 'Test data for backup/restore',
                    ]);
                }
            }
        }

        // Backup with user data.
        $backupid = $this->backup_course($course, true);

        // Restore to new course.
        $newcourseid = restore_dbops::create_new_course(
            'Restored Course with Data',
            'RESTORED_DATA',
            $course->category
        );

        $this->restore_course($backupid, $newcourseid);

        // Verify that user data was restored correctly.
        $newprojetvet = $DB->get_record('projetvet', ['course' => $newcourseid]);
        $this->assertNotEmpty($newprojetvet);

        // Check if form entries were restored (if formsets exist).
        $formentrycount = $DB->count_records('projetvet_form_entry', ['projetvetid' => $newprojetvet->id]);
        if (!empty($formsets)) {
            $this->assertGreaterThanOrEqual(1, $formentrycount, 'Form entries should be restored if created');
        }
    }

    /**
     * Test backup and restore of the substitute tutors with user data.
     */
    public function test_backup_restore_substitutions(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);
        $primary = $generator->create_and_enrol($course, 'editingteacher');
        $substitute = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        \mod_projetvet\local\api\groups::assign_students_to_teacher($primary->id, [$student->id], $projetvet->id);
        \mod_projetvet\local\api\substitutions::create_substitutions(
            $projetvet->cmid,
            $projetvet->id,
            [$student->id],
            $substitute->id,
            time() - DAYSECS,
            time() + WEEKSECS,
            'Sick leave'
        );

        $backupid = $this->backup_course($course, true);
        $newcourseid = restore_dbops::create_new_course('Restored substitutions', 'RESTORED_SUBS', $course->category);
        $this->restore_course($backupid, $newcourseid);

        $newprojetvet = $DB->get_record('projetvet', ['course' => $newcourseid], '*', MUST_EXIST);
        $restored = $DB->get_records('projetvet_substitutions', ['projetvetid' => $newprojetvet->id]);
        $this->assertCount(1, $restored);
        $restored = reset($restored);
        $this->assertEquals($student->id, $restored->studentid);
        $this->assertEquals($primary->id, $restored->primarytutorid);
        $this->assertEquals($substitute->id, $restored->substitutetutorid);
        $this->assertEquals('Sick leave', $restored->reason);
        $this->assertEquals(\mod_projetvet\local\persistent\substitution::STATUS_OPEN, $restored->status);
    }

    /**
     * Backup a course.
     *
     * @param object $course Course to backup
     * @param bool $userdata Include user data
     * @return string Backup ID
     */
    protected function backup_course($course, $userdata = false): string {
        global $CFG, $USER;

        // Turn off file logging.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        if ($userdata) {
            $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
            $bc->get_plan()->get_setting('users')->set_value(true);
        }

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restore a course.
     *
     * @param string $backupid Backup ID
     * @param int $courseid Target course ID
     */
    protected function restore_course(string $backupid, int $courseid): void {
        global $USER;

        $rc = new restore_controller(
            $backupid,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }
}
