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

namespace local_socialcert\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use aiprovider_datacurso\httpclient\ai_services_api;
use local_socialcert\event\ai_text_generated;
use local_socialcert\output\main_panel;

/**
 * External API helper for AI-based certificate generation.
 *
 * Provides web service definitions to communicate with an external AI API
 * that generates social media–ready certificate texts. This class defines
 * the parameters, execution logic, and return structure of the service.
 *
 * Extends {@see external_api} to integrate with Moodle's external functions system.
 *
 * @package    local_socialcert
 * @category   external
 */
class ai_helper extends external_api {
    /**
     * Social networks the assistant knows how to write a post for.
     *
     * The network is part of the prompt the AI service receives, so only the networks the plugin
     * supports are accepted; any other value is refused before the service is contacted.
     *
     * @var string[]
     */
    public const SOCIAL_NETWORKS = ['linkedin'];

    /**
     * Components whose exception messages may be shown to the user of the panel.
     *
     * Only the AI provider and this plugin write their messages for the user of the panel (the
     * provider, for instance, localizes the rate limit message with the retry time). Any other
     * exception (core, the database layer, PHP itself) may name internals, so its text never
     * leaves the server and the generic message of the plugin is returned instead.
     *
     * @var string[]
     */
    public const TRUSTED_MESSAGE_COMPONENTS = ['aiprovider_datacurso', 'local_socialcert'];

