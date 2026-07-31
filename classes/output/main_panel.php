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
 * Renderable helper class for the main social certificate panel.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert\output;

use renderable;
use templatable;
use renderer_base;
use context_course;
use context_module;
use core\context;
use core_user;
use moodle_url;

/**
 * Main panel renderer helper.
 *
 * Provides data preparation and rendering context for the main panel
 * of the social certificate feature. This class collects user, course,
 * and certificate information for use in a Mustache template.
 *
 * Implements {@see renderable} and {@see templatable}.
 *
 * @package    local_socialcert
 * @category   output
 */
class main_panel implements renderable, templatable {
    /** @var int */
    protected $cmid;

    /**
     * Class constructor.
     *
     * @param int $cmid The course module ID.
     */
    public function __construct(int $cmid) {
        $this->cmid = $cmid;
    }

    /**
     * Exports data for use in a Mustache template.
     *
     * Retrieves contextual data about the current user, course, and certificate.
     * Builds an associative array with all the variables needed by the renderer.
     *
     * @param renderer_base $output Renderer instance for template rendering.
     * @return array Template-ready data for the mustache renderer.
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        // The state is calculated in a single place and shared with the web service, so the panel
        // rendered on the server and the panel refreshed in the browser can never disagree.
        $state = self::get_share_state($this->cmid, (int) $USER->id);

        $shareurl = $state['shareurl'];

        // The template uses this flag to render the disabled state, so it is true when the panel
        // cannot offer the share action: no issue of the session user, or no organization ID.
        $issued = $shareurl === null;

        // The assistant subcontext is exported by the same method the web service uses, so the
        // card added by the browser is built from exactly the same data as the one rendered here.
        // It is merged unconditionally, exactly as before: the enableai flag is what gates the
        // rendering of the card in the template, never the presence of these keys.
        $aicard = self::get_ai_card_context($this->cmid, $state);

        return array_merge($aicard, [
            'buttonid'          => 'btn-normal',
            'imageid'           => 'ai-image',
            'certid'            => $state['certid'],
            'datanetwork'       => $state['datanetwork'],
            // The AI assistant demands an issued certificate, exactly like the share button, so no
            // generation can be requested (and no credits spent) without a certificate.
            'enableai'          => $state['enableai'],
            'issued'            => $issued,
            'shareurl'          => $shareurl,
            'verifyurl'         => $state['verifyurl'],
            'verifywarning'     => $state['verifywarning'],
            'buttonlabel'       => get_string('linkcertbuttontext', 'local_socialcert'),
            'buttonlabelshare'  => get_string('buttonlabelshare', 'local_socialcert'),
            'intro'             => get_string('shareinstruction', 'local_socialcert'),
            'linktext'          => get_string('linktext', 'local_socialcert'),
            'popupblocked'      => get_string('popupblocked', 'local_socialcert'),
            'sharecompleted'    => get_string('sharecompleted', 'local_socialcert'),
            'sharesubtitle'     => get_string('sharesubtitle', 'local_socialcert'),
            'sharetitle'        => get_string('sharetitle', 'local_socialcert'),
            'whatsharelabel'    => get_string('whatsharelabel', 'local_socialcert'),
            // The notice of the disabled state tells the two situations apart. The panel is only
            // rendered for users who can receive the certificate of the activity (see
            // \local_socialcert\hook_callbacks::before_footer_html_generation()), so a missing issue
            // is always actionable and the notice names the action that solves it; with the issue
            // already recorded the only thing left to configure is the LinkedIn organization ID,
            // which is not something the user can do. The former single 'certerror' string is kept
            // in the language packs because the shipped translations still declare it, but no code
            // path uses it any more.
            'certerror'         => $state['hasissue']
                ? get_string('certerrornoorg', 'local_socialcert')
                : get_string('certerrordownload', 'local_socialcert'),
        ]);
    }

    /**
     * Builds the context the local_socialcert/ai_card template needs.
     *
     * The card is a template of its own because it has two renderers: the server, through the
     * enableai section of local_socialcert/main, and the browser, which renders the very same
     * template with the context returned by local_socialcert_get_share_state as soon as the
     * assistant becomes available. Exporting the subcontext here is what keeps both paths on the
     * same data and the markup in a single place instead of being rebuilt in JavaScript.
     *
     * This method reports data only: whether the card may be rendered at all is decided by the
     * enableai flag of {@see self::get_share_state()}, and the generation itself is revalidated
     * server side by {@see \local_socialcert\external\ai_helper::execute()}.
     *
     * @param int $cmid Course module ID of the custom certificate activity.
     * @param array $state State returned by {@see self::get_share_state()} for the same activity.
     * @return array Context of the assistant card, ready for the Mustache template.
     */
    public static function get_ai_card_context(int $cmid, array $state): array {
        global $DB, $USER;

        $cm      = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);

