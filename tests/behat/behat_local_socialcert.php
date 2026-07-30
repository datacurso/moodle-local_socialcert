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
 * Step definitions for local_socialcert acceptance tests.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2025 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Tester\Exception\PendingException;

/**
 * Step definitions for local_socialcert acceptance tests.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2025 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_socialcert extends behat_base {
    /**
     * Marks the current scenario as pending, with an explicit reason.
     *
     * Used by scenarios that document behaviour required by the test case definition which
     * either the plugin does not implement yet, or which cannot be exercised without an
     * external dependency (the Datacurso AI service and credits manager).
     *
     * Behat reports the scenario as "pending", so it is never counted as passed (no false
     * green) and, with the default non strict interpretation, it does not fail the run
     * either. The remaining steps of the scenario are skipped.
     *
     * phpcs:ignore
     * @Given /^this scenario is pending because "(?P<reason_string>(?:[^"]|\\")*)"$/
     * @param string $reason Why the scenario cannot be executed yet.
     */
    public function this_scenario_is_pending_because(string $reason): void {
        throw new PendingException($reason);
    }
}
