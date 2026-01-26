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
     * Get all members of this group
     *
     * @param string $membertype Optional: filter by member type
     * @return array Array of group_member persistent objects
     */
    public function get_members($membertype = null) {
        $params = ['groupid' => $this->get('id')];

        if ($membertype) {
            $params['membertype'] = $membertype;
        }

        return group_member::get_records($params, 'timecreated', 'ASC');
    }

    /**
     * Add a member to this group
     *
     * @param int $userid
     * @param string $membertype
     * @return group_member
     */
    public function add_member($userid, $membertype = 'student') {
        global $USER;

        $member = new group_member(0, (object)[
            'groupid' => $this->get('id'),
            'userid' => $userid,
            'membertype' => $membertype,
        ]);

        $member->set('usermodified', $USER->id);
        $member->create();

        return $member;
    }

    /**
     * Get student count for this group
     *
     * @return int
     */
    public function get_student_count(): int {
        return count($this->get_members(group_member::TYPE_STUDENT));
    }

    /**
     * Get secondary tutors for this group
     *
     * @return array Array of group_member objects
     */
    public function get_secondary_tutors(): array {
        return $this->get_members(group_member::TYPE_SECONDARY_TUTOR);
    }
}
