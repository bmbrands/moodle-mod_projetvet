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

use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Groups API test
 *
 * @package   mod_projetvet
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_projetvet\local\api\groups
 */
final class groups_test extends \advanced_testcase {
    /**
     * Test setup - create course, users, module
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create test data
     *
     * @return array Contains course, projetvet, teacher, students
     */
    protected function create_test_data(): array {
        $generator = $this->getDataGenerator();

        // Create course.
        $course = $generator->create_course();

        // Create users.
        $teacher = $generator->create_user(['username' => 'teacher1']);
        $student1 = $generator->create_user(['username' => 'student1']);
        $student2 = $generator->create_user(['username' => 'student2']);
        $student3 = $generator->create_user(['username' => 'student3']);

        // Enrol users.
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');
        $generator->enrol_user($student3->id, $course->id, 'student');

        // Create projetvet instance.
        $projetvet = $generator->create_module('projetvet', ['course' => $course->id]);

        return [
            'course' => $course,
            'projetvet' => $projetvet,
            'teacher' => $teacher,
            'student1' => $student1,
            'student2' => $student2,
            'student3' => $student3,
        ];
    }

    /**
     * Test get_primary_student_count with no groups
     */
    public function test_get_primary_student_count_no_groups(): void {
        $data = $this->create_test_data();

        $count = groups::get_primary_student_count($data['teacher']->id, $data['projetvet']->id);

        $this->assertEquals(0, $count);
    }

    /**
     * Test get_primary_student_count with students
     */
    public function test_get_primary_student_count_with_students(): void {
        $data = $this->create_test_data();

        // Create group with teacher as owner.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();

        // Add students.
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        $count = groups::get_primary_student_count($data['teacher']->id, $data['projetvet']->id);

        $this->assertEquals(2, $count);
    }

    /**
     * Test get_student_groups
     */
    public function test_get_student_groups(): void {
        $data = $this->create_test_data();

        // Create group.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();

        // Add student.
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        $memberships = groups::get_student_groups($data['student1']->id, $data['projetvet']->id);

        $this->assertCount(1, $memberships);
        $this->assertEquals($group->get('id'), $memberships[0]->get('groupid'));
        $this->assertTrue($memberships[0]->is_student());
    }

    /**
     * Test get_user_memberships with different member types
     */
    public function test_get_user_memberships_filter_by_type(): void {
        $data = $this->create_test_data();

        // Create group.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();

        // Add members of different types.
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_SECONDARY_TUTOR);

        // Get only students.
        $students = groups::get_user_memberships($data['student1']->id, $data['projetvet']->id, group_member::TYPE_STUDENT);
        $this->assertCount(1, $students);
        $this->assertTrue($students[0]->is_student());

        // Get only tutors.
        $tutors = groups::get_user_memberships(
            $data['student2']->id,
            $data['projetvet']->id,
            group_member::TYPE_SECONDARY_TUTOR
        );
        $this->assertCount(1, $tutors);
        $this->assertTrue($tutors[0]->is_secondary_tutor());

