<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * This file keeps track of upgrades to the createcourse module
 *
 * @package    local_createcourse
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 function xmldb_local_createcourse_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023022500) {

        // Define table fetarget to be created.
        $table = new xmldb_table('fe_target');

        // Adding fields to table fetarget.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('rol', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('target_audience', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table fetarget.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for fetarget.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Createcourse savepoint reached.
        upgrade_plugin_savepoint(true, 2023022501, 'local', 'createcourse');
    }

    if ($oldversion < 2023022500) {

        // Define table fedescription to be created.
        $table = new xmldb_table('fe_description');

        // Adding fields to table fedescription.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('summarytext', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('printhours', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('summaryhours', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table fedescription.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for fedescription.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Createcourse savepoint reached.
        upgrade_plugin_savepoint(true, 2023022501, 'local', 'createcourse');
    }

    return true;
}