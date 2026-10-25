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

use core\url as moodle_url;
use mod_projetvet\local\api\substitutions;
use mod_projetvet\local\persistent\substitution;
use mod_projetvet\local\persistent\teacher_rating;
use renderable;
use renderer_base;
use templatable;

/**
 * Substitute tutors management page of a principal tutor.
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitutions_page implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $cm The course module
     * @param \stdClass $tutor The principal tutor
     * @param string $createform The rendered substitution creation form
     * @param string $manageform The rendered open substitutions form
     */
    public function __construct(
        /** @var \stdClass $cm The course module */
        protected \stdClass $cm,
        /** @var \stdClass $tutor The principal tutor */
        protected \stdClass $tutor,
        /** @var string $createform The rendered substitution creation form */
        protected string $createform,
        /** @var string $manageform The rendered open substitutions form */
        protected string $manageform
    ) {
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $projetvetid = (int) $this->cm->instance;
        $status = \mod_projetvet\local\api\groups::get_teacher_status($this->tutor->id, $projetvetid);
        $open = substitutions::get_open_substitutions_for_primary($this->tutor->id, $projetvetid);

        $data = [
            'tutorname' => fullname($this->tutor),
            'statuslabel' => teacher_rating::get_status_string_for($status),
            'statusbadgeclass' => teacher_rating::get_status_badge_class_for($status),
            'backurl' => (new moodle_url('/mod/projetvet/assignments.php', ['id' => $this->cm->id]))->out(false),
            'createform' => $this->createform,
            'manageform' => $this->manageform,
            'history' => $this->export_history($projetvetid),
        ];

        // Signal the return of the principal tutor: they are available again but substitutions are still open.
        if (!empty($open) && self::is_available_status($status)) {
            $data['returnmessage'] = get_string('substitution_return_notice', 'mod_projetvet', (object) [
                'name' => fullname($this->tutor),
                'count' => count($open),
            ]);
        }
        $data['hashistory'] = !empty($data['history']);

        return $data;
    }

    /**
     * Whether a tutor status means the tutor is available (active, open or closed to new assignments).
     *
     * @param int $status
     * @return bool
     */
    public static function is_available_status(int $status): bool {
        return in_array($status, [teacher_rating::STATUS_ACTIVE_OPEN, teacher_rating::STATUS_ACTIVE_CLOSED], true);
    }

    /**
     * Export the substitution history of the principal tutor's students.
     *
     * @param int $projetvetid
     * @return array
     */
    protected function export_history(int $projetvetid): array {
        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        $rows = [];
        foreach (substitutions::get_history_for_primary($this->tutor->id, $projetvetid) as $substitution) {
            $status = (int) $substitution->get('status');
            $rows[] = [
                'student' => $this->get_user_name((int) $substitution->get('studentid')),
                'substitute' => $this->get_user_name((int) $substitution->get('substitutetutorid')),
                'startdate' => userdate($substitution->get('startdate'), $dateformat),
                'enddate' => $substitution->get('enddate') ? userdate($substitution->get('enddate'), $dateformat) : '',
                'timeclosed' => $substitution->get('timeclosed') ? userdate($substitution->get('timeclosed'), $dateformat) : '',
                'status' => substitution::get_status_string_for($status),
                'statusbadgeclass' => self::get_status_badge_class($status),
                'overdue' => $substitution->is_overdue(),
                'reason' => $substitution->get('reason'),
                'createdby' => $this->get_user_name((int) $substitution->get('createdby')),
                'closedby' => $this->get_user_name((int) $substitution->get('closedby')),
            ];
        }
        return $rows;
    }

    /**
     * Get the Bootstrap badge class of a substitution status (light text on a dark background).
     *
     * @param int $status
     * @return string
     */
    public static function get_status_badge_class(int $status): string {
        return [
            substitution::STATUS_OPEN => 'bg-success',
            substitution::STATUS_CLOSED => 'bg-dark',
            substitution::STATUS_DEFINITIVE => 'bg-info',
        ][$status] ?? 'bg-dark';
    }

    /**
     * Get the full name of a user, '' for no user.
     *
     * @param int $userid
     * @return string
     */
    protected function get_user_name(int $userid): string {
        if (!$userid) {
            return '';
        }
        $user = \core_user::get_user($userid);
        return $user ? fullname($user) : '';
    }
}
