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

namespace mod_projetvet;

/**
 * Utils class
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Process a filter and return its value.
     *
     * Filters are dynamic data placeholders that can be used in HTML fields.
     *
     * @param string $filter The filter name (e.g., 'gettutor')
     * @param int $studentid The student ID
     * @param int $cmid The course module ID
     * @return string The filter value
     */
    public static function get_filter(string $filter, int $studentid, int $cmid): string {
        switch ($filter) {
            case 'gettutor':
                $tutor = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $cmid);
                if ($tutor) {
                    return fullname($tutor);
                }
                return get_string('no_tutor_found', 'mod_projetvet');

            case 'getsuggestedectsfile':
                return self::get_ects_guide_url();

            case 'hoursects':
                $hoursperects = get_config('mod_projetvet', 'hours_per_ects');
                return $hoursperects ?: '30';

            default:
                return '';
        }
    }

    /**
     * Get users with a specific role in the module context.
     *
     * @param string $roleshortname The role shortname
     * @param int $cmid The course module ID
     * @return array Array of user objects
     */
    public static function get_users_with_role(string $roleshortname, int $cmid): array {
        global $DB;

        // Get the role ID.
        $role = $DB->get_record('role', ['shortname' => $roleshortname]);
        if (!$role) {
            return [];
        }

        // Get the course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return [];
        }

        $context = \context_module::instance($cm->id);

        // Get all users with this role in this context.
        $users = get_role_users($role->id, $context, true);

        return $users ?: [];
    }

    /**
     * Get the URL for the ECTS attribution guide PDF file.
     *
     * @return string The URL to the PDF file or empty string if not set
     */
    public static function get_ects_guide_url(): string {
        global $CFG;

        $syscontext = \context_system::instance();
        $component = 'mod_projetvet';
        $filearea = 'ectsguide';
        $itemid = 0;

        // Check if a file has been uploaded.
        $fs = get_file_storage();
        $files = $fs->get_area_files($syscontext->id, $component, $filearea, $itemid, 'filename', false);

        if (empty($files)) {
            return '#';
        }

        // Get the first (and should be only) file.
        $file = reset($files);

        $url = \moodle_url::make_pluginfile_url(
            $syscontext->id,
            $component,
            $filearea,
            $itemid,
            $file->get_filepath(),
            $file->get_filename()
        );

        return $url->out();
    }

    /**
     * Get a custom user profile field value for a student.
     *
     * @param int $userid The user ID
     * @param string $fieldshortname The shortname of the custom profile field (e.g., 'promotion')
     * @return string The field value or empty string if not found
     */
    public static function get_user_profile_field(int $userid, string $fieldshortname): string {
        global $CFG;
        // Load the custom profile fields for the user.
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $customfields = profile_user_record($userid, false);

        if (isset($customfields->{$fieldshortname})) {
            return $customfields->{$fieldshortname};
        }

        return '';
    }

    /**
     * Get the first cohort name for a user.
     *
     * Returns the name of the first cohort the user belongs to.
     * This is used to determine the year in course.
     *
     * @param int $userid The user ID
     * @return string The cohort name or empty string if user is not in any cohort
     */
    public static function get_user_cohort(int $userid): string {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $cohorts = cohort_get_user_cohorts($userid);

        if (!empty($cohorts)) {
            $firstcohort = reset($cohorts);
            return $firstcohort->name;
        }

        return '';
    }

    /**
     * Get total ECTS credits for a student.
     *
     * Sums the final_ects field values across all form entries for a student.
     * This is a performant method using a single SQL query.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @return int Total ECTS credits
     */
    public static function get_student_total_ects(int $projetvetid, int $studentid): int {
        global $DB;

        // Get the field ID for 'final_ects' once.
        $field = $DB->get_record('projetvet_form_field', ['idnumber' => 'final_ects'], 'id');
        if (!$field) {
            return 0;
        }

        // Single optimized query to sum all final_ects values for this student.
        $sql = "SELECT COALESCE(SUM(fd.intvalue), 0) as total
                  FROM {projetvet_form_data} fd
                  JOIN {projetvet_form_entry} fe ON fe.id = fd.entryid
                 WHERE fe.projetvetid = :projetvetid
                   AND fe.studentid = :studentid
                   AND fd.fieldid = :fieldid
                   AND fd.intvalue IS NOT NULL";

        $params = [
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'fieldid' => $field->id,
        ];

        $result = $DB->get_record_sql($sql, $params);

        return $result ? (int)$result->total : 0;
    }

    /**
     * Check if student has entries requiring teacher action.
     *
     * Returns true if the student has any entries at an entrystatus where
     * the statusmsg is 'teacheraccept' or 'validated'.
     * - For activities: checks entrystatus 1 (teacheraccept) or 3 (validated)
     * - For facetoface: checks entrystatus 1 (teacheraccept) only
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @return bool True if there are entries requiring teacher action
     */
    public static function student_has_pending_teacher_action(int $projetvetid, int $studentid): bool {
        global $DB;

        // Check for activities at entrystatus 1 or 3, or facetoface at entrystatus 1.
        // Using a single optimized query to minimize database calls.
        $sql = "SELECT COUNT(fe.id)
                  FROM {projetvet_form_entry} fe
                  JOIN {projetvet_form_set} fs ON fe.formsetid = fs.id
                 WHERE fe.projetvetid = :projetvetid
                   AND fe.studentid = :studentid
                   AND (
                       (fs.idnumber = :activities AND fe.entrystatus IN (:status1, :status3))
                       OR
                       (fs.idnumber = :facetoface AND fe.entrystatus = :status1_f2f)
                   )";

        $params = [
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'activities' => 'activities',
            'status1' => 1,
            'status3' => 3,
            'facetoface' => 'facetoface',
            'status1_f2f' => 1,
        ];

        $count = $DB->count_records_sql($sql, $params);

        return $count > 0;
    }

    /**
     * Get suggested ECTS credits based on hours and rang.
     *
     * @param int $projetvetid The projetvet instance ID
     * @param int $studentid The student ID
     * @param int $entryid The entry ID to get rang value from
     * @param float $hours Number of hours
     * @param string $stringidentifier The language string identifier for the message
     * @param int $rangvalue The rang value (0 = fetch from entry, 1 = A, 2 = B)
     * @param float|null $finalects The final ECTS value (optional)
     * @return array Array with 'suggestedects', 'message', 'warning', and 'error' keys
     */
    public static function get_suggested_ects(
        int $projetvetid,
        int $studentid,
        int $entryid,
        float $hours,
        string $stringidentifier = '',
        int $rangvalue = 0,
        ?float $finalects = 0
    ): array {
        // Handle null finalects.
        $finalects = $finalects ?? 0;

        // Get configuration values.
        $hoursperects = (int) get_config('mod_projetvet', 'hours_per_ects') ?: 30;
        $maxects = (int) get_config('mod_projetvet', 'max_ects') ?: 10;
        $minhours = (int) get_config('mod_projetvet', 'min_hours') ?: 20;

        // Validate hours.
        if ($hours <= 0 || !is_numeric($hours)) {
            return [
                'suggestedects' => 0,
                'message' => '',
                'warning' => '',
                'error' => get_string('invalid_hours', 'mod_projetvet'),
            ];
        }

        // Check minimum hours requirement.
        if ($hours < $minhours) {
            return [
                'suggestedects' => 0,
                'message' => '',
                'warning' => '',
                'error' => get_string('min_hours_error', 'mod_projetvet', $minhours),
            ];
        }

        // Get rang value from entry if not provided.
        if ($rangvalue === 0 && $entryid > 0) {
            $entry = \mod_projetvet\local\api\entries::get_entry($entryid);
            // Loop through categories and fields to find rang.
            foreach ($entry->categories as $category) {
                foreach ($category->fields as $field) {
                    if ($field->idnumber === 'rang') {
                        $rangvalue = (int) $field->value;
                        break 2;
                    }
                }
            }
        }

        $suggestedects = 0;
        $warning = '';

        if ($rangvalue === 2) {
            // Rang B: flat rate of 1 ECTS.
            $suggestedects = 1;
        } else if ($rangvalue === 1) {
            // Rang A: calculate based on hours.
            $suggestedects = (int) ceil($hours / $hoursperects);

            // Apply maximum ECTS cap.
            if ($suggestedects > $maxects) {
                $warning = get_string('max_ects_warning', 'mod_projetvet', $maxects);
                $suggestedects = $maxects;
            }
        } else {
            // No rang selected yet, use default calculation.
            $suggestedects = (int) ceil($hours / $hoursperects);
            if ($suggestedects > $maxects) {
                $suggestedects = $maxects;
            }
        }

        // Calculate "before" value from the 'hours' field if entry exists.
        $beforeects = 0;
        if ($entryid > 0 && $stringidentifier !== '') {
            $entry = \mod_projetvet\local\api\entries::get_entry($entryid);
            // Loop through categories and fields to find the 'hours' field.
            foreach ($entry->categories as $category) {
                foreach ($category->fields as $field) {
                    if ($field->idnumber === 'hours' && !empty($field->value)) {
                        $hoursbefore = (float) $field->value;
                        // Calculate ECTS for hours field using same rang logic.
                        if ($rangvalue === 2) {
                            $beforeects = 1;
                        } else if ($rangvalue === 1) {
                            $beforeects = (int) ceil($hoursbefore / $hoursperects);
                            if ($beforeects > $maxects) {
                                $beforeects = $maxects;
                            }
                        } else {
                            $beforeects = (int) ceil($hoursbefore / $hoursperects);
                            if ($beforeects > $maxects) {
                                $beforeects = $maxects;
                            }
                        }
                        break 2;
                    }
                }
            }
        }

        // Format the message if a string identifier is provided.
        $message = '';
        if ($stringidentifier !== '') {
            $a = new \stdClass();
            $a->after = $suggestedects;
            $a->before = $beforeects;
            $a->hours = $hours;
            $a->finalects = $finalects;
            $message = get_string($stringidentifier, 'mod_projetvet', $a);

            if (preg_match_all('/\[([a-z]+)\]/', $message, $matches)) {
                foreach ($matches[1] as $filter) {
                    $cmid = get_coursemodule_from_instance('projetvet', $projetvetid)->id;
                    $filtervalue = self::get_filter($filter, $studentid, $cmid);
                    $message = str_replace('[' . $filter . ']', $filtervalue, $message);
                }
            }
        }

        return [
            'suggestedects' => $suggestedects,
            'message' => $message,
            'warning' => $warning,
            'error' => '',
        ];
    }

    /**
     * Get the teacher/tutor name for a student.
     *
     * @param int $userid The student user ID
     * @param int $cmid The course module ID
     * @return string The teacher's full name or empty string if not found
     */
    public static function get_student_teacher_name(int $userid, int $cmid): string {
        $tutor = \mod_projetvet\local\api\groups::get_student_primary_tutor($userid, $cmid);
        if ($tutor) {
            return fullname($tutor);
        }
        return '';
    }

    /**
     * Get all cohorts available in a course context.
     *
     * Returns an array of cohorts that are visible/available in the course context.
     * Cohorts can be defined at system level, category level, or course level.
     *
     * @param int $courseid The course ID
     * @return array Array of cohorts with id as key and name as value
     */
    public static function get_course_cohorts(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $context = \context_course::instance($courseid);

        // Get all cohorts available in this context (includes parent contexts).
        $cohorts = cohort_get_available_cohorts($context, COHORT_ALL, 0, 0);

        $result = [];
        foreach ($cohorts as $cohort) {
            $result[$cohort->id] = $cohort->name;
        }

        return $result;
    }

    /**
     * Get all values for a custom profile field.
     *
     * Returns distinct values for a specific custom profile field.
     *
     * @param string $fieldshortname The shortname of the custom profile field
     * @return array Array of field values with value as both key and value
     */
    public static function get_profile_field_values(string $fieldshortname): array {
        global $DB;

        $values = $DB->get_records_sql("
            SELECT DISTINCT d.data as value, d.data as label
            FROM {user_info_data} d
            JOIN {user_info_field} f ON f.id = d.fieldid
            WHERE f.shortname = :shortname
            AND d.data IS NOT NULL
            AND d.data != ''
            ORDER BY d.data
        ", ['shortname' => $fieldshortname]);

        $result = [];
        foreach ($values as $value) {
            $result[$value->value] = $value->label;
        }

        return $result;
    }

    /**
     * Get SQL fragment to filter users by cohort membership.
     *
     * This is used in report filters to generate SQL that checks if a user
     * is a member of one or more cohorts.
     *
     * @param array $cohortids Array of cohort IDs
     * @param string $fieldsql The field SQL (usually the user ID field)
     * @return array Array containing SQL string and parameters [sql, params]
     */
    public static function get_cohort_members_filter_sql(array $cohortids, string $fieldsql): array {
        global $DB;

        if (empty($cohortids)) {
            return [null, []];
        }

        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $sql = "{$fieldsql} IN (
            SELECT cm.userid
            FROM {cohort_members} cm
            WHERE cm.cohortid {$insql}
        )";

        return [$sql, $params];
    }
}
