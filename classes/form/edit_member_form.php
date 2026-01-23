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

namespace mod_projetvet\form;

use context;
use context_module;
use core_form\dynamic_form;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\projetvet_group;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Form for adding/editing group members
 *
 * @package    mod_projetvet
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_member_form extends dynamic_form {
    /**
     * Get context for dynamic submission
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return context_module::instance($cmid);
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/projetvet:admin', $context);
    }

    /**
     * Process dynamic submission
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        global $DB;

        $data = $this->get_data();
        $memberid = $data->memberid ?? 0;
        $groupid = $data->groupid;
        $teacherid = $data->teacherid;
        $projetvetid = $data->projetvetid;

        // Get or create group.
        if (empty($groupid)) {
            // Check if group exists for this teacher.
            $groups = projetvet_group::get_by_owner($teacherid, $projetvetid);
            if (empty($groups)) {
                // Create new group.
                $teacher = $DB->get_record('user', ['id' => $teacherid], '*', MUST_EXIST);
                $group = new projetvet_group(0, (object)[
                    'projetvetid' => $projetvetid,
                    'name' => get_string('tutorgroupname', 'mod_projetvet', fullname($teacher)),
                    'description' => '',
                    'ownerid' => $teacherid,
                ]);
                $group->create();

                // Add teacher as primary tutor.
                $group->add_member($teacherid, group_member::TYPE_PRIMARY_TUTOR, time(), 0);
            } else {
                $group = reset($groups);
            }
        } else {
            $group = new projetvet_group($groupid);
        }

        if ($memberid) {
            // Update existing member.
            $member = new group_member($memberid);
            $member->set('membertype', $data->membertype);
            $member->update();

            $message = get_string('memberupdated', 'mod_projetvet');
        } else {
            // Add new members using groups API.
            $teacheruserids = !empty($data->teacheruserid) && is_array($data->teacheruserid) ? $data->teacheruserid : [];
            $studentuserids = !empty($data->studentuserid) && is_array($data->studentuserid) ? $data->studentuserid : [];

            $addedcount = \mod_projetvet\local\api\groups::add_members(
                $group->get('id'),
                $teacheruserids,
                $studentuserids
            );

            // Set appropriate message.
            if ($addedcount > 0) {
                $message = get_string('membersadded', 'mod_projetvet', $addedcount);
            } else {
                $message = get_string('nochanges', 'core');
            }
        }

        return [
            'result' => true,
            'message' => $message,
        ];
    }

    /**
     * Get page URL for dynamic submission
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $teacherid = $this->optional_param('teacherid', null, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', null, PARAM_INT);

        return new moodle_url('/mod/projetvet/assign_students.php', [
            'id' => $cmid,
            'teacherid' => $teacherid,
            'projetvetid' => $projetvetid,
        ]);
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $memberid = $this->optional_param('memberid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $teacherid = $this->optional_param('teacherid', 0, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);

        $data = [
            'cmid' => $cmid,
            'memberid' => $memberid,
            'groupid' => $groupid,
            'projetvetid' => $projetvetid,
            'teacherid' => $teacherid,
        ];

        // Get or find group.
        if (empty($groupid) && $teacherid && $projetvetid) {
            // Try to find existing group for this teacher.
            $groups = projetvet_group::get_by_owner($teacherid, $projetvetid);
            if (!empty($groups)) {
                $group = reset($groups);
                $data['groupid'] = $group->get('id');
                $groupid = $group->get('id');
            }
        }

        if ($memberid) {
            // Load existing member data.
            $member = new group_member($memberid);
            $data['userid'] = $member->get('userid');
            $data['membertype'] = $member->get('membertype');

            // Get username for display.
            $user = $DB->get_record('user', ['id' => $member->get('userid')], '*', MUST_EXIST);
            $data['username'] = fullname($user);
        } else if (!empty($groupid)) {
            // Load existing members for the group.
            $group = new projetvet_group($groupid);
            $members = $group->get_members();

            $teacheruserids = [];
            $studentuserids = [];

            foreach ($members as $member) {
                $membertype = $member->get('membertype');
                $userid = $member->get('userid');

                // Skip primary tutor (owner).
                if ($membertype === group_member::TYPE_PRIMARY_TUTOR) {
                    continue;
                }

                if ($membertype === group_member::TYPE_SECONDARY_TUTOR) {
                    $teacheruserids[] = "$userid";
                } else if ($membertype === group_member::TYPE_STUDENT) {
                    $studentuserids[] = "$userid";
                }
            }

            if (!empty($teacheruserids)) {
                $data['teacheruserid'] = $teacheruserids;
            }

            if (!empty($studentuserids)) {
                $data['studentuserid'] = $studentuserids;
            }
        }
        parent::set_data((object) $data);
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $DB, $CFG;

        $mform = $this->_form;

        // Register the custom HTML element type.
        \MoodleQuickForm::registerElementType(
            'html',
            "$CFG->dirroot/mod/projetvet/classes/form/html_element.php",
            'mod_projetvet\form\html_element'
        );
        \MoodleQuickForm::registerElementType(
            'tagselect',
            "$CFG->dirroot/mod/projetvet/classes/form/tagselect_element.php",
            'mod_projetvet\form\tagselect_element'
        );

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $memberid = $this->optional_param('memberid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', 0, PARAM_INT);
        $teacherid = $this->optional_param('teacherid', 0, PARAM_INT);

        // Try to find group if not provided.
        if (empty($groupid) && $teacherid && $projetvetid) {
            $groups = projetvet_group::get_by_owner($teacherid, $projetvetid);
            if (!empty($groups)) {
                $group = reset($groups);
                $groupid = $group->get('id');
            }
        }

        // Hidden fields.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'memberid', $memberid);
        $mform->setType('memberid', PARAM_INT);

        $mform->addElement('hidden', 'groupid', $groupid);
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->setType('projetvetid', PARAM_INT);

        $mform->addElement('hidden', 'teacherid', $teacherid);
        $mform->setType('teacherid', PARAM_INT);

        $modcontext = context_module::instance($cmid);
        $coursecontext = $modcontext->get_course_context();

        // User selection (only for new members).
        if (empty($memberid)) {
            // Get available teachers (excluding current teacher).
            $teacheroptions = \mod_projetvet\local\api\groups::get_available_teachers($cmid, $teacherid);

            if (!empty($teacheroptions)) {
                $groupedteacheroptions = [
                    [
                        'name' => get_string('teachers'),
                        'items' => $teacheroptions,
                    ],
                ];

                $mform->addElement('tagselect', 'teacheruserid', get_string('selectteacher', 'mod_projetvet'), [], [
                    'groupedoptions' => $groupedteacheroptions,
                    'multiple' => true,
                    'showsuggestions' => true,
                    'maxtags' => 1
                ]);
            }

             // Get available students (excluding those already in other groups).
            $studentoptions = \mod_projetvet\local\api\groups::get_available_students($cmid, $projetvetid, $groupid);

            if (!empty($studentoptions)) {
                $groupedstudentoptions = [
                    [
                        'name' => get_string('students'),
                        'items' => $studentoptions,
                    ],
                ];

                // Calculate initial max capacity.
                $rating = \mod_projetvet\local\persistent\teacher_rating::get_or_create_rating($teacherid, $projetvetid);
                $capacity = $rating->get_capacity();

                $mform->addElement('tagselect', 'studentuserid', get_string('selectstudent', 'mod_projetvet'), [], [
                    'groupedoptions' => $groupedstudentoptions,
                    'multiple' => true,
                    'showsuggestions' => true,
                    'maxtags' => $capacity,
                ]);
            }

            // Hidden field for the actual userid that will be submitted.
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
        } else {
            $member = new group_member($memberid);
            $user = $DB->get_record('user', ['id' => $member->get('userid')], '*', MUST_EXIST);

            $mform->addElement('static', 'username', get_string('user'), fullname($user));
            $mform->addElement('hidden', 'userid', $member->get('userid'));
            $mform->setType('userid', PARAM_INT);
        }
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
