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

use local_socialcert\fixtures\customcert_dependency_trait;
use local_socialcert\local\tenant_config;
use local_socialcert\output\linkedin_helper;
use local_socialcert\output\main_panel;
use mod_customcert\certificate;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/customcert_dependency_trait.php');

/**
 * Tests that the plugin settings of a tenant never reach the users of another tenant.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\output\linkedin_helper
 * @covers      \local_socialcert\output\main_panel
 */
final class tenant_settings_isolation_test extends \advanced_testcase {
    use customcert_dependency_trait;

    /**
     * Creates one certificate activity with an issued certificate for a student of each tenant.
     *
     * @return \stdClass Object with cmid, the two tenant ids and the two students.
     */
    private function create_two_tenant_scenario(): \stdClass {
        $this->require_customcert();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $tenantgenerator = $generator->get_plugin_generator('tool_tenant');
        $tenanta = (int) $tenantgenerator->create_tenant(['name' => 'Tenant A'])->id;
        $tenantb = (int) $tenantgenerator->create_tenant(['name' => 'Tenant B'])->id;

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', ['course' => $course->id, 'name' => 'AI Fundamentals']);

        $studenta = $generator->create_user();
        $studentb = $generator->create_user();
        $tenantgenerator->allocate_user($studenta->id, $tenanta);
        $tenantgenerator->allocate_user($studentb->id, $tenantb);
        foreach ([$studenta, $studentb] as $student) {
            $generator->enrol_user($student->id, $course->id, 'student');
            certificate::issue_certificate($customcert->id, $student->id);
        }

        return (object) [
            'cmid' => (int) $customcert->cmid,
            'tenanta' => $tenanta,
            'tenantb' => $tenantb,
            'studenta' => $studenta,
            'studentb' => $studentb,
        ];
    }

    /**
     * Returns the share state of a student, logging them in first.
     *
     * @param \stdClass $scenario Scenario returned by {@see self::create_two_tenant_scenario()}.
     * @param \stdClass $student Student record.
     * @return array State of the share panel.
     */
    private function get_state_as(\stdClass $scenario, \stdClass $student): array {
        $this->setUser($student);
        return main_panel::get_share_state($scenario->cmid, (int) $student->id);
    }

    /**
     * The organization ID of tenant A is never used for a user of tenant B.
     */
    public function test_organization_id_of_one_tenant_is_never_used_for_another(): void {
        $this->resetAfterTest();
        $scenario = $this->create_two_tenant_scenario();
        tenant_config::set('organizationid', '111', $scenario->tenanta);
        tenant_config::set('organizationid', '222', $scenario->tenantb);

        $statea = $this->get_state_as($scenario, $scenario->studenta);
        $stateb = $this->get_state_as($scenario, $scenario->studentb);

        $this->assertStringContainsString('organizationId=111', $statea['shareurl']);
        $this->assertStringNotContainsString('organizationId=222', $statea['shareurl']);
        $this->assertStringContainsString('organizationId=222', $stateb['shareurl']);
        $this->assertStringNotContainsString('organizationId=111', $stateb['shareurl']);
    }

    /**
     * A tenant without an organization ID gets no LinkedIn URL, even when another tenant has one.
     */
    public function test_tenant_without_organization_id_gets_no_linkedin_url(): void {
        $this->resetAfterTest();
        $scenario = $this->create_two_tenant_scenario();
        tenant_config::set('organizationid', '111', $scenario->tenanta);
        // The site level value is not a fallback either.
        set_config('organizationid', '999', 'local_socialcert');

        $this->assertNotNull($this->get_state_as($scenario, $scenario->studenta)['shareurl']);

        $stateb = $this->get_state_as($scenario, $scenario->studentb);
        $this->assertNull($stateb['shareurl']);
        $this->assertNull(linkedin_helper::build_linkedin_url('Cert', time(), 'https://example.com/v', 'ABC'));
    }

    /**
     * Without any stored row the AI assistant is enabled by default.
     */
    public function test_ai_is_enabled_by_default_when_the_tenant_stores_nothing(): void {
        $this->resetAfterTest();
        $scenario = $this->create_two_tenant_scenario();

        $this->assertSame([], tenant_config::get_all($scenario->tenanta));
        $this->assertTrue($this->get_state_as($scenario, $scenario->studenta)['enableai']);
    }

    /**
     * One tenant can turn the AI assistant off without affecting the others.
     */
    public function test_one_tenant_can_disable_the_ai_independently(): void {
        $this->resetAfterTest();
        $scenario = $this->create_two_tenant_scenario();
        tenant_config::set('enableai', 0, $scenario->tenantb);

        $this->assertTrue($this->get_state_as($scenario, $scenario->studenta)['enableai']);
        $this->assertFalse($this->get_state_as($scenario, $scenario->studentb)['enableai']);
    }

    /**
     * The organization name sent to the assistant is the one of the tenant of the user.
     */
    public function test_organization_name_is_resolved_per_tenant(): void {
        $this->resetAfterTest();
        $scenario = $this->create_two_tenant_scenario();
        tenant_config::set('organizationname', 'Org A', $scenario->tenanta);
        tenant_config::set('organizationname', 'Org B', $scenario->tenantb);

        $this->setUser($scenario->studenta);
        $inputsa = main_panel::get_ai_prompt_inputs($scenario->cmid, (int) $scenario->studenta->id);
        $this->setUser($scenario->studentb);
        $inputsb = main_panel::get_ai_prompt_inputs($scenario->cmid, (int) $scenario->studentb->id);

        $this->assertSame('Org A', $inputsa['org']);
        $this->assertSame('Org B', $inputsb['org']);
    }
}
