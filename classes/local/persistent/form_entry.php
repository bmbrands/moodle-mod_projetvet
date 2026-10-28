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

namespace mod_projetvet\local\persistent;

use core\persistent;

/**
 * Activity entry entity
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_entry extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_form_entry';

    /** The highest entry status at which a student can delete their own entry (0 draft, 1 submitted). */
    const STUDENT_DELETE_MAX_STATUS = 1;

    /**
     * Return the custom definition of the properties of this model.
     *
     * Each property MUST be listed here.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'projetvetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'formsetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'studentid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            'parententryid' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'entrystatus' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Check if the current user can edit this entry
     * Returns true if the user can edit ANY category in the entry
     *
     * @return bool
     */
    public function can_edit(): bool {
        $context = $this->get_context();
        $entrystatus = $this->get('entrystatus');

        // Get the form structure.
        $formset = form_set::get_record(['id' => $this->get('formsetid')]);
        if (!$formset) {
            return false;
        }

        // Get all categories for this form set.
        $categories = form_cat::get_records(['formsetid' => $formset->get('id')]);
        if (empty($categories)) {
            return false;
        }

        // Check if user can edit any category.
        foreach ($categories as $categoryrecord) {
            // Convert persistent to object with required properties.
            $category = (object)[
                'id' => $categoryrecord->get('id'),
                'capability' => $categoryrecord->get('capability'),
                'entrystatus' => $categoryrecord->get('entrystatus'),
            ];

            // Use the API function to check if user can edit this category.
            if (\mod_projetvet\local\api\entries::can_edit_category($category, $entrystatus, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user can delete this entry
     *
     * - the student can delete their own entries while they are a draft or submitted
     *   (status up to {@see self::STUDENT_DELETE_MAX_STATUS});
     * - teachers (mod/projetvet:approve) and administrators (mod/projetvet:admin) who can view
     *   the student can delete their entries at any status.
     *
     * @param int|null $userid Defaults to the current user
     * @return bool
     */
    public function can_delete(?int $userid = null): bool {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/projetvet/lib.php');

        $userid = $userid ?? (int) $USER->id;
        $context = $this->get_context();
        $studentid = (int) $this->get('studentid');

        $isteacher = has_capability('mod/projetvet:approve', $context, $userid)
            || has_capability('mod/projetvet:admin', $context, $userid);
        if ($isteacher && projetvet_user_can_view_student($userid, $studentid, $context)) {
            return true;
        }

        return $studentid === $userid && (int) $this->get('entrystatus') <= self::STUDENT_DELETE_MAX_STATUS;
    }

    /**
     * Get the context for this entry
     *
     * @return \context_module
     */
    protected function get_context() {
        global $DB;
        $cm = get_coursemodule_from_instance('projetvet', $this->get('projetvetid'));
        return \context_module::instance($cm->id);
    }

    /**
     * Hook to execute after delete.
     *
     * @param bool $result
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            // Delete all associated form_data records using the persistent class.
            $datarecords = form_data::get_records(['entryid' => $this->get('id')]);
            foreach ($datarecords as $datarecord) {
                $datarecord->delete();
            }
        }
    }
}
