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
 * Site switch of the AI assistant of the share panel.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\local;

/**
 * Reads the local_socialcert/aiassistant_enabled setting.
 *
 * The setting only switches the AI assistant (the card that drafts the LinkedIn post) off; the share
 * option stays available. It is not the switch of the Datacurso AI provider, which disables the whole
 * plugin. It is deliberately not named 'enableai': the provider reads that former setting as the
 * legacy state of the whole plugin.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_assistant {
    /** @var string Name of the setting in the configuration of the plugin. */
    public const SETTING = 'aiassistant_enabled';

    /**
     * Whether the AI assistant is enabled on this site.
     *
     * A setting that was never stored (false) or stored empty is treated as enabled, so a site
     * keeps the assistant until an administrator turns it off.
     *
     * @return bool True when the assistant may be offered and used.
     */
    public static function is_enabled(): bool {
        $value = get_config('local_socialcert', self::SETTING);

        if ($value === false || $value === '') {
            return true;
        }

        return (bool) (int) $value;
    }
}
