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

namespace mod_projetvet\local\persistent;

/**
 * Tests for the substitution persistent.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(substitution::class)]
final class substitution_test extends \advanced_testcase {
    /**
     * Create a substitution record.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @param array $data Overridden fields
     * @return substitution
     */
    private function create_substitution(int $projetvetid, int $studentid, array $data = []): substitution {
        $substitution = new substitution(0, (object) ($data + [
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'primarytutorid' => 2,
            'substitutetutorid' => 3,
            'startdate' => time() - DAYSECS,
        ]));
        return $substitution->create();
    }

    /**
     * Open, in effect and overdue states.
     */
    public function test_states(): void {
        $this->resetAfterTest();
        $now = time();

        $substitution = $this->create_substitution(1, 10, ['startdate' => $now + DAYSECS]);
        $this->assertTrue($substitution->is_open());
        $this->assertFalse($substitution->is_in_effect($now));
        $this->assertTrue($substitution->is_in_effect($now + 2 * DAYSECS));
        $this->assertFalse($substitution->is_overdue($now));

        $substitution->set('enddate', $now - HOURSECS);
        $this->assertTrue($substitution->is_overdue($now));

        $substitution->set('status', substitution::STATUS_CLOSED);
        $this->assertFalse($substitution->is_open());
        $this->assertFalse($substitution->is_in_effect($now + 2 * DAYSECS));
        $this->assertFalse($substitution->is_overdue($now));
    }

    /**
     * Only the open substitution of the student in the instance is returned.
     */
    public function test_get_open_for_student(): void {
        $this->resetAfterTest();

        $this->assertNull(substitution::get_open_for_student(10, 1));

        $this->create_substitution(1, 10, ['status' => substitution::STATUS_CLOSED]);
        $this->create_substitution(2, 10);
        $this->assertNull(substitution::get_open_for_student(10, 1));

        $open = $this->create_substitution(1, 10);
        $this->assertEquals($open->get('id'), substitution::get_open_for_student(10, 1)->get('id'));
    }

    /**
     * The substitutions are deleted with the activity.
     */
    public function test_deleted_with_instance(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/projetvet/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $projetvet = $this->getDataGenerator()->create_module('projetvet', ['course' => $course->id]);
        $this->create_substitution($projetvet->id, 10);
        $this->create_substitution(999, 10);

        projetvet_delete_instance($projetvet->id);

        $this->assertEquals(0, $DB->count_records(substitution::TABLE, ['projetvetid' => $projetvet->id]));
        $this->assertEquals(1, $DB->count_records(substitution::TABLE, ['projetvetid' => 999]));
    }
}
