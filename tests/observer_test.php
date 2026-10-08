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

/**
 * Tests for the tenant_deleted observer cleanup.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\observer
 */
final class observer_test extends \advanced_testcase {
    use \local_socialcert\tests\requires_workplace;

    /**
     * Deleting a tenant removes its settings, keeping other tenants intact.
     */
    public function test_tenant_deleted_removes_tenant_settings(): void {
        global $DB;

        $this->require_tool_tenant();
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int) $tenantgenerator->create_tenant()->id;
        $tenantb = (int) $tenantgenerator->create_tenant()->id;

        tenant_config::set('organizationid', '111', $tenanta);
        tenant_config::set('enableai', 0, $tenanta);
        tenant_config::set('organizationid', '222', $tenantb);

        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenanta);
        $manager->delete_tenant($tenanta);

        $this->assertSame(0, $DB->count_records('local_socialcert_tenant_config', ['tenantid' => $tenanta]));
        $this->assertSame(1, $DB->count_records('local_socialcert_tenant_config', ['tenantid' => $tenantb]));
        $this->assertSame('222', tenant_config::get_raw('organizationid', $tenantb));
    }

    /**
     * The observer ignores an event that does not carry a real tenant id.
     */
    public function test_tenant_deleted_ignores_invalid_tenant_id(): void {
        global $DB;

        $this->require_tool_tenant();
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenantid = (int) $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant()->id;
        tenant_config::set('organizationid', '222', $tenantid);

        $event = \tool_tenant\event\tenant_deleted::create([
            'objectid' => 0,
            'context' => \context_system::instance(),
        ]);
        observer::tenant_deleted($event);

        $this->assertSame(1, $DB->count_records('local_socialcert_tenant_config', ['tenantid' => $tenantid]));
    }
}
