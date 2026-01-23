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
     * Send notification to tutor when student submits entry
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

        // Get the tutor for this student.
        $tutor = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $cmid);
        if (!$tutor) {
            // No tutor found - cannot send notification.
            return false;
        }

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);

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
        return message_send($message) !== false;
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

        // Get the tutor for this student.
        $tutor = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $cmid);
        if (!$tutor) {
            // No tutor found - send from noreply.
            $tutor = \core_user::get_noreply_user();
        }

        // Get course module to build the link.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);

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
