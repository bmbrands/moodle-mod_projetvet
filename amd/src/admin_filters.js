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
 * Admin filters for students and teachers reports
 *
 * @module     mod_projetvet/admin_filters
 * @copyright  2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize module
 */
export const init = () => {
    // Handle student filter toggle.
    const studentFilterToggle = document.getElementById('filter-students-without-teacher');
    if (studentFilterToggle) {
        studentFilterToggle.addEventListener('change', () => {
            const filterActive = studentFilterToggle.checked ? 1 : 0;

            // Reload page with filter parameter.
            const url = new URL(window.location.href);
            url.searchParams.set('filterstudents', filterActive);
            window.location.href = url.toString();
        });

        // Set initial state from URL parameter.
        const urlParams = new URLSearchParams(window.location.search);
        const filterStudents = urlParams.get('filterstudents');
        if (filterStudents === '1') {
            studentFilterToggle.checked = true;
        }
    }

    // Handle teacher filter toggle.
    const teacherFilterToggle = document.getElementById('filter-teachers-with-capacity');
    if (teacherFilterToggle) {
        teacherFilterToggle.addEventListener('change', () => {
            const filterActive = teacherFilterToggle.checked ? 1 : 0;

            // Reload page with filter parameter.
            const url = new URL(window.location.href);
            url.searchParams.set('filterteachers', filterActive);
            window.location.href = url.toString();
        });

        // Set initial state from URL parameter.
        const urlParams = new URLSearchParams(window.location.search);
        const filterTeachers = urlParams.get('filterteachers');
        if (filterTeachers === '1') {
            teacherFilterToggle.checked = true;
        }
    }
};
