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

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;

/**
 * Student list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class students extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB, $USER;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Base fields needed for actions.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname,
            {$entityuseralias}.email");

        // Check if current user is a tutor and get their students.
        if (has_capability('mod/projetvet:admin', $context)) {
            $studentarrays = \mod_projetvet\local\api\groups::get_all_students($cmid);
            $studentids = array_column($studentarrays, 'uniqueid');
        } else {
            $studentids = \mod_projetvet\local\api\groups::get_students_for_tutor($USER->id, $projetvetid);
        }

        if (empty($studentids)) {
            // No students for this tutor, add impossible condition.
            $this->add_base_condition_sql("1 = 0");
        } else {
            // Show only students assigned to this tutor.
            [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, database::generate_param_name());

            $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);

        // Set pagination (default is 30, you can change this).
        $this->set_default_per_page(30);
    }

    /**
     * Validates access to view this report
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

        $context = \context_module::instance($cm->id);
        return has_capability('mod/projetvet:viewallactivities', $context);
    }

    /**
     * Add columns to the report
     */
    protected function add_columns(): void {
        global $DB;

        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // Fullname with picture.
        $this->add_column($entityuser->get_column('fullnamewithpicture'));

        // Promotion (custom profile field) - column 2.
        $promotioncolumn = (new \core_reportbuilder\local\report\column(
            'promotion',
            new lang_string('promoyear', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_promotion')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row): string {
                return \mod_projetvet\utils::get_user_profile_field($row->userid_promotion, 'promotion');
            });

        $this->add_column($promotioncolumn);

        // Cohort (year in course) - column 3.
        $cohortcolumn = (new \core_reportbuilder\local\report\column(
            'cohort',
            new lang_string('year', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_cohort')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row): string {
                return \mod_projetvet\utils::get_user_cohort($row->userid_cohort);
            });

        $this->add_column($cohortcolumn);

        // Email.
        $this->add_column($entityuser->get_column('email'));

        // Total ECTS - create custom column.
        $totalectscolumn = (new \core_reportbuilder\local\report\column(
            'totalects',
            new lang_string('totalcredits', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_ects')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                return \mod_projetvet\utils::get_student_total_ects($projetvetid, $row->userid_ects);
            });

        $this->add_column($totalectscolumn);

        // Face-to-face count - create custom column.
        $facetofacecolumn = (new \core_reportbuilder\local\report\column(
            'facetofacesessions',
            new lang_string('facetofacesessions', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid2')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                global $DB;
                // Get all form entries for this student in facetoface.
                $entries = $DB->get_records_sql(
                    "SELECT pfe.id
                     FROM {projetvet_form_entry} pfe
                     JOIN {projetvet_form_set} pfs ON pfe.formsetid = pfs.id
                     WHERE pfe.studentid = :studentid
                     AND pfe.projetvetid = :projetvetid
                     AND pfs.idnumber = :idnumber
                     AND pfe.entrystatus > 0",
                    ['studentid' => $row->userid2, 'projetvetid' => $projetvetid, 'idnumber' => 'facetoface']
                );
                return count($entries);
            });

        $this->add_column($facetofacecolumn);

        // Action required column - shows icon if there are entries needing teacher action.
        $actionrequiredcolumn = (new \core_reportbuilder\local\report\column(
            'actionrequired',
            new lang_string('actionrequired', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_action')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): string {
                if (\mod_projetvet\utils::student_has_pending_teacher_action($projetvetid, $row->userid_action)) {
                    return \html_writer::tag(
                        'i',
                        '',
                        [
                            'class' => 'icon fa fa-exclamation-circle text-info',
                            'title' => get_string('actionrequired', 'mod_projetvet'),
                        ]
                    );
                }
                return '';
            });

        $this->add_column($actionrequiredcolumn);

        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');

        // Fullname filter.
        $this->add_filter($entityuser->get_filter('fullname'));

        // Email filter.
        $this->add_filter($entityuser->get_filter('email'));
    }

    /**
     * Add actions to the report
     */
    protected function add_actions(): void {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // View student activities action.
        $this->add_action((new action(
            new moodle_url('/mod/projetvet/view.php', ['id' => $cmid, 'studentid' => ':id']),
            new pix_icon('i/search', ''),
            [],
            false,
            new lang_string('viewactivities', 'mod_projetvet'),
        )));
    }

    /**
     * Get CSS class for each row to make it clickable via JavaScript.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return 'clickable-row';
    }
}
