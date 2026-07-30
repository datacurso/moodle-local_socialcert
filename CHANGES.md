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