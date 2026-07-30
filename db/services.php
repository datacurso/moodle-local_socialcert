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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_socialcert_get_ai_response' => [
        'classname'   => 'local_socialcert\\external\\ai_helper',
        'methodname'  => 'execute',
        'description' => 'Get AI response from the service.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_socialcert_get_share_state' => [
        'classname'   => 'local_socialcert\\external\\share_state',
        'methodname'  => 'execute',
        'description' => 'Get the state of the share panel for the user in session.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
