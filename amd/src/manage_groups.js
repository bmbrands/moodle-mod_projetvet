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
 * Manage teacher groups with modal forms
 *
 * @module     mod_projetvet/manage_groups
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';

/**
 * Initialize module
 */
export const init = () => {
    // Handle assign students button clicks in admin teachers report.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="assign-students"]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const teacherid = button.dataset.teacherid;
        const projetvetid = button.dataset.projetvetid;
        const cmid = button.dataset.cmid;

        if (!teacherid || !projetvetid || !cmid) {
            return;
        }

        // Open modal form to add/manage members.
        showManageMembersModal(cmid, teacherid, projetvetid);
    });

    // Handle update teacher rating button clicks in admin teachers report.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="update-teacher-rating"]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const teacherid = button.dataset.teacherid;
        const projetvetid = button.dataset.projetvetid;
        const cmid = button.dataset.cmid;

        if (!teacherid || !projetvetid || !cmid) {
            return;
        }

        // Open modal form to update teacher settings.
        showTeacherSettingsModal(cmid, teacherid, projetvetid);
    });

    // Handle assign teacher button clicks in admin students report.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="assign-teacher"]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const projetvetid = button.dataset.projetvetid;
        const cmid = button.dataset.cmid;
        const userid = button.dataset.userid;

        if (!projetvetid || !cmid) {
            return;
        }

        // Get all checked student checkboxes.
        const checkedBoxes = document.querySelectorAll('.student-select-checkbox:checked');
        const studentids = Array.from(checkedBoxes).map(cb => cb.dataset.studentid);

        // Always add the userid from the clicked button if it exists.
        if (userid && !studentids.includes(userid)) {
            studentids.push(userid);
        }

        if (studentids.length === 0) {
            Notification.addNotification({
                message: 'Please select at least one student',
                type: 'error',
            });
            return;
        }

        // Open modal form to select teacher.
        showAssignTeacherModal(cmid, studentids, projetvetid);
    });

    // Handle teacher radio button selection in edit_teacher_form modal.
    document.addEventListener('change', (event) => {
        const radio = event.target.closest('.teacher-select-radio');
        if (!radio) {
            return;
        }

        const teacherid = radio.dataset.teacherid;
        if (!teacherid) {
            return;
        }

        // Find the hidden teacherid field in the form and set its value.
        const form = radio.closest('form');
        if (form) {
            const hiddenField = form.querySelector('input[name="teacherid"]');
            if (hiddenField) {
                hiddenField.value = teacherid;
            }
        }
    });
};


/**
 * Show modal form for managing group members
 *
 * @param {number} cmid Course module ID
 * @param {number} teacherid Teacher user ID
 * @param {number} projetvetid Projetvet instance ID
 */
const showManageMembersModal = (cmid, teacherid, projetvetid) => {
    const modalForm = new ModalForm({
        formClass: '\\mod_projetvet\\form\\edit_member_form',
        args: {
            cmid: cmid,
            teacherid: teacherid,
            projetvetid: projetvetid,
            memberid: 0,
            groupid: 0, // Will be created or loaded on the server side.
        },
        returnFocus: document.activeElement,
    });

    // Add custom class to modal after it's loaded.
    modalForm.addEventListener(modalForm.events.LOADED, () => {
        modalForm.modal.getModal().addClass('modal-fullscreen-form');
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        if (event.detail.message) {
            Notification.addNotification({
                message: event.detail.message,
                type: 'success',
            });
        }
        // Reload the page to refresh the report.
        window.location.reload();
    });

    modalForm.show();
};

/**
 * Show modal form for updating teacher settings (rating/capacity)
 *
 * @param {number} cmid Course module ID
 * @param {number} teacherid Teacher user ID
 * @param {number} projetvetid Projetvet instance ID
 */
const showTeacherSettingsModal = (cmid, teacherid, projetvetid) => {
    const modalForm = new ModalForm({
        formClass: '\\mod_projetvet\\form\\teacher_settings_form',
        args: {
            cmid: cmid,
            teacherid: teacherid,
            projetvetid: projetvetid,
        },
        returnFocus: document.activeElement,
    });

    // Add custom class to modal after it's loaded.
    modalForm.addEventListener(modalForm.events.LOADED, () => {
        modalForm.modal.getModal().addClass('modal-dialog-centered');
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        if (event.detail.message) {
            Notification.addNotification({
                message: event.detail.message,
                type: 'success',
            });
        }
        // Reload the page to refresh the report.
        window.location.reload();
    });

    modalForm.show();
};

/**
 * Show modal form for assigning a teacher to students
 *
 * @param {number} cmid Course module ID
 * @param {Array} studentids Array of student user IDs
 * @param {number} projetvetid Projetvet instance ID
 */
const showAssignTeacherModal = (cmid, studentids, projetvetid) => {
    const modalForm = new ModalForm({
        formClass: '\\mod_projetvet\\form\\edit_teacher_form',
        args: {
            cmid: cmid,
            studentids: JSON.stringify(studentids),
            projetvetid: projetvetid,
        },
        returnFocus: document.activeElement,
    });

    // Add custom class to modal after it's loaded.
    modalForm.addEventListener(modalForm.events.LOADED, () => {
        modalForm.modal.getModal().addClass('modal-dialog-centered modal-fullscreen-form');
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        if (event.detail.message) {
            Notification.addNotification({
                message: event.detail.message,
                type: 'success',
            });
        }
        // Reload the page to refresh the report.
        window.location.reload();
    });

    modalForm.show();
};
