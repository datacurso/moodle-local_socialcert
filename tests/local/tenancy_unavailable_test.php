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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for the plugin on a site without tool_tenant (plain Moodle LMS).
 *
 * Every test simulates the missing tool_tenant, so they run the same on
 * Workplace and on plain Moodle.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\local\tenancy
 * @covers     \local_socialcert\local\tenant_config
 * @covers     \local_socialcert\local\tenant_migration
 */
final class tenancy_unavailable_test extends \advanced_testcase {
    /**
     * Simulates a site without tool_tenant.
     */
    protected function setUp(): void {
        parent::setUp();
        tenancy::simulate_unavailable_for_testing();
        tenant_config::reset_cache();
    }

    /**
     * Restores the real tool_tenant detection.
     */
    protected function tearDown(): void {
        tenancy::reset_for_testing();
        tenant_config::reset_cache();
        parent::tearDown();
    }

    /**
     * The wrapper reports tenancy as unavailable.
     */
    public function test_is_not_available(): void {
        $this->assertFalse(tenancy::is_available());
    }

    /**
     * Reset restores the real detection.
     */
    public function test_reset_restores_real_detection(): void {
        tenancy::reset_for_testing();

        $this->assertSame(class_exists('\tool_tenant\tenancy'), tenancy::is_available());
    }

    /**
     * Everybody belongs to the single implicit tenant 0.
     */
    public function test_tenant_ids_are_the_implicit_tenant(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertSame(0, tenancy::get_tenant_id());
        $this->assertSame(0, tenancy::get_tenant_id($user->id));
        $this->assertSame(0, tenancy::get_default_tenant_id());
        $this->assertSame('', tenancy::get_tenant_name(0));
        $this->assertSame('', tenancy::get_tenant_name(5));
    }

    /**
     * Granting the capabilities to the tenant admin role is a no-op that does not throw.
     */
    public function test_add_capabilities_returns_false_without_throwing(): void {
        $this->assertFalse(tenancy::add_plugin_capabilities_to_tenant_admin_role());
    }

    /**
     * The install hook runs without tool_tenant.
     */
    public function test_install_hook_runs_without_tool_tenant(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/socialcert/db/install.php');

        $this->assertTrue(xmldb_local_socialcert_install());
    }

    /**
     * Values written without an explicit tenant id live in the implicit tenant 0.
     */
    public function test_values_default_to_the_implicit_tenant(): void {
        $this->resetAfterTest();

        tenant_config::set('organizationid', '777', 0);

        $this->assertSame('777', tenant_config::get('organizationid'));
        $this->assertSame('777', tenant_config::get_raw('organizationid', tenancy::get_tenant_id()));
        $this->assertSame('fallback', tenant_config::get('missing', 'fallback'));
    }

    /**
     * The site settings are migrated to tenant 0.
     */
    public function test_migration_moves_site_settings_to_the_implicit_tenant(): void {
        $this->resetAfterTest();
        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', '0', 'local_socialcert');

        tenant_migration::migrate_site_data_to_default_tenant();

        $this->assertSame('12345', tenant_config::get_raw('organizationid', 0));
        $this->assertSame('0', tenant_config::get_raw('enableai', 0));
        $this->assertFalse(get_config('local_socialcert', 'organizationid'));
        $this->assertFalse(get_config('local_socialcert', 'enableai'));
    }

    /**
     * The settings page reads and saves the values of tenant 0, without the tenant notice.
     */
    public function test_settings_page_reads_and_saves_the_implicit_tenant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        tenant_config::set('organizationid', '555', 0);

        $page = admin_get_root(true, false)->locate('local_socialcert');

        $settings = [];
        foreach ($page->settings as $setting) {
            $settings[$setting->name] = $setting;
        }
        $this->assertSame(['organizationid', 'organizationname', 'enableai'], array_keys($settings));
        $this->assertSame('555', $settings['organizationid']->get_setting());

        $count = admin_write_settings((object) ['s_local_socialcert_organizationname' => 'Acme']);
        $this->assertSame(1, $count);
        $this->assertSame('Acme', tenant_config::get_raw('organizationname', 0));
    }
}
