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
 * Gateway to the webservices.
 *
 * @module     mod_projetvet/repository
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Projetvet repository class.
 */
class Repository {

    /**
     * Delete an activity entry.
     * @param {Object} args The arguments.
     * @return {Promise} The promise.
     */
    deleteEntry(args) {
        const request = {
            methodname: 'mod_projetvet_delete_entry',
            args: args
        };

        let promise = Ajax.call([request])[0]
            .fail(Notification.exception);

        return promise;
    }

    /**
     * Get entry list for a formset.
     * @param {Object} args The arguments containing projetvetid, studentid, formsetidnumber, parententryid.
     * @return {Promise} The promise.
     */
    getEntryList(args) {
        const request = {
            methodname: 'mod_projetvet_get_entry_list',
            args: args
        };

        let promise = Ajax.call([request])[0]
            .fail(Notification.exception);

        return promise;
    }

    /**
     * Get (and create on first use) the archive entry of a student.
     * @param {Object} args The arguments containing cmid and studentid.
     * @return {Promise} The promise.
     */
    getArchiveEntry(args) {
        const request = {
            methodname: 'mod_projetvet_get_archive_entry',
            args: args
        };

        let promise = Ajax.call([request])[0]
            .fail(Notification.exception);

        return promise;
    }

    /**
     * Get suggested ECTS for given hours and rang.
     * @param {Object} args The arguments containing projetvetid, studentid, entryid, hours, stringidentifier, rangvalue, finalects.
     * @return {Promise} The promise.
     */
    getSuggestedEcts(args) {
        const request = {
            methodname: 'mod_projetvet_get_suggested_ects',
            args: args
        };

        let promise = Ajax.call([request])[0]
            .fail(Notification.exception);

        return promise;
    }

    /**
     * Send a contact message to students through the Moodle messaging system.
     * @param {Object} args The arguments containing cmid, projetvetid, studentids, subjectkey, bodykey.
     * @return {Promise} The promise.
     */
    sendMessage(args) {
        const request = {
            methodname: 'mod_projetvet_send_message',
            args: args
        };

        let promise = Ajax.call([request])[0]
            .fail(Notification.exception);

        return promise;
    }
}

const RepositoryInstance = new Repository();

export default RepositoryInstance;
