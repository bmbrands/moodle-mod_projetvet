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

namespace mod_projetvet\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for mod_projetvet.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin stores personal user data.
    \core_privacy\local\metadata\provider,
    // This plugin can determine which users have data in a context.
    \core_privacy\local\request\core_userlist_provider,
        // This plugin implements the core request provider.
    \core_privacy\local\request\plugin\provider
{
    /**
     * Returns metadata about this system.
     *
     * @param collection $items The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection {
        // The projetvet_form_entry table stores form entries created by users.
        $items->add_database_table('projetvet_form_entry', [
            'projetvetid' => 'privacy:metadata:projetvet_form_entry:projetvetid',
            'formsetid' => 'privacy:metadata:projetvet_form_entry:formsetid',
            'studentid' => 'privacy:metadata:projetvet_form_entry:studentid',
            'parententryid' => 'privacy:metadata:projetvet_form_entry:parententryid',
            'entrystatus' => 'privacy:metadata:projetvet_form_entry:entrystatus',
            'timecreated' => 'privacy:metadata:projetvet_form_entry:timecreated',
            'timemodified' => 'privacy:metadata:projetvet_form_entry:timemodified',
            'usermodified' => 'privacy:metadata:projetvet_form_entry:usermodified',
        ], 'privacy:metadata:projetvet_form_entry');

        // The projetvet_form_data table stores the actual field data entered by users.
        $items->add_database_table('projetvet_form_data', [
            'fieldid' => 'privacy:metadata:projetvet_form_data:fieldid',
            'entryid' => 'privacy:metadata:projetvet_form_data:entryid',
            'intvalue' => 'privacy:metadata:projetvet_form_data:intvalue',
            'decvalue' => 'privacy:metadata:projetvet_form_data:decvalue',
            'shortcharvalue' => 'privacy:metadata:projetvet_form_data:shortcharvalue',
            'charvalue' => 'privacy:metadata:projetvet_form_data:charvalue',
            'textvalue' => 'privacy:metadata:projetvet_form_data:textvalue',
            'timecreated' => 'privacy:metadata:projetvet_form_data:timecreated',
            'timemodified' => 'privacy:metadata:projetvet_form_data:timemodified',
            'usermodified' => 'privacy:metadata:projetvet_form_data:usermodified',
        ], 'privacy:metadata:projetvet_form_data');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Find all projetvet activities where the user has entries.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {projetvet} p ON p.id = cm.instance
            INNER JOIN {projetvet_form_entry} pe ON pe.projetvetid = p.id
                 WHERE (
                       pe.studentid = :studentid OR
                       pe.usermodified = :usermodified
                 )";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'projetvet',
            'studentid' => $userid,
            'usermodified' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_module) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modulename' => 'projetvet',
        ];

        // Find users who own entries.
        $sql = "SELECT pe.studentid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {projetvet} p ON p.id = cm.instance
                  JOIN {projetvet_form_entry} pe ON pe.projetvetid = p.id
                 WHERE cm.id = :instanceid";

        $userlist->add_from_sql('studentid', $sql, $params);

        // Find users who modified entries.
        $sql = "SELECT pe.usermodified
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {projetvet} p ON p.id = cm.instance
                  JOIN {projetvet_form_entry} pe ON pe.projetvetid = p.id
                 WHERE cm.id = :instanceid";

        $userlist->add_from_sql('usermodified', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Get all projetvet instances in these contexts where the user has data.
        $sql = "SELECT p.*, cm.id AS cmid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {projetvet} p ON p.id = cm.instance
                 WHERE c.id {$contextsql}";

        $params = array_merge($contextparams, ['modname' => 'projetvet']);
        $projetvets = $DB->get_records_sql($sql, $params);

        foreach ($projetvets as $projetvet) {
            $context = context_module::instance($projetvet->cmid);
            static::export_projetvet_data_for_user($userid, $context, $projetvet);
        }
    }

    /**
     * Export all projetvet data for a user in a specific context.
     *
     * @param int $userid The user ID.
     * @param context $context The context.
     * @param object $projetvet The projetvet instance.
     */
    protected static function export_projetvet_data_for_user(int $userid, context $context, object $projetvet) {
        global $DB;

        // Export all entries for this user.
        $entries = $DB->get_records('projetvet_form_entry', [
            'projetvetid' => $projetvet->id,
            'studentid' => $userid,
        ]);

        foreach ($entries as $entry) {
            // Get the form set name for subcontext.
            $formset = $DB->get_record('projetvet_form_set', ['id' => $entry->formsetid]);
            $formsetname = $formset ? $formset->name : 'Unknown';

            $subcontext = [
                get_string('entries', 'mod_projetvet'),
                $formsetname,
                'entry_' . $entry->id,
            ];

            // Export entry data.
            $entrydata = (object) [
                'entrystatus' => $entry->entrystatus,
                'timecreated' => transform::datetime($entry->timecreated),
                'timemodified' => transform::datetime($entry->timemodified),
            ];

            // Get all field data for this entry.
            $fielddata = $DB->get_records('projetvet_form_data', ['entryid' => $entry->id]);
            $fields = [];

            foreach ($fielddata as $data) {
                // Get field information.
                $field = $DB->get_record('projetvet_form_field', ['id' => $data->fieldid]);
                if ($field) {
                    $value = '';
                    // Determine which value field to use based on field type.
                    if (!empty($data->textvalue)) {
                        $value = $data->textvalue;
                    } else if (!empty($data->charvalue)) {
                        $value = $data->charvalue;
                    } else if (!empty($data->shortcharvalue)) {
                        $value = $data->shortcharvalue;
                    } else if ($data->decvalue != 0) {
                        $value = $data->decvalue;
                    } else if ($data->intvalue != 0) {
                        $value = $data->intvalue;
                    }

                    $fields[] = (object) [
                        'fieldname' => $field->name,
                        'fieldtype' => $field->type,
                        'value' => $value,
                        'timemodified' => transform::datetime($data->timemodified),
                    ];
                }
            }

            $entrydata->fields = $fields;

            writer::with_context($context)
                ->export_data($subcontext, $entrydata);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('projetvet', $context->instanceid);
        if (!$cm) {
            return;
        }

        // Get all entries for this projetvet instance.
        $entries = $DB->get_records('projetvet_form_entry', ['projetvetid' => $cm->instance]);

        foreach ($entries as $entry) {
            // Delete form data for this entry.
            $DB->delete_records('projetvet_form_data', ['entryid' => $entry->id]);
        }

        // Delete all entries.
        $DB->delete_records('projetvet_form_entry', ['projetvetid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('projetvet', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Get all entries for this user in this projetvet instance.
            $entries = $DB->get_records('projetvet_form_entry', [
                'projetvetid' => $cm->instance,
                'studentid' => $userid,
            ]);

            foreach ($entries as $entry) {
                // Delete form data for this entry.
                $DB->delete_records('projetvet_form_data', ['entryid' => $entry->id]);
                // Delete the entry itself.
                $DB->delete_records('projetvet_form_entry', ['id' => $entry->id]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('projetvet', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Get all entries for these users.
        $sql = "SELECT id
                  FROM {projetvet_form_entry}
                 WHERE projetvetid = :projetvetid
                   AND studentid {$usersql}";

        $params = array_merge(['projetvetid' => $cm->instance], $userparams);
        $entries = $DB->get_records_sql($sql, $params);

        if (!empty($entries)) {
            $entryids = array_keys($entries);
            [$entrysql, $entryparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);

            // Delete form data for these entries.
            $DB->delete_records_select('projetvet_form_data', "entryid {$entrysql}", $entryparams);

            // Delete the entries.
            $DB->delete_records_select('projetvet_form_entry', "id {$entrysql}", $entryparams);
        }
    }
}
