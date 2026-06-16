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

namespace mod_projetvet\reportbuilder\local\systemreports;

use context_module;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use lang_string;
use mod_projetvet\reportbuilder\local\filters\cohort;
use mod_projetvet\reportbuilder\local\filters\projectcount;
use mod_projetvet\reportbuilder\local\filters\projectstovalidate;
use mod_projetvet\reportbuilder\local\filters\promotion;

/**
 * Dashboard system report for DEVE-style overview.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard extends system_report {
    /**
     * Initialise report.
     */
    protected function initialise(): void {
        global $DB;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname");

        // Report is restricted to enrolled students.
        $enrolledusers = get_enrolled_users($context, 'mod/projetvet:submit', 0, 'u.id', null, 0, 0, true);
        if (empty($enrolledusers)) {
            $this->add_base_condition_sql("1 = 0");
        } else {
            $studentids = array_keys($enrolledusers);
            [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, database::generate_param_name());
            $this->add_base_condition_sql("{$entityuseralias}.id {$insql}", $params);
        }

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(true);
        $this->set_default_per_page(30);
    }

    /**
     * Validate access.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        if (!$cmid) {
            return false;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return false;
        }

        $context = context_module::instance($cm->id);
        return has_capability('mod/projetvet:admin', $context);
    }

    /**
     * Add columns.
     */
    protected function add_columns(): void {
        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Etudiant.
        $this->add_column($entityuser->get_column('fullnamewithpicturelink'));

        // Promotion.
        $promotioncolumn = (new column(
            'promotion',
            new lang_string('promotion', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_promotion')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row): string {
                return \mod_projetvet\utils::get_user_profile_field($row->userid_promotion, 'promotion');
            });
        $this->add_column($promotioncolumn);

        // Annee.
        $yearcolumn = (new column(
            'year',
            new lang_string('year', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_year')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row): string {
                return \mod_projetvet\utils::get_user_cohort($row->userid_year);
            });
        $this->add_column($yearcolumn);

        // Nombre de projets.
        $projectcountcolumn = (new column(
            'projectcount',
            new lang_string('projectcount', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_projectcount')
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                return \mod_projetvet\utils::get_student_project_count($projetvetid, $row->userid_projectcount);
            });
        $this->add_column($projectcountcolumn);

        // Nombre de projets a valider.
        $projectstovalidatecolumn = (new column(
            'projectstovalidate',
            new lang_string('projectstovalidate', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_projectstovalidate')
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                return \mod_projetvet\utils::get_student_projects_to_validate_count(
                    $projetvetid,
                    $row->userid_projectstovalidate
                );
            });
        $this->add_column($projectstovalidatecolumn);

        // Total ECTS.
        $totalectscolumn = (new column(
            'totalects',
            new lang_string('totalcredits', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_totalects')
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                return \mod_projetvet\utils::get_student_total_ects($projetvetid, $row->userid_totalects);
            });
        $this->add_column($totalectscolumn);

        // ECTS median.
        $medianectscolumn = (new column(
            'medianects',
            new lang_string('medianects', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_medianects')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): string {
                $median = \mod_projetvet\utils::get_student_median_ects($projetvetid, $row->userid_medianects);
                if ((int)$median === $median) {
                    return (string)(int)$median;
                }
                return number_format($median, 1, '.', '');
            });
        $this->add_column($medianectscolumn);

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Add filters.
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('projetvet', $cmid);

        $this->add_filter($entityuser->get_filter('fullname'));

        $promotionfilter = (new filter(
            promotion::class,
            'promotion',
            new lang_string('promotion', 'mod_projetvet'),
            $entityuser->get_entity_name(),
            "{$entityuseralias}.id"
        ))
            ->add_joins($entityuser->get_joins());
        $this->add_filter($promotionfilter);

        $cohortfilter = (new filter(
            cohort::class,
            'cohort',
            new lang_string('year', 'mod_projetvet'),
            $entityuser->get_entity_name(),
            "{$entityuseralias}.id"
        ))
            ->add_joins($entityuser->get_joins())
            ->set_options(['courseid' => $cm->course]);
        $this->add_filter($cohortfilter);

        $projectcountfilter = (new filter(
            projectcount::class,
            'projectcount',
            new lang_string('projectcount', 'mod_projetvet'),
            $entityuser->get_entity_name(),
            "{$entityuseralias}.id"
        ))
            ->add_joins($entityuser->get_joins())
            ->set_options(['projetvetid' => $this->get_parameter('projetvetid', 0, PARAM_INT)]);
        $this->add_filter($projectcountfilter);

        $projectstovalidatefilter = (new filter(
            projectstovalidate::class,
            'projectstovalidate',
            new lang_string('projectstovalidate', 'mod_projetvet'),
            $entityuser->get_entity_name(),
            "{$entityuseralias}.id"
        ))
            ->add_joins($entityuser->get_joins())
            ->set_options(['projetvetid' => $this->get_parameter('projetvetid', 0, PARAM_INT)]);
        $this->add_filter($projectstovalidatefilter);
    }
}
