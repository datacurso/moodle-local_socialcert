## [1.1.7] - 2026-11-09

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.3**.

This version closes the three findings of the Mindfree security report MFR-ZN624XJAL27LCFWPNQQYCB6T on version 1.1.4; versions 1.1.5 and 1.1.6 shipped before these fixes and do not include them. Each entry names the finding it closes and the automated test that backs it.

### 🚀 Added

- New setting **AI requests limit per user per hour** (`airatelimit`, default 10), with its description in the seven language packs and in the README

### 🔧 Changed

- The PHPUnit test of a missing provider licence key asserts the error code of the provider instead of its localized text, which changed in aiprovider_datacurso 1.6.4 (`tests/external_ai_helper_test.php`)

### 🔒 Security

- SOCIALCERT-SEC-001: remove the GitHub Actions workflow `moodle-release.yml`, which inserted the manually typed tag name into a shell step that had the moodle.org publishing token at hand. A guard test now fails if any workflow directly interpolates untrusted input into a `run` or `script` step, or exposes that token again; its docblock lists the indirect forms it does not cover (`tests/ci/workflow_security_test.php`). Publishing to the moodle.org directory becomes a manual step
- SOCIALCERT-SEC-002: the web service `local_socialcert_get_ai_response` spends one unit of a per user hourly quota, taken from the Moodle AI rate limiter under a per user lock, after every business check passes and before the provider is contacted. Calls over the limit are refused with `error_ratelimit_exceeded` and a message that names the limit; a call that cannot take the lock is refused with `error_ratelimit_busy` without spending the quota, which the assistant card shows as its generic "try again later" message (`tests/external_ai_helper_ratelimit_test.php`)

### 🐞 Fixed

- SOCIALCERT-PRIV-001: the privacy metadata of the LinkedIn destination now declares the expiration date that the "Add to profile" link carries when the certificate expires. A test builds the link with and without an expiration date and checks that every parameter it carries is declared with its language string (`tests/privacy_provider_test.php`)

## [1.1.6] - 2026-11-06

### 🔧 Changed

- Disable the whole plugin when it is disabled in the Datacurso AI provider (Site administration > AI > Datacurso plugins list): the share panel is not rendered and the web services (share state, share log and AI generation) reject every call with the provider message, which links an administrator to the provider plugins list
- Move the former "Enable AI to suggest post text" switch (`enableai`) to the Datacurso AI provider, which copies its value as the state of the whole plugin; the setting and its strings are removed from the plugin and the setting is deleted on upgrade
- Add the "Enable AI assistant" setting (`aiassistant_enabled`, on by default and switched on by the upgrade, so existing sites keep the assistant) to the plugin settings page: turning it off hides the AI card that writes the LinkedIn post, keeps the Share on LinkedIn option and makes the AI generation web service refuse with the new `aiassistantdisabled` error before contacting the AI service. It replaces the former `aidisabled` error, whose string is removed
- Require aiprovider_datacurso 2026110400

## [1.1.5] - 2026-11-04

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.3**.

### 🔧 Changed

- Extend the supported range to Moodle 5.3

### 🐞 Fixed

