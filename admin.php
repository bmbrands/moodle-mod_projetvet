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
 * Admin view for Projetvet - Students and Teachers management
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_projetvet\reportbuilder\local\systemreports\admin_students;
use mod_projetvet\reportbuilder\local\systemreports\admin_teachers;
use core_reportbuilder\system_report_factory;

// Course module id.
$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('projetvet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record('projetvet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/projetvet:admin', $context);

\mod_projetvet\event\course_module_viewed::create_from_record($moduleinstance, $cm, $course)->trigger();

$PAGE->set_url('/mod/projetvet/admin.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('admin_page_title', 'mod_projetvet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// Load JavaScript for modal forms.
$PAGE->requires->js_call_amd('mod_projetvet/clickable_rows', 'init');
$PAGE->requires->js_call_amd('mod_projetvet/manage_groups', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_page_heading', 'mod_projetvet'));

// Students report.
echo html_writer::tag('h3', get_string('admin_students_heading', 'mod_projetvet'));

$studentsreport = system_report_factory::create(admin_students::class, $context, '', '', 0, [
    'cmid' => $cm->id,
    'projetvetid' => $moduleinstance->id,
]);

echo $studentsreport->output();

// Add spacing between reports.
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');

// Teachers report.
echo html_writer::tag('h3', get_string('admin_teachers_heading', 'mod_projetvet'));

$teachersreport = system_report_factory::create(admin_teachers::class, $context, '', '', 0, [
    'cmid' => $cm->id,
    'projetvetid' => $moduleinstance->id,
]);

echo $teachersreport->output();

echo $OUTPUT->footer();
