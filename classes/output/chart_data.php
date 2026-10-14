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

namespace mod_projetvet\output;

use mod_projetvet\local\api\archive;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_field;

/**
 * Chart data calculator class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_data {
    /**
     * Get total ECTS credits for a student.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @return int Total ECTS credits
     */
    public static function get_total_ects(int $projetvetid, int $studentid): int {
        global $DB;

        // Get the field ID for 'final_ects'.
        $field = form_field::get_record(['idnumber' => 'final_ects']);
        if (!$field) {
            return 0;
        }

        // Get all entries for this student in this projetvet instance.
        $entries = form_entry::get_records([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
        ]);

        if (empty($entries)) {
            return 0;
        }

        $entryids = array_map(function ($entry) {
            return $entry->get('id');
        }, $entries);

        // Get all form_data records for these entries with the final_ects field.
        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        $params['fieldid'] = $field->get('id');

        $sql = "SELECT SUM(intvalue) as total
                FROM {projetvet_form_data}
                WHERE entryid $insql
                AND fieldid = :fieldid";

        $result = $DB->get_record_sql($sql, $params);
        $total = $result && $result->total ? (int)$result->total : 0;

        // Add the validated ECTS of the projects already validated with the previous system.
        return $total + archive::get_validated_ects($projetvetid, $studentid)['total'];
    }

    /**
     * Get total ECTS credits by rank for a student.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @return array Array with 'rank_a' and 'rank_b' totals
     */
    public static function get_ects_by_rank(int $projetvetid, int $studentid): array {
        global $DB;

        // Get the field IDs we need.
        $ectsfield = form_field::get_record(['idnumber' => 'final_ects']);
        $rankfield = form_field::get_record(['idnumber' => 'rang']);

        if (!$ectsfield || !$rankfield) {
            return ['rank_a' => 0, 'rank_b' => 0];
        }

        // Get all entries for this student.
        $entries = form_entry::get_records([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
        ]);

        if (empty($entries)) {
            return ['rank_a' => 0, 'rank_b' => 0];
        }

        $rankatotal = 0;
        $rankbtotal = 0;

        // For each entry, check rank and sum ECTS.
        foreach ($entries as $entry) {
            // Get rank value.
            $rankdata = form_data::get_record([
                'entryid' => $entry->get('id'),
                'fieldid' => $rankfield->get('id'),
            ]);

            if ($rankdata) {
                $rankvalue = $rankdata->get('intvalue');

                // Get ECTS value.
                $ectsdata = form_data::get_record([
                    'entryid' => $entry->get('id'),
                    'fieldid' => $ectsfield->get('id'),
                ]);

                if ($ectsdata) {
                    $ects = (int)$ectsdata->get('intvalue');

                    if ($rankvalue == 1) { // Rank A.
                        $rankatotal += $ects;
                    } else if ($rankvalue == 2) { // Rank B.
                        $rankbtotal += $ects;
                    }
                }
            }
        }

        // Add the validated ECTS of the projects already validated with the previous system.
        $archived = archive::get_validated_ects($projetvetid, $studentid);

        return [
            'rank_a' => $rankatotal + $archived['rank_a'],
            'rank_b' => $rankbtotal + $archived['rank_b'],
        ];
    }

    /**
     * Get total ECTS credits by category/type for a student.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @param string $categoryidnumber The category idnumber to filter by
     * @return int Total ECTS credits for the category
     */
    public static function get_ects_by_category(int $projetvetid, int $studentid, string $categoryidnumber): int {
        global $DB;

        // Get the field IDs we need.
        $ectsfield = form_field::get_record(['idnumber' => 'final_ects']);
        $categoryfield = form_field::get_record(['idnumber' => 'category']);

        if (!$ectsfield || !$categoryfield) {
            return 0;
        }

        // Get all entries for this student.
        $entries = form_entry::get_records([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
        ]);

        if (empty($entries)) {
            return 0;
        }

        $total = 0;

        // For each entry, check if it has the specified category and sum ECTS.
        foreach ($entries as $entry) {
            // Check category value.
            $categorydata = form_data::get_record([
                'entryid' => $entry->get('id'),
                'fieldid' => $categoryfield->get('id'),
            ]);

            if ($categorydata) {
                $categoryvalue = $categorydata->get_display_value();
                // Check if this matches our target category.
                if (stripos($categoryvalue, $categoryidnumber) !== false) {
                    // Get ECTS value.
                    $ectsdata = form_data::get_record([
                        'entryid' => $entry->get('id'),
                        'fieldid' => $ectsfield->get('id'),
                    ]);

                    if ($ectsdata) {
                        $total += (int)$ectsdata->get('intvalue');
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Get count of completed tutor interviews for a student.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @return int Number of completed interviews
     */
    public static function get_completed_interviews(int $projetvetid, int $studentid): int {
        global $DB;

        // Get entries from the facetoface formset with validated status.
        $sql = "SELECT COUNT(pfe.id)
                FROM {projetvet_form_entry} pfe
                JOIN {projetvet_form_set} pfs ON pfe.formsetid = pfs.id
                WHERE pfe.projetvetid = :projetvetid
                AND pfe.studentid = :studentid
                AND pfs.idnumber = :idnumber
                AND pfe.entrystatus >= :status";

        $count = $DB->count_records_sql($sql, [
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'idnumber' => 'facetoface',
            'status' => 2, // At least submitted status.
        ]);

        return $count;
    }
}
