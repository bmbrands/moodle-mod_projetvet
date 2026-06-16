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
 * Activity entry form modal
 *
 * @module     mod_projetvet/projetvet_form
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Repository from 'mod_projetvet/repository';
import Templates from 'core/templates';
import Pending from 'core/pending';

/**
 * Initialize ECTS suggestion listeners
 */
const initEctsSuggestion = () => {
    // Find all number inputs with data-action="suggestedects".
    const inputs = document.querySelectorAll('input[type="number"][data-action="suggestedects"]');

    inputs.forEach(input => {
        const suggestionDiv = document.getElementById(input.id + '_suggestion');
        if (!suggestionDiv) {
            return;
        }

        // Find the rang select element and hidden fields.
        const rangSelect = document.querySelector('select.custom-select[name="field_rang"]');
        const projetvetidInput = document.querySelector('input[name="projetvetid"]');
        const studentidInput = document.querySelector('input[name="studentid"]');
        const entryidInput = document.querySelector('input[name="entryid"]');

        // Get the string identifier from data-string attribute.
        const stringIdentifier = input.getAttribute('data-string') || '';

        // Update suggestion when user types.
        const updateSuggestion = async() => {
            const pending = new Pending('mod_projetvet/projetvet_form_suggested_ects');
            const hours = parseFloat(input.value);
            if (isNaN(hours) || hours <= 0) {
                suggestionDiv.innerHTML = '';
                pending.resolve();
                return;
            }

            // Get projetvetid, studentid, and entryid.
            const projetvetid = projetvetidInput ? parseInt(projetvetidInput.value) : 0;
            const studentid = studentidInput ? parseInt(studentidInput.value) : 0;
            const entryid = entryidInput ? parseInt(entryidInput.value) : 0;
            const rangvalue = rangSelect ? parseInt(rangSelect.value) || 0 : 0;

            if (!projetvetid || !studentid) {
                pending.resolve();
                return;
            }

            try {
                // Call the webservice to get suggested ECTS.
                const result = await Repository.getSuggestedEcts({
                    projetvetid: projetvetid,
                    studentid: studentid,
                    entryid: entryid,
                    hours: hours,
                    stringidentifier: stringIdentifier,
                    rangvalue: rangvalue,
                    finalects: 0,
                });

                // Handle error.
                if (result.error) {
                    suggestionDiv.innerHTML = `<div class="alert alert-warning" role="alert">${result.error}</div>`;
                    return;
                }

                // Display the suggestion using the returned message or fallback to simple display.
                let html = result.message || `<strong>ECTS suggérés : ${result.suggestedects}</strong>`;

                if (result.warning) {
                    html += `<div class="alert alert-warning mt-2" role="alert">${result.warning}</div>`;
                }

                suggestionDiv.innerHTML = html;
            } catch (error) {
                Notification.exception(error);
            } finally {
                pending.resolve();
            }
        };

        // Listen for input changes.
        input.addEventListener('input', updateSuggestion);

        // Listen for rang select changes.
        if (rangSelect) {
            rangSelect.addEventListener('change', updateSuggestion);
        }

        // Update immediately if there's already a value.
        if (input.value) {
            updateSuggestion();
        }
    });

    // Find all HTML elements with data-action="getects".
    const htmlElements = document.querySelectorAll('[data-action="getects"]');

    htmlElements.forEach(element => {
        const suggestionDiv = document.getElementById(element.id + '_suggestion');
        if (!suggestionDiv) {
            return;
        }

        // Find the rang select element and hidden fields.
        const rangSelect = document.querySelector('select.custom-select[name="field_rang"]');
        const projetvetidInput = document.querySelector('input[name="projetvetid"]');
        const studentidInput = document.querySelector('input[name="studentid"]');
        const entryidInput = document.querySelector('input[name="entryid"]');
        const hoursInput = document.querySelector('input[type="number"][name="field_hours_completed"]');
        const finalInput = document.querySelector('input[type="number"][name="field_final_ects"]');

        // Get the string identifier from data-string attribute.
        const stringIdentifier = element.getAttribute('data-string') || '';

        // Update suggestion when dependent fields change.
        const updateSuggestion = async() => {
            const pending = new Pending('mod_projetvet/projetvet_form_get_ects');
            const hours = hoursInput ? parseFloat(hoursInput.value) : 0;
            const finalects = finalInput ? parseFloat(finalInput.value) : 0;

            if (isNaN(hours) || hours <= 0) {
                suggestionDiv.innerHTML = '';
                pending.resolve();
                return;
            }

            // Get projetvetid, studentid, and entryid.
            const projetvetid = projetvetidInput ? parseInt(projetvetidInput.value) : 0;
            const studentid = studentidInput ? parseInt(studentidInput.value) : 0;
            const entryid = entryidInput ? parseInt(entryidInput.value) : 0;
            const rangvalue = rangSelect ? parseInt(rangSelect.value) || 0 : 0;

            if (!projetvetid || !studentid) {
                pending.resolve();
                return;
            }

            try {
                // Call the webservice to get suggested ECTS.
                const result = await Repository.getSuggestedEcts({
                    projetvetid: projetvetid,
                    studentid: studentid,
                    entryid: entryid,
                    hours: hours,
                    stringidentifier: stringIdentifier,
                    rangvalue: rangvalue,
                    finalects: finalects,
                });

                // Handle error.
                if (result.error) {
                    suggestionDiv.innerHTML = `<div class="alert alert-warning" role="alert">${result.error}</div>`;
                    return;
                }

                // Display the suggestion using the returned message.
                let html = result.message || '';

                if (result.warning) {
                    html += `<div class="alert alert-warning mt-2" role="alert">${result.warning}</div>`;
                }

                suggestionDiv.innerHTML = html;

                // Set finalInput to suggestedects if it doesn't have a value yet.
                if (finalInput && !finalInput.value) {
                    finalInput.value = result.suggestedects;
                }
            } catch (error) {
                Notification.exception(error);
            } finally {
                pending.resolve();
            }
        };

        // Listen for input changes on hours field.
        if (hoursInput) {
            hoursInput.addEventListener('input', updateSuggestion);
        }

        // Listen for rang select changes.
        if (rangSelect) {
            rangSelect.addEventListener('change', updateSuggestion);
        }

        // Update immediately if there are already values.
        if (hoursInput && hoursInput.value) {
            updateSuggestion();
        }
    });

    // Find all HTML elements with data-action="validateects".
    const validateElements = document.querySelectorAll('[data-action="validateects"]');

    validateElements.forEach(element => {
        const suggestionDiv = document.getElementById(element.id + '_suggestion');
        if (!suggestionDiv) {
            return;
        }
        const stringIdentifier = element.getAttribute('data-string') || '';

        // Update validation message when final ECTS input changes.
        const updateValidation = async() => {
            const finalects = element ? parseFloat(element.value) : 0;
            suggestionDiv.innerHTML = '';
            if (finalects > 10) {
                suggestionDiv.innerHTML = await getString(stringIdentifier, 'mod_projetvet');
                return;
            }
            // Check if decimals are used.
            if (finalects % 1 !== 0) {
                suggestionDiv.innerHTML = await getString('nodecimals', 'mod_projetvet');
                return;
            }
            suggestionDiv.innerHTML = '';
        };

        // Listen for input changes on the element
        element.addEventListener('input', updateValidation);

        // Update immediately if there is already a value.
        if (element.value) {
            updateValidation();
        }
    });
};

