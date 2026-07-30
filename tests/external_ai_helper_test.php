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
use local_socialcert\external\ai_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

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
 * The whole class runs in separate processes because classes/external/ai_helper.php still does a
 * file level require_once of lib/externallib.php, and that file calls require_phpunit_isolation().
 * Without isolation the class cannot even be autoloaded during a test run.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class external_ai_helper_test extends \externallib_advanced_testcase {

    /** @var string Name of the single external function declared by the plugin. */
    private const FUNCTIONNAME = 'local_socialcert_get_ai_response';

    /**
     * Build a course with a custom certificate activity and an enrolled student.
     *
     * @return \stdClass Object with course, customcert, cmid, modcontext and student.
     */
    private function create_certificate_fixture(): \stdClass {
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
     * Default request body accepted by the external function.
     *
     * @return array Body payload.
     */
    private function sample_body(): array {
        return [
            'certname' => 'Advanced Moodle Development',
            'course' => 'Moodle administration',
            'org' => 'Datacurso',
            'socialmedia' => 'linkedin',
        ];
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
     * Discard the debugging message that execute() emits for every caught exception.
     *
     * @return void
     */
    private function flush_debugging(): void {
        $this->resetDebugging();
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

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
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

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
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

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
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

        $zero = ai_helper::execute($this->sample_body(), 0);
        $this->flush_debugging();
        $this->assert_controlled_rejection($zero, 'invalidcmid');

        $negative = ai_helper::execute($this->sample_body(), -1);
        $this->flush_debugging();
        $this->assert_controlled_rejection($negative, 'invalidcmid');

        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_modules}') + 1000;
        $missing = ai_helper::execute($this->sample_body(), $missingid);
        $this->flush_debugging();
        $this->assert_controlled_rejection($missing);
    }

    /**
     * MDL-INT-009: A body missing a documented key throws the parameter validation exception.
     *
     * validate_parameters() runs before the try/catch, so malformed input still propagates.
     */
    public function test_body_missing_a_required_key_throws_validation_exception(): void {
        $body = $this->sample_body();
        unset($body['certname']);

        $this->expectException(\invalid_parameter_exception::class);
        ai_helper::execute($body, 1);
    }

    /**
     * MDL-INT-009: A non numeric course module id throws the parameter validation exception.
     */
    public function test_non_numeric_course_module_id_throws_validation_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        ai_helper::execute($this->sample_body(), 'not-an-id');
    }

    /**
     * MDL-INT-009: Without a provider license key the request fails locally, before any HTTP call.
     */
    public function test_missing_provider_license_key_fails_before_contacting_the_service(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        $this->disable_provider_license();

        $this->setUser($fixture->student);

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
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
     * [Pendiente:fail] The plugin never reads local_socialcert/enableai in the external function, so
     * the call reaches the HTTP client. The AI provider is left without a license key, so the
     * failure today is the provider refusing to build (a transport concern) instead of a business
     * rejection, which is exactly what this assertion rules out. This test MUST FAIL until the
     * revalidation is implemented.
     */
    public function test_request_is_rejected_when_ai_is_globally_disabled(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->issue_certificate($fixture->customcert, $fixture->student);
        $this->disable_provider_license();
        set_config('enableai', 0, 'local_socialcert');

        $this->setUser($fixture->student);

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made while AI is globally disabled');
    }

    /**
     * MDL-INT-010: A user without an issued certificate is rejected without consuming credits.
     *
     * [Pendiente:fail] The external function never checks customcert_issues, so credits can be
     * spent by a user who owns no certificate. This test MUST FAIL until the check is added.
     */
    public function test_request_is_rejected_when_user_has_no_issued_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');

        $this->setUser($fixture->student);

        $result = ai_helper::execute($this->sample_body(), $fixture->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request made without an issued certificate');
    }

    /**
     * MDL-INT-010: A course module that is not a custom certificate is rejected.
     *
     * [Pendiente:fail] The function only requires mod/customcert:view, a capability every enrolled
     * student holds in any module context of the course, so any course module id is accepted.
     * This test MUST FAIL until the module type is revalidated.
     */
    public function test_request_is_rejected_for_a_module_that_is_not_a_custom_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();
        $this->disable_provider_license();
        set_config('enableai', 1, 'local_socialcert');

        $page = $this->getDataGenerator()->create_module('page', ['course' => $fixture->course->id]);
        $this->setUser($fixture->student);

        $result = ai_helper::execute($this->sample_body(), (int) $page->cmid);
        $this->flush_debugging();

        $this->assert_business_rejection($result, 'A request pointing at a non certificate activity');
    }

    /**
     * MDL-CTR-001: The plugin publishes exactly one external function with the documented metadata.
     */
    public function test_plugin_publishes_a_single_external_function(): void {
        global $DB;

        $functions = $DB->get_records('external_functions', ['component' => 'local_socialcert']);
        $this->assertCount(1, $functions, 'The plugin must expose exactly one external function.');

        $function = reset($functions);
        $this->assertSame(self::FUNCTIONNAME, $function->name);
        $this->assertSame(ai_helper::class, $function->classname);
        $this->assertSame('execute', $function->methodname);

        $info = external_api::external_function_info(self::FUNCTIONNAME);
        $this->assertSame('read', $info->type);
        $this->assertTrue((bool) $info->allowed_from_ajax, 'The panel calls the function through core/ajax.');
        $this->assertTrue((bool) $info->loginrequired, 'The function must require an active session.');
    }

    /**
     * MDL-CTR-001: The parameter contract exposes the five documented inputs.
     */
    public function test_parameter_contract_declares_the_documented_inputs(): void {
        $parameters = ai_helper::execute_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $parameters);
        $this->assertEqualsCanonicalizing(['body', 'cmid'], array_keys($parameters->keys));

        $body = $parameters->keys['body'];
        $this->assertInstanceOf(external_single_structure::class, $body);
        $this->assertEqualsCanonicalizing(
            ['certname', 'course', 'org', 'socialmedia'],
            array_keys($body->keys)
        );

        foreach (['certname', 'course', 'org', 'socialmedia'] as $key) {
            $this->assertInstanceOf(external_value::class, $body->keys[$key]);
            $this->assertSame(PARAM_TEXT, $body->keys[$key]->type, "Wrong type for body/{$key}.");
            $this->assertSame(VALUE_REQUIRED, $body->keys[$key]->required, "body/{$key} must be required.");
        }

        $cmid = $parameters->keys['cmid'];
        $this->assertInstanceOf(external_value::class, $cmid);
        $this->assertSame(PARAM_INT, $cmid->type);
        $this->assertSame(VALUE_REQUIRED, $cmid->required);

        // Certificate name, course, organization, social network and activity id.
        $this->assertCount(5, array_merge(array_keys($body->keys), ['cmid']));
    }

    /**
     * MDL-CTR-001: The return contract keeps the success and failure shapes consumed by the panel.
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
            'errorcode' => PARAM_ALPHANUMEXT,
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
     * Not automatable today: ai_helper::execute() instantiates
     * aiprovider_datacurso\httpclient\ai_services_api directly, and that constructor already
     * performs an HTTP request (is_for_ue() queries the token manager). There is no factory
     * method, constructor argument or setter to substitute a double, so the happy path cannot be
     * exercised without real network access.
     */
    public function test_successful_generation_returns_the_service_payload(): void {
        $this->markTestSkipped(
            'No seam to inject the AI HTTP client: ai_helper::execute() builds ai_services_api '
            . 'directly and its constructor performs a network call. A protected factory method '
            . 'in ai_helper is required before the success path can be tested.'
        );
    }
}
