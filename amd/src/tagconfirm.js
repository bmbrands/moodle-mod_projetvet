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
 * Tag confirmation form element with add competence feature.
 *
 * @module     mod_projetvet/tagconfirm
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Pending from 'core/pending';

class TagConfirm {

    /**
     * Constructor.
     *
     * @param {string} elementId The ID of the element
     * @param {string} elementName The name of the form element
     */
    constructor(elementId, elementName) {
        this.elementId = elementId;
        this.elementName = elementName;
        this.wrapper = document.querySelector(`[data-element-id="${elementId}"]`);

        if (!this.wrapper) {
            return;
        }

        this.popup = this.wrapper.querySelector('[data-region="tagconfirm-popup"]');
        this.searchInput = this.popup?.querySelector('[data-region="tagconfirm-search"]');
        this.selectedAdditionsPopup = this.popup?.querySelector('[data-region="selected-additions-popup"]');
        this.table = this.wrapper.querySelector('[data-region="tagconfirm-table"]');
        this.tbody = this.wrapper.querySelector('[data-region="tagconfirm-tbody"]');

        // Track temporarily selected additions (not yet saved).
        this.tempSelections = new Set();

        this.addEventListeners();
    }

    /**
     * Add event listeners.
     */
    addEventListeners() {
        // Open popup.
        this.wrapper.querySelector('[data-action="open-tagconfirm-popup"]')?.addEventListener('click', () => {
            this.openPopup();
        });

        // Close popup.
        this.popup?.querySelectorAll('[data-action="close-tagconfirm-popup"]').forEach(btn => {
            btn.addEventListener('click', () => {
                this.closePopup();
            });
        });

        // Save additions.
        this.popup?.querySelector('[data-action="save-tagconfirm-additions"]')?.addEventListener('click', () => {
            this.saveAdditions();
        });

        // Select addition from list.
        this.popup?.querySelectorAll('[data-action="select-addition"]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tagId = link.dataset.tagId;
                const tagName = link.dataset.tagName;
                this.addTempSelection(tagId, tagName);
            });
        });

        // Remove temp selection.
        this.popup?.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-action="remove-temp-selection"]');
            if (removeBtn) {
                e.preventDefault();
                const tagId = removeBtn.dataset.tagId;
                this.removeTempSelection(tagId);
            }
        });

        // Search/filter.
        this.searchInput?.addEventListener('input', () => {
            this.filterTags();
        });
    }

    /**
     * Open the popup.
     */
    openPopup() {
        this.popup.classList.remove('d-none');
        this.searchInput?.focus();
        this.tempSelections.clear();
        this.selectedAdditionsPopup.innerHTML = '';
    }

    /**
     * Close the popup.
     */
    closePopup() {
        this.popup.classList.add('d-none');
        this.searchInput.value = '';
        this.filterTags();
        this.tempSelections.clear();
        this.selectedAdditionsPopup.innerHTML = '';
    }

    /**
     * Add a temporary selection.
     *
     * @param {string} tagId
     * @param {string} tagName
     */
    async addTempSelection(tagId, tagName) {
        const pending = new Pending('mod_projetvet/tagconfirm');
        // Check if already exists in table.
        const existingCheckbox = this.tbody.querySelector(`input[data-tag-id="${tagId}"]`);
        if (existingCheckbox) {
            pending.resolve();
            return;
        }

        // Check if already in temp selections.
        if (this.tempSelections.has(tagId)) {
            pending.resolve();
            return;
        }

        this.tempSelections.add(tagId);

        // Add badge to popup.
        const context = {
            tagid: tagId,
            tagname: tagName,
            action: 'remove-temp-selection'
        };

        try {
            const {html, js} = await Templates.renderForPromise('mod_projetvet/tagselect_badge', context);
            Templates.appendNodeContents(this.selectedAdditionsPopup, html, js);
        } finally {
            pending.resolve();
        }
    }

    /**
     * Remove a temporary selection.
     *
     * @param {string} tagId
     */
    removeTempSelection(tagId) {
        this.tempSelections.delete(tagId);

        // Remove badge.
        const badge = this.selectedAdditionsPopup.querySelector(`[data-tag-id="${tagId}"]`);
        if (badge) {
            badge.closest('.badge').remove();
        }
    }

    /**
     * Save additions to the table.
     */
    async saveAdditions() {
        if (this.tempSelections.size === 0) {
            this.closePopup();
            return;
        }

        // Get all tag data from the popup links.
        const tagList = this.popup.querySelector('[data-region="tagconfirm-tag-list"]');
        const allLinks = tagList.querySelectorAll('[data-action="select-addition"]');

        // Group selections by their group heading.
        const groupedAdditions = new Map();

        allLinks.forEach(link => {
            const tagId = link.dataset.tagId;
            if (this.tempSelections.has(tagId)) {
                const tagName = link.dataset.tagName;

                // Find the group heading (previous sibling with bg-light class).
                let groupHeading = '';
                let prev = link.previousElementSibling;
                while (prev) {
                    if (prev.classList.contains('bg-light')) {
                        groupHeading = prev.querySelector('div')?.textContent || '';
                        break;
                    }
                    prev = prev.previousElementSibling;
                }

                if (!groupedAdditions.has(groupHeading)) {
                    groupedAdditions.set(groupHeading, []);
                }
                groupedAdditions.get(groupHeading).push({tagId, tagName});
            }
        });

        // Add rows to table for each group.
        for (const [groupHeading, items] of groupedAdditions) {
            // Check if group already exists in table.
            let groupRow = null;
            const groupRows = this.tbody.querySelectorAll('tr.table-active');
            groupRows.forEach(row => {
                if (row.querySelector('strong')?.textContent === groupHeading) {
                    groupRow = row;
                }
            });

            // If group doesn't exist, create it.
            if (!groupRow) {
                groupRow = document.createElement('tr');
                groupRow.className = 'table-active';
                groupRow.innerHTML = `<td colspan="2"><strong>${groupHeading}</strong></td>`;
                this.tbody.appendChild(groupRow);
            }

            // Add items after the group row.
            for (const {tagId, tagName} of items) {
                const row = document.createElement('tr');
                row.setAttribute('data-tag-id', tagId);
                row.innerHTML = `
                    <td>${tagName}</td>
                    <td class="text-center">
                        <input type="checkbox"
                               name="${this.elementName}[${tagId}]"
                               id="${this.elementId}_${tagId}"
                               value="1"
                               checked
                               class="tagconfirm-checkbox"
                               data-tag-id="${tagId}">
                    </td>
                `;

                // Insert after the group row.
                groupRow.insertAdjacentElement('afterend', row);
            }
        }

        this.closePopup();
    }

    /**
     * Filter tags based on search input.
     */
    filterTags() {
        const searchTerm = this.searchInput.value.toLowerCase();
        const tagList = this.popup.querySelector('[data-region="tagconfirm-tag-list"]');
        const items = tagList.querySelectorAll('[data-action="select-addition"]');

        items.forEach(item => {
            const tagName = item.dataset.tagName.toLowerCase();
            if (tagName.includes(searchTerm)) {
                item.classList.remove('d-none');
                this.highlightMatch(item, searchTerm);
            } else {
                item.classList.add('d-none');
            }
        });
    }

    /**
     * Highlight matching text.
     *
     * @param {HTMLElement} element
     * @param {string} searchTerm
     */
    highlightMatch(element, searchTerm) {
        const tagName = element.dataset.tagName;
        if (!searchTerm) {
            element.innerHTML = tagName;
            return;
        }

        const index = tagName.toLowerCase().indexOf(searchTerm);
        if (index === -1) {
            element.innerHTML = tagName;
            return;
        }

        const before = tagName.slice(0, index);
        const match = tagName.slice(index, index + searchTerm.length);
        const after = tagName.slice(index + searchTerm.length);
        element.innerHTML = `${before}<strong>${match}</strong>${after}`;
    }
}

const init = (elementId, elementName) => {
    new TagConfirm(elementId, elementName);
};

export default {
    init: init
};
