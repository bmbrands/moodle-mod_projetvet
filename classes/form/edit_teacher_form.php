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

use context;
use context_module;
use core_form\dynamic_form;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Form for assigning a teacher to a student
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_teacher_form extends dynamic_form {
    /**
     * Get context for dynamic submission
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return context_module::instance($cmid);
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/projetvet:admin', $context);
    }

    /**
     * Process dynamic submission
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        global $DB;

        $data = $this->get_data();
        $studentidsJson = $data->studentids ?? '[]';
        $studentids = json_decode($studentidsJson, true);
        $projetvetid = $data->projetvetid;
        $teacherid = $data->teacherid ?? 0;

        if (empty($studentids) || !is_array($studentids)) {
            throw new \moodle_exception('nouseridprovided', 'mod_projetvet');
        }

        if (empty($teacherid)) {
            throw new \moodle_exception('selectteacher', 'mod_projetvet');
        }

        // Get or create teacher's group.
        $groups = projetvet_group::get_by_owner($teacherid, $projetvetid);
        if (empty($groups)) {
            $teacher = \core_user::get_user($teacherid, '*', MUST_EXIST);
            $group = new projetvet_group(0, (object)[
                'projetvetid' => $projetvetid,
                'name' => get_string('tutorgroupname', 'mod_projetvet', fullname($teacher)),
                'description' => '',
                'ownerid' => $teacherid,
            ]);
            $group->create();
            $group->add_member($teacherid, group_member::TYPE_PRIMARY_TUTOR);
        } else {
            $group = reset($groups);
        }

        // Assign students to the teacher's group using API.
        $assignedcount = \mod_projetvet\local\api\groups::assign_students_to_group(
            $group->get('id'),
            $studentids,
            $projetvetid
        );

        return [
            'result' => true,
            'message' => get_string('membersadded', 'mod_projetvet', $assignedcount),
        ];
    }

    /**
     * Get page URL for dynamic submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return new moodle_url('/mod/projetvet/admin.php', ['id' => $cmid]);
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $studentidsJson = $this->optional_param('studentids', '[]', PARAM_RAW);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);
        $cmid = $this->optional_param('cmid', 0, PARAM_INT);

        $data = [
            'studentids' => $studentidsJson,
            'projetvetid' => $projetvetid,
            'cmid' => $cmid,
        ];

        parent::set_data((object) $data);
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $DB, $CFG;

        $mform = $this->_form;

        // Register the tagselect element type.
        \MoodleQuickForm::registerElementType(
            'tagselect',
            "$CFG->dirroot/mod/projetvet/classes/form/tagselect_element.php",
            'mod_projetvet\form\tagselect_element'
        );

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $studentidsJson = $this->optional_param('studentids', '[]', PARAM_RAW);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);

        // Hidden fields.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'studentids', $studentidsJson);
        $mform->setType('studentids', PARAM_RAW);

        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->setType('projetvetid', PARAM_INT);

        // Display selected students.
        $studentshtml = $this->get_selected_students_html($cmid, $projetvetid, $studentidsJson);
        if (!empty($studentshtml)) {
            $mform->addElement('html', $studentshtml);
        }

        // Hidden field for selected teacher (set by JavaScript).
        $mform->addElement('hidden', 'teacherid', '');
        $mform->setType('teacherid', PARAM_INT);

        // Add admin teachers report to show capacity.
        $reporthtml = $this->get_teachers_report_html($cmid, $projetvetid);
        if (!empty($reporthtml)) {
            $mform->addElement('html', '<div class="admin-teachers-report">' . $reporthtml . '</div>');
        }
    }

    /**
     * Get the HTML for displaying selected students
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @param string $studentidsJson JSON string of student IDs
     * @return string HTML output of the student list
     */
    protected function get_selected_students_html(int $cmid, int $projetvetid, string $studentidsJson): string {
        global $OUTPUT;

        $studentids = json_decode($studentidsJson, true);
        if (empty($studentids) || !is_array($studentids)) {
            return '';
        }

        // Get all students from the course (not filtered by assignment status).
        $allstudents = \mod_projetvet\local\api\groups::get_all_students($cmid);

        // Filter to only selected students.
        $selectedstudents = [];
        foreach ($allstudents as $student) {
            if (in_array($student['uniqueid'], $studentids)) {
                $selectedstudents[] = $student;
            }
        }

        if (empty($selectedstudents)) {
            return '';
        }

        // Prepare context for template.
        $context = [
            'studentcount' => count($selectedstudents),
            'students' => $selectedstudents,
        ];

        return $OUTPUT->render_from_template('mod_projetvet/form/selected_students', $context);
    }

    /**
     * Get the HTML for the teachers report
     *
     * @param int $cmid Course module ID
     * @param int $projetvetid ProjetVet ID
     * @return string HTML output of the report
     */
    protected function get_teachers_report_html(int $cmid, int $projetvetid): string {
        global $PAGE;

        try {
            // Create the report instance.
            $report = \core_reportbuilder\system_report_factory::create(
                \mod_projetvet\reportbuilder\local\systemreports\admin_teachers::class,
                $PAGE->context,
                '',
                '',
                0,
                [
                    'cmid' => $cmid,
                    'projetvetid' => $projetvetid,
                    'showcheckboxes' => true,
                ]
            );

            // Get the report output.
            $output = $report->output();

            // Handle null or unexpected output.
            if ($output === null) {
                return '';
            }

            // Return just the HTML (JS will be handled by the page), ensure it's a string.
            return $output ?? '';
        } catch (\Exception $e) {
            // If report fails, return empty string.
            debugging('Failed to load teachers report: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['teacherid'])) {
            $errors['teacherid'] = get_string('selectteacher', 'mod_projetvet');
        }

        return $errors;
    }
}
