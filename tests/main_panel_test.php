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
     *
     * The notice used to be the generic 'certerror' string. Since the panel is only injected for
     * users who can receive the certificate (MDL-INT-007), a missing issue is always actionable, so
     * the exported notice is now the 'certerrordownload' string, which names that action.
     */
    public function test_missing_issue_exports_the_certificate_error_notice(): void {
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertTrue($data['issued']);
        $this->assertNotEmpty($data['certerror']);
        $this->assertSame(get_string('certerrordownload', 'local_socialcert'), $data['certerror']);
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
     * Previously skipped because a single generic 'certerror' string was exported for every
     * situation. The three situations of the case are told apart now:
     *
     * - Actionable: the user can obtain the certificate and has to download it, which the notice
     *   states explicitly.
     * - Foreign to the user: the certificate is already issued and what is missing is the LinkedIn
     *   organization ID of the site, which only the administrator can configure.
     * - Non actionable: a user who cannot receive the certificate never gets the panel injected at
     *   all (MDL-INT-007), so no notice is rendered for them.
     */
    public function test_error_notice_distinguishes_actionable_from_non_actionable_situations(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        // The actionable situation names the action, instead of only stating that the certificate is
        // missing.
        $data = $this->export_panel($scenario->customcert->cmid);
        $this->assertSame(get_string('certerrordownload', 'local_socialcert'), $data['certerror']);
        $this->assertNotSame(get_string('certerror', 'local_socialcert'), $data['certerror']);
        $this->assertStringContainsStringIgnoringCase('download', $data['certerror']);

        // With the certificate already issued and the organization ID missing the notice points at
        // the site configuration instead of asking again for a certificate that already exists.
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);
        set_config('organizationid', '', 'local_socialcert');
        $data = $this->export_panel($scenario->customcert->cmid);
        $this->assertTrue($data['issued'], 'The share action stays disabled without the organization ID.');
        $this->assertSame(get_string('certerrornoorg', 'local_socialcert'), $data['certerror']);

        // And the non actionable situation is out of reach: a user who cannot receive the
        // certificate never gets the panel injected, so no notice is rendered for them at all.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $scenario->course->id, 'editingteacher');
        $context = \context_module::instance($scenario->customcert->cmid);
        $this->assertFalse(
            has_capability('mod/customcert:receiveissue', $context, $teacher),
            'A teacher cannot receive the certificate, so the panel is never rendered for them.'
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
     * Previously skipped: format_string() escaped the ampersand into '&amp;' and the Mustache
     * template escaped it again, so 'Data & Skills' arrived at LinkedIn and at the AI service as
     * 'Data &amp;amp; Skills'. The panel now asks the filters not to escape and decodes whatever
     * entity the text cleaning leaves behind, so the transported value is the readable text and the
     * escaping happens once, in the template that renders it.
     *
     * Both values of $CFG->formatstringstriptags are covered because they take different code paths
     * inside format_string(): with the tags stripped nothing is cleaned afterwards, while otherwise
     * clean_text() escapes the ampersand again on its own.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of the formatstringstriptags site setting.
     */
    public function test_names_containing_an_ampersand_are_exported_without_double_escaping(int $striptags): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->formatstringstriptags = $striptags;
        set_config('organizationid', '12345', 'local_socialcert');

        $scenario = $this->create_certificate_scenario('Research & Development', 'Data & Skills');
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        // The data transported to the AI service carries the readable name.
        $this->assertSame('Data & Skills', $data['certname']);
        $this->assertSame('Research & Development', $data['course']);
        $this->assertStringNotContainsString('&amp;', $data['certname']);
        $this->assertStringNotContainsString('&amp;', $data['course']);

        // And so does the name that travels inside the LinkedIn URL.
        $params = self::query_params($data['shareurl']);
        $this->assertSame('Data & Skills', $params['name']);
        $this->assertStringNotContainsString('amp%3B', $data['shareurl']);
    }

    /**
     * Values of the formatstringstriptags setting that take different paths inside format_string().
     *
     * @return array[]
     */
    public static function striptags_provider(): array {
        return [
            'names stripped of tags' => [1],
            'names cleaned as html' => [0],
        ];
    }

    /**
     * MDL-INT-004: the post preview shows the real profile picture of the session user.
     *
     * Previously skipped: export_for_template() exported no author avatar URL, so the preview
     * always fell back to the generic silhouette. The panel now exports the URL built by the public
     * user picture API of Moodle whenever the user really has a picture.
     */
    public function test_preview_exports_the_real_user_profile_picture(): void {
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $student = $this->getDataGenerator()->create_user(['picture' => 1]);
        $this->getDataGenerator()->enrol_user($student->id, $scenario->course->id, 'student');
        $this->setUser($student);
        certificate::issue_certificate($scenario->customcert->id, $student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertNotEmpty($data['author_avatar_url'], 'The preview must show the real picture of the user.');
        $this->assertStringContainsString('/user/icon/', $data['author_avatar_url']);
        $this->assertStringContainsString('rev=1', $data['author_avatar_url']);
    }

    /**
     * MDL-INT-004: the generic silhouette is only a fallback, used when the user has no picture.
     */
    public function test_preview_exports_no_avatar_url_when_the_user_has_no_picture(): void {
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame(
            '',
            $data['author_avatar_url'],
            'Without a picture the panel exports no URL, so the template draws the silhouette.'
        );
    }

    /**
     * MDL-INT-016: with an empty LinkedIn organization ID setting the share action must be
     * disabled, exactly as the setting help text promises.
     *
     * The builder used to fall back to the generic organization ID '1337', so the credential was
     * published attributed to a third party organization. The fallback is gone: without the
     * setting there is no URL and the panel renders its disabled state.
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
        $this->assertSame(
            get_string('certerrornoorg', 'local_socialcert'),
            $data['certerror'],
            'With the certificate issued the notice must point at the missing site configuration.'
        );
    }

    /**
     * MDL-E2E-006: the panel exports the accessible names of the assistant controls.
     *
     * The template used the aibuttonlabel and copytextlabel variables the renderable never
     * exported, so the assistant button had no accessible name and the copy button had an empty
     * one, and it carried three labels hardcoded in Spanish. All of them are language strings now.
     */
    public function test_panel_exports_the_accessible_names_of_the_assistant_controls(): void {
        $this->resetAfterTest();

        $scenario = $this->create_certificate_scenario('Machine Learning 101', 'AI Fundamentals');
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        // The label of the assistant button is the same string amd/src/actions.js restores after a
        // generation, so the accessible name of the control is stable.
        $this->assertSame(get_string('airesponsebtn', 'local_socialcert'), $data['aibuttonlabel']);
        $this->assertSame(get_string('copytextlabel', 'local_socialcert'), $data['copytextlabel']);
        $this->assertSame(get_string('ailogoalt', 'local_socialcert'), $data['ailogoalt']);
        $this->assertSame(get_string('airegionlabel', 'local_socialcert'), $data['airegionlabel']);
        $this->assertSame(
            get_string('avatarlabel', 'local_socialcert', $data['author_name']),
            $data['avatarlabel']
        );

        foreach (['aibuttonlabel', 'copytextlabel', 'ailogoalt', 'airegionlabel', 'avatarlabel'] as $key) {
            $this->assertNotEmpty($data[$key], "The '{$key}' accessible name must not be empty.");
        }
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
