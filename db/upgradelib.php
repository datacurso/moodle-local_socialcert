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
 * Helpers used by the upgrade steps of local_socialcert.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Renames the former per tenant "Enable AI" setting (enableai) to aiassistant_enabled.
 *
 * The setting only switches the AI assistant off for a tenant, so it must not keep the enableai name:
 * the Datacurso AI provider reads that name as the legacy state of the whole plugin. Every tenant
 * keeps its own value and a value already stored under the new name is never overwritten. A site
 * value of enableai left behind is handed to the default tenant first, exactly like the 2026100602
 * step did, unless the default tenant already stores a value of its own, and is then removed. The
 * switch of the Datacurso AI provider is never touched, so the plugin stays enabled by default.
 *
 * @return void
 */
function local_socialcert_upgrade_rename_ai_assistant_switch(): void {
    global $DB;

    $table = \local_socialcert\local\tenant_config::TABLE;
    $oldname = 'enableai';
    $newname = \local_socialcert\local\ai_assistant::SETTING;

    $sitevalue = get_config('local_socialcert', $oldname);
    if ($sitevalue !== false) {
        $defaulttenantid = \local_socialcert\local\tenancy::get_default_tenant_id();
        $hasvalue = $DB->record_exists_select(
            $table,
            'tenantid = :tenantid AND (name = :oldname OR name = :newname)',
            ['tenantid' => $defaulttenantid, 'oldname' => $oldname, 'newname' => $newname]
        );
        if (!$hasvalue) {
            $DB->insert_record($table, (object) [
                'tenantid' => $defaulttenantid,
                'name' => $oldname,
                'value' => (string) $sitevalue,
            ]);
        }
        unset_config($oldname, 'local_socialcert');
    }

    $records = $DB->get_records($table, ['name' => $oldname]);
    foreach ($records as $record) {
        $exists = $DB->record_exists($table, ['tenantid' => $record->tenantid, 'name' => $newname]);
        if (!$exists) {
            $DB->insert_record($table, (object) [
                'tenantid' => $record->tenantid,
                'name' => $newname,
                'value' => $record->value,
            ]);
        }
    }
    $DB->delete_records($table, ['name' => $oldname]);

    \local_socialcert\local\tenant_config::reset_cache();
}
