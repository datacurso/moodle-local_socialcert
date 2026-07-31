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

use cm_info;
use core\context\module as context_module;
use core\hook\output\before_footer_html_generation;
use mod_customcert\certificate;

/**
 * Defines plugin hook callbacks for local_socialcert.
 *
 * Contains the static method that responds to Moodle's hook system in order to inject the share
 * panel into the custom certificate view page.
 *
 * The stylesheet of the panel is deliberately not declared here: Moodle adds the styles.css of
 * every plugin to the CSS of the theme by itself (see \core\output\theme_config::get_css_files()),
 * so an explicit declaration would be redundant. The plugin used to register a callback for a
 * hypothetical before_standard_html_head_generation hook, which does not exist in Moodle 4.5
 * (the real one is before_standard_head_html_generation) and therefore never ran.
 *
 * @package    local_socialcert
 * @category   output
 */
class hook_callbacks {
    /**
     * Injects the share panel into the footer area of the certificate view page.
     *
     * Triggered by the before_footer_html_generation hook. Renders the local_socialcert main panel
     * using a Mustache template and inserts it into the page output. Also loads the required
     * JavaScript module.
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
        $context = context_module::instance($cmid);

        // Only the users who may receive the certificate can ever have something to share, so the
        // panel belongs to them alone. Teachers and managers open the same view to read the issues
        // report and used to get the panel in its error state underneath it, which was pure noise.
        if (!has_capability('mod/customcert:receiveissue', $context)) {
            return;
        }

        // The capability of the plugin is what lets an administrator take the panel away from a role
        // without touching mod_customcert. Its default archetype is the student, which is the only
        // archetype of mod/customcert:receiveissue, so by default this check changes nothing.
        if (!has_capability('local/socialcert:viewsharepanel', $context)) {
            return;
        }

        // Sharing makes no sense on the intermediate pages mod_customcert serves from this very
        // same URL and page type, so the panel is kept out of them.
        if (self::is_intermediate_page($PAGE->cm, $context)) {
            return;
        }

        $panel = new \local_socialcert\output\main_panel(cmid: $cmid);
        $templatecontext = $panel->export_for_template(output: $OUTPUT);
        $html  = $OUTPUT->render_from_template(
            'local_socialcert/main',
            $templatecontext
        );

        $hook->add_html($html);

        $PAGE->requires->js_call_amd('local_socialcert/actions', 'init', [
            'cmid' => $cmid,
        ]);
    }

    /**
     * Whether mod/customcert/view.php is serving one of its intermediate pages.
     *
     * Both of them keep the mod-customcert-view page type and the course module of the activity, so
     * they can only be told apart by the same conditions view.php itself evaluates:
     *
     * - The issue deletion confirmation is shown while the deleteissue parameter is present, the
     *   deletion has not been confirmed yet and the user may manage the activity.
     * - The required time notice replaces the whole page while the activity demands a minimum time
     *   in the course, the user cannot manage the activity and has not spent that time yet.
     *
     * @param cm_info $cm Course module of the certificate activity.
     * @param context_module $context Context of the course module.
     * @return bool True while the page being rendered is an intermediate one.
     */
    private static function is_intermediate_page(cm_info $cm, context_module $context): bool {
        global $DB;

        $canmanage = has_capability('mod/customcert:manage', $context);

        // The deletion confirmation is only reachable by a user who can manage the activity. Note
        // that the receiveissue check above already keeps teachers and managers out of the panel;
        // this branch is what covers the site administrator, who holds every capability.
        if (
            $canmanage &&
            optional_param('deleteissue', 0, PARAM_INT) &&
            !optional_param('confirm', false, PARAM_BOOL)
        ) {
            return true;
        }

        if ($canmanage) {
            return false;
        }

        $customcert = $DB->get_record('customcert', ['id' => $cm->instance], 'id, requiredtime', IGNORE_MISSING);
        if (!$customcert || empty($customcert->requiredtime)) {
            return false;
        }

        // The time spent in the course is only read when the activity really demands one, so the
        // log queries of get_course_time() are not paid for by every certificate of the site.
        return certificate::get_course_time((int) $cm->course) < ((int) $customcert->requiredtime * MINSECS);
    }
}
