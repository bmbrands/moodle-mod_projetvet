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
use mod_projetvet\local\persistent\teacher_rating;
use moodle_url;

/**
 * Form for updating teacher settings (rating/capacity)
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_settings_form extends dynamic_form {
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
        $data = $this->get_data();
        $teacherid = $data->teacherid;
        $projetvetid = $data->projetvetid;
        $newrating = $data->rating;

        // Use the groups API to set the teacher rating.
        \mod_projetvet\local\api\groups::set_teacher_rating($teacherid, $projetvetid, $newrating);

        return [
            'result' => true,
            'message' => get_string('teachersettingsupdated', 'mod_projetvet'),
        ];
    }

    /**
     * Get page URL for dynamic submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return new moodle_url('/mod/projetvet/assignments.php', ['id' => $cmid]);
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $teacherid = $this->optional_param('teacherid', 0, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);

        $data = [
            'cmid' => $cmid,
            'teacherid' => $teacherid,
            'projetvetid' => $projetvetid,
        ];

        // Get teacher info.
        if ($teacherid) {
            $teacher = $DB->get_record('user', ['id' => $teacherid], '*', MUST_EXIST);
            $data['teachername'] = fullname($teacher);

            // Get current rating.
            $rating = teacher_rating::get_or_create_rating($teacherid, $projetvetid);
            $data['rating'] = $rating->get('rating');
        }

        parent::set_data((object) $data);
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $DB, $CFG;

        $mform = $this->_form;

        // Register the custom HTML element type.
        \MoodleQuickForm::registerElementType(
            'html',
            "$CFG->dirroot/mod/projetvet/classes/form/html_element.php",
            'mod_projetvet\form\html_element'
        );

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $teacherid = $this->optional_param('teacherid', 0, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);

        // Hidden fields.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'teacherid', $teacherid);
        $mform->setType('teacherid', PARAM_INT);

        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->setType('projetvetid', PARAM_INT);

        // Get teacher info.
        $teacher = $DB->get_record('user', ['id' => $teacherid], '*', MUST_EXIST);

        // Display teacher name.
        $mform->addElement('static', 'teachername', get_string('teacher', 'mod_projetvet'), fullname($teacher));

        // Add explanation HTML.
        $explanationhtml = get_string('teachersettingsexplanation', 'mod_projetvet');
        $mform->addElement('html', '', $explanationhtml);

        // Rating selection.
        $ratingoptions = [
            teacher_rating::RATING_EXPERT => get_string('rating_expert', 'mod_projetvet') .
                ' (' . teacher_rating::CAPACITY_EXPERT . ' ' . get_string('students') . ')',
            teacher_rating::RATING_AVERAGE => get_string('rating_average', 'mod_projetvet') .
                ' (' . teacher_rating::CAPACITY_AVERAGE . ' ' . get_string('students') . ')',
            teacher_rating::RATING_NOVICE => get_string('rating_novice', 'mod_projetvet') .
                ' (' . teacher_rating::CAPACITY_NOVICE . ' ' . get_string('students') . ')',
        ];

        $mform->addElement('select', 'rating', get_string('teacher_rating', 'mod_projetvet'), $ratingoptions);
        $mform->addRule('rating', get_string('required'), 'required', null, 'client');
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

        // Validate rating value.
        $validratings = [
            teacher_rating::RATING_EXPERT,
            teacher_rating::RATING_AVERAGE,
            teacher_rating::RATING_NOVICE,
        ];

        if (!in_array($data['rating'], $validratings)) {
            $errors['rating'] = get_string('invalidrating', 'mod_projetvet');
        }

        return $errors;
    }
}
