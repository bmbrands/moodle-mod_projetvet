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

namespace mod_projetvet\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/substitution_test_setup.php');

use mod_projetvet\local\api\groups;
use mod_projetvet\local\api\substitutions;
use mod_projetvet\local\persistent\teacher_rating;
use mod_projetvet\output\substitutions_page;

/**
 * Tests for the substitute tutors management forms and page.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(substitution_create_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(substitution_manage_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(substitutions_page::class)]
final class substitution_forms_test extends \advanced_testcase {
    use \substitution_test_setup;

    /**
     * Set up the substitution scenario.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_substitution_scenario();
        $this->setUser($this->manager);
    }

    /**
     * Build the creation form for the principal tutor.
     *
     * @return substitution_create_form
     */
    private function create_form(): substitution_create_form {
        $projetvetid = $this->projetvet->id;
        $open = substitutions::get_open_substitutions_for_primary($this->primary->id, $projetvetid);
        return new substitution_create_form(null, [
            'cmid' => $this->projetvet->cmid,
            'projetvetid' => $projetvetid,
            'tutorid' => $this->primary->id,
            'students' => array_diff_key(substitutions::get_primary_students($this->primary->id, $projetvetid), $open),
            'candidates' => substitutions::get_candidate_tutors($this->projetvet->cmid, $projetvetid, $this->primary->id),
        ]);
    }

    /**
     * Submitted creation form data.
     *
     * @param array $studentids
     * @param int $substituteid
     * @param array $data Overridden values
     * @return array
     */
    private function create_data(array $studentids, int $substituteid, array $data = []): array {
        $students = [];
        foreach ($this->students as $student) {
            $students[$student->id] = in_array($student->id, $studentids) ? 1 : 0;
        }
        return $data + [
            'id' => $this->projetvet->cmid,
            'tutorid' => $this->primary->id,
            'students' => $students,
            'substitutetutorid' => $substituteid,
            'startdate' => usergetmidnight(time()),
            'enddate' => 0,
            'reason' => 'Sick leave',
            'notifyprimary' => 0,
            'allowovercapacity' => 0,
        ];
    }

    /**
     * The students of the principal tutor and the candidate substitutes are listed.
     */
    public function test_lists(): void {
        $students = substitutions::get_primary_students($this->primary->id, $this->projetvet->id);
        $this->assertEqualsCanonicalizing(array_column($this->students, 'id'), array_keys($students));

        groups::set_teacher_status($this->substitutes[3]->id, $this->projetvet->id, teacher_rating::STATUS_INACTIVE);
        $candidates = substitutions::get_candidate_tutors($this->projetvet->cmid, $this->projetvet->id, $this->primary->id);
        $this->assertNotContains($this->primary->id, array_column($candidates, 'id'));
        $this->assertCount(3, $candidates);
        // Ineligible tutors come last.
        $last = end($candidates);
        $this->assertEquals($this->substitutes[3]->id, $last['id']);
        $this->assertFalse($last['eligible']);
    }

    /**
     * A valid batch passes the validation.
     */
    public function test_create_valid(): void {
        $data = $this->create_data([$this->students[1]->id, $this->students[2]->id], $this->substitutes[1]->id);
        $this->assertSame([], $this->create_form()->validation($data, []));
        $this->assertEquals(
            [$this->students[1]->id, $this->students[2]->id],
            substitution_create_form::get_selected_students($data)
        );
    }

    /**
     * Conflicts are reported next to each student, the substitute and the capacity.
     */
    public function test_create_conflicts(): void {
        $this->set_profile_field($this->students[2], 'promotion', 'A1');
        groups::set_teacher_a1_acceptance($this->substitutes[1]->id, $this->projetvet->id, teacher_rating::ACCEPTS_A1_NO);
        groups::set_teacher_rating($this->substitutes[1]->id, $this->projetvet->id, teacher_rating::RATING_NOVICE);

        // Nothing selected.
        $errors = $this->create_form()->validation($this->create_data([], $this->substitutes[1]->id), []);
        $this->assertCount(1, $errors);

        // A1 conflict on student 2 only, end date before start date.
        $data = $this->create_data([$this->students[1]->id, $this->students[2]->id], $this->substitutes[1]->id, [
            'enddate' => usergetmidnight(time()) - DAYSECS,
        ]);
        $errors = $this->create_form()->validation($data, []);
        $this->assertArrayHasKey('students[' . $this->students[2]->id . ']', $errors);
        $this->assertArrayNotHasKey('students[' . $this->students[1]->id . ']', $errors);
        $this->assertArrayHasKey('enddate', $errors);

        // Unavailable substitute.
        groups::set_teacher_status($this->substitutes[2]->id, $this->projetvet->id, teacher_rating::STATUS_TEMPORARILY_UNAVAILABLE);
        $errors = $this->create_form()->validation($this->create_data([$this->students[1]->id], $this->substitutes[2]->id), []);
        $this->assertArrayHasKey('substitutetutorid', $errors);

        // Capacity overrun (novice, 5 places) must be accepted explicitly.
        groups::set_teacher_rating($this->substitutes[3]->id, $this->projetvet->id, teacher_rating::RATING_NOVICE);
        $own = [];
        for ($i = 0; $i < 4; $i++) {
            $own[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id;
        }
        groups::assign_students_to_teacher($this->substitutes[3]->id, $own, $this->projetvet->id);
        $data = $this->create_data([$this->students[1]->id, $this->students[3]->id], $this->substitutes[3]->id);
        $this->assertArrayHasKey('allowovercapacity', $this->create_form()->validation($data, []));
        $data['allowovercapacity'] = 1;
        $this->assertSame([], $this->create_form()->validation($data, []));
    }

    /**
     * The manage form validates the selection and the new planned end date.
     */
    public function test_manage_form(): void {
        $start = usergetmidnight(time());
        $created = $this->substitute([$this->students[1]], $this->substitutes[1], ['startdate' => $start]);
        $id = $created[$this->students[1]->id]->get('id');
        $form = new substitution_manage_form(null, [
            'cmid' => $this->projetvet->cmid,
            'tutorid' => $this->primary->id,
            'substitutions' => array_values($created),
        ]);

        $errors = $form->validation(['substitutions' => [$id => 0], 'closebutton' => 1], []);
        $this->assertCount(1, $errors);

        $data = ['substitutions' => [$id => 1], 'closebutton' => 1];
        $this->assertSame([], $form->validation($data, []));
        $this->assertSame(substitution_manage_form::ACTION_CLOSE, substitution_manage_form::get_action($data));
        $this->assertSame([$id], substitution_manage_form::get_selected_substitutions($data));

        $errors = $form->validation(['substitutions' => [$id => 1], 'extendbutton' => 1, 'newenddate' => 0], []);
        $this->assertArrayHasKey('newenddate', $errors);
        $errors = $form->validation(['substitutions' => [$id => 1], 'extendbutton' => 1, 'newenddate' => $start - DAYSECS], []);
        $this->assertArrayHasKey('newenddate', $errors);
        $data = ['substitutions' => [$id => 1], 'extendbutton' => 1, 'newenddate' => $start + WEEKSECS];
        $this->assertSame([], $form->validation($data, []));
        $this->assertSame(substitution_manage_form::ACTION_EXTEND, substitution_manage_form::get_action($data));
    }

    /**
     * The page flags the return of the principal tutor and lists the history.
     */
    public function test_page_export(): void {
        global $PAGE;

        $cm = get_coursemodule_from_id('projetvet', $this->projetvet->cmid);
        $PAGE->set_url('/mod/projetvet/substitutions.php');
        $output = $PAGE->get_renderer('mod_projetvet');

        groups::set_teacher_status($this->primary->id, $this->projetvet->id, teacher_rating::STATUS_TEMPORARILY_UNAVAILABLE);
        $created = $this->substitute([$this->students[1], $this->students[2]], $this->substitutes[1]);
        substitutions::close_substitutions($this->projetvet->id, [$created[$this->students[2]->id]->get('id')]);

        $data = (new substitutions_page($cm, $this->primary, '', ''))->export_for_template($output);
        $this->assertArrayNotHasKey('returnmessage', $data);
        $this->assertCount(2, $data['history']);
        $this->assertTrue($data['hashistory']);

        // The principal tutor is available again while a substitution is still open.
        groups::set_teacher_status($this->primary->id, $this->projetvet->id, teacher_rating::STATUS_ACTIVE_OPEN);
        $data = (new substitutions_page($cm, $this->primary, '', ''))->export_for_template($output);
        $this->assertStringContainsString(fullname($this->primary), $data['returnmessage']);

        $html = $output->render_substitutions_page(new substitutions_page($cm, $this->primary, '', ''));
        $this->assertStringContainsString(fullname($this->students[1]), $html);
        $this->assertStringContainsString(get_string('substitution_status_closed', 'mod_projetvet'), $html);
    }
}
