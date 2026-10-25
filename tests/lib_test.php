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
 * Tests for Projetvet
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test create and delete module.
     *
     * @covers ::projetvet_add_instance
     * @covers ::projetvet_delete_instance
     */
    public function test_create_delete_module(): void {
        global $DB;
        $this->resetAfterTest();

        // Disable recycle bin so we are testing module deletion and not backup.
        set_config('coursebinenable', 0, 'tool_recyclebin');

        // Create an instance of a module.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('projetvet', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('projetvet', $mod->id);

        // Assert it was created.
        $this->assertNotEmpty(\context_module::instance($mod->cmid));
        $this->assertEquals($mod->id, $cm->instance);
        $this->assertEquals('projetvet', $cm->modname);
        $this->assertEquals(1, $DB->count_records('projetvet', ['id' => $mod->id]));
        $this->assertEquals(1, $DB->count_records('course_modules', ['id' => $cm->id]));

        // Delete module.
        (new \core_courseformat\local\cmactions($course))->delete($cm->id);
        $this->assertEquals(0, $DB->count_records('projetvet', ['id' => $mod->id]));
        $this->assertEquals(0, $DB->count_records('course_modules', ['id' => $cm->id]));
    }

    /**
     * Test module backup and restore by duplicating it.
     *
     * @covers \backup_projetvet_activity_structure_step
     * @covers \restore_projetvet_activity_structure_step
     */
    public function test_backup_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Createa a module.
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module(
            'projetvet',
            ['course' => $course->id, 'name' => 'My test module']
        );
        $cm = get_coursemodule_from_instance('projetvet', $mod->id);

        // Call duplicate_module - it will backup and restore this module.
        $cmnew = (new \core_courseformat\local\cmactions($course))->duplicate($cm->id);

        $this->assertNotNull($cmnew);
        $this->assertGreaterThan($cm->id, $cmnew->id);
        $this->assertGreaterThan($mod->id, $cmnew->instance);
        $this->assertEquals('projetvet', $cmnew->modname);

        $name = $DB->get_field('projetvet', 'name', ['id' => $cmnew->instance]);
        $this->assertEquals('My test module (copy)', $name);
    }

    /**
     * Test the privacy boundary for viewing a student's data.
     *
     * A student may only view their own data, while users with the viewallactivities
     * capability (teachers, tutors and managers) may view any student's data.
     *
     * @covers ::projetvet_user_can_view_student
     */
    public function test_user_can_view_student(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $mod = $generator->create_module('projetvet', ['course' => $course->id]);
        $context = \context_module::instance($mod->cmid);

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $teacher = $generator->create_user();

        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // A user may always view their own data.
        $this->assertTrue(projetvet_user_can_view_student($student1->id, $student1->id, $context));
        $this->assertTrue(projetvet_user_can_view_student($teacher->id, $teacher->id, $context));

        // A student may not view another student's data.
        $this->assertFalse(projetvet_user_can_view_student($student1->id, $student2->id, $context));

        // A teacher (viewallactivities) may view a student's data.
        $this->assertTrue(projetvet_user_can_view_student($teacher->id, $student1->id, $context));
        $this->assertTrue(projetvet_user_can_view_student($teacher->id, $student2->id, $context));
    }

    /**
     * Test the tutor detection helpers (global and project-scoped).
     *
     * A tutor is the primary owner of a group. A leftover secondary tutor membership (replaced
     * by the substitute tutors) does not make a user a tutor.
     *
     * @covers \mod_projetvet\utils::is_tutor
     * @covers \mod_projetvet\utils::is_tutor_for_project
     */
    public function test_utils_is_tutor(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $mod1 = $generator->create_module('projetvet', ['course' => $course->id]);
        $mod2 = $generator->create_module('projetvet', ['course' => $course->id]);

        $owner = $generator->create_user();
        $secondary = $generator->create_user();
        $outsider = $generator->create_user();

        // A group in the first project, owned by the owner.
        $group = new \mod_projetvet\local\persistent\projetvet_group(0, (object)[
            'projetvetid' => $mod1->id,
            'name' => 'Test group',
            'ownerid' => $owner->id,
        ]);
        $group->create();

        // A leftover secondary tutor membership of the group.
        $DB->insert_record(\mod_projetvet\local\persistent\group_member::TABLE, (object)[
            'groupid' => $group->get('id'),
            'userid' => $secondary->id,
            'membertype' => 'secondary_tutor',
        ]);

        // Global tutor detection: the owner is a tutor, the secondary tutor and the outsider are not.
        $this->assertTrue(\mod_projetvet\utils::is_tutor($owner->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor($secondary->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor($outsider->id));

        // Project-scoped tutor detection.
        $this->assertTrue(\mod_projetvet\utils::is_tutor_for_project($owner->id, $mod1->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor_for_project($secondary->id, $mod1->id));
        // The tutor has no group in the second project.
        $this->assertFalse(\mod_projetvet\utils::is_tutor_for_project($owner->id, $mod2->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor_for_project($secondary->id, $mod2->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor_for_project($outsider->id, $mod1->id));
    }
}