        $coursefullname = self::format_plain_name($course->fullname, context_course::instance($course->id));
        $displayname    = self::format_plain_name(fullname($USER), $context);

        return [
            'aibuttonid'    => 'btn-ai',
            'responseid'    => 'ai-response',
            'socialmedia'   => 'LinkedIn',
            'cmid'          => (int) $cm->id,
            'author_name'   => $displayname,
            // The silhouette drawn by the template is only a fallback, so the real profile picture
            // of the user in session is exported whenever there is one to show.
            'author_avatar_url' => self::get_author_avatar_url($context),
            'certname'      => $state['certname'],
            'course'        => $coursefullname,
            'org'           => (string) get_config('local_socialcert', 'organizationname'),
            // The share URL only feeds the data-certurl attribute of the card; an activity without
            // the organization ID configured has no URL to publish, and the card carries none.
            'shareurl'      => (string) ($state['shareurl'] ?? ''),
            'imageurl'      => (new moodle_url('/local/socialcert/assets/logo_title.png'))->out(false),
            'imagelogo'     => (new moodle_url('/local/socialcert/assets/logo.png'))->out(false),
            'ai_actioncall' => get_string('ai_actioncall', 'local_socialcert'),
            // The label of the assistant button is the very same string amd/src/actions.js restores
            // once a generation finishes, so the accessible name of the control never changes.
            'aibuttonlabel' => get_string('airesponsebtn', 'local_socialcert'),
            'ailogoalt'     => get_string('ailogoalt', 'local_socialcert'),
            'airegionlabel' => get_string('airegionlabel', 'local_socialcert'),
            'avatarlabel'   => get_string('avatarlabel', 'local_socialcert', $displayname),
            'copytextlabel' => get_string('copytextlabel', 'local_socialcert'),
            'errorcredits'  => get_string('errorcredits', 'local_socialcert'),
            'errorgeneric'  => get_string('errorgeneric', 'local_socialcert'),
            'errorlicense'  => get_string('errorlicense', 'local_socialcert'),
        ];
    }

    /**
     * URL of the profile picture of the user in session, or an empty string when there is none.
     *
     * The card draws a generic silhouette when this value is empty, so the URL is only reported for
     * a user who really has a picture: user_picture::get_url() would otherwise answer with the
     * placeholder image of the theme, and the fallback of the template would become unreachable.
     *
     * @param context $context Context the picture is displayed in.
     * @return string Absolute URL of the picture, empty when the user has none.
     */
    private static function get_author_avatar_url(context $context): string {
        global $CFG, $PAGE, $USER;

        $hasownpicture = !empty($USER->picture);
        $hasgravatar   = empty($USER->picture) && !empty($CFG->enablegravatar) && !empty($USER->email);

        if (!$hasownpicture && !$hasgravatar) {
            return '';
        }

        $picture = core_user::get_profile_picture($USER, $context, ['size' => 100]);

        return $picture->get_url($PAGE)->out(false);
    }

    /**
     * Applies the platform text filters to a name and returns it as readable plain text.
     *
     * format_string() escapes the ampersand of a name into the '&amp;' entity, and the Mustache
     * template escapes whatever it renders once more, so 'Data & Skills' used to reach LinkedIn and
     * the AI service as 'Data &amp;amp; Skills'. The filters are therefore asked not to escape
     * (the 'escape' option of format_string(), supported since Moodle 4.5) and any entity left by
     * the text cleaning is decoded, so the value that travels is the readable text.
     *
     * This is only safe because the panel transports these names: they are carried inside the
     * LinkedIn URL, which percent encodes every parameter, and inside data attributes of the panel,
     * which the Mustache template escapes when it renders them.
     *
     * @param string|null $name Raw name, as the teacher or the administrator typed it.
     * @param context $context Context the filters are applied in.
     * @return string Filtered name as readable plain text.
     */
    private static function format_plain_name(?string $name, context $context): string {
        $filtered = format_string($name, true, ['context' => $context, 'escape' => false]);

        return html_entity_decode($filtered, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Calculates the share state of a certificate activity for one user.
     *
     * This is the single source of the business rules of the panel. The renderer uses it to build
     * the template context and the local_socialcert_get_share_state web service returns it to the
     * browser, so the panel can enable itself as soon as the certificate is issued (mod_customcert
     * only creates the issue when the certificate is downloaded) without asking for a manual
     * reload, and both sides always apply exactly the same rules.
     *
     * @param int $cmid Course module ID of the custom certificate activity.
     * @param int $userid ID of the user the state belongs to.
     * @return array State with the keys hasissue, certname, certid, issuedts, expiryts, verifyurl,
     *               shareurl, datanetwork, verifywarning and enableai.
     */
    public static function get_share_state(int $cmid, int $userid): array {
        global $DB;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $issue = $DB->get_record('customcert_issues', [
            'customcertid' => $cm->instance,
            'userid'       => $userid,
        ], '*', IGNORE_MISSING);

        $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', IGNORE_MISSING);

        $state = [
            'hasissue'      => (bool) ($customcert && $issue),
            'certname'      => '',
            'certid'        => '',
            'issuedts'      => 0,
            'expiryts'      => 0,
            'verifyurl'     => '',
            'shareurl'      => null,
            'datanetwork'   => '',
            'verifywarning' => '',
            'enableai'      => false,
        ];

        if ($state['hasissue']) {
            $state['certname']  = self::format_plain_name($customcert->name, $context);
            $state['issuedts']  = (int) $issue->timecreated;
            $state['expiryts']  = self::get_expiry_timestamp((int) $customcert->id, $userid);
            $state['certid']    = $issue->code;
            $state['verifyurl'] = (new moodle_url(
                '/mod/customcert/verify_certificate.php',
                ['code' => $issue->code]
            ))->out(false);

            // The share URL is only built when the LinkedIn organization ID is configured, so the
            // credential is never published attributed to a foreign organization.
            $state['shareurl'] = linkedin_helper::build_linkedin_url(
                certname: $state['certname'],
                issueunixtime: $state['issuedts'],
                certurl: $state['verifyurl'] ?: '',
                certid: $state['certid'] ?: '',
                expiryunixtime: $state['expiryts'] ?: null
            );

            if ($state['shareurl'] !== null) {
                $state['datanetwork'] = 'linkedin';

                if (!self::is_publicly_verifiable($customcert)) {
                    $state['verifywarning'] = get_string('verifywarning', 'local_socialcert');
                }
            }
        }

        // The AI assistant demands an issued certificate, exactly like the share button, so no
        // generation can be requested (and no credits spent) without a certificate. It also demands
        // the capability of the assistant, so a role can be left with the sharing and without the
        // generation; the default archetype of the capability is the student, so nothing changes for
        // the users who reach the panel today. The web service revalidates the very same capability,
        // because hiding the card is not the control.
        $state['enableai'] = $state['hasissue']
            && (bool) ((int) get_config('local_socialcert', 'enableai'))
            && has_capability('local/socialcert:useaiassistant', $context, $userid);

        return $state;
    }

    /**
     * Expiry date of the credential of a user, as the verification page of mod_customcert reads it.
     *
     * The expiry date is not a field of the issue: it is calculated by the customcertelement_expiry
     * subplugin from the element the certificate template carries, exactly as
     * mod/customcert/verify_certificate.php does. The subplugin is optional, so its absence simply
     * means the credential has no expiration and none is sent to LinkedIn.
     *
     * @param int $customcertid ID of the custom certificate instance.
     * @param int $userid ID of the user the credential belongs to.
     * @return int Expiry timestamp, 0 when the certificate has no expiry element or none applies.
     */
    private static function get_expiry_timestamp(int $customcertid, int $userid): int {
        if (!class_exists('\customcertelement_expiry\element')) {
            return 0;
        }

        if (!\customcertelement_expiry\element::has_expiry($customcertid)) {
            return 0;
        }

        // The subplugin answers 0 when the expiry cannot be resolved, for instance while it is
        // relative to a course completion the user has not reached yet.
        return max(0, \customcertelement_expiry\element::get_expiry_date($customcertid, $userid));
    }

    /**
     * Whether an anonymous visitor can verify the credential through the site verification page.
     *
     * The link published on LinkedIn points at /mod/customcert/verify_certificate.php, which only
     * answers a visitor without a session when the site wide setting customcert/verifyallcertificates
     * is enabled and the activity allows anyone to verify its certificates.
     *
     * @param \stdClass $customcert Custom certificate instance record.
     * @return bool True when the published link is verifiable by third parties.
     */
    private static function is_publicly_verifiable(\stdClass $customcert): bool {
        if (empty(get_config('customcert', 'verifyallcertificates'))) {
            return false;
        }

        return !empty($customcert->verifyany);
    }
}
