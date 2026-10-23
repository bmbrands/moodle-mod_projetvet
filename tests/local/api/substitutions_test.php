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
require_once($CFG->dirroot . '/mod/projetvet/tests/substitution_test_setup.php');

use mod_projetvet\local\persistent\substitution;
use mod_projetvet\local\persistent\teacher_rating;
use moodle_exception;

/**
 * Tests for the temporary substitute tutors API.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(substitutions::class)]
final class substitutions_test extends \advanced_testcase {
    use \substitution_test_setup;

    /**
     * Set up the substitution scenario.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_substitution_scenario();
    }

    /**
     * Students of the same principal tutor can have different substitutes, the principal
     * assignment is never overwritten.
     */
    public function test_create_per_student(): void {
        $projetvetid = $this->projetvet->id;
        $this->substitute([$this->students[1], $this->students[2]], $this->substitutes[1]);
        $this->substitute([$this->students[3]], $this->substitutes[2]);

        foreach ([1 => 1, 2 => 1, 3 => 2] as $student => $substitute) {
            $open = substitutions::get_open_substitution($this->students[$student]->id, $projetvetid);
            $this->assertEquals($this->substitutes[$substitute]->id, $open->get('substitutetutorid'));
            $this->assertEquals($this->primary->id, $open->get('primarytutorid'));
            $this->assertEquals('Sick leave', $open->get('reason'));
            $this->assertEquals($this->manager->id, $open->get('createdby'));
            $this->assertEquals(
                $this->substitutes[$substitute]->id,
                substitutions::get_active_tutor($this->students[$student]->id, $projetvetid)->id
            );
            // The principal tutor stays recorded.
            $this->assertEquals(
                $this->primary->id,
                groups::get_student_primary_tutor($this->students[$student]->id, $projetvetid)->id
            );
        }

        // The fourth student is still followed by the principal tutor.
        $this->assertNull(substitutions::get_open_substitution($this->students[4]->id, $projetvetid));
        $this->assertEquals($this->primary->id, substitutions::get_active_tutor($this->students[4]->id, $projetvetid)->id);
        $this->assertCount(3, substitutions::get_open_substitutions_for_primary($this->primary->id, $projetvetid));
        $this->assertCount(2, substitutions::get_open_substitutions_for_substitute($this->substitutes[1]->id, $projetvetid));
        $this->assertEquals(4, groups::get_primary_student_count($this->primary->id, $projetvetid));
    }

    /**
     * A student can only have one open substitution.
     */
    public function test_one_open_substitution_per_student(): void {
        $this->substitute([$this->students[1]], $this->substitutes[1]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(fullname($this->substitutes[1]));
        $this->substitute([$this->students[1]], $this->substitutes[2]);
    }

    /**
     * Data provider for test_status_rule.
     *
     * @return array
     */
    public static function status_provider(): array {
        return [
            'Active and open' => [teacher_rating::STATUS_ACTIVE_OPEN, true],
            'Active and closed' => [teacher_rating::STATUS_ACTIVE_CLOSED, false],
            'Temporarily unavailable' => [teacher_rating::STATUS_TEMPORARILY_UNAVAILABLE, false],
            'Inactive' => [teacher_rating::STATUS_INACTIVE, false],
        ];
    }

    /**
     * Only tutors open to new assignments can be substitutes.
     *
     * @param int $status The substitute status
     * @param bool $allowed Whether the substitution is allowed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('status_provider')]
    public function test_status_rule(int $status, bool $allowed): void {
        groups::set_teacher_status($this->substitutes[1]->id, $this->projetvet->id, $status);

        $check = substitutions::check_batch(
            $this->projetvet->cmid,
            $this->projetvet->id,
            [$this->students[1]->id],
            $this->substitutes[1]->id
        );
        $this->assertSame(!$allowed, substitutions::has_problems($check));
        $this->assertSame($allowed, empty($check['tutor']));
    }

    /**
     * The substitute must be a tutor of the activity, other than the principal tutor, and the
     * student must have a principal tutor.
     */
    public function test_people_rules(): void {
        $cmid = $this->projetvet->cmid;
        $projetvetid = $this->projetvet->id;

        $check = substitutions::check_batch($cmid, $projetvetid, [$this->students[1]->id], $this->students[2]->id);
        $this->assertNotEmpty($check['tutor']);

        $check = substitutions::check_batch($cmid, $projetvetid, [$this->students[1]->id], $this->primary->id);
        $this->assertArrayHasKey($this->students[1]->id, $check['students']);

        $check = substitutions::check_batch($cmid, $projetvetid, [$this->unassigned->id], $this->substitutes[1]->id);
        $this->assertEquals(
            [get_string('substitution_error_noprimary', 'mod_projetvet')],
            $check['students'][$this->unassigned->id]
        );
    }

    /**
     * An A1 student can only be followed by a substitute accepting A1 students.
     */
    public function test_a1_rule(): void {
        $this->set_profile_field($this->students[1], 'promotion', 'A1');
        $this->set_profile_field($this->students[2], 'promotion', 'A2');
        groups::set_teacher_a1_acceptance($this->substitutes[1]->id, $this->projetvet->id, teacher_rating::ACCEPTS_A1_NO);

        $check = substitutions::check_batch(
            $this->projetvet->cmid,
            $this->projetvet->id,
            [$this->students[1]->id, $this->students[2]->id],
            $this->substitutes[1]->id
        );
        $this->assertEquals([$this->students[1]->id], array_keys($check['students']));

        $check = substitutions::check_batch(
            $this->projetvet->cmid,
            $this->projetvet->id,
            [$this->students[1]->id],
            $this->substitutes[2]->id
        );
        $this->assertFalse(substitutions::has_problems($check));
    }

    /**
     * The substitute load counts principal students and open substitutions; an overrun must be
     * accepted explicitly.
     */
    public function test_capacity_rule(): void {
        $projetvetid = $this->projetvet->id;
        $substitute = $this->substitutes[1];
        groups::set_teacher_rating($substitute->id, $projetvetid, teacher_rating::RATING_NOVICE);

        // Three principal students of their own.
        $own = [];
        for ($i = 0; $i < 3; $i++) {
            $own[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        }
        groups::assign_students_to_teacher($substitute->id, $own, $projetvetid);
        $this->assertEquals(2, substitutions::get_available_capacity($substitute->id, $projetvetid));

        $this->substitute([$this->students[1]], $substitute);
        $this->assertEquals(4, substitutions::get_tutor_load($substitute->id, $projetvetid));
        $this->assertEquals(1, substitutions::get_available_capacity($substitute->id, $projetvetid));

        $students = [$this->students[2]->id, $this->students[3]->id];
        $check = substitutions::check_batch($this->projetvet->cmid, $projetvetid, $students, $substitute->id);
        $this->assertNotEquals('', $check['capacity']);
        $this->assertEmpty($check['students']);

        $check = substitutions::check_batch($this->projetvet->cmid, $projetvetid, $students, $substitute->id, true);
        $this->assertFalse(substitutions::has_problems($check));

        $this->substitute([$this->students[2], $this->students[3]], $substitute, ['allowovercapacity' => true]);
        $this->assertEquals(-1, substitutions::get_available_capacity($substitute->id, $projetvetid));
    }

    /**
     * A batch is saved completely or not at all.
     */
    public function test_batch_is_atomic(): void {
        $this->set_profile_field($this->students[2], 'promotion', 'A1');
        groups::set_teacher_a1_acceptance($this->substitutes[1]->id, $this->projetvet->id, teacher_rating::ACCEPTS_A1_NO);

        try {
            $this->substitute([$this->students[1], $this->students[2], $this->unassigned], $this->substitutes[1]);
            $this->fail('The batch must be refused');
        } catch (moodle_exception $e) {
            $this->assertEquals('substitutionnotallowed', $e->errorcode);
            $this->assertStringContainsString(fullname($this->students[2]), $e->getMessage());
            $this->assertStringContainsString(fullname($this->unassigned), $e->getMessage());
        }
        $this->assertEmpty(substitutions::get_open_substitutions_for_primary($this->primary->id, $this->projetvet->id));
    }

    /**
     * The planned end date cannot be before the start date.
     */
    public function test_dates(): void {
        $this->expectException(moodle_exception::class);
        $this->substitute([$this->students[1]], $this->substitutes[1], [
            'startdate' => time(),
            'enddate' => time() - DAYSECS,
        ]);
    }

    /**
     * A planned substitution is only in effect from its start date.
     */
    public function test_planned_substitution(): void {
        $projetvetid = $this->projetvet->id;
        $this->substitute([$this->students[1]], $this->substitutes[1], ['startdate' => time() + WEEKSECS]);

        $this->assertNotNull(substitutions::get_open_substitution($this->students[1]->id, $projetvetid));
        $this->assertNull(substitutions::get_substitution_in_effect($this->students[1]->id, $projetvetid));
        $later = time() + 2 * WEEKSECS;
        $this->assertNotNull(substitutions::get_substitution_in_effect($this->students[1]->id, $projetvetid, $later));
        $this->assertEquals($this->primary->id, substitutions::get_active_tutor($this->students[1]->id, $projetvetid)->id);
    }

    /**
     * Closing returns the student to the principal tutor and keeps the history.
     */
    public function test_close(): void {
        $projetvetid = $this->projetvet->id;
        $created = $this->substitute([$this->students[1], $this->students[2]], $this->substitutes[1]);

        $this->setUser($this->manager);
        $closed = substitutions::close_substitutions($projetvetid, [$created[$this->students[1]->id]->get('id')]);
        $this->assertCount(1, $closed);

        $substitution = new substitution($created[$this->students[1]->id]->get('id'));
        $this->assertEquals(substitution::STATUS_CLOSED, $substitution->get('status'));
        $this->assertEquals($this->manager->id, $substitution->get('closedby'));
        $this->assertGreaterThan(0, $substitution->get('timeclosed'));
        $this->assertEquals($this->primary->id, substitutions::get_active_tutor($this->students[1]->id, $projetvetid)->id);
        $this->assertEquals($this->substitutes[1]->id, substitutions::get_active_tutor($this->students[2]->id, $projetvetid)->id);

        // Closing again is ignored, a new substitution can be created and the history is kept.
        $this->assertEmpty(substitutions::close_substitutions($projetvetid, [$substitution->get('id')]));
        $this->substitute([$this->students[1]], $this->substitutes[2]);
        $history = substitutions::get_history_for_student($this->students[1]->id, $projetvetid);
        $this->assertCount(2, $history);
        $this->assertCount(3, substitutions::get_history_for_primary($this->primary->id, $projetvetid));
    }

    /**
     * Extending changes the planned end date of open substitutions.
     */
    public function test_extend(): void {
        $projetvetid = $this->projetvet->id;
        $start = time() - DAYSECS;
        $created = $this->substitute([$this->students[1]], $this->substitutes[1], [
            'startdate' => $start,
            'enddate' => $start + WEEKSECS,
        ]);
        $id = $created[$this->students[1]->id]->get('id');

        substitutions::extend_substitutions($projetvetid, [$id], $start + 2 * WEEKSECS);
        $this->assertEquals($start + 2 * WEEKSECS, (new substitution($id))->get('enddate'));

        try {
            substitutions::extend_substitutions($projetvetid, [$id], $start - DAYSECS);
            $this->fail('The end date cannot be before the start date');
        } catch (moodle_exception $e) {
            $this->assertEquals('substitution_error_dates', $e->errorcode);
        }

        substitutions::close_substitutions($projetvetid, [$id]);
        $this->assertEmpty(substitutions::extend_substitutions($projetvetid, [$id], $start + 3 * WEEKSECS));
    }

    /**
     * A substitution can be turned into a definitive change of principal tutor.
     */
    public function test_make_definitive(): void {
        $projetvetid = $this->projetvet->id;
        $created = $this->substitute([$this->students[1]], $this->substitutes[1]);
        $id = $created[$this->students[1]->id]->get('id');

        substitutions::make_definitive($projetvetid, [$id]);

        $this->assertEquals(substitution::STATUS_DEFINITIVE, (new substitution($id))->get('status'));
        $this->assertEquals(
            $this->substitutes[1]->id,
            groups::get_student_primary_tutor($this->students[1]->id, $projetvetid)->id
        );
        $this->assertNull(substitutions::get_open_substitution($this->students[1]->id, $projetvetid));
        $this->assertEquals(3, groups::get_primary_student_count($this->primary->id, $projetvetid));
    }

    /**
     * Substitutions of another instance are ignored.
     */
    public function test_other_instance_ignored(): void {
        $created = $this->substitute([$this->students[1]], $this->substitutes[1]);
        $id = $created[$this->students[1]->id]->get('id');

        $this->assertEmpty(substitutions::close_substitutions($this->projetvet->id + 1, [$id]));
        $this->assertNull(substitutions::get_open_substitution($this->students[1]->id, $this->projetvet->id + 1));
        $this->assertTrue((new substitution($id))->is_open());
    }
}
