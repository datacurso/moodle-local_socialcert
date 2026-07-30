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

namespace local_socialcert\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy Subsystem implementation for local_socialcert.
 *
 * The plugin keeps no table and no user preference of its own, so it declares the null provider
 * to state that it stores no personal data locally. It does send data to two third parties, so it
 * also declares the metadata provider with one external location per destination:
 *
 * - The Datacurso AI service, which receives the certificate, course and organization names, the
 *   numeric user identifier and the site data needed to authorise the request.
 * - LinkedIn, which receives the credential data when the user activates the share button.
 *
 * No request provider is needed because there is no locally stored data to export or delete.
 *
 * @package    local_socialcert
 * @copyright  2025 Manuel Bojaca <manuel@buendata.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\null_provider,
    \core_privacy\local\metadata\provider {
    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return string The string identifier of the reason.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    /**
     * Declare the personal data this plugin transmits to external services.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'datacurso_ai_service',
            [
                'certname' => 'privacy:metadata:aiservice:certname',
                'coursename' => 'privacy:metadata:aiservice:coursename',
                'organizationname' => 'privacy:metadata:aiservice:organizationname',
                'userid' => 'privacy:metadata:aiservice:userid',
                'sitedata' => 'privacy:metadata:aiservice:sitedata',
            ],
            'privacy:metadata:aiservice'
        );

        $collection->add_external_location_link(
            'linkedin.com',
            [
                'certificationname' => 'privacy:metadata:linkedin:certificationname',
                'organizationid' => 'privacy:metadata:linkedin:organizationid',
                'issuedate' => 'privacy:metadata:linkedin:issuedate',
                'credentialcode' => 'privacy:metadata:linkedin:credentialcode',
                'verificationlink' => 'privacy:metadata:linkedin:verificationlink',
            ],
            'privacy:metadata:linkedin'
        );

        return $collection;
    }
}
