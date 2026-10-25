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
use context_module;
use context_course;
use moodle_url;
use pix_icon;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\reportbuilder\local\entities\teacher;

/**
 * Assignments teachers list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignments_teachers extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $filterwithcapacity = $this->get_parameter('filterwithcapacity', 0, PARAM_BOOL);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = context_course::instance($cm->course);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Teacher capacity entity. Assign the report's user table alias so the entity's
        // sub-queries correlate against the report's main user table.
        $entityteacher = new teacher($projetvetid);
        $entityteacher->set_table_alias('user', $entityuseralias);
        $this->add_entity($entityteacher);

        // Base fields needed.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname");

        // Get all teachers with approve capability.
        $teacherids = \mod_projetvet\local\api\groups::get_all_teachers($cmid);

        if (!empty($teacherids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, database::generate_param_name());
            $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);

            // Only show teachers who still have capacity when requested.
            if ($filterwithcapacity) {
                $capacitypvparam = database::generate_param_name();
                $groupspvparam = database::generate_param_name();
                $mtparam = database::generate_param_name();
                $subspvparam = database::generate_param_name();
                $this->add_base_condition_sql(
                    '(' . $entityteacher->get_gap_sql($capacitypvparam, $groupspvparam, $mtparam, $subspvparam) . ') > 0',
                    [
                        $capacitypvparam => $projetvetid,
                        $groupspvparam => $projetvetid,
                        $mtparam => group_member::TYPE_STUDENT,
                        $subspvparam => $projetvetid,
                    ]
                );
            }
        } else {
            $this->add_base_condition_sql("1 = 0");
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
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

        $context = context_module::instance($cm->id);
        return has_capability('mod/projetvet:admin', $context);
    }

    /**
     * Add columns to the report
     */
    protected function add_columns(): void {
        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $entityteacher = $this->get_entity('teacher');

        // Fullname with picture. Sort by the actual lastname field, rather than by the
        // configured display order of the user's full name.
        $fullnamecolumn = $entityuser->get_column('fullnamewithpicturelink');
        $fullnamecolumn->add_field("{$entityuseralias}.lastname")
            ->add_field("{$entityuseralias}.firstname")
            ->set_is_sortable(true, ["{$entityuseralias}.lastname", "{$entityuseralias}.firstname"]);
        $this->add_column($fullnamecolumn);

        // Rating, target capacity, current student count and gap columns from the teacher entity.
        $this->add_column($entityteacher->get_column('rating'));
        $this->add_column($entityteacher->get_column('status'));
        $this->add_column($entityteacher->get_column('target'));
        $this->add_column($entityteacher->get_column('current'));
        $this->add_column($entityteacher->get_column('gap'));

        // A1 acceptance column (with the inconsistency warning badge).
        $this->add_column($entityteacher->get_column('acceptsa1'));

        // Open substitutions column (with the return of the principal tutor badge).
        $this->add_column($entityteacher->get_column('substitutions'));

        // Default sorting by the actual lastname field.
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');
        $entityteacher = $this->get_entity('teacher');

        // Fullname filter.
        $this->add_filter($entityuser->get_filter('fullname'));
        $this->add_filter($entityteacher->get_filter('status'));
    }

    /**
     * Add actions to the report
     */
    protected function add_actions(): void {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Assign students action.
        $this->add_action((new action(
            new moodle_url('#', []),
            new pix_icon('i/assignroles', ''),
            [
                'data-action' => 'assign-students',
                'data-teacherid' => ':id',
                'data-projetvetid' => $projetvetid,
                'data-cmid' => $cmid,
            ],
            false,
            new lang_string('assignstudents', 'mod_projetvet'),
        )));

        // Manage the substitute tutors of the tutor's students.
        $this->add_action((new action(
            new moodle_url('/mod/projetvet/substitutions.php', ['id' => $cmid, 'tutorid' => ':id']),
            new pix_icon('i/switchrole', ''),
            [],
            false,
            new lang_string('substitution_manage', 'mod_projetvet'),
        )));

        // Update teacher rating action.
        $this->add_action((new action(
            new moodle_url('#', []),
            new pix_icon('i/settings', ''),
            [
                'data-action' => 'update-teacher-rating',
                'data-teacherid' => ':id',
                'data-projetvetid' => $projetvetid,
                'data-cmid' => $cmid,
            ],
            false,
            new lang_string('updateteacherrating', 'mod_projetvet'),
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
