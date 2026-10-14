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

namespace mod_projetvet;

use mod_projetvet\local\importer\fields_json_importer;
use mod_projetvet\local\persistent\form_field;

/**
 * Setup routines
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /** @var array Default form sets to create */
    public const DEFAULT_FORM_SETS = [
        'activities' => [
            'name' => 'Activities',
            'description' => 'Activity form fields',
            'sortorder' => 0,
            'jsonfile' => 'default_activity_form.json',
        ],
        'facetoface' => [
            'name' => 'Face-to-face sessions',
            'description' => 'Face-to-face session forms',
            'sortorder' => 1,
            'jsonfile' => 'default_facetoface_form.json',
        ],
        'carnet_cas' => [
            'name' => 'Carnet de cas',
            'description' => 'Carnet de cas cliniques',
            'sortorder' => 2,
            'jsonfile' => 'default_carnet_cas_form.json',
        ],
        'thesis' => [
            'name' => 'Thesis',
            'description' => 'Thesis subject form',
            'sortorder' => 3,
            'jsonfile' => 'default_thesis_form.json',
        ],
        'mobility' => [
            'name' => 'International Mobility',
            'description' => 'International mobility form',
            'sortorder' => 4,
            'jsonfile' => 'default_mobility_form.json',
        ],
        'teacherinfo' => [
            'name' => 'Teacher Information',
            'description' => 'Teacher practical information form',
            'sortorder' => 5,
            'jsonfile' => 'default_teacherinfo_form.json',
        ],
        'archive' => [
            'name' => 'Archive',
            'description' => 'Archive of projects already validated with the previous system',
            'sortorder' => 6,
            'jsonfile' => 'default_archive_form.json',
        ],
        'archive_project' => [
            'name' => 'Archived project',
            'description' => 'A project already validated with the previous system',
            'sortorder' => 7,
            'jsonfile' => 'default_archive_project_form.json',
        ],
    ];
    /**
     * Create the default activity fields.
     *
     * @return void
     */
    public static function create_default_activities() {
        self::import_form_sets(array_keys(self::DEFAULT_FORM_SETS));
    }

    /**
     * Import (create or update) the given default form sets and the tagselect lookup data.
     *
     * @param string[] $idnumbers The form set idnumbers, keys of {@see self::DEFAULT_FORM_SETS}
     * @return void
     */
    public static function import_form_sets(array $idnumbers): void {
        global $CFG;

        foreach ($idnumbers as $idnumber) {
            if (!isset(self::DEFAULT_FORM_SETS[$idnumber])) {
                debugging("Unknown form set: {$idnumber}", DEBUG_DEVELOPER);
                continue;
            }
            $config = self::DEFAULT_FORM_SETS[$idnumber];
            $jsonfile = $CFG->dirroot . '/mod/projetvet/data/' . $config['jsonfile'];

            if (!file_exists($jsonfile)) {
                debugging("JSON file not found: {$config['jsonfile']}", DEBUG_DEVELOPER);
                continue;
            }

            $importer = new fields_json_importer(
                $idnumber,
                $config['name'],
                $config['description'],
                $config['sortorder']
            );
            $importer->import($jsonfile);
        }

        // Import field lookup data for tagselect fields.
        fields_json_importer::import_field_lookup_data();

        \cache::make('mod_projetvet', 'activitystructures')->purge();
    }
}
