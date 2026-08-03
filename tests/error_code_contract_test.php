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
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 */
final class error_code_contract_test extends \externallib_advanced_testcase {
    /**
     * MDL-CTR-002: a provider error code that is not strictly alphanumeric reaches the panel intact.
     *
     * Previously skipped: the return structure declared the error code as PARAM_ALPHANUMEXT, so the
     * real provider codes containing spaces did not survive the response cleaning. The declared type
     * is now PARAM_TEXT, so the case is implemented and asserts the real code arrives untouched.
     */
    public function test_provider_error_code_with_spaces_reaches_the_panel(): void {
        $this->resetAfterTest();

        // Literal code thrown by aiprovider_datacurso when the client cannot be built.
        $errorcode = 'API baseurl or licensekey not configured';

        $cleaned = external_api::clean_returnvalue(ai_helper::execute_returns(), [
            'ok' => false,
            'message' => 'error/API baseurl or licensekey not configured',
            'errorcode' => $errorcode,
        ]);

        $this->assertArrayHasKey('errorcode', $cleaned, 'The provider error code must survive the cleaning.');
        $this->assertSame($errorcode, $cleaned['errorcode']);

        // The panel classifies the failure by that code, so it must still be recognisable.
        $this->assertStringContainsString('licensekey', $cleaned['errorcode']);
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
