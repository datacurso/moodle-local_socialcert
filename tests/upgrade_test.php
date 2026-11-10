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

use xmldb_field;
use xmldb_index;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/provider_dependency_trait.php');

/**
 * Tests for the multi-tenancy schema and role set up done on install and upgrade, and for the upgrade steps.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class upgrade_test extends \advanced_testcase {
    use \local_socialcert\tests\requires_workplace;
    use \local_socialcert\fixtures\provider_dependency_trait;

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
     * The tenant configuration table exists with its fields and the (tenantid, name) unique key.
     */
    public function test_tenant_config_table_is_installed(): void {
        global $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_socialcert_tenant_config');
        $this->assertTrue($dbman->table_exists($table));
        foreach (['id', 'tenantid', 'name', 'value'] as $fieldname) {
            $this->assertTrue($dbman->field_exists($table, new xmldb_field($fieldname)), $fieldname);
        }

        // Unique keys are backed by a unique index on the same columns.
        $this->assertTrue($dbman->index_exists($table, new xmldb_index('tenantid_name', XMLDB_INDEX_UNIQUE, ['tenantid', 'name'])));
    }

    /**
     * The plugin install hook granted the tenant administrator role all plugin capabilities.
     */
    public function test_tenant_admin_role_holds_plugin_capabilities_after_install(): void {
        global $DB;
        $this->require_tool_tenant();

        $roleid = \tool_tenant\manager::get_tenant_admin_role();
        $allowed = $DB->get_fieldset_select(
            'role_capabilities',
            'capability',
            'roleid = :roleid AND contextid = :contextid AND permission = :permission',
            ['roleid' => $roleid, 'contextid' => \context_system::instance()->id, 'permission' => CAP_ALLOW]
        );

        $expected = ['local/socialcert:managetenantsettings'];
        foreach ($expected as $capability) {
            $this->assertContains($capability, $allowed);
        }
    }

    /**
     * A fresh install keeps no settings outside a tenant and creates no tenant rows: only the plugin version is stored.
     */
    public function test_fresh_install_stores_no_site_settings(): void {
        global $DB;

        $this->assertSame(['version'], array_keys((array) get_config('local_socialcert')));
        $this->assertSame(0, $DB->count_records('local_socialcert_tenant_config'));
    }
    /**
     * The upgrade renames the per tenant enableai value to aiassistant_enabled and keeps every tenant's choice.
     *
     * A tenant that already stores aiassistant_enabled keeps its own value: it is never overwritten.
     *
     * @return void
     */
    public function test_upgrade_renames_the_tenant_ai_assistant_setting_without_overwriting(): void {
        global $DB;
        $this->resetAfterTest();

        $table = 'local_socialcert_tenant_config';
        $DB->insert_record($table, (object) ['tenantid' => 11, 'name' => 'enableai', 'value' => '0']);
        $DB->insert_record($table, (object) ['tenantid' => 12, 'name' => 'enableai', 'value' => '1']);
        $DB->insert_record($table, (object) ['tenantid' => 13, 'name' => 'enableai', 'value' => '1']);
        $DB->insert_record($table, (object) ['tenantid' => 13, 'name' => 'aiassistant_enabled', 'value' => '0']);
        set_config('version', 2026110901, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110901));

        $this->assertSame('2026110902', get_config('local_socialcert', 'version'));
        $this->assertSame(0, $DB->count_records($table, ['name' => 'enableai']));
        $this->assertSame('0', local\tenant_config::get_raw('aiassistant_enabled', 11));
        $this->assertSame('1', local\tenant_config::get_raw('aiassistant_enabled', 12));
        $this->assertSame('0', local\tenant_config::get_raw('aiassistant_enabled', 13));
        $this->assertSame(1, $DB->count_records($table, ['tenantid' => 13, 'name' => 'aiassistant_enabled']));
        $this->assertFalse(local\ai_assistant::is_enabled(11));
        $this->assertTrue(local\ai_assistant::is_enabled(12));
        $this->assertFalse(local\ai_assistant::is_enabled(13));
        // A tenant that never stored the setting keeps the assistant.
        $this->assertTrue(local\ai_assistant::is_enabled(14));
    }

    /**
     * A site value of enableai left behind is handed to the default tenant as aiassistant_enabled and removed.
     *
     * @return void
     */
    public function test_upgrade_moves_the_site_enableai_value_to_the_default_tenant(): void {
        $this->resetAfterTest();

        $defaulttenantid = local\tenancy::get_default_tenant_id();
        set_config('enableai', '0', 'local_socialcert');
        set_config('version', 2026110901, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110901));

        $this->assertFalse(get_config('local_socialcert', 'enableai'));
        $this->assertSame('0', local\tenant_config::get_raw('aiassistant_enabled', $defaulttenantid));
        $this->assertNull(local\tenant_config::get_raw('enableai', $defaulttenantid));
    }

    /**
     * The site value of enableai never overwrites a value the default tenant already stores.
     *
     * @return void
     */
    public function test_upgrade_keeps_the_value_of_the_default_tenant_over_the_site_value(): void {
        $this->resetAfterTest();

        $defaulttenantid = local\tenancy::get_default_tenant_id();
        local\tenant_config::set('aiassistant_enabled', '1', $defaulttenantid);
        set_config('enableai', '0', 'local_socialcert');
        set_config('version', 2026110901, 'local_socialcert');

        $this->assertTrue(xmldb_local_socialcert_upgrade(2026110901));

        $this->assertFalse(get_config('local_socialcert', 'enableai'));
        $this->assertSame('1', local\tenant_config::get_raw('aiassistant_enabled', $defaulttenantid));
    }

    /**
     * Moodle Workplace: the upgrade leaves the switch of the Datacurso AI provider untouched.
     *
     * Turning the AI assistant off for a tenant never disables the whole plugin for that tenant.
     *
     * @return void
     */
    public function test_upgrade_leaves_the_provider_switch_untouched(): void {
        global $DB;

        $this->require_tool_tenant();
        $this->require_ai_provider();
        $this->resetAfterTest();

        $tenantid = (int) $this->getDataGenerator()->get_plugin_generator('tool_tenant')->create_tenant()->id;
        $DB->insert_record('local_socialcert_tenant_config', (object) [
            'tenantid' => $tenantid,
            'name' => 'enableai',
            'value' => '0',
        ]);
        set_config('version', 2026110901, 'local_socialcert');

        xmldb_local_socialcert_upgrade(2026110901);

        $this->assertFalse(local\ai_assistant::is_enabled($tenantid));
        $this->assertTrue(\aiprovider_datacurso\local\plugin_settings::is_enabled('local_socialcert', $tenantid));
        $this->assertNull(
            \aiprovider_datacurso\local\tenant_config::get_stored(
                'aiprovider_datacurso',
                $tenantid,
                \aiprovider_datacurso\local\plugin_settings::CONFIG_PREFIX . 'local_socialcert'
            )
        );
    }
}
