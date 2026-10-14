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

namespace mod_projetvet\local\importer;

use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_set;
use mod_projetvet\local\persistent\field_data;

/**
 * Fields JSON importer
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_json_importer {
    /**
     * @var string $formsetidnumber The form set idnumber
     */
    protected $formsetidnumber;

    /**
     * @var string $formsetname The form set name
     */
    protected $formsetname;

    /**
     * @var string $formsetdescription The form set description
     */
    protected $formsetdescription;

    /**
     * @var int $formsetsortorder The form set sortorder
     */
    protected $formsetsortorder;

    /**
     * Constructor
     *
     * @param string $formsetidnumber Form set idnumber
     * @param string $formsetname Form set name
     * @param string $formsetdescription Form set description
     * @param int $formsetsortorder Form set sortorder
     */
    public function __construct(
        string $formsetidnumber,
        string $formsetname = '',
        string $formsetdescription = '',
        int $formsetsortorder = 0
    ) {
        $this->formsetidnumber = $formsetidnumber;
        $this->formsetname = $formsetname ?: ucfirst($formsetidnumber);
        $this->formsetdescription = $formsetdescription;
        $this->formsetsortorder = $formsetsortorder;
    }

    /**
     * Import fields from JSON file
     *
     * @param string $filepath Path to JSON file
     * @return array Statistics array with 'categories' and 'fields' counts
     */
    public function import(string $filepath): array {
        $stats = ['categories' => 0, 'fields' => 0];

        if (!file_exists($filepath)) {
            debugging("File not found: $filepath", DEBUG_DEVELOPER);
            return $stats;
        }

        $jsondata = file_get_contents($filepath);
        $data = json_decode($jsondata, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging("Invalid JSON: " . json_last_error_msg(), DEBUG_DEVELOPER);
            return $stats;
        }

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            debugging("Missing categories array in JSON", DEBUG_DEVELOPER);
            return $stats;
        }

        // Validate uniqueness of idnumbers.
        $validation = $this->validate_idnumbers($data);
        if (!empty($validation['errors'])) {
            foreach ($validation['errors'] as $error) {
                debugging($error, DEBUG_DEVELOPER);
            }
            return $stats;
        }

        // Get or create the form set.
        $formset = form_set::get_record(['idnumber' => $this->formsetidnumber]);
        if (!$formset) {
            $formset = new form_set(0, (object)[
                'idnumber' => $this->formsetidnumber,
                'name' => $this->formsetname,
                'description' => $this->formsetdescription,
                'sortorder' => $this->formsetsortorder,
            ]);
            $formset->create();
        }

        foreach ($data['categories'] as $categoryindex => $categorydata) {
            // Create or get category.
            $category = form_cat::get_record(['idnumber' => $categorydata['idnumber']]);
            if (!$category) {
                $category = new form_cat(0, (object)[
                    'formsetid' => $formset->get('id'),
                    'idnumber' => $categorydata['idnumber'],
                    'name' => $categorydata['name'],
                    'description' => $categorydata['description'] ?? '',
                    'sortorder' => $categoryindex + 1, // Use array index for sortorder.
                    'capability' => $categorydata['capability'] ?? null,
                    'entrystatus' => $categorydata['entrystatus'] ?? 0,
                    'statusmsg' => $categorydata['statusmsg'] ?? null,
                ]);
                $category->create();
                $stats['categories']++;
            } else {
                // Update existing category.
                $category->set('formsetid', $formset->get('id'));
                $category->set('name', $categorydata['name']);
                $category->set('description', $categorydata['description'] ?? '');
                $category->set('sortorder', $categoryindex + 1); // Use array index for sortorder.
                $category->set('statusmsg', $categorydata['statusmsg'] ?? null);
                $category->update();
            }

            // Process fields for this category.
            if (isset($categorydata['fields']) && is_array($categorydata['fields'])) {
                foreach ($categorydata['fields'] as $fieldindex => $fielddata) {
                    $created = $this->import_field($fielddata, $category->get('id'), $fieldindex);
                    if ($created) {
                        $stats['fields']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Validate that all idnumbers are unique within the JSON data
     *
     * @param array $data Decoded JSON data
     * @return array Array with 'errors' key containing validation error messages
     */
    protected function validate_idnumbers(array $data): array {
        $errors = [];
        $categoryidnumbers = [];
        $fieldidnumbers = [];

        foreach ($data['categories'] as $categoryindex => $categorydata) {
            $catidnumber = $categorydata['idnumber'] ?? '';

            // Check category idnumber.
            if (empty($catidnumber)) {
                $errors[] = "Category at index $categoryindex is missing idnumber";
            } else if (isset($categoryidnumbers[$catidnumber])) {
                $errors[] = "Duplicate category idnumber '$catidnumber' found at index $categoryindex " .
                    "(previously at index {$categoryidnumbers[$catidnumber]})";
            } else {
                $categoryidnumbers[$catidnumber] = $categoryindex;
            }

            // Check field idnumbers within this category.
            if (isset($categorydata['fields']) && is_array($categorydata['fields'])) {
                foreach ($categorydata['fields'] as $fieldindex => $fielddata) {
                    $fieldidnumber = $fielddata['idnumber'] ?? '';

                    if (empty($fieldidnumber)) {
                        $errors[] = "Field at index $fieldindex in category '$catidnumber' is missing idnumber";
                    } else if (isset($fieldidnumbers[$fieldidnumber])) {
                        $errors[] = "Duplicate field idnumber '$fieldidnumber' found in category '$catidnumber' " .
                            "at index $fieldindex (previously in category '{$fieldidnumbers[$fieldidnumber]['category']}' " .
                            "at index {$fieldidnumbers[$fieldidnumber]['index']})";
                    } else {
                        $fieldidnumbers[$fieldidnumber] = [
                            'category' => $catidnumber,
                            'index' => $fieldindex,
                        ];
                    }
                }
            }
        }

        return ['errors' => $errors];
    }

    /**
     * Import a single field
     *
     * @param array $fielddata Field data from JSON
     * @param int $categoryid Category ID
     * @param int $fieldindex Field index in array (for sortorder)
     * @return bool True if field was created, false if updated
     */
    protected function import_field(array $fielddata, int $categoryid, int $fieldindex): bool {
        // Create or update field.
        $field = form_field::get_record(['idnumber' => $fielddata['idnumber'], 'categoryid' => $categoryid]);

        $fieldrecord = (object)[
            'idnumber' => $fielddata['idnumber'],
            'name' => $fielddata['name'],
            'type' => $fielddata['type'],
            'description' => $fielddata['description'] ?? '',
            'sortorder' => $fieldindex + 1, // Use array index for sortorder.
            'categoryid' => $categoryid,
            'configdata' => json_encode($fielddata['configdata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'capability' => $fielddata['capability'] ?? null,
            'entrystatus' => $fielddata['entrystatus'] ?? 0,
            'listorder' => $fielddata['listorder'] ?? 0,
        ];

        if ($field) {
            $field->from_record($fieldrecord);
            // Explicitly update categoryid in case field moved between categories.
            $field->set('categoryid', $categoryid);
            $field->update();
            return false;
        } else {
            $field = new form_field(0, $fieldrecord);
            $field->create();
            return true;
        }
    }

    /**
     * Import field lookup data from JSON files
     *
     * @return int Number of records imported
     */
    public static function import_field_lookup_data(): int {
        global $CFG;

        $totalcount = 0;

        // Get all tagselect fields.
        $fields = form_field::get_records(['type' => 'tagselect']);

        if (empty($fields)) {
            return 0;
        }

        foreach ($fields as $field) {
            // Determine which JSON file to use based on idnumber.
            $jsonfile = null;
            if ($field->get('idnumber') === 'competency') {
                $jsonfile = $CFG->dirroot . '/mod/projetvet/data/complist.json';
            } else if (in_array($field->get('idnumber'), ['category', 'archive_category'])) {
                $jsonfile = $CFG->dirroot . '/mod/projetvet/data/categories.json';
            }

            if (!$jsonfile || !file_exists($jsonfile)) {
                continue;
            }

            $json = file_get_contents($jsonfile);
            $data = json_decode($json, true);

            if (empty($data)) {
                continue;
            }

            // Delete existing data for this field.
            field_data::delete_field_data($field->get('id'));

            foreach ($data as $item) {
                $record = new field_data(0, (object)[
                    'fieldid' => $field->get('id'),
                    'uniqueid' => $item['uniqueid'],
                    'itemtype' => $item['type'],
                    'parent' => $item['parent'],
                    'name' => $item['name'],
                    'sortorder' => $item['sortorder'],
                ]);
                $record->create();
                $totalcount++;
            }
        }

        return $totalcount;
    }
}
