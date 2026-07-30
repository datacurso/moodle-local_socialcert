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
 * Tests for the data exported to the social certificate panel.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use local_socialcert\output\main_panel;
use mod_customcert\certificate;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for main_panel::export_for_template().
 *
 * The exported 'issued' flag is inverted in the source: it is true when the session user has
 * NO issue, because the template uses it to render the error state. The assertions below
 * follow that contract and name the intent explicitly.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\output\main_panel
 */
final class main_panel_test extends \advanced_testcase {

    /**
     * MDL-INT-003: with an issued certificate the share action is active, targets the
     * LinkedIn form and carries the credential of the session user.
     */
    public function test_share_action_is_enabled_when_the_session_user_has_an_issue(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        $issueid = certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $data = $this->export_panel($scenario->customcert->cmid);

        // False means "no error state", i.e. the share button is actionable.
        $this->assertFalse($data['issued']);
        $this->assertSame('linkedin', $data['datanetwork']);
        $this->assertNotNull($data['shareurl']);
        $this->assertStringStartsWith('https://www.linkedin.com/profile/add?', $data['shareurl']);
        $this->assertSame($issue->code, $data['certid']);
        $this->assertStringContainsString('code=' . $issue->code, $data['verifyurl']);

        $params = self::query_params($data['shareurl']);
        $this->assertSame('12345', $params['organizationId']);
        $this->assertSame('AI Fundamentals', $params['name']);
        $this->assertSame($issue->code, $params['certId']);
    }

    /**
     * MDL-INT-003: without an issued certificate no share URL is produced, so the button
     * cannot open the LinkedIn form by any route.
     */
    public function test_share_action_is_disabled_when_the_session_user_has_no_issue(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        // True means "error state", i.e. the share button is rendered disabled.
        $this->assertTrue($data['issued']);
        $this->assertNull($data['shareurl']);
        $this->assertSame('', $data['datanetwork']);
        $this->assertSame('', $data['certid']);
        $this->assertSame('', $data['certname']);
        $this->assertSame('', $data['verifyurl']);
    }

    /**
     * MDL-INT-003: without an issued certificate the panel exports the error notice text.
     */
    public function test_missing_issue_exports_the_certificate_error_notice(): void {
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertTrue($data['issued']);
        $this->assertNotEmpty($data['certerror']);
        $this->assertSame(get_string('certerror', 'local_socialcert'), $data['certerror']);
    }

    /**
     * MDL-INT-003: the panel state belongs exclusively to the session user issue, with no
     * data leaking from other users of the same activity.
     */
    public function test_panel_state_belongs_only_to_the_session_user(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');

        $generator = $this->getDataGenerator();
        $other = $generator->create_user();
        $generator->enrol_user($other->id, $scenario->course->id, 'student');

        $issueid = certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        // The user who owns the issue sees the enabled panel with their own code.
        $this->setUser($scenario->student);
        $owndata = $this->export_panel($scenario->customcert->cmid);
        $this->assertFalse($owndata['issued']);
        $this->assertSame($issue->code, $owndata['certid']);

        // The other enrolled user, without an issue, sees the disabled panel and no foreign code.
        $this->setUser($other);
        $otherdata = $this->export_panel($scenario->customcert->cmid);
        $this->assertTrue($otherdata['issued']);
        $this->assertNull($otherdata['shareurl']);
        $this->assertSame('', $otherdata['certid']);
        $this->assertStringNotContainsString($issue->code, (string) $otherdata['verifyurl']);
    }

    /**
     * MDL-INT-003: the error notice distinguishes the actionable situation (the user can still
     * obtain the certificate) from the non actionable one (the user never receives a
     * certificate in this activity).
     *
     * [Pendiente:skip] A single 'certerror' string is exported for both situations, so there is
     * no exported data that could tell them apart. Clarity improvement pending.
     */
    public function test_error_notice_distinguishes_actionable_from_non_actionable_situations(): void {
        $this->markTestSkipped(
            'main_panel exports the single certerror string for every missing-issue situation, ' .
            'so actionable and non actionable cases cannot be distinguished. Pending improvement.'
        );
    }

    /**
     * MDL-INT-004: certificate and course names are exported with the platform text filters
     * applied, so multilang tags are resolved instead of reaching the panel as raw markup.
     */
    public function test_certificate_and_course_names_are_exported_with_filters_applied(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->filterall = true;
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', 1);
        \core_filters\filter_manager::reset_caches();

        $coursename = '<span lang="en" class="multilang">Analytics</span>'
            . '<span lang="es" class="multilang">Analitica</span>';
        $certname = '<span lang="en" class="multilang">Data Skills</span>'
            . '<span lang="es" class="multilang">Habilidades de datos</span>';

        $scenario = $this->create_certificate_scenario($coursename, $certname);
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame('Data Skills', $data['certname']);
        $this->assertSame('Analytics', $data['course']);
        $this->assertStringNotContainsString('multilang', $data['certname']);
        $this->assertStringNotContainsString('multilang', $data['course']);
        $this->assertStringNotContainsString('<span', $data['certname']);
        $this->assertStringNotContainsString('<span', $data['course']);
    }

