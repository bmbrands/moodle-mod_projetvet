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

namespace mod_projetvet\local\importer;

use mod_projetvet\local\api\groups;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Tests for group importer.
 *
 * @package   mod_projetvet
 * @category  test
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(group_importer::class)]
final class group_importer_test extends \advanced_testcase {
    /**
     * @var \stdClass
     */
    protected \stdClass $course;

    /**
     * @var \stdClass
     */
    protected \stdClass $projetvet;

    /**
     * @var \stdClass
     */
    protected \stdClass $teacher1;

    /**
     * @var \stdClass
     */
    protected \stdClass $teacher2;

    /**
     * @var \stdClass
     */
    protected \stdClass $student1;

    /**
     * @var \stdClass
     */
    protected \stdClass $student2;

    /**
     * Set up test data.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->projetvet = $generator->create_module('projetvet', ['course' => $this->course->id]);

        $this->teacher1 = $generator->create_user(['username' => 'teacher1']);
        $this->teacher2 = $generator->create_user(['username' => 'teacher2']);
        $this->student1 = $generator->create_user(['username' => 'student1']);
        $this->student2 = $generator->create_user(['username' => 'student2']);

        $generator->enrol_user($this->teacher1->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->teacher2->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->student1->id, $this->course->id, 'student');
        $generator->enrol_user($this->student2->id, $this->course->id, 'student');
    }

    /**
     * Test import with semicolon delimiter and encoding parameter.
     */
    public function test_import_with_delimiter_and_encoding(): void {
        $cm = get_coursemodule_from_instance('projetvet', $this->projetvet->id, $this->course->id, false, MUST_EXIST);
        $filepath = make_request_directory() . '/groups_import_semicolon.csv';
        $csvcontent = implode(';', ['teacher', 'teacherrating', 'secondaryteacher', 'student1', 'student2'])
            . "\n" .
            implode(
                ';',
                [
                    $this->teacher1->username, 'novice',
                    $this->teacher2->username, $this->student1->username,
                    $this->student2->username,
                ]
            ) . "\n";
        file_put_contents($filepath, $csvcontent);

        $importer = new group_importer($this->course->id, $cm->id, $this->projetvet->id);
        $importer->import($filepath, 'semicolon', 'UTF-8');

        $groups = projetvet_group::get_by_owner($this->teacher1->id, $this->projetvet->id);
        $this->assertCount(1, $groups);
        $group = reset($groups);

        $students = group_member::get_records([
            'groupid' => $group->get('id'),
            'membertype' => group_member::TYPE_STUDENT,
        ]);
        $this->assertCount(2, $students);
        $studentids = array_map(static function ($member): int {
            return $member->get('userid');
        }, $students);
        $this->assertContains((int) $this->student1->id, $studentids);
        $this->assertContains((int) $this->student2->id, $studentids);

        // The secondaryteacher column of older files is ignored.
        $this->assertEmpty(group_member::get_records([
            'groupid' => $group->get('id'),
            'userid' => $this->teacher2->id,
        ]));

        $rating = teacher_rating::get_user_rating($this->teacher1->id, $this->projetvet->id);
        $this->assertNotNull($rating);
        $this->assertEquals(teacher_rating::RATING_NOVICE, $rating->get('rating'));
    }

    /**
     * Test invalid CSV structure raises an exception.
     */
    public function test_import_with_invalid_csv_structure(): void {
        $cm = get_coursemodule_from_instance('projetvet', $this->projetvet->id, $this->course->id, false, MUST_EXIST);
        $filepath = make_request_directory() . '/invalid_groups_import.csv';
        file_put_contents($filepath, "teacherrating,student1\nnovice,student1\n");

        $importer = new group_importer($this->course->id, $cm->id, $this->projetvet->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidcsvstructure', 'mod_projetvet'));
        $importer->import($filepath, 'comma', 'UTF-8');
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
     * Test the import refuses a new A1 student for a tutor set to "no", and that the whole
     * file is rolled back (nothing is written) when any row is rejected.
     */
    public function test_import_refuses_a1_for_no_acceptance(): void {
        global $DB;

        $cm = get_coursemodule_from_instance('projetvet', $this->projetvet->id, $this->course->id, false, MUST_EXIST);

        groups::set_teacher_a1_acceptance(
            $this->teacher1->id,
            $this->projetvet->id,
            teacher_rating::ACCEPTS_A1_NO
        );
        // Student1 is A1 (rejected), student2 is not A1 (would be accepted on its own).
        $this->set_profile_field($this->student1, 'promotion', 'A1');

        $filepath = make_request_directory() . '/groups_import_a1_rejected.csv';
        $csvcontent = implode(',', ['teacher', 'teacherrating', 'secondaryteacher', 'student1', 'student2'])
            . "\n" .
            implode(
                ',',
                [
                    $this->teacher1->username, 'novice', '',
                    $this->student1->username, $this->student2->username,
                ]
            ) . "\n";
        file_put_contents($filepath, $csvcontent);

        $importer = new group_importer($this->course->id, $cm->id, $this->projetvet->id);

        $exception = null;
        try {
            $importer->import($filepath, 'comma', 'UTF-8');
        } catch (\moodle_exception $e) {
            $exception = $e;
        }

        // The import is refused with the number of rejected A1 assignments.
        $this->assertNotNull($exception);
        $this->assertSame('a1importrejected', $exception->errorcode);

        // All-or-nothing: no group and no members were written to the database. The pre-existing
        // rating (set in setup) is left untouched by the rolled-back import.
        $this->assertSame([], array_keys(projetvet_group::get_by_owner($this->teacher1->id, $this->projetvet->id)));
        $this->assertEquals(
            0,
            $DB->count_records('projetvet_group_members', ['userid' => $this->student2->id])
        );
        $rating = teacher_rating::get_user_rating($this->teacher1->id, $this->projetvet->id);
        $this->assertNotNull($rating);
        $this->assertEquals(teacher_rating::ACCEPTS_A1_NO, $rating->get('acceptsa1'));
    }

    /**
     * Test the import accepts a new A1 student for an eligible tutor set to "yes".
     */
    public function test_import_accepts_a1_for_yes_acceptance(): void {
        $cm = get_coursemodule_from_instance('projetvet', $this->projetvet->id, $this->course->id, false, MUST_EXIST);

        groups::set_teacher_a1_acceptance(
            $this->teacher1->id,
            $this->projetvet->id,
            teacher_rating::ACCEPTS_A1_YES
        );
        $this->set_profile_field($this->student1, 'promotion', 'A1');

        $filepath = make_request_directory() . '/groups_import_a1_accepted.csv';
        $csvcontent = implode(',', ['teacher', 'teacherrating', 'secondaryteacher', 'student1', 'student2'])
            . "\n" .
            implode(
                ',',
                [
                    $this->teacher1->username, 'novice', '',
                    $this->student1->username, $this->student2->username,
                ]
            ) . "\n";
        file_put_contents($filepath, $csvcontent);

        $importer = new group_importer($this->course->id, $cm->id, $this->projetvet->id);
        $importer->import($filepath, 'comma', 'UTF-8');

        $groups = projetvet_group::get_by_owner($this->teacher1->id, $this->projetvet->id);
        $this->assertCount(1, $groups);
        $group = reset($groups);

        $students = group_member::get_records([
            'groupid' => $group->get('id'),
            'membertype' => group_member::TYPE_STUDENT,
        ]);
        $studentids = array_map(static function ($member): int {
            return $member->get('userid');
        }, $students);
        $this->assertContains((int) $this->student1->id, $studentids);
        $this->assertContains((int) $this->student2->id, $studentids);
    }
}
