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

use core_reportbuilder\local\filters\base;
use core_reportbuilder\local\helpers\database;
use MoodleQuickForm;

/**
 * Filter students by projects waiting for validation.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class projectstovalidate extends base {
    /**
     * Get projetvet id from filter options.
     *
     * @return int
     */
    private function get_projetvetid(): int {
        $options = $this->filter->get_options();
        return (int)($options['projetvetid'] ?? 0);
    }

    /**
     * Setup form.
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            "{$this->name}_value",
            '',
            [
                '' => get_string('any'),
                'none' => get_string('filter_projects_to_validate_none', 'mod_projetvet'),
                'has' => get_string('filter_projects_to_validate_has', 'mod_projetvet'),
            ]
        );
    }

    /**
     * Return filter SQL.
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        $value = $values["{$this->name}_value"] ?? '';
        if ($value === '') {
            return ['', []];
        }

        [$fieldsql, $params] = $this->filter->get_field_sql_and_params();
        $paramformset = database::generate_param_name();
        $paramprojetvetid = database::generate_param_name();
        $paramstatus1 = database::generate_param_name();
        $paramstatus3 = database::generate_param_name();

        $params[$paramformset] = 'activities';
        $params[$paramprojetvetid] = $this->get_projetvetid();
        $params[$paramstatus1] = 1;
        $params[$paramstatus3] = 3;

        $countsql = "(SELECT COUNT(fe.id)
                        FROM {projetvet_form_entry} fe
                        JOIN {projetvet_form_set} fs ON fs.id = fe.formsetid
                       WHERE fe.studentid = {$fieldsql}
                         AND fe.projetvetid = :{$paramprojetvetid}
                         AND fs.idnumber = :{$paramformset}
                         AND fe.entrystatus IN (:{$paramstatus1}, :{$paramstatus3}))";

        if ($value === 'none') {
            return ["{$countsql} = 0", $params];
        }

        return ["{$countsql} > 0", $params];
    }
}
