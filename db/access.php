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
 * Capability definitions of local_socialcert.
 *
 * Two capabilities, one per feature of the panel, so an administrator can restrict the sharing and
 * the AI assistant independently from the permissions administration.
 *
 * Context level: both are declared at CONTEXT_MODULE. The whole feature lives inside one custom
 * certificate activity (the footer callback only runs on mod/customcert/view.php and both web
 * services receive a course module id), so the activity context is the narrowest context where the
 * question "may this user share this certificate" can be answered. Declaring them at module level
 * also lets them be overridden at every level above it (activity, course and category), which is
 * exactly what makes the feature restrictable per activity, per course or site wide.
 *
 * Default archetypes: only 'student' => CAP_ALLOW. The defaults are deliberately chosen so that
 * nobody gains or loses the feature by installing this version: the panel already demanded
 * mod/customcert:receiveissue, whose only archetype in mod_customcert is 'student', so the students
 * are the only role that could ever see the panel or reach the assistant. Teachers and managers are
 * left out of the defaults for the same reason: they do not receive the certificate, so the panel is
 * not offered to them today either. Site administrators keep the feature because they hold every
 * capability by definition, not because of an archetype.
 *
 * Capability type: both are 'read'. Neither of them writes anything in Moodle: sharing opens the
 * LinkedIn add-to-profile form in the browser of the user, and the assistant returns a draft text to
 * the very same user. No risk bitmask applies either, because no capability lets a user publish
 * content for other users, reach personal data of others or change any configuration.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Who gets the share panel of the certificate activity. Also required by the web services that
    // report the state of the panel and record a share, so hiding the panel really removes the
    // feature instead of only removing its markup.
    'local/socialcert:viewsharepanel' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'student' => CAP_ALLOW,
        ],
    ],

    // Who may draft the post text with the AI assistant. It is a capability of its own because the
    // generation spends the AI credits of the licence, so it has to be restrictable without taking
    // the sharing itself away.
    'local/socialcert:useaiassistant' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'student' => CAP_ALLOW,
        ],
    ],
];
