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
 * External function that reports the state of the share panel to the browser.
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
use local_socialcert\output\main_panel;

/**
 * Reports whether the session user can already share the certificate of an activity.
 *
 * mod_customcert only records the issue when the student downloads the certificate, and with the
 * default inline delivery the PDF replaces the activity page in the same tab. Going back restores
 * the previous HTML from the browser cache, which was rendered while there was still no issue, so
 * the panel stayed disabled until the user reloaded by hand. The panel calls this function to read
 * its own state again and enable itself without any manual reload.
 *
 * The semantics of the issue are untouched: this function never creates one, it only reports it.
 *
 * @package    local_socialcert
 * @category   external
 */
class share_state extends external_api {
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
     * Returns the state of the share panel for the session user.
     *
     * The access rules are the ones the panel itself is subject to: an active session with access
     * to the activity, the mod/customcert:view capability and the local/socialcert:viewsharepanel
     * capability, on a course module that really is a custom certificate.
     *
     * Unlike ai_helper::execute(), the rejections are not converted into a payload: this function
     * spends no credits and reaches no external service, so a failure is a plain exception and the
     * panel simply stays in the disabled state the server already rendered.
     *
     * @param int $cmid Course module ID of the custom certificate activity.
     * @return array State of the panel with the hasissue, shareurl, network, enableai and, only
     *               when the assistant is available, aicard keys.
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

        // A role without the capability of the panel has no panel to report the state of, so the
        // request is rejected here as well and not only in the footer callback that renders it.
        require_capability('local/socialcert:viewsharepanel', $context);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname !== 'customcert') {
            throw new \moodle_exception('notacertificateactivity', 'local_socialcert');
        }

        $state = main_panel::get_share_state($params['cmid'], (int) $USER->id);

        $response = [
            'hasissue' => $state['hasissue'],
            // An empty URL means the panel must stay disabled: either there is no issue yet or the
            // LinkedIn organization ID is not configured, and no share link may be invented here.
            'shareurl' => (string) ($state['shareurl'] ?? ''),
            'network'  => $state['datanetwork'],
            // The panel enabled in the browser must carry the same verification warning the server
            // renders, so the student is never told to publish a link nobody else can verify.
            'verifywarning' => $state['verifywarning'],
            'enableai' => $state['enableai'],
        ];

        // The context of the assistant card only travels to the browser when the assistant really
        // is available, which demands the issued certificate. A user without the issue receives no
        // certificate data at all, so there is nothing to render the card with, exactly like the
        // server side rendering of the panel.
        if ($state['enableai']) {
            $response['aicard'] = main_panel::get_ai_card_context($params['cmid'], $state);
        }

        return $response;
    }

    /**
     * Defines the return structure for the external function.
     *
     * @return external_single_structure The definition of the return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasissue' => new external_value(PARAM_BOOL, 'Whether the user already has an issued certificate'),
            // PARAM_URL cleans the value the browser is going to put in an href. The builder
            // percent encodes every parameter with http_build_query(), so accented certificate
            // names and the verification link survive the syntax validation untouched.
            'shareurl' => new external_value(PARAM_URL, 'LinkedIn share URL, empty while the panel must stay disabled'),
            'network'  => new external_value(PARAM_ALPHANUMEXT, 'Social network of the share action, empty when disabled'),
            'verifywarning' => new external_value(PARAM_TEXT, 'Warning shown when third parties cannot verify the link'),
            'enableai' => new external_value(PARAM_BOOL, 'Whether the AI assistant is available for this user'),
            'aicard' => self::ai_card_structure(),
        ]);
    }

    /**
     * Defines the context of the assistant card returned to the browser.
     *
     * The panel renders the local_socialcert/ai_card template with this context when the assistant
     * becomes available while the page is already open, so the markup of the card is never rebuilt
     * in JavaScript. The structure is optional: it is absent whenever the assistant is not
     * available, and its absence is what keeps the card out of the page.
     *
     * @return external_single_structure The optional assistant card context.
     */
    private static function ai_card_structure(): external_single_structure {
        return new external_single_structure([
            'aibuttonid' => new external_value(PARAM_ALPHANUMEXT, 'DOM id of the button that starts the generation'),
            'responseid' => new external_value(PARAM_ALPHANUMEXT, 'DOM id of the container of the generated text'),
            'socialmedia' => new external_value(PARAM_ALPHANUMEXT, 'Social network the post is written for'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the certificate activity'),
            'author_name' => new external_value(PARAM_TEXT, 'Full name of the user in session'),
            // Empty for a user without a profile picture, which is what makes the card draw the
            // generic silhouette instead.
            'author_avatar_url' => new external_value(PARAM_URL, 'Profile picture of the user in session, may be empty'),
            'certname' => new external_value(PARAM_TEXT, 'Name of the issued certificate'),
            'course' => new external_value(PARAM_TEXT, 'Full name of the course of the certificate'),
            'org' => new external_value(PARAM_TEXT, 'Name of the issuing organization'),
            'shareurl' => new external_value(PARAM_URL, 'LinkedIn share URL, empty without the organization ID'),
            'imageurl' => new external_value(PARAM_URL, 'URL of the assistant logo'),
            'imagelogo' => new external_value(PARAM_URL, 'URL of the background image of the assistant button'),
            'ai_actioncall' => new external_value(PARAM_TEXT, 'Call to action of the assistant'),
            // Accessible names of the controls of the card, so the browser never has to rebuild a
            // label of its own for the card it renders after the certificate has just been issued.
            'aibuttonlabel' => new external_value(PARAM_TEXT, 'Accessible name of the button that starts the generation'),
            'ailogoalt' => new external_value(PARAM_TEXT, 'Alternative text of the assistant logo'),
            'airegionlabel' => new external_value(PARAM_TEXT, 'Accessible name of the region of the assistant'),
            'avatarlabel' => new external_value(PARAM_TEXT, 'Accessible name of the picture of the post preview'),
            'copytextlabel' => new external_value(PARAM_TEXT, 'Accessible name of the button that copies the text'),
            // The three error messages are lang strings of this plugin and legitimately carry a
            // link to the Datacurso shop, so they are declared raw to keep the anchor. The module
            // rebuilds them from an allowlist of nodes before showing them, never with innerHTML.
            'errorcredits' => new external_value(PARAM_RAW, 'Message shown when the licence has no AI credits left'),
            'errorgeneric' => new external_value(PARAM_RAW, 'Message shown when the generation fails'),
            'errorlicense' => new external_value(PARAM_RAW, 'Message shown when the licence cannot generate'),
        ], 'Context of the assistant card, absent while the assistant is not available', VALUE_OPTIONAL);
    }
}
