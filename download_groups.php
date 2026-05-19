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
 * Download groups as CSV
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\teacher_rating;

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$projetvet = $DB->get_record('projetvet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/projetvet:admin', $context);

// Get all groups for this projetvet instance.
$groups = projetvet_group::get_records(['projetvetid' => $projetvet->id]);

// Build CSV data.
$csvdata = [];

// Find the maximum number of students across all groups.
$maxstudents = 0;
foreach ($groups as $group) {
    $members = group_member::get_records(['groupid' => $group->get('id')]);
    $studentcount = 0;
    foreach ($members as $member) {
        if ($member->get('membertype') === group_member::TYPE_STUDENT) {
            $studentcount++;
        }
    }
    $maxstudents = max($maxstudents, $studentcount);
}

// Build header row.
$headers = ['teacher', 'teacherrating', 'secondaryteacher'];
for ($i = 1; $i <= $maxstudents; $i++) {
    $headers[] = 'student' . $i;
}
$csvdata[] = $headers;

// Build data rows.
foreach ($groups as $group) {
    $row = [];

    // Get teacher (owner).
    $owner = $DB->get_record('user', ['id' => $group->get('ownerid')]);
    $row[] = $owner ? $owner->username : '';

    // Get teacher rating.
    $rating = teacher_rating::get_user_rating($group->get('ownerid'), $projetvet->id);
    $row[] = $rating ? $rating->get('rating') : teacher_rating::RATING_AVERAGE;

    // Get secondary teacher and students.
    $members = group_member::get_records(['groupid' => $group->get('id')]);
    $secondaryteacher = '';
    $students = [];

    foreach ($members as $member) {
        $user = $DB->get_record('user', ['id' => $member->get('userid')]);
        if (!$user) {
            continue;
        }

        if ($member->get('membertype') === group_member::TYPE_SECONDARY_TUTOR) {
            $secondaryteacher = $user->username;
        } else if ($member->get('membertype') === group_member::TYPE_STUDENT) {
            $students[] = $user->username;
        }
    }

    $row[] = $secondaryteacher;

    // Add students.
    foreach ($students as $student) {
        $row[] = $student;
    }

    // Fill remaining student columns with empty strings.
    while (count($row) < count($headers)) {
        $row[] = '';
    }

    $csvdata[] = $row;
}

// Output CSV.
$filename = clean_filename('projetvet_groups_' . $projetvet->name . '_' . date('Y-m-d') . '.csv');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility.
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

foreach ($csvdata as $row) {
    fputcsv($output, $row, ',', '"', '\\');
}

fclose($output);
exit;
