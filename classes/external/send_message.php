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

namespace mod_projetvet\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_projetvet\local\api\substitutions;
use mod_projetvet\local\messaging;
use stdClass;

/**
 * Send a contact message to students through the standard Moodle messaging.
 *
 * The webservice validates that the caller is a teacher of the module and that
 * each recipient is a student assigned to that teacher. The actual delivery is
 * deferred to an adhoc task, so that the call is fast and the message goes
 * through the standard messaging pipeline (recipient language, preferences,
 * enabled output plugins).
 *
 * @package   mod_projetvet
 * @copyright 2026 Laurent David <laurent@call-learning.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Maximum number of recipients for a single contact request.
     */
    const MAX_RECIPIENTS = 100;

    /**
     * Queue a contact message to the given students.
     *
     * @param int $cmid Course module ID.
     * @param int $projetvetid Projetvet instance ID.
     * @param array $studentids Recipient student user IDs (or an empty array to
     *                          address all students of the caller).
     * @param string $subjectkey Language string key for the subject.
     * @param string $bodykey Language string key for the body.
     * @return stdClass Object holding success flag, message and count.
     */
    public static function execute(
        int $cmid,
        int $projetvetid,
        array $studentids = [],
        string $subjectkey = 'contactactivity',
        string $bodykey = 'contactactivity'
    ): stdClass {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'cmid' => $cmid,
                'projetvetid' => $projetvetid,
                'studentids' => $studentids,
                'subjectkey' => $subjectkey,
                'bodykey' => $bodykey,
            ]
        );

        $cm = get_coursemodule_from_id('projetvet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/projetvet:approve', $context);

        if ((int) $cm->instance !== $params['projetvetid']) {
            throw new \invalid_parameter_exception('The projetvet instance does not match the course module.');
        }

        if (!self::is_known_message_key($params['subjectkey']) || !self::is_known_message_key($params['bodykey'])) {
            throw new \moodle_exception('invalidcontactmessage', 'mod_projetvet');
        }

        // Determine the recipients.
        if (empty($params['studentids'])) {
            // No explicit list: address all students of the caller.
            $recipientids = substitutions::get_students_followed_by_tutor($USER->id, $params['projetvetid']);
        } else {
            $recipientids = array_map('intval', $params['studentids']);
            $recipientids = array_values(array_unique($recipientids));
        }

        if (empty($recipientids)) {
            throw new \moodle_exception('nouseridprovided', 'mod_projetvet');
        }
        if (count($recipientids) > self::MAX_RECIPIENTS) {
            throw new \moodle_exception('toomanyrecipients', 'mod_projetvet', (object)['count' => count($recipientids)]);
        }

        // Each recipient must be a student assigned to the caller, or followed as a substitute.
        $allowedids = substitutions::get_students_followed_by_tutor($USER->id, $params['projetvetid']);
        $unauthorized = array_diff($recipientids, $allowedids);
        if (!empty($unauthorized)) {
            throw new \moodle_exception('invalidrecipient', 'mod_projetvet');
        }

        messaging::schedule_send(
            $params['cmid'],
            $USER->id,
            $recipientids,
            $params['subjectkey'],
            $params['bodykey']
        );

        $count = count($recipientids);
        return (object) [
            'result' => true,
            'message' => get_string('messagesent', 'mod_projetvet', (object)['count' => $count]),
            'count' => $count,
        ];
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_REQUIRED),
            'projetvetid' => new external_value(PARAM_INT, 'Projetvet instance ID', VALUE_REQUIRED),
            'studentids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Student user ID'),
                'Student user IDs to contact (empty to address all students of the caller)',
                VALUE_DEFAULT,
                []
            ),
            'subjectkey' => new external_value(PARAM_TEXT, 'Language string key for the subject', VALUE_DEFAULT, 'contactactivity'),
            'bodykey' => new external_value(PARAM_TEXT, 'Language string key for the body', VALUE_DEFAULT, 'contactactivity'),
        ]);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'Success', VALUE_REQUIRED),
            'message' => new external_value(PARAM_TEXT, 'Result message', VALUE_REQUIRED),
            'count' => new external_value(PARAM_INT, 'Number of recipients', VALUE_REQUIRED),
        ]);
    }

    /**
     * Check that a language string key is a known contact message key.
     *
     * This is a whitelisted list of keys; any other value is rejected, so that
     * arbitrary strings cannot be used to build a message.
     *
     * @param string $key The language string key
     * @return bool
     */
    protected static function is_known_message_key(string $key): bool {
        $knownkeys = [
            'contactactivity',
            'contactf2f',
        ];
        return in_array($key, $knownkeys, true);
    }
}
