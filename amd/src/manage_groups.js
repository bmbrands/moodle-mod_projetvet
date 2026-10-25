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

import Ajax from 'core/ajax';
import Fragment from 'core/fragment';
import ModalForm from 'core_form/modalform';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import * as Str from 'core/str';

/**
 * Initialize module
 */
export const init = () => {
    // Handle assign students button clicks in the teachers report.
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

        // Open modal form to assign students only.
        showAssignStudentsModal(cmid, teacherid, projetvetid);
    });

    // Handle update teacher rating button clicks in the teachers report.
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

    // Handle assign teacher button clicks in the students report.
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

    // Handle student checkbox changes to show/hide bulk assign button.
    document.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.student-select-checkbox');
        if (!checkbox) {
            return;
        }

        updateBulkAssignButton();
    });

    // Handle upload groups button clicks in assignments page.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="upload-groups"]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const projetvetid = button.dataset.projetvetid;
        const cmid = button.dataset.cmid;
        const courseid = button.dataset.courseid;

        if (!projetvetid || !cmid || !courseid) {
            return;
        }

        // Open modal form to upload groups CSV.
        showUploadGroupsModal(cmid, courseid, projetvetid);
    });
};


/**
 * Show modal form for managing group members
 *
 * @param {number} cmid Course module ID
 * @param {number} teacherid Teacher user ID
 * @param {number} projetvetid Projetvet instance ID
 */