    /**
     * Defines the parameters accepted by the external function.
     *
     * The browser only names the activity and the social network. The inputs of the prompt
     * (certificate, course and organization names) are computed on the server from the activity
     * and the user in session, so no caller can feed the AI service arbitrary text.
     *
     * @return external_function_parameters The parameter structure definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the certificate activity'),
            'socialmedia' => new external_value(
                PARAM_ALPHANUMEXT,
                'Social network the post is written for (one of ' . implode(', ', self::SOCIAL_NETWORKS) . ')',
                VALUE_DEFAULT,
                'linkedin'
            ),
        ]);
    }

    /**
     * Executes the external API request to generate AI certificate content.
     *
     * Validates input parameters, revalidates the business rules that govern the AI assistant,
     * sends a POST request to the external AI service, and returns the response JSON as a string.
     * If the response is not an array or object, it is wrapped into a JSON object with a
     * "text" key.
     *
     * The business rules are revalidated here, and not only in the panel, because hiding the AI
     * card in the interface does not stop a direct call to the web service from spending credits.
     *
     * A generation the service really answered fires \local_socialcert\event\ai_text_generated, so
     * the consumption of the assistant is traceable in the logs of the platform.
     *
     * @param int $cmid Course module ID of the certificate activity.
     * @param string $socialmedia Social network the post is written for, see {@see self::SOCIAL_NETWORKS}.
     * @return array An associative array with a 'json' key holding the API response, or a controlled
     *               failure with the keys ok, message and (for Moodle exceptions) errorcode.
     */
    public static function execute(int $cmid, string $socialmedia = 'linkedin'): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'socialmedia' => $socialmedia]
        );

        try {
            // The network travels inside the prompt, so it is checked against the allowlist before
            // anything else: an unknown network must never reach the service nor spend credits.
            if (!in_array($params['socialmedia'], self::SOCIAL_NETWORKS, true)) {
                throw new \moodle_exception('invalidsocialmedia', 'local_socialcert');
            }

            if ($params['cmid'] <= 0) {
                throw new \moodle_exception('invalidcmid', 'local_socialcert');
            }
            $context = \context_module::instance($params['cmid']);
            self::validate_context($context);
            require_capability('mod/customcert:view', $context);

            // The capability of the assistant is revalidated here for the same reason the rest of
            // the rules are: hiding the card in the interface does not stop a direct call to the web
            // service from spending credits.
            require_capability('local/socialcert:useaiassistant', $context);

            if (!((int) get_config('local_socialcert', 'enableai'))) {
                throw new \moodle_exception('aidisabled', 'local_socialcert');
            }

            $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
            if ($cm->modname !== 'customcert') {
                throw new \moodle_exception('notacertificateactivity', 'local_socialcert');
            }

            $hasissue = $DB->record_exists('customcert_issues', [
                'customcertid' => $cm->instance,
                'userid'       => $USER->id,
            ]);
            if (!$hasissue) {
                throw new \moodle_exception('nocertificateissued', 'local_socialcert');
            }

            // The inputs of the prompt come from the same source the assistant card renders them
            // from, so the text the service receives is always the one the panel showed.
            $body = main_panel::get_ai_prompt_inputs($params['cmid'], (int) $USER->id);
            $body['socialmedia'] = $params['socialmedia'];

            $client   = static::get_ai_client();
            $response = $client->request('POST', '/certificate/answer', $body);
            if (is_array(value: $response) || is_object(value: $response)) {
                $json = json_encode(value: $response, flags: JSON_UNESCAPED_UNICODE);
            } else {
                $json = json_encode(value: ['text' => (string)$response], flags: JSON_UNESCAPED_UNICODE);
            }

            // Only a generation the service really answered is traced, so the log records the calls
            // that consumed credits and never the ones the plugin or the provider rejected.
            ai_text_generated::create([
                'context' => $context,
                'other'   => ['socialmedia' => $params['socialmedia']],
            ])->trigger();

            return ['json' => $json];
        } catch (\Throwable $e) {
            // The detail stays on the server, for the developer only: the class and the text of the
            // failure, never the request or the response bodies.
            debugging(
                'AI generation failed with ' . get_class($e) . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );

            $result = [
                'ok' => false,
                'message' => get_string('errorgeneric', 'local_socialcert'),
            ];

            if ($e instanceof \moodle_exception) {
                // The code lets the panel classify the failure (credits, licence, rate limit).
                $result['errorcode'] = $e->errorcode;

                // Only the messages written for the user of the panel are shown as they are: the
                // provider localizes the rate limit message with the retry time, and this plugin
                // explains its own business rejections. Any other component may name internals.
                if (in_array($e->module, self::TRUSTED_MESSAGE_COMPONENTS, true)) {
                    $result['message'] = $e->getMessage();
                }
            }

            return $result;
        }
    }

    /**
     * Builds the HTTP client the generation is requested through.
     *
     * The client is obtained from this factory, and not with a direct instantiation inside
     * execute(), because its constructor already performs a network request (it asks the token
     * manager which region the licence belongs to). Without this seam the successful path of the
     * function, and therefore the event it fires, could not be exercised without real network
     * access. It is protected and late bound on purpose: only a subclass can substitute the client.
     *
     * @return ai_services_api Client of the Datacurso AI services API.
     */
    protected static function get_ai_client(): ai_services_api {
        return new ai_services_api();
    }

    /**
     * Defines the return structure for the external function.
     *
     * Returns a single JSON string representing the AI-generated response
     * for the given certificate context.
     *
     * @return external_single_structure The definition of the return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Response status from server', VALUE_OPTIONAL),
            // Localized message of the failure: the text of the provider or of this plugin, or the
            // generic message of the plugin for any other failure.
            'message' => new external_value(PARAM_RAW, 'Localized message of the failure', VALUE_OPTIONAL),
            // PARAM_TEXT and not PARAM_ALPHANUMEXT: real provider codes contain spaces (for
            // instance 'API baseurl or licensekey not configured'), and the stricter type dropped
            // them while cleaning the response, so the panel lost the specific message.
            'errorcode' => new external_value(PARAM_TEXT, 'Moodle exception errorcode when the request failed', VALUE_OPTIONAL),
            'json' => new external_value(PARAM_RAW, 'Respuesta JSON de la API externa', VALUE_OPTIONAL),
        ]);
    }
}
