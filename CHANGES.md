## [1.1.3] - 2026-07-30

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.1**.

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