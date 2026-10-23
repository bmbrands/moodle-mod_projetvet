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

namespace mod_projetvet\privacy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/substitution_test_setup.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_projetvet\local\persistent\substitution;

/**
 * Privacy provider tests for the substitute tutors.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class substitutions_provider_test extends provider_testcase {
    use \substitution_test_setup;

    /** @var \context_module The module context */
    private \context_module $context;

    /**
     * Set up the substitution scenario with two substitutions.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_substitution_scenario();
        $this->context = \context_module::instance($this->projetvet->cmid);
        $this->substitute([$this->students[1]], $this->substitutes[1]);
        $this->substitute([$this->students[2]], $this->substitutes[2]);
    }

    /**
     * The substitutions table is declared.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_projetvet'));
        $tables = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('projetvet_substitutions', $tables);
    }

    /**
     * The students, tutors and the manager involved in substitutions have data in the context.
     */
    public function test_contexts_and_users(): void {
        foreach (
            ['student' => $this->students[1], 'primary' => $this->primary, 'substitute' => $this->substitutes[1],
                'manager' => $this->manager] as $role => $user
        ) {
            $this->assertContains(
                (int) $this->context->id,
                array_map('intval', provider::get_contexts_for_userid($user->id)->get_contextids()),
                $role
            );
        }
        $this->assertNotContains(
            (int) $this->context->id,
            array_map('intval', provider::get_contexts_for_userid($this->substitutes[3]->id)->get_contextids())
        );

        $userlist = new userlist($this->context, 'mod_projetvet');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            [
                $this->students[1]->id, $this->students[2]->id, $this->primary->id,
                $this->substitutes[1]->id, $this->substitutes[2]->id, $this->manager->id,
            ],
            $userlist->get_userids()
        );
    }

    /**
     * The substitutions of a user are exported.
     */
    public function test_export(): void {
        $this->export_context_data_for_user($this->substitutes[1]->id, $this->context, 'mod_projetvet');
        $data = writer::with_context($this->context)->get_data(
            [get_string('privacy:metadata:projetvet_substitutions', 'mod_projetvet')]
        );
        $this->assertCount(1, $data->substitutions);
        $this->assertEquals('Sick leave', $data->substitutions[0]->reason);
    }

    /**
     * Deleting a user deletes their substitutions as student or tutor and anonymises the others.
     */
    public function test_delete_for_user(): void {
        global $DB;

        provider::delete_data_for_user(new approved_contextlist($this->students[1], 'mod_projetvet', [$this->context->id]));
        $this->assertEquals(0, $DB->count_records(substitution::TABLE, ['studentid' => $this->students[1]->id]));
        $this->assertEquals(1, $DB->count_records(substitution::TABLE, ['studentid' => $this->students[2]->id]));

        provider::delete_data_for_user(new approved_contextlist($this->manager, 'mod_projetvet', [$this->context->id]));
        $this->assertEquals(1, $DB->count_records(substitution::TABLE, ['createdby' => 0]));

        $userlist = new approved_userlist($this->context, 'mod_projetvet', [$this->substitutes[2]->id]);
        provider::delete_data_for_users($userlist);
        $this->assertEquals(0, $DB->count_records(substitution::TABLE));
    }

    /**
     * Deleting all data of the context deletes the substitutions.
     */
    public function test_delete_all_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records(substitution::TABLE, ['projetvetid' => $this->projetvet->id]));
    }
}
