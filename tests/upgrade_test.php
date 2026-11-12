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

namespace local_socialcert;

/**
 * Tests for the upgrade steps of the plugin.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_socialcert_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Load the upgrade libraries the tests use.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/socialcert/db/upgrade.php');
    }

    /**
     * S7: the upgrade removes the former "Enable AI" setting, whose state now lives in the provider.
     *
     * @return void
     */
    public function test_upgrade_removes_the_former_enableai_setting(): void {
        $this->resetAfterTest();

        set_config('enableai', '1', 'local_socialcert');
        set_config('version', 2026103000, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026103000));

        $this->assertFalse(get_config('local_socialcert', 'enableai'));
        $this->assertSame('2026111100', get_config('local_socialcert', 'version'));
    }

    /**
     * The upgrade enables the AI assistant setting on a site that never stored it.
     *
     * @return void
     */
    public function test_upgrade_enables_the_ai_assistant_setting_when_unset(): void {
        $this->resetAfterTest();

        unset_config('aiassistant_enabled', 'local_socialcert');
        set_config('version', 2026110400, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110400));

        $this->assertSame('1', get_config('local_socialcert', 'aiassistant_enabled'));
        $this->assertSame('2026111100', get_config('local_socialcert', 'version'));
    }

    /**
     * The upgrade keeps the AI assistant setting an administrator already turned off.
     *
     * @return void
     */
    public function test_upgrade_keeps_a_stored_ai_assistant_setting(): void {
        $this->resetAfterTest();

        set_config('aiassistant_enabled', '0', 'local_socialcert');
        set_config('version', 2026110400, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110400));

        $this->assertSame('0', get_config('local_socialcert', 'aiassistant_enabled'));
    }

    /**
     * 1.1.8: the upgrade deletes the stored value of the removed "AI requests limit per user per hour"
     * setting; the usage limit is the per plugin limit of the Datacurso AI provider.
     *
     * @return void
     */
    public function test_upgrade_removes_the_former_airatelimit_setting(): void {
        $this->resetAfterTest();

        set_config('airatelimit', '10', 'local_socialcert');
        set_config('version', 2026110900, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110900));

        $this->assertFalse(get_config('local_socialcert', 'airatelimit'));
        $this->assertSame('2026111100', get_config('local_socialcert', 'version'));
    }
}
