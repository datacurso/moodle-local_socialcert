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
 * Tests for the availability of the AI assistant depending on the certificate issue.
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
 * Tests that the AI assistant demands an issued certificate, just like the share button.
 *
 * The panel exports 'enableai' as the single flag that enables the AI card, and 'certname' as
 * the certificate name the assistant needs to generate the post. Both are checked through the
 * public export of main_panel.
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
final class ai_card_availability_test extends \advanced_testcase {
    /**
     * MDL-E2E-004: a user without an issued certificate must not get the AI assistant, so no
     * generation can be requested and no credits can be consumed.
     *
     * export_for_template() used to derive 'enableai' from the global setting alone, ignoring the
     * issue, so the card was offered and operative without a certificate. It now requires both the
     * global setting and the issue of the session user.
     */
    public function test_ai_assistant_is_not_available_without_an_issued_certificate(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        // Precondition of the case: this user has no issue, so the panel is in its error state.
        $this->assertTrue($data['issued']);

        $this->assertFalse(
            $data['enableai'],
            'The AI assistant must not be available to a user without an issued certificate: the '
                . 'card is offered and generates text with an empty certificate name, consuming credits.'
        );
    }

    /**
     * MDL-E2E-004: without an issue the panel exports no certificate data, so there is nothing
     * for the assistant to build a generation request with.
     */
    public function test_no_generation_context_is_exported_without_an_issued_certificate(): void {
        $this->resetAfterTest();

        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertSame('', $data['certname']);
        $this->assertSame('', $data['certid']);
        $this->assertNull($data['shareurl']);
    }

    /**
     * MDL-E2E-004: with the AI disabled globally the assistant is never available, not even for a
     * user who already holds the certificate.
     */
    public function test_ai_assistant_is_not_available_when_the_ai_is_disabled_globally(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 0, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertFalse($data['issued']);
        $this->assertFalse(
            $data['enableai'],
            'The AI assistant must stay unavailable while the AI is disabled globally.'
        );
    }

    /**
     * MDL-E2E-004: with the AI enabled and the certificate already issued the assistant is
     * available and receives a non empty certificate name.
     */
    public function test_ai_assistant_is_available_with_an_issue_and_the_ai_enabled(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $data = $this->export_panel($scenario->customcert->cmid);

        $this->assertFalse($data['issued']);
        $this->assertTrue($data['enableai']);
        $this->assertNotEmpty($data['certname']);
        $this->assertSame('AI Fundamentals', $data['certname']);
    }

    /**
     * MDL-E2E-004: the subcontext shared with the state web service carries no certificate data
     * while there is no issue, so a card rendered with it could never request a generation.
     *
     * The card lives in its own template (local_socialcert/ai_card) so the browser can add it as
     * soon as the certificate is issued. That template is rendered with this subcontext, so the
     * check that the assistant cannot be used without a certificate has to hold here too.
     */
    public function test_shared_assistant_context_carries_no_certificate_data_without_an_issue(): void {
        global $USER;

        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);

        $cmid = (int) $scenario->customcert->cmid;
        $state = main_panel::get_share_state($cmid, (int) $USER->id);
        $aicard = main_panel::get_ai_card_context($cmid, $state);

        $this->assertFalse($state['enableai'], 'The assistant must stay unavailable without an issue.');
        $this->assertSame('', $aicard['certname'], 'There is no certificate to write a post about.');
        $this->assertSame('', $aicard['shareurl'], 'There is no credential link to publish.');
    }

    /**
     * MDL-E2E-004: the panel and the state web service share one single assistant subcontext.
     *
     * The card is rendered by the server through local_socialcert/main and by the browser through
     * core/templates, both with this subcontext, so the two renderings can never disagree on the
     * data of the assistant. The export keeps the keys flat because a Mustache partial inherits the
     * context of the template that includes it.
     */
    public function test_exported_panel_carries_the_shared_assistant_context(): void {
        global $USER;

        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $cmid = (int) $scenario->customcert->cmid;
        $state = main_panel::get_share_state($cmid, (int) $USER->id);
        $aicard = main_panel::get_ai_card_context($cmid, $state);
        $data = $this->export_panel($cmid);

        $this->assertTrue($data['enableai']);
        foreach ($aicard as $key => $value) {
            $this->assertArrayHasKey($key, $data, "The panel must export the assistant key '{$key}'.");
        }

        // The share URL is the only key the panel exports in its own shape: the template needs to
        // tell a missing URL apart to render the disabled state, so it keeps the nullable value.
        $this->assertSame($state['shareurl'], $data['shareurl']);
        $this->assertSame($aicard['certname'], $data['certname']);
        $this->assertSame($aicard['course'], $data['course']);
        $this->assertSame($aicard['author_name'], $data['author_name']);
    }

    /**
     * Creates a course with a customcert activity and an enrolled student.
     *
     * @return \stdClass Object with the course, customcert and student records.
     */
    private function create_certificate_scenario(): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', [
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
}