        // Get all memberships for student1.
        $all = groups::get_user_memberships($data['student1']->id, $data['projetvet']->id);
        $this->assertCount(1, $all);
    }

    /**
     * Test get_teacher_available_capacity
     */
    public function test_get_teacher_available_capacity(): void {
        $data = $this->create_test_data();

        // Set teacher rating with capacity of 12 (expert).
        $rating = teacher_rating::get_or_create_rating($data['teacher']->id, $data['projetvet']->id);
        $rating->set('rating', teacher_rating::RATING_EXPERT);
        $rating->create();

        // Create group with 2 students.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        $capacity = groups::get_teacher_available_capacity($data['teacher']->id, $data['projetvet']->id);

        // Expert rating = 12 capacity, 2 students assigned = 10 available.
        $this->assertEquals(10, $capacity);
    }

    /**
     * Test get_available_teachers
     */
    public function test_get_available_teachers(): void {
        $data = $this->create_test_data();
        $generator = $this->getDataGenerator();

        // Create additional teacher.
        $teacher2 = $generator->create_user(['username' => 'teacher2', 'firstname' => 'Jane', 'lastname' => 'Doe']);
        $generator->enrol_user($teacher2->id, $data['course']->id, 'editingteacher');

        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        // Get available teachers excluding teacher1.
        $teachers = groups::get_available_teachers($cm->id, $data['teacher']->id);

        $this->assertCount(1, $teachers);
        $this->assertEquals($teacher2->id, $teachers[0]['uniqueid']);
        $this->assertStringContainsString('Jane', $teachers[0]['name']);
    }

    /**
     * Test get_available_students
     */
    public function test_get_available_students(): void {
        $data = $this->create_test_data();

        // Create group and assign student1.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        // Get available students (should exclude student1).
        $students = groups::get_available_students($cm->id, $data['projetvet']->id);

        $this->assertCount(2, $students);
        $studentids = array_column($students, 'uniqueid');
        $this->assertNotContains($data['student1']->id, $studentids);
        $this->assertContains($data['student2']->id, $studentids);
        $this->assertContains($data['student3']->id, $studentids);
    }

    /**
     * Test get_available_students with current group exclusion
     */
    public function test_get_available_students_with_current_group(): void {
        $data = $this->create_test_data();

        // Create group and assign student1.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        // Get available students allowing re-selection from current group.
        $students = groups::get_available_students($cm->id, $data['projetvet']->id, $group->get('id'));

        $this->assertCount(3, $students); // All 3 students available.
    }

    /**
     * Test add_members syncs correctly
     */
    public function test_add_members_sync(): void {
        $data = $this->create_test_data();

        // Create group.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();

        // Add initial students.
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        // Sync to remove student2 and add student3.
        groups::add_members($group->get('id'), [], [$data['student1']->id, $data['student3']->id]);

        // Re-fetch the group to get updated members.
        $group = new projetvet_group($group->get('id'));
        $members = $group->get_members(group_member::TYPE_STUDENT);
        $this->assertCount(2, $members);

        $memberids = array_map(fn($m) => $m->get('userid'), $members);
        $this->assertContains((int)$data['student1']->id, $memberids);
        $this->assertContains((int)$data['student3']->id, $memberids);
        $this->assertNotContains((int)$data['student2']->id, $memberids);
    }

    /**
     * Test sync_group_students updates only students and keeps secondary tutors untouched.
     */
    public function test_sync_group_students_preserves_secondary_tutors(): void {
        $data = $this->create_test_data();
        $generator = $this->getDataGenerator();

        $teacher2 = $generator->create_user(['username' => 'teacher2']);
        $generator->enrol_user($teacher2->id, $data['course']->id, 'editingteacher');

        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);
        $group->add_member($teacher2->id, group_member::TYPE_SECONDARY_TUTOR);

        // Keep only student2.
        groups::sync_group_students($group->get('id'), [$data['student2']->id]);

        $students = $group->get_members(group_member::TYPE_STUDENT);
        $secondary = $group->get_members(group_member::TYPE_SECONDARY_TUTOR);

        $this->assertCount(1, $students);
        $this->assertEquals($data['student2']->id, $students[0]->get('userid'));
        $this->assertCount(1, $secondary);
        $this->assertEquals($teacher2->id, $secondary[0]->get('userid'));
    }

    /**
     * Test sync_group_secondary_tutors updates only secondary tutors and keeps students untouched.
     */
    public function test_sync_group_secondary_tutors_preserves_students(): void {
        $data = $this->create_test_data();
        $generator = $this->getDataGenerator();

        $teacher2 = $generator->create_user(['username' => 'teacher2']);
        $teacher3 = $generator->create_user(['username' => 'teacher3']);
        $generator->enrol_user($teacher2->id, $data['course']->id, 'editingteacher');
        $generator->enrol_user($teacher3->id, $data['course']->id, 'editingteacher');

        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);
        $group->add_member($teacher2->id, group_member::TYPE_SECONDARY_TUTOR);

        // Replace teacher2 by teacher3.
        groups::sync_group_secondary_tutors($group->get('id'), [$teacher3->id]);

        $students = $group->get_members(group_member::TYPE_STUDENT);
        $secondary = $group->get_members(group_member::TYPE_SECONDARY_TUTOR);

        $this->assertCount(2, $students);
        $studentids = array_map(fn($m) => $m->get('userid'), $students);
        $this->assertContains((int)$data['student1']->id, $studentids);
        $this->assertContains((int)$data['student2']->id, $studentids);
        $this->assertCount(1, $secondary);
        $this->assertEquals($teacher3->id, $secondary[0]->get('userid'));
    }

    /**
     * Test get_student_primary_tutor
     */
    public function test_get_student_primary_tutor(): void {
        $data = $this->create_test_data();

        // Create group.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        $tutor = groups::get_student_primary_tutor($data['student1']->id, $data['projetvet']->id);

        $this->assertNotNull($tutor);
        $this->assertEquals($data['teacher']->id, $tutor->id);
    }

    /**
     * Test get_student_primary_tutor when student not in group
     */
    public function test_get_student_primary_tutor_no_group(): void {
        $data = $this->create_test_data();

        $tutor = groups::get_student_primary_tutor($data['student1']->id, $data['projetvet']->id);

        $this->assertNull($tutor);
    }

    /**
     * Test get_student_secondary_tutors
     */
    public function test_get_student_secondary_tutors(): void {
        $data = $this->create_test_data();
        $generator = $this->getDataGenerator();

        // Create secondary tutor.
        $teacher2 = $generator->create_user(['username' => 'teacher2']);
        $generator->enrol_user($teacher2->id, $data['course']->id, 'editingteacher');

        // Create group.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($teacher2->id, group_member::TYPE_SECONDARY_TUTOR);

        $tutors = groups::get_student_secondary_tutors($data['student1']->id, $data['projetvet']->id);

        $this->assertCount(1, $tutors);
        $this->assertEquals($teacher2->id, $tutors[0]->id);
    }

    /**
     * Test assign_students_to_group removes from other groups
     */
    public function test_assign_students_to_group_removes_duplicates(): void {
        $data = $this->create_test_data();

        // Create two groups.
        $group1 = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Group 1',
        ]);
        $group1->create();

        $group2 = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Group 2',
        ]);
        $group2->create();

        // Assign student1 to group1.
        $group1->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        // Now assign student1 to group2 (should remove from group1).
        groups::assign_students_to_group($group2->get('id'), [$data['student1']->id], $data['projetvet']->id);

        // Check student1 is only in group2.
        $group1members = $group1->get_members(group_member::TYPE_STUDENT);
        $this->assertCount(0, $group1members);

        $group2members = $group2->get_members(group_member::TYPE_STUDENT);
        $this->assertCount(1, $group2members);
        $this->assertEquals($data['student1']->id, $group2members[0]->get('userid'));
    }

    /**
     * Test get_students_for_tutor as primary tutor
     */
    public function test_get_students_for_tutor_as_primary(): void {
        $data = $this->create_test_data();

        // Create group with teacher as owner.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        $studentids = groups::get_students_for_tutor($data['teacher']->id, $data['projetvet']->id);

        $this->assertCount(2, $studentids);
        $this->assertContains((int)$data['student1']->id, $studentids);
        $this->assertContains((int)$data['student2']->id, $studentids);
    }

    /**
     * Test get_students_for_tutor as secondary tutor
     */
    public function test_get_students_for_tutor_as_secondary(): void {
        $data = $this->create_test_data();
        $generator = $this->getDataGenerator();

        // Create secondary tutor.
        $teacher2 = $generator->create_user(['username' => 'teacher2']);
        $generator->enrol_user($teacher2->id, $data['course']->id, 'editingteacher');

        // Create group with teacher1 as owner.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($teacher2->id, group_member::TYPE_SECONDARY_TUTOR);

        // Get students for teacher2 (secondary tutor).
        $studentids = groups::get_students_for_tutor($teacher2->id, $data['projetvet']->id);

        $this->assertCount(1, $studentids);
        $this->assertContains((int)$data['student1']->id, $studentids);
    }

    /**
     * Test get_all_students excludes teachers
     */
    public function test_get_all_students_excludes_teachers(): void {
        $data = $this->create_test_data();

        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);
        $students = groups::get_all_students($cm->id);

        $this->assertCount(3, $students);
        $studentids = array_column($students, 'uniqueid');
        $this->assertNotContains($data['teacher']->id, $studentids);
    }
}
