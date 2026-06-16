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

namespace mod_projetvet\local\api;

use cache;
use core\invalid_persistent_exception;
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_set;
use moodle_exception;
use stdClass;

/**
 * Class entries - Generic API for form entries
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entries {
    /**
     * Get the form structure for a form set.
     *
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @param int|null $entrystatus The current entry status (optional, for permission hydration)
     * @param \context|null $context The module context (optional, for permission hydration)
     * @return array
     */
    public static function get_form_structure(
        string $formsetidnumber = 'activities',
        ?int $entrystatus = null,
        ?\context $context = null
    ): array {
        $actstructure = cache::make('mod_projetvet', 'activitystructures');
        // Include current language in cache key since field names are translated.
        $cachekey = 'activitystructure_' . $formsetidnumber . '_' . current_language();
        if ($actstructure->get($cachekey)) {
            $structure = $actstructure->get($cachekey);
        } else {
            // Get the form set.
            $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
            if (!$formset) {
                return [];
            }

            $categories = form_cat::get_records(['formsetid' => $formset->get('id')], 'sortorder');
            $fields = form_field::get_records([], 'sortorder');
            $data = [];
            foreach ($categories as $category) {
                $data[$category->get('id')] = (object) [
                    'id' => $category->get('id'),
                    'name' => get_string('category_' . $category->get('idnumber'), 'mod_projetvet'),
                    'description' => $category->get('description'),
                    'capability' => $category->get('capability'),
                    'entrystatus' => $category->get('entrystatus'),
                    'statusmsg' => $category->get('statusmsg'),
                    'fields' => [],
                ];
            }
            foreach ($fields as $field) {
                // Only include fields for categories in this form set.
                if (isset($data[$field->get('categoryid')])) {
                    // Decode configdata once here so all consumers get it as an array.
                    $configdata = $field->get('configdata');
                    if (is_string($configdata)) {
                        $configdata = json_decode(stripslashes($configdata), true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $configdata = [];
                        }
                    } else {
                        $configdata = (array) $configdata;
                    }

                    $data[$field->get('categoryid')]->fields[] = (object) [
                        'id' => $field->get('id'),
                        'idnumber' => $field->get('idnumber'),
                        'name' => get_string('field_' . $field->get('idnumber'), 'mod_projetvet'),
                        'type' => $field->get('type'),
                        'description' => $field->get('description'),
                        'configdata' => $configdata,
                        'capability' => $field->get('capability'),
                        'entrystatus' => $field->get('entrystatus'),
                        'listorder' => $field->get('listorder'),
                    ];
                }
            }
            $structure = array_values($data);
            $actstructure->set($cachekey, $structure);
        }

        // Hydrate canview and canedit properties if entrystatus and context are provided.
        if ($entrystatus !== null && $context !== null) {
            $structure = self::hydrate_permissions($structure, $entrystatus, $context);
        }

        return $structure;
    }

    /**
     * Hydrate canview and canedit properties for each category in the structure.
     *
     * Rules:
     * - All users cannot view categories where entrystatus > current entrystatus
     * - Users with mod/projetvet:approve can edit approve and submit categories (unless they have unlock)
     * - Users with mod/projetvet:submit can only edit submit categories
     * - Users with mod/projetvet:unlock can only edit unlock categories
     *
     * @param array $structure The form structure
     * @param int $entrystatus The current entry status
     * @param \context $context The module context
     * @return array The structure with hydrated permissions
     */
    private static function hydrate_permissions(array $structure, int $entrystatus, \context $context): array {
        $hasunlock = has_capability('mod/projetvet:unlock', $context);
        $hasapprove = has_capability('mod/projetvet:approve', $context);
        $hassubmit = has_capability('mod/projetvet:submit', $context);

        foreach ($structure as $category) {
            if ($category->capability === 'all' || $category->capability === 'alledit') {
                // Categories with 'all' capability are always viewable and editable.
                $category->canview = true;
                $category->canedit = true;
                continue;
            }
            // Determine canview: cannot view if category entrystatus is greater than current entrystatus.
            $category->canview = $category->entrystatus <= $entrystatus;

            // Determine canedit based on capability rules.
            $category->canedit = false;

            if ($hasunlock && $category->capability === 'unlock' && $category->entrystatus === $entrystatus) {
                // Users with unlock can only edit unlock categories.
                $category->canedit = true;
            } else if ($hasapprove && $category->entrystatus == $entrystatus) {
                // Users with approve (but not unlock) can edit approve and submit categories.
                $category->canedit = ($category->capability === 'approve');
            } else if (
                $hasapprove && $category->capability === 'submit' &&
                ( $category->entrystatus == ($entrystatus - 1) || $entrystatus === 3 )
            ) {
                // Users with approve can edit submit categories from previous status if setting is enabled.
                $category->canedit = true;
            } else if ($hassubmit && $category->entrystatus == $entrystatus) {
                // Users with only submit can only edit submit categories.
                $category->canedit = ($category->capability === 'submit');
            }
        }

        return $structure;
    }

    /**
     * Get an activity entry
     *
     * @param int $entryid
     * @return stdClass
     */
    public static function get_entry(int $entryid): stdClass {
        $actentry = form_entry::get_record(['id' => $entryid]);
        if (empty($actentry)) {
            throw new moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }

        // Get the form set from the entry to load the correct structure.
        $formset = form_set::get_record(['id' => $actentry->get('formsetid')]);
        $formsetidnumber = $formset ? $formset->get('idnumber') : 'activities';
        $structure = self::get_form_structure($formsetidnumber);

        return self::do_get_entry_content($structure, $actentry);
    }

    /**
     * Entry structure content
     *
     * @param array $actstructure
     * @param form_entry $actentry
     * @return object
     */
    private static function do_get_entry_content(array $actstructure, form_entry $actentry): object {
        $data = form_data::get_records(['entryid' => $actentry->get('id')], 'timecreated');
        $activity = [];

        foreach ($actstructure as $category) {
            $fields = [];
            foreach ($category->fields as $field) {
                $fielddata = null;
                foreach ($data as $dataitem) {
                    if ($dataitem->get('fieldid') == $field->id) {
                        $fielddata = $dataitem;
                        break;
                    }
                }
                $fields[] = (object) [
                    'id' => $field->id,
                    'idnumber' => $field->idnumber,
                    'name' => $field->name,
                    'type' => $field->type,
                    'value' => $fielddata ? $fielddata->get_value() : '',
                    'displayvalue' => $fielddata ? $fielddata->get_display_value() : '',
                ];
            }
            $activity[] = (object) [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'fields' => $fields,
            ];
        }
        $record = (object) [
            'id' => $actentry->get('id'),
            'projetvetid' => $actentry->get('projetvetid'),
            'studentid' => $actentry->get('studentid'),
            'timecreated' => $actentry->get('timecreated'),
            'timemodified' => $actentry->get('timemodified'),
            'usermodified' => $actentry->get('usermodified'),
            'entrystatus' => $actentry->get('entrystatus'),
            'categories' => $activity,
            'canedit' => $actentry->can_edit(),
            'candelete' => $actentry->can_delete(),
        ];
        return $record;
    }

    /**
     * Create an activity entry
     *
     * @param int $projetvetid The projetvet id
     * @param int $studentid The student id
     * @param array $fields The fields
     * @param int $entrystatus The entry status (default 0)
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @param int $parententryid The parent entry id for subset forms (default 0)
     *
     * @return int
     */
    public static function create_entry(
        int $projetvetid,
        int $studentid,
        array $fields,
        int $entrystatus = 0,
        string $formsetidnumber = 'activities',
        int $parententryid = 0
    ): int {
        // Get the form set.
        $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
        if (!$formset) {
            throw new moodle_exception('formsetnotfound', 'projetvet', '', $formsetidnumber);
        }

        // Create the activity entry.
        $entry = new form_entry();
        $entry->set('projetvetid', $projetvetid);
        $entry->set('formsetid', $formset->get('id'));
        $entry->set('studentid', $studentid);
        $entry->set('parententryid', $parententryid);
        $entry->set('entrystatus', $entrystatus);
        $entry->create();
        $entry->save();

        // Create the activity data.
        foreach ($fields as $fieldid => $value) {
            $data = new form_data();
            $data->set('fieldid', $fieldid);
            $data->set('entryid', $entry->get('id'));
            $data->set_value($value);
            $data->create();
            $data->save();
        }

        return $entry->get('id');
    }

    /**
     * Check if user can edit a specific category based on capability and entry status
     *
     * @param object $category The category object with capability and entrystatus properties
     * @param int $currententrystatus The current entry status
     * @param \context $context The module context
     * @return bool
     */
    public static function can_edit_category(object $category, int $currententrystatus, \context $context): bool {
        if ($category->capability == 'approve') {
            // Only users with approve capability can edit approve categories.
            return has_capability('mod/projetvet:approve', $context);
        } else if ($category->capability == 'submit') {
            // Users with submit capability can edit when category entrystatus matches current entry status.
            if (
                has_capability('mod/projetvet:submit', $context) &&
                $category->entrystatus == $currententrystatus
            ) {
                return true;
            }
            // Users with approve capability can edit when category entrystatus is less than current entry status.
            if (
                has_capability('mod/projetvet:approve', $context) &&
                $category->entrystatus < $currententrystatus
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user can view a specific field based on capability
     *
     * @param object $field The field object with capability property
     * @param int $studentid The student ID who owns the entry
     * @param \context $context The module context
     * @return bool
     */
    public static function can_view_field(object $field, int $studentid, \context $context): bool {
        global $USER;

        // If no specific capability is set on the field, everyone can view it.
        if (empty($field->capability)) {
            return true;
        }

        // Check if the field has the 'viewown' capability.
        if ($field->capability === 'viewown') {
            // User must have the viewown capability.
            if (!has_capability('mod/projetvet:viewown', $context)) {
                return false;
            }
            // Additionally, for viewown, the user must be the student who owns the entry.
            return $USER->id == $studentid;
        }

        // Check if the field has the 'viewteacher' capability.
        if ($field->capability === 'viewteacher') {
            // User must have the viewteacher capability.
            if (!has_capability('mod/projetvet:approve', $context)) {
                return false;
            }
            // Additionally, for viewteacher, the user must not be the student who owns the entry.
            return $USER->id !== $studentid;
        }

        // For now all else goes.
        return true;
    }

    /**
     * Check if user can edit a specific field based on capability
     *
     * @param object $category The category object with capability property
     * @param object $context The module context
     * @return bool
     */
    public static function can_edit_field(object $category, $context): bool {
        if ($category->capability == 'alledit') {
            return true;
        }
        if ($category->capability == 'all') {
            if (has_capability('mod/projetvet:approve', $context)) {
                return true;
            }
            if (has_capability('mod/projetvet:submit', $context)) {
                return false;
            }
            return false;
        } else {
            return $category->canedit;
        }
    }

    /**
     * Update an activity entry
     *
     * @param int $entryid The entry id
     * @param array $fields The fields
     * @param int|null $entrystatus The entry status (null = no change)
     * @return void
     */
    public static function update_entry(int $entryid, array $fields, ?int $entrystatus = null): void {

        // Get the entry.
        $entry = form_entry::get_record(['id' => $entryid]);
        if (empty($entry)) {
            throw new moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }

        // Get context for permission checks.
        $cm = get_coursemodule_from_instance('projetvet', $entry->get('projetvetid'));
        $context = \context_module::instance($cm->id);

        // Get the form structure with hydrated permissions to validate field permissions.
        $formset = form_set::get_record(['id' => $entry->get('formsetid')]);
        $currententrystatus = $entry->get('entrystatus');
        $structure = self::get_form_structure($formset->get('idnumber'), $currententrystatus, $context);

        // Build a map of fieldid => category for permission checking.
        $fieldcategorymap = [];
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                $fieldcategorymap[$field->id] = $category;
            }
        }

        // Validate that user can edit each field being submitted.
        foreach ($fields as $fieldid => $value) {
            if (!isset($fieldcategorymap[$fieldid])) {
                continue; // Skip unknown fields.
            }
            $category = $fieldcategorymap[$fieldid];
            if (!$category->canedit) {
                throw new moodle_exception('cannoteditfield', 'projetvet', '', $fieldid);
            }
        }

        // Update entry status if provided.
        if ($entrystatus !== null) {
            $entry->set('entrystatus', $entrystatus);
            $entry->update();
        }

        // Update field values.
        foreach ($fields as $fieldid => $value) {
            $data = form_data::get_record(['entryid' => $entryid, 'fieldid' => $fieldid]);
            if ($data) {
                $data->set_value($value);
                $data->update();
            } else {
                $data = new form_data();
                $data->set('fieldid', $fieldid);
                $data->set('entryid', $entryid);
                $data->set_value($value);
                $data->create();
            }
            $data->save();
        }
    }

    /**
     * Delete an activity entry
     *
     * @param int $entryid The entry id
     * @return bool
     */
    public static function delete_entry(int $entryid): bool {
        $entry = new form_entry($entryid);
        if (empty($entry)) {
            throw new moodle_exception('entry_not_found', 'projetvet', '', $entryid);
        }
        if (!$entry->can_delete()) {
            throw new moodle_exception('cannotdeleteactivity', 'projetvet');
        }
        try {
            // Delete all associated data first.
            $datarecords = form_data::get_records(['entryid' => $entryid]);
            foreach ($datarecords as $data) {
                $data->delete();
            }
            // Then delete the entry.
            $entry->delete();
        } catch (invalid_persistent_exception $e) {
            throw new moodle_exception('cannotdeleteactivity', 'projetvet', '', $e->getMessage());
        }
        return true;
    }

    /**
     * Get the activity entry list for display in tables
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The user id
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @param int $parententryid The parent entry id for subset entries (optional, default 0)
     * @return array
     */
    public static function get_entry_list(
        int $projetvetid,
        int $studentid,
        string $formsetidnumber = 'activities',
        int $parententryid = 0
    ): array {
        $entries = self::get_entries($projetvetid, $studentid, $formsetidnumber, $parententryid);
        $structure = self::get_form_structure($formsetidnumber);

        // Get fields with listorder > 0, sorted by listorder.
        $listfields = [];
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                if ($field->listorder > 0) {
                    $listfields[$field->listorder] = $field;
                }
            }
        }
        ksort($listfields);

        $activitylist = [];
        foreach ($entries->activities as $activity) {
            // Get status message using the API method.
            $statustext = self::get_status_message($activity->entrystatus, $formsetidnumber);

            $activitydata = [
                'id' => $activity->id,
                'fields' => [],
                'entrystatus' => $activity->entrystatus,
                'statustext' => $statustext,
                'timemodified' => $activity->timemodified,
                'canedit' => $activity->canedit,
                'candelete' => $activity->candelete,
            ];

            // Add dynamic fields based on listorder.
            foreach ($listfields as $field) {
                $activitydata['fields'][] = [
                    'idnumber' => $field->idnumber,
                    'name' => $field->name,
                    'value' => self::get_entry_field_value($activity, $field->idnumber),
                    'displayvalue' => self::get_entry_field_value($activity, $field->idnumber, true),
                ];
            }

            $activitylist[] = $activitydata;
        }

        return [
            'activities' => $activitylist,
            'listfields' => array_values($listfields),
        ];
    }

    /**
     * Get the activity entries
     *
     * @param int $projetvetid The projetvet instance id
     * @param int $studentid The user id
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @param int $parententryid The parent entry id for subset entries (optional, default 0)
     * @return stdClass
     */
    public static function get_entries(
        int $projetvetid,
        int $studentid,
        string $formsetidnumber = 'activities',
        int $parententryid = 0
    ): stdClass {
        $structure = self::get_form_structure($formsetidnumber);

        // Get the form set to filter entries.
        $formset = form_set::get_record(['idnumber' => $formsetidnumber]);
        if (!$formset) {
            return (object) [
                'activities' => [],
                'structure' => $structure,
            ];
        }

        $filter = [
            'studentid' => $studentid,
            'projetvetid' => $projetvetid,
            'formsetid' => $formset->get('id'),
        ];

        // Add parententryid filter if specified.
        if ($parententryid > 0) {
            $filter['parententryid'] = $parententryid;
        }

        $entries = form_entry::get_records($filter);
        $activities = [];
        foreach ($entries as $entry) {
            $activities[] = self::do_get_entry_content($structure, $entry);
        }
        return (object) [
            'activities' => $activities,
            'structure' => $structure,
        ];
    }

    /**
     * Get activity field value across categories
     *
     * @param mixed $activity
     * @param string $fieldidnumber
     * @param bool $displayvalue
     * @return mixed|null
     */
    private static function get_entry_field_value(mixed $activity, string $fieldidnumber, bool $displayvalue = false) {
        foreach ($activity->categories as $category) {
            foreach ($category->fields as $field) {
                if ($field->idnumber === $fieldidnumber) {
                    return $displayvalue ? $field->displayvalue : $field->value;
                }
            }
        }
        return null;
    }

    /**
     * Get all possible entry statuses with their status messages for a formset
     *
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return array Array of entrystatus => status message
     */
    public static function get_all_status_messages(string $formsetidnumber = 'activities'): array {
        $structure = self::get_form_structure($formsetidnumber);
        $statusmessages = [];

        foreach ($structure as $category) {
            if (!isset($statusmessages[$category->entrystatus]) && !empty($category->statusmsg)) {
                $statusmessages[$category->entrystatus] = get_string('statusmsg_' . $category->statusmsg, 'mod_projetvet');
            }
        }

        ksort($statusmessages);
        return $statusmessages;
    }

    /**
     * Get the status message for a given entry status and formset
     *
     * @param int $entrystatus The entry status
     * @param string $formsetidnumber The form set idnumber (default: 'activities')
     * @return string The translated status message or empty string if not found
     */
    public static function get_status_message(int $entrystatus, string $formsetidnumber = 'activities'): string {
        $statusmessages = self::get_all_status_messages($formsetidnumber);
        return $statusmessages[$entrystatus] ?? '';
    }
}
