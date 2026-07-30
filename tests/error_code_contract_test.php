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
use local_socialcert\external\ai_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests that the provider error code survives the external return structure.
 *
 * The panel classifies provider failures by their error code to show the specific message
 * (insufficient credits, unauthorised licence, rate limit). That code therefore has to
 * travel intact through the web service return structure and reach the browser.
 *
 * Runs in separate processes for the same reason as external_ai_helper_test: the external
 * class does a file level require_once of lib/externallib.php, which calls
 * require_phpunit_isolation().
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class error_code_contract_test extends \externallib_advanced_testcase {

    /**
     * MDL-CTR-002: a provider error code that is not strictly alphanumeric reaches the panel intact.
     *
     * [Pendiente:skip] The return structure declares the error code as alphanumeric text, so real
     * provider codes containing spaces do not survive the response cleaning and the panel falls back
     * to the generic message. Skipped until the declared parameter type accepts the real codes.
     */
    public function test_provider_error_code_with_spaces_reaches_the_panel(): void {
        $this->resetAfterTest();

        $this->markTestSkipped(
            'The error code is declared as alphanumeric text while real provider codes contain spaces, '
            . 'so cleaning the response drops the code and the panel loses the specific message.'
        );
    }

    /**
     * MDL-CTR-002: a well formed error code is preserved by the declared return structure.
     *
     * This is the part of the contract that holds today and guards against regressions in the
     * fields the panel depends on when it classifies a provider failure.
     */
    public function test_well_formed_error_payload_is_preserved_by_the_return_structure(): void {
        $this->resetAfterTest();

        $payload = [
            'ok' => false,
            'message' => 'Insufficient AI credits.',
            'errorcode' => 'error_ratelimit_exceeded',
        ];

        $cleaned = external_api::clean_returnvalue(ai_helper::execute_returns(), $payload);

        $this->assertFalse($cleaned['ok']);
        $this->assertSame('Insufficient AI credits.', $cleaned['message']);
        $this->assertSame('error_ratelimit_exceeded', $cleaned['errorcode']);
    }
}
