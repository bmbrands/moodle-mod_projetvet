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

namespace mod_projetvet\reportbuilder\local\filters;

use MoodleQuickForm;
use core_reportbuilder\local\filters\base;
use core_reportbuilder\local\helpers\database;

/**
 * Teacher filter for student report
 *
 * Filters students by their assigned teacher based on group membership
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher extends base {
    /**
     * Get projetvet ID from filter options
     *
     * @return int
     */
    private function get_projetvetid(): int {
        $options = $this->filter->get_options();
        return (int) ($options['projetvetid'] ?? 0);
    }

    /**
     * Get teachers from filter options
     *
     * @return array
     */
    private function get_teachers(): array {
        $options = $this->filter->get_options();
        return $options['teachers'] ?? [];
    }

    /**
     * Setup form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $teachers = $this->get_teachers();
        if (empty($teachers)) {
            return;
        }

        $mform->addElement(
            'select',
            "{$this->name}_value",
            '',
            ['' => get_string('any')] + $teachers
        );
    }

    /**
     * Return filter SQL
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        global $DB;

        $teacherid = (int) ($values["{$this->name}_value"] ?? 0);
        if ($teacherid === 0) {
            return ['', []];
        }

        // Get students for this teacher using Groups API.
        // This returns an array of user IDs directly.
        $studentids = \mod_projetvet\local\api\groups::get_students_for_tutor($teacherid, $this->get_projetvetid());

        if (empty($studentids)) {
            return ["1 = 0", []];
        }

        [$fieldsql, $params] = $this->filter->get_field_sql_and_params();
        [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, database::generate_param_name());

        $params = array_merge($params, $inparams);
        $sql = "{$fieldsql} {$insql}";

        return [$sql, $params];
    }
}
