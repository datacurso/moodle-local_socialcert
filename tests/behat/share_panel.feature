@local @local_socialcert
Feature: Share an issued certificate on LinkedIn from the certificate activity panel
  In order to publish a verified achievement without retyping any credential data
  As a student with an issued certificate
  I need the share panel to expose a prefilled LinkedIn add-to-profile link

  # Scenarios tagged @skip_pending describe behaviour that cannot be asserted in this
  # environment: either the plugin does not implement it yet ([Pendiente:skip] in
  # socialcert-1.1.3.md), or it is implemented but needs a real browser container, which is
  # not available here. Their steps are deliberately commented out so no scenario can ever
  # report unverified behaviour as correct. Exclude them explicitly when running the suite:
  #   --tags="@local_socialcert&&~@skip_pending"

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity   | name               | intro                    | course | idnumber |
      | customcert | Course certificate | Course certificate intro | C1     | cert1    |
    And the following config values are set as admin:
      | organizationid   | 98765     | local_socialcert |
      | organizationname | Buen Data | local_socialcert |
      | enableai         | 1         | local_socialcert |

  # The LinkedIn form itself is never visited: linkedin.com is out of the test boundary.
  # Instead the anchor is inspected, which is what the plugin is responsible for building.
  # The credential code is generated randomly by mod_customcert on every run, so only the
  # presence of the certId and certUrl parameters is asserted here. The exact propagation of
  # the code value is covered by MDL-UNIT-003 at the unit layer.
  @MDL-E2E-001
  Scenario: Student with an issued certificate gets a prefilled LinkedIn add to profile link
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "div.local-socialcert" "css_element" should exist
    And "div.local-socialcert[data-network='linkedin']" "css_element" should exist
    And I should see "Share your achievement on LinkedIn"
    And I should see "We’ll post a verifiable link to your certificate."
    And I should see "Share on LinkedIn"
    And "a#btn-normal" "css_element" should exist
    And "a#btn-normal.disabled" "css_element" should not exist
    And "div.lsc-error-message" "css_element" should not exist
    And the "href" attribute of "a#btn-normal" "css_element" should be set
    And the "href" attribute of "a#btn-normal" "css_element" should contain "https://www.linkedin.com/profile/add"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "startTask=CERTIFICATION_NAME"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "name=Course%20certificate"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "organizationId=98765"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "issueYear="
    And the "href" attribute of "a#btn-normal" "css_element" should contain "issueMonth="
    And the "href" attribute of "a#btn-normal" "css_element" should contain "certId="
    And the "href" attribute of "a#btn-normal" "css_element" should contain "%2Fmod%2Fcustomcert%2Fverify_certificate.php%3Fcode%3D"
    And the "target" attribute of "a#btn-normal" "css_element" should contain "_blank"
    And the "rel" attribute of "a#btn-normal" "css_element" should contain "noopener"

  # Step 4 of MDL-E2E-001: the organization sent to LinkedIn must be the configured numeric id,
  # never the hardcoded generic fallback, and the organization name must not travel to LinkedIn.
  @MDL-E2E-001
  Scenario: The shared link carries the configured organization id and not the generic fallback
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then the "href" attribute of "a#btn-normal" "css_element" should contain "organizationId=98765"
    And the "href" attribute of "a#btn-normal" "css_element" should not contain "organizationId=1337"
    And the "href" attribute of "a#btn-normal" "css_element" should not contain "Buen%20Data"

  # Step 5 of MDL-E2E-001: sharing is a plain outbound link, so the plugin can never post on
  # behalf of the user nor request authorisation over the LinkedIn account.
  @MDL-E2E-001
  Scenario: Sharing never asks for LinkedIn authorisation and never posts automatically
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "a#btn-normal" "css_element" should exist
    And "form[action*='linkedin']" "css_element" should not exist
    And "div.local-socialcert form" "css_element" should not exist
    And the "href" attribute of "a#btn-normal" "css_element" should not contain "oauth"
    And the "href" attribute of "a#btn-normal" "css_element" should not contain "/sharing/share-offsite"
    And the "href" attribute of "a#btn-normal" "css_element" should not contain "/uas/"

  # MDL-E2E-002 — implemented in 1.1.4 and completed in 1.1.5: the panel asks
  # local_socialcert_get_share_state for its own state on pageshow (which includes the restoration
  # from the back/forward cache when the student returns from the certificate PDF) and when the tab
  # becomes visible again, and enables the button by itself. Since 1.1.5 the same refresh also adds
  # the AI assistant card, which the server could not have rendered while there was no issue: the
  # state returns the context of the local_socialcert/ai_card template and the browser renders that
  # very same template through core/templates, so neither the button nor the card needs a manual
  # reload. The scenario cannot be executed in this environment because there is no real browser
  # container (no Selenium/Chrome): the flow needs a genuine back navigation restored from the
  # browser cache, which the goutte driver cannot reproduce. The steps below describe the behaviour
  # already implemented and stay commented out until a browser container is available.
  @javascript @MDL-E2E-002 @skip_pending
  Scenario: Share panel and assistant card appear right after the certificate is obtained for the first time
    Given this scenario is pending because "MDL-E2E-002 requires a real browser (Selenium/Chrome), which this environment does not provide: the implemented behaviour is that the panel re-reads its state when the page is shown again after the certificate download and, without any manual reload, enables the share button and renders the AI assistant card"
    # When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # Then "a#btn-normal.disabled" "css_element" should exist
    # And I should see "Get your certificate first: download it from this page to enable sharing it on LinkedIn."
    # And "div.lsc-response-wrap" "css_element" should not exist
    # And "button#btn-ai" "css_element" should not exist
    # And I press "View certificate"
    # And I press the browser back button
    # And "a#btn-normal.disabled" "css_element" should not exist
    # And the "aria-disabled" attribute of "a#btn-normal" "css_element" should not be set
    # And the "href" attribute of "a#btn-normal" "css_element" should contain "https://www.linkedin.com/profile/add"
    # And "div.local-socialcert[data-network='linkedin']" "css_element" should exist
    # And "div.lsc-error-message" "css_element" should not exist
    # And I should see "Your certificate has been issued, so you can now share it on LinkedIn." in the "div.lsc-live" "css_element"
    # The assistant card is rendered by the browser from local_socialcert/ai_card without reloading:
    # And "div.lsc-response-wrap" "css_element" should exist
    # And "div.bd-post[data-ai-composer]" "css_element" should exist
    # And "button#btn-ai[data-action='run-ai']" "css_element" should exist
    # And the "data-certname" attribute of "button#btn-ai" "css_element" should contain "Course certificate"
    # And I should see "Create a professional message for your LinkedIn post in one click"
    # And "div.ai-bar__panel[aria-hidden='true']" "css_element" should exist
    # The card injected by the browser expands like the one rendered by the server, because the
    # opening logic is delegated on the panel root instead of living in an inline script:
    # And I click on "button#btn-ai" "css_element"
    # And "div.ai-bar.is-open" "css_element" should exist

  # MDL-E2E-008 is implemented: the share action detects a window the browser refused to open and
  # announces it, and confirms the share when the window did open. Verifying it needs a real browser
  # that can block a pop-up, which this environment has no container for, so the steps stay commented.
  @javascript @MDL-E2E-008 @skip_pending
  Scenario: Share action reports a blocked popup and confirms a completed share
    Given this scenario is pending because "MDL-E2E-008: the popup blocked warning and the share confirmation are implemented; asserting them needs a real browser able to block a pop-up, which is not available in this environment"
    # When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "a#btn-normal" "css_element"
    # Then I should see "Enable pop-ups to continue." in the "div.lsc-live" "css_element"
    # And I should see "LinkedIn share completed." in the "div.lsc-live" "css_element"
