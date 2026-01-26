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
use core\output\plugin_renderer_base;

/**
 * Renderer for mod_projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the info section above the activity list
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @param int $studentid The student ID
     * @param bool $isteacher Whether the user is a teacher
     * @return string HTML to output
     */
    public function render_student_info($moduleinstance, $cm, $context, $studentid, $isteacher) {
        $studentinfo = new student_info($moduleinstance, $cm, $studentid, $isteacher);
        return $this->render_from_template('mod_projetvet/student_info', $studentinfo->export_for_template($this));
    }

    /**
     * Render the student view page
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @param int $studentid The student ID being viewed
     * @param bool $isteacher Whether the viewer is a teacher
     * @return string HTML to output
     */
    public function render_student_view($moduleinstance, $cm, $context, $studentid, $isteacher) {
        $viewpage = new view_page($moduleinstance, $cm, $context, $studentid, $isteacher);

        return $this->render_from_template('mod_projetvet/view_page', $viewpage->export_for_template($this));
    }

    /**
     * Render the admin page
     *
     * @param \stdClass $moduleinstance The projetvet instance
     * @param \stdClass $cm The course module
     * @param \context_module $context The context
     * @param bool $filterstudents Whether to filter students without teachers
     * @param bool $filterteachers Whether to filter teachers with capacity
     * @return string HTML to output
     */
    public function render_admin_page($moduleinstance, $cm, $context, $filterstudents = false, $filterteachers = false) {
        $adminpage = new admin_page($moduleinstance, $cm, $context, $filterstudents, $filterteachers);
        return $this->render_from_template('mod_projetvet/admin_page', $adminpage->export_for_template($this));
    }

    /**
     * Render the "action required" notification (email + Moodle message).
     *
     * @param entry_action_required_message $message
     * @return string HTML to output
     */
    public function render_entry_action_required_message(entry_action_required_message $message): string {

        return $this->render_from_template(
            'mod_projetvet/notification_entry_action_required',
            $message->export_for_template($this)
        );
    }
}
