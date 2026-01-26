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
use core_reportbuilder\system_report;
use lang_string;
use context_module;
use context_course;
use moodle_url;
use pix_icon;

/**
 * Admin teacher list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_teachers extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $showcheckboxes = $this->get_parameter('showcheckboxes', false, PARAM_BOOL);
        $filterwithcapacity = $this->get_parameter('filterwithcapacity', 0, PARAM_BOOL);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = context_course::instance($cm->course);

        // Get tutor role.
        $tutorrole = get_config('mod_projetvet', 'tutor_role') ?: 'teacher';
        $role = $DB->get_record('role', ['shortname' => $tutorrole]);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Base fields needed.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname");

        if ($role) {
            // Get teachers with this role in the course.
            $teachers = get_role_users($role->id, $context, false, 'u.id, u.lastname, u.firstname ');
            if (!empty($teachers)) {
                $teacherids = array_keys($teachers);

                // Filter teachers with capacity if requested.
                if ($filterwithcapacity) {
                    $teacherswithcapacity = [];
                    foreach ($teacherids as $teacherid) {
                        $capacity = \mod_projetvet\local\api\groups::get_teacher_available_capacity($teacherid, $projetvetid);
                        if ($capacity > 0) {
                            $teacherswithcapacity[] = $teacherid;
                        }
                    }
                    $teacherids = $teacherswithcapacity;
                }

                if (!empty($teacherids)) {
                    [$insql, $inparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, database::generate_param_name());
                    $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
                } else {
                    $this->add_base_condition_sql("1 = 0");
                }
            } else {
                $this->add_base_condition_sql("1 = 0");
            }
        } else {
            $this->add_base_condition_sql("1 = 0");
        }

        $this->add_columns();
        $this->add_filters();
        if (!$showcheckboxes) {
            $this->add_actions();
        }

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
        global $DB;

        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $showcheckboxes = $this->get_parameter('showcheckboxes', false, PARAM_BOOL);
        $cm = get_coursemodule_from_id('projetvet', $cmid);

        // Checkbox column (only shown when parameter is set).
        if ($showcheckboxes) {
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
                ->add_callback(static function($value, $row): string {
                    global $OUTPUT;
                    return $OUTPUT->render_from_template('mod_projetvet/reportbuilder/teacher_radio', [
                        'teacherid' => $row->userid_select,
                    ]);
                });

            $this->add_column($selectcolumn);
        }

        // Fullname with picture.
        $this->add_column($entityuser->get_column('fullnamewithpicturelink'));

        // Rating column.
        $ratingcolumn = (new column(
            'rating',
            new lang_string('teacher_rating', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$entityuseralias}.id")
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($projetvetid): string {
                $rating = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($row->id, $projetvetid);
                return $rating->get_rating_string();
            });

        $this->add_column($ratingcolumn);

        // Target capacity (Cible).
        $targetcolumn = (new column(
            'target',
            new lang_string('teacher_target', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$entityuseralias}.id")
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                $rating = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($row->id, $projetvetid);
                return $rating->get_capacity();
            });

        $this->add_column($targetcolumn);

        // Current student count (Nombre actuel).
        $currentcolumn = (new column(
            'current',
            new lang_string('teacher_current', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$entityuseralias}.id")
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                // Get student count for groups where this teacher is PRIMARY owner only.
                // This is used for capacity planning - doesn't count secondary tutor assignments.
                return \mod_projetvet\local\api\groups::get_primary_student_count($row->id, $projetvetid, true);
            });

        $this->add_column($currentcolumn);

        // Gap (Ecart) = Target - Current.
        $gapcolumn = (new column(
            'gap',
            new lang_string('teacher_gap', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_fields("{$entityuseralias}.id")
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($cm, $projetvetid): int {
                // Get available capacity (target - current primary students).
                // Only counts students in groups where teacher is PRIMARY owner.
                return \mod_projetvet\local\api\groups::get_teacher_available_capacity($row->id, $projetvetid);
            });

        $this->add_column($gapcolumn);

        // Default sorting by lastname.
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');

        // Fullname filter.
        $this->add_filter($entityuser->get_filter('fullname'));
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
        $showcheckboxes = $this->get_parameter('showcheckboxes', false, PARAM_BOOL);
        if ($showcheckboxes) {
            return '';
        } else {
            return 'clickable-row';
        }
    }
}
