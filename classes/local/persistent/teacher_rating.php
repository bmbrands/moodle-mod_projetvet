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
 * Teacher rating persistent
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_rating extends persistent {
    /**
     * Current table
     */
    const TABLE = 'projetvet_teacher_rating';

    /** Rating: expert teacher */
    const RATING_EXPERT = 'expert';

    /** Rating: average teacher */
    const RATING_AVERAGE = 'average';

    /** Rating: novice teacher */
    const RATING_NOVICE = 'novice';

    /** Capacity for expert teachers */
    const CAPACITY_EXPERT = 12;

    /** Capacity for average teachers */
    const CAPACITY_AVERAGE = 8;

    /** Capacity for novice teachers */
    const CAPACITY_NOVICE = 5;

    /**
     * Return the custom definition of the properties of this model.
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return [
            'userid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'userid'),
            ],
            'projetvetid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
                'message' => new lang_string('invaliddata', 'projetvet', 'projetvetid'),
            ],
            'rating' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => self::RATING_AVERAGE,
                'message' => new lang_string('invaliddata', 'projetvet', 'rating'),
            ],
        ];
    }

    /**
     * Validate rating
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_rating($value) {
        $validratings = [
            self::RATING_EXPERT,
            self::RATING_AVERAGE,
            self::RATING_NOVICE,
        ];

        if (!in_array($value, $validratings)) {
            return new lang_string('invalidrating', 'projetvet');
        }

        return true;
    }

    /**
     * Get the target capacity based on rating
     *
     * @return int
     */
    public function get_capacity(): int {
        $capacities = [
            self::RATING_EXPERT => self::CAPACITY_EXPERT,
            self::RATING_AVERAGE => self::CAPACITY_AVERAGE,
            self::RATING_NOVICE => self::CAPACITY_NOVICE,
        ];

        return $capacities[$this->get('rating')] ?? self::CAPACITY_AVERAGE;
    }

    /**
     * Get the localized rating string
     *
     * @return string
     */
    public function get_rating_string(): string {
        return get_string('rating_' . $this->get('rating'), 'mod_projetvet');
    }

    /**
     * Check if this is an expert rating
     *
     * @return bool
     */
    public function is_expert(): bool {
        return $this->get('rating') === self::RATING_EXPERT;
    }

    /**
     * Check if this is an average rating
     *
     * @return bool
     */
    public function is_average(): bool {
        return $this->get('rating') === self::RATING_AVERAGE;
    }

    /**
     * Check if this is a novice rating
     *
     * @return bool
     */
    public function is_novice(): bool {
        return $this->get('rating') === self::RATING_NOVICE;
    }

    /**
     * Get rating for a specific user in a projetvet instance
     *
     * @param int $userid
     * @param int $projetvetid
     * @return teacher_rating|null
     */
    public static function get_user_rating(int $userid, int $projetvetid): ?teacher_rating {
        $records = self::get_records(['userid' => $userid, 'projetvetid' => $projetvetid]);
        return empty($records) ? null : reset($records);
    }

    /**
     * Get or create rating for a user (returns average if not found)
     *
     * @param int $userid
     * @param int $projetvetid
     * @return teacher_rating
     */
    public static function get_or_create_rating(int $userid, int $projetvetid): teacher_rating {
        $rating = self::get_user_rating($userid, $projetvetid);

        if (!$rating) {
            $rating = new self(0, (object)[
                'userid' => $userid,
                'projetvetid' => $projetvetid,
                'rating' => self::RATING_AVERAGE,
            ]);
        }

        return $rating;
    }

    /**
     * Get all ratings for a projetvet instance
     *
     * @param int $projetvetid
     * @return array Array of teacher_rating objects indexed by userid
     */
    public static function get_all_ratings(int $projetvetid): array {
        $records = self::get_records(['projetvetid' => $projetvetid]);
        $ratings = [];

        foreach ($records as $record) {
            $ratings[$record->get('userid')] = $record;
        }

        return $ratings;
    }

    /**
     * Get capacity for a specific rating value
     *
     * @param string $rating
     * @return int
     */
    public static function get_capacity_for_rating(string $rating): int {
        $capacities = [
            self::RATING_EXPERT => self::CAPACITY_EXPERT,
            self::RATING_AVERAGE => self::CAPACITY_AVERAGE,
            self::RATING_NOVICE => self::CAPACITY_NOVICE,
        ];

        return $capacities[$rating] ?? self::CAPACITY_AVERAGE;
    }
}
