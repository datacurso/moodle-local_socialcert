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
 * Event observers of local_socialcert.
 *
 * @package    local_socialcert
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Removes the configuration of a deleted tenant.
     *
     * Other tenants are never touched.
     *
     * @param \tool_tenant\event\tenant_deleted $event The tenant_deleted event.
     * @return void
     */
    public static function tenant_deleted(\tool_tenant\event\tenant_deleted $event): void {
        $tenantid = (int) $event->objectid;
        if ($tenantid <= 0) {
            // Not a real tenant: nothing can belong to it.
            return;
        }

        local\tenant_config::delete_all($tenantid);
    }
}
