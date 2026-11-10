<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_socialcert\local\admin;

use local_socialcert\local\provider_limit;

/**
 * Settings page notice that tells whether the provider usage limit protects the AI credits.
 *
 * It is a description, not a stored setting: it renders the current state of the provider limit
 * every time the page is shown, red with a link to the provider configuration when the limit is
 * off, green with the limit when it is on, and an informational note while the assistant is off
 * (no credit can be spent, so neither alarm nor approval applies). It sits right above the switch
 * of the assistant, so an administrator cannot enable the assistant without seeing it. The
 * security check of the plugin reports the same three states.
 *
 * @package    local_socialcert
 * @copyright  2026 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_limit_notice extends \admin_setting_description {
    /**
     * Declares the notice under the plugin settings.
     */
    public function __construct() {
        parent::__construct(
            'local_socialcert/providerlimit_notice',
            get_string('providerlimit_notice', 'local_socialcert'),
            ''
        );
    }

    /**
     * Renders the row with the alert that matches the current state of the provider limit.
     *
     * @param mixed $data Ignored: the notice stores nothing.
     * @param string $query Search query of the administration, ignored.
     * @return string HTML of the settings row.
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $limit = provider_limit::get();
        $alert = [
            'assistantoff' => !provider_limit::is_assistant_on(),
            'active' => $limit !== null,
            'configurl' => provider_limit::get_configuration_url()->out(false),
        ];
        if ($limit !== null) {
            $described = provider_limit::describe($limit);
            $alert['limit'] = $described->limit;
            $alert['window'] = $described->window;
        }

        $context = new \stdClass();
        $context->title = $this->visiblename;
        $context->description = $OUTPUT->render_from_template('local_socialcert/provider_limit_notice', $alert);

        return $OUTPUT->render_from_template('core_admin/setting_description', $context);
    }
}
