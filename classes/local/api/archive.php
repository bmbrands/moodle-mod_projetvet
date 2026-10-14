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

use context_module;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_set;

/**
 * Archive of CPP projects already validated with the previous system.
 *
 * A student has at most one archive entry (form set "archive") per ProjetVet instance.
 * The archive holds the batch workflow status and its archived projects are child
 * entries (form set "archive_project") linked through parententryid.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archive {
    /** @var string The form set holding the archive (one per student) */
    public const FORMSET = 'archive';

    /** @var string The form set holding the archived projects (children of the archive) */
    public const PROJECT_FORMSET = 'archive_project';

    /** @var int The student is entering the archive */
    public const STATUS_DRAFT = 0;

    /** @var int The archive has been submitted to the tutor */
    public const STATUS_SUBMITTED = 1;

    /** @var int The tutor validated the archive, it is locked */
    public const STATUS_VALIDATED = 2;

    /**
     * Whether a form set belongs to the archive feature.
     *
     * @param string $formsetidnumber
     * @return bool
     */
    public static function is_archive_formset(string $formsetidnumber): bool {
        return in_array($formsetidnumber, [self::FORMSET, self::PROJECT_FORMSET], true);
    }

    /**
     * Whether students can currently use the archive function (site setting).
     *
     * @return bool
     */
    public static function is_enabled_for_students(): bool {
        return (bool) get_config('mod_projetvet', 'archive_enabled');
    }

    /**
     * Whether the user can manage archives (administrators, always available).
     *
     * @param context_module $context
     * @param int|null $userid Defaults to the current user
     * @return bool
     */
    public static function can_manage(context_module $context, ?int $userid = null): bool {
        return has_capability('mod/projetvet:managearchive', $context, $userid);
    }

    /**
     * Get the archive entry of a student.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @return form_entry|null
     */
    public static function get_archive_entry(int $projetvetid, int $studentid): ?form_entry {
        $formset = form_set::get_record(['idnumber' => self::FORMSET]);
        if (!$formset) {
            return null;
        }
        $entry = form_entry::get_record([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'formsetid' => $formset->get('id'),
        ]);
        return $entry ?: null;
    }

    /**
     * Get the archive status of a student.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @return int|null The status, null when the student has no archive yet
     */
    public static function get_status(int $projetvetid, int $studentid): ?int {
        $entry = self::get_archive_entry($projetvetid, $studentid);
        return $entry ? (int) $entry->get('entrystatus') : null;
    }

    /**
     * Whether a student may open (and start) their own archive.
     *
     * Student access is one-shot and temporary: only while the function is enabled
     * and until the archive has been submitted to the tutor.
     *
     * @param context_module $context
     * @param int $projetvetid
     * @param int $studentid
     * @param int $userid
     * @return bool
     */
    public static function student_can_open(context_module $context, int $projetvetid, int $studentid, int $userid): bool {
        if ($studentid != $userid || !has_capability('mod/projetvet:submit', $context, $userid)) {
            return false;
        }
        if (!self::is_enabled_for_students()) {
            return false;
        }
        $status = self::get_status($projetvetid, $studentid);
        return $status === null || $status === self::STATUS_DRAFT;
    }

    /**
     * Whether a user can open the archive of a student.
     *
     * - the student: see {@see self::student_can_open()};
     * - administrators: always (even when disabled for students);
     * - tutors: once the archive has been submitted.
     *
     * @param context_module $context
     * @param int $projetvetid
     * @param int $studentid
     * @param int $userid
     * @return bool
     */
    public static function can_open(context_module $context, int $projetvetid, int $studentid, int $userid): bool {
        if (self::student_can_open($context, $projetvetid, $studentid, $userid)) {
            return true;
        }
        global $CFG;
        require_once($CFG->dirroot . '/mod/projetvet/lib.php');
        if (!projetvet_user_can_view_student($userid, $studentid, $context)) {
            return false;
        }
        if (self::can_manage($context, $userid)) {
            return true;
        }
        $status = self::get_status($projetvetid, $studentid);
        return $status !== null && $status >= self::STATUS_SUBMITTED
            && has_capability('mod/projetvet:approve', $context, $userid);
    }

    /**
     * Get the archive entry of a student, creating it when it does not exist yet.
     *
     * @param context_module $context
     * @param int $projetvetid
     * @param int $studentid
     * @param int $userid The user opening the archive
     * @return form_entry
     */
    public static function get_or_create_archive_entry(
        context_module $context,
        int $projetvetid,
        int $studentid,
        int $userid
    ): form_entry {
        if (!self::can_open($context, $projetvetid, $studentid, $userid)) {
            throw new \moodle_exception('archivenoaccess', 'mod_projetvet');
        }
        $entry = self::get_archive_entry($projetvetid, $studentid);
        if ($entry) {
            return $entry;
        }
        $entryid = entries::create_entry($projetvetid, $studentid, [], self::STATUS_DRAFT, self::FORMSET);
        return new form_entry($entryid);
    }
}
