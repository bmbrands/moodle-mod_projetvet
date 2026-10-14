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
use mod_projetvet\local\api\archive;
use mod_projetvet\local\persistent\form_field;
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
    /** @var int The archived project form set listed with the entries (0 for none) */
    protected int $archiveformsetid = 0;

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
            {$entityentryalias}.timemodified, {$entityentryalias}.formsetid");

        // Filter by projetvet instance, student, and formset.
        $paramprojetvet = database::generate_param_name();
        $paramstudent = database::generate_param_name();
        $paramformset = database::generate_param_name();

        // Determine status filter based on user role.
        $isteacher = $studentid != $USER->id;
        $statusfilter = $isteacher ? "{$entityentryalias}.entrystatus > 0" : "{$entityentryalias}.entrystatus >= 0";

        $formsetsql = "{$entityentryalias}.formsetid = :{$paramformset} AND {$statusfilter}";
        $params = [
            $paramprojetvet => $projetvetid,
            $paramstudent => $studentid,
            $paramformset => $formset->id,
        ];

        // The projects already validated with the previous system ("Projet repris") are
        // summary records listed with the activities once the tutor validated the archive.
        $archiveformset = $this->get_archive_project_formset($formsetidnumber);
        $this->archiveformsetid = $archiveformset ? (int) $archiveformset->id : 0;
        if ($archiveformset) {
            $paramarchiveformset = database::generate_param_name();
            $paramarchivestatus = database::generate_param_name();
            $formsetsql = "(({$formsetsql}) OR ({$entityentryalias}.formsetid = :{$paramarchiveformset}
                AND {$entityentryalias}.entrystatus = :{$paramarchivestatus}))";
            $params[$paramarchiveformset] = $archiveformset->id;
            $params[$paramarchivestatus] = archive::STATUS_VALIDATED;
        }

        $this->add_base_condition_sql(
            "{$entityentryalias}.projetvetid = :{$paramprojetvet}
            AND {$entityentryalias}.studentid = :{$paramstudent}
            AND {$formsetsql}",
            $params
        );

        $this->add_columns($formset->id, $archiveformset ? $archiveformset->id : 0);
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
     * Get the archived project form set listed together with the given form set.
     *
     * @param string $formsetidnumber
     * @return \stdClass|null The archived project form set record, null when not applicable
     */
    protected function get_archive_project_formset(string $formsetidnumber): ?\stdClass {
        global $DB;

        if ($formsetidnumber !== 'activities') {
            return null;
        }
        return $DB->get_record('projetvet_form_set', ['idnumber' => archive::PROJECT_FORMSET]) ?: null;
    }

    /**
     * Get the archived project fields displayed in the activity columns, keyed by activity field idnumber.
     *
     * Archived project fields name the activity field they are shown with in their configdata
     * ("reportfield").
     *
     * @param int $archiveformsetid
     * @return \stdClass[]
     */
    protected function get_archive_report_fields(int $archiveformsetid): array {
        global $DB;

        $fields = $DB->get_records_sql("
            SELECT ff.*
            FROM {projetvet_form_field} ff
            JOIN {projetvet_form_cat} fc ON ff.categoryid = fc.id
            WHERE fc.formsetid = :formsetid
        ", ['formsetid' => $archiveformsetid]);

        $reportfields = [];
        foreach ($fields as $field) {
            $configdata = json_decode(stripslashes((string) $field->configdata), true);
            if (!empty($configdata['reportfield'])) {
                $reportfields[$configdata['reportfield']] = $field;
            }
        }
        return $reportfields;
    }

    /**
     * Add columns to the report
     *
     * @param int $formsetid
     * @param int $archiveformsetid The archived project form set listed with the entries (0 for none)
     */
    protected function add_columns(int $formsetid, int $archiveformsetid = 0): void {
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

        $archivefields = $archiveformsetid ? $this->get_archive_report_fields($archiveformsetid) : [];

        // Add dynamic columns for each field.
        foreach ($fields as $field) {
            $this->add_field_column($field, $entityentryalias, $archivefields[$field->idnumber] ?? null);
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
     * @param \stdClass|null $archivefield The archived project field shown in the same column
     */
    protected function add_field_column(\stdClass $field, string $entityentryalias, ?\stdClass $archivefield = null): void {
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

        $valuesql = "{$dataalias}.{$datacolumn}";
        $fieldidsql = "{$dataalias}.fieldid";
        if ($archivefield && $archivefield->type === $field->type) {
            // Archived project rows take their value from the matching archived project field.
            $archivedataalias = 'archivedata_' . $field->id;
            $this->add_join("LEFT JOIN {projetvet_form_data} {$archivedataalias}
                ON {$archivedataalias}.entryid = {$entityentryalias}.id
                AND {$archivedataalias}.fieldid = {$archivefield->id}");
            $valuesql = "COALESCE({$valuesql}, {$archivedataalias}.{$datacolumn})";
            $fieldidsql = "COALESCE({$fieldidsql}, {$archivedataalias}.fieldid)";
        }

        // Get report parameters for use in callback.
        $studentid = $this->get_parameter('studentid', 0, PARAM_INT);
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // Create column.
        $column = (new column(
            'field_' . $field->idnumber,
            new lang_string('field_' . $field->idnumber . '_column', 'mod_projetvet'),
            'form_entry'
        ))
            ->add_field($valuesql, $fieldalias)
            ->add_field($fieldidsql, $fieldalias . '_fieldid')
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

        // Archived project ("Projet repris") rows are summary records only: they cannot be opened, edited or deleted.
        $archiveformsetid = $this->archiveformsetid;
        $isentry = static function (\stdClass $row) use ($archiveformsetid): bool {
            return (int) $row->formsetid !== $archiveformsetid;
        };

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
        ))->add_callback($isentry));

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
            ))->add_callback($isentry));
        }

        // Delete action (for students viewing their own entries with entrystatus 0).
        if (!$isteacher) {
            $this->add_action((new action(
                new moodle_url('#'),
                new pix_icon('i/delete', ''),
                [
                    'data-action' => 'delete-activity',
                    'data-entryid' => ':id',
                ],
                false,
                new lang_string('delete'),
            ))
                ->add_callback(static function (\stdClass $row) use ($isentry): bool {
                    return $isentry($row) && ($row->entrystatus == 0 || $row->entrystatus == 1);
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
        if ($this->archiveformsetid && (int) $row->formsetid === $this->archiveformsetid) {
            // Archived project ("Projet repris") rows cannot be opened.
            return 'projetvet-archived-row';
        }
        return 'projetvet-entry-row';
    }
}
