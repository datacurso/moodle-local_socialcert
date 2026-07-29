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
 * Unit tests for the LinkedIn "Add to profile" URL builder.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use local_socialcert\output\linkedin_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the raw construction of the LinkedIn "Add to profile" URL.
 *
 * These tests exercise the public static builder with raw input only. The end to end
 * chain (values that already went through the platform text filters) is covered by
 * main_panel_test.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\output\linkedin_helper
 */
final class linkedin_helper_test extends \advanced_testcase {

    /** @var string Sample verification URL used across the tests. */
    private const VERIFY_URL = 'https://example.com/mod/customcert/verify_certificate.php?code=ABC1234567';

    /**
     * MDL-UNIT-001: the URL points to the official LinkedIn certification form and carries
     * the certification task, the certificate name, the configured numeric organization ID,
     * the issue month and year, the credential code and the public verification link.
     */
    public function test_url_targets_the_linkedin_form_with_every_credential_parameter(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $issuetime = gmmktime(12, 0, 0, 6, 15, 2025);

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            $issuetime,
            self::VERIFY_URL,
            'ABC1234567'
        );

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://www.linkedin.com/profile/add?', $url);

        $params = self::query_params($url);

        // Exactly the seven documented parameters, in the documented order.
        $this->assertSame(
            ['startTask', 'name', 'organizationId', 'issueYear', 'issueMonth', 'certUrl', 'certId'],
            array_keys($params)
        );

