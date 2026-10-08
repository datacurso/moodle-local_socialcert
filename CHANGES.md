## [1.2.0-wp] - 2026-10-06

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### 🚀 Added

- Store the plugin settings (LinkedIn organization ID, LinkedIn organization name and AI assistant toggle) per Workplace tenant, so every tenant publishes its certificates under its own LinkedIn organization and decides on its own whether the AI assistant is available
- Add the local/socialcert:managetenantsettings capability, granted by default to the manager archetype and to the tenant administrator role, so tenant administrators can open the settings page of their own tenant
- Show a notice at the top of the settings page naming the tenant the settings apply to
- Delete the settings of a tenant when the tenant is deleted

### 🔧 Changed

- Require Moodle Workplace: the plugin now depends on tool_tenant and supports only Moodle 4.5
- Move the former site-wide settings to the default tenant on upgrade; there is no site-wide value and no inheritance between tenants, so a tenant without an organization ID gets no LinkedIn share link
- Require mod/customcert:receiveissue, the same capability the panel demands, in the local_socialcert_get_share_state, local_socialcert_log_share and local_socialcert_get_ai_response web services instead of mod/customcert:view
- Reword the strings that told the site administrator to configure the plugin, which is now a task of the tenant administrator
- Install, upgrade and run the plugin and its tests on sites without tool_tenant (plain Moodle): every tenancy call goes through one wrapper, the settings then apply to a single implicit tenant (id 0), and the tests that need Workplace are skipped

### 🐞 Fixed

- Resolve the issue and expiry month and year sent to LinkedIn in the timezone of the user instead of the one of the server, so they match the dates the user sees
- Correct the file descriptions of settings.php, db/hooks.php and db/services.php

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