## [1.1.5] - 2026-07-30

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.1**.

### 🐞 Fixed

- Render the AI assistant card as soon as the certificate is issued, so it no longer needs a manual page reload to appear
- Return the context of the assistant card in the local_socialcert_get_share_state web service, only while the assistant is really available

### 🔧 Changed

- Move the AI assistant card to its own local_socialcert/ai_card template, rendered by the server and by the browser from the same single source of truth
- Move the expansion of the assistant card from an inline script in the template to the AMD module, delegated on the panel root

## [1.1.4] - 2026-07-30

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.1**.

### 🚀 Added

- Enable the share panel by itself once the certificate has been issued, so the student no longer has to reload the page after downloading it
- Add the local_socialcert_get_share_state web service, which reports the state of the panel for the user in session without creating any certificate issue
- Announce the newly available share action in the live region of the panel

## [1.1.3] - 2026-07-29

**Compatibility note:** This version is compatible from **Moodle 4.5** to **Moodle 5.1**.

### 🐞 Fixed

- Declare the privacy provider under the plugin namespace and publish the metadata of the data sent to the Datacurso AI service and to LinkedIn
- Stop building the LinkedIn share URL with a hardcoded fallback organization ID, so no credential is published attributed to a third party organization
- Require an issued certificate before offering the AI assistant in the panel
- Warn in the panel when the certificate verification settings prevent third parties from verifying the published link
- Revalidate the AI global setting, the certificate issue and the activity type in the web service before contacting the AI service
- Deliver the provider error code to the panel intact by declaring it as plain text instead of alphanumeric text
- Validate the page type before reading the course module in the footer callback, so no page of the site emits debugging notices
- Rename the misnamed French language file so the French pack loads

## [1.1.1] - 2026-04-23

### 🚀 Added

- Add LinkedIn Docs reference for organization ID (003e148)

### 🐞 Fixed

- Update verification URL in README and main_panel to use correct endpoint (962d67f)