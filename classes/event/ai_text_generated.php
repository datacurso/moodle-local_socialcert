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
 * Event fired when the AI assistant drafts the text of a post for a user.
 *
 * @package     local_socialcert
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\event;

/**
 * The AI assistant returned a draft post text to the user.
 *
 * The event is only fired by \local_socialcert\external\ai_helper::execute() after the AI service
 * has answered, so the log records the generations that really consumed credits and never the
 * requests the plugin or the provider rejected.
 *
 * It is declared as a read operation because the draft is not stored anywhere in Moodle: it is
 * returned to the browser of the user who asked for it.
 *
 * @package    local_socialcert
 * @category   event
 */
class ai_text_generated extends \core\event\base {
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
        return get_string('eventaitextgenerated', 'local_socialcert');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string A detailed description of the event.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' generated with the AI assistant the text of a " .
            "'{$this->other['socialmedia']}' post about the certificate of the activity with course " .
            "module id '{$this->contextinstanceid}'.";
    }

    /**
     * Returns the URL relevant to the event.
     *
     * @return \moodle_url URL of the certificate activity the generation was requested from.
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
            throw new \coding_exception('Context level must be CONTEXT_MODULE: the generation belongs to an activity.');
        }

        if (!isset($this->other['socialmedia'])) {
            throw new \coding_exception("The 'socialmedia' value must be set in other.");
        }
    }

    /**
     * Mapping of the custom data for the restore of course logs.
     *
     * The custom data only carries the name of the social network the post was written for, so
     * there is no identifier of the site to map.
     *
     * @return array Empty mapping.
     */
    public static function get_other_mapping() {
        return [];
    }
}
