@local @local_socialcert
Feature: Share panel injection scope and share button states
  In order to keep the sharing call to action coherent with the certificate state
  As a student of a course with a custom certificate activity
  I need the panel to appear only on the certificate view and to reflect my own issue

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
      | student2 | Student   | Two      | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity   | name               | intro                    | course | idnumber |
      | customcert | Course certificate | Course certificate intro | C1     | cert1    |
      | page       | Course page        | Course page intro        | C1     | page1    |
    And the following config values are set as admin:
      | organizationid   | 98765     | local_socialcert |
      | organizationname | Buen Data | local_socialcert |
      | enableai         | 1         | local_socialcert |

  @MDL-INT-002
  Scenario: Panel is injected on the certificate activity view for an authenticated student
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    Then "div.local-socialcert" "css_element" should exist
    And "div.local-socialcert section.lsc-hero" "css_element" should exist
    And "div.local-socialcert a#btn-normal" "css_element" should exist
    And I should see "Share your achievement on LinkedIn"

  @MDL-INT-002
  Scenario: Panel is not injected outside the certificate activity view
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I am on site homepage
    Then "div.local-socialcert" "css_element" should not exist
    And I am on "Course 1" course homepage
    And "div.local-socialcert" "css_element" should not exist
    And I am on the "Course page" "page activity" page
    And "div.local-socialcert" "css_element" should not exist

  @MDL-INT-002
  Scenario: Panel is not injected for visitors without a session
    When I am on the "Course certificate" "customcert activity" page
    Then "div.local-socialcert" "css_element" should not exist

  @MDL-INT-003
  Scenario: Share button is active and uses the LinkedIn palette once the certificate is issued
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "a#btn-normal.btn-social" "css_element" should exist
    And "a#btn-normal.disabled" "css_element" should not exist
    And "a#btn-normal .lsc-cta__icon svg" "css_element" should exist
    And "div.lsc-error-message" "css_element" should not exist
    And I should see "Share on LinkedIn"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "https://www.linkedin.com/profile/add"
    And the "aria-disabled" attribute of "a#btn-normal" "css_element" should not be set

  @MDL-INT-003
  Scenario: Share button is disabled with the error notice while no certificate has been issued
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    Then "a#btn-normal.disabled" "css_element" should exist
    And the "href" attribute of "a#btn-normal" "css_element" should not be set
    And the "aria-disabled" attribute of "a#btn-normal" "css_element" should contain "true"
    And the "tabindex" attribute of "a#btn-normal" "css_element" should contain "-1"
    And "div.local-socialcert[data-network='linkedin']" "css_element" should not exist
    And "div.lsc-error-message" "css_element" should exist
    And I should see "You’ll need to have an issued certificate before you can share it on LinkedIn."

  # Step 4 of MDL-INT-003: the panel state must depend exclusively on the issue of the user in
  # session, with no leak between users of the same activity.
  @MDL-INT-003
  Scenario: Panel state reflects only the issue of the user in session
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "a#btn-normal.disabled" "css_element" should not exist
    And I am on the "Course certificate" "customcert activity" page logged in as "student2"
    And "a#btn-normal.disabled" "css_element" should exist
    And "div.lsc-error-message" "css_element" should exist
    And I should see "You’ll need to have an issued certificate before you can share it on LinkedIn."

  # [Pendiente:skip] MDL-INT-006 — the panel is currently injected, in error state, on the
  # required-time notice page and on the issue deletion confirmation page, where sharing makes
  # no sense. Steps commented out on purpose so the defect is not recorded as correct.
  @MDL-INT-006 @skip_pending
  Scenario: Panel is not injected on the intermediate pages of the certificate activity
    Given this scenario is pending because "MDL-INT-006 [Pendiente:skip]: the panel is currently injected in error state on the required time notice page and on the issue deletion confirmation page"
    # And the following "activities" exist:
    #   | activity   | name              | course | idnumber | requiredtime |
    #   | customcert | Timed certificate | C1     | cert2    | 1            |
    # And the following "users" exist:
    #   | username | firstname | lastname | email                |
    #   | teacher1 | Teacher   | One      | teacher1@example.com |
    # And the following "course enrolments" exist:
    #   | user     | course | role           |
    #   | teacher1 | C1     | editingteacher |
    # When I am on the "Timed certificate" "customcert activity" page logged in as "student1"
    # Then I should see "You must spend at least a minimum of"
    # And "div.local-socialcert" "css_element" should not exist
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page logged in as "teacher1"
    # And I click on ".delete-icon" "css_element" in the "Student One" "table_row"
    # And "div.local-socialcert" "css_element" should not exist

  # [Pendiente:skip] MDL-INT-007 — the panel is rendered for every authenticated user; there is
  # no capability nor receiveissue check in hook_callbacks, so teachers and managers also see it
  # (in error state) below the issues report. Steps commented out on purpose.
  @MDL-INT-007 @skip_pending
  Scenario: Panel is only shown to users who can receive the certificate
    Given this scenario is pending because "MDL-INT-007 [Pendiente:skip]: hook_callbacks renders the panel for every authenticated non guest user; there is no capability or receiveissue check, so teachers and managers also see it"
    # And the following "users" exist:
    #   | username | firstname | lastname | email                |
    #   | teacher1 | Teacher   | One      | teacher1@example.com |
    #   | manager1 | Manager   | One      | manager1@example.com |
    # And the following "course enrolments" exist:
    #   | user     | course | role           |
    #   | teacher1 | C1     | editingteacher |
    # And the following "role assigns" exist:
    #   | user     | role    | contextlevel | reference |
    #   | manager1 | manager | System       |           |
    # When I am on the "Course certificate" "customcert activity" page logged in as "teacher1"
    # Then "div.local-socialcert" "css_element" should not exist
    # And I am on the "Course certificate" "customcert activity" page logged in as "manager1"
    # And "div.local-socialcert" "css_element" should not exist

  # [Pendiente:skip] MDL-E2E-005 — the panel exists only on the web view of the certificate
  # activity. There is no panel on the "My certificates" profile page and no mobile app support.
  # The mobile app layer cannot be exercised by Behat at all. Steps commented out on purpose.
  @MDL-E2E-005 @skip_pending
  Scenario: Panel is available on the My certificates profile page
    Given this scenario is pending because "MDL-E2E-005 [Pendiente:skip]: the panel only exists on the web view of the certificate activity; there is no panel on the My certificates profile page and the mobile app layer cannot be exercised by Behat"
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I follow "My certificates"
    # Then "div.local-socialcert" "css_element" should exist
    # And I should see "Share your achievement on LinkedIn"