export const init = async() => {

    // Check if there's a stored submitpopup message to display after page reload.
    const storedPopup = sessionStorage.getItem('projetvet_submitpopup');
    if (storedPopup) {
        sessionStorage.removeItem('projetvet_submitpopup');
        try {
            const message = await getString(storedPopup, 'mod_projetvet');
            const closeLabel = await getString('ok', 'core');
            await Notification.alert('', message, closeLabel);
        } catch (error) {
            // Silently fail if there's an error getting the string.
        }
    }

    const submitEventHandler = async(event) => {
        // Check if there's a submitpopup message to display.
        const submitpopup = event.detail?.submitpopup || null;

        if (submitpopup) {
            // Store the popup message in sessionStorage to display after reload.
            sessionStorage.setItem('projetvet_submitpopup', submitpopup);
        }

        // Reload the page.
        window.location.reload();
    };

    // Handle create/edit activity button clicks.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-action="activity-entry-form"]')) {
            return;
        }
        const button = event.target.closest('[data-action="activity-entry-form"]');
        event.preventDefault();

        const modalForm = new ModalForm({
            moduleName: 'core/modal',
            formClass: '\\mod_projetvet\\form\\projetvet_form',
            args: {
                ...button.dataset,
            },
        });

        // Add custom class to modal after it's loaded.
        modalForm.addEventListener(modalForm.events.LOADED, () => {
            modalForm.modal.getModal().addClass('modal-fullscreen-form');
            modalForm.modal.getRoot().on('modal:bodyRendered', () => {
                // Initialize ECTS suggestion listeners.
                initEctsSuggestion();
            });
        });

        // Intercept form submission to show dialog only if switch is not checked.

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, submitEventHandler);
        modalForm.show();
    });

    // Make entry table rows clickable - trigger the edit/view action when row is clicked.
    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr.projetvet-entry-row');
        if (!row) {
            return;
        }

        // Don't trigger if clicking on an action button within the row.
        if (event.target.closest('[data-action]') || event.target.closest('.action-menu')) {
            return;
        }

        event.preventDefault();

        // Remove highlight from all rows, then add to clicked row.
        document.querySelectorAll('tr.row-highlight').forEach(r => r.classList.remove('row-highlight'));
        row.classList.add('row-highlight');

        // Find the edit button in this row to get the data attributes.
        const editButton = row.querySelector('[data-action="activity-entry-form"][data-readonly="0"]');
        if (!editButton) {
            return;
        }

        const modalForm = new ModalForm({
            moduleName: 'core/modal',
            formClass: '\\mod_projetvet\\form\\projetvet_form',
            args: {
                ...editButton.dataset,
            },
        });

        modalForm.addEventListener(modalForm.events.LOADED, () => {
            modalForm.modal.getModal().addClass('modal-fullscreen-form');
            // Initialize ECTS suggestion listeners.
            modalForm.modal.getRoot().on('modal:bodyRendered', () => {
                // Initialize ECTS suggestion listeners.
                initEctsSuggestion();
            });
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, submitEventHandler);
        modalForm.show();
    });

    // Handle subset entry form button clicks (forms within forms).
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-action="subset-entry-form"]')) {
            return;
        }
        const button = event.target.closest('[data-action="subset-entry-form"]');
        event.preventDefault();

        const modalForm = new ModalForm({
            moduleName: 'core/modal',
            formClass: '\\mod_projetvet\\form\\projetvet_form',
            args: {
                ...button.dataset,
            },
        });

        // After form submission, reload the subset entries list via AJAX.
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, async() => {
            const pending = new Pending('mod_projetvet/projetvet_form_subset_reload');
            // Get the element ID and container.
            const elementId = button.dataset.elementid;
            const listContainer = document.querySelector(`#${elementId}_list`);

            if (!listContainer) {
                pending.resolve();
                return;
            }

            try {
                // Fetch updated entry list from the webservice.
                const data = await Repository.getEntryList({
                    projetvetid: parseInt(button.dataset.projetvetid),
                    studentid: parseInt(button.dataset.studentid),
                    formsetidnumber: button.dataset.formsetidnumber,
                    parententryid: parseInt(button.dataset.parententryid),
                });

                // Build context for the partial template.
                const context = {
                    hasentries: data.activities && data.activities.length > 0,
                    entries: data.activities && data.activities.length > 0 ? {
                        headers: data.listfields.map(field => ({name: field.name})),
                        rows: data.activities.map(activity => ({
                            id: activity.id,
                            fields: activity.fields.map(field => ({value: field.displayvalue})),
                        })),
                    } : null,
                    subsetformsetidnumber: button.dataset.formsetidnumber,
                    parententryid: button.dataset.parententryid,
                    studentid: button.dataset.studentid,
                    cmid: button.dataset.cmid,
                    projetvetid: button.dataset.projetvetid,
                    elementid: elementId,
                };

                // Re-render the entry list using the partial template.
                const {html, js} = await Templates.renderForPromise(
                    'mod_projetvet/form/element_subset_entries',
                    context
                );
                Templates.replaceNodeContents(listContainer, html, js);
            } catch (error) {
                Notification.exception(error);
            } finally {
                pending.resolve();
            }
        });

        modalForm.show();
    });

    // Handle form button clicks for save/submit actions.
    document.addEventListener('click', async(event) => {
        if (!event.target.closest('.projetvet-form-button')) {
            return;
        }
        const button = event.target.closest('.projetvet-form-button');
        event.preventDefault();

        const entrystatus = button.dataset.entrystatus;
        const emailAction = button.dataset.emailaction;
        const form = button.closest('form');

        // Handle email action.
        if (emailAction && form) {
            const studentEmailInput = form.querySelector('input[name="studentemail"]');
            if (studentEmailInput && studentEmailInput.value) {
                const email = studentEmailInput.value;
                const subjectString = await getString(emailAction + '_subject', 'mod_projetvet');
                const bodyString = await getString(emailAction, 'mod_projetvet');
                const subject = encodeURIComponent(subjectString);
                const body = encodeURIComponent(bodyString);
                window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
            }
            return;
        }

        if (form) {
            // Add a hidden field to indicate button submission.
            let buttonField = form.querySelector('input[name="button_entrystatus"]');
            if (!buttonField) {
                buttonField = document.createElement('input');
                buttonField.type = 'hidden';
                buttonField.name = 'button_entrystatus';
                form.appendChild(buttonField);
            }
            buttonField.value = entrystatus;

            // Add submitpopup value if present.
            const submitpopup = button.dataset.submitpopup;
            if (submitpopup) {
                let popupField = form.querySelector('input[name="button_submitpopup"]');
                if (!popupField) {
                    popupField = document.createElement('input');
                    popupField.type = 'hidden';
                    popupField.name = 'button_submitpopup';
                    form.appendChild(popupField);
                }
                popupField.value = submitpopup;
            }

            // Add teachermessage value if present.
            const teachermessage = button.dataset.teachermessage;
            if (teachermessage) {
                let messageField = form.querySelector('input[name="button_teachermessage"]');
                if (!messageField) {
                    messageField = document.createElement('input');
                    messageField.type = 'hidden';
                    messageField.name = 'button_teachermessage';
                    form.appendChild(messageField);
                }
                messageField.value = teachermessage;
            }

            // Add studentmessage value if present.
            const studentmessage = button.dataset.studentmessage;
            if (studentmessage) {
                let studentMessageField = form.querySelector('input[name="button_studentmessage"]');
                if (!studentMessageField) {
                    studentMessageField = document.createElement('input');
                    studentMessageField.type = 'hidden';
                    studentMessageField.name = 'button_studentmessage';
                    form.appendChild(studentMessageField);
                }
                studentMessageField.value = studentmessage;
            }

            // Submit the form.
            form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
        }
    });

    // Handle delete activity button clicks.
    document.addEventListener('click', async(event) => {
        if (!event.target.closest('[data-action="delete-activity"]')) {
            return;
        }
        event.preventDefault();

        const button = event.target.closest('[data-action="delete-activity"]');
        const entryid = button.dataset.entryid;

        if (!entryid) {
            return;
        }

        const confirmString = await getString('confirmdeleteactivity', 'mod_projetvet');

        Notification.confirm(
            getString('confirm'),
            confirmString,
            getString('delete'),
            getString('cancel'),
            async() => {
                const pending = new Pending('mod_projetvet/projetvet_form_delete_entry');
                try {
                    await Repository.deleteEntry({entryid: parseInt(entryid)});
                    window.location.reload();
                } catch (error) {
                    Notification.exception(error);
                } finally {
                    pending.resolve();
                }
            }
        );
    });
};
