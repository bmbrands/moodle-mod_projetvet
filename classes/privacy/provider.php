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
    \core_privacy\local\request\plugin\provider,
    // This plugin stores a sitewide user preference.
    \core_privacy\local\request\user_preference_provider
{
    /** @var string[] The user fields of the substitutions table */
    const SUBSTITUTION_USER_FIELDS = ['studentid', 'primarytutorid', 'substitutetutorid', 'createdby', 'closedby'];

    /**
     * Returns metadata about this system.
     *
     * @param collection $items The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_user_preference(
            'projetvet_tutor_info',
            'privacy:metadata:preference:projetvet_tutor_info'
        );

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

        // The projetvet_substitutions table stores the temporary substitute tutors of students.
        $items->add_database_table('projetvet_substitutions', [
            'projetvetid' => 'privacy:metadata:projetvet_substitutions:projetvetid',
            'studentid' => 'privacy:metadata:projetvet_substitutions:studentid',
            'primarytutorid' => 'privacy:metadata:projetvet_substitutions:primarytutorid',
            'substitutetutorid' => 'privacy:metadata:projetvet_substitutions:substitutetutorid',
            'startdate' => 'privacy:metadata:projetvet_substitutions:startdate',
            'enddate' => 'privacy:metadata:projetvet_substitutions:enddate',
            'reason' => 'privacy:metadata:projetvet_substitutions:reason',
            'status' => 'privacy:metadata:projetvet_substitutions:status',
            'timeclosed' => 'privacy:metadata:projetvet_substitutions:timeclosed',
            'closedby' => 'privacy:metadata:projetvet_substitutions:closedby',
            'createdby' => 'privacy:metadata:projetvet_substitutions:createdby',
            'timecreated' => 'privacy:metadata:projetvet_substitutions:timecreated',
        ], 'privacy:metadata:projetvet_substitutions');

        return $items;
    }

    /**
     * Export the plugin's user preferences.
     *
     * @param int $userid The user whose preferences are being exported.
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        $value = get_user_preferences('projetvet_tutor_info', null, $userid);
        if ($value !== null) {
            writer::export_user_preference(
                'mod_projetvet',
                'projetvet_tutor_info',
                $value,
                get_string('privacy:metadata:preference:projetvet_tutor_info', 'mod_projetvet')
            );
        }
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

        // Find all projetvet activities where the user is involved in a substitution.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {projetvet_substitutions} s ON s.projetvetid = cm.instance
                 WHERE s.studentid = :studentid
                    OR s.primarytutorid = :primarytutorid
                    OR s.substitutetutorid = :substitutetutorid
                    OR s.createdby = :createdby
                    OR s.closedby = :closedby";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'projetvet',
            'studentid' => $userid,
            'primarytutorid' => $userid,
            'substitutetutorid' => $userid,
            'createdby' => $userid,
            'closedby' => $userid,
        ]);

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

        // Find users involved in substitutions.
        foreach (self::SUBSTITUTION_USER_FIELDS as $field) {
            $sql = "SELECT s.{$field}
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                      JOIN {projetvet_substitutions} s ON s.projetvetid = cm.instance
                     WHERE cm.id = :instanceid";
            $userlist->add_from_sql($field, $sql, $params);
        }
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

        static::export_substitutions_for_user($userid, $context, $projetvet);
    }

    /**
     * Export the substitutions a user is involved in (as student, tutor, creator or closer).
     *
     * @param int $userid The user ID.
     * @param context $context The context.
     * @param object $projetvet The projetvet instance.
     */
    protected static function export_substitutions_for_user(int $userid, context $context, object $projetvet) {
        global $DB;

        $select = 'projetvetid = :projetvetid AND (' . implode(' OR ', array_map(
            fn($field) => "{$field} = :{$field}",
            self::SUBSTITUTION_USER_FIELDS
        )) . ')';
        $params = ['projetvetid' => $projetvet->id] + array_fill_keys(self::SUBSTITUTION_USER_FIELDS, $userid);
        $records = $DB->get_records_select('projetvet_substitutions', $select, $params, 'timecreated');
        if (empty($records)) {
            return;
        }

        $substitutions = [];
        foreach ($records as $record) {
            $substitutions[] = (object) [
                'student' => transform::user($record->studentid),
                'primarytutor' => transform::user($record->primarytutorid),
                'substitutetutor' => transform::user($record->substitutetutorid),
                'startdate' => transform::date($record->startdate),
                'enddate' => $record->enddate ? transform::date($record->enddate) : '',
                'reason' => $record->reason,
                'status' => \mod_projetvet\local\persistent\substitution::get_status_string_for((int) $record->status),
                'timeclosed' => $record->timeclosed ? transform::datetime($record->timeclosed) : '',
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:metadata:projetvet_substitutions', 'mod_projetvet')],
            (object) ['substitutions' => $substitutions]
        );
    }

    /**
     * Delete the substitutions of users in a projetvet instance.
     *
     * The substitutions of the users as student, principal tutor or substitute are deleted; the users
     * are removed from the substitutions they created or closed.
     *
     * @param int $projetvetid The projetvet instance id.
     * @param int[] $userids The user ids.
     */
    protected static function delete_substitutions_for_users(int $projetvetid, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }
        // A named parameter can only be used once in a query: build one IN clause per field.
        $conditions = [];
        $params = ['projetvetid' => $projetvetid];
        foreach (['studentid', 'primarytutorid', 'substitutetutorid'] as $field) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, $field);
            $conditions[] = "{$field} {$usersql}";
            $params += $userparams;
        }
        $DB->delete_records_select(
            'projetvet_substitutions',
            'projetvetid = :projetvetid AND (' . implode(' OR ', $conditions) . ')',
            $params
        );

        foreach (['createdby', 'closedby'] as $field) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, $field);
            $DB->set_field_select(
                'projetvet_substitutions',
                $field,
                0,
                "projetvetid = :projetvetid AND {$field} {$usersql}",
                ['projetvetid' => $projetvetid] + $userparams
            );
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

        // Delete all substitutions.
        $DB->delete_records('projetvet_substitutions', ['projetvetid' => $cm->instance]);
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

            static::delete_substitutions_for_users($cm->instance, [$userid]);
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

        static::delete_substitutions_for_users($cm->instance, $userids);
    }
}
