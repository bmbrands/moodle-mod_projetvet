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
 * Upgrade script for mod_projetvet.
 *
 * @package   mod_projetvet
 * @copyright 2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade this plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_projetvet_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025111900) {
        // Define field parententryid to be added to projetvet_form_entry.
        $table = new xmldb_table('projetvet_form_entry');
        $field = new xmldb_field('parententryid', XMLDB_TYPE_INTEGER, '10', null, false, null, '0', 'studentid');

        // Conditionally launch add field parententryid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add foreign key for parententryid.
        $key = new xmldb_key('parententryid', XMLDB_KEY_FOREIGN, ['parententryid'], 'projetvet_form_entry', ['id']);
        $dbman->add_key($table, $key);

        // Add index for parententryid.
        $index = new xmldb_index('parententryid_idx', XMLDB_INDEX_NOTUNIQUE, ['parententryid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025111900, 'projetvet');
    }

    if ($oldversion < 2025112000) {
        // Define field statusmsg to be added to projetvet_form_cat.
        $table = new xmldb_table('projetvet_form_cat');
        $field = new xmldb_field('statusmsg', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'entrystatus');

        // Conditionally launch add field statusmsg.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025112000, 'projetvet');
    }

    if ($oldversion < 2025120101) {
        // Define fields promo and currentyear to be dropped from projetvet.
        $table = new xmldb_table('projetvet');

        // Conditionally drop field promo.
        $field = new xmldb_field('promo');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Conditionally drop field currentyear.
        $field = new xmldb_field('currentyear');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025120101, 'projetvet');
    }

    if ($oldversion < 2025120200) {
        // Drop projetvet_thesis table - migrated to flexible form system.
        $table = new xmldb_table('projetvet_thesis');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Drop projetvet_mobility table - migrated to flexible form system.
        $table = new xmldb_table('projetvet_mobility');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025120200, 'projetvet');
    }

    if ($oldversion < 2025121600) {
        // Define table projetvet_teacher_rating to be created.
        $table = new xmldb_table('projetvet_teacher_rating');

        // Adding fields to table projetvet_teacher_rating.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('projetvetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rating', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'average');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_teacher_rating.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('projetvetid', XMLDB_KEY_FOREIGN, ['projetvetid'], 'projetvet', ['id']);

        // Adding indexes to table projetvet_teacher_rating.
        $table->add_index('userid_projetvetid', XMLDB_INDEX_UNIQUE, ['userid', 'projetvetid']);

        // Conditionally launch create table for projetvet_teacher_rating.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2025121600, 'projetvet');
    }

    if ($oldversion < 2026011300) {
        // Define table projetvet_groups to be created.
        $table = new xmldb_table('projetvet_groups');

        // Adding fields to table projetvet_groups.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('projetvetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ownerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_groups.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_projetvetid', XMLDB_KEY_FOREIGN, ['projetvetid'], 'projetvet', ['id']);
        $table->add_key('fk_ownerid', XMLDB_KEY_FOREIGN, ['ownerid'], 'user', ['id']);

        // Conditionally launch create table for projetvet_groups.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table projetvet_group_members to be created.
        $table = new xmldb_table('projetvet_group_members');

        // Adding fields to table projetvet_group_members.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('membertype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'student');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table projetvet_group_members.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'projetvet_groups', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table projetvet_group_members.
        $table->add_index('groupid_userid', XMLDB_INDEX_UNIQUE, ['groupid', 'userid']);
        $table->add_index('membertype_idx', XMLDB_INDEX_NOTUNIQUE, ['membertype']);

        // Conditionally launch create table for projetvet_group_members.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Projetvet savepoint reached.
        upgrade_mod_savepoint(true, 2026011300, 'projetvet');
    }

    return true;
}
