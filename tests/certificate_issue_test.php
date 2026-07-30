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
 * Tests for the relation between the certificate issue and the panel availability.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use local_socialcert\output\main_panel;
use mod_customcert\certificate;
use mod_customcert\task\issue_certificates_task;
use mod_customcert\template;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the data layer of the issue and the resulting panel availability.
 *
 * The exported 'issued' flag is inverted in the source: it is true when the session user has
 * NO issue, because the template uses it to render the error state. The helper
 * assert_panel_is_operational() names that intent so the assertions stay readable.
 *
 * Coverage decisions for MDL-INT-005:
 * - Step 1 exercises the data layer of the download through the public API
 *   \mod_customcert\certificate::issue_certificate(), which is the single entry point used by
 *   the web download, the scheduled task and the mobile app. The browser download itself is a
 *   UI flow and belongs to the acceptance layer.
 * - Step 2 asserts that rendering the panel on the activity view never creates an issue.
 * - Step 3 forces \mod_customcert\task\issue_certificates_task. The task skips every
 *   certificate whose template has no elements, so the scenario is only reproducible after
 *   adding a page element to the template. The element row is inserted with the standard
 *   $DB API, exactly as the mod_customcert core tests do, because the module exposes no public
 *   API to add an element. The email delivery is deferred to an adhoc task
 *   (customcert/useadhoc) so the assertions stay on the issue and not on PDF generation.
 * - Step 4 covers the guards that are deterministic in a unit test: template without content
 *   and required time not met.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\output\main_panel
 */
final class certificate_issue_test extends \advanced_testcase {

    /**
     * MDL-INT-005: once the issue exists for the session user the panel becomes operational and
     * carries the code of that issue.
     */
    public function test_panel_becomes_operational_once_the_certificate_is_issued(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        $issueid = certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assert_panel_is_operational($data);
        $this->assertSame($issue->code, $data['certid']);
        $this->assertNotNull($data['shareurl']);
        $this->assertStringContainsString('code=' . $issue->code, $data['verifyurl']);
    }

    /**
     * MDL-INT-005: with no issue at all the panel stays in its non operational state.
     */
    public function test_panel_is_not_operational_while_no_issue_exists(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assert_panel_is_not_operational($data);
        $this->assertSame('', $data['certid']);
        $this->assertEmpty($DB->get_records('customcert_issues'));
    }

    /**
     * MDL-INT-005: rendering the panel on the activity view does not create an issue, so a plain
     * visit never makes the panel operational.
     */
    public function test_visiting_the_activity_view_does_not_create_an_issue(): void {
        global $DB;
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        // Two consecutive renders of the panel, as two consecutive visits would do.
        $first = $this->export_panel($scenario->customcert->cmid);
        $second = $this->export_panel($scenario->customcert->cmid);

        $this->assert_panel_is_not_operational($first);
        $this->assert_panel_is_not_operational($second);
        $this->assertEmpty(
            $DB->get_records('customcert_issues', ['customcertid' => $scenario->customcert->id]),
            'Rendering the share panel must never create a certificate issue.'
        );
    }

    /**
     * MDL-INT-005: the email options of the activity do not change the panel state by themselves.
     * Only the existence of the issue governs it.
     */
    public function test_panel_state_ignores_the_activity_email_settings(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario([
            'emailstudents' => 1,
            'emailteachers' => 1,
            'emailothers'   => 'quality@example.com',
        ]);
        $this->setUser($scenario->student);

        // Every email option is enabled, but while the issue does not exist the panel is off.
        $before = $this->export_panel($scenario->customcert->cmid);
        $this->assert_panel_is_not_operational($before);

        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $after = $this->export_panel($scenario->customcert->cmid);
        $this->assert_panel_is_operational($after);
    }

    /**
     * MDL-INT-005: with an email option enabled the scheduled task issues the certificate ahead
     * of any download, and the panel is operational on the first visit of the student.
     */
    public function test_scheduled_task_issues_the_certificate_and_the_panel_is_operational(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('certificateexecutionperiod', 0, 'customcert');
        set_config('useadhoc', 1, 'customcert');

        $scenario = $this->create_certificate_scenario(['emailstudents' => 1]);
        $this->add_content_to_certificate_template($scenario->customcert);

        $this->assertEmpty($DB->get_records('customcert_issues'));

        (new issue_certificates_task())->execute();

        $issue = $DB->get_record('customcert_issues', [
            'customcertid' => $scenario->customcert->id,
            'userid'       => $scenario->student->id,
        ], '*', MUST_EXIST);

        // First visit of the student after the task ran.
        $this->setUser($scenario->student);
        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assert_panel_is_operational($data);
        $this->assertSame($issue->code, $data['certid']);
    }

