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
 * Guard for the tests that build a custom certificate activity.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\fixtures;

/**
 * Skips the current test when the mod_customcert activity module is not installed.
 *
 * The plugin only works on top of mod_customcert, which is not part of the Moodle core
 * distribution. When the module is absent, the data generator fails with "Component
 * mod_customcert does not support generators yet" before any assertion runs; this guard turns
 * that error into an explicit skip, the same way the tests skip when aiprovider_datacurso is
 * not installed.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait customcert_dependency_trait {
    /**
     * Skip the current test when mod_customcert is not installed on this site.
     *
     * @return void
     */
    protected function require_customcert(): void {
        if (\core_component::get_component_directory('mod_customcert') === null) {
            $this->markTestSkipped('The mod_customcert activity is not installed on this site.');
        }
    }
}
