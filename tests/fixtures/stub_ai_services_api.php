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
 * Stub of the AI HTTP client that answers without reaching the network.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\fixtures;

use aiprovider_datacurso\httpclient\ai_services_api;

/**
 * AI services client that answers a fixed reply instead of contacting the service.
 *
 * The constructor of the real client asks the token manager which region the licence belongs to,
 * which is an HTTP request, so it is deliberately not called here.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_ai_services_api extends ai_services_api {
    /** @var array Reply every request of the stub answers with. */
    public const REPLY = ['reply' => 'Proud to share my new certificate'];

    /** @var array[] Requests received by the stub, in order. */
    public array $requests = [];

    /**
     * Builds the stub without any of the setup of the real client.
     */
    public function __construct() {
        // The parent constructor performs a network request, which is exactly what the stub avoids.
    }

    /**
     * Records the request and answers the fixed reply.
     *
     * @param string $method HTTP method of the request.
     * @param string $path Path of the endpoint being called.
     * @param array $body Body of the request.
     * @return array|null The fixed reply of the stub.
     */
    public function request(string $method, string $path, array $body = []): ?array {
        $this->requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

        return self::REPLY;
    }
}
