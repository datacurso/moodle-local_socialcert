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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_socialcert
 * @category    upgrade
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_socialcert upgrade from the given old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_socialcert_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100600) {
        // Define table local_socialcert_tenant_config to be created: the plugin
        // settings of every tenant (there is no site-wide layer).
        $table = new xmldb_table('local_socialcert_tenant_config');

        // Adding fields to table local_socialcert_tenant_config.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tenantid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_socialcert_tenant_config.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('tenantid_name', XMLDB_KEY_UNIQUE, ['tenantid', 'name']);

        // Conditionally launch create table for local_socialcert_tenant_config.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Socialcert savepoint reached.
        upgrade_plugin_savepoint(true, 2026100600, 'local', 'socialcert');
    }

    if ($oldversion < 2026100601) {
        // Grant the plugin capabilities to the Workplace "Tenant administrator" role.
        // The new local/socialcert:managetenantsettings capability must exist first.
        update_capabilities('local_socialcert');
        \tool_tenant\tenancy::add_plugin_capabilities_to_tenant_admin_role('local_socialcert');

        // Socialcert savepoint reached.
        upgrade_plugin_savepoint(true, 2026100601, 'local', 'socialcert');
    }

    if ($oldversion < 2026100602) {
        // Every tenant is now fully independent: the former site-wide settings
        // (config_plugins) are handed to the Workplace default tenant.
        \local_socialcert\local\tenant_migration::migrate_site_data_to_default_tenant();

        // Socialcert savepoint reached.
        upgrade_plugin_savepoint(true, 2026100602, 'local', 'socialcert');
    }

    return true;
}
