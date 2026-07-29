@local @local_socialcert
Feature: Share an issued certificate on LinkedIn from the certificate activity panel
  In order to publish a verified achievement without retyping any credential data
  As a student with an issued certificate
  I need the share panel to expose a prefilled LinkedIn add-to-profile link

  # Scenarios tagged @skip_pending describe behaviour required by the test case definition
  # that the plugin does not implement yet ([Pendiente:skip] in socialcert-1.1.2.md). Their
  # steps are deliberately commented out so no scenario can ever report a known defect as
  # correct behaviour. Exclude them explicitly when running the suite:
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

  # [Pendiente:skip] MDL-E2E-002 — the panel only becomes enabled after a page reload or a new
  # visit; there is no automatic refresh nor any notice after the certificate is downloaded.
  # The steps below describe the required behaviour and are commented out on purpose so they
  # cannot pass against the current implementation.
  @javascript @MDL-E2E-002 @skip_pending
  Scenario: Share panel becomes enabled right after the certificate is obtained for the first time
    Given this scenario is pending because "MDL-E2E-002 [Pendiente:skip]: the panel is only enabled after reloading or re-entering the page; there is no automatic refresh nor any notice to the user"
    # When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # Then "a#btn-normal.disabled" "css_element" should exist
    # And I should see "You’ll need to have an issued certificate before you can share it on LinkedIn."
    # And I press "View certificate"
    # And "a#btn-normal.disabled" "css_element" should not exist
    # And the "href" attribute of "a#btn-normal" "css_element" should contain "https://www.linkedin.com/profile/add"
    # And "div.lsc-error-message" "css_element" should not exist

  # [Pendiente:skip] MDL-E2E-008 — neither the popup-blocked warning nor the share confirmation
  # is ever displayed. Both strings exist and are translated, but no code path renders them and
  # there is no popup-blocking detection at all. Steps commented out on purpose.
  @javascript @MDL-E2E-008 @skip_pending
  Scenario: Share action reports a blocked popup and confirms a completed share
    Given this scenario is pending because "MDL-E2E-008 [Pendiente:skip]: neither the popup blocked warning nor the share confirmation is ever rendered; both strings are translated but no code path shows them and there is no popup blocking detection"
    # When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "a#btn-normal" "css_element"
    # Then I should see "Enable pop-ups to continue." in the "div.lsc-live" "css_element"
    # And I should see "LinkedIn share completed." in the "div.lsc-live" "css_element"
