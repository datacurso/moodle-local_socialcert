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

defined('MOODLE_INTERNAL') || die();

$string['ai_actioncall'] = 'Create a professional message for your LinkedIn post in one click';
$string['ai_field_heading'] = 'Post text';
$string['aidisabled'] = 'The AI assistant is disabled for this site, so no post text can be generated.';
$string['ailogoalt'] = 'Datacurso logo';
$string['airegionlabel'] = 'AI assistant to draft your post';
$string['airesponsebtn'] = 'Activate AI';
$string['avatarlabel'] = 'Profile picture of {$a}';
$string['buttonlabelshare']  = 'Share on LinkedIn';
$string['certerror'] = "You’ll need to have an issued certificate before you can share it on LinkedIn.";
$string['certerrordownload'] = 'Get your certificate first: download it from this page to enable sharing it on LinkedIn.';
$string['certerrornoorg'] = 'Sharing on LinkedIn is not available yet: the site administrator has to configure the organization ID.';
$string['certificate_url'] = 'Link';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'Copy LinkedIn article';
$string['copyconfirmation'] = 'Copied ✔';
$string['copytextlabel'] = 'Copy the generated text';
$string['description'] = 'Allows the user to share their certificate directly on LinkedIn.';
$string['enableai'] = 'Enable AI to suggest post text';
$string['enableai_desc'] = 'If unchecked, the plugin will not call Provider AI or generate LinkedIn post suggestions.';
$string['errorcredits'] = 'Insufficient AI credits. Please visit <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop to allocate or purchase more credits. Or contact your administrator.';
$string['errorgeneric'] = 'An error occurred while generating the content. Please try again later.';
$string['errorlicense'] = 'Your license is not allowed to perform this request. Please manage your licenses and credits in <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Manage Credits</a> in the Datacurso Shop.';
$string['eventaitextgenerated'] = 'AI post text generated';
$string['eventcertificateshared'] = 'Certificate shared on a social network';
$string['generating'] = 'Generating…';
$string['invalidcmid'] = 'The activity identifier of the request is not valid.';
$string['linkcertbuttontext'] = 'Share on LinkedIn';
$string['linktext'] = 'Certificate link';
$string['nocertificateissued'] = 'You need an issued certificate in this activity before you can generate a post text.';
$string['noissue'] = 'You do not have an issued certificate for this course yet.';
$string['notacertificateactivity'] = 'The requested activity is not a custom certificate.';
$string['nothingtoshare'] = 'There is nothing to share yet for this certificate.';
$string['organizationid'] = 'LinkedIn organization ID';
$string['organizationid_desc'] = 'Numeric company/organization ID used by LinkedIn Add-to-profile. Leave empty to disable until configured.';
$string['organizationname'] = 'LinkedIn organization name';
$string['organizationname_desc'] = 'Name of the organization to display in LinkedIn. Must match exactly as it appears on LinkedIn. Leave empty to disable until configured.';
$string['pluginname'] = 'Share Certificate AI';
$string['popupblocked'] = 'Enable pop-ups to continue.';
$string['privacy:metadata'] = 'The Share Certificate AI plugin does not store any personal data.';
$string['privacy:metadata:aiservice'] = 'Certificate and course data sent to the Datacurso AI service to draft the social post text.';
$string['privacy:metadata:aiservice:certname'] = 'The name of the certificate the post is written about.';
$string['privacy:metadata:aiservice:coursename'] = 'The name of the course the certificate belongs to.';
$string['privacy:metadata:aiservice:organizationname'] = 'The name of the issuing organization configured by the site administrator.';
$string['privacy:metadata:aiservice:sitedata'] = 'The site identifier, site URL and time zone used to authorise the request.';
$string['privacy:metadata:aiservice:userid'] = 'The numeric identifier of the user requesting the generation.';
$string['privacy:metadata:linkedin'] = 'Credential data sent to LinkedIn when the user shares the certificate on their profile.';
$string['privacy:metadata:linkedin:certificationname'] = 'The name of the certification added to the LinkedIn profile.';
$string['privacy:metadata:linkedin:credentialcode'] = 'The credential code of the certificate issue.';
$string['privacy:metadata:linkedin:issuedate'] = 'The month and year the certificate was issued.';
$string['privacy:metadata:linkedin:organizationid'] = 'The numeric LinkedIn organization identifier configured by the site administrator.';
$string['privacy:metadata:linkedin:verificationlink'] = 'The public verification link of the certificate.';
$string['sharecompleted'] = 'LinkedIn share completed.';
$string['shareinstruction'] = 'Celebrate your achievement! Click below to showcase your certificate on LinkedIn and let your network know about your success:';
$string['sharenowavailable'] = 'Your certificate has been issued, so you can now share it on LinkedIn.';
$string['sharesubtitle'] = 'We’ll post a verifiable link to your certificate.';
$string['sharetitle'] = 'Share your achievement on LinkedIn';
$string['socialcert:useaiassistant'] = 'Use the AI assistant to draft the text of the post';
$string['socialcert:viewsharepanel'] = 'View the panel to share the certificate on social networks';
$string['verifywarning'] = 'Certificate verification is disabled, so the link published on LinkedIn will not be verifiable by third parties.';
$string['whatsharelabel'] = 'What do we share?';
