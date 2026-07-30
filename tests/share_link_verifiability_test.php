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
 * Tests for the public verifiability of the published credential link.
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
 * Tests that the credential link published on LinkedIn is verifiable by third parties.
 *
 * The panel always exports the site level verification URL of mod_customcert
 * (/mod/customcert/verify_certificate.php?code=CODE). That page only answers an anonymous
 * visitor when the global setting customcert/verifyallcertificates is enabled and the activity
 * setting verifyany is enabled; otherwise the visitor gets the "cannot verify" notice.
 *
 * The exported 'issued' flag is inverted in the source: it is true when the session user has
 * NO issue, because the template uses it to render the error state.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\output\main_panel
 */
final class share_link_verifiability_test extends \advanced_testcase {

    /**
     * MDL-E2E-003: with the platform default configuration the credential link is not verifiable
     * by an anonymous visitor, so the panel must not publish it, or must export a warning that
     * the current configuration does not allow public verification.
     *
     * [Pendiente:fail] The panel builds and publishes the share URL without looking at the
     * verification configuration, and exports no warning of any kind. This test asserts the
     * correct behaviour and MUST fail until the plugin validates that configuration.
     */
    public function test_share_link_is_withheld_or_flagged_when_public_verification_is_disabled(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        // Platform default configuration: no site wide verification, no open verification.
        set_config('verifyallcertificates', 0, 'customcert');
        $scenario = $this->create_certificate_scenario(['verifyany' => 0]);
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $publishesunverifiablelink = $data['shareurl'] !== null
            && $data['verifyurl'] !== ''
            && str_contains(urldecode((string) $data['shareurl']), (string) $data['verifyurl']);

        $this->assertFalse(
            $publishesunverifiablelink && !self::warns_about_public_verification($data),
            'With public verification disabled the panel publishes a certUrl that an anonymous '
                . 'visitor cannot verify, and exports no warning about it. The credential reaches '
                . 'LinkedIn without effective verification.'
        );
    }

    /**
     * MDL-E2E-003: with the platform default configuration an anonymous visitor really has no way
     * of verifying a credential through the site level verification page.
     */
    public function test_anonymous_visitor_cannot_verify_at_site_level_by_default(): void {
        $this->resetAfterTest();

        set_config('verifyallcertificates', 0, 'customcert');
        $scenario = $this->create_certificate_scenario(['verifyany' => 0]);

        $this->setGuestUser();

        $this->assertEmpty(
            get_config('customcert', 'verifyallcertificates'),
            'The default configuration keeps the site wide verification disabled.'
        );
        $this->assertFalse(
            has_capability('mod/customcert:verifyallcertificates', \context_system::instance()),
            'A visitor without a real account cannot verify all certificates.'
        );
        $this->assertSame(
            0,
            (int) $scenario->customcert->verifyany,
            'The default configuration keeps the open verification of the activity disabled.'
        );
    }

    /**
     * MDL-E2E-003: with both verification settings enabled the published link points to the
     * verification page carrying the code of the issue, and the LinkedIn certUrl parameter is
     * exactly that link.
     */
    public function test_share_link_points_to_the_verification_page_when_public_verification_is_enabled(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('verifyallcertificates', 1, 'customcert');

        $scenario = $this->create_certificate_scenario(['verifyany' => 1]);
        $this->setUser($scenario->student);

        $issueid = certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertFalse($data['issued']);
        $this->assertStringContainsString('/mod/customcert/verify_certificate.php', $data['verifyurl']);
        $this->assertStringContainsString('code=' . $issue->code, $data['verifyurl']);

        $params = self::query_params($data['shareurl']);
        $this->assertSame($data['verifyurl'], $params['certUrl']);
        $this->assertSame($issue->code, $params['certId']);
    }

    /**
     * Tells whether the exported panel data carries a warning about the verification setup.
     *
     * The plugin exports no such key today. Any of these names would be an acceptable
     * implementation of the warning required by the case.
     *
     * @param array $data Exported template data.
     * @return bool True when a verification warning is exported.
     */
    private static function warns_about_public_verification(array $data): bool {
        $candidates = [
            'verifywarning',
            'verificationwarning',
            'notverifiable',
            'shareblocked',
            'verifydisabled',
        ];

        foreach ($candidates as $key) {
            if (!empty($data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a course with a customcert activity and an enrolled student.
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
