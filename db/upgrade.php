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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_socialcert upgrade from the given old version.
 *
 * @param int $oldversion Version of the plugin installed before the upgrade.
 * @return bool True on success.
 */
function xmldb_local_socialcert_upgrade($oldversion) {
    if ($oldversion < 2026110500) {
        // The "Enable AI" switch of the plugin is now kept by the Datacurso AI provider, which copied
        // the value of enableai into its own setting before this upgrade, so the old setting is removed.
        unset_config('enableai', 'local_socialcert');

        // The new AI assistant switch starts enabled, so existing sites keep the assistant they had.
        // A value already stored by an administrator is never overwritten.
        if (get_config('local_socialcert', 'aiassistant_enabled') === false) {
            set_config('aiassistant_enabled', 1, 'local_socialcert');
        }

        // Socialcert savepoint reached.
        upgrade_plugin_savepoint(true, 2026110500, 'local', 'socialcert');
    }

    if ($oldversion < 2026111100) {
        // The per user hourly limit of the AI assistant (1.1.7) is removed: the usage limit is the
        // per plugin limit of the Datacurso AI provider, so its stored value is deleted.
        unset_config('airatelimit', 'local_socialcert');

        // Socialcert savepoint reached.
        upgrade_plugin_savepoint(true, 2026111100, 'local', 'socialcert');
    }

    return true;
}
