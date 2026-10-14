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
use core_external\external_single_structure;
use core_external\external_value;
use mod_projetvet\local\api\archive;

/**
 * Get (and create on first use) the archive entry of a student.
 *
 * The archived projects are subset entries of the archive entry, so the archive
 * entry must exist before the first project can be added.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_archive_entry extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_REQUIRED),
            'studentid' => new external_value(PARAM_INT, 'Student ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get (and create on first use) the archive entry of a student.
     *
     * @param int $cmid The course module id
     * @param int $studentid The student id
     * @return array
     */
    public static function execute(int $cmid, int $studentid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'studentid' => $studentid,
        ]);

        $cm = get_coursemodule_from_id('projetvet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $entry = archive::get_or_create_archive_entry($context, $cm->instance, $params['studentid'], $USER->id);

        return [
            'entryid' => $entry->get('id'),
            'entrystatus' => $entry->get('entrystatus'),
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entryid' => new external_value(PARAM_INT, 'The archive entry ID'),
            'entrystatus' => new external_value(PARAM_INT, 'The archive entry status'),
        ]);
    }
}
