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
use core_text;
use mod_projetvet\local\importer\group_importer;
use moodle_exception;
use moodle_url;

/**
 * Class group_upload_form
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_upload_form extends dynamic_form {
    /**
     * Process the form submission
     *
     * @return array
     * @throws moodle_exception
     */
    public function process_dynamic_submission(): array {
        global $USER;
        $context = $this->get_context_for_dynamic_submission();
        $data = $this->get_data();

        // If deleteexisting is checked, remove all groups in this projetvet instance.
        if (!empty($data->deleteexisting)) {
            $groups = \mod_projetvet\local\persistent\projetvet_group::get_records(['projetvetid' => $data->projetvetid]);
            foreach ($groups as $group) {
                // Delete all members first.
                $members = \mod_projetvet\local\persistent\group_member::get_records(['groupid' => $group->get('id')]);
                foreach ($members as $member) {
                    $member->delete();
                }
                // Delete the group.
                $group->delete();
            }
        }

        // Get the file and create the content based on it.
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $this->get_data()->csvfile,
            'itemid, filepath, filename',
            false
        );

        if (!empty($files)) {
            $file = reset($files);
            if (core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'csv') {
                throw new moodle_exception('invalidfiletype', 'error', '', $file->get_filename());
            }
            $filepath = make_request_directory() . '/' . $file->get_filename();
            $file->copy_content_to($filepath);
            try {
                $groupimporter = new group_importer($data->courseid, $data->cmid, $data->projetvetid);
                $groupimporter->import($filepath, $data->delimiter_name, $data->encoding);
            } finally {
                unlink($filepath);
            }
        }

        return [
            'result' => true,
            'returnurl' => new moodle_url('/mod/projetvet/assignments.php', ['id' => $data->cmid]),
        ];
    }

    /**
     * Get context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $context = context_module::instance($cmid);
        return $context;
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        if (!has_capability('mod/projetvet:admin', $this->get_context_for_dynamic_submission())) {
            throw new moodle_exception('invalidaccess');
        }
    }

    /**
     * Get page URL
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return new moodle_url('/mod/projetvet/assignments.php', ['id' => $cmid]);
    }

    /**
     * Form definition
     *
     * @return void
     */
    protected function definition() {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');

        $mform = $this->_form;
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $courseid = $this->optional_param('courseid', null, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', null, PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->setType('projetvetid', PARAM_INT);

        // Add download link.
        $downloadurl = new moodle_url('/mod/projetvet/download_groups.php', ['id' => $cmid]);
        $downloadlink = \html_writer::link(
            $downloadurl,
            get_string('downloadgroupscsv', 'mod_projetvet'),
            ['class' => 'btn btn-secondary mb-3', 'target' => '_blank']
        );
        $mform->addElement('static', 'downloadlink', '', $downloadlink);

        // Upload the CSV file.
        $mform->addElement('filemanager', 'csvfile', get_string('csvfile', 'mod_projetvet'), null, [
            'maxbytes' => 0,
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.csv'],
        ]);

        $choices = \csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'group'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'group'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        // Add checkbox for deleting existing groups.
        $mform->addElement('advcheckbox', 'deleteexisting', get_string('deleteexistinggroups', 'mod_projetvet'));
        $mform->setType('deleteexisting', PARAM_BOOL);
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [
            'cmid' => $this->optional_param('cmid', 0, PARAM_INT),
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'projetvetid' => $this->optional_param('projetvetid', 0, PARAM_INT),
        ];
        parent::set_data((object) $data);
    }
}
