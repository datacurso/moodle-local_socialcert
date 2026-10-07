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

namespace local_socialcert;

use local_socialcert\local\tenant_config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for the Workplace admin tree registration done in settings.php.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class admin_tree_test extends \advanced_testcase {
    /**
     * Creates a tenant and a tenant administrator, and logs in as that administrator.
     *
     * @return int Tenant id.
     */
    private function login_as_new_tenant_admin(): int {
        $this->setAdminUser();
        \tool_tenant\tenancy::add_plugin_capabilities_to_tenant_admin_role('local_socialcert');
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantid = (int) $generator->create_tenant()->id;
        $tenantadmin = $this->getDataGenerator()->create_user();
        $generator->allocate_user($tenantadmin->id, $tenantid);
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$tenantadmin->id], $tenantid);
        $this->setUser($tenantadmin);
        return $tenantid;
    }

    /**
     * A tenant administrator (no site:config) finds the settings page in the tree and may access it.
     */
    public function test_tenant_admin_can_access_the_settings_page(): void {
        $this->resetAfterTest();
        $this->login_as_new_tenant_admin();

        $root = admin_get_root(true, false);

        $page = $root->locate('local_socialcert');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertTrue($page->check_access());
    }

    /**
     * A site administrator finds the settings page under "Local plugins".
     */
    public function test_site_admin_finds_the_page_under_local_plugins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = admin_get_root(true, false);

        $localplugins = $root->locate('localplugins');
        $this->assertInstanceOf(\admin_category::class, $localplugins);
        $page = $localplugins->locate('local_socialcert');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertTrue($page->check_access());
    }

    /**
     * A regular user finds the page registered but may not access it.
     */
    public function test_regular_user_cannot_access_the_settings_page(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $root = admin_get_root(true, false);

        $page = $root->locate('local_socialcert');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertFalse($page->check_access());
    }

    /**
     * The page exposes the scope notice and the three tenant settings, each with its documented default.
     */
    public function test_page_declares_the_notice_and_the_three_tenant_settings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = admin_get_root(true, false)->locate('local_socialcert');

        $names = [];
        foreach ($page->settings as $setting) {
            $names[$setting->name] = $setting;
        }
        $this->assertSame(
            ['tenantscopenotice', 'organizationid', 'organizationname', 'enableai'],
            array_keys($names)
        );
        $this->assertInstanceOf(\local_socialcert\admin\setting_tenant_scope_notice::class, $names['tenantscopenotice']);
        $this->assertInstanceOf(\local_socialcert\admin\setting_configtext::class, $names['organizationid']);
        $this->assertInstanceOf(\local_socialcert\admin\setting_configtext::class, $names['organizationname']);
        $this->assertInstanceOf(\local_socialcert\admin\setting_configcheckbox::class, $names['enableai']);
        $this->assertSame('', $names['organizationid']->get_defaultsetting());
        $this->assertSame('', $names['organizationname']->get_defaultsetting());
        $this->assertEquals(1, $names['enableai']->get_defaultsetting());
    }

    /**
     * Saving the page as a tenant administrator (the admin/settings.php path) stores the values for the own tenant.
     */
    public function test_tenant_admin_saves_settings_page_for_own_tenant(): void {
        $this->resetAfterTest();
        $tenantid = $this->login_as_new_tenant_admin();

        $count = admin_write_settings((object) [
            's_local_socialcert_organizationid' => '54321',
            's_local_socialcert_organizationname' => 'Acme Learning',
        ]);

        $this->assertSame(2, $count);
        $this->assertEmpty(admin_get_root()->errors);
        $this->assertSame('54321', tenant_config::get_raw('organizationid', $tenantid));
        $this->assertSame('Acme Learning', tenant_config::get_raw('organizationname', $tenantid));
        $this->assertFalse(get_config('local_socialcert', 'organizationid'));
        $this->assertFalse(get_config('local_socialcert', 'organizationname'));
    }
}
