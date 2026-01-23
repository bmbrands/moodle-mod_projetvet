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
 * Cohort (year) filter for student report
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort extends base {
    /**
     * Get course ID from filter options
     *
     * @return int
     */
    private function get_courseid(): int {
        $options = $this->filter->get_options();
        return (int) ($options['courseid'] ?? 0);
    }

    /**
     * Setup form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $options = \mod_projetvet\utils::get_course_cohorts($this->get_courseid());

        if (empty($options)) {
            return;
        }

        $mform->addElement(
            'select',
            "{$this->name}_value",
            '',
            ['' => get_string('any')] + $options
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

        $cohortid = (int) ($values["{$this->name}_value"] ?? 0);
        if ($cohortid === 0) {
            return ['', []];
        }

        [$fieldsql, $params] = $this->filter->get_field_sql_and_params();
        $memberalias = database::generate_alias();
        $cohortparam = database::generate_param_name();

        $params[$cohortparam] = $cohortid;

        $sql = "{$fieldsql} IN (
            SELECT {$memberalias}.userid
            FROM {cohort_members} {$memberalias}
            WHERE {$memberalias}.cohortid = :{$cohortparam}
        )";

        return [$sql, $params];
    }
}
