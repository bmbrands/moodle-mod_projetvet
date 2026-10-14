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

namespace mod_projetvet\reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/projetvet/tests/archive_test_setup.php');

use core_reportbuilder\system_report_factory;
use mod_projetvet\local\api\archive;

/**
 * Tests for the entries (Projects) system report.
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(entries::class)]
final class entries_test extends \advanced_testcase {
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
     * Render the Projects report of the student as the current user.
     *
     * @return string
     */
    private function render_report(): string {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/mod/projetvet/view.php', ['id' => $this->projetvet->cmid]));
        $PAGE->set_context($this->context);
        $report = system_report_factory::create(entries::class, $this->context, 'mod_projetvet', 'entries', 1, [
            'cmid' => $this->projetvet->cmid,
            'projetvetid' => $this->projetvet->id,
            'studentid' => $this->student->id,
            'formsetidnumber' => 'activities',
        ]);
        return $report->output();
    }

    /**
     * Validated archived projects are listed as "Projet repris" summary records.
     */
    public function test_validated_archived_projects_listed(): void {
        $project = $this->project(3, 1);
        $project['archive_title'] = 'Stage rural 2023';
        $archive = $this->create_archive([$project], archive::STATUS_SUBMITTED);

        $this->setUser($this->student);
        $html = $this->render_report();
        $this->assertStringNotContainsString('Stage rural 2023', $html);

        $archive->set('entrystatus', archive::STATUS_VALIDATED);
        $archive->update();
        archive::sync_project_statuses($archive);

        foreach ([$this->student, $this->tutor] as $user) {
            $this->setUser($user);
            $html = $this->render_report();
            $this->assertStringContainsString('Stage rural 2023', $html);
            $this->assertStringContainsString(get_string('statusmsg_projetrepris', 'mod_projetvet'), $html);
            $this->assertStringContainsString('projetvet-archived-row', $html);
            // Archived projects cannot be opened, edited or deleted from the table.
            $this->assertStringNotContainsString('data-action="activity-entry-form"', $html);
            $this->assertStringNotContainsString('data-action="delete-activity"', $html);
        }
    }
}
