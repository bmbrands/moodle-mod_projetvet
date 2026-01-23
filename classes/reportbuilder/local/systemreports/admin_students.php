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
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;
use context_module;
use context_course;
use mod_projetvet\reportbuilder\local\filters\promotion;
use mod_projetvet\reportbuilder\local\filters\cohort;
use mod_projetvet\reportbuilder\local\filters\teacher;

/**
 * Admin student list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_students extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Base fields needed for actions.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname,
            {$entityuseralias}.email");

        // Get list of enrolled students with submit capability.
        $enrolledusers = get_enrolled_users($context, 'mod/projetvet:submit', 0, 'u.id', null, 0, 0, true);

        if (empty($enrolledusers)) {
            // No enrolled users, add impossible condition.
            $this->add_base_condition_sql("1 = 0");
        } else {
            $enrolleduserids = array_keys($enrolledusers);
            [$insql, $inparams] = $DB->get_in_or_equal($enrolleduserids, SQL_PARAMS_NAMED, database::generate_param_name());
            $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
        $this->set_default_per_page(10);
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
        global $DB;

        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('projetvet', $cmid);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Checkbox column for students without teacher.
        $selectcolumn = (new column(
            'select',
            new lang_string('select', 'core'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_select')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_attributes(['class' => 'w-30'])
            ->add_callback(static function($value, $row) use ($projetvetid): string {
                // Check if student has a teacher.
                return '<input type="checkbox" class="student-select-checkbox" data-action="select-student" data-studentid="' . $row->userid_select . '">';
            });

        $this->add_column($selectcolumn);

        // Fullname with picture.
        $this->add_column($entityuser->get_column('fullnamewithpicturelink'));

        // Promotion (custom profile field) - column 2.
        $promotioncolumn = (new column(
            'promotion',
            new lang_string('promotion', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_promotion')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function($value, $row): string {
                return \mod_projetvet\utils::get_user_profile_field($row->userid_promotion, 'promotion');
            });

        $this->add_column($promotioncolumn);

        // Year (cohort) - column 3.
        $yearcolumn = (new column(
            'year',
            new lang_string('year', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_cohort')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function($value, $row): string {
                return \mod_projetvet\utils::get_user_cohort($row->userid_cohort);
            });

        $this->add_column($yearcolumn);

        // Teacher (tutor) - column 4.
        $teachercolumn = (new column(
            'teacher',
            new lang_string('teacher', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_teacher')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function($value, $row) use ($cm): string {
                $primarytutor = \mod_projetvet\local\api\groups::get_student_primary_tutor(
                    $row->userid_teacher,
                    $cm->instance
                );
                return $primarytutor ? fullname($primarytutor) : '-';
            });

        $this->add_column($teachercolumn);

        // Secondary tutor(s) - column 5.
        $secondarytutorcolumn = (new column(
            'secondary_tutor',
            new lang_string('secondary_tutor', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_secondary')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_callback(static function($value, $row) use ($cm): string {
                $secondarytutors = \mod_projetvet\local\api\groups::get_student_secondary_tutors(
                    $row->userid_secondary,
                    $cm->instance
                );
                if (empty($secondarytutors)) {
                    return '-';
                }
                $names = array_map('fullname', $secondarytutors);
                return implode(', ', $names);
            });

        $this->add_column($secondarytutorcolumn);

        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        global $DB;

        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('projetvet', $cmid);

        // Fullname filter (this will be the search box).
        $this->add_filter($entityuser->get_filter('fullname'));

        // Promotion filter (custom profile field).
        $promotionfilter = (new filter(
            promotion::class,
            'promotion',
            new lang_string('promotion', 'mod_projetvet'),
            $entityuser->get_entity_name(),
            "{$entityuseralias}.id"
        ))
            ->add_joins($entityuser->get_joins());
        $this->add_filter($promotionfilter);

        // Year (cohort) filter.
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

        // Teacher filter - use Groups API.
        $teachers = \mod_projetvet\local\api\groups::get_available_teachers($cmid, 0);
        $teacheroptions = [];
        foreach ($teachers as $teacher) {
            $teacheroptions[$teacher['uniqueid']] = $teacher['name'];
        }

        if (!empty($teacheroptions)) {
            $teacherfilter = (new filter(
                teacher::class,
                'teacher',
                new lang_string('teacher', 'mod_projetvet'),
                $entityuser->get_entity_name(),
                "{$entityuseralias}.id"
            ))
                ->add_joins($entityuser->get_joins())
                ->set_options(['projetvetid' => $cm->instance, 'teachers' => $teacheroptions]);
            $this->add_filter($teacherfilter);
        }
    }

    /**
     * Add actions to the report
     */
    protected function add_actions(): void {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Assign teacher action (for bulk assignment).
        $this->add_action((new action(
            new moodle_url('#', []),
            new pix_icon('i/assignroles', ''),
            [
                'data-action' => 'assign-teacher',
                'data-userid' => ':id',
                'data-projetvetid' => $projetvetid,
                'data-cmid' => $cmid,
            ],
            false,
            new lang_string('assignteacher', 'mod_projetvet'),
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
