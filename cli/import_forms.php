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

/**
 * Import form structures and field data from JSON files
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_projetvet\local\persistent\form_set;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\field_data;
use mod_projetvet\local\importer\fields_json_importer;

// Get CLI options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'reset' => false,
        'formset' => null,
    ],
    [
        'h' => 'help',
        'r' => 'reset',
        'f' => 'formset',
    ]
);

if ($options['help']) {
    echo "Import form structures and field data from JSON files.

Options:
-h, --help          Print this help
-r, --reset         Delete all existing form data before importing
-f, --formset=NAME  Import only specified form set (see mod_projetvet\setup::DEFAULT_FORM_SETS)

Examples:
# Import all form sets
php import_forms.php

# Reset and reimport all form sets
php import_forms.php --reset

# Import only activities form set
php import_forms.php --formset=activities

# Import only facetoface form set
php import_forms.php --formset=facetoface

# Import only thesis form set
php import_forms.php --formset=thesis

# Import the archive of projects validated with the previous system
php import_forms.php --formset=archive
php import_forms.php --formset=archive_project
";
    exit(0);
}

// Define form sets to import.
$formsets = mod_projetvet\setup::DEFAULT_FORM_SETS;

// Filter by requested form set if specified.
if ($options['formset']) {
    if (!isset($formsets[$options['formset']])) {
        echo "Error: Unknown form set '{$options['formset']}'\n";
        echo "Available form sets: " . implode(', ', array_keys($formsets)) . "\n";
        exit(1);
    }
    $formsets = [$options['formset'] => $formsets[$options['formset']]];
}

// Reset all data if requested.
if ($options['reset']) {
    echo "Resetting all form data...\n";
    $DB->delete_records('projetvet_form_data');
    echo "  Deleted form_data records\n";
    $DB->delete_records('projetvet_form_entry');
    echo "  Deleted form_entry records\n";
    $DB->delete_records('projetvet_field_data');
    echo "  Deleted field_data records\n";
    $DB->delete_records('projetvet_form_field');
    echo "  Deleted form_field records\n";
    $DB->delete_records('projetvet_form_cat');
    echo "  Deleted form_cat records\n";

    // Only delete form sets if resetting everything.
    if (!$options['formset']) {
        $DB->delete_records('projetvet_form_set');
        echo "  Deleted form_set records\n";
    }

    // Clear caches.
    $cache = cache::make('mod_projetvet', 'activitystructures');
    $cache->purge();
    echo "  Cleared cache\n\n";
}

/**
 * Import a form set from JSON file
 *
 * @param string $idnumber Form set idnumber
 * @param array $config Form set configuration
 * @return array Statistics
 */
function import_formset($idnumber, $config) {
    global $CFG;

    // Check if form set already exists.
    $formset = form_set::get_record(['idnumber' => $idnumber]);
    if (!$formset) {
        echo "Creating form set: {$config['name']}\n";
    } else {
        echo "Using existing form set: {$config['name']} (ID: {$formset->get('id')})\n";
    }

    // Create importer and import.
    $importer = new fields_json_importer(
        $idnumber,
        $config['name'],
        $config['description'],
        $config['sortorder']
    );

    $jsonfile = $CFG->dirroot . '/mod/projetvet/data/' . $config['jsonfile'];
    if (!file_exists($jsonfile)) {
        echo "  ERROR: File not found: {$config['jsonfile']}\n";
        return ['categories' => 0, 'fields' => 0];
    }

    $stats = $importer->import($jsonfile);

    return $stats;
}

/**
 * Import field lookup data from JSON files
 *
 * @return int Number of records imported
 */
function import_field_data() {
    // Get all tagselect fields.
    $fields = form_field::get_records(['type' => 'tagselect']);

    if (empty($fields)) {
        echo "No tagselect fields found for data import\n";
        return 0;
    }

    echo "\nImporting field lookup data...\n";

    // Show which fields will be processed.
    foreach ($fields as $field) {
        echo "  Processing field: {$field->get('name')} (idnumber: {$field->get('idnumber')})\n";
    }

    // Use the importer class to import field lookup data.
    $totalcount = fields_json_importer::import_field_lookup_data();

    if ($totalcount > 0) {
        echo "  Imported $totalcount total records\n";
    }

    return $totalcount;
}

// Import form sets.
echo "=== Importing Form Sets ===\n\n";

$totalstats = ['categories' => 0, 'fields' => 0];

foreach ($formsets as $idnumber => $config) {
    $stats = import_formset($idnumber, $config);
    $totalstats['categories'] += $stats['categories'];
    $totalstats['fields'] += $stats['fields'];
    echo "\n";
}

// Import field lookup data.
$fielddata = import_field_data();

// Purge caches to ensure changes take effect.
echo "\n=== Purging Caches ===\n";
$cache = cache::make('mod_projetvet', 'activitystructures');
$cache->purge();
echo "Cleared activity structures cache\n";

// Also purge language cache as form structures include translated strings.
purge_all_caches();
echo "Purged all caches\n";

// Display summary.
echo "\n=== Import Summary ===\n";
echo "Form sets processed: " . count($formsets) . "\n";
echo "Categories created: {$totalstats['categories']}\n";
echo "Fields created: {$totalstats['fields']}\n";
echo "Field data records: $fielddata\n";
echo "\nImport completed successfully!\n";
