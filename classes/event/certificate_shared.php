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
 * Event fired when a user shares the credential of a certificate on a social network.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\event;

/**
 * The user opened the share form of a social network for the credential of a certificate.
 *
 * The click happens in the browser, so the event is recorded by the
 * local_socialcert_log_share web service, which revalidates that the credential really was
 * shareable before writing anything to the log.
 *
 * The event is declared as a read operation because nothing is created, updated or deleted in
 * Moodle: the credential data travels to the social network and the certificate issue of
 * mod_customcert stays untouched.
 *
 * @package    local_socialcert
 * @category   event
 */
class certificate_shared extends \core\event\base {
    /**
     * Initialises the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the localized event name.
     *
     * @return string The name of the event.
     */
    public static function get_name() {
        return get_string('eventcertificateshared', 'local_socialcert');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string A detailed description of the event.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' shared the credential '{$this->other['certid']}' of the " .
            "certificate activity with course module id '{$this->contextinstanceid}' on " .
            "'{$this->other['network']}'.";
    }

    /**
     * Returns the URL relevant to the event.
     *
     * @return \moodle_url URL of the certificate activity the credential belongs to.
     */
    public function get_url() {
        return new \moodle_url('/mod/customcert/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Validates the custom data of the event.
     *
     * @return void
     * @throws \coding_exception When the context or the custom data are not the documented ones.
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel !== CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE: the credential belongs to an activity.');
        }

        if (!isset($this->other['certid'])) {
            throw new \coding_exception("The 'certid' value must be set in other.");
        }

        if (!isset($this->other['network'])) {
            throw new \coding_exception("The 'network' value must be set in other.");
        }
    }

    /**
     * Mapping of the custom data for the restore of course logs.
     *
     * The custom data carries no identifier of the site: the credential code is the public code of
     * the issue and the network is the name of the destination, so there is nothing to map.
     *
     * @return array Empty mapping.
     */
    public static function get_other_mapping() {
        return [];
    }
}
