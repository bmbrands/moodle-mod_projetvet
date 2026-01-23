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
 * View Projetvet instance
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$p = optional_param('p', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('projetvet', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('projetvet', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('projetvet', ['id' => $p], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('projetvet', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Check if viewing a specific student (for teachers/managers).
$studentid = optional_param('studentid', 0, PARAM_INT);

\mod_projetvet\event\course_module_viewed::create_from_record($moduleinstance, $cm, $course)->trigger();

$PAGE->set_url('/mod/projetvet/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Determine if user can view all activities (teacher or manager).
$canviewall = has_capability('mod/projetvet:viewallactivities', $context);

// Get the current group for this activity.
$currentgroup = groups_get_activity_group($cm, true);

// Get the renderer.
$renderer = $PAGE->get_renderer('mod_projetvet');

// Display appropriate view based on capability and context.
if ($canviewall && !$studentid) {
    // Teacher/Manager view: show list of students with submitted entries.
    echo $OUTPUT->header();
    echo $OUTPUT->box(format_module_intro('projetvet', $moduleinstance, $cm->id), 'generalbox', 'intro');

    // Load JavaScript for clickable rows.
    $PAGE->requires->js_call_amd('mod_projetvet/clickable_rows', 'init');

    // Use reportbuilder system report for student list.
    $report = \core_reportbuilder\system_report_factory::create(
        \mod_projetvet\reportbuilder\local\systemreports\students::class,
        $context,
        parameters: [
            'cmid' => $cm->id,
            'projetvetid' => $moduleinstance->id
        ]
    );
    echo $report->output();
} else {
    // Student view or teacher/manager viewing a specific student.
    $viewingstudentid = $studentid ? $studentid : $USER->id;

    // Verify access: teachers can only view students from their groups (unless they have accessallgroups).
    // if ($studentid && $canviewall && $viewingstudentid != $USER->id) {
    //     // Check if teacher has accessallgroups capability.
    //     $hasaccessallgroups = has_capability('moodle/site:accessallgroups', $context);

    //     if (!$hasaccessallgroups) {
    //         // Get the group mode for this activity.
    //         $groupmode = groups_get_activity_groupmode($cm);

    //         // In separate groups mode, verify the student is in an allowed group.
    //         if ($groupmode == SEPARATEGROUPS) {
    //             $allowedstudents = get_enrolled_users(
    //                 $context,
    //                 'mod/projetvet:submit',
    //                 $currentgroup,
    //                 'u.id'
    //             );

    //             $studentids = array_keys($allowedstudents);
    //             if (!in_array($viewingstudentid, $studentids)) {
    //                 // Teacher doesn't have access to this student.
    //                 throw new \moodle_exception('nopermissions', 'error', '', get_string('viewallactivities', 'mod_projetvet'));
    //             }
    //         }
    //     }
    // }

    // Load JavaScript for activity forms.
    $PAGE->requires->js_call_amd('mod_projetvet/projetvet_form', 'init');
    $PAGE->requires->js_call_amd('mod_projetvet/student_info_forms', 'init');

    echo $OUTPUT->header();
    echo $OUTPUT->box(format_module_intro('projetvet', $moduleinstance, $cm->id), 'generalbox', 'intro');

    // Determine if viewer is a teacher viewing a student.
    $canviewall = has_capability('mod/projetvet:viewallactivities', $context);
    $isteacher = $canviewall && $viewingstudentid != $USER->id;


    echo $renderer->render_student_info($moduleinstance, $cm, $context, $viewingstudentid, $isteacher);

    // Render the student view page (entry lists).
    echo $renderer->render_student_view($moduleinstance, $cm, $context, $viewingstudentid, $isteacher);
}

echo $OUTPUT->footer();
