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
 * Projetvet group persistent
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class projetvet_group extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_groups';

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
            'name' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'message' => new lang_string('invaliddata', 'projetvet', 'name'),
            ],
            'description' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
                'message' => new lang_string('invaliddata', 'projetvet', 'description'),
            ],
            'ownerid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'ownerid'),
            ],
        ];
    }

    /**
     * Get all groups for a projetvet instance
     *
     * @param int $projetvetid
     * @return array
     */
    public static function get_by_projetvet($projetvetid) {
        return self::get_records(['projetvetid' => $projetvetid], 'name', 'ASC');
    }

    /**
     * Get groups owned by a specific user
     *
     * @param int $userid
     * @param int $projetvetid Optional: filter by projetvet instance
     * @return array
     */
    public static function get_by_owner($userid, $projetvetid = null) {
        $params = ['ownerid' => $userid];
        if ($projetvetid) {
            $params['projetvetid'] = $projetvetid;
        }
        return self::get_records($params, 'name', 'ASC');
    }

    /**
     * Get groups where user is a member (any type)
     *
     * @param int $userid
     * @param int $projetvetid Optional: filter by projetvet instance
     * @return array
     */
    public static function get_by_member($userid, $projetvetid = null) {
        global $DB;

        $sql = "SELECT g.*
                  FROM {projetvet_groups} g
                  JOIN {projetvet_group_members} gm ON gm.groupid = g.id
                 WHERE gm.userid = :userid";

        $params = ['userid' => $userid];

        if ($projetvetid) {
            $sql .= " AND g.projetvetid = :projetvetid";
            $params['projetvetid'] = $projetvetid;
        }

        $sql .= " ORDER BY g.name ASC";

        $records = $DB->get_records_sql($sql, $params);

        $groups = [];
        foreach ($records as $record) {
            $groups[] = new self(0, $record);
        }

        return $groups;
    }

    /**
     * Get member count for this group
     *
     * @param string $membertype Optional: filter by member type
     * @return int
     */
    public function get_member_count($membertype = null) {
        global $DB;

        $params = ['groupid' => $this->get('id')];

        if ($membertype) {
            $params['membertype'] = $membertype;
        }

        return $DB->count_records('projetvet_group_members', $params);
    }

    /**
     * Get all members of this group
     *
     * @param string $membertype Optional: filter by member type
     * @param bool $activeonly Only get members with no enddate or enddate in future
     * @return array Array of group_member persistent objects
     */
    public function get_members($membertype = null, $activeonly = false) {
        $params = ['groupid' => $this->get('id')];

        if ($membertype) {
            $params['membertype'] = $membertype;
        }

        $members = group_member::get_records($params, 'timecreated', 'ASC');

        if ($activeonly) {
            $now = time();
            $members = array_filter($members, function ($member) use ($now) {
                $enddate = $member->get('enddate');
                return empty($enddate) || $enddate > $now;
            });
        }

        return $members;
    }

    /**
     * Check if user is a member of this group
     *
     * @param int $userid
     * @param string $membertype Optional: specific member type to check
     * @param bool $activeonly Only check active memberships
     * @return bool
     */
    public function is_member($userid, $membertype = null, $activeonly = false) {
        global $DB;

        $params = [
            'groupid' => $this->get('id'),
            'userid' => $userid,
        ];

        if ($membertype) {
            $params['membertype'] = $membertype;
        }

        $members = $DB->get_records('projetvet_group_members', $params);

        if (empty($members)) {
            return false;
        }

        if (!$activeonly) {
            return true;
        }

        // Check if any membership is active.
        $now = time();
        foreach ($members as $member) {
            if (empty($member->enddate) || $member->enddate > $now) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a member to this group
     *
     * @param int $userid
     * @param string $membertype
     * @param int $startdate Optional start date (defaults to now)
     * @param int $enddate Optional end date (NULL = no end date)
     * @return group_member
     */
    public function add_member($userid, $membertype = 'student', $startdate = null, $enddate = null) {
        global $USER;

        if ($startdate === null) {
            $startdate = time();
        }

        $member = new group_member(0, (object)[
            'groupid' => $this->get('id'),
            'userid' => $userid,
            'membertype' => $membertype,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ]);

        $member->set('usermodified', $USER->id);
        $member->create();

        return $member;
    }

    /**
     * Remove a member from this group
     *
     * @param int $userid
     * @return bool
     */
    public function remove_member($userid) {
        global $DB;

        return $DB->delete_records('projetvet_group_members', [
            'groupid' => $this->get('id'),
            'userid' => $userid,
        ]);
    }

    /**
     * Get student count for this group
     *
     * @param bool $activeonly Only count active students (default: true)
     * @return int
     */
    public function get_student_count(bool $activeonly = true): int {
        $members = $this->get_members(group_member::TYPE_STUDENT, false);

        if (!$activeonly) {
            return count($members);
        }

        $count = 0;
        foreach ($members as $member) {
            if ($member->is_active()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get tutor count for this group (primary and secondary)
     *
     * @param bool $activeonly Only count active tutors (default: true)
     * @return int
     */
    public function get_tutor_count(bool $activeonly = true): int {
        $members = $this->get_members(null, false);

        $count = 0;
        foreach ($members as $member) {
            if (!$member->is_tutor()) {
                continue;
            }

            if ($activeonly && !$member->is_active()) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Get primary tutor for this group
     *
     * @return group_member|null
     */
    public function get_primary_tutor(): ?group_member {
        $members = $this->get_members(group_member::TYPE_PRIMARY_TUTOR, false);
        return empty($members) ? null : reset($members);
    }

    /**
     * Get secondary tutors for this group
     *
     * @param bool $activeonly Only get active tutors (default: true)
     * @return array Array of group_member objects
     */
    public function get_secondary_tutors(bool $activeonly = true): array {
        return $this->get_members(group_member::TYPE_SECONDARY_TUTOR, $activeonly);
    }
}
