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

/**
 * Tests for the external function that reports the state of the share panel.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_socialcert\external\share_state;
use mod_customcert\certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the share panel state external function.
 *
 * The function is the entry point the panel uses to enable itself once mod_customcert has issued
 * the certificate, so both the access rules and the reported state are exercised through it.
 * Rejections are plain exceptions here (unlike ai_helper, which converts them into a payload):
 * this function spends no credits and contacts no external service, so a failure just leaves the
 * panel in the disabled state the server already rendered.
 *
 * No process isolation is needed because the class under test uses the core_external namespace
 * instead of a file level require_once of lib/externallib.php.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\share_state
 */
final class external_share_state_test extends \externallib_advanced_testcase {

    /** @var string Name of the external function under test. */
    private const FUNCTIONNAME = 'local_socialcert_get_share_state';

    /**
     * Build a course with a custom certificate activity and an enrolled student.
     *
     * @param string $certname Name of the certificate activity.
     * @return \stdClass Object with course, customcert, cmid, modcontext and student.
     */
    private function create_certificate_fixture(string $certname = 'AI Fundamentals'): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => $certname,
        ]);
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
     * Execute the function expecting a rejection and return the exception it raised.
     *
     * @param int $cmid Course module ID sent in the request.
     * @return \moodle_exception The rejection raised by the function.
     */
    private function capture_rejection(int $cmid): \moodle_exception {
        try {
            share_state::execute($cmid);
        } catch (\moodle_exception $e) {
            return $e;
        }

        $this->fail('The request must be rejected instead of reporting a panel state.');
    }

    /**
     * MDL-INT-009: The function rejects calls made without an active session.
     */
    public function test_request_without_session_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        $this->setUser(null);

        $rejection = $this->capture_rejection($fixture->cmid);
        $this->assertSame('requireloginerror', $rejection->errorcode);
    }

    /**
     * MDL-INT-009: The function rejects users with no access to the activity.
     */
    public function test_request_from_user_without_activity_access_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        // A confirmed user who is not enrolled and holds no elevated permission.
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $rejection = $this->capture_rejection($fixture->cmid);
        $this->assertSame('requireloginerror', $rejection->errorcode);
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

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'socialcertdenied']);
        assign_capability('mod/customcert:view', CAP_PROHIBIT, $roleid, $fixture->modcontext->id, true);
        role_assign($roleid, $fixture->student->id, $fixture->modcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($fixture->student);

        $rejection = $this->capture_rejection($fixture->cmid);
        $this->assertContains(
            $rejection->errorcode,
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
        $this->setUser($fixture->student);

        $this->assertSame('invalidcmid', $this->capture_rejection(0)->errorcode);
        $this->assertSame('invalidcmid', $this->capture_rejection(-1)->errorcode);

        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_modules}') + 1000;
        $this->assertInstanceOf(\moodle_exception::class, $this->capture_rejection($missingid));
    }

    /**
     * MDL-INT-009: A non numeric course module id throws the parameter validation exception.
     */
    public function test_non_numeric_course_module_id_throws_validation_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        share_state::execute('not-an-id');
    }

    /**
     * MDL-INT-009: A course module that is not a custom certificate is rejected.
     *
     * Every enrolled student holds mod/customcert:view in any module context of the course, so the
     * capability alone would accept any course module id: the module type is revalidated too.
     */
    public function test_request_is_rejected_for_a_module_that_is_not_a_custom_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $fixture->course->id]);
        $this->setUser($fixture->student);

        $rejection = $this->capture_rejection((int) $page->cmid);
        $this->assertSame('notacertificateactivity', $rejection->errorcode);
    }

    /**
     * MDL-E2E-002: Before the certificate is obtained the state keeps the panel disabled.
     */
    public function test_state_reports_no_issue_before_the_certificate_is_obtained(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        $state = share_state::execute($fixture->cmid);

        $this->assertFalse($state['hasissue']);
        $this->assertSame('', $state['shareurl'], 'No share URL may exist without an issued certificate.');
        $this->assertSame('', $state['network']);
        $this->assertSame('', $state['verifywarning'], 'There is nothing to warn about while nothing can be shared.');
        $this->assertFalse($state['enableai'], 'The assistant demands an issued certificate.');
        $this->assertArrayNotHasKey(
            'aicard',
            $state,
            'Without an issued certificate the browser must receive no context to render the assistant card with.'
        );
    }

    /**
     * MDL-E2E-002: Once mod_customcert records the issue the state carries the share URL.
     *
     * This is the state the panel reads when the user comes back from the certificate PDF, so it
     * can enable the button without a manual reload.
     */
    public function test_state_reports_the_share_url_once_the_certificate_is_issued(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        $issueid = certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $state = share_state::execute($fixture->cmid);

        $this->assertTrue($state['hasissue']);
        $this->assertStringStartsWith('https://www.linkedin.com/profile/add?', $state['shareurl']);
        $this->assertStringContainsString('organizationId=98765', $state['shareurl']);
        $this->assertStringContainsString('certId=' . $issue->code, $state['shareurl']);
        $this->assertStringContainsString('name=AI%20Fundamentals', $state['shareurl']);
        $this->assertSame('linkedin', $state['network'], 'The panel needs the network to apply the LinkedIn palette.');
        $this->assertTrue($state['enableai']);

        // The test site keeps the default verification settings, under which a third party cannot
        // verify the published link, so the panel enabled in the browser must carry the warning.
        $this->assertSame(get_string('verifywarning', 'local_socialcert'), $state['verifywarning']);
    }

    /**
     * MDL-E2E-002: With an issue but no organization ID configured the panel stays disabled.
     *
     * The credential may never be published attributed to a foreign organization, so there is no
     * share URL to enable the button with, even though the certificate exists.
     */
    public function test_state_stays_disabled_when_the_organization_id_is_not_configured(): void {
        $this->resetAfterTest();

        set_config('organizationid', '', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $state = share_state::execute($fixture->cmid);

        $this->assertTrue($state['hasissue'], 'The issue exists, so it must be reported as such.');
        $this->assertSame('', $state['shareurl'], 'No share URL may be built without the organization ID.');
        $this->assertSame('', $state['network'], 'Without a share URL the panel must keep its disabled palette.');
        $this->assertTrue($state['enableai'], 'The assistant only depends on the issue and the global setting.');

        // The assistant is available, so its card travels even though the share action stays
        // disabled, and it carries no share URL because there is none to publish.
        $this->assertArrayHasKey('aicard', $state);
        $this->assertSame('', $state['aicard']['shareurl']);
        $this->assertSame('AI Fundamentals', $state['aicard']['certname']);
    }

    /**
     * MDL-E2E-002: The reported state belongs exclusively to the user in session.
     */
    public function test_state_belongs_only_to_the_session_user(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $fixture->course->id, 'student');

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $this->setUser($fixture->student);
        $ownstate = share_state::execute($fixture->cmid);
        $this->assertTrue($ownstate['hasissue']);

        $this->setUser($other);
        $otherstate = share_state::execute($fixture->cmid);
        $this->assertFalse($otherstate['hasissue'], 'The issue of another user must never enable the panel.');
        $this->assertSame('', $otherstate['shareurl']);
    }

    /**
     * MDL-E2E-002: The assistant availability follows the global setting.
     */
    public function test_state_reports_the_assistant_as_unavailable_when_the_ai_is_disabled(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('enableai', 0, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $state = share_state::execute($fixture->cmid);

        $this->assertTrue($state['hasissue']);
        $this->assertNotSame('', $state['shareurl']);
        $this->assertFalse($state['enableai']);
        $this->assertArrayNotHasKey(
            'aicard',
            $state,
            'With the AI disabled globally no assistant context may reach the browser.'
        );
    }

    /**
     * MDL-E2E-002: With the certificate issued the state carries the context of the assistant card.
     *
     * The card is a template of its own precisely so the browser can add it without a manual
     * reload, and the context returned here is the one that template is rendered with. The very
     * same subcontext is exported by main_panel for the server side rendering, so the card added
     * in the browser and the card rendered by the server are built from identical data.
     */
    public function test_state_reports_the_assistant_card_context_once_the_certificate_is_issued(): void {
        global $CFG;

        $this->resetAfterTest();

        $CFG->fullnamedisplay = 'firstname lastname';

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('organizationname', 'Buen Data', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $student = $this->getDataGenerator()->create_user([
            'firstname' => 'Ana',
            'lastname'  => 'Nunez',
        ]);
        $this->getDataGenerator()->enrol_user($student->id, $fixture->course->id, 'student');
        $this->setUser($student);

        certificate::issue_certificate($fixture->customcert->id, $student->id);

        $state = share_state::execute($fixture->cmid);

        $this->assertTrue($state['enableai']);
        $this->assertArrayHasKey('aicard', $state, 'The available assistant must report the context of its card.');

        $aicard = $state['aicard'];

        // The identifiers and the data attributes the assistant button needs to request a
        // generation, which is what the card would be useless without.
        $this->assertSame('btn-ai', $aicard['aibuttonid']);
        $this->assertSame('ai-response', $aicard['responseid']);
        $this->assertSame('LinkedIn', $aicard['socialmedia']);
        $this->assertSame($fixture->cmid, $aicard['cmid']);
        $this->assertSame('AI Fundamentals', $aicard['certname']);
        $this->assertSame('Machine Learning 101', $aicard['course']);
        $this->assertSame('Buen Data', $aicard['org']);
        $this->assertStringStartsWith('https://www.linkedin.com/profile/add?', $aicard['shareurl']);

        // The preview and the assets of the card.
        $this->assertSame('Ana Nunez', $aicard['author_name']);
        $this->assertStringEndsWith('/local/socialcert/assets/logo_title.png', $aicard['imageurl']);
        $this->assertStringEndsWith('/local/socialcert/assets/logo.png', $aicard['imagelogo']);

        // The texts of the card, taken from the language pack and never rebuilt in the browser.
        $this->assertSame(get_string('ai_actioncall', 'local_socialcert'), $aicard['ai_actioncall']);
        $this->assertSame(get_string('errorcredits', 'local_socialcert'), $aicard['errorcredits']);
        $this->assertSame(get_string('errorgeneric', 'local_socialcert'), $aicard['errorgeneric']);
        $this->assertSame(get_string('errorlicense', 'local_socialcert'), $aicard['errorlicense']);
    }

    /**
     * MDL-E2E-002: The assistant card context reaches the browser intact through the contract.
     *
     * The credits and licence messages carry a link to the Datacurso shop, so they are declared
     * raw: a cleaning that stripped the anchor would leave the student without the link to solve
     * the problem the message is reporting.
     */
    public function test_assistant_card_context_survives_the_return_contract(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture('Diseño Gráfico Avanzado');
        $this->setUser($fixture->student);

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $state = share_state::execute($fixture->cmid);
        $cleaned = external_api::clean_returnvalue(share_state::execute_returns(), $state);

        $this->assertArrayHasKey('aicard', $cleaned);
        $this->assertSame('Diseño Gráfico Avanzado', $cleaned['aicard']['certname']);
        $this->assertSame($state['aicard']['shareurl'], $cleaned['aicard']['shareurl']);
        $this->assertStringContainsString('<a href=', $cleaned['aicard']['errorcredits']);
        $this->assertStringContainsString('<a href=', $cleaned['aicard']['errorlicense']);
    }

    /**
     * MDL-E2E-002: An unavailable assistant produces no card context, not even an empty one.
     *
     * The absence of the key is what keeps the card out of the page, so the optional structure has
     * to survive the cleaning of the contract while missing.
     */
    public function test_absent_assistant_card_context_survives_the_return_contract(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        $cleaned = external_api::clean_returnvalue(share_state::execute_returns(), share_state::execute($fixture->cmid));

        $this->assertFalse($cleaned['enableai']);
        $this->assertArrayNotHasKey('aicard', $cleaned, 'No card context may be invented for an unavailable assistant.');
    }

    /**
     * MDL-CTR-001: The declared contract exposes the documented input and output.
     */
    public function test_parameter_and_return_contracts_declare_the_documented_shape(): void {
        $parameters = share_state::execute_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $parameters);
        $this->assertSame(['cmid'], array_keys($parameters->keys));
        $this->assertInstanceOf(external_value::class, $parameters->keys['cmid']);
        $this->assertSame(PARAM_INT, $parameters->keys['cmid']->type);
        $this->assertSame(VALUE_REQUIRED, $parameters->keys['cmid']->required);

        $returns = share_state::execute_returns();
        $this->assertInstanceOf(external_single_structure::class, $returns);
        $this->assertEqualsCanonicalizing(
            ['hasissue', 'shareurl', 'network', 'verifywarning', 'enableai', 'aicard'],
            array_keys($returns->keys)
        );

        $expectedtypes = [
            'hasissue' => PARAM_BOOL,
            'shareurl' => PARAM_URL,
            'network'  => PARAM_ALPHANUMEXT,
            'verifywarning' => PARAM_TEXT,
            'enableai' => PARAM_BOOL,
        ];
        foreach ($expectedtypes as $key => $type) {
            $this->assertInstanceOf(external_value::class, $returns->keys[$key]);
            $this->assertSame($type, $returns->keys[$key]->type, "Wrong type for {$key}.");
            $this->assertSame(VALUE_REQUIRED, $returns->keys[$key]->required, "{$key} must always be reported.");
        }
    }

    /**
     * MDL-CTR-001: The assistant card context is an optional structure with the documented shape.
     *
     * It has to be optional because the assistant is not always available, and its absence is what
     * keeps the card out of the page for a user without an issued certificate.
     */
    public function test_return_contract_declares_the_assistant_card_as_an_optional_structure(): void {
        $aicard = share_state::execute_returns()->keys['aicard'];

        $this->assertInstanceOf(external_single_structure::class, $aicard);
        $this->assertSame(VALUE_OPTIONAL, $aicard->required, 'The card context must be absent while it cannot be used.');

        $expectedtypes = [
            'aibuttonid' => PARAM_ALPHANUMEXT,
            'responseid' => PARAM_ALPHANUMEXT,
            'socialmedia' => PARAM_ALPHANUMEXT,
            'cmid' => PARAM_INT,
            'author_name' => PARAM_TEXT,
            'certname' => PARAM_TEXT,
            'course' => PARAM_TEXT,
            'org' => PARAM_TEXT,
            'shareurl' => PARAM_URL,
            'imageurl' => PARAM_URL,
            'imagelogo' => PARAM_URL,
            'ai_actioncall' => PARAM_TEXT,
            // Raw on purpose: these plugin owned messages carry the link to the Datacurso shop.
            'errorcredits' => PARAM_RAW,
            'errorgeneric' => PARAM_RAW,
            'errorlicense' => PARAM_RAW,
        ];
        $this->assertEqualsCanonicalizing(array_keys($expectedtypes), array_keys($aicard->keys));

        foreach ($expectedtypes as $key => $type) {
            $this->assertInstanceOf(external_value::class, $aicard->keys[$key]);
            $this->assertSame($type, $aicard->keys[$key]->type, "Wrong type for aicard {$key}.");
        }
    }

    /**
     * MDL-CTR-001: An accented certificate name survives the cleaning of the declared contract.
     *
     * The share URL is declared as PARAM_URL, and a value the URL syntax validator rejected would
     * be emptied silently, leaving the panel disabled with a certificate already issued.
     */
    public function test_share_url_of_an_accented_certificate_survives_the_return_contract(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture('Diseño Gráfico Avanzado');
        $this->setUser($fixture->student);

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $state = share_state::execute($fixture->cmid);
        $cleaned = external_api::clean_returnvalue(share_state::execute_returns(), $state);

        $this->assertSame($state['shareurl'], $cleaned['shareurl'], 'The share URL must reach the panel intact.');
        $this->assertStringContainsString('Dise%C3%B1o%20Gr%C3%A1fico%20Avanzado', $cleaned['shareurl']);
        $this->assertTrue($cleaned['hasissue']);
        $this->assertSame('linkedin', $cleaned['network']);
    }

    /**
     * MDL-CTR-001: The function is registered as an ajax read function and requires a session.
     */
    public function test_function_is_registered_as_an_ajax_read_function(): void {
        global $DB;

        $function = $DB->get_record('external_functions', ['name' => self::FUNCTIONNAME]);
        $this->assertNotEmpty($function, 'The external function must be registered by the plugin.');
        $this->assertSame('local_socialcert', $function->component);
        $this->assertSame(share_state::class, $function->classname);
        $this->assertSame('execute', $function->methodname);

        $info = external_api::external_function_info(self::FUNCTIONNAME);
        $this->assertSame('read', $info->type, 'Reporting the panel state must never write anything.');
        $this->assertTrue((bool) $info->allowed_from_ajax, 'The panel calls the function through core/ajax.');
        $this->assertTrue((bool) $info->loginrequired, 'The function must require an active session.');
    }

    /**
     * MDL-CTR-001: The function is not published in any external service, including the mobile one.
     */
    public function test_function_is_not_published_in_any_external_service(): void {
        global $DB;

        $function = $DB->get_record('external_functions', ['name' => self::FUNCTIONNAME]);
        $this->assertNotEmpty($function);
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
}
