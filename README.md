# Share Certificate AI (local_socialcert)

A Moodle plugin that lets learners add the certificates they earn in **Custom certificate** activities to their **LinkedIn profile** with one click, and generates a **professional AI message** ready to paste into a LinkedIn post.

The plugin does not modify *Custom certificate*: it adds its own panel at the end of the certificate page.

---

## Features

* **LinkedIn Add-to-profile** button, prefilled with the certificate name, the issuing organization, the issue date, the credential code, the public verification link and, when the certificate carries an expiry element, the expiry date.
* **AI assistant** that drafts the LinkedIn post for the learner, with a preview of the post and a button to copy the text to the clipboard.
* **Enables itself without a page reload**: as soon as the learner obtains the certificate, the share button and the AI assistant become available when the page is shown again, with no manual refresh.
* **Warns when the published link will not be verifiable** by a third party, so administrators can review the verification settings of the site and of the activity.
* **Own capabilities** to restrict the share panel and the AI assistant by role.
* **Event logging** of every share and every AI generation, so the use of the assistant is traceable in the logs of the site.
* Complies with the Moodle privacy API: no personal data is stored by the plugin, and the data sent to the Datacurso AI service and to LinkedIn is declared in the privacy registry.
* Languages: English, Spanish, German, French, Portuguese, Indonesian and Russian.

---

## Prerequisites

* **Moodle 4.5** to **Moodle 5.1**.
* **[Custom certificate](https://moodle.org/plugins/mod_customcert)** (`mod_customcert`), minimum version **2024042212**.
* **[DataCurso AI Provider](https://moodle.org/plugins/aiprovider_datacurso/versions)** (`aiprovider_datacurso`), minimum version **2025100201**, configured with a valid licence key. See [Getting license keys](https://docs.datacurso.com/index.php?title=Datacurso_AI_Provider#Getting_license_keys).

> **Both plugins are required dependencies:** Moodle will not install Share Certificate AI until they are present. The share button works without a licence key, but the AI assistant needs the provider installed **and** licensed.

---

## Installing via ZIP upload

1. Log in as **administrator** and go to **Site administration → Plugins → Install plugins**.
2. Upload the plugin **ZIP**.
3. Review the validation report and **complete the installation**.

## Installing manually

1. Copy this directory to:

   ```
   {your/moodle/dirroot}/local/socialcert
   ```

2. Log in as administrator and visit **Site administration → Notifications** to complete the upgrade,
   or run from CLI:

   ```bash
   php admin/cli/upgrade.php
   ```

---

## Plugin configuration

1. **Sign in as a site administrator.**
2. Navigate to **Site administration → Plugins → Local plugins → Share Certificate AI**.
3. Review and complete the settings:

   * **LinkedIn organization ID** (`organizationid`) — **required to share**.

     * Numeric ID of the company or organization page that LinkedIn associates with the certification.
     * While it is empty the plugin builds no share link and the share action stays disabled, so no credential is ever published attributed to another organization.
   * **LinkedIn organization name** (`organizationname`) — *recommended*.

     * Used only as context for the AI assistant when it drafts the post. It is **not** sent to LinkedIn: LinkedIn resolves the organization from the ID above.
     * Enter the **exact name as it appears on LinkedIn** so the drafted text matches the page.
   * **Enable AI to suggest post text** (`enableai`) — *global toggle*, enabled by default.

     * When enabled, learners with an issued certificate see the AI assistant in the panel.
     * When disabled, the assistant is hidden and the AI service is never contacted; the share button stays available.
4. Click **Save changes**.

![Plugin settings](./_docs/images/local_socialcert_settings.png)

### How to find your LinkedIn organization ID

You must be an **administrator** of your institution’s LinkedIn Page.

1. Open your organization’s LinkedIn Page in admin view.
2. Copy the **numeric ID** from the URL.

Example:

```
https://www.linkedin.com/company/61803398/admin/...
```

In this example, the **organization ID** is `61803398`.

### Making the published credential verifiable

The share link points at the certificate verification page of *Custom certificate*, so that anyone reading the LinkedIn profile can validate the credential. For a visitor with no session to verify it, two settings have to be enabled:

* **Site administration → Plugins → Activity modules → Custom certificate → Allow verification of all certificates** (`verifyallcertificates`).
* **Allow anyone to verify certificates** (`verifyany`) in the settings of each Custom certificate activity.

Both are disabled by default in *Custom certificate*. While either of them is off, the panel warns the learner that the published link will not be verifiable by third parties.

---

## Using it in a course

1. **Add a Custom certificate** activity to your course.
2. The learner opens the activity. The **Share your achievement on LinkedIn** panel appears at the end of the page.
3. The share button becomes available once the certificate has been **issued**, which happens when the learner obtains it from **View certificate**, or earlier if the activity sends the certificate by email.

![Share panel](./_docs/images/local_socialcert_main_panel.png)

While no certificate has been issued, the button is disabled and the panel explains what to do next. The learner does not need to reload the page: after obtaining the certificate and returning to the activity, the panel enables the share button and adds the AI assistant on its own.

![Share panel without an issued certificate](./_docs/images/local_socialcert_main_panel_error.png)

The panel is shown only to users who can receive the certificate of the activity, so it does not appear on the issues report of teachers and managers, nor on the intermediate pages of the activity.

---

## Add to LinkedIn (one click)

1. On the certificate page, click **Share on LinkedIn**.

   ![Share on LinkedIn button](./_docs/images/local_socialcert_linkedin_button.png)

2. A LinkedIn window opens with the certificate details **already filled in** (title, issuing organization, issue date, credential ID and verification URL). Review and click **Save**.

   ![LinkedIn add-to-profile dialog prefilled](./_docs/images/local_socialcert_linkedin_form.png)

> If you are not signed in to LinkedIn, you are asked to **log in** first.
> If the browser blocks the new window, the panel asks you to allow pop-ups and the share can be retried.

The plugin never publishes on behalf of the learner: it prefills the official LinkedIn form and, optionally, drafts a text to copy.

---

## Generate a LinkedIn post suggestion (AI)

1. On the certificate page, find the assistant card and its **AI button** (brain icon).

   ![AI button](./_docs/images/local_socialcert_button_ai.png)

2. **Click the AI button.** The card expands and shows a preview of the **suggested LinkedIn message** while it is written.

   ![AI panel expanded](./_docs/images/local_socialcert_main_panel_ai.png)

3. Click **Copy** (clipboard icon) to copy the text.

   ![Copy button](./_docs/images/local_socialcert_copy_button.png)

4. Open LinkedIn, **paste** the message into your post, adjust it if you want, and publish.

Each generation consumes credits of the DataCurso AI Provider licence. When the licence has no credits left, is not authorised, or the consumption limit has been reached, the card explains which of those happened instead of showing a generic error.

---

## Capabilities

| Capability | Controls | Allowed by default |
|---|---|---|
| `local/socialcert:viewsharepanel` | Seeing the share panel on the certificate page | Student |
| `local/socialcert:useaiassistant` | Using the AI assistant to draft the post | Student |

Both are checked in the module context, so they can be overridden per activity, course or category. The defaults keep the behaviour the plugin had before they existed: only the users who can receive the certificate of the activity see the panel.

---

## Logs

Every share and every AI generation is recorded as an event of the plugin, visible in **Site administration → Reports → Logs** and in the course logs. Only generations the AI service really answered are logged, so the log reflects the consumption of credits.

---

## License

2025 Data Curso LLC <https://datacurso.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
