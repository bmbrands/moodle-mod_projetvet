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
require_once($CFG->dirroot . '/mod/projetvet/tests/archive_test_setup.php');

use core_external\external_api;
use mod_projetvet\local\api\archive;
use moodle_exception;

/**
 * Tests for the get_archive_entry web service.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_archive_entry::class)]
final class get_archive_entry_test extends \advanced_testcase {
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
     * Call the web service.
     *
     * @param int $studentid
     * @return array
     */
    private function call(int $studentid): array {
        $result = get_archive_entry::execute($this->projetvet->cmid, $studentid);
        return external_api::clean_returnvalue(get_archive_entry::execute_returns(), $result);
    }

    /**
     * The student's archive is created on first use and returned afterwards.
     */
    public function test_create_and_get(): void {
        $this->setUser($this->student);
        $first = $this->call($this->student->id);
        $this->assertEquals(archive::STATUS_DRAFT, $first['entrystatus']);
        $this->assertEquals($first['entryid'], $this->call($this->student->id)['entryid']);
        $this->assertEquals(
            $first['entryid'],
            archive::get_archive_entry($this->projetvet->id, $this->student->id)->get('id')
        );
    }

    /**
     * Students cannot open their archive once the function is disabled, administrators still can.
     */
    public function test_disabled_for_students(): void {
        set_config('archive_enabled', 0, 'mod_projetvet');

        $this->setUser($this->manager);
        $this->assertNotEmpty($this->call($this->student->id)['entryid']);

        $this->setUser($this->student);
        $this->expectException(moodle_exception::class);
        $this->call($this->student->id);
    }

    /**
     * A student cannot open the archive of another student.
     */
    public function test_other_student(): void {
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->projetvet->course), 'student');
        $this->setUser($other);

        $this->expectException(moodle_exception::class);
        $this->call($this->student->id);
    }
}
