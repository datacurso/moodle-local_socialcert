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
 * Tests for the external function that records a share in the platform logs.
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
use local_socialcert\event\certificate_shared;
use local_socialcert\external\log_share;
use mod_customcert\certificate;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the share logging external function.
 *
 * The share itself happens in the browser (the LinkedIn add-to-profile form opens in a new window),
 * so this function is the only server side moment of the action and therefore the only place where
 * it can be traced. It is a write function of its own instead of the state function, which is a read
 * function called on every return to the activity page and would record shares that never happened.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\log_share
 */
final class external_log_share_test extends \externallib_advanced_testcase {
    /** @var string Name of the external function under test. */
    private const FUNCTIONNAME = 'local_socialcert_log_share';

    /**
     * Build a course with a custom certificate activity and an enrolled student.
     *
     * @return \stdClass Object with course, customcert, cmid, modcontext and student.
     */
    private function create_certificate_fixture(): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
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
            log_share::execute($cmid);
        } catch (\moodle_exception $e) {
            return $e;
        }

        $this->fail('The request must be rejected instead of recording a share.');
    }

    /**
     * MDL-INT-014: A share of an issued and shareable credential is recorded once.
     */
    public function test_share_of_an_issued_credential_is_recorded(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        $issueid = certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $sink = $this->redirectEvents();
        $result = log_share::execute($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(['logged' => true], $result);
        $this->assertCount(1, $events);

        $event = reset($events);
        $this->assertInstanceOf(certificate_shared::class, $event);
        $this->assertSame((int) $fixture->student->id, (int) $event->userid);
        $this->assertSame((int) $fixture->course->id, (int) $event->courseid);
        $this->assertSame($fixture->modcontext->id, (int) $event->contextid);
        $this->assertSame($fixture->cmid, (int) $event->contextinstanceid);
        $this->assertSame($issue->code, $event->other['certid'], 'The event must name the shared credential.');
        $this->assertSame('linkedin', $event->other['network']);
    }

    /**
     * MDL-INT-014: The recorded share always belongs to the user in session.
     *
     * The function receives only the course module id, so the credential it records is the one of the
     * session user and never the one of a classmate.
     */
    public function test_recorded_share_belongs_to_the_session_user(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $fixture->course->id, 'student');

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);
        $otherissueid = certificate::issue_certificate($fixture->customcert->id, $other->id);
        $otherissue = $DB->get_record('customcert_issues', ['id' => $otherissueid], '*', MUST_EXIST);

        $this->setUser($other);

        $sink = $this->redirectEvents();
        log_share::execute($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();

        $event = reset($events);
        $this->assertSame((int) $other->id, (int) $event->userid);
        $this->assertSame($otherissue->code, $event->other['certid']);
    }

    /**
     * MDL-INT-014: Nothing is recorded while the certificate has not been issued.
     */
    public function test_share_without_an_issued_certificate_is_rejected(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        $sink = $this->redirectEvents();
        $rejection = $this->capture_rejection($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('nothingtoshare', $rejection->errorcode);
        $this->assertSame([], $events, 'A share that could not happen must leave no trace.');
    }

    /**
     * MDL-INT-014: Nothing is recorded while the organization ID is not configured.
     *
     * Without it the panel builds no share URL at all, so no credential could have been published.
     */
    public function test_share_without_the_organization_id_is_rejected(): void {
        $this->resetAfterTest();

        set_config('organizationid', '', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        $this->setUser($fixture->student);

        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $this->assertSame('nothingtoshare', $this->capture_rejection($fixture->cmid)->errorcode);
    }

    /**
     * MDL-INT-009: The function rejects calls made without an active session.
     */
    public function test_request_without_session_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        $this->setUser(null);

        $this->assertSame('requireloginerror', $this->capture_rejection($fixture->cmid)->errorcode);
    }

    /**
     * MDL-INT-009: The function rejects users with no access to the activity.
     */
    public function test_request_from_user_without_activity_access_is_rejected(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->assertSame('requireloginerror', $this->capture_rejection($fixture->cmid)->errorcode);
    }

    /**
     * MDL-INT-015: The function rejects a user without the capability of the share panel.
     *
     * A role that cannot see the panel cannot have shared anything through it, so the log must never
     * record a share on its behalf.
     */
    public function test_request_without_the_share_panel_capability_is_rejected(): void {
        $this->resetAfterTest();

        set_config('organizationid', '98765', 'local_socialcert');

        $fixture = $this->create_certificate_fixture();
        certificate::issue_certificate($fixture->customcert->id, $fixture->student->id);

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'socialcertsharedenied']);
        assign_capability('local/socialcert:viewsharepanel', CAP_PROHIBIT, $roleid, $fixture->modcontext->id, true);
        role_assign($roleid, $fixture->student->id, $fixture->modcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($fixture->student);

        // The activity itself is still visible, so the rejection comes from the capability.
        $this->assertTrue(has_capability('mod/customcert:view', $fixture->modcontext));

        $sink = $this->redirectEvents();
        $rejection = $this->capture_rejection($fixture->cmid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('nopermissions', $rejection->errorcode);
        $this->assertSame([], $events);
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
     * MDL-INT-009: A course module that is not a custom certificate is rejected.
     */
    public function test_request_is_rejected_for_a_module_that_is_not_a_custom_certificate(): void {
        $this->resetAfterTest();
        $fixture = $this->create_certificate_fixture();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $fixture->course->id]);
        $this->setUser($fixture->student);

        $this->assertSame('notacertificateactivity', $this->capture_rejection((int) $page->cmid)->errorcode);
    }

    /**
     * MDL-INT-009: A non numeric course module id throws the parameter validation exception.
     */
    public function test_non_numeric_course_module_id_throws_validation_exception(): void {
        $this->expectException(\invalid_parameter_exception::class);
        log_share::execute('not-an-id');
    }

    /**
     * MDL-CTR-001: The declared contract exposes the documented input and output.
     */
    public function test_parameter_and_return_contracts_declare_the_documented_shape(): void {
        $parameters = log_share::execute_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $parameters);
        $this->assertSame(['cmid'], array_keys($parameters->keys));
        $this->assertInstanceOf(external_value::class, $parameters->keys['cmid']);
        $this->assertSame(PARAM_INT, $parameters->keys['cmid']->type);
        $this->assertSame(VALUE_REQUIRED, $parameters->keys['cmid']->required);

        $returns = log_share::execute_returns();
        $this->assertInstanceOf(external_single_structure::class, $returns);
        $this->assertSame(['logged'], array_keys($returns->keys));
        $this->assertSame(PARAM_BOOL, $returns->keys['logged']->type);
    }

    /**
     * MDL-CTR-001: The function is registered as an ajax write function and requires a session.
     *
     * It is the only function of the plugin declared as a write one, because it is the only one that
     * leaves something behind: the event of the share.
     */
    public function test_function_is_registered_as_an_ajax_write_function(): void {
        global $DB;

        $function = $DB->get_record('external_functions', ['name' => self::FUNCTIONNAME]);
        $this->assertNotEmpty($function, 'The external function must be registered by the plugin.');
        $this->assertSame('local_socialcert', $function->component);
        $this->assertSame(log_share::class, $function->classname);
        $this->assertSame('execute', $function->methodname);

        $info = external_api::external_function_info(self::FUNCTIONNAME);
        $this->assertSame('write', $info->type, 'Recording the share writes a log entry.');
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
