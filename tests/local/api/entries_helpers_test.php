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
require_once($CFG->dirroot . '/mod/projetvet/tests/test_data_definition.php');

use advanced_testcase;
use context_module;
use core_user;
use test_data_definition;

/**
 * Test helper permission methods in entries API.
 *
 * @package     mod_projetvet
 * @category    test
 * @copyright   2026 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_projetvet\local\api\entries
 */
final class entries_helpers_test extends advanced_testcase {
    use test_data_definition;

    /** @var \context_module */
    private $context;

    /**
     * Setup test data.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_scenario('set_1');

        $course = $DB->get_record('course', ['shortname' => 'course 1'], '*', MUST_EXIST);
        $module = $DB->get_record('projetvet', ['course' => $course->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('projetvet', $module->id);
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Test can_edit_field returns strict bool and expected value for repeated scenarios.
     *
     * @dataProvider can_edit_field_provider
     * @param string $userkey User key in fixture, or "nonaccess" for a user without capabilities.
     * @param string $capability Category capability value.
     * @param bool $canedit Category canedit value.
     * @param bool $expected Expected result.
     * @return void
     */
    public function test_can_edit_field_with_provider(
        string $userkey,
        string $capability,
        bool $canedit,
        bool $expected
    ): void {
        if ($userkey === 'nonaccess') {
            $user = $this->getDataGenerator()->create_user();
        } else {
            $user = core_user::get_user_by_username($userkey);
        }
        $this->setUser($user);

        $category = (object)[
            'capability' => $capability,
            'canedit' => $canedit,
        ];

        $result = entries::can_edit_field($category, $this->context);
        $this->assertIsBool($result);
        $this->assertSame($expected, $result);
    }

    /**
     * Data provider for can_edit_field scenarios.
     *
     * @return array
     */
    public static function can_edit_field_provider(): array {
        return [
            '"all" with no capability returns false' => ['nonaccess', 'all', true, false],
            '"all" with submit capability returns false' => ['student1', 'all', true, false],
            '"all" with approve capability returns true' => ['teacher1', 'all', false, true],
            '"submit" uses category canedit=true' => ['student1', 'submit', true, true],
            '"submit" uses category canedit=false' => ['student1', 'submit', false, false],
            '"alledit" always returns true' => ['nonaccess', 'alledit', false, true],
        ];
    }
}
