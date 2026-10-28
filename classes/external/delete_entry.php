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

namespace mod_projetvet\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_projetvet\local\persistent\form_entry;

/**
 * Class delete_entry
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_entry extends external_api {
    /**
     * Delete an activity entry
     *
     * @param int $entryid The entry id
     * @return bool
     */
    public static function execute(int $entryid): bool {
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'entryid' => $entryid,
            ]
        );

        // Get the entry and validate context.
        $entry = form_entry::get_record(['id' => $params['entryid']]);
        if (!$entry) {
            throw new \moodle_exception('entry_not_found', 'mod_projetvet', '', $params['entryid']);
        }
        // Get context from the projetvet instance.
        global $DB;
        $cm = get_coursemodule_from_instance('projetvet', $entry->get('projetvetid'));
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Delete the entry, with its data, files and subset entries, if the user can delete it.
        return \mod_projetvet\local\api\entries::delete_entry($entry->get('id'));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'entryid' => new external_value(PARAM_INT, 'Entry ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Success');
    }
}
