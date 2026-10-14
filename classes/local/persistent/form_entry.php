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
use mod_projetvet\local\api\archive;

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
     * Check if the current user can delete this entry
     *
     * @return bool
     */
    public function can_delete(): bool {
        global $USER;

        // Archives are never deleted, archived projects only while the archive can be edited.
        $formset = form_set::get_record(['id' => $this->get('formsetid')]);
        $formsetidnumber = $formset ? $formset->get('idnumber') : '';
        if ($formsetidnumber === archive::FORMSET) {
            return false;
        }
        if ($formsetidnumber === archive::PROJECT_FORMSET) {
            $archive = self::get_record(['id' => $this->get('parententryid')]);
            return $archive && archive::can_edit_projects($archive);
        }

        // Students can delete their own entries.
        if ($this->get('studentid') == $USER->id) {
            return true;
        }
        // Teachers can delete any entry.
        $context = \context_module::instance($this->get_context()->instanceid);
        if (has_capability('mod/projetvet:edit', $context)) {
            return true;
        }
        return false;
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
