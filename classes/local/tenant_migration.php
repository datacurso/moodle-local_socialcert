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

namespace local_socialcert\local;

/**
 * Moves the former site-wide plugin settings to the Workplace default tenant.
 *
 * Before every tenant became fully independent, the plugin settings lived in
 * config_plugins. The upgrade hands them to the default tenant, so the site keeps
 * behaving as before for its users, and nothing remains outside a real tenant.
 *
 * @package    local_socialcert
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_migration {
    /** @var string Plugin whose config_plugins rows are migrated. */
    private const PLUGIN = 'local_socialcert';

    /** @var string[] Tenant-scoped settings. */
    private const SETTINGS = [
        'organizationid',
        'organizationname',
        'enableai',
    ];

    /**
     * Moves the site settings to the default tenant.
     *
     * A value the default tenant already stores wins over the site value; the
     * site value is removed from config_plugins either way. Safe to run more
     * than once.
     *
     * @return void
     */
    public static function migrate_site_data_to_default_tenant(): void {
        $tenantid = tenancy::get_default_tenant_id();

        foreach (get_config(self::PLUGIN) as $name => $value) {
            if (!in_array((string) $name, self::SETTINGS, true)) {
                continue;
            }
            if (tenant_config::get_raw((string) $name, $tenantid) === null) {
                tenant_config::set((string) $name, $value, $tenantid);
            }
            unset_config((string) $name, self::PLUGIN);
        }
    }
}
