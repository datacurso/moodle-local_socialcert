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
 * Tests for the upgrade migration of the former site-wide settings to the Workplace default tenant.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\local\tenant_migration
 */
final class tenant_migration_test extends \advanced_testcase {
    /**
     * Tenant-scoped settings move from config_plugins to the default tenant and are removed from config_plugins.
     */
    public function test_moves_tenant_settings_to_default_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $settings = [
            'enableai' => '0',
            'organizationid' => '12345',
            'organizationname' => 'Datacurso',
        ];
        foreach ($settings as $name => $value) {
            set_config($name, $value, 'local_socialcert');
        }

        tenant_migration::migrate_site_data_to_default_tenant();

        $migrated = array_intersect_key(tenant_config::get_all($defaulttenantid), $settings);
        ksort($settings);
        ksort($migrated);
        $this->assertSame($settings, $migrated);
        foreach (array_keys($settings) as $name) {
            $this->assertFalse(get_config('local_socialcert', $name), $name);
        }
    }

    /**
     * Other tenants never receive the former site values.
     */
    public function test_other_tenants_receive_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $othertenantid = (int) $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant()->id;
        set_config('organizationid', '12345', 'local_socialcert');

        tenant_migration::migrate_site_data_to_default_tenant();

        $this->assertSame([], tenant_config::get_all($othertenantid));
    }

    /**
     * Keys that are not tenant settings (such as the plugin version) stay in config_plugins.
     */
    public function test_leaves_non_setting_keys_untouched(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $version = get_config('local_socialcert', 'version');
        set_config('someinternalflag', 'x', 'local_socialcert');

        tenant_migration::migrate_site_data_to_default_tenant();

        $this->assertSame($version, get_config('local_socialcert', 'version'));
        $this->assertSame('x', get_config('local_socialcert', 'someinternalflag'));
        $this->assertArrayNotHasKey('version', tenant_config::get_all($defaulttenantid));
        $this->assertArrayNotHasKey('someinternalflag', tenant_config::get_all($defaulttenantid));
    }

    /**
     * A value the default tenant already stores wins over the former site value, which is still removed.
     */
    public function test_existing_default_tenant_value_wins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        tenant_config::set('organizationid', '777', $defaulttenantid);
        set_config('organizationid', '111', 'local_socialcert');

        tenant_migration::migrate_site_data_to_default_tenant();

        $this->assertSame('777', tenant_config::get_raw('organizationid', $defaulttenantid));
        $this->assertFalse(get_config('local_socialcert', 'organizationid'));
    }

    /**
     * Running the migration twice leaves the same result.
     */
    public function test_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        set_config('organizationname', 'Datacurso', 'local_socialcert');

        tenant_migration::migrate_site_data_to_default_tenant();
        $afterfirst = tenant_config::get_all($defaulttenantid);
        tenant_migration::migrate_site_data_to_default_tenant();

        $this->assertSame($afterfirst, tenant_config::get_all($defaulttenantid));
        $this->assertSame('Datacurso', tenant_config::get_raw('organizationname', $defaulttenantid));
        $this->assertSame(1, $DB->count_records('local_socialcert_tenant_config', [
            'tenantid' => $defaulttenantid,
            'name' => 'organizationname',
        ]));
    }
}
