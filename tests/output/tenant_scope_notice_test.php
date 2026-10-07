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

namespace local_socialcert\output;

/**
 * Tests for the read-only notice naming the tenant the configuration page applies to.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\output\tenant_scope_notice
 */
final class tenant_scope_notice_test extends \advanced_testcase {
    /**
     * Creates a tenant with the given name, allocates a new user to it and logs that user in.
     *
     * @param string $name Tenant name.
     * @return int Tenant id.
     */
    private function login_into_new_tenant(string $name): int {
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantid = (int) $generator->create_tenant(['name' => $name])->id;
        $user = $this->getDataGenerator()->create_user();
        $generator->allocate_user($user->id, $tenantid);
        $this->setUser($user);
        return $tenantid;
    }

    /**
     * The notice names the tenant of the current user and offers no tenant selector.
     */
    public function test_names_current_tenant_without_selector(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->login_into_new_tenant('Tenant, Alpha');
        $output = $PAGE->get_renderer('core');

        $notice = new tenant_scope_notice();
        $exported = $notice->export_for_template($output);
        $html = $output->render($notice);

        $expected = get_string('tenantscopenotice', 'local_socialcert', 'Tenant, Alpha');
        $this->assertSame($expected, $exported['message']);
        $this->assertFalse($exported['hasdescription']);
        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString('<select', $html);
        $this->assertStringNotContainsString('tenantid', $html);
    }

    /**
     * An optional page description is shown together with the notice.
     */
    public function test_optional_description(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->login_into_new_tenant('Beta');
        $output = $PAGE->get_renderer('core');

        $exported = (new tenant_scope_notice('Settings of this tenant.'))->export_for_template($output);

        $this->assertTrue($exported['hasdescription']);
        $this->assertSame('Settings of this tenant.', $exported['description']);
        $this->assertSame(get_string('tenantscopenotice', 'local_socialcert', 'Beta'), $exported['message']);
    }
}
