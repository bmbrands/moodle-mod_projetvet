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

/**
 * Web service external functions and service definitions.
 *
 * @package    mod_projetvet
 * @category   external
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_projetvet_delete_entry' => [
        'classname'     => 'mod_projetvet\external\delete_entry',
        'methodname'    => 'execute',
        'description'   => 'Delete an activity entry',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:view',
    ],
    'mod_projetvet_get_archive_entry' => [
        'classname'     => 'mod_projetvet\external\get_archive_entry',
        'methodname'    => 'execute',
        'description'   => 'Get (and create on first use) the archive of previously validated projects of a student',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:view',
    ],
    'mod_projetvet_get_entry_list' => [
        'classname'     => 'mod_projetvet\external\entry_list',
        'methodname'    => 'execute',
        'description'   => 'Get entry list for a form set',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:view',
    ],
    'mod_projetvet_get_suggested_ects' => [
        'classname'     => 'mod_projetvet\external\suggested_ects',
        'methodname'    => 'execute',
        'description'   => 'Get suggested ECTS credits for given hours and rang',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:view',
    ],
    'mod_projetvet_get_assign_teacher_modal' => [
        'classname'     => 'mod_projetvet\external\get_assign_teacher_modal',
        'methodname'    => 'execute',
        'description'   => 'Get the HTML body of the assign a teacher selection popup',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:admin',
    ],
    'mod_projetvet_assign_teacher' => [
        'classname'     => 'mod_projetvet\external\assign_teacher',
        'methodname'    => 'execute',
        'description'   => 'Assign a teacher (primary tutor) to a set of students',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:admin',
    ],
    'mod_projetvet_send_assignment_notifications' => [
        'classname'     => 'mod_projetvet\external\send_assignment_notifications',
        'methodname'    => 'execute',
        'description'   => 'Queue manager-confirmed tutor assignment notifications',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:admin',
    ],
    'mod_projetvet_send_message' => [
        'classname'     => 'mod_projetvet\external\send_message',
        'methodname'    => 'execute',
        'description'   => 'Send a contact message to students through the Moodle messaging system',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/projetvet:approve',
    ],
];
