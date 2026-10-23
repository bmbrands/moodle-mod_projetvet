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

namespace mod_projetvet\local\api;

use mod_projetvet\local\persistent\substitution;
use mod_projetvet\local\persistent\teacher_rating;
use moodle_exception;

/**
 * Temporary substitute tutors, per student.
 *
 * A substitution never changes the tutor groups: the principal tutor (group owner) stays the
 * official referent of the student. While a substitution is in effect, the substitute is the
 * active tutor of the student. Closing a substitution returns the student to the principal tutor.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitutions {
    /**
     * Get the open substitution of a student (planned or in effect).
     *
     * @param int $studentid
     * @param int $projetvetid
     * @return substitution|null
     */
    public static function get_open_substitution(int $studentid, int $projetvetid): ?substitution {
        return substitution::get_open_for_student($studentid, $projetvetid);
    }

    /**
     * Get the substitution of a student that is in effect (open and started).
     *
     * @param int $studentid
     * @param int $projetvetid
     * @param int|null $now Defaults to the current time
     * @return substitution|null
     */
    public static function get_substitution_in_effect(int $studentid, int $projetvetid, ?int $now = null): ?substitution {
        $substitution = self::get_open_substitution($studentid, $projetvetid);
        return ($substitution && $substitution->is_in_effect($now)) ? $substitution : null;
    }

    /**
     * Get the active tutor of a student: the substitute while a substitution is in effect,
     * otherwise the principal tutor.
     *
     * @param int $studentid
     * @param int $projetvetid
     * @return \stdClass|null The user, null when the student has no tutor
     */
    public static function get_active_tutor(int $studentid, int $projetvetid): ?\stdClass {
        $substitution = self::get_substitution_in_effect($studentid, $projetvetid);
        if ($substitution) {
            return \core_user::get_user($substitution->get('substitutetutorid')) ?: null;
        }
        return groups::get_student_primary_tutor($studentid, $projetvetid);
    }

    /**
     * Get the open substitutions of the students of a principal tutor.
     *
     * @param int $primarytutorid
     * @param int $projetvetid
     * @return substitution[] Indexed by student id
     */
    public static function get_open_substitutions_for_primary(int $primarytutorid, int $projetvetid): array {
        $records = substitution::get_records([
            'projetvetid' => $projetvetid,
            'primarytutorid' => $primarytutorid,
            'status' => substitution::STATUS_OPEN,
        ], 'startdate');

        $substitutions = [];
        foreach ($records as $record) {
            $substitutions[(int) $record->get('studentid')] = $record;
        }
        return $substitutions;
    }

    /**
     * Get the open substitutions in which a tutor is the substitute.
     *
     * @param int $substitutetutorid
     * @param int $projetvetid
     * @return substitution[] Indexed by student id
     */
    public static function get_open_substitutions_for_substitute(int $substitutetutorid, int $projetvetid): array {
        $records = substitution::get_records([
            'projetvetid' => $projetvetid,
            'substitutetutorid' => $substitutetutorid,
            'status' => substitution::STATUS_OPEN,
        ], 'startdate');

        $substitutions = [];
        foreach ($records as $record) {
            $substitutions[(int) $record->get('studentid')] = $record;
        }
        return $substitutions;
    }

    /**
     * Get the students a tutor follows: their principal and secondary students (tutor groups)
     * and the students they follow as an open substitute.
     *
     * @param int $tutorid
     * @param int $projetvetid
     * @return int[] Student ids
     */
    public static function get_students_followed_by_tutor(int $tutorid, int $projetvetid): array {
        $studentids = array_map('intval', groups::get_students_for_tutor($tutorid, $projetvetid));
        $substituteids = array_keys(self::get_open_substitutions_for_substitute($tutorid, $projetvetid));
        return array_values(array_unique(array_merge($studentids, $substituteids)));
    }

    /**
     * Get the substitution history of the students of a principal tutor (most recent first).
     *
     * @param int $primarytutorid
     * @param int $projetvetid
     * @return substitution[]
     */
    public static function get_history_for_primary(int $primarytutorid, int $projetvetid): array {
        return array_values(substitution::get_records(
            ['projetvetid' => $projetvetid, 'primarytutorid' => $primarytutorid],
            'timecreated',
            'DESC'
        ));
    }

    /**
     * Get the substitution history of a student (most recent first).
     *
     * @param int $studentid
     * @param int $projetvetid
     * @return substitution[]
     */
    public static function get_history_for_student(int $studentid, int $projetvetid): array {
        return array_values(substitution::get_records(
            ['projetvetid' => $projetvetid, 'studentid' => $studentid],
            'timecreated',
            'DESC'
        ));
    }

    /**
     * Get the students of a principal tutor (members of the groups they own), sorted by name.
     *
     * @param int $primarytutorid
     * @param int $projetvetid
     * @return \stdClass[] User records indexed by id
     */
    public static function get_primary_students(int $primarytutorid, int $projetvetid): array {
        global $DB;

        $groups = \mod_projetvet\local\persistent\projetvet_group::get_by_owner($primarytutorid, $projetvetid);
        if (empty($groups)) {
            return [];
        }
        $groupids = array_map(fn($group) => $group->get('id'), $groups);
        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $params['membertype'] = \mod_projetvet\local\persistent\group_member::TYPE_STUDENT;
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        return $DB->get_records_sql(
            "SELECT DISTINCT u.id, {$userfields}
               FROM {user} u
               JOIN {projetvet_group_members} gm ON gm.userid = u.id
              WHERE gm.groupid {$insql}
                AND gm.membertype = :membertype
                AND u.deleted = 0
           ORDER BY u.lastname, u.firstname",
            $params
        );
    }

    /**
     * Get the tutors of the activity that could replace a principal tutor, with the information
     * needed to choose a substitute (status, capacity left, A1 acceptance).
     *
     * @param int $cmid
     * @param int $projetvetid
     * @param int $primarytutorid The principal tutor, excluded from the list
     * @return array[] Arrays with id, name, status, available, acceptsa1, eligible and label
     */
    public static function get_candidate_tutors(int $cmid, int $projetvetid, int $primarytutorid): array {
        $candidates = [];
        foreach (groups::get_all_teachers($cmid) as $teacherid) {
            $teacherid = (int) $teacherid;
            if ($teacherid === $primarytutorid) {
                continue;
            }
            $status = groups::get_teacher_status($teacherid, $projetvetid);
            $acceptsa1 = teacher_rating::get_a1_acceptance_for($teacherid, $projetvetid) === teacher_rating::ACCEPTS_A1_YES;
            $available = self::get_available_capacity($teacherid, $projetvetid);
            $eligible = $status === teacher_rating::STATUS_ACTIVE_OPEN;
            $name = fullname(\core_user::get_user($teacherid));

            $candidates[] = [
                'id' => $teacherid,
                'name' => $name,
                'status' => $status,
                'available' => $available,
                'acceptsa1' => $acceptsa1,
                'eligible' => $eligible,
                'label' => get_string('substitution_candidate_label', 'mod_projetvet', (object) [
                    'name' => $name,
                    'status' => teacher_rating::get_status_short_string_for($status),
                    'available' => $available,
                    'a1' => get_string($acceptsa1 ? 'a1accepts_yes' : 'a1accepts_no', 'mod_projetvet'),
                ]),
            ];
        }

        // Eligible tutors first, then by name.
        usort($candidates, fn($a, $b) => [!$a['eligible'], $a['name']] <=> [!$b['eligible'], $b['name']]);
        return $candidates;
    }

    /**
     * Get the load of a tutor: their principal students plus the students they follow as an
     * open substitute.
     *
     * @param int $tutorid
     * @param int $projetvetid
     * @return int
     */
    public static function get_tutor_load(int $tutorid, int $projetvetid): int {
        return groups::get_primary_student_count($tutorid, $projetvetid)
            + count(self::get_open_substitutions_for_substitute($tutorid, $projetvetid));
    }

    /**
     * Get the capacity a tutor has left for substitutions (can be negative).
     *
     * @param int $tutorid
     * @param int $projetvetid
     * @return int
     */
    public static function get_available_capacity(int $tutorid, int $projetvetid): int {
        $rating = teacher_rating::get_or_create_rating($tutorid, $projetvetid);
        return $rating->get_capacity() - self::get_tutor_load($tutorid, $projetvetid);
    }

    /**
     * Get the problems preventing a tutor from being a substitute, independently of the students.
     *
     * @param int $cmid
     * @param int $projetvetid
     * @param int $substitutetutorid
     * @return string[] Localized messages, empty when the tutor can take substitutions
     */
    public static function get_tutor_problems(int $cmid, int $projetvetid, int $substitutetutorid): array {
        $teacherids = array_map('intval', groups::get_all_teachers($cmid));
        if (!in_array($substitutetutorid, $teacherids, true)) {
            return [get_string('substitution_error_notteacher', 'mod_projetvet')];
        }

        $status = groups::get_teacher_status($substitutetutorid, $projetvetid);
        if ($status !== teacher_rating::STATUS_ACTIVE_OPEN) {
            return [get_string('substitution_error_status', 'mod_projetvet', (object) [
                'name' => fullname(\core_user::get_user($substitutetutorid)),
                'status' => teacher_rating::get_status_string_for($status),
            ])];
        }

        return [];
    }

    /**
     * Get the problems preventing a student from being followed by a substitute.
     *
     * @param int $cmid
     * @param int $projetvetid
     * @param int $studentid
     * @param int $substitutetutorid
     * @return string[] Localized messages, empty when the substitution is possible
     */
    public static function get_student_problems(int $cmid, int $projetvetid, int $studentid, int $substitutetutorid): array {
        $studentids = array_map('intval', array_column(groups::get_all_students($cmid), 'uniqueid'));
        if (!in_array($studentid, $studentids, true)) {
            return [get_string('substitution_error_notstudent', 'mod_projetvet')];
        }

        $problems = [];
        $primarytutor = groups::get_student_primary_tutor($studentid, $projetvetid);
        if (!$primarytutor) {
            $problems[] = get_string('substitution_error_noprimary', 'mod_projetvet');
        } else if ((int) $primarytutor->id === $substitutetutorid) {
            $problems[] = get_string('substitution_error_isprimary', 'mod_projetvet');
        }

        $open = self::get_open_substitution($studentid, $projetvetid);
        if ($open) {
            $problems[] = get_string(
                'substitution_error_alreadyopen',
                'mod_projetvet',
                fullname(\core_user::get_user($open->get('substitutetutorid')))
            );
        }

        if (
            \mod_projetvet\utils::is_a1_student($studentid)
            && teacher_rating::get_a1_acceptance_for($substitutetutorid, $projetvetid) !== teacher_rating::ACCEPTS_A1_YES
        ) {
            $problems[] = get_string(
                'substitution_error_a1',
                'mod_projetvet',
                fullname(\core_user::get_user($substitutetutorid))
            );
        }

        return $problems;
    }

    /**
     * Check a batch of substitutions with the same substitute, before saving it.
     *
     * Every student is checked so that the manager gets all conflicts at once.
     *
     * @param int $cmid
     * @param int $projetvetid
     * @param int[] $studentids
     * @param int $substitutetutorid
     * @param bool $allowovercapacity Whether the manager accepted a capacity overrun
     * @return array{tutor: string[], students: array<int, string[]>, capacity: string} Localized
     *     problems of the substitute, of each student (indexed by student id) and the capacity
     *     overrun message ('' when within capacity or accepted).
     */
    public static function check_batch(
        int $cmid,
        int $projetvetid,
        array $studentids,
        int $substitutetutorid,
        bool $allowovercapacity = false
    ): array {
        $result = ['tutor' => [], 'students' => [], 'capacity' => ''];

        $result['tutor'] = self::get_tutor_problems($cmid, $projetvetid, $substitutetutorid);
        if (!empty($result['tutor'])) {
            return $result;
        }

        $studentids = array_values(array_unique(array_map('intval', $studentids)));
        foreach ($studentids as $studentid) {
            $problems = self::get_student_problems($cmid, $projetvetid, $studentid, $substitutetutorid);
            if (!empty($problems)) {
                $result['students'][$studentid] = $problems;
            }
        }

        $available = self::get_available_capacity($substitutetutorid, $projetvetid);
        if (!$allowovercapacity && count($studentids) > $available) {
            $result['capacity'] = get_string('substitution_error_capacity', 'mod_projetvet', (object) [
                'name' => fullname(\core_user::get_user($substitutetutorid)),
                'available' => max(0, $available),
                'count' => count($studentids),
            ]);
        }

        return $result;
    }

    /**
     * Whether a batch check result contains problems.
     *
     * @param array $check Result of {@see self::check_batch()}
     * @return bool
     */
    public static function has_problems(array $check): bool {
        return !empty($check['tutor']) || !empty($check['students']) || $check['capacity'] !== '';
    }

    /**
     * Create the substitutions of several students by the same substitute.
     *
     * Nothing is saved when any student cannot be followed by the substitute.
     *
     * @param int $cmid
     * @param int $projetvetid
     * @param int[] $studentids
     * @param int $substitutetutorid
     * @param int $startdate
     * @param int $enddate Planned end date, 0 when not planned
     * @param string $reason
     * @param bool $notifyprimary Whether the principal tutor keeps receiving the notifications
     * @param bool $allowovercapacity Whether the manager accepted a capacity overrun
     * @return substitution[] Indexed by student id
     * @throws moodle_exception When the batch has problems
     */
    public static function create_substitutions(
        int $cmid,
        int $projetvetid,
        array $studentids,
        int $substitutetutorid,
        int $startdate,
        int $enddate,
        string $reason,
        bool $notifyprimary = false,
        bool $allowovercapacity = false
    ): array {
        global $DB, $USER;

        if (empty($studentids)) {
            throw new moodle_exception('nouseridprovided', 'mod_projetvet');
        }
        if ($enddate && $enddate < $startdate) {
            throw new moodle_exception('substitution_error_dates', 'mod_projetvet');
        }

        $check = self::check_batch($cmid, $projetvetid, $studentids, $substitutetutorid, $allowovercapacity);
        if (self::has_problems($check)) {
            throw new moodle_exception('substitutionnotallowed', 'mod_projetvet', '', self::format_problems($check));
        }

        $transaction = $DB->start_delegated_transaction();
        $created = [];
        foreach (array_unique(array_map('intval', $studentids)) as $studentid) {
            $primarytutor = groups::get_student_primary_tutor($studentid, $projetvetid);
            $substitution = new substitution(0, (object) [
                'projetvetid' => $projetvetid,
                'studentid' => $studentid,
                'primarytutorid' => $primarytutor->id,
                'substitutetutorid' => $substitutetutorid,
                'startdate' => $startdate,
                'enddate' => $enddate,
                'reason' => $reason,
                'notifyprimary' => $notifyprimary,
                'createdby' => $USER->id,
            ]);
            $created[$studentid] = $substitution->create();
        }
        $transaction->allow_commit();

        return $created;
    }

    /**
     * Close open substitutions: the students return to their principal tutor.
     *
     * @param int $projetvetid
     * @param int[] $substitutionids
     * @return substitution[] The closed substitutions
     */
    public static function close_substitutions(int $projetvetid, array $substitutionids): array {
        return self::finish_substitutions($projetvetid, $substitutionids, substitution::STATUS_CLOSED);
    }

    /**
     * Change the planned end date of open substitutions.
     *
     * @param int $projetvetid
     * @param int[] $substitutionids
     * @param int $enddate The new planned end date
     * @return substitution[] The extended substitutions
     * @throws moodle_exception When the end date is before the start date of a substitution
     */
    public static function extend_substitutions(int $projetvetid, array $substitutionids, int $enddate): array {
        $substitutions = self::get_open_substitutions_by_id($projetvetid, $substitutionids);
        foreach ($substitutions as $substitution) {
            if ($enddate < (int) $substitution->get('startdate')) {
                throw new moodle_exception('substitution_error_dates', 'mod_projetvet');
            }
        }
        foreach ($substitutions as $substitution) {
            $substitution->set('enddate', $enddate);
            $substitution->update();
        }
        return $substitutions;
    }

    /**
     * Turn open substitutions into definitive changes: the substitute becomes the principal tutor.
     *
     * @param int $projetvetid
     * @param int[] $substitutionids
     * @return substitution[] The substitutions made definitive
     */
    public static function make_definitive(int $projetvetid, array $substitutionids): array {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $substitutions = self::finish_substitutions($projetvetid, $substitutionids, substitution::STATUS_DEFINITIVE);
        foreach ($substitutions as $substitution) {
            groups::assign_students_to_teacher(
                (int) $substitution->get('substitutetutorid'),
                [(int) $substitution->get('studentid')],
                $projetvetid
            );
        }
        $transaction->allow_commit();

        return $substitutions;
    }

    /**
     * Format the problems of a batch check as a single message.
     *
     * @param array $check Result of {@see self::check_batch()}
     * @return string
     */
    public static function format_problems(array $check): string {
        $messages = $check['tutor'];
        foreach ($check['students'] as $studentid => $problems) {
            $name = fullname(\core_user::get_user($studentid));
            foreach ($problems as $problem) {
                $messages[] = $name . ': ' . $problem;
            }
        }
        if ($check['capacity'] !== '') {
            $messages[] = $check['capacity'];
        }
        return implode(' ', $messages);
    }

    /**
     * Close open substitutions with the given final status.
     *
     * @param int $projetvetid
     * @param int[] $substitutionids
     * @param int $status substitution::STATUS_CLOSED or substitution::STATUS_DEFINITIVE
     * @return substitution[]
     */
    protected static function finish_substitutions(int $projetvetid, array $substitutionids, int $status): array {
        global $USER;

        $substitutions = self::get_open_substitutions_by_id($projetvetid, $substitutionids);
        foreach ($substitutions as $substitution) {
            $substitution->set('status', $status);
            $substitution->set('timeclosed', time());
            $substitution->set('closedby', $USER->id);
            $substitution->update();
        }
        return $substitutions;
    }

    /**
     * Get open substitutions of an instance by id, ignoring the others.
     *
     * @param int $projetvetid
     * @param int[] $substitutionids
     * @return substitution[]
     */
    protected static function get_open_substitutions_by_id(int $projetvetid, array $substitutionids): array {
        $substitutions = [];
        foreach (array_unique(array_map('intval', $substitutionids)) as $substitutionid) {
            $substitution = substitution::get_record([
                'id' => $substitutionid,
                'projetvetid' => $projetvetid,
                'status' => substitution::STATUS_OPEN,
            ]);
            if ($substitution) {
                $substitutions[] = $substitution;
            }
        }
        return $substitutions;
    }
}
