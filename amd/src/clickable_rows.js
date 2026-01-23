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
 * Make reportbuilder table rows clickable
 *
 * @module     mod_projetvet/clickable_rows
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    // Make table rows clickable.
    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr.clickable-row');
        const input = event.target.closest('input, button, select, textarea');
        if (input) {
            // Don't trigger if clicking on a form element.
            return;
        }
        if (!row) {
            return;
        }

        // Don't trigger if clicking on an action dropdown or link.
        if (event.target.closest('.dropdown-toggle') || event.target.closest('a')) {
            return;
        }

        event.preventDefault();

        // Find the link in the dropdown and click it.
        const link = row.querySelector('.dropdown-menu a');
        if (link) {
            link.click();
        }
    });
};