        $this->assertSame('CERTIFICATION_NAME', $params['startTask']);
        $this->assertSame('AI Fundamentals', $params['name']);
        $this->assertSame('54321', $params['organizationId']);
        $this->assertSame('2025', $params['issueYear']);
        $this->assertSame('6', $params['issueMonth']);
        $this->assertSame(self::VERIFY_URL, $params['certUrl']);
        $this->assertSame('ABC1234567', $params['certId']);
    }

    /**
     * MDL-UNIT-001: the organization name never travels to LinkedIn, only the numeric ID does.
     */
    public function test_organization_name_is_never_sent_to_linkedin(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '98765', 'local_socialcert');
        set_config('organizationname', 'Datacurso Formacion', 'local_socialcert');

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            gmmktime(12, 0, 0, 6, 15, 2025),
            self::VERIFY_URL,
            'ABC1234567'
        );

        $params = self::query_params($url);

        $this->assertSame('98765', $params['organizationId']);
        $this->assertArrayNotHasKey('organizationName', $params);
        $this->assertArrayNotHasKey('organization', $params);
        $this->assertStringNotContainsString('Datacurso', $url);
        $this->assertStringNotContainsString('Formacion', $url);
        $this->assertStringNotContainsString('Datacurso', urldecode($url));
    }

    /**
     * MDL-UNIT-001: special characters (accents, enie, spaces, quotes) are encoded
     * following RFC3986, so spaces become %20 and never a plus sign.
     */
    public function test_special_characters_are_encoded_following_rfc3986(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $certname = 'Diseño Gráfico "Avanzado" para Niños';

        $url = linkedin_helper::build_linkedin_url(
            $certname,
            gmmktime(12, 0, 0, 6, 15, 2025),
            self::VERIFY_URL,
            'ABC1234567'
        );

        // Spaces must be percent encoded, never encoded as a plus sign.
        $this->assertStringContainsString('%20', $url);
        $this->assertStringNotContainsString('+', $url);

        // Accents, enie and double quotes are percent encoded.
        $this->assertStringContainsString('Dise%C3%B1o', $url);
        $this->assertStringContainsString('Gr%C3%A1fico', $url);
        $this->assertStringContainsString('%22Avanzado%22', $url);
        $this->assertStringContainsString('Ni%C3%B1os', $url);

        // And the value round trips without loss.
        $params = self::query_params($url);
        $this->assertSame($certname, $params['name']);
    }

    /**
     * MDL-UNIT-001: issue month and year are resolved against the server timezone,
     * covering January, December and year changes.
     *
     * @dataProvider issue_date_boundary_provider
     * @param string $timezone Server timezone in use when the URL is built.
     * @param int    $issuetime Issue timestamp (UNIX, absolute).
     * @param string $expectedyear Expected issueYear parameter.
     * @param string $expectedmonth Expected issueMonth parameter.
     */
    public function test_issue_month_and_year_follow_the_server_timezone(
        string $timezone,
        int $issuetime,
        string $expectedyear,
        string $expectedmonth
    ): void {
        $this->resetAfterTest();
        $this->setTimezone($timezone, $timezone);
        set_config('organizationid', '54321', 'local_socialcert');

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            $issuetime,
            self::VERIFY_URL,
            'ABC1234567'
        );

        $params = self::query_params($url);

        $this->assertSame($expectedyear, $params['issueYear']);
        $this->assertSame($expectedmonth, $params['issueMonth']);
    }

    /**
     * Boundary issue dates. Timestamps are absolute UTC values so the expectations depend
     * only on the server timezone fixed inside the test.
     *
     * @return array[]
     */
    public static function issue_date_boundary_provider(): array {
        return [
            'first second of January' => ['UTC', gmmktime(0, 0, 0, 1, 1, 2024), '2024', '1'],
            'last second of December' => ['UTC', gmmktime(23, 59, 59, 12, 31, 2024), '2024', '12'],
            'year change, previous year' => ['UTC', gmmktime(23, 59, 59, 12, 31, 2023), '2023', '12'],
            'year change, new year' => ['UTC', gmmktime(0, 0, 0, 1, 1, 2025), '2025', '1'],
            'negative offset moves back to December' => ['America/Lima', gmmktime(0, 30, 0, 1, 1, 2025), '2024', '12'],
            'positive offset moves forward to January' => ['Pacific/Auckland', gmmktime(23, 30, 0, 12, 31, 2024), '2025', '1'],
        ];
    }

    /**
     * MDL-UNIT-002: when an expiration timestamp is supplied the URL carries the expiration
     * month and year.
     *
     * Decision: build_linkedin_url() does accept the optional $expiryunixtime argument, so the
     * unit level behaviour is verifiable directly and this test must pass. Only the integrated
     * panel scenario is pending, see
     * test_panel_forwards_the_certificate_expiry_date_to_linkedin().
     */
    public function test_expiration_year_and_month_are_added_when_an_expiry_time_is_supplied(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            gmmktime(12, 0, 0, 6, 15, 2025),
            self::VERIFY_URL,
            'ABC1234567',
            gmmktime(12, 0, 0, 11, 30, 2027)
        );

        $params = self::query_params($url);

        $this->assertSame('2027', $params['expirationYear']);
        $this->assertSame('11', $params['expirationMonth']);
        // The issue date is untouched by the expiry.
        $this->assertSame('2025', $params['issueYear']);
        $this->assertSame('6', $params['issueMonth']);
    }

    /**
     * MDL-UNIT-002: without an expiration timestamp the URL carries no expiration fields.
     */
    public function test_expiration_fields_are_absent_when_no_expiry_time_is_supplied(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            gmmktime(12, 0, 0, 6, 15, 2025),
            self::VERIFY_URL,
            'ABC1234567'
        );

        $params = self::query_params($url);

        $this->assertArrayNotHasKey('expirationYear', $params);
        $this->assertArrayNotHasKey('expirationMonth', $params);
    }

    /**
     * MDL-UNIT-002: the share panel forwards the credential expiry date to LinkedIn.
     *
     * [Pendiente:skip] The builder supports the expiry argument (covered by
     * test_expiration_year_and_month_are_added_when_an_expiry_time_is_supplied) but
     * main_panel::export_for_template() never passes it, so the expiry never reaches
     * LinkedIn from the panel. Pending functionality.
     */
    public function test_panel_forwards_the_certificate_expiry_date_to_linkedin(): void {
        $this->markTestSkipped(
            'main_panel::export_for_template() calls build_linkedin_url() without the expiry ' .
            'argument, so the credential expiration date is never sent to LinkedIn from the panel. ' .
            'Pending functionality.'
        );
    }

    /**
     * MDL-UNIT-003: the ten character alphanumeric code travels unchanged both as the
     * credential ID and inside the verification link.
     */
    public function test_ten_character_alphanumeric_code_travels_unchanged(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $code = 'a1B2c3D4e5';
        $verifyurl = 'https://example.com/mod/customcert/verify_certificate.php?code=' . $code;

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            gmmktime(12, 0, 0, 6, 15, 2025),
            $verifyurl,
            $code
        );

        $params = self::query_params($url);

        $this->assertSame($code, $params['certId']);
        $this->assertSame($verifyurl, $params['certUrl']);
        $this->assertStringContainsString('code%3D' . $code, $url);
    }

    /**
     * MDL-UNIT-003: the numeric hyphenated code travels unchanged both as the credential ID
     * and inside the verification link.
     */
    public function test_numeric_hyphenated_code_travels_unchanged(): void {
        $this->resetAfterTest();
        $this->setTimezone('UTC', 'UTC');
        set_config('organizationid', '54321', 'local_socialcert');

        $code = '1234-5678-9012';
        $verifyurl = 'https://example.com/mod/customcert/verify_certificate.php?code=' . $code;

        $url = linkedin_helper::build_linkedin_url(
            'AI Fundamentals',
            gmmktime(12, 0, 0, 6, 15, 2025),
            $verifyurl,
            $code
        );

        $params = self::query_params($url);

        $this->assertSame($code, $params['certId']);
        $this->assertSame($verifyurl, $params['certUrl']);
        $this->assertStringContainsString('code%3D1234-5678-9012', $url);
    }

    /**
     * Decodes the query string of a built URL into an associative array.
     *
     * @param string|null $url The URL returned by the builder.
     * @return array Decoded query parameters, preserving their original order.
     */
    private static function query_params(?string $url): array {
        $params = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $params);
        return $params;
    }
}
