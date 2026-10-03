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
 * External AI function wired to a stubbed HTTP client.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\fixtures;

use aiprovider_datacurso\httpclient\ai_services_api;
use local_socialcert\external\ai_helper;

/**
 * The real external function with the only seam of the class substituted.
 *
 * Everything under test (the access rules, the business revalidations, the shape of the answer and
 * the event of a successful generation) is inherited untouched from
 * \local_socialcert\external\ai_helper; the subclass only replaces the HTTP client, whose real
 * constructor performs a network request.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_ai_helper extends ai_helper {
    /**
     * Client handed to every call of the function.
     *
     * It is kept here so the test can inspect the requests the function sent and configure the
     * failure the service answers with. A test replaces it before calling the function.
     *
     * @var stub_ai_services_api|null
     */
    public static ?stub_ai_services_api $client = null;

    /**
     * Returns the stubbed client instead of the real one.
     *
     * @return ai_services_api Client that answers without reaching the network.
     */
    protected static function get_ai_client(): ai_services_api {
        if (self::$client === null) {
            self::$client = new stub_ai_services_api();
        }

        return self::$client;
    }
}
