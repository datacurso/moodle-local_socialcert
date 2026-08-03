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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\output;

/**
 * Helper class for building LinkedIn profile URLs.
 *
 * Provides utilities to construct the "Add to profile" link for a given
 * certificate, including metadata such as name, issuing organization,
 * issue and expiration dates, and public verification URL.
 * Used by the local_socialcert plugin to enable certificate sharing
 * through LinkedIn.
 *
 * @package    local_socialcert
 * @category   output
 */
class linkedin_helper {
    /**
     * Builds the LinkedIn "Add to profile" URL for a certificate.
     *
     * Reference base: https://www.linkedin.com/profile/add
     *
     * Required params for our MVP:
     * - name: Certificate name to display in LinkedIn.
     * - organizationId: Admin-configured LinkedIn org/company ID (global setting).
     * - issueYear & issueMonth: From the certificate issue time.
     * - certUrl: Public verification URL (must be accessible without login).
     * - certId: Unique certificate id/code.
     *
     * @param string   $certname        Display name of the certificate.
     * @param int      $issueunixtime   Issued timestamp (UNIX).
     * @param string   $certurl         Public verification URL (no auth).
     * @param string   $certid          Unique certificate ID (e.g., issue code).
     * @param int|null $expiryunixtime  Optional expiration timestamp.
     * @return string|null              Fully built URL, or null when the organization ID is not configured.
     */
    public static function build_linkedin_url(
        string $certname,
        int $issueunixtime,
        string $certurl,
        string $certid,
        ?int $expiryunixtime = null
    ): ?string {
        // No organization may be invented here: publishing the credential under a foreign
        // organization ID would attribute it to a third party. Without the setting there is no URL.
        $orgid = trim((string) get_config('local_socialcert', 'organizationid'));
        if ($orgid === '') {
            return null;
        }

        $params = [
            'startTask' => 'CERTIFICATION_NAME',
            'name'      => $certname,
            'organizationId' => $orgid,
            'issueYear'  => (int) date(format: 'Y', timestamp: $issueunixtime),
            'issueMonth' => (int) date(format: 'n', timestamp: $issueunixtime),
            'certUrl'    => $certurl,
            'certId'     => $certid,
        ];

        if (!empty($expiryunixtime)) {
            $params['expirationYear']  = (int) date(format: 'Y', timestamp: $expiryunixtime);
            $params['expirationMonth'] = (int) date(format: 'n', timestamp: $expiryunixtime);
        }

        $query = http_build_query(data: $params, numeric_prefix: '', arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
        return 'https://www.linkedin.com/profile/add?' . $query;
    }
}
