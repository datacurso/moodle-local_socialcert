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

use core\check\result;

/**
 * Tests for the security check that reports the usage limit of the AI assistant.
 *
 * Since 1.1.8 the plugin applies no usage limit of its own: the limit is the per plugin limit of
 * the Datacurso AI provider, which is off until an administrator enables it. The check makes that
 * state visible in Site administration > Reports > Security checks.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\check\providerratelimit
 */
final class providerratelimit_test extends \advanced_testcase {
    /**
     * Skip when the provider the check reads is not installed on this site.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (\core_component::get_component_directory('aiprovider_datacurso') === null) {
            $this->markTestSkipped('The aiprovider_datacurso plugin is not installed on this site.');
        }
    }

    /**
     * Store the per plugin limit of the provider for this plugin.
     *
     * @param int $enabled 1 to enable the limit, 0 to disable it.
     * @param int $limit Credits allowed per window.
     * @param int $hours Length of the window in hours.
     * @return void
     */
    private function set_provider_limit(int $enabled, int $limit = 3, int $hours = 1): void {
        set_config('ratelimit_local_socialcert_enable', $enabled, 'aiprovider_datacurso');
        set_config('ratelimit_local_socialcert_limit', $limit, 'aiprovider_datacurso');
        set_config(
            'ratelimit_local_socialcert_window',
            json_encode(['value' => $hours, 'unit' => 'hours']),
            'aiprovider_datacurso'
        );
    }

    /**
     * The plugin registers the check in the security report of the site.
     */
    public function test_the_check_is_listed_among_the_security_checks(): void {
        $ours = array_values(array_filter(
            \core\check\manager::get_security_checks(),
            static fn(\core\check\check $check): bool => $check instanceof providerratelimit
        ));

        $this->assertCount(1, $ours, 'The plugin must register exactly one security check.');
        $this->assertSame('local_socialcert', $ours[0]->get_component());
        $this->assertNotEmpty($ours[0]->get_name());
    }

    /**
     * Assistant on and provider limit off: the site is spending credits without any limit.
     */
    public function test_error_when_the_assistant_is_on_and_the_provider_limit_is_off(): void {
        set_config('aiassistant_enabled', 1, 'local_socialcert');
        $this->set_provider_limit(0);

        $check = new providerratelimit();
        $result = $check->get_result();

        $this->assertSame(result::ERROR, $result->get_status());
        $this->assertNotEmpty($result->get_summary());
        $this->assertNotEmpty($result->get_details());

        $link = $check->get_action_link();
        $this->assertInstanceOf(\action_link::class, $link);
        $this->assertStringContainsString('/ai/provider/datacurso/admin/report_sections.php', $link->url->out(false));
        $this->assertSame('config', $link->url->get_param('tab'));
    }

    /**
     * A limit that the provider cannot apply (zero credits) counts as no limit.
     */
    public function test_error_when_the_provider_limit_is_enabled_with_zero_credits(): void {
        set_config('aiassistant_enabled', 1, 'local_socialcert');
        $this->set_provider_limit(1, 0);

        $this->assertSame(result::ERROR, (new providerratelimit())->get_result()->get_status());
    }

    /**
     * Assistant on and provider limit on: the summary names the limit and the window.
     */
    public function test_ok_when_the_provider_limit_is_on(): void {
        set_config('aiassistant_enabled', 1, 'local_socialcert');
        $this->set_provider_limit(1, 3, 2);

        $result = (new providerratelimit())->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $this->assertStringContainsString('3', $result->get_summary());
        $this->assertStringContainsString(format_time(2 * HOURSECS), $result->get_summary());
    }

    /**
     * A site that never stored the assistant switch has it on (the plugin's own rule), so the check
     * must not read an unset value as "off".
     */
    public function test_error_when_the_assistant_setting_was_never_stored(): void {
        unset_config('aiassistant_enabled', 'local_socialcert');
        $this->set_provider_limit(0);

        $this->assertSame(result::ERROR, (new providerratelimit())->get_result()->get_status());
    }

    /**
     * The provider disabled the whole plugin: no generation can happen, whatever the switch says.
     */
    public function test_ok_when_the_provider_disabled_the_plugin(): void {
        set_config('aiassistant_enabled', 1, 'local_socialcert');
        set_config('plugin_enabled_local_socialcert', 0, 'aiprovider_datacurso');
        $this->set_provider_limit(0);

        $this->assertSame(result::OK, (new providerratelimit())->get_result()->get_status());
    }

    /**
     * Assistant off: no generation can spend credits, whatever the provider limit is.
     */
    public function test_ok_when_the_assistant_is_off(): void {
        set_config('aiassistant_enabled', 0, 'local_socialcert');
        $this->set_provider_limit(0);

        $result = (new providerratelimit())->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $this->assertNotEmpty($result->get_summary());
    }
}
