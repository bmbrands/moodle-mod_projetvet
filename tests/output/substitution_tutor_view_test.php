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

namespace mod_projetvet\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/substitution_test_setup.php');

use core_renderer;
use mod_projetvet\external\send_message;
use mod_projetvet\local\api\substitutions;
use mod_projetvet\reportbuilder\local\systemreports\students;
use mod_projetvet\utils;

/**
 * Tests for the tutor side of the substitutions: student lists, tools and the student page.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(student_info::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(students::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(substitutions::class)]
final class substitution_tutor_view_test extends \advanced_testcase {
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
     * The substitute follows the substituted students, the principal tutor keeps all their students.
     */
    public function test_students_followed_by_tutor(): void {
        $projetvetid = $this->projetvet->id;
        $this->substitute([$this->students[1]], $this->substitutes[1]);

        $this->assertEquals(
            [$this->students[1]->id],
            substitutions::get_students_followed_by_tutor($this->substitutes[1]->id, $projetvetid)
        );
        $this->assertEqualsCanonicalizing(
            array_column($this->students, 'id'),
            substitutions::get_students_followed_by_tutor($this->primary->id, $projetvetid)
        );
        $this->assertEmpty(substitutions::get_students_followed_by_tutor($this->substitutes[2]->id, $projetvetid));

        // A substitute is a tutor of the activity while the substitution is open.
        $this->assertTrue(utils::is_tutor($this->substitutes[1]->id));
        $this->assertTrue(utils::is_tutor_for_project($this->substitutes[1]->id, $projetvetid));
        $this->assertFalse(utils::is_tutor($this->substitutes[2]->id));
        $this->assertFalse(utils::is_tutor_for_project($this->substitutes[2]->id, $projetvetid));
    }

    /**
     * The tutoring column distinguishes principal and substitution students.
     */
    public function test_tutoring_label(): void {
        $projetvetid = $this->projetvet->id;
        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->substitute([$this->students[2]], $this->substitutes[2], ['startdate' => time() + WEEKSECS]);

        // The substitute sees the replaced principal tutor.
        $label = students::get_tutoring_label($this->students[1]->id, $projetvetid, $this->substitutes[1]->id);
        $this->assertStringContainsString(get_string('substitution_tutoring_badge', 'mod_projetvet'), $label);
        $this->assertStringContainsString(fullname($this->primary), $label);

        // The principal tutor sees the active substitute of their student.
        $label = students::get_tutoring_label($this->students[1]->id, $projetvetid, $this->primary->id);
        $this->assertStringContainsString(get_string('substitution_tutoring_primary', 'mod_projetvet'), $label);
        $this->assertStringContainsString(fullname($this->substitutes[1]), $label);

        // A planned substitution is shown with its start date.
        $label = students::get_tutoring_label($this->students[2]->id, $projetvetid, $this->primary->id);
        $this->assertStringContainsString(fullname($this->substitutes[2]), $label);
        $this->assertStringContainsString(
            get_string('substitution_period_from', 'mod_projetvet', userdate(
                time() + WEEKSECS,
                get_string('strftimedatefullshort', 'langconfig')
            )),
            $label
        );

        // Without substitution: principal tutor for them, the principal tutor's name for the others.
        $this->assertEquals(
            get_string('substitution_tutoring_primary', 'mod_projetvet'),
            students::get_tutoring_label($this->students[3]->id, $projetvetid, $this->primary->id)
        );
        $this->assertEquals(
            fullname($this->primary),
            students::get_tutoring_label($this->students[3]->id, $projetvetid, $this->manager->id)
        );
    }

    /**
     * The student page shows the substitute next to the principal tutor.
     */
    public function test_student_info_row(): void {
        $cm = get_coursemodule_from_id('projetvet', $this->projetvet->cmid);
        $context = \context_module::instance($cm->id);
        $renderer = new core_renderer(new \moodle_page(), \RENDERER_TARGET_GENERAL);
        $this->setUser($this->students[1]);

        $labels = fn(array $data) => array_column($data['infotable']['rows'], 'value', 'label');
        $data = (new student_info($this->projetvet, $cm, $context, $this->students[1]->id, false))
            ->export_for_template($renderer);
        $this->assertArrayNotHasKey(get_string('substitution_substitute', 'mod_projetvet'), $labels($data));

        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $data = (new student_info($this->projetvet, $cm, $context, $this->students[1]->id, false))
            ->export_for_template($renderer);
        $rows = $labels($data);
        $this->assertEquals(fullname($this->primary), $rows[get_string('tutor', 'mod_projetvet')]);
        $this->assertStringContainsString(
            fullname($this->substitutes[1]),
            $rows[get_string('substitution_substitute', 'mod_projetvet')]
        );
    }

    /**
     * The substitute can contact the students they follow, and only those.
     */
    public function test_substitute_can_message_followed_students(): void {
        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->setUser($this->substitutes[1]);

        $result = send_message::execute(
            $this->projetvet->cmid,
            $this->projetvet->id,
            [],
            'contactactivity',
            'contactactivity'
        );
        $this->assertEquals(1, $result->count);

        $this->expectException(\moodle_exception::class);
        send_message::execute(
            $this->projetvet->cmid,
            $this->projetvet->id,
            [$this->students[2]->id],
            'contactactivity',
            'contactactivity'
        );
    }
}
