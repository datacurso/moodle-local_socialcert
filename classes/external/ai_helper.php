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
     * Defines the parameters accepted by the external function.
     *
     * Each request must include a `body` structure containing:
     * - certname: The certificate display name.
     * - course: The course name where the certificate was issued.
     * - org: The issuing organization's name.
     * - socialmedia: The platform where the certificate will be shared.
     *
     * @return external_function_parameters The parameter structure definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'body' => new external_single_structure([
                'certname'    => new external_value(PARAM_TEXT, 'Certificate name'),
                'course'      => new external_value(PARAM_TEXT, 'Course name'),
                'org'         => new external_value(PARAM_TEXT, 'Name of the issuing organization'),
                'socialmedia' => new external_value(PARAM_TEXT, 'Social network where the certificate will be published'),
            ]),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
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
     * @param array $body The body data containing certificate information.
     * @param array $cmid The body data containing certificate information.
     * @return array An associative array with a 'json' key holding the API response.
     */
    public static function execute($body, $cmid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['body' => $body, 'cmid' => $cmid]);

        try {
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

            $body   = $params['body'];

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
                'other'   => ['socialmedia' => (string) $body['socialmedia']],
            ])->trigger();

            return ['json' => $json];
        } catch (\Exception $e) {
            debugging("Unexpected error while starting resource generation (stream): " . $e->getMessage());
            $result = [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
            // Expose the provider's exception code so the UI can show the real (localized) message
            // for known cases such as the rate limit, instead of a generic fallback.
            if ($e instanceof \moodle_exception) {
                $result['errorcode'] = $e->errorcode;
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
            'message' => new external_value(PARAM_RAW, 'Response message from server', VALUE_OPTIONAL),
            // PARAM_TEXT and not PARAM_ALPHANUMEXT: real provider codes contain spaces (for
            // instance 'API baseurl or licensekey not configured'), and the stricter type dropped
            // them while cleaning the response, so the panel lost the specific message.
            'errorcode' => new external_value(PARAM_TEXT, 'Moodle exception errorcode when the request failed', VALUE_OPTIONAL),
            'json' => new external_value(PARAM_RAW, 'Respuesta JSON de la API externa', VALUE_OPTIONAL),
        ]);
    }
}