- Read the time spent in the course through `\mod_customcert\service\certificate_time_service` when it exists, instead of `certificate::get_course_time()`, deprecated by mod_customcert for Moodle 5.2 and later; older releases keep using the static method (#11)
- Tell the user in the panel live region when copying the generated text fails, instead of failing silently (#13)
- Replace the "Get your certificate first" notice with the missing organization ID notice when the certificate is issued while the page is open and the LinkedIn organization ID is not configured (#14)
- Show the license message with the link to the Datacurso shop when the AI provider reports a missing license key (`licensekey_missing`), instead of the generic error (#15)

## [1.1.4] - 2026-10-02

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.2**.

### 🔧 Changed

- Extend the supported range to Moodle 5.2
- Skip the PHPUnit tests that build a certificate activity with an explicit message when mod_customcert is not installed, instead of failing inside the data generator

### 🔒 Security

- Sanitize the error responses of the local_socialcert_get_ai_response web service: only the localized messages of the plugin and of the Datacurso AI provider reach the browser, every other failure (including PHP errors) is answered with the generic message of the plugin and kept in the developer debugging output
- Compute the inputs of the AI prompt (certificate, course and organization names) on the server, from the same source the assistant card renders, and accept only the social networks of an allowlist. The signature of the web service changes: the `body` parameter is removed and `socialmedia` is added (optional, defaults to `linkedin`); the function is now declared as a write function

## [1.1.3] - 2026-07-30

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.2**.

### 🚀 Added

- Enable the share panel by itself once the certificate has been issued, so the student no longer has to reload the page after downloading it
- Render the AI assistant card as soon as the certificate is issued, so it no longer needs a manual page reload to appear either
- Add the local_socialcert_get_share_state web service, which reports the state of the panel for the user in session without creating any certificate issue, including the context of the assistant card while the assistant is really available
- Announce the newly available share action in the live region of the panel
- Add the local/socialcert:viewsharepanel and local/socialcert:useaiassistant capabilities, both at activity level and allowed by default only for the student archetype, so the share panel and the AI assistant can be restricted by role without changing who reaches them today
- Record the share of a credential and every successful AI generation in the logs of the platform, through the new certificate_shared and ai_text_generated events
- Add the local_socialcert_log_share web service, the write function the panel calls when it opens the LinkedIn window, which revalidates that the credential really was shareable before recording the event

### 🐞 Fixed

- Declare the privacy provider under the plugin namespace and publish the metadata of the data sent to the Datacurso AI service and to LinkedIn
- Stop building the LinkedIn share URL with a hardcoded fallback organization ID, so no credential is published attributed to a third party organization
- Require an issued certificate before offering the AI assistant in the panel
- Warn in the panel when the certificate verification settings prevent third parties from verifying the published link
- Revalidate the AI global setting, the certificate issue and the activity type in the web service before contacting the AI service
- Deliver the provider error code to the panel intact by declaring it as plain text instead of alphanumeric text
- Validate the page type before reading the course module in the footer callback, so no page of the site emits debugging notices
- Rename the misnamed French language file so the French pack loads
- Insert the AI response as text instead of markup, and rebuild the plugin messages from an allowed node list, so no remote content can run code in the browser
- Use the translatable string while the assistant is generating, and show the copy confirmation of the copy button
- Show the share panel only to users who can receive the certificate of the activity, so teachers and managers no longer get it in its error state below the issues report
- Keep the panel out of the intermediate pages of the activity, namely the required time notice and the issue deletion confirmation
- Name the action that enables the sharing in the notice shown while no certificate has been issued, and point at the missing organization ID of the site when the certificate is already issued, instead of showing the same text for both situations
- Stop double escaping the ampersand of certificate and course names, so the value that travels to LinkedIn and to the AI service is the readable text
- Show the real profile picture of the user in the post preview, and keep the generic silhouette only as the fallback
- Export the accessible names of the assistant and copy buttons, and turn the labels hardcoded in Spanish inside the templates into language strings
- Rewrite the Portuguese language pack, which carried most of its texts in French, including the title and the subtitle of the panel, the share button, the call to action of the assistant and the generic error message
- Complete the German, French, Portuguese, Indonesian and Russian packs, which were missing the strings added by the latest versions: the notices of the two disabled states, the verification warning, the availability announcement, the rejection messages of the service, the accessible names of the assistant, the names of the events, the names of the capabilities and the privacy metadata
- Declare the generic error message of the Russian pack under its real key, errorgeneric, instead of the unused error_generating_resource, and add the missing enableai and enableai_desc settings texts
- Add the missing ai_actioncall call to action of the Indonesian pack, and the missing copyarticlebuttontext and linktext strings of the Spanish pack
- Translate the English text left in Spanish in the noissue string of the English pack
- Translate the name of the plugin in the six languages that repeated the English name
- Drop the stylesheet callback registered against a hook class that does not exist in Moodle 4.5, and scope every rule of styles.css under the root class of the panel
- Send the expiry date of the credential to LinkedIn when the certificate carries an expiry element

### 🔧 Changed

- Move the AI assistant card to its own local_socialcert/ai_card template, rendered by the server and by the browser from the same single source of truth
- Move the expansion of the assistant card from an inline script in the template to the AMD module, delegated on the panel root
- Migrate the AI external function to the current external API namespace, so its tests no longer need process isolation

## [1.1.1] - 2026-04-23

### 🚀 Added

- Add LinkedIn Docs reference for organization ID (003e148)

### 🐞 Fixed

- Update verification URL in README and main_panel to use correct endpoint (962d67f)