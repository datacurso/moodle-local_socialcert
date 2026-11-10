<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_socialcert;

use local_socialcert\fixtures\customcert_dependency_trait;
use local_socialcert\fixtures\stub_ai_services_api;
use local_socialcert\fixtures\testable_ai_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once(__DIR__ . '/fixtures/customcert_dependency_trait.php');

/**
 * The AI text generation external function applies no usage limit of its own.
 *
 * Version 1.1.7 refused calls over a per user hourly quota (SOCIALCERT-SEC-002). Since 1.1.8 the
 * usage limit is the per plugin limit of the Datacurso AI provider, enforced by the provider for
 * every plugin that depends on it, so every call that passes the business checks of the function
 * reaches the provider client. This test pins that behaviour so a local quota does not come back
 * unnoticed; the administrator warnings about the provider limit are tested elsewhere.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 */
final class external_ai_helper_no_local_limit_test extends \externallib_advanced_testcase {
    use customcert_dependency_trait;

    /** @var int Consecutive calls of one student, above the former default quota of 10 per hour. */
    private const CALLS = 12;

    /**
     * Load the external function wired to a stubbed provider client.
     *
     * @return string Fully qualified name of the testable subclass.
     */
    private function load_helper(): string {
        global $CFG;

        $this->require_customcert();
        if (\core_component::get_component_directory('aiprovider_datacurso') === null) {
            $this->markTestSkipped('The aiprovider_datacurso plugin is not installed on this site.');
        }

        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/stub_ai_services_api.php');
        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/testable_ai_helper.php');
        testable_ai_helper::$client = new stub_ai_services_api();
        set_config('aiassistant_enabled', 1, 'local_socialcert');

        return testable_ai_helper::class;
    }

    /**
     * Build a certificate activity and a student who has the certificate issued.
     *
     * @return \stdClass Object with cmid and student.
     */
    private function create_fixture(): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', ['course' => $course->id]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $DB->insert_record('customcert_issues', (object) [
            'userid' => $student->id,
            'customcertid' => $customcert->id,
            'code' => 'CODE' . $student->id,
            'emailed' => 0,
            'timecreated' => time(),
        ]);

        return (object) ['cmid' => (int) $customcert->cmid, 'student' => $student];
    }

    /**
     * Every authorized call reaches the provider: no counter, lock or quota of the plugin stops it.
     */
    public function test_every_authorized_call_reaches_the_provider(): void {
        $this->resetAfterTest();
        $helper = $this->load_helper();
        $fixture = $this->create_fixture();
        $this->setUser($fixture->student);

        $sink = $this->redirectEvents();
        for ($i = 1; $i <= self::CALLS; $i++) {
            $result = $helper::execute($fixture->cmid);
            $this->assertArrayHasKey('json', $result, "Call {$i} must reach the provider and return its payload.");
            $this->assertArrayNotHasKey('errorcode', $result, "Call {$i} must not be refused by the plugin.");
        }

        $this->assertCount(
            self::CALLS,
            testable_ai_helper::$client->requests,
            'Every call must produce one request to the provider client.'
        );

        $generated = array_filter(
            $sink->get_events(),
            static fn(\core\event\base $event): bool => $event instanceof \local_socialcert\event\ai_text_generated
        );
        $this->assertCount(self::CALLS, $generated, 'Every generation the provider answered is logged, and only those.');
        $sink->close();
        $this->assertDebuggingNotCalled();
    }

    /**
     * The plugin no longer ships a usage limit setting nor the messages of a local refusal.
     */
    public function test_the_plugin_declares_no_local_limit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $manager = get_string_manager();
        foreach (['airatelimit', 'airatelimit_desc', 'error_ratelimit_busy', 'error_ratelimit_exceeded'] as $identifier) {
            $this->assertFalse(
                $manager->string_exists($identifier, 'local_socialcert'),
                "The string '{$identifier}' belongs to the removed local limit."
            );
        }

        $this->assertFalse(
            method_exists(\local_socialcert\external\ai_helper::class, 'consume_user_quota'),
            'The quota method of the removed local limit must not exist.'
        );
        $this->assertFalse(
            defined(\local_socialcert\external\ai_helper::class . '::DEFAULT_RATE_LIMIT'),
            'The default of the removed local limit must not exist.'
        );
    }
}
