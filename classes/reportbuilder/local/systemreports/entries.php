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

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_entry as form_entry_persistent;
use mod_projetvet\reportbuilder\local\entities\form_entry;
use core\url as moodle_url;
use pix_icon;

/**
 * Entry list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entries extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB, $USER;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $studentid = $this->get_parameter('studentid', 0, PARAM_INT);
        $formsetidnumber = $this->get_parameter('formsetidnumber', 'activities', PARAM_ALPHANUMEXT);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Get formset.
        $formset = $DB->get_record('projetvet_form_set', ['idnumber' => $formsetidnumber], '*', MUST_EXIST);

        // Main entry entity.
        $entityentry = new form_entry();
        $entityentryalias = $entityentry->get_table_alias('projetvet_form_entry');
        $formsetalias = $entityentry->get_table_alias('projetvet_form_set');

        $this->set_main_table('projetvet_form_entry', $entityentryalias);
        $this->add_entity($entityentry);

        // Join with form_set.
        $this->add_join("LEFT JOIN {projetvet_form_set} {$formsetalias}
            ON {$formsetalias}.id = {$entityentryalias}.formsetid");

        // Base fields.
        $this->add_base_fields("{$entityentryalias}.id, {$entityentryalias}.entrystatus,
            {$entityentryalias}.timemodified");

        // The project title, shown in the delete confirmation.
        $titlefield = $DB->get_record('projetvet_form_field', ['idnumber' => 'activity_title'], 'id');
        if ($titlefield) {
            $paramtitlefield = database::generate_param_name();
            $this->add_join(
                "LEFT JOIN {projetvet_form_data} entrytitle
                        ON entrytitle.entryid = {$entityentryalias}.id
                       AND entrytitle.fieldid = :{$paramtitlefield}",
                [$paramtitlefield => $titlefield->id]
            );
            $this->add_base_fields('entrytitle.charvalue AS entrytitle');
        }

        // Filter by projetvet instance, student, and formset.
        $paramprojetvet = database::generate_param_name();
        $paramstudent = database::generate_param_name();
        $paramformset = database::generate_param_name();

        // Determine status filter based on user role.
        $isteacher = $studentid != $USER->id;
        $statusfilter = $isteacher ? "{$entityentryalias}.entrystatus > 0" : "{$entityentryalias}.entrystatus >= 0";

        $this->add_base_condition_sql(
            "{$entityentryalias}.projetvetid = :{$paramprojetvet}
            AND {$entityentryalias}.studentid = :{$paramstudent}
            AND {$entityentryalias}.formsetid = :{$paramformset}
            AND {$statusfilter}",
            [
                $paramprojetvet => $projetvetid,
                $paramstudent => $studentid,
                $paramformset => $formset->id,
            ]
        );

        $this->add_columns($formset->id);
        $this->add_filters();
        $this->add_actions($cmid, $formsetidnumber);

        $this->set_downloadable(false);
        $this->set_default_per_page(30);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        global $USER;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $studentid = $this->get_parameter('studentid', 0, PARAM_INT);

        if (!$cmid) {
            return false;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id);

        // Students can view their own entries.
        if ($studentid == $USER->id && has_capability('mod/projetvet:submit', $context)) {
            return true;
        }

        // Teachers can view all entries.
        return has_capability('mod/projetvet:viewallactivities', $context);
    }

    /**
     * Add columns to the report
     *
     * @param int $formsetid
     */
    protected function add_columns(int $formsetid): void {
        global $DB;

        $entityentry = $this->get_entity('form_entry');
        $entityentryalias = $entityentry->get_table_alias('projetvet_form_entry');

        // Get fields with listorder > 0, ordered by listorder.
        $fields = $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            JOIN {projetvet_form_cat} fc ON ff.categoryid = fc.id
            WHERE fc.formsetid = :formsetid
            AND ff.listorder > 0
            ORDER BY ff.listorder ASC
        ", ['formsetid' => $formsetid]);

        // Add dynamic columns for each field.
        foreach ($fields as $field) {
            $this->add_field_column($field, $entityentryalias);
        }

        // Add time modified column.
        $this->add_column($entityentry->get_column('timemodified'));

        // Add entry status column.
        $this->add_column($entityentry->get_column('entrystatus'));

        $this->set_initial_sort_column('form_entry:timemodified', SORT_DESC);
    }

    /**
     * Add a column for a specific field
     *
     * @param \stdClass $field
     * @param string $entityentryalias
     */
    protected function add_field_column(\stdClass $field, string $entityentryalias): void {
        global $DB;

        $fieldpersistent = new form_field(0, $field);
        $fieldalias = 'field_' . $field->id;
        $dataalias = 'data_' . $field->id;

        // Determine which data column to use based on field type.
        $datacolumn = $this->get_data_column_for_field_type($field->type);

        // Add LEFT JOIN for this field's data.
        $this->add_join("LEFT JOIN {projetvet_form_data} {$dataalias}
            ON {$dataalias}.entryid = {$entityentryalias}.id
            AND {$dataalias}.fieldid = {$field->id}");

        // Get report parameters for use in callback.
        $studentid = $this->get_parameter('studentid', 0, PARAM_INT);
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // Create column.
        $column = (new column(
            'field_' . $field->idnumber,
            new lang_string('field_' . $field->idnumber . '_column', 'mod_projetvet'),
            'form_entry'
        ))
            ->add_field("{$dataalias}.{$datacolumn}", $fieldalias)
            ->add_field("{$dataalias}.fieldid", $fieldalias . '_fieldid')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($fieldpersistent, $fieldalias, $studentid, $cmid): string {
                $configdata = json_decode(stripslashes($fieldpersistent->get('configdata')), true);
                if (!empty($configdata['columnfilter'])) {
                    // Apply the filter to get the display value.
                    return \mod_projetvet\utils::get_filter($configdata['columnfilter'], $studentid, $cmid);
                }
                // Use the persistent class display_value method.
                if (empty($row->{$fieldalias . '_fieldid'})) {
                    return '';
                }
                // Check if field has tickondataentry configuration.
                if (!empty($configdata['tickondataentry'])) {
                    // Display a tick if there's any data.
                    if (!empty($value)) {
                        return '<i class="fa fa-check text-success" aria-hidden="true"></i>';
                    }
                    return '';
                }

                return $fieldpersistent->display_value($value);
            });

        $this->add_column($column);
    }

    /**
     * Get the data column name for a field type
     *
     * @param string $fieldtype
     * @return string
     */
    protected function get_data_column_for_field_type(string $fieldtype): string {
        $mapping = [
            'text' => 'charvalue',
            'number' => 'intvalue',
            'textarea' => 'textvalue',
            'select' => 'intvalue',
            'checkbox' => 'intvalue',
            'autocomplete' => 'textvalue',
            'tagselect' => 'textvalue',
            'date' => 'intvalue',
            'datetime' => 'intvalue',
            'tagconfirm' => 'textvalue',
        ];

        return $mapping[$fieldtype] ?? 'charvalue';
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityentry = $this->get_entity('form_entry');

        // Entry status filter.
        $this->add_filter($entityentry->get_filter('entrystatus'));

        // Time modified filter.
        $this->add_filter($entityentry->get_filter('timemodified'));
    }

    /**
     * Add actions to the report
     *
     * @param int $cmid
     * @param string $formsetidnumber
     */
    protected function add_actions(int $cmid, string $formsetidnumber): void {
        global $USER;

        $studentid = $this->get_parameter('studentid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $isteacher = $studentid != $USER->id;

        $cm = get_coursemodule_from_id('projetvet', $cmid);
        $context = \context_module::instance($cm->id);

        // View action (always available).
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/search', ''),
            [
                'data-action' => 'activity-entry-form',
                'data-readonly' => '1',
                'data-cmid' => $cmid,
                'data-projetvetid' => $projetvetid,
                'data-studentid' => $studentid,
                'data-entryid' => ':id',
                'data-formsetidnumber' => $formsetidnumber,
            ],
            false,
            new lang_string('view'),
        )));

        // Edit action (for students viewing their own entries or teachers with approve capability).
        if (!$isteacher || has_capability('mod/projetvet:approve', $context)) {
            $this->add_action((new action(
                new moodle_url('#'),
                new pix_icon('i/edit', ''),
                [
                    'data-action' => 'activity-entry-form',
                    'data-cmid' => $cmid,
                    'data-readonly' => '0',
                    'data-projetvetid' => $projetvetid,
                    'data-studentid' => $studentid,
                    'data-entryid' => ':id',
                    'data-formsetidnumber' => $formsetidnumber,
                ],
                false,
                new lang_string('edit'),
            )));
        }

        // Delete action: students delete their own projects at any status and their other entries while
        // draft or submitted; teachers and administrators delete the student's projects at any status
        // (see form_entry::can_delete()).
        $isproject = $formsetidnumber === form_entry_persistent::PROJECT_FORMSET;
        $teacherdelete = $isteacher && $isproject
            && (has_capability('mod/projetvet:approve', $context) || has_capability('mod/projetvet:admin', $context));
        if (!$isteacher || $teacherdelete) {
            $this->add_action((new action(
                new moodle_url('#'),
                new pix_icon('i/delete', ''),
                [
                    'data-action' => 'delete-activity',
                    'data-entryid' => ':id',
                    'data-entrytitle' => ':entrytitle',
                ],
                false,
                new lang_string('delete'),
            ))
                ->add_callback(static function (\stdClass $row) use ($isproject): bool {
                    return $isproject || $row->entrystatus <= form_entry_persistent::STUDENT_DELETE_MAX_STATUS;
                }));
        }
    }

    /**
     * Get CSS class for each row to make it clickable via JavaScript.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return 'projetvet-entry-row';
    }
}
