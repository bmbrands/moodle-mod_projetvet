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

namespace mod_projetvet\local;

use core\message\message;
use moodle_url;

/**
 * Notifications helper class
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {
    /**
     * Get the tutors to notify about the follow-up of a student.
     *
     * While a substitution is in effect the substitute is notified instead of the principal tutor;
     * the principal tutor also receives the notifications when the manager chose to keep them
     * informed for that substitution. Otherwise the principal tutor is notified.
     *
     * @param int $studentid The student ID
     * @param int $projetvetid The projetvet instance ID
     * @return \stdClass[] The tutors to notify, indexed by user id
     */
    public static function get_tutor_recipients(int $studentid, int $projetvetid): array {
        $primary = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $projetvetid);
        $substitution = \mod_projetvet\local\api\substitutions::get_substitution_in_effect($studentid, $projetvetid);

        $recipients = [];
        if ($substitution) {
            $substitute = \core_user::get_user($substitution->get('substitutetutorid'));
            if ($substitute) {
                $recipients[$substitute->id] = $substitute;
            }
            if ($primary && $substitution->get('notifyprimary')) {
                $recipients[$primary->id] = $primary;
            }
        } else if ($primary) {
            $recipients[$primary->id] = $primary;
        }
        return $recipients;
    }

    /**
     * Send notification to the tutor(s) when student submits entry
     *
     * The recipients follow the substitution rule, see {@see self::get_tutor_recipients()}.
     *
     * @param int $entryid The entry ID
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @param string $messagekey The language string key for the message
     * @return bool True if notification sent successfully
     */
    public static function send_tutor_notification(int $entryid, int $studentid, int $cmid, string $messagekey): bool {
        global $DB;

        // Get the student user object.
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $projetvetid = $cm->instance;

        // Get the tutors to notify for this student.
        $tutors = self::get_tutor_recipients($studentid, $projetvetid);
        if (empty($tutors)) {
            // No tutor found - cannot send notification.
            return false;
        }

        // Build the link to the entry.
        $linkurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
            'studentid' => $studentid,
        ]);

        // Prepare the message data.
        $messagedata = new \stdClass();
        $messagedata->studentname = fullname($student);
        $messagedata->link = $linkurl->out(false);

        // Get the message HTML from language string.
        $messagehtml = get_string($messagekey, 'mod_projetvet', $messagedata);

        $sent = false;
        foreach ($tutors as $tutor) {
            // Create the message object.
            $message = new message();
            $message->component = 'mod_projetvet';
            $message->name = 'entry_action_required';
            $message->userfrom = $student;
            $message->userto = $tutor;
            $message->subject = get_string($messagekey . '_subject', 'mod_projetvet');
            $message->fullmessage = html_to_text($messagehtml);
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $messagehtml;
            $message->smallmessage = get_string('notification_smallmessage', 'mod_projetvet', $messagedata);
            $message->notification = 1;
            $message->contexturl = $linkurl->out(false);
            $message->contexturlname = get_string('openprojetvet', 'mod_projetvet');
            $message->courseid = $cm->course;

            // Send the message.
            $sent = (message_send($message) !== false) || $sent;
        }
        return $sent;
    }

    /**
     * Send notification to student when tutor takes action
     *
     * @param int $entryid The entry ID
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @param string $messagekey The language string key for the message
     * @return bool True if notification sent successfully
     */
    public static function send_student_notification(int $entryid, int $studentid, int $cmid, string $messagekey): bool {
        global $DB;

        // Get the student user object.
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $projetvetid = $cm->instance;

        // Send on behalf of the active tutor (the substitute while a substitution is in effect).
        $tutor = \mod_projetvet\local\api\substitutions::get_active_tutor($studentid, $projetvetid);
        if (!$tutor) {
            // No tutor found - send from noreply.
            $tutor = \core_user::get_noreply_user();
        }

        // Build the link to the entry.
        $linkurl = new moodle_url('/mod/projetvet/view.php', [
            'id' => $cmid,
        ]);

        // Prepare the message data.
        $messagedata = new \stdClass();
        $messagedata->link = $linkurl->out(false);

        // Get the message HTML from language string.
        $messagehtml = get_string($messagekey, 'mod_projetvet', $messagedata);

        // Create the message object.
        $message = new message();
        $message->component = 'mod_projetvet';
        $message->name = 'entry_action_required';
        $message->userfrom = $tutor;
        $message->userto = $student;
        $message->subject = get_string($messagekey . '_subject', 'mod_projetvet');
        $message->fullmessage = html_to_text($messagehtml);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $messagehtml;
        $message->smallmessage = get_string('notification_smallmessage_student', 'mod_projetvet');
        $message->notification = 1;
        $message->contexturl = $linkurl->out(false);
        $message->contexturlname = get_string('openprojetvet', 'mod_projetvet');
        $message->courseid = $cm->course;

        // Send the message.
        return message_send($message) !== false;
    }
}