const showAssignStudentsModal = (cmid, teacherid, projetvetid) => {
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
        Str.get_string('assignstudents', 'mod_projetvet')
            .then((title) => {
                modalForm.modal.setTitle(title);
                return title;
            })
            .catch(Notification.exception);
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
        Str.get_string('updateteacherrating', 'mod_projetvet')
            .then((title) => {
                modalForm.modal.setTitle(title);
                return title;
            })
            .catch(Notification.exception);
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
 * Fetch the HTML and JavaScript for the assign teacher modal body.
 *
 * @param {number} cmid Course module ID
 * @param {Array} studentids Array of student user IDs
 * @param {number} projetvetid Projetvet instance ID
 * @return {Promise} Resolves to an object with html and js properties
 */
const fetchAssignTeacherBody = (cmid, studentids, projetvetid) => Ajax.call([{
    methodname: 'mod_projetvet_get_assign_teacher_modal',
    args: {cmid, projetvetid, studentids},
}])[0].then((response) => ({
    html: response.html,
    js: Fragment.processCollectedJavascript(response.javascript),
}));

/**
 * Show a modal to assign a teacher to students.
 *
 * This dialog intentionally uses a plain save/cancel modal rather than a form,
 * because its body contains a reportbuilder report which renders its own filter
 * form. Embedding that report inside a form would produce invalid nested forms,
 * breaking the filter controls and the radio/hidden-field association. The
 * assignment is therefore performed with a web service on save, using the
 * selected teacher radio, instead of submitting a form.
 *
 * @param {number} cmid Course module ID
 * @param {Array} studentids Array of student user IDs
 * @param {number} projetvetid Projetvet instance ID
 */
const showAssignTeacherModal = (cmid, studentids, projetvetid) => {
    ModalSaveCancel.create({
        large: true,
        isVerticallyCentered: true,
        removeOnClose: true,
        returnElement: document.activeElement,
    })
        .then((modal) => {
            modal.getModal().addClass('modal-fullscreen-form');

            // Fetch the popup body (selected students + teachers selection report)
            // and set the modal title.
            const bodyPromise = fetchAssignTeacherBody(cmid, studentids, projetvetid);

            // eslint-disable-next-line promise/no-nesting
            return Promise.all([modal.setBodyContent(bodyPromise), Str.get_string('assignteacher', 'mod_projetvet')])
                .then(([, title]) => {
                    modal.setTitle(title);
                    return modal;
                });
        })
        .then((modal) => {
            // Intercept the save button to perform the assignment.
            modal.getRoot().on(ModalEvents.save, (event) => {
                handleAssignTeacherSave(event, modal, cmid, studentids, projetvetid);
            });

            return modal.show();
        })
        .catch(Notification.exception);
};

/**
 * Handle the save button of the assign teacher modal.
 *
 * @param {object} event The save event
 * @param {object} modal The modal save/cancel instance
 * @param {number} cmid Course module ID
 * @param {Array} studentids Array of student user IDs
 * @param {number} projetvetid Projetvet instance ID
 */
const handleAssignTeacherSave = (event, modal, cmid, studentids, projetvetid) => {
    event.preventDefault();

    const radio = modal.getRoot().find('.teacher-select-radio:checked');
    if (radio.length === 0) {
        Str.get_string('assignselectteacher', 'mod_projetvet')
            .then((message) => {
                Notification.addNotification({message, type: 'error'});
                return message;
            })
            .catch(Notification.exception);
        return;
    }

    const teacherid = Number(radio.first().data('teacherid'));
    Ajax.call([{
        methodname: 'mod_projetvet_assign_teacher',
        args: {cmid, projetvetid, studentids, teacherid},
    }])[0]
        .then((response) => {
            if (response.message) {
                Notification.addNotification({
                    message: response.message,
                    type: 'success',
                });
            }
            modal.hide();
            if (response.notificationchanges && response.notificationchanges.length > 0) {
                showAssignmentNotificationChoice(cmid, projetvetid, response.notificationchanges);
            } else {
                // Reload the page to refresh the reports.
                window.location.reload();
            }
            return response;
        })
        .catch(Notification.exception);
};

/**
 * Show the optional notification choice after an assignment succeeds.
 *
 * @param {number} cmid Course module ID
 * @param {number} projetvetid ProjetVet instance ID
 * @param {Array} changes Assignment state changes
 * @return {Promise} Modal promise
 */
const showAssignmentNotificationChoice = (cmid, projetvetid, changes) => {
    const change = changes[0];
    const scenario = change.beforeprimaryid === 0
        ? 'first_assignment'
        : 'definitive_change';
    const scenarioString = scenario === 'first_assignment'
        ? 'assignmentnotification_first'
        : 'assignmentnotification_definitive';

    return Promise.all([
        Str.get_string('assignmentnotificationchoice', 'mod_projetvet'),
        Str.get_string('sendnotifications', 'mod_projetvet'),
        Str.get_string('donotsendnotifications', 'mod_projetvet'),
        Str.get_string(scenarioString, 'mod_projetvet'),
    ])
        .then(([choiceLabel, sendLabel, doNotSendLabel, scenarioLabel]) => createAssignmentNotificationModal(
            choiceLabel,
            sendLabel,
            doNotSendLabel,
            scenarioLabel
        ))
        .then((modal) => configureAssignmentNotificationModal(modal, cmid, projetvetid, changes, scenario))
        .catch(Notification.exception);
};

/**
 * Create the assignment notification modal.
 *
 * @param {string} choiceLabel Modal title and question
 * @param {string} sendLabel Send button label
 * @param {string} doNotSendLabel Cancel button label
 * @param {string} scenarioLabel Scenario label
 * @return {Promise} Modal promise
 */
const createAssignmentNotificationModal = (choiceLabel, sendLabel, doNotSendLabel, scenarioLabel) => ModalSaveCancel.create({
        isVerticallyCentered: true,
        removeOnClose: true,
        returnElement: document.activeElement,
    }).then((modal) => {
        const body = `<p>${choiceLabel}</p><p><strong>${scenarioLabel}</strong></p>`;
        modal.setBodyContent(Promise.resolve(body));
        modal.setTitle(choiceLabel);
        modal.setSaveButtonText(sendLabel);
        modal.setButtonText('cancel', doNotSendLabel);
        return modal;
     });

/**
 * Configure and display the assignment notification modal.
 *
 * @param {object} modal The modal instance
 * @param {number} cmid Course module ID
 * @param {number} projetvetid ProjetVet instance ID
 * @param {Array} changes Assignment state changes
 * @param {string} scenario Notification scenario
 * @return {Promise} Modal display promise
 */
const configureAssignmentNotificationModal = (modal, cmid, projetvetid, changes, scenario) => {
    modal.getRoot().on(ModalEvents.save, (event) => {
        handleAssignmentNotificationSave(event, modal, cmid, projetvetid, changes, scenario);
    });
    modal.getRoot().on(ModalEvents.cancel, () => window.location.reload());
    return modal.show();
};

/**
 * Send the selected assignment notifications.
 *
 * @param {object} event The save event
 * @param {object} modal The modal instance
 * @param {number} cmid Course module ID
 * @param {number} projetvetid ProjetVet instance ID
 * @param {Array} changes Assignment state changes
 * @param {string} scenario Notification scenario
 * @return {Promise} The AJAX request promise
 */
const handleAssignmentNotificationSave = (event, modal, cmid, projetvetid, changes, scenario) => {
    event.preventDefault();
    return Ajax.call([{
        methodname: 'mod_projetvet_send_assignment_notifications',
        args: {cmid, projetvetid, scenario, changes},
    }])[0]
        .then((response) => {
            if (response.message) {
                Notification.addNotification({message: response.message, type: 'success'});
            }
            modal.hide();
            window.location.reload();
            return response;
        })
        .catch(Notification.exception);
};

/**
 * Update visibility of bulk assign button based on selected checkboxes
 */
const updateBulkAssignButton = () => {
    const button = document.getElementById('assign-selected-students');
    if (!button) {
        return;
    }

    const checkedBoxes = document.querySelectorAll('.student-select-checkbox:checked');
    if (checkedBoxes.length > 0) {
        button.classList.remove('d-none');
    } else {
        button.classList.add('d-none');
    }
};

/**
 * Show modal form for uploading groups from CSV
 *
 * @param {number} cmid Course module ID
 * @param {number} courseid Course ID
 * @param {number} projetvetid Projetvet instance ID
 */
const showUploadGroupsModal = (cmid, courseid, projetvetid) => {
    const modalForm = new ModalForm({
        formClass: '\\mod_projetvet\\form\\group_upload_form',
        args: {
            cmid: cmid,
            courseid: courseid,
            projetvetid: projetvetid,
        },
        returnFocus: document.activeElement,
    });

    modalForm.addEventListener(modalForm.events.LOADED, () => {
        modalForm.modal.getModal().addClass('modal-dialog-centered');
        Str.get_string('uploadgroups', 'mod_projetvet')
            .then((title) => {
                modalForm.modal.setTitle(title);
                return title;
            })
            .catch(Notification.exception);
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
        if (event.detail.message) {
            Notification.addNotification({
                message: event.detail.message,
                type: 'success',
            });
        }
        // Reload the page to refresh the reports.
        window.location.reload();
    });

    modalForm.show();
};
