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
 * Group member persistent
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_member extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_group_members';

    /** Member type: primary tutor (owner of the group) */
    const TYPE_PRIMARY_TUTOR = 'primary_tutor';

    /** Member type: secondary/temporary tutor */
    const TYPE_SECONDARY_TUTOR = 'secondary_tutor';

    /** Member type: student */
    const TYPE_STUDENT = 'student';

    /**
     * Return the custom definition of the properties of this model.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'groupid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'groupid'),
            ],
            'userid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'userid'),
            ],
            'membertype' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => self::TYPE_STUDENT,
                'message' => new lang_string('invaliddata', 'projetvet', 'membertype'),
            ],
            'startdate' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'default' => null,
                'message' => new lang_string('invaliddata', 'projetvet', 'startdate'),
            ],
            'enddate' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'default' => null,
                'message' => new lang_string('invaliddata', 'projetvet', 'enddate'),
            ],
        ];
    }

    /**
     * Validate membertype
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_membertype($value) {
        $validtypes = [
            self::TYPE_PRIMARY_TUTOR,
            self::TYPE_SECONDARY_TUTOR,
            self::TYPE_STUDENT,
        ];

        if (!in_array($value, $validtypes)) {
            return new lang_string('invalidmembertype', 'projetvet');
        }

        return true;
    }

    /**
     * Get the group object for this member
     *
     * @return projetvet_group
     */
    public function get_group() {
        return new projetvet_group($this->get('groupid'));
    }

    /**
     * Get the user object for this member
     *
     * @return \stdClass
     */
    public function get_user() {
        global $DB;
        return $DB->get_record('user', ['id' => $this->get('userid')], '*', MUST_EXIST);
    }

    /**
     * Check if this membership is currently active
     *
     * @param int $timestamp Optional: check at specific time (defaults to now)
     * @return bool
     */
    public function is_active($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $startdate = $this->get('startdate');
        $enddate = $this->get('enddate');

        // Check start date.
        if ($startdate && $startdate > $timestamp) {
            return false;
        }

        // Check end date.
        if ($enddate && $enddate <= $timestamp) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is a tutor membership (primary or secondary)
     *
     * @return bool
     */
    public function is_tutor() {
        $type = $this->get('membertype');
        return $type === self::TYPE_PRIMARY_TUTOR || $type === self::TYPE_SECONDARY_TUTOR;
    }

    /**
     * Check if this is a primary tutor
     *
     * @return bool
     */
    public function is_primary_tutor() {
        return $this->get('membertype') === self::TYPE_PRIMARY_TUTOR;
    }

    /**
     * Check if this is a secondary tutor
     *
     * @return bool
     */
    public function is_secondary_tutor() {
        return $this->get('membertype') === self::TYPE_SECONDARY_TUTOR;
    }

    /**
     * Check if this is a student
     *
     * @return bool
     */
    public function is_student() {
        return $this->get('membertype') === self::TYPE_STUDENT;
    }

    /**
     * Extend the end date of this membership
     *
     * @param int $enddate New end date timestamp (NULL = remove end date)
     * @return void
     */
    public function extend_membership($enddate) {
        $this->set('enddate', $enddate);
        $this->update();
    }

    /**
     * Get all groups for a user
     *
     * @param int $userid
     * @param int $projetvetid Optional: filter by projetvet instance
     * @param string $membertype Optional: filter by member type
     * @param bool $activeonly Only get active memberships
     * @return array Array of group_member objects
     */
    public static function get_user_memberships($userid, $projetvetid = null, $membertype = null, $activeonly = false) {
        global $DB;

        $sql = "SELECT gm.*
                  FROM {projetvet_group_members} gm";

        if ($projetvetid) {
            $sql .= " JOIN {projetvet_groups} g ON g.id = gm.groupid";
        }

        $sql .= " WHERE gm.userid = :userid";
        $params = ['userid' => $userid];

        if ($projetvetid) {
            $sql .= " AND g.projetvetid = :projetvetid";
            $params['projetvetid'] = $projetvetid;
        }

        if ($membertype) {
            $sql .= " AND gm.membertype = :membertype";
            $params['membertype'] = $membertype;
        }

        $sql .= " ORDER BY gm.timecreated ASC";

        $records = $DB->get_records_sql($sql, $params);

        $memberships = [];
        foreach ($records as $record) {
            $member = new self(0, $record);

            if ($activeonly && !$member->is_active()) {
                continue;
            }

            $memberships[] = $member;
        }

        return $memberships;
    }

    /**
     * Get membership for a specific user in a specific group
     *
     * @param int $groupid
     * @param int $userid
     * @return group_member|null
     */
    public static function get_membership($groupid, $userid) {
        $records = self::get_records(['groupid' => $groupid, 'userid' => $userid]);
        return empty($records) ? null : reset($records);
    }
}