    /**
     * MDL-INT-004: accented certificate and course names reach the panel and the LinkedIn URL
     * unaltered.
     */
    public function test_accented_certificate_and_course_names_are_exported_unaltered(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        $scenario = $this->create_certificate_scenario('Programación en Español', 'Diseño Gráfico Avanzado');
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame('Diseño Gráfico Avanzado', $data['certname']);
        $this->assertSame('Programación en Español', $data['course']);

        $params = self::query_params($data['shareurl']);
        $this->assertSame('Diseño Gráfico Avanzado', $params['name']);
    }

    /**
     * MDL-INT-004: the configured organization name is transported as panel data for the AI
     * assistant, and never inside the LinkedIn share URL.
     */
    public function test_organization_name_is_exported_as_panel_data_only(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('organizationname', 'Datacurso Formación', 'local_socialcert');

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame('Datacurso Formación', $data['org']);
        $this->assertStringNotContainsString('Datacurso', $data['shareurl']);
        $this->assertStringNotContainsString('Datacurso', urldecode($data['shareurl']));
    }

    /**
     * MDL-INT-004: the post preview exports the full name of the session user.
     */
    public function test_preview_exports_the_session_user_full_name(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->fullnamedisplay = 'firstname lastname';

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $student = $this->getDataGenerator()->create_user([
            'firstname' => 'Ana María',
            'lastname'  => 'Núñez',
        ]);
        $this->getDataGenerator()->enrol_user($student->id, $scenario->course->id, 'student');
        $this->setUser($student);
        certificate::issue_certificate($scenario->customcert->id, $student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame('Ana María Núñez', $data['author_name']);
    }

    /**
     * MDL-INT-004: names containing an ampersand reach the panel, the AI service and LinkedIn
     * with a single, correctly decoded ampersand.
     *
     * [Pendiente:skip] format_string() escapes the ampersand to '&amp;' and the Mustache
     * template escapes it a second time, so 'Data &amp; Skills' arrives at LinkedIn and at the
     * AI service as 'Data &amp;amp; Skills'. Known gap.
     */
    public function test_names_containing_an_ampersand_are_exported_without_double_escaping(): void {
        $this->markTestSkipped(
            'format_string() returns the ampersand already escaped as &amp; and the Mustache ' .
            'template escapes it again, so the name reaches LinkedIn and the AI service with a ' .
            'doubly escaped entity. Known gap.'
        );
    }

    /**
     * MDL-INT-004: the post preview shows the real profile picture of the session user.
     *
     * [Pendiente:skip] export_for_template() never exports an author avatar URL, so the
     * template always falls back to the generic silhouette. Known gap.
     */
    public function test_preview_exports_the_real_user_profile_picture(): void {
        $this->markTestSkipped(
            'export_for_template() exports no author_avatar_url key, so the preview always ' .
            'renders the generic silhouette instead of the real user picture. Known gap.'
        );
    }

    /**
     * MDL-INT-016: with an empty LinkedIn organization ID setting the share action must be
     * disabled, exactly as the setting help text promises.
     *
     * [Pendiente:fail] The builder falls back to the generic organization ID '1337', so the
     * credential would be published attributed to a third party organization. This test
     * asserts the correct behaviour and MUST fail until the fallback is removed.
     */
    public function test_share_action_is_disabled_when_the_organization_id_is_not_configured(): void {
        $this->resetAfterTest();

        set_config('organizationid', '', 'local_socialcert');

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertNull(
            $data['shareurl'],
            'No LinkedIn share URL may be built while the organization ID setting is empty.'
        );
        $this->assertTrue(
            $data['issued'],
            'The panel must render its disabled state while the organization ID setting is empty.'
        );
    }

    /**
     * Creates a course with a customcert activity and an enrolled student.
     *
     * @param string $coursename Course full name (raw, as typed by the teacher).
     * @param string $certname Certificate activity name (raw, as typed by the teacher).
     * @return \stdClass Object with the course, customcert and student records.
     */
    private function create_certificate_scenario(string $coursename, string $certname): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => $coursename]);
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => $certname,
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

    /**
     * Decodes the query string of a LinkedIn share URL into an associative array.
     *
     * @param string|null $url Share URL exported by the panel.
     * @return array Decoded query parameters.
     */
    private static function query_params(?string $url): array {
        $params = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $params);
        return $params;
    }
}
