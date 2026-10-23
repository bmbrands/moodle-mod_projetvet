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
 * Manage the temporary substitute tutors of the students of a principal tutor.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_projetvet\form\substitution_create_form;
use mod_projetvet\form\substitution_manage_form;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\api\substitutions;

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id and principal tutor.
$id = required_param('id', PARAM_INT);
$tutorid = required_param('tutorid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('projetvet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/projetvet:admin', $context);

if (!in_array($tutorid, array_map('intval', groups::get_all_teachers($cm->id)), true)) {
    throw new moodle_exception('invaliduser', 'error');
}
$tutor = core_user::get_user($tutorid, '*', MUST_EXIST);

$url = new moodle_url('/mod/projetvet/substitutions.php', ['id' => $cm->id, 'tutorid' => $tutorid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('substitutions_page_title', 'mod_projetvet', fullname($tutor)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(
    get_string('assignments_page_heading', 'mod_projetvet'),
    new moodle_url('/mod/projetvet/assignments.php', ['id' => $cm->id])
);
$PAGE->navbar->add(fullname($tutor));

// Confirmed "make definitive" action.
if ($action === 'definitive' && confirm_sesskey()) {
    $substitutionids = array_filter(array_map('intval', explode(',', required_param('ids', PARAM_SEQUENCE))));
    $done = substitutions::make_definitive($cm->instance, $substitutionids);
    redirect(
        $url,
        get_string('substitution_definitive_done', 'mod_projetvet', count($done)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Students of the principal tutor without open substitution can get a substitute.
$open = substitutions::get_open_substitutions_for_primary($tutorid, $cm->instance);
$students = array_diff_key(substitutions::get_primary_students($tutorid, $cm->instance), $open);
$customdata = [
    'cmid' => $cm->id,
    'projetvetid' => $cm->instance,
    'tutorid' => $tutorid,
    'students' => $students,
    'candidates' => substitutions::get_candidate_tutors($cm->id, $cm->instance, $tutorid),
];
$createform = new substitution_create_form($url, $customdata);
$manageform = new substitution_manage_form($url, [
    'cmid' => $cm->id,
    'tutorid' => $tutorid,
    'substitutions' => array_values($open),
]);

if ($data = $createform->get_data()) {
    $created = substitutions::create_substitutions(
        $cm->id,
        $cm->instance,
        substitution_create_form::get_selected_students($data),
        (int) $data->substitutetutorid,
        (int) $data->startdate,
        (int) ($data->enddate ?? 0),
        $data->reason,
        !empty($data->notifyprimary),
        !empty($data->allowovercapacity)
    );
    redirect(
        $url,
        get_string('substitution_created', 'mod_projetvet', count($created)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($data = $manageform->get_data()) {
    $selected = substitution_manage_form::get_selected_substitutions($data);
    switch (substitution_manage_form::get_action($data)) {
        case substitution_manage_form::ACTION_CLOSE:
            $closed = substitutions::close_substitutions($cm->instance, $selected);
            redirect(
                $url,
                get_string('substitution_closed', 'mod_projetvet', count($closed)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case substitution_manage_form::ACTION_EXTEND:
            $extended = substitutions::extend_substitutions($cm->instance, $selected, (int) $data->newenddate);
            redirect(
                $url,
                get_string('substitution_extended', 'mod_projetvet', count($extended)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        case substitution_manage_form::ACTION_DEFINITIVE:
            // Ask for confirmation: the substitute becomes the principal tutor.
            $names = [];
            foreach ($open as $substitution) {
                if (in_array((int) $substitution->get('id'), $selected, true)) {
                    $names[] = get_string('substitution_definitive_item', 'mod_projetvet', (object) [
                        'student' => fullname(core_user::get_user($substitution->get('studentid'))),
                        'substitute' => fullname(core_user::get_user($substitution->get('substitutetutorid'))),
                    ]);
                }
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('substitutions_page_title', 'mod_projetvet', fullname($tutor)));
            echo $OUTPUT->confirm(
                get_string('substitution_definitive_confirm', 'mod_projetvet') . html_writer::alist(array_map('s', $names)),
                new moodle_url($url, ['action' => 'definitive', 'ids' => implode(',', $selected), 'sesskey' => sesskey()]),
                $url
            );
            echo $OUTPUT->footer();
            die();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('substitutions_page_title', 'mod_projetvet', fullname($tutor)));
echo $OUTPUT->box(get_string('substitutions_page_intro', 'mod_projetvet'), 'generalbox mb-3');

$renderer = $PAGE->get_renderer('mod_projetvet');
echo $renderer->render_substitutions_page(
    new \mod_projetvet\output\substitutions_page($cm, $tutor, $createform->render(), $manageform->render())
);

echo $OUTPUT->footer();
