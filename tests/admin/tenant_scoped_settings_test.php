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

namespace local_socialcert\admin;

use local_socialcert\local\tenant_config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for the admin settings that read and write the configuration of the current tenant.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\admin\tenant_scoped_setting
 * @covers     \local_socialcert\admin\setting_configtext
 * @covers     \local_socialcert\admin\setting_configcheckbox
 * @covers     \local_socialcert\admin\setting_tenant_scope_notice
 */
final class tenant_scoped_settings_test extends \advanced_testcase {
    use \local_socialcert\tests\requires_workplace;

    /**
     * Creates a tenant with a user allocated to it and logs in as that user.
     *
     * @param string $name Tenant name.
     * @return int Tenant id.
     */
    private function login_into_new_tenant(string $name = 'Tenant'): int {
        $this->require_tool_tenant();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantid = (int) $generator->create_tenant(['name' => $name])->id;
        $user = $this->getDataGenerator()->create_user();
        $generator->allocate_user($user->id, $tenantid);
        $this->setUser($user);
        return $tenantid;
    }

    /**
     * Builds the organization ID setting as settings.php does.
     *
     * @return setting_configtext
     */
    private function make_text_setting(): setting_configtext {
        return new setting_configtext('local_socialcert/organizationid', 'Organization ID', '', '', PARAM_RAW_TRIMMED);
    }

    /**
     * Builds the AI checkbox as settings.php does.
     *
     * @return setting_configcheckbox
     */
    private function make_checkbox_setting(): setting_configcheckbox {
        return new setting_configcheckbox('local_socialcert/enableai', 'Enable AI', '', 1);
    }

    /**
     * A tenant without a stored value reads the default, never config_plugins.
     */
    public function test_get_setting_returns_default_when_tenant_has_no_value(): void {
        $this->resetAfterTest();
        $this->login_into_new_tenant();
        set_config('organizationid', '999', 'local_socialcert');
        set_config('enableai', '0', 'local_socialcert');

        $this->assertSame('', $this->make_text_setting()->get_setting());
        $this->assertSame('1', $this->make_checkbox_setting()->get_setting());
    }

    /**
     * Saving writes the current tenant's configuration only; other tenants and config_plugins are untouched.
     */
    public function test_write_setting_stores_value_for_current_tenant_only(): void {
        $this->resetAfterTest();
        $othertenantid = $this->login_into_new_tenant('Other');
        $tenantid = $this->login_into_new_tenant('Mine');

        $setting = $this->make_text_setting();
        $this->assertSame('', $setting->write_setting('54321'));

        $this->assertSame('54321', $setting->get_setting());
        $this->assertSame('54321', tenant_config::get_raw('organizationid', $tenantid));
        $this->assertNull(tenant_config::get_raw('organizationid', $othertenantid));
        $this->assertFalse(get_config('local_socialcert', 'organizationid'));
    }

    /**
     * The checkbox stores its on and off values for the current tenant.
     */
    public function test_checkbox_stores_on_and_off_for_current_tenant(): void {
        $this->resetAfterTest();
        $tenantid = $this->login_into_new_tenant();

        $setting = $this->make_checkbox_setting();
        $this->assertSame('', $setting->write_setting('0'));
        $this->assertSame('0', tenant_config::get_raw('enableai', $tenantid));
        $this->assertSame('0', $setting->get_setting());

        $this->assertSame('', $setting->write_setting('1'));
        $this->assertSame('1', tenant_config::get_raw('enableai', $tenantid));
        $this->assertFalse(get_config('local_socialcert', 'enableai'));
    }

    /**
     * Saving the default while the tenant has no value stores nothing (as on install, when defaults are applied).
     */
    public function test_write_setting_skips_default_when_tenant_has_no_value(): void {
        global $DB;
        $this->resetAfterTest();
        $tenantid = $this->login_into_new_tenant();

        $this->assertSame('', $this->make_text_setting()->write_setting(''));
        $this->assertSame('', $this->make_checkbox_setting()->write_setting('1'));

        $this->assertSame(0, $DB->count_records('local_socialcert_tenant_config', ['tenantid' => $tenantid]));
    }

    /**
     * Admin defaults applied on install or upgrade never write tenant rows nor config_plugins.
     */
    public function test_apply_default_settings_writes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $DB->count_records('local_socialcert_tenant_config');

        admin_apply_default_settings(admin_get_root(true, true)->locate('local_socialcert'), true);

        $this->assertSame($before, $DB->count_records('local_socialcert_tenant_config'));
        $this->assertFalse(get_config('local_socialcert', 'organizationid'));
        $this->assertFalse(get_config('local_socialcert', 'organizationname'));
        $this->assertFalse(get_config('local_socialcert', 'enableai'));
    }

    /**
     * The scope notice names the current tenant.
     */
    public function test_scope_notice_names_current_tenant(): void {
        $this->resetAfterTest();
        $this->login_into_new_tenant('Acme');

        $notice = new setting_tenant_scope_notice('local_socialcert/tenantscopenotice');
        $html = $notice->output_html($notice->get_setting());

        $this->assertStringContainsString(get_string('tenantscopenotice', 'local_socialcert', 'Acme'), $html);
        $this->assertTrue($notice->get_setting());
        $this->assertSame('', $notice->write_setting('anything'));
    }
}
