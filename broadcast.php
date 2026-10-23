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
 * Send a custom broadcast message to a tutor's assigned students.
 *
 * This is a dedicated page (rather than an inline modal) so it can be opened
 * directly from the view page. It is protected with a session key plus
 * capability checks. The tutor selects recipients, composes a subject and body,
 * and submits. Delivery is queued as an adhoc task so the page stays fast.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

global $DB, $USER, $SITE, $PAGE, $OUTPUT;

// Course module id. This page must be reached with a valid activity id; direct
// access (or a wrong id) is denied cleanly rather than failing with a raw
// "missing parameter" error.
$cmid = optional_param('id', 0, PARAM_INT);
$cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, IGNORE_MISSING);
if (!$cm) {
    throw new moodle_exception('invalidaccess', 'error');
}
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record('projetvet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Only tutors who can contact their students may use this page.
require_capability('mod/projetvet:approve', $context);
if (!\mod_projetvet\utils::is_tutor($USER->id)) {
    throw new moodle_exception('accessdenied', 'error');
}

$returnurl = new moodle_url('/mod/projetvet/view.php', ['id' => $cmid]);
$PAGE->set_url(new moodle_url('/mod/projetvet/broadcast.php', ['id' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('broadcast_select_recipients', 'mod_projetvet'));
$PAGE->set_heading(format_string($course->fullname));

// The students this tutor may message.
$allowedids = array_map(
    'intval',
    \mod_projetvet\local\api\substitutions::get_students_followed_by_tutor($USER->id, $moduleinstance->id)
);
$recipientlimit = \mod_projetvet\local\messaging::MAX_RECIPIENTS;

// Fetch the student records for the checkbox list. Only the id and the name
// fields needed by fullname() are selected, to keep the query lightweight.
$studentrecords = [];
if (!empty($allowedids)) {
    [$inids, $idparams] = $DB->get_in_or_equal($allowedids, SQL_PARAMS_NAMED);
    $namefields = implode(',', \core_user\fields::get_name_fields());
    $studentrecords = $DB->get_records_sql(
        "SELECT id, $namefields, deleted
           FROM {user}
          WHERE id $inids AND deleted = 0",
        $idparams
    );
}

$mform = new \mod_projetvet\form\broadcast_form(null, [
    'id' => $cm->id,
    'students' => $studentrecords,
    'returnurl' => $returnurl->out(),
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($mform->is_submitted() && $mform->is_validated()) {
    // CSRF protection before any send (the sesskey is a hidden field in the form).
    require_sesskey();

    $data = $mform->get_data();

    // The students checkbox group submits as students[id] => 0|1. Collect the
    // checked ids (truthy values).
    $studentsdata = (array) ($data->students ?? []);
    $recipientids = [];
    foreach ($studentsdata as $studentid => $checked) {
        if ($checked) {
            $recipientids[] = (int) $studentid;
        }
    }
    $recipientids = array_values(array_unique($recipientids));

    if (empty($recipientids)) {
        throw new moodle_exception('broadcast_no_recipients', 'mod_projetvet');
    }

    // Every recipient must be a student assigned to the caller.
    $unauthorized = array_diff($recipientids, $allowedids);
    if (!empty($unauthorized)) {
        throw new moodle_exception('invalidrecipient', 'mod_projetvet');
    }
    if (count($recipientids) > $recipientlimit) {
        throw new moodle_exception('toomanyrecipients', 'mod_projetvet', (object) ['count' => count($recipientids)]);
    }

    // Sanitize the body for the allowed message format.
    $body = format_text($data->body, FORMAT_HTML, ['context' => $context]);

    \mod_projetvet\local\messaging::schedule_broadcast_send(
        $cmid,
        $USER->id,
        $recipientids,
        trim($data->subject),
        $body
    );

    redirect(
        $returnurl,
        get_string('messagesent', 'mod_projetvet', (object) ['count' => count($recipientids)])
    );
}

echo $OUTPUT->header();
echo $OUTPUT->box(format_module_intro('projetvet', $moduleinstance, $cmid), 'generalbox', 'intro');
echo $OUTPUT->heading(get_string('broadcast_select_recipients', 'mod_projetvet'));

if (empty($allowedids)) {
    echo $OUTPUT->notification(get_string('broadcast_no_students', 'mod_projetvet'), 'notifmessage');
} else {
    $mform->display();
}

echo $OUTPUT->footer();
