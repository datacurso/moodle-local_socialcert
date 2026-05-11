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
        global $DB, $USER;

        $cm     = get_coursemodule_from_id('', $this->cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);

        $issue = $DB->get_record('customcert_issues', [
            'customcertid' => $cm->instance,
            'userid'       => $USER->id,
        ], '*', IGNORE_MISSING);

        $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', IGNORE_MISSING);

        $certname = '';
        $issuedts = 0;
        $certid = '';
        $verifyurl = '';
        $shareurl = null;
        $datanetwork = '';

        if ($customcert && $issue) {
            $certname  = format_string($customcert->name, true, ['context' => $context]);
            $issuedts  = (int) $issue->timecreated;
            $certid    = $issue->code;
            $verifyurl = (new moodle_url('/mod/customcert/verify_certificate.php', ['code' => $issue->code]))->out(false);

            $shareurl = \local_socialcert\output\linkedin_helper::build_linkedin_url(
                certname: $certname,
                issueunixtime: $issuedts,
                certurl: $verifyurl ?: '',
                certid: $certid ?: ''
            );

            $datanetwork = 'linkedin';
        }

        $issued = $issue ? false : true;

        $orgname  = get_config('local_socialcert', 'organizationname');
        $enableai = (bool)((int) get_config('local_socialcert', 'enableai'));

        $coursefullname = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
        $displayname    = format_string(fullname($USER), true, ['context' => $context]);

        $imgurl = (new moodle_url('/local/socialcert/assets/logo_title.png'))->out(false);

        $imglogo = (new moodle_url('/local/socialcert/assets/logo.png'))->out(false);

        $errorgeneric = get_string('errorgeneric', 'local_socialcert');
        $errorlicense = get_string('errorlicense', 'local_socialcert');
        $errorcredits = get_string('errorcredits', 'local_socialcert');

        return [
            'aibuttonid'        => 'btn-ai',
            'buttonid'          => 'btn-normal',
            'imageid'           => 'ai-image',
            'responseid'        => 'ai-response',
            'socialmedia'       => 'LinkedIn',
            'author_name'       => $displayname,
            'certid'            => $certid,
            'certname'          => $certname,
            'cmid'              => $cm->id,
            'course'            => $coursefullname,
            'datanetwork'       => $datanetwork,
            'enableai'          => $enableai,
            'errorcredits'      => $errorcredits,
            'errorgeneric'      => $errorgeneric,
            'errorlicense'      => $errorlicense,
            'imagelogo'         => $imglogo,
            'imageurl'          => $imgurl,
            'issued'            => $issued,
            'org'               => $orgname,
            'shareurl'          => $shareurl,
            'verifyurl'         => $verifyurl,
            'ai_actioncall'     => get_string('ai_actioncall', 'local_socialcert'),
            'buttonlabel'       => get_string('linkcertbuttontext', 'local_socialcert'),
            'buttonlabelshare'  => get_string('buttonlabelshare', 'local_socialcert'),
            'intro'             => get_string('shareinstruction', 'local_socialcert'),
            'linktext'          => get_string('linktext', 'local_socialcert'),
            'popupblocked'      => get_string('popupblocked', 'local_socialcert'),
            'sharecompleted'    => get_string('sharecompleted', 'local_socialcert'),
            'sharesubtitle'     => get_string('sharesubtitle', 'local_socialcert'),
            'sharetitle'        => get_string('sharetitle', 'local_socialcert'),
            'whatsharelabel'    => get_string('whatsharelabel', 'local_socialcert'),
            'certerror'         => get_string('certerror', 'local_socialcert'),
        ];
    }
}
