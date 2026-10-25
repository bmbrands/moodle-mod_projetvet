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
 */
#[\PHPUnit\Framework\Attributes\CoversClass(groups::class)]
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
        $group->add_member($data['student2']->id, group_member::TYPE_PRIMARY_TUTOR);

        // Get only students.
        $students = groups::get_user_memberships($data['student1']->id, $data['projetvet']->id, group_member::TYPE_STUDENT);
        $this->assertCount(1, $students);
        $this->assertTrue($students[0]->is_student());

        // Get only tutors.
        $tutors = groups::get_user_memberships(
            $data['student2']->id,
            $data['projetvet']->id,
            group_member::TYPE_PRIMARY_TUTOR
        );
        $this->assertCount(1, $tutors);
        $this->assertTrue($tutors[0]->is_tutor());

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
     * Test that a tutor without a settings record is open by default.
     */
    public function test_teacher_status_defaults_to_active_open(): void {
        $data = $this->create_test_data();

        $this->assertSame(
            teacher_rating::STATUS_ACTIVE_OPEN,
            groups::get_teacher_status($data['teacher']->id, $data['projetvet']->id)
        );
        $this->assertTrue(groups::is_teacher_open_for_new_assignments(
            $data['teacher']->id,
            $data['projetvet']->id
        ));

        $rating = teacher_rating::get_or_create_rating($data['teacher']->id, $data['projetvet']->id);
        $rating->create();

        $this->assertSame(teacher_rating::STATUS_ACTIVE_OPEN, $rating->get_availability_status());
    }

    /**
     * Test that the tutor status can be changed independently from the rating.
     */
    public function test_set_teacher_status(): void {
        $data = $this->create_test_data();

        groups::set_teacher_rating(
            $data['teacher']->id,
            $data['projetvet']->id,
            teacher_rating::RATING_EXPERT
        );
        groups::set_teacher_status(
            $data['teacher']->id,
            $data['projetvet']->id,
            teacher_rating::STATUS_ACTIVE_CLOSED
        );

        $rating = teacher_rating::get_user_rating($data['teacher']->id, $data['projetvet']->id);
        $this->assertSame(teacher_rating::STATUS_ACTIVE_CLOSED, $rating->get_availability_status());
        $this->assertSame(teacher_rating::RATING_EXPERT, $rating->get('rating'));
    }

    /**
     * Test that an unknown tutor availability status is rejected.
     */
    public function test_unknown_teacher_status_is_rejected(): void {
        $data = $this->create_test_data();
        $rating = teacher_rating::get_or_create_rating($data['teacher']->id, $data['projetvet']->id);
        $rating->set('status', 'unknown');

        $this->expectException(\core\invalid_persistent_exception::class);
        $rating->create();
    }

    /**
     * Test that changing status preserves assignments, groups, rating and capacity.
     */
    public function test_teacher_status_change_preserves_existing_data(): void {
        $data = $this->create_test_data();
        $rating = teacher_rating::get_or_create_rating($data['teacher']->id, $data['projetvet']->id);
        $rating->set('rating', teacher_rating::RATING_EXPERT);
        $rating->create();

        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        $capacity = groups::get_teacher_available_capacity($data['teacher']->id, $data['projetvet']->id);
        $rating->set('status', teacher_rating::STATUS_INACTIVE);
        $rating->update();

        $updatedrating = teacher_rating::get_user_rating($data['teacher']->id, $data['projetvet']->id);
        $this->assertNotNull($updatedrating);
        $this->assertSame(teacher_rating::STATUS_INACTIVE, $updatedrating->get_availability_status());
        $this->assertSame(teacher_rating::RATING_EXPERT, $updatedrating->get('rating'));
        $this->assertSame($capacity, groups::get_teacher_available_capacity(
            $data['teacher']->id,
            $data['projetvet']->id
        ));
        $this->assertCount(2, groups::get_students_for_tutor(
            $data['teacher']->id,
            $data['projetvet']->id
        ));
        $this->assertFalse(groups::is_teacher_open_for_new_assignments(
            $data['teacher']->id,
            $data['projetvet']->id
        ));

        groups::set_teacher_rating(
            $data['teacher']->id,
            $data['projetvet']->id,
            teacher_rating::RATING_NOVICE
        );
        $updatedrating = teacher_rating::get_user_rating($data['teacher']->id, $data['projetvet']->id);
        $this->assertSame(teacher_rating::STATUS_INACTIVE, $updatedrating->get_availability_status());
        $this->assertSame(teacher_rating::RATING_NOVICE, $updatedrating->get('rating'));
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
     * Test sync_group_students updates only students and keeps the primary tutor untouched.
     */
    public function test_sync_group_students_preserves_primary_tutor(): void {
        $data = $this->create_test_data();

        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['teacher']->id, group_member::TYPE_PRIMARY_TUTOR);
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);
        $group->add_member($data['student2']->id, group_member::TYPE_STUDENT);

        // Keep student2 and add student3.
        groups::sync_group_students($group->get('id'), [$data['student2']->id, $data['student3']->id]);

        $studentids = array_map(fn($m) => (int) $m->get('userid'), $group->get_members(group_member::TYPE_STUDENT));
        $tutors = array_values($group->get_members(group_member::TYPE_PRIMARY_TUTOR));

        $this->assertEqualsCanonicalizing([(int) $data['student2']->id, (int) $data['student3']->id], $studentids);
        $this->assertCount(1, $tutors);
        $this->assertEquals($data['teacher']->id, $tutors[0]->get('userid'));
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

        $group2members = array_values($group2->get_members(group_member::TYPE_STUDENT));
        $this->assertCount(1, $group2members);
        $this->assertEquals($data['student1']->id, $group2members[0]->get('userid'));
    }

    /**
     * Test assign_students_to_teacher creates the teacher group when it does not exist yet.
     */
    public function test_assign_students_to_teacher_creates_group(): void {
        $data = $this->create_test_data();

        $assigned = groups::assign_students_to_teacher(
            $data['teacher']->id,
            [$data['student1']->id, $data['student2']->id],
            $data['projetvet']->id
        );

        $this->assertEquals(2, $assigned);

        // The teacher now owns a group containing the two students.
        $groups = projetvet_group::get_by_owner($data['teacher']->id, $data['projetvet']->id);
        $this->assertCount(1, $groups);
        $group = reset($groups);
        $members = array_values($group->get_members(group_member::TYPE_STUDENT));
        $this->assertCount(2, $members);
        $memberids = array_map(fn($m) => (int) $m->get('userid'), $members);
        $this->assertContains((int) $data['student1']->id, $memberids);
        $this->assertContains((int) $data['student2']->id, $memberids);

        // The students resolve to this teacher as their primary tutor.
        $tutor = groups::get_student_primary_tutor($data['student1']->id, $data['projetvet']->id);
        $this->assertNotNull($tutor);
        $this->assertEquals($data['teacher']->id, $tutor->id);
    }

    /**
     * Test assign_students_to_teacher reuses the teacher's existing group.
     */
    public function test_assign_students_to_teacher_reuses_group(): void {
        $data = $this->create_test_data();

        // Pre-create the teacher group with one student.
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        // Assign another student to the same teacher.
        groups::assign_students_to_teacher($data['teacher']->id, [$data['student2']->id], $data['projetvet']->id);

        // Still a single group, now containing both students.
        $groups = projetvet_group::get_by_owner($data['teacher']->id, $data['projetvet']->id);
        $this->assertCount(1, $groups);
        $group = new projetvet_group($group->get('id'));
        $memberids = array_map(fn($m) => (int) $m->get('userid'), array_values($group->get_members(group_member::TYPE_STUDENT)));
        $this->assertContains((int) $data['student1']->id, $memberids);
        $this->assertContains((int) $data['student2']->id, $memberids);
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
     * Test a leftover secondary tutor membership (replaced by the substitute tutors) gives no
     * access to the students of the group, and cannot be created anymore.
     */
    public function test_legacy_secondary_tutor_has_no_access(): void {
        global $DB;

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
        $DB->insert_record(group_member::TABLE, (object) [
            'groupid' => $group->get('id'),
            'userid' => $teacher2->id,
            'membertype' => 'secondary_tutor',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertEmpty(groups::get_students_for_tutor($teacher2->id, $data['projetvet']->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor($teacher2->id));
        $this->assertFalse(\mod_projetvet\utils::is_tutor_for_project($teacher2->id, $data['projetvet']->id));

        $this->expectException(\core\invalid_persistent_exception::class);
        $group->add_member($teacher2->id, 'secondary_tutor');
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

    /**
     * Test get_all_students_join built against a custom user id column (as the reportbuilder
     * report uses), proving the fragment is coherent, executable and consistent with the
     * array-based source of truth.
     */
    public function test_get_all_students_join_with_custom_alias(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        $join = groups::get_all_students_join($cm->id, 'rbalias0.id');

        $this->assertNotSame('', $join->joins);
        $this->assertStringContainsString('rbalias0', $join->joins);

        // Every parameter referenced in the fragment must be provided (otherwise the query is broken).
        preg_match_all('/:(\w+)/', $join->joins . ' ' . $join->wheres, $matches);
        $missing = array_diff($matches[1], array_keys($join->params));
        $this->assertSame([], array_values($missing));

        // The fragment must run against a query that aliases {user} as rbalias0 (not "u").
        $rows = $DB->get_records_sql(
            "SELECT rbalias0.id
               FROM {user} rbalias0
               {$join->joins}
              WHERE {$join->wheres}",
            $join->params
        );

        $joinedids = array_keys($rows);
        sort($joinedids);

        $expected = [$data['student1']->id, $data['student2']->id, $data['student3']->id];
        sort($expected);
        $this->assertEquals($expected, $joinedids);
        $this->assertNotContains($data['teacher']->id, $joinedids);

        // Must be identical to the array-based source of truth.
        $expectedfromarray = array_column(groups::get_all_students($cm->id), 'uniqueid');
        sort($expectedfromarray);
        $this->assertEquals($expectedfromarray, $joinedids);
    }

    /**
     * Test get_all_teachers_join built against a custom user id column (as the reportbuilder
     * report uses), proving the fragment is coherent and consistent with the array variant.
     */
    public function test_get_all_teachers_join_with_custom_alias(): void {
        global $DB;

        $data = $this->create_test_data();
        $cm = get_coursemodule_from_instance('projetvet', $data['projetvet']->id);

        $join = groups::get_all_teachers_join($cm->id, 'rbalias0.id');

        $this->assertNotSame('', $join->joins);
        $this->assertStringContainsString('rbalias0', $join->joins);

        // The fragment must run against a query that aliases {user} as rbalias0 (not "u").
        $rows = $DB->get_records_sql(
            "SELECT rbalias0.id
               FROM {user} rbalias0
               {$join->joins}
              WHERE {$join->wheres}",
            $join->params
        );

        $joinedids = array_keys($rows);
        $this->assertEquals([$data['teacher']->id], $joinedids);

        // Must be identical to the array-based source of truth.
        $teachers = groups::get_all_teachers($cm->id);
        sort($teachers);
        sort($joinedids);
        $this->assertEquals($teachers, $joinedids);
    }

    /**
     * Set the value of a custom profile field for a user.
     *
     * @param \stdClass $user The user.
     * @param string $shortname The profile field shortname.
     * @param string $value The profile field value.
     */
    protected function set_profile_field(\stdClass $user, string $shortname, string $value): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            $field = (object)[
                'shortname' => $shortname,
                'name' => $shortname,
                'datatype' => 'text',
                'categoryid' => 0,
                'sortorder' => 0,
                'visible' => 1,
            ];
            $field->id = $DB->insert_record('user_info_field', $field);
        }

        $DB->delete_records('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
        $DB->insert_record('user_info_data', (object)[
            'userid' => $user->id,
            'fieldid' => $field->id,
            'data' => $value,
        ]);
    }

    /**
     * Test the A1 acceptance defaults to "yes" for an existing tutor with no rating record.
     */
    public function test_a1_acceptance_defaults_to_yes(): void {
        $data = $this->create_test_data();

        // No rating record at all.
        $acceptsa1 = \mod_projetvet\local\persistent\teacher_rating::get_a1_acceptance_for(
            $data['teacher']->id,
            $data['projetvet']->id
        );

        $this->assertEquals(\mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_YES, $acceptsa1);
    }

    /**
     * Test the A1 acceptance persists when set to "no" and is read back.
     */
    public function test_set_teacher_a1_acceptance_persists(): void {
        $data = $this->create_test_data();

        groups::set_teacher_a1_acceptance(
            $data['teacher']->id,
            $data['projetvet']->id,
            \mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_NO
        );

        $acceptsa1 = \mod_projetvet\local\persistent\teacher_rating::get_a1_acceptance_for(
            $data['teacher']->id,
            $data['projetvet']->id
        );
        $this->assertEquals(\mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_NO, $acceptsa1);

        // Toggle back to "yes".
        groups::set_teacher_a1_acceptance(
            $data['teacher']->id,
            $data['projetvet']->id,
            \mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_YES
        );
        $acceptsa1 = \mod_projetvet\local\persistent\teacher_rating::get_a1_acceptance_for(
            $data['teacher']->id,
            $data['projetvet']->id
        );
        $this->assertEquals(\mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_YES, $acceptsa1);
    }

    /**
     * Test a new A1 assignment is refused for a tutor set to "no".
     */
    public function test_check_a1_assignment_eligibility_refusesa1(): void {
        $data = $this->create_test_data();

        groups::set_teacher_a1_acceptance(
            $data['teacher']->id,
            $data['projetvet']->id,
            \mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_NO
        );

        $eligibility = groups::check_a1_assignment_eligibility($data['teacher']->id, $data['projetvet']->id, 1);

        $this->assertFalse($eligibility['allowed']);
        $this->assertEquals(get_string('a1assignmentblocked_refusesa1', 'mod_projetvet'), $eligibility['reason']);
    }

    /**
     * Test a new A1 assignment is refused when the tutor has no available capacity.
     */
    public function test_check_a1_assignment_eligibility_capacity(): void {
        $data = $this->create_test_data();

        // Novice = capacity 5.
        $rating = teacher_rating::get_or_create_rating($data['teacher']->id, $data['projetvet']->id);
        $rating->set('rating', teacher_rating::RATING_NOVICE);
        $rating->create();

        // Fill the tutor to capacity (5 students).
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $generator = $this->getDataGenerator();
        for ($i = 1; $i <= 5; $i++) {
            $student = $generator->create_user(['username' => 'capstudent' . $i]);
            $generator->enrol_user($student->id, $data['course']->id, 'student');
            $group->add_member($student->id, group_member::TYPE_STUDENT);
        }

        $eligibility = groups::check_a1_assignment_eligibility($data['teacher']->id, $data['projetvet']->id, 1);

        $this->assertFalse($eligibility['allowed']);
        $this->assertEquals(get_string('a1assignmentblocked_capacity', 'mod_projetvet'), $eligibility['reason']);
    }

    /**
     * Test a new A1 assignment is allowed for an eligible tutor set to "yes" with capacity.
     */
    public function test_check_a1_assignment_eligibility_allowed(): void {
        $data = $this->create_test_data();

        $eligibility = groups::check_a1_assignment_eligibility($data['teacher']->id, $data['projetvet']->id, 1);

        $this->assertTrue($eligibility['allowed']);
        $this->assertSame('', $eligibility['reason']);
    }

    /**
     * Test get_new_a1_student_ids returns only the new A1 students in a batch.
     */
    public function test_get_new_a1_student_ids(): void {
        $data = $this->create_test_data();

        // Mark student1 as A1, student2 as A2.
        $this->set_profile_field($data['student1'], 'promotion', 'A1');
        $this->set_profile_field($data['student2'], 'promotion', 'A2');

        // Pre-assign student1 to the teacher (existing, not new).
        $group = new projetvet_group(0, (object)[
            'projetvetid' => $data['projetvet']->id,
            'ownerid' => $data['teacher']->id,
            'name' => 'Test Group',
        ]);
        $group->create();
        $group->add_member($data['student1']->id, group_member::TYPE_STUDENT);

        $newa1 = groups::get_new_a1_student_ids(
            $data['teacher']->id,
            $data['projetvet']->id,
            [$data['student1']->id, $data['student2']->id, $data['student3']->id]
        );

        // Student1 is A1 but already assigned (not new), student2 is A2, student3 has no promotion.
        $this->assertNotContains((int) $data['student1']->id, $newa1);
        $this->assertNotContains((int) $data['student2']->id, $newa1);
        $this->assertNotContains((int) $data['student3']->id, $newa1);

        // Now a fresh A1 student should be detected.
        $newstudent = $this->getDataGenerator()->create_user(['username' => 'newa1']);
        $this->set_profile_field($newstudent, 'promotion', 'A1');
        $newa1 = groups::get_new_a1_student_ids($data['teacher']->id, $data['projetvet']->id, [$newstudent->id]);
        $this->assertContains((int) $newstudent->id, $newa1);
    }

    /**
     * Test that switching the A1 field to "no" does not remove existing A1 assignments.
     */
    public function test_existing_a1_assignments_kept_when_field_switched_to_no(): void {
        $data = $this->create_test_data();

        $this->set_profile_field($data['student1'], 'promotion', 'A1');

        // Assign student1 to the teacher.
        groups::assign_students_to_teacher($data['teacher']->id, [$data['student1']->id], $data['projetvet']->id);

        // Switch the field to "no".
        groups::set_teacher_a1_acceptance(
            $data['teacher']->id,
            $data['projetvet']->id,
            \mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_NO
        );

        // The existing assignment is kept.
        $tutor = groups::get_student_primary_tutor($data['student1']->id, $data['projetvet']->id);
        $this->assertNotNull($tutor);
        $this->assertEquals($data['teacher']->id, $tutor->id);

        // But a new A1 assignment is now refused.
        $newa1 = $this->getDataGenerator()->create_user(['username' => 'newa1']);
        $this->getDataGenerator()->enrol_user($newa1->id, $data['course']->id, 'student');
        $this->set_profile_field($newa1, 'promotion', 'A1');
        $eligibility = groups::check_a1_assignment_eligibility($data['teacher']->id, $data['projetvet']->id, 1);
        $this->assertFalse($eligibility['allowed']);
    }

    /**
     * Test a non-A1 assignment is not refused on the basis of the A1 field alone.
     */
    public function test_non_a1_assignment_not_blocked_by_a1_field(): void {
        $data = $this->create_test_data();

        groups::set_teacher_a1_acceptance(
            $data['teacher']->id,
            $data['projetvet']->id,
            \mod_projetvet\local\persistent\teacher_rating::ACCEPTS_A1_NO
        );

        // A non-A1 student (no promotion field) is not subject to the A1 rule.
        $newstudent = $this->getDataGenerator()->create_user(['username' => 'nona1']);
        $this->getDataGenerator()->enrol_user($newstudent->id, $data['course']->id, 'student');

        $newa1 = groups::get_new_a1_student_ids($data['teacher']->id, $data['projetvet']->id, [$newstudent->id]);
        $this->assertSame([], $newa1);
    }
}
