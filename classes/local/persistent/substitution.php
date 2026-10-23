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

namespace mod_projetvet\local\persistent;

use core\persistent;
use lang_string;

/**
 * Temporary substitute tutor of a student.
 *
 * A substitution never changes the tutor groups: the principal tutor stays the official referent
 * of the student. Rows are never deleted, closed substitutions are the substitution history.
 *
 * @package   mod_projetvet
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitution extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_substitutions';

    /** The substitution is open (in effect once its start date is reached). */
    const STATUS_OPEN = 0;

    /** The substitution was closed: the student returned to the principal tutor. */
    const STATUS_CLOSED = 1;

    /** The substitution was turned into a definitive change of principal tutor. */
    const STATUS_DEFINITIVE = 2;

    /**
     * Return the custom definition of the properties of this model.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'projetvetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'projetvetid'),
            ],
            'studentid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'studentid'),
            ],
            'primarytutorid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'primarytutorid'),
            ],
            'substitutetutorid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'substitutetutorid'),
            ],
            'startdate' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'enddate' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'reason' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'notifyprimary' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'status' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => self::STATUS_OPEN,
                'choices' => [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_DEFINITIVE],
            ],
            'timeclosed' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'closedby' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'createdby' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Whether the substitution is open (not closed nor made definitive).
     *
     * @return bool
     */
    public function is_open(): bool {
        return (int) $this->get('status') === self::STATUS_OPEN;
    }

    /**
     * Whether the substitution is open and its start date is reached.
     *
     * @param int|null $now Defaults to the current time
     * @return bool
     */
    public function is_in_effect(?int $now = null): bool {
        return $this->is_open() && (int) $this->get('startdate') <= ($now ?? time());
    }

    /**
     * Whether the planned end date of an open substitution is passed.
     *
     * @param int|null $now Defaults to the current time
     * @return bool
     */
    public function is_overdue(?int $now = null): bool {
        $enddate = (int) $this->get('enddate');
        return $this->is_open() && $enddate > 0 && $enddate < ($now ?? time());
    }

    /**
     * Get the open substitution of a student, if any.
     *
     * @param int $studentid
     * @param int $projetvetid
     * @return substitution|null
     */
    public static function get_open_for_student(int $studentid, int $projetvetid): ?substitution {
        $records = self::get_records([
            'projetvetid' => $projetvetid,
            'studentid' => $studentid,
            'status' => self::STATUS_OPEN,
        ]);
        return empty($records) ? null : reset($records);
    }

    /**
     * Get the localized string for a substitution status.
     *
     * @param int $status
     * @return string
     */
    public static function get_status_string_for(int $status): string {
        $statusstrings = [
            self::STATUS_OPEN => 'substitution_status_open',
            self::STATUS_CLOSED => 'substitution_status_closed',
            self::STATUS_DEFINITIVE => 'substitution_status_definitive',
        ];

        if (!array_key_exists($status, $statusstrings)) {
            return '';
        }

        return get_string($statusstrings[$status], 'mod_projetvet');
    }
}
