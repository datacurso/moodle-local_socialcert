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

namespace local_socialcert\local;

/**
 * Reads the usage limit the Datacurso AI provider applies to this plugin.
 *
 * Since 1.1.8 the plugin applies no usage limit of its own: every generation of the AI assistant
 * spends credits of the provider licence, and the only ceiling is the per plugin limit of the
 * provider, which an administrator enables in the provider configuration and the provider service
 * enforces per user. The settings page and the security check read that limit through this class
 * so both report the same state, taken from the provider API and never from its raw configuration.
 *
 * @package    local_socialcert
 * @copyright  2026 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_limit {
    /** @var string Service identifier of this plugin in the provider. */
    public const SERVICE = 'local_socialcert';

    /**
     * Returns the active limit of the provider for this plugin, or null when none applies.
     *
     * The provider answers with an empty header map when the limit is disabled, has no credits or
     * no window, or is otherwise unusable: every one of those cases means the credits are not
     * protected, so they are all reported as "no limit".
     *
     * @return array{limit: int, windowseconds: int}|null Credits per user and window, or null.
     */
    public static function get(): ?array {
        if (!class_exists(\aiprovider_datacurso\local\ratelimiter::class)) {
            return null;
        }

        $headers = (new \aiprovider_datacurso\local\ratelimiter())->get_rate_limit_header_map(self::SERVICE);
        if (empty($headers['X-RateLimit-Limit']) || empty($headers['X-RateLimit-WindowSeconds'])) {
            return null;
        }

        return [
            'limit' => (int) $headers['X-RateLimit-Limit'],
            'windowseconds' => (int) $headers['X-RateLimit-WindowSeconds'],
        ];
    }

    /**
     * Tells whether the assistant can spend credits at all on this site.
     *
     * Two switches must be on: the plugin's own assistant switch, read through the same helper the
     * web service uses (an unset value counts as on), and the switch of the plugin in the provider.
     * Without the provider class nothing can be generated, so the answer is false.
     *
     * @return bool
     */
    public static function is_assistant_on(): bool {
        if (!class_exists(\aiprovider_datacurso\local\plugin_settings::class)) {
            return false;
        }

        return ai_assistant::is_enabled()
            && \aiprovider_datacurso\local\plugin_settings::is_enabled(self::SERVICE);
    }

    /**
     * Returns the limit as the placeholders of the strings that name it.
     *
     * @param array{limit: int, windowseconds: int} $limit An active limit, as returned by get().
     * @return \stdClass Object with limit (int) and window (localized length, e.g. "1 hour").
     */
    public static function describe(array $limit): \stdClass {
        return (object) [
            'limit' => $limit['limit'],
            'window' => format_time($limit['windowseconds']),
        ];
    }

    /**
     * Page of the provider where an administrator enables the limit.
     *
     * @return \moodle_url
     */
    public static function get_configuration_url(): \moodle_url {
        return new \moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'config']);
    }
}
