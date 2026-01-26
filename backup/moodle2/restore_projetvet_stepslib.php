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

/**
 * Structure step to restore one Projetvet activity
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_projetvet_activity_structure_step extends restore_activity_structure_step {
    /**
     * Structure step to restore one projetvet activity
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];

        // Main activity element.
        $paths[] = new restore_path_element('projetvet', '/activity/projetvet');

        // Configuration data elements (always restored).
        $paths[] = new restore_path_element('formset', '/activity/projetvet/formsets/formset');
        $paths[] = new restore_path_element(
            'formcat',
            '/activity/projetvet/formsets/formset/formcats/formcat'
        );
        $paths[] = new restore_path_element(
            'formfield',
            '/activity/projetvet/formsets/formset/formcats/formcat/formfields/formfield'
        );
        $paths[] = new restore_path_element(
            'fielddata',
            '/activity/projetvet/formsets/formset/formcats/formcat/formfields/formfield/fielddatas/fielddata'
        );

        // User data elements (only when userinfo is included).
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('formentry', '/activity/projetvet/formentries/formentry');
            $paths[] = new restore_path_element('formdata', '/activity/projetvet/formentries/formentry/formdatas/formdata');
            $paths[] = new restore_path_element('teacherrating', '/activity/projetvet/teacherratings/teacherrating');
            $paths[] = new restore_path_element('group', '/activity/projetvet/groups/group');
            $paths[] = new restore_path_element('groupmember', '/activity/projetvet/groups/group/groupmembers/groupmember');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a projetvet restore
     *
     * @param array $data
     * @return void
     */
    protected function process_projetvet($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the projetvet record.
        $newitemid = $DB->insert_record('projetvet', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process form set data
     *
     * @param array $data
     * @return void
     */
    protected function process_formset($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Check if the form set already exists by idnumber.
        if (!$DB->record_exists('projetvet_form_set', ['idnumber' => $data->idnumber])) {
            // Insert the form set record.
            $newitemid = $DB->insert_record('projetvet_form_set', $data);
        } else {
            // Use existing form set.
            $newitemid = $DB->get_field('projetvet_form_set', 'id', ['idnumber' => $data->idnumber]);
        }
        $this->set_mapping('formset', $oldid, $newitemid);
    }

    /**
     * Process form category data
     *
     * @param array $data
     * @return void
     */
    protected function process_formcat($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->formsetid = $this->get_new_parentid('formset');

        // Check if the form category already exists by idnumber and formsetid.
        $conditions = ['idnumber' => $data->idnumber, 'formsetid' => $data->formsetid];
        if (!$DB->record_exists('projetvet_form_cat', $conditions)) {
            // Insert the form category record.
            $newitemid = $DB->insert_record('projetvet_form_cat', $data);
        } else {
            // Use existing form category.
            $newitemid = $DB->get_field('projetvet_form_cat', 'id', $conditions);
        }
        $this->set_mapping('formcat', $oldid, $newitemid);
    }

    /**
     * Process form field data
     *
     * @param array $data
     * @return void
     */
    protected function process_formfield($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->categoryid = $this->get_new_parentid('formcat');

        // Check if the form field already exists by idnumber and categoryid.
        $conditions = ['idnumber' => $data->idnumber, 'categoryid' => $data->categoryid];
        if (!$DB->record_exists('projetvet_form_field', $conditions)) {
            // Insert the form field record.
            $newitemid = $DB->insert_record('projetvet_form_field', $data);
        } else {
            // Use existing form field.
            $newitemid = $DB->get_field('projetvet_form_field', 'id', $conditions);
        }
        $this->set_mapping('formfield', $oldid, $newitemid);
    }

    /**
     * Process field data
     *
     * @param array $data
     * @return void
     */
    protected function process_fielddata($data) {
        global $DB;

        $data = (object)$data;
        $data->fieldid = $this->get_new_parentid('formfield');

        // Check if the field data already exists by fieldid and uniqueid.
        if (!$DB->record_exists('projetvet_field_data', ['fieldid' => $data->fieldid, 'uniqueid' => $data->uniqueid])) {
            // Insert the field data record.
            $DB->insert_record('projetvet_field_data', $data);
        }
    }

    /**
     * Process form entry data
     *
     * @param array $data
     * @return void
     */
    protected function process_formentry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->projetvetid = $this->get_new_parentid('projetvet');
        $data->formsetid = $this->get_mappingid('formset', $data->formsetid);
        $data->studentid = $this->get_mappingid('user', $data->studentid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Map parententryid to the new ID if it exists.
        if (!empty($data->parententryid)) {
            $data->parententryid = $this->get_mappingid('formentry', $data->parententryid);
        }

        // Insert the form entry record.
        $newitemid = $DB->insert_record('projetvet_form_entry', $data);
        $this->set_mapping('formentry', $oldid, $newitemid);
    }

    /**
     * Process form data
     *
     * @param array $data
     * @return void
     */
    protected function process_formdata($data) {
        global $DB;

        $data = (object)$data;
        $data->fieldid = $this->get_mappingid('formfield', $data->fieldid);
        $data->entryid = $this->get_new_parentid('formentry');
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Insert the form data record.
        $DB->insert_record('projetvet_form_data', $data);
    }

    /**
     * Process teacher rating data
     *
     * @param array $data
     * @return void
     */
    protected function process_teacherrating($data) {
        global $DB;

        $data = (object)$data;
        $data->projetvetid = $this->get_new_parentid('projetvet');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Insert the teacher rating record.
        $DB->insert_record('projetvet_teacher_rating', $data);
    }

    /**
     * Process group data
     *
     * @param array $data
     * @return void
     */
    protected function process_group($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->projetvetid = $this->get_new_parentid('projetvet');
        $data->ownerid = $this->get_mappingid('user', $data->ownerid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Insert the group record.
        $newitemid = $DB->insert_record('projetvet_groups', $data);
        $this->set_mapping('group', $oldid, $newitemid);
    }

    /**
     * Process group member data
     *
     * @param array $data
     * @return void
     */
    protected function process_groupmember($data) {
        global $DB;

        $data = (object)$data;
        $data->groupid = $this->get_new_parentid('group');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // Insert the group member record.
        $DB->insert_record('projetvet_group_members', $data);
    }

    /**
     * Actions to be executed after the restore is completed
     */
    protected function after_execute() {
        // Add projetvet related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_projetvet', 'intro', null);
    }
}
