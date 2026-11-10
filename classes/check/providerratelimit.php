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

namespace local_socialcert\check;

use core\check\check;
use core\check\result;
use local_socialcert\local\provider_limit;

/**
 * Security check: the AI assistant must not be able to spend credits without a usage limit.
 *
 * The plugin applies no usage limit of its own, so the check reads the per plugin limit of the
 * Datacurso AI provider. It reports an error when the assistant is on and that limit is off, and
 * is listed in Site administration > Reports > Security checks through lib.php.
 *
 * @package    local_socialcert
 * @copyright  2026 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class providerratelimit extends check {
    /**
     * Name of the check in the report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('checkproviderratelimit', 'local_socialcert');
    }

    /**
     * Link to the provider page where the limit is enabled.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            provider_limit::get_configuration_url(),
            get_string('providerlimit_configure', 'local_socialcert')
        );
    }

    /**
     * Result of the check.
     *
     * @return result
     */
    public function get_result(): result {
        $details = get_string('checkproviderratelimit_details', 'local_socialcert');

        $assistanton = !empty(get_config('local_socialcert', 'aiassistant_enabled'))
            && \aiprovider_datacurso\local\plugin_settings::is_enabled('local_socialcert');
        if (!$assistanton) {
            return new result(result::OK, get_string('checkproviderratelimit_assistantoff', 'local_socialcert'), $details);
        }

        $limit = provider_limit::get();
        if ($limit === null) {
            return new result(result::ERROR, get_string('checkproviderratelimit_error', 'local_socialcert'), $details);
        }

        return new result(
            result::OK,
            get_string('providerlimit_active', 'local_socialcert', provider_limit::describe($limit)),
            $details
        );
    }
}
