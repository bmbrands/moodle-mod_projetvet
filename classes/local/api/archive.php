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
use mod_projetvet\local\persistent\form_cat;
use mod_projetvet\local\persistent\form_data;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\form_set;
use moodle_exception;

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

    /** @var int Value of the archived project international mobility field meaning "yes" */
    public const MOBILITY_YES = 1;

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
            throw new moodle_exception('archivenoaccess', 'mod_projetvet');
        }
        $entry = self::get_archive_entry($projetvetid, $studentid);
        if ($entry) {
            return $entry;
        }
        $entryid = entries::create_entry($projetvetid, $studentid, [], self::STATUS_DRAFT, self::FORMSET);
        return new form_entry($entryid);
    }

    /**
     * Whether the user can add, edit or delete the archived projects of an archive.
     *
     * - draft: the student (while the function is enabled for students);
     * - submitted: tutors, to correct the values entered by the student;
     * - validated: nobody, the archive is locked (an administrator must reopen it);
     * - administrators: at any stage before validation.
     *
     * @param form_entry $archive The archive entry
     * @param int|null $userid Defaults to the current user
     * @return bool
     */
    public static function can_edit_projects(form_entry $archive, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? $USER->id;
        $context = self::get_context($archive);
        $status = (int) $archive->get('entrystatus');
        $projetvetid = $archive->get('projetvetid');
        $studentid = $archive->get('studentid');

        if ($status === self::STATUS_VALIDATED || !self::can_open($context, $projetvetid, $studentid, $userid)) {
            return false;
        }
        if (self::can_manage($context, $userid)) {
            return true;
        }
        if ($status === self::STATUS_DRAFT) {
            return self::student_can_open($context, $projetvetid, $studentid, $userid);
        }
        return has_capability('mod/projetvet:approve', $context, $userid);
    }

    /**
     * Check that the user may move the archive from one status to another.
     *
     * Allowed transitions:
     * - draft to draft (save) or submitted: the student or an administrator;
     * - submitted to submitted (save) or validated: a tutor or an administrator;
     * - validated to draft (reopen): an administrator only.
     *
     * @param form_entry $archive The archive entry
     * @param int $newstatus The requested status
     * @param int|null $userid Defaults to the current user
     * @return void
     * @throws moodle_exception When the transition is not allowed
     */
    public static function require_status_transition(form_entry $archive, int $newstatus, ?int $userid = null): void {
        global $USER;

        $userid = $userid ?? $USER->id;
        $context = self::get_context($archive);
        $status = (int) $archive->get('entrystatus');
        $ismanager = self::can_manage($context, $userid);

        switch ($status) {
            case self::STATUS_DRAFT:
                $allowed = in_array($newstatus, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true)
                    && ($ismanager || self::student_can_open(
                        $context,
                        $archive->get('projetvetid'),
                        $archive->get('studentid'),
                        $userid
                    ));
                break;
            case self::STATUS_SUBMITTED:
                $allowed = in_array($newstatus, [self::STATUS_SUBMITTED, self::STATUS_VALIDATED], true)
                    && ($ismanager || has_capability('mod/projetvet:approve', $context, $userid));
                break;
            case self::STATUS_VALIDATED:
                $allowed = in_array($newstatus, [self::STATUS_VALIDATED, self::STATUS_DRAFT], true) && $ismanager;
                break;
            default:
                $allowed = false;
        }

        if (!$allowed) {
            throw new moodle_exception('archivenoaccess', 'mod_projetvet');
        }
    }

    /**
     * Get the archived project entries of an archive.
     *
     * @param form_entry $archive The archive entry
     * @return form_entry[]
     */
    public static function get_project_entries(form_entry $archive): array {
        $formset = form_set::get_record(['idnumber' => self::PROJECT_FORMSET]);
        if (!$formset) {
            return [];
        }
        return form_entry::get_records([
            'parententryid' => $archive->get('id'),
            'formsetid' => $formset->get('id'),
        ]);
    }

    /**
     * Give all archived projects of an archive the status of the archive.
     *
     * The archived projects follow the archive workflow: the status decides who can
     * edit them, and validated (locked) projects are the "Projet repris" records.
     *
     * @param form_entry $archive The archive entry
     * @return void
     */
    public static function sync_project_statuses(form_entry $archive): void {
        $status = (int) $archive->get('entrystatus');
        foreach (self::get_project_entries($archive) as $project) {
            if ((int) $project->get('entrystatus') !== $status) {
                $project->set('entrystatus', $status);
                $project->update();
            }
        }
    }

    /**
     * Get the validated archived projects ("Projet repris") of a student.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @return \stdClass[] Objects with the archived project field values keyed by idnumber
     *                     (archive_title, archive_ects, archive_rang, archive_mobility, ...) and entryid.
     */
    public static function get_validated_projects(int $projetvetid, int $studentid): array {
        $formset = form_set::get_record(['idnumber' => self::PROJECT_FORMSET]);
        if (!$formset) {
            return [];
        }
        $entries = form_entry::get_records([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'formsetid' => $formset->get('id'),
            'entrystatus' => self::STATUS_VALIDATED,
        ]);
        if (empty($entries)) {
            return [];
        }

        $fieldidnumbers = self::get_project_field_idnumbers($formset->get('id'));
        $projects = [];
        foreach ($entries as $entry) {
            $project = (object) ['entryid' => $entry->get('id')];
            foreach (form_data::get_records(['entryid' => $entry->get('id')]) as $data) {
                $idnumber = $fieldidnumbers[$data->get('fieldid')] ?? null;
                if ($idnumber !== null) {
                    $project->{$idnumber} = $data->get_value();
                }
            }
            $projects[] = $project;
        }
        return $projects;
    }

    /**
     * Get the validated ECTS of the "Projet repris" records of a student, in total and by rank.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @return array With keys 'total', 'rank_a' and 'rank_b'
     */
    public static function get_validated_ects(int $projetvetid, int $studentid): array {
        $result = ['total' => 0, 'rank_a' => 0, 'rank_b' => 0];
        foreach (self::get_validated_projects($projetvetid, $studentid) as $project) {
            $ects = (int) ($project->archive_ects ?? 0);
            $result['total'] += $ects;
            $rank = (int) ($project->archive_rang ?? 0);
            if ($rank === 1) {
                $result['rank_a'] += $ects;
            } else if ($rank === 2) {
                $result['rank_b'] += $ects;
            }
        }
        return $result;
    }

    /**
     * Whether at least one "Projet repris" record of the student is an international mobility.
     *
     * @param int $projetvetid
     * @param int $studentid
     * @return bool
     */
    public static function has_validated_mobility(int $projetvetid, int $studentid): bool {
        foreach (self::get_validated_projects($projetvetid, $studentid) as $project) {
            if ((int) ($project->archive_mobility ?? 0) === self::MOBILITY_YES) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map the field ids of a form set to their idnumbers.
     *
     * @param int $formsetid
     * @return array fieldid => idnumber
     */
    protected static function get_project_field_idnumbers(int $formsetid): array {
        $map = [];
        foreach (form_cat::get_records(['formsetid' => $formsetid]) as $category) {
            foreach (form_field::get_records(['categoryid' => $category->get('id')]) as $field) {
                $map[$field->get('id')] = $field->get('idnumber');
            }
        }
        return $map;
    }

    /**
     * Get the module context of an archive entry.
     *
     * @param form_entry $archive
     * @return context_module
     */
    protected static function get_context(form_entry $archive): context_module {
        $cm = get_coursemodule_from_instance('projetvet', $archive->get('projetvetid'), 0, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }
}
