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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_socialcert\event\ai_text_generated;
use local_socialcert\external\ai_helper;
use local_socialcert\fixtures\customcert_dependency_trait;
use local_socialcert\fixtures\stub_ai_services_api;
use local_socialcert\fixtures\testable_ai_helper;
use local_socialcert\output\main_panel;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once(__DIR__ . '/fixtures/customcert_dependency_trait.php');

/**
 * Tests for the AI text generation external function.
 *
 * The external function is the public entry point of the plugin, so every access and
 * business rule is exercised through it. Note that ai_helper::execute() wraps everything
 * after validate_parameters() in a try/catch that converts exceptions into a controlled
 * ['ok' => false, 'message' => ...] payload, so rejections are asserted on the returned
 * array instead of with expectException(). Only malformed parameters still throw, because
 * validate_parameters() runs outside the try block.
 *
 * Since version 1.1.4 the browser only names the activity and the social network: the inputs of
 * the prompt (certificate, course and organization) are computed on the server from the same
 * source the panel renders them from, so no caller can feed the AI service arbitrary text.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 */
final class external_ai_helper_test extends \externallib_advanced_testcase {
    use customcert_dependency_trait;

    /** @var string Name of the single external function declared by the plugin. */
    private const FUNCTIONNAME = 'local_socialcert_get_ai_response';

    /**
     * Build a course with a custom certificate activity and an enrolled student.
     *
     * @return \stdClass Object with course, customcert, cmid, modcontext and student.
     */
    private function create_certificate_fixture(): \stdClass {
        $this->require_customcert();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', ['course' => $course->id]);
        $student = $generator->create_user();

        // Enrol before switching user so capability checks are evaluated on a real participant.
        $generator->enrol_user($student->id, $course->id, 'student');

        return (object) [
            'course' => $course,
            'customcert' => $customcert,
            'cmid' => (int) $customcert->cmid,
            'modcontext' => \context_module::instance((int) $customcert->cmid),
            'student' => $student,
        ];
    }