    /**
     * MDL-INT-005: the early issue respects the template content condition, so a certificate
     * without elements is skipped and the panel stays off.
     */
    public function test_scheduled_task_skips_certificates_whose_template_has_no_content(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('certificateexecutionperiod', 0, 'customcert');
        set_config('useadhoc', 1, 'customcert');

        $scenario = $this->create_certificate_scenario(['emailstudents' => 1]);

        (new issue_certificates_task())->execute();

        $this->assertEmpty(
            $DB->get_records('customcert_issues', ['customcertid' => $scenario->customcert->id]),
            'A certificate whose template has no elements must not be issued by the task.'
        );

        $this->setUser($scenario->student);
        $this->assert_panel_is_not_operational($this->export_panel($scenario->customcert->cmid));
    }

    /**
     * MDL-INT-005: the early issue respects the required time of the activity, so a student who
     * has not spent that time in the course is skipped and the panel stays off.
     */
    public function test_scheduled_task_skips_users_who_have_not_met_the_required_time(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('certificateexecutionperiod', 0, 'customcert');
        set_config('useadhoc', 1, 'customcert');

        $scenario = $this->create_certificate_scenario([
            'emailstudents' => 1,
            'requiredtime'  => 600,
        ]);
        $this->add_content_to_certificate_template($scenario->customcert);

        (new issue_certificates_task())->execute();

        $this->assertEmpty(
            $DB->get_records('customcert_issues', ['customcertid' => $scenario->customcert->id]),
            'A student below the required time must not receive an early issue.'
        );

        $this->setUser($scenario->student);
        $this->assert_panel_is_not_operational($this->export_panel($scenario->customcert->cmid));
    }

    /**
     * MDL-INT-005: an issue that belongs to another student never makes the panel operational for
     * the session user.
     */
    public function test_another_user_issue_does_not_make_the_panel_operational(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario();

        $generator = $this->getDataGenerator();
        $other = $generator->create_user();
        $generator->enrol_user($other->id, $scenario->course->id, 'student');

        $issueid = certificate::issue_certificate($scenario->customcert->id, $other->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $this->setUser($scenario->student);
        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assert_panel_is_not_operational($data);
        $this->assertSame('', $data['certid']);
        $this->assertStringNotContainsString($issue->code, (string) $data['verifyurl']);
        $this->assertStringNotContainsString($issue->code, (string) $data['shareurl']);
    }

    /**
     * Asserts that the panel is in its operational state for the session user.
     *
     * @param array $data Exported template data.
     */
    private function assert_panel_is_operational(array $data): void {
        // False means "no error state", i.e. the share button is actionable.
        $this->assertFalse($data['issued'], 'The panel must be operational once the issue exists.');
        $this->assertSame('linkedin', $data['datanetwork']);
    }

    /**
     * Asserts that the panel is in its non operational state for the session user.
     *
     * @param array $data Exported template data.
     */
    private function assert_panel_is_not_operational(array $data): void {
        // True means "error state", i.e. the share button is rendered disabled.
        $this->assertTrue($data['issued'], 'The panel must not be operational while there is no issue.');
        $this->assertNull($data['shareurl']);
        $this->assertSame('', $data['datanetwork']);
    }

    /**
     * Creates a visible course with a customcert activity and an enrolled student.
     *
     * @param array $options Extra customcert instance settings.
     * @return \stdClass Object with the course, customcert and student records.
     */
    private function create_certificate_scenario(array $options = []): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', $options + [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        return (object) [
            'course'     => $course,
            'customcert' => $customcert,
            'student'    => $student,
        ];
    }

    /**
     * Adds one element to the template of a customcert activity.
     *
     * The scheduled task skips certificates whose template has no elements, so the template needs
     * real content before the early issue can happen. mod_customcert exposes no public API to add
     * an element, so the row is inserted with the standard $DB API, as its own core tests do.
     *
     * @param \stdClass $customcert Customcert instance record returned by the generator.
     */
    private function add_content_to_certificate_template(\stdClass $customcert): void {
        global $DB;

        $pageid = $DB->get_field('customcert_pages', 'id', ['templateid' => $customcert->templateid], IGNORE_MULTIPLE);

        if (!$pageid) {
            $record = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
            $pageid = (new template($record))->add_page(false);
        }

        $DB->insert_record('customcert_elements', (object) [
            'pageid' => $pageid,
            'name'   => 'Test element',
        ]);
    }

    /**
     * Exports the panel data for a course module using a real core renderer.
     *
     * @param int $cmid Course module ID of the customcert activity.
     * @return array Exported template data.
     */
    private function export_panel(int $cmid): array {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            $PAGE->set_url('/mod/customcert/view.php', ['id' => $cmid]);
        }

        $panel = new main_panel($cmid);

        return $panel->export_for_template($PAGE->get_renderer('core'));
    }
}
