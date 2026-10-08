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
 * External functions and services declared by local_socialcert.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Declared as a write function because every successful call spends the AI credits of the
    // licence and records the ai_text_generated event. The browser only names the activity and
    // the social network: the inputs of the prompt are computed on the server.
    'local_socialcert_get_ai_response' => [
        'classname'   => 'local_socialcert\\external\\ai_helper',
        'methodname'  => 'execute',
        'description' => 'Generate the AI post text for the certificate of the user in session.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_socialcert_get_share_state' => [
        'classname'   => 'local_socialcert\\external\\share_state',
        'methodname'  => 'execute',
        'description' => 'Get the state of the share panel for the user in session.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    // Declared as a write function because it is the only one that leaves something behind: the
    // certificate_shared event of the user in session. The share itself happens in the browser, so
    // this is what makes the action traceable in the logs of the platform.
    'local_socialcert_log_share' => [
        'classname'   => 'local_socialcert\\external\\log_share',
        'methodname'  => 'execute',
        'description' => 'Record in the logs that the user shared the credential of a certificate.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
