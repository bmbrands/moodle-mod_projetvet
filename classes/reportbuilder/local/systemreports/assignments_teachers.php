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
 * Assignments teachers list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignments_teachers extends system_report {
    /**
     * The name of the temporary table used to store teacher data
     */
    const TEACHER_TEMP_TABLE_NAME = 'temp_reportbuilder_teachers';

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

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Base fields needed.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname");

        // Get all teachers with approve capability.
        $teacherids = \mod_projetvet\local\api\groups::get_all_teachers($cmid);

        if (!empty($teacherids)) {
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

            // Initialize temp table with teacher data.
            $this->init_temp_table($teacherids, $projetvetid);

            [$insql, $inparams] = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED, database::generate_param_name());
            $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
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

        // Get alias for temp table.
        $temptablealias = 'teacherdata';

        // Add join to temp table.
        $this->add_join(
            "LEFT JOIN {" . self::TEACHER_TEMP_TABLE_NAME . "} {$temptablealias} " .
            "ON {$temptablealias}.userid = {$entityuseralias}.id"
        );

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
                ->add_callback(static function ($value, $row): string {
                    global $OUTPUT;
                    return $OUTPUT->render_from_template('mod_projetvet/reportbuilder/teacher_radio', [
                        'teacherid' => $row->userid_select,
                    ]);
                });

            $this->add_column($selectcolumn);
        }

        // Fullname with picture.
        $this->add_column($entityuser->get_column('fullnamewithpicturelink'));

        // Rating column - now sortable using temp table data.
        $ratingcolumn = (new column(
            'rating',
            new lang_string('teacher_rating', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$temptablealias}.rating")
            ->set_is_sortable(true);

        $this->add_column($ratingcolumn);

        // Target capacity (Cible) - now sortable using temp table data.
        $targetcolumn = (new column(
            'target',
            new lang_string('teacher_target', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$temptablealias}.target_capacity")
            ->set_is_sortable(true);

        $this->add_column($targetcolumn);

        // Current student count (Nombre actuel) - now sortable using temp table data.
        $currentcolumn = (new column(
            'current',
            new lang_string('teacher_current', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$temptablealias}.current_students")
            ->set_is_sortable(true);

        $this->add_column($currentcolumn);

        // Gap (Ecart) = Target - Current - now sortable using temp table data.
        $gapcolumn = (new column(
            'gap',
            new lang_string('teacher_gap', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$temptablealias}.gap")
            ->set_is_sortable(true);

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

        // Assign secondary teacher action.
        $this->add_action((new action(
            new moodle_url('#', []),
            new pix_icon('i/users', ''),
            [
                'data-action' => 'assign-secondary-teacher',
                'data-teacherid' => ':id',
                'data-projetvetid' => $projetvetid,
                'data-cmid' => $cmid,
            ],
            false,
            new lang_string('assignsecondaryteacher', 'mod_projetvet'),
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

    /**
     * Create and fill a temporary table with teacher data
     *
     * @param array $teacherids Array of teacher user IDs
     * @param int $projetvetid The projetvet instance ID
     */
    private function init_temp_table(array $teacherids, int $projetvetid): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(self::TEACHER_TEMP_TABLE_NAME);

        // If the table already exists, drop it first to ensure fresh data.
        if ($dbman->table_exists(self::TEACHER_TEMP_TABLE_NAME)) {
            $dbman->drop_table($table);
        }

        // Define the table structure.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rating', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('target_capacity', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('current_students', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('gap', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Create the temporary table.
        $dbman->create_temp_table($table);

        // Fill the temporary table with teacher data.
        foreach ($teacherids as $teacherid) {
            // Get rating for this teacher.
            $ratingobj = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($teacherid, $projetvetid);

            // Get capacity.
            $targetcapacity = $ratingobj->get_capacity();

            // Get current primary student count.
            $currentstudents = \mod_projetvet\local\api\groups::get_primary_student_count($teacherid, $projetvetid, true);

            // Calculate gap.
            $gap = $targetcapacity - $currentstudents;

            // Insert into temp table.
            $record = (object)[
                'userid' => $teacherid,
                'rating' => $ratingobj->get_rating_string(),
                'target_capacity' => $targetcapacity,
                'current_students' => $currentstudents,
                'gap' => $gap,
            ];

            $DB->insert_record(self::TEACHER_TEMP_TABLE_NAME, $record);
        }
    }
}
