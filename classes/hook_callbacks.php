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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use core\hook\output\before_standard_html_head_generation;
use core\hook\output\before_footer_html_generation;

/**
 * Defines plugin hook callbacks for local_socialcert.
 *
 * Contains static methods that respond to Moodle’s hook system in order
 * to inject custom CSS, JavaScript, and HTML into the certificate view page.
 *
 * Specifically:
 * - Adds a custom stylesheet before the <head> tag is rendered.
 * - Injects the rendered panel template before the page footer.
 *
 * @package    local_socialcert
 * @category   output
 */
class hook_callbacks {
    /**
     * Adds custom CSS and JS requirements before the standard <head> section is generated.
     *
     * This callback is triggered via the before_standard_html_head_generation hook.
     * It ensures that the plugin stylesheet is loaded only on the custom certificate
     * view page for logged-in, non-guest users.
     *
     * @param before_standard_html_head_generation $hook The hook object for the event.
     * @return void
     */
    public static function before_standard_html_head_generation(
        before_standard_html_head_generation $hook
    ): void {
        global $PAGE;

        if (
            $PAGE->pagetype !== 'mod-customcert-view' ||
            empty($PAGE->cm->id) ||
            !isloggedin() ||
            isguestuser()
        ) {
            return;
        }

        $PAGE->requires->css('/local/socialcert/styles.css');
    }

    /**
     * Injects custom HTML into the footer area of the certificate view page.
     *
     * Triggered by the before_footer_html_generation hook. Renders the
     * local_socialcert main panel using a Mustache template and inserts
     * it into the page output. Also loads the required JavaScript module.
     *
     * @param before_footer_html_generation $hook The hook object for the event.
     * @return void
     */
    public static function before_footer_html_generation(
        before_footer_html_generation $hook
    ): void {
        global $PAGE, $OUTPUT;

        // The page type is validated before touching $PAGE->cm: reading the course module first
        // dereferences a null object on every page of the site that has no module associated,
        // which emits a debugging notice everywhere while developer debugging is enabled.
        if (
            $PAGE->pagetype !== 'mod-customcert-view' ||
            empty($PAGE->cm->id) ||
            !isloggedin() ||
            isguestuser()
        ) {
            return;
        }

        $cmid = (int) $PAGE->cm->id;

        $panel = new \local_socialcert\output\main_panel(cmid: $cmid);
        $context = $panel->export_for_template(output: $OUTPUT);
        $html  = $OUTPUT->render_from_template(
            'local_socialcert/main',
            $context
        );

        $hook->add_html($html);

        $PAGE->requires->js_call_amd('local_socialcert/actions', 'init', [
            'cmid' => $cmid,
        ]);
    }
}