    /**
     * Record an issued certificate for a user, which is the precondition of the share panel.
     *
     * @param \stdClass $customcert Custom certificate instance.
     * @param \stdClass $user User receiving the issue.
     * @return void
     */
    private function issue_certificate(\stdClass $customcert, \stdClass $user): void {
        global $DB;

        $DB->insert_record('customcert_issues', (object) [
            'userid' => $user->id,
            'customcertid' => $customcert->id,
            'code' => 'ABCDE12345',
            'emailed' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Leave the AI provider without a license key.
     *
     * With an empty license key the HTTP client constructor fails locally (the token manager
     * client refuses to be built) so no request ever leaves the site and the tests stay fast.
     *
     * @return void
     */
    private function disable_provider_license(): void {
        set_config('licensekey', '', 'aiprovider_datacurso');
    }

    /**
     * Skip the current test when the AI provider plugin is not installed on the site.
     *
     * The provider is declared as a dependency of this plugin, but it is not published publicly,
     * so continuous integration environments may run without it. Only the tests that reach the
     * HTTP client need it: every business revalidation happens before the client is built.
     *
     * @return void
     */
    private function require_ai_provider(): void {
        if (\core_component::get_component_directory('aiprovider_datacurso') === null) {
            $this->markTestSkipped('The aiprovider_datacurso plugin is not installed on this site.');
        }
    }

    /**
     * Discard the debugging message that execute() emits for every caught exception.
     *
     * @return void
     */
    private function flush_debugging(): void {
        $this->resetDebugging();
    }

    /**
     * Load the external function wired to a stubbed AI HTTP client.
     *
     * The real client is only reachable through ai_helper::get_ai_client(), which exists precisely
     * so the successful path can be exercised: the constructor of the real client already performs a
     * network request. Everything else under test is inherited untouched from the real class.
     *
     * @return string Fully qualified name of the testable subclass.
     */
    private function load_testable_ai_helper(): string {
        global $CFG;

        $this->require_ai_provider();

        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/stub_ai_services_api.php');
        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/testable_ai_helper.php');

        // A fresh stub per test, so the requests and the failure of one test never leak into another.
        testable_ai_helper::$client = new stub_ai_services_api();

        return testable_ai_helper::class;
    }

    /**
     * Assert that the returned payload is a controlled failure and never a generated text.
     *
     * @param array $result Payload returned by execute().
     * @param string|null $errorcode Expected Moodle error code, when it is deterministic.
     * @return void
     */
    private function assert_controlled_rejection(array $result, ?string $errorcode = null): void {
        $this->assertArrayHasKey('ok', $result, 'A rejection must report ok=false.');
        $this->assertFalse($result['ok'], 'A rejection must report ok=false.');
        $this->assertArrayHasKey('message', $result, 'A rejection must carry a message.');
        $this->assertNotEmpty($result['message'], 'A rejection must carry a non empty message.');
        $this->assertArrayNotHasKey('json', $result, 'A rejection must not return a service payload.');

        if ($errorcode !== null) {
            $this->assertArrayHasKey('errorcode', $result, 'A rejection must expose the Moodle error code.');
            $this->assertSame($errorcode, $result['errorcode']);
        }
    }

    /**
     * Assert that the rejection comes from a business rule and not from the AI provider.
     *
     * The AI provider is intentionally left unconfigured in these tests, so a rejection that
     * mentions the license key (or any transport failure) proves that the plugin let the call
     * reach the HTTP client instead of stopping it beforehand.
     *
     * @param array $result Payload returned by execute().
     * @param string $reason Human readable description of the business rule under test.
     * @return void
     */
    private function assert_business_rejection(array $result, string $reason): void {
        $providerfailures = [
            'invalidlicensekey',
            'curlerror',
            'httperror',
            'emptyresponse',
            'jsondecodeerror',
            'forbidden',
            'notenoughtokens',
            'license_not_allowed',
        ];

        $this->assert_controlled_rejection($result);
        $this->assertNotContains(
            $result['errorcode'] ?? '',
            $providerfailures,
            "{$reason} must be rejected by the plugin, not by the AI provider."
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'licensekey',
            (string) $result['message'],
            "{$reason} must be rejected before the AI HTTP client is built."
        );
    }

    /**
     * MDL-INT-009: The function rejects calls made without an active session.
     */
    public function test_request_without_session_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();

        $this->setUser(null);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result, 'requireloginerror');
    }

    /**
     * MDL-INT-009: The function rejects users with no access to the activity.
     */
    public function test_request_from_user_without_activity_access_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();

        // A confirmed user who is not enrolled and holds no elevated permission.
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result, 'requireloginerror');
    }

    /**
     * MDL-INT-009: The function rejects users without the custom certificate view permission.
     *
     * Moodle stops these users one layer earlier than require_capability(): cm_info::$uservisible
     * already depends on mod/customcert:view, so validate_context() fails first and the reported
     * code is requireloginerror instead of nopermissions. Both are accepted here because either
     * one is a correct rejection of a user without the permission.
     */
    public function test_request_from_user_without_view_capability_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();

        // Prohibit the view capability for the enrolled student at activity level.
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'socialcertdenied']);
        assign_capability('mod/customcert:view', CAP_PROHIBIT, $roleid, $fixture->modcontext->id, true);
        role_assign($roleid, $fixture->student->id, $fixture->modcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($fixture->student);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result);
        $this->assertContains(
            $result['errorcode'] ?? '',
            ['requireloginerror', 'nopermissions'],
            'The user must be rejected because of the missing mod/customcert:view permission.'
        );
    }

    /**
     * MDL-INT-009: The function rejects zero, negative and non existing course module ids.
     */
    public function test_invalid_course_module_ids_are_rejected(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        $this->setUser($fixture->student);

        $zero = ai_helper::execute(0);
        $this->flush_debugging();
        $this->assert_controlled_rejection($zero, 'invalidcmid');

        $negative = ai_helper::execute(-1);
        $this->flush_debugging();
        $this->assert_controlled_rejection($negative, 'invalidcmid');

        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_modules}') + 1000;
        $missing = ai_helper::execute($missingid);
        $this->flush_debugging();
        $this->assert_controlled_rejection($missing);
    }

    /**
     * MDL-INT-009: A non numeric course module id is refused by the declared parameter contract.
     *
     * The parameters are typed since version 1.1.4, so the declared contract is what core/ajax
     * validates before the function runs; it is asserted here through validate_parameters().
     */
    public function test_non_numeric_course_module_id_throws_validation_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        external_api::validate_parameters(ai_helper::execute_parameters(), ['cmid' => 'not-an-id']);
    }

    /**
     * MDL-INT-009: A social network with characters outside the declared type is refused by the contract.
     */
    public function test_malformed_social_network_throws_validation_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        external_api::validate_parameters(ai_helper::execute_parameters(), ['cmid' => 1, 'socialmedia' => 'linked in']);
    }

    /**
     * The social network defaults to LinkedIn when the caller omits it.
     */
    public function test_social_network_defaults_to_linkedin(): void {
        $params = external_api::validate_parameters(ai_helper::execute_parameters(), ['cmid' => 1]);

        $this->assertSame(['cmid' => 1, 'socialmedia' => 'linkedin'], $params);
    }

    /**
     * A social network outside the allowlist is rejected before the AI service is contacted.
     *
     * The network is part of the prompt the service receives, so only the networks the plugin
     * knows how to write for are accepted; anything else is refused without spending credits.
     */
    public function test_social_network_outside_the_allowlist_is_rejected_before_contacting_the_service(): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $sink = $this->redirectEvents();
        $result = $helper::execute($fixture->cmid, 'facebook');
        $events = $sink->get_events();
        $sink->close();
        $this->flush_debugging();

        $this->assert_controlled_rejection($result, 'invalidsocialmedia');
        $this->assertSame(get_string('invalidsocialmedia', 'local_socialcert'), $result['message']);
        $this->assertSame([], $helper::$client->requests, 'The service must not be contacted for an unknown network.');
        $this->assertSame([], $events, 'A rejected generation must leave no trace in the logs.');
    }

    /**
     * The inputs of the prompt are computed on the server, from the same source the panel renders.
     *
     * Until version 1.1.3 the browser sent the certificate, course and organization names in the
     * request, so any caller could feed the AI service arbitrary text. The function now derives the
     * three values from the activity and the user in session, exactly as the assistant card shows
     * them: the names pass through the platform filters and keep their ampersands readable.
     */
    public function test_prompt_inputs_are_computed_on_the_server(): void {
        global $DB;

        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $DB->set_field('course', 'fullname', 'Data & Skills 101', ['id' => $fixture->course->id]);
        $DB->set_field('customcert', 'name', 'AI & Analytics Fundamentals', ['id' => $fixture->customcert->id]);
        rebuild_course_cache($fixture->course->id, true);
        set_config('organizationname', 'Buen Data', 'local_socialcert');
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $result = $helper::execute($fixture->cmid, 'linkedin');

        $this->assertArrayHasKey('json', $result, 'The generation must succeed with the server computed inputs.');
        $this->assertCount(1, $helper::$client->requests, 'Exactly one request must reach the service.');

        $request = $helper::$client->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('/certificate/answer', $request['path']);
        $this->assertSame([
            'certname'    => 'AI & Analytics Fundamentals',
            'course'      => 'Data & Skills 101',
            'org'         => 'Buen Data',
            'socialmedia' => 'linkedin',
        ], $request['body']);

        // The very same values the assistant card renders, so the panel and the prompt never disagree.
        $state = main_panel::get_share_state($fixture->cmid, (int) $fixture->student->id);
        $card = main_panel::get_ai_card_context($fixture->cmid, $state);
        $this->assertSame($card['certname'], $request['body']['certname']);
        $this->assertSame($card['course'], $request['body']['course']);
        $this->assertSame($card['org'], $request['body']['org']);
    }

    /**
     * A rejection of the AI provider reaches the panel with its own localized message and code.
     *
     * The panel shows the message of the provider as is for the rate limit, because it already
     * carries the retry time in the language of the user, so the text of aiprovider_datacurso is
     * one of the two trusted sources of messages (the other one is this plugin).
     */
    public function test_provider_rejection_reaches_the_panel_with_its_localized_message(): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $failure = new \moodle_exception('error_ratelimit_exceeded', 'aiprovider_datacurso', '', '12:00');
        $helper::$client->failure = $failure;

        $result = $helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result, 'error_ratelimit_exceeded');
        $this->assertSame($failure->getMessage(), $result['message']);
    }

    /**
     * A Moodle exception of any other component keeps its code but never its message.
     *
     * The code lets the panel classify the failure; the message of a foreign component (core,
     * the database layer, another plugin) is not written for the user of the panel and may name
     * internals, so the generic message of the plugin is returned instead.
     */
    public function test_rejection_from_another_component_is_reported_with_the_generic_message(): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $failure = new \moodle_exception('invalidrecord', 'error', '', 'customcert_issues');
        $helper::$client->failure = $failure;

        $result = $helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result, 'invalidrecord');
        $this->assertSame(get_string('errorgeneric', 'local_socialcert'), $result['message']);
        $this->assertStringNotContainsString($failure->getMessage(), $result['message']);
    }

    /**
     * Unexpected failures of the generation.
     *
     * @return array[] Class of the throwable and the text it carries.
     */
    public static function unexpected_failure_provider(): array {
        return [
            'runtime exception' => [\RuntimeException::class, 'SELECT secret FROM table'],
            'type error' => [\TypeError::class, 'Argument #1 must be of type secret'],
        ];
    }

    /**
     * An unexpected failure is reported with the generic message and never leaks its text or a code.
     *
     * Until version 1.1.3 the function returned the raw text of any exception to the browser, so a
     * database error could reveal a query. Every throwable is now caught, including PHP errors,
     * the answer carries only the generic message of the plugin, and the detail is kept on the
     * server through the developer debugging output.
     *
     * @dataProvider unexpected_failure_provider
     * @param string $class Class of the throwable the service raises.
     * @param string $text Text of the throwable, which must never reach the browser.
     */
    public function test_unexpected_failure_never_leaks_its_text(string $class, string $text): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $helper::$client->failure = new $class($text);

        $result = $helper::execute($fixture->cmid);

        $this->assert_controlled_rejection($result);
        $this->assertArrayNotHasKey('errorcode', $result, 'Only a Moodle exception carries a code the panel can classify.');
        $this->assertSame(get_string('errorgeneric', 'local_socialcert'), $result['message']);
        $this->assertStringNotContainsString($text, json_encode($result), 'The raw text must never reach the browser.');

        // The detail stays on the server, for the developer only.
        $debugging = $this->getDebuggingMessages();
        $this->assertCount(1, $debugging, 'The failure must be reported once to the developer.');
        $this->assertSame(DEBUG_DEVELOPER, $debugging[0]->level);
        $this->assertStringContainsString($class, $debugging[0]->message);
        $this->assertStringContainsString($text, $debugging[0]->message);
        $this->resetDebugging();
    }

    /**
     * MDL-INT-009: Without a provider license key the request fails locally, before any HTTP call.
     */
    public function test_missing_provider_license_key_fails_before_contacting_the_service(): void {
        $this->resetAfterTest();
        $this->require_ai_provider();
        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        $this->disable_provider_license();

        $this->setUser($fixture->student);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_controlled_rejection($result);
        $this->assertStringContainsStringIgnoringCase(
            'licensekey',
            (string) $result['message'],
            'The failure must come from the unconfigured provider, proving no external call was attempted.'
        );
    }

    /**
     * MDL-INT-009: The function is not published in any external service, including the mobile one.
     */
    public function test_function_is_not_published_in_any_external_service(): void {
        global $DB;

        $function = $DB->get_record('external_functions', ['name' => self::FUNCTIONNAME]);
        $this->assertNotEmpty($function, 'The external function must be registered by the plugin.');
        $this->assertSame('local_socialcert', $function->component);
        $this->assertEmpty($function->services, 'The function must not declare any service by default.');

        $this->assertFalse(
            $DB->record_exists('external_services_functions', ['functionname' => self::FUNCTIONNAME]),
            'The function must not be assigned to any external service.'
        );

        $mobile = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        if ($mobile) {
            $this->assertFalse(
                $DB->record_exists('external_services_functions', [
                    'externalserviceid' => $mobile->id,
                    'functionname' => self::FUNCTIONNAME,
                ]),
                'The function must not be assigned to the official mobile service.'
            );
        }
    }

    /**
     * MDL-INT-010: With AI globally disabled the request is rejected without contacting the service.
     *
     * The plugin never read local_socialcert/enableai in the external function, so the call reached
     * the HTTP client and the failure came from the unconfigured provider (a transport concern)
     * instead of a business rule. The setting is now revalidated before the client is built.
     */
    public function test_request_is_rejected_when_ai_is_globally_disabled(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        $this->disable_provider_license();
        set_config('enableai', 0, 'local_socialcert');

        $this->setUser($fixture->student);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made while AI is globally disabled');
    }

    /**
     * MDL-INT-010: A user without an issued certificate is rejected without consuming credits.
     *
     * The external function never checked customcert_issues, so credits could be spent by a user
     * who owned no certificate. The issue of the session user is now required.
     */
    public function test_request_is_rejected_when_user_has_no_issued_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');

        $this->setUser($fixture->student);

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made without an issued certificate');
    }

    /**
     * MDL-INT-010: A course module that is not a custom certificate is rejected.
     *
     * The function only required mod/customcert:view, a capability every enrolled student holds in
     * any module context of the course, so any course module id was accepted. The module type is
     * now revalidated before the client is built.
     */
    public function test_request_is_rejected_for_a_module_that_is_not_a_custom_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');

        $page = $this->getDataGenerator()->create_module('page', ['course' => $fixture->course->id]);
        $this->setUser($fixture->student);

        $result = ai_helper::execute((int) $page->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request pointing at a non certificate activity');
    }

    /**
     * MDL-CTR-001: The plugin publishes only the documented external functions.
     *
     * The assertion pinned a single function until version 1.1.4, when the panel gained
     * local_socialcert_get_share_state (a read only function that reports whether the certificate
     * is already issued) so it can enable itself without a manual reload. The third one,
     * local_socialcert_log_share, was added for MDL-INT-014: the share happens in the browser, so a
     * write function is the only way the server can learn about it and record the event. The list is
     * asserted exhaustively, so any further function has to be documented here on purpose.
     */
    public function test_plugin_publishes_only_the_documented_external_functions(): void {
        global $DB;

        $functions = [];
        foreach ($DB->get_records('external_functions', ['component' => 'local_socialcert']) as $record) {
            $functions[$record->name] = $record;
        }

        $this->assertEqualsCanonicalizing(
            [self::FUNCTIONNAME, 'local_socialcert_get_share_state', 'local_socialcert_log_share'],
            array_keys($functions),
            'The plugin must expose exactly the three documented external functions.'
        );

        $function = $functions[self::FUNCTIONNAME];
        $this->assertSame(self::FUNCTIONNAME, $function->name);
        $this->assertSame(ai_helper::class, $function->classname);
        $this->assertSame('execute', $function->methodname);

        $info = external_api::external_function_info(self::FUNCTIONNAME);
        // The generation spends the AI credits of the licence and records an event, so it is a write.
        $this->assertSame('write', $info->type);
        $this->assertTrue((bool) $info->allowed_from_ajax, 'The panel calls the function through core/ajax.');
        $this->assertTrue((bool) $info->loginrequired, 'The function must require an active session.');
    }

    /**
     * MDL-CTR-001: The parameter contract exposes the two documented inputs.
     *
     * The contract pinned a body of four free texts until version 1.1.4. The browser now only
     * names the activity and the social network; the inputs of the prompt are computed on the
     * server, so the body is gone from the contract on purpose.
     */
    public function test_parameter_contract_declares_the_documented_inputs(): void {
        $parameters = ai_helper::execute_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $parameters);
        $this->assertEqualsCanonicalizing(['cmid', 'socialmedia'], array_keys($parameters->keys));

        $cmid = $parameters->keys['cmid'];
        $this->assertInstanceOf(external_value::class, $cmid);
        $this->assertSame(PARAM_INT, $cmid->type);
        $this->assertSame(VALUE_REQUIRED, $cmid->required);

        $socialmedia = $parameters->keys['socialmedia'];
        $this->assertInstanceOf(external_value::class, $socialmedia);
        $this->assertSame(PARAM_ALPHANUMEXT, $socialmedia->type);
        $this->assertSame(VALUE_DEFAULT, $socialmedia->required);
        $this->assertSame('linkedin', $socialmedia->default);
    }

    /**
     * MDL-CTR-001: The return contract keeps the success and failure shapes consumed by the panel.
     *
     * The expected type of 'errorcode' was updated from PARAM_ALPHANUMEXT to PARAM_TEXT together
     * with the MDL-CTR-002 fix: real provider codes contain spaces, so the stricter type dropped
     * them while cleaning the response. The assertion pinned the defect, not the contract, so it
     * now pins the type that lets the code reach the panel intact.
     */
    public function test_return_contract_declares_the_documented_output(): void {
        $returns = ai_helper::execute_returns();
        $this->assertInstanceOf(external_single_structure::class, $returns);
        $this->assertEqualsCanonicalizing(
            ['ok', 'message', 'errorcode', 'json'],
            array_keys($returns->keys)
        );

        $expectedtypes = [
            'ok' => PARAM_BOOL,
            'message' => PARAM_RAW,
            'errorcode' => PARAM_TEXT,
            'json' => PARAM_RAW,
        ];
        foreach ($expectedtypes as $key => $type) {
            $this->assertInstanceOf(external_value::class, $returns->keys[$key]);
            $this->assertSame($type, $returns->keys[$key]->type, "Wrong type for {$key}.");
            $this->assertSame(VALUE_OPTIONAL, $returns->keys[$key]->required, "{$key} must be optional.");
        }

        // The success shape is the serialized service response only.
        $success = external_api::clean_returnvalue(
            ai_helper::execute_returns(),
            ['json' => '{"post":"Proud to share my new certificate"}']
        );
        $this->assertSame(['json' => '{"post":"Proud to share my new certificate"}'], $success);

        // The failure shape is a flag, a message and an error code.
        $failure = external_api::clean_returnvalue(
            ai_helper::execute_returns(),
            ['ok' => false, 'message' => 'Something went wrong', 'errorcode' => 'invalidcmid']
        );
        $this->assertFalse($failure['ok']);
        $this->assertSame('Something went wrong', $failure['message']);
        $this->assertSame('invalidcmid', $failure['errorcode']);
        $this->assertArrayNotHasKey('json', $failure);
    }

    /**
     * MDL-CTR-001: A successful generation returns the service payload under the json key.
     *
     * Previously skipped: ai_helper::execute() built aiprovider_datacurso\httpclient\ai_services_api
     * directly and that constructor already performs an HTTP request, so there was no way to
     * exercise the happy path without real network access. The class now obtains the client from the
     * protected ai_helper::get_ai_client() factory, which the fixture subclass overrides, so the
     * success path is asserted through the real function with only the transport substituted.
     */
    public function test_successful_generation_returns_the_service_payload(): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $result = $helper::execute($fixture->cmid);

        $this->assertArrayHasKey('json', $result, 'A successful generation must return the service payload.');
        $this->assertArrayNotHasKey('ok', $result, 'A success must not carry the failure flag.');
        $this->assertSame(
            \local_socialcert\fixtures\stub_ai_services_api::REPLY,
            json_decode($result['json'], true),
            'The payload of the service must reach the panel untouched.'
        );
    }

    /**
     * MDL-INT-014: A successful generation is recorded in the platform logs.
     *
     * Previously there was no event at all, so the consumption of the assistant left no trace. The
     * event is fired only after the service has answered, which is what makes the log a record of
     * the generations that really consumed credits.
     */
    public function test_successful_generation_records_the_ai_event(): void {
        $this->resetAfterTest();
        $helper = $this->load_testable_ai_helper();

        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $sink = $this->redirectEvents();
        $result = $helper::execute($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertArrayHasKey('json', $result, 'The event belongs to a generation that succeeded.');
        $this->assertCount(1, $events, 'A successful generation must record exactly one event.');

        $event = reset($events);
        $this->assertInstanceOf(ai_text_generated::class, $event);
        $this->assertSame('local_socialcert', $event->component);
        $this->assertSame((int) $fixture->student->id, (int) $event->userid, 'The event must name the user.');
        $this->assertSame((int) $fixture->course->id, (int) $event->courseid, 'The event must name the course.');
        $this->assertSame($fixture->cmid, (int) $event->contextinstanceid, 'The event must name the activity.');
        $this->assertSame($fixture->modcontext->id, (int) $event->contextid);
        $this->assertSame('linkedin', $event->other['socialmedia']);
        $this->assertNotEmpty($event->get_description());
    }

    /**
     * MDL-INT-014: A rejected generation records nothing in the platform logs.
     *
     * The event traces the consumption of the assistant, so a request the plugin refused (here,
     * because the user holds no issued certificate) must never reach the log.
     */
    public function test_rejected_generation_records_no_event(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');
        $this->setUser($fixture->student);

        $sink = $this->redirectEvents();
        $result = ai_helper::execute($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made without an issued certificate');
        $this->assertSame([], $events, 'A rejected generation must leave no trace in the logs.');
    }

    /**
     * MDL-INT-015: The function rejects a user without the capability of the AI assistant.
     *
     * The assistant spends the AI credits of the licence, so the capability is revalidated in the
     * web service: hiding the card in the panel does not stop a direct call from consuming credits.
     */
    public function test_request_without_the_assistant_capability_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'socialcertaidenied']);
        assign_capability('local/socialcert:useaiassistant', CAP_PROHIBIT, $roleid, $fixture->modcontext->id, true);
        role_assign($roleid, $fixture->student->id, $fixture->modcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($fixture->student);

        // The activity is still visible, so the rejection can only come from the capability of the
        // assistant, and it happens before the AI HTTP client is built.
        $this->assertTrue(has_capability('mod/customcert:view', $fixture->modcontext));

        $result = ai_helper::execute($fixture->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made without the capability of the assistant');
        $this->assertSame('nopermissions', $result['errorcode'] ?? '');
    }
}
