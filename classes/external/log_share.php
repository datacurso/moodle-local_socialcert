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
 * External function that records the share of a credential in the platform logs.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_socialcert\event\certificate_shared;
use local_socialcert\output\main_panel;

/**
 * Records the \local_socialcert\event\certificate_shared event for the session user.
 *
 * The share itself happens in the browser: the panel opens the LinkedIn add-to-profile form in a new
 * window, and the server is never part of that navigation. A dedicated write function is therefore
 * the only clean way to leave a trace of the action, and it is deliberately NOT
 * local_socialcert_get_share_state:
 *
 * - That function is declared as a read function and is called on every pageshow and every
 *   visibilitychange of the activity page, so reusing it would record shares that never happened and
 *   would break its read only contract at the same time.
 * - The share is a single user action, so it deserves a function that is called exactly once per
 *   click and can be rejected on its own permissions.
 *
 * The function writes nothing but the log entry: the certificate issue of mod_customcert is only
 * read, exactly like the panel does.
 *
 * @package    local_socialcert
 * @category   external
 */
class log_share extends external_api {
    /**
     * Defines the parameters accepted by the external function.
     *
     * @return external_function_parameters The parameter structure definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the custom certificate activity'),
        ]);
    }

    /**
     * Records that the session user shared the credential of a certificate activity.
     *
     * The access rules are the ones of the panel: an active session with access to the activity, the
     * mod/customcert:view capability and the local/socialcert:viewsharepanel capability, on a course
     * module that really is a custom certificate.
     *
     * The business rules of the panel are revalidated too, so the log can never record a share that
     * was impossible: the session user needs the issue of the certificate and the site needs the
     * LinkedIn organization ID configured, which is exactly what produces a share URL.
     *
     * @param int $cmid Course module ID of the custom certificate activity.
     * @return array Result with the logged key, true once the event has been recorded.
     */
    public static function execute($cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        if ($params['cmid'] <= 0) {
            throw new \moodle_exception('invalidcmid', 'local_socialcert');
        }

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/customcert:view', $context);
        require_capability('local/socialcert:viewsharepanel', $context);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname !== 'customcert') {
            throw new \moodle_exception('notacertificateactivity', 'local_socialcert');
        }

        $state = main_panel::get_share_state($params['cmid'], (int) $USER->id);

        // No share URL means the panel could not offer the action at all, so there is no share to
        // record: either the certificate is not issued yet or the organization ID is missing.
        if ($state['shareurl'] === null) {
            throw new \moodle_exception('nothingtoshare', 'local_socialcert');
        }

        certificate_shared::create([
            'context' => $context,
            'other'   => [
                'certid'  => (string) $state['certid'],
                'network' => (string) $state['datanetwork'],
            ],
        ])->trigger();

        return ['logged' => true];
    }

    /**
     * Defines the return structure for the external function.
     *
     * @return external_single_structure The definition of the return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'logged' => new external_value(PARAM_BOOL, 'Whether the share has been recorded in the logs'),
        ]);
    }
}
