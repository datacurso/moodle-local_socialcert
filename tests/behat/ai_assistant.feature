@local @local_socialcert
Feature: AI assistant card on the certificate share panel
  In order to compose a LinkedIn post without writing it from scratch
  As a student with an issued certificate
  I need the AI assistant card to follow the plugin configuration, the interface language and its documented lifecycle

  # Scenarios tagged @skip_pending describe behaviour required by the test case definition that
  # cannot be exercised here, either because the plugin does not implement it ([Pendiente:skip] /
  # [Pendiente:fail] in socialcert-1.1.3.md) or because it needs the external Datacurso AI service
  # and credits manager, which are not reachable from the plugin CI. Their steps are deliberately
  # commented out so no scenario can ever report a known defect as correct behaviour.
  # Exclude them explicitly when running the suite:
  #   --tags="@local_socialcert&&~@skip_pending"
  #
  # deliveryoption is set to D (download) so that pressing "View certificate" issues the
  # certificate without navigating the browser into an inline PDF viewer, which keeps the
  # @javascript scenarios on the activity page.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username  | firstname  | lastname | email                 | lang |
      | student1  | Student    | One      | student1@example.com  | en   |
      | studentes | Estudiante | Dos      | studentes@example.com | es   |
    And the following "course enrolments" exist:
      | user      | course | role    |
      | student1  | C1     | student |
      | studentes | C1     | student |
    And the following "activities" exist:
      | activity   | name               | intro                    | course | idnumber | deliveryoption |
      | customcert | Course certificate | Course certificate intro | C1     | cert1    | D              |
    And the following config values are set as admin:
      | organizationid   | 98765     | local_socialcert |
      | organizationname | Buen Data | local_socialcert |
      | enableai         | 1         | local_socialcert |

  # Visual layer of MDL-INT-001 step 4. The certificate is issued first on purpose: the assistant is
  # only available to a user with an issued certificate, which MDL-E2E-004 covers on the service
  # layer, so this scenario never asserts the card for a user without one.
  @MDL-INT-001
  Scenario: AI assistant card is rendered when AI is enabled globally
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "div.lsc-response-wrap" "css_element" should exist
    And "div.bd-post[data-ai-composer]" "css_element" should exist
    And "button#btn-ai[data-action='run-ai']" "css_element" should exist
    And I should see "Create a professional message for your LinkedIn post in one click"
    And "a#btn-normal" "css_element" should exist

  @MDL-INT-001
  Scenario: Disabling AI removes the assistant card and keeps the share button available
    Given the following config values are set as admin:
      | enableai | 0 | local_socialcert |
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "div.lsc-response-wrap" "css_element" should not exist
    And "button#btn-ai" "css_element" should not exist
    And I should not see "Create a professional message for your LinkedIn post in one click"
    And "a#btn-normal" "css_element" should exist
    And "a#btn-normal.disabled" "css_element" should not exist
    And I should see "Share your achievement on LinkedIn"
    And I should see "Share on LinkedIn"
    And the "href" attribute of "a#btn-normal" "css_element" should contain "https://www.linkedin.com/profile/add"

  # Steps 1 and 2 of MDL-E2E-006 that need no browser: the accessible names of the assistant
  # controls and the label of its region are rendered by the server from language strings, so they
  # follow the interface language instead of being hardcoded in Spanish inside the template.
  @MDL-E2E-006
  Scenario: Assistant controls carry their accessible name in the interface language
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then I should see "Activate AI" in the "button#btn-ai" "css_element"
    And the "aria-label" attribute of "button.bd-message__btn-copy" "css_element" should contain "Copy the generated text"
    And the "title" attribute of "button.bd-message__btn-copy" "css_element" should contain "Copy the generated text"
    And the "aria-label" attribute of "div.ai-bar" "css_element" should contain "AI assistant to draft your post"
    And the "alt" attribute of "img.logo-datacurso" "css_element" should contain "Datacurso logo"
    And the "aria-label" attribute of "div.bd-avatar" "css_element" should contain "Profile picture of Student One"

  @MDL-E2E-007
  Scenario: Panel and assistant call to action are shown in English for an English interface
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then I should see "Share your achievement on LinkedIn"
    And I should see "We’ll post a verifiable link to your certificate."
    And I should see "Share on LinkedIn"
    And I should see "Create a professional message for your LinkedIn post in one click"

  # Moodle only honours a session language whose pack is installed on the site, and the acceptance
  # test site ships English only (the same is true of the plugin CI), so a user with lang = es falls
  # back to English and the Spanish strings never render. The content of every bundled language pack
  # is therefore verified at the unit level instead, in the MDL-INT-013 language pack tests.
  @MDL-E2E-007 @skip_pending
  Scenario: Panel and assistant call to action are shown in Spanish for a Spanish interface
    Given this scenario is pending because "MDL-E2E-007: rendering the panel in Spanish needs the Spanish language pack installed on the test site, which ships English only; language pack content is covered by the MDL-INT-013 unit tests"
    # When I am on the "Course certificate" "customcert activity" page logged in as "studentes"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # Then I should see "Comparte tu logro en LinkedIn"
    # And I should see "Publicaremos un enlace verificable de tu certificado."
    # And I should see "Compartir en LinkedIn"
    # And I should see "Crea un mensaje profesional para tu publicación de LinkedIn con un solo clic"
    # And I should not see "Share your achievement on LinkedIn"

  # The language gaps of MDL-INT-013 are fixed: the seven packs now declare the whole English key
  # set, each one written in its own language and with its own name of the plugin. What still blocks
  # this scenario is the very same environment limitation as the one above — Moodle only honours a
  # session language whose pack is installed on the site, and the test site ships English only, so a
  # user with lang = fr, pt, id, ru or de falls back to English and nothing of the translation is
  # rendered. The content of the seven packs is asserted at the unit level, in the MDL-INT-013 cases
  # of plugin_compliance_test (key set parity, own language per pack and translated plugin name).
  @MDL-E2E-007 @skip_pending
  Scenario: Panel is fully translated in the seven bundled languages
    Given this scenario is pending because "MDL-E2E-007: rendering the panel in the other six languages needs their language packs installed on the test site, which ships English only; the content of the seven packs is covered by the MDL-INT-013 unit tests"
    # And the following "users" exist:
    #   | username  | firstname | lastname | email          | lang |
    #   | studentfr | Etudiant  | Trois    | fr@example.com | fr   |
    #   | studentpt | Estudante | Quatro   | pt@example.com | pt   |
    #   | studentid | Siswa     | Lima     | id@example.com | id   |
    #   | studentru | Student   | Shest    | ru@example.com | ru   |
    #   | studentde | Student   | Sieben   | de@example.com | de   |
    # When I am on the "Course certificate" "customcert activity" page logged in as "studentfr"
    # Then I should not see "Share your achievement on LinkedIn"
    # And I should see "Partagez votre réussite sur LinkedIn"

  # Steps 1 and 3 of MDL-E2E-009 that need no network: the card starts collapsed, it expands when
  # the assistant button is pressed, and nothing is persisted, so a reload brings the card back
  # collapsed and empty. The generated text itself is NOT asserted because the Datacurso AI
  # service is unreachable from CI; see the @skip_pending scenario below.
  @javascript @MDL-E2E-009
  Scenario: Assistant card starts collapsed, expands on demand and keeps no result after a reload
    When I am on the "Course certificate" "customcert activity" page logged in as "student1"
    And I press "View certificate"
    And I am on the "Course certificate" "customcert activity" page
    Then "div.ai-bar__panel[aria-hidden='true']" "css_element" should exist
    And "div.ai-bar.is-open" "css_element" should not exist
    And "#copyBtn[hidden]" "css_element" should exist
    And I click on "button#btn-ai" "css_element"
    And "div.ai-bar.is-open" "css_element" should exist
    And I reload the page
    And "div.ai-bar.is-open" "css_element" should not exist
    And "div.ai-bar__panel[aria-hidden='true']" "css_element" should exist
    And "#copyBtn[hidden]" "css_element" should exist

  # Steps 1 and 2 of MDL-E2E-009 need a stubbed AI service response: the button must stay disabled
  # while a generation is running, there must be no way to cancel it, and every press must issue a
  # new request with no caching. None of that is observable without a deterministic service
  # response, and local_socialcert has no Behat stub for local_socialcert_get_ai_response.
  # Steps commented out on purpose.
  @javascript @MDL-E2E-009 @skip_pending
  Scenario: Assistant button is blocked during a generation and every press issues a new request
    Given this scenario is pending because "MDL-E2E-009 steps 1 and 2 need a deterministic AI response: the Datacurso AI service is unreachable from the plugin CI and local_socialcert provides no Behat stub for local_socialcert_get_ai_response"
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "button#btn-ai" "css_element"
    # Then "button#btn-ai:disabled" "css_element" should exist
    # And "#ai-card.skeleton-card" "css_element" should exist
    # And I should see "Generating…"

  # MDL-E2E-006 — the accessible names of the controls are covered by the scenario above, which
  # needs no browser. What is left here is the feedback of each action, which does need one: the
  # skeleton restored on every generation, the spinner state of the button and the copy
  # confirmation, all of them only observable with JavaScript running. Steps commented out on
  # purpose so no unverified behaviour is reported as correct.
  @javascript @MDL-E2E-006 @skip_pending
  Scenario: Assistant and copy controls give visible feedback on every action
    Given this scenario is pending because "MDL-E2E-006: the remaining steps need a real browser (Selenium/Chrome), which this environment does not provide; the accessible names of the controls are verified without JavaScript in the scenario above"
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "button#btn-ai" "css_element"
    # Then "#ai-card.skeleton-card" "css_element" should exist
    # And I click on "button.bd-message__btn-copy" "css_element"
    # And I should see "Copied ✔"

  # [Pendiente:fail] MDL-INT-018 — SECURITY DEFECT, NOT A MISSING FEATURE.
  # amd/src/actions.js typewriter() detects markup with /<[^>]+>/ and assigns el.innerHTML = text
  # without any sanitisation, so a service response containing event handlers or executable markup
  # runs in the user's browser. This scenario is intentionally left unimplemented instead of being
  # written against the live service: asserting anything here without controlling the response would
  # produce a false green while the XSS remains open. Implementing it requires a Behat stub (or a
  # mocked external function) able to inject an arbitrary AI response; the safe-rendering assertion
  # is covered at the unit/JS layer. This case MUST NOT be reported as passing until the fix lands.
  @javascript @MDL-INT-018 @skip_pending
  Scenario: AI response with executable markup is rendered safely in the card
    Given this scenario is pending because "MDL-INT-018 [Pendiente:fail] OPEN SECURITY DEFECT: typewriter() assigns el.innerHTML with the unsanitised service response, so markup with event handlers executes in the browser. Verifying it needs a Behat stub able to inject the response; this case MUST NOT be reported as passing until the XSS is fixed"
    # Requires a stubbed local_socialcert_get_ai_response returning, for example,
    # '<img src=x onerror="window.xssRan = true">' and then asserting that no script executed and
    # that the markup was escaped as text:
    # Then I should see "<img src=x onerror=\"window.xssRan = true\">" in the "#ai-response" "css_element"
    # And "#ai-response img" "css_element" should not exist

  # SYS-E2E-001 depends on the full connected system (Moodle + Datacurso AI service + credits
  # manager) with a licence holding credits. Neither the service nor the credits manager is
  # reachable from the plugin CI, and the plugin exposes no stub, so the end to end generation,
  # the typewriter output, the clipboard copy and the exact request payload are verified in the
  # integration environment instead. Steps commented out on purpose.
  @javascript @SYS-E2E-001 @skip_pending
  Scenario: Full AI generation flow from the panel with the external system connected
    Given this scenario is pending because "SYS-E2E-001 depends on external systems: the Datacurso AI service and the credits manager must be connected with a licence holding credits, which is not available in the plugin CI; it runs in the integration environment"
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "button#btn-ai" "css_element"
    # Then "div.ai-bar.is-open" "css_element" should exist
    # And "#ai-card.skeleton-card" "css_element" should exist
    # And I should see "Course certificate" in the "#ai-response" "css_element"
    # And I click on "button.bd-message__btn-copy" "css_element"

  # SYS-E2E-002 needs a manipulable licence in the external credits manager (no credits, not
  # authorised, low rate limit) plus a forced service failure. Those states cannot be produced from
  # the plugin CI, so the message classification per error type and per interface language is
  # verified in the integration environment. Steps commented out on purpose.
  @javascript @SYS-E2E-002 @skip_pending
  Scenario: Service errors are reflected in the assistant card with their specific message
    Given this scenario is pending because "SYS-E2E-002 depends on external systems: it needs a manipulable licence in the credits manager (no credits, not authorised, low rate limit) plus a forced service failure, which cannot be produced from the plugin CI; it runs in the integration environment"
    # And I am on the "Course certificate" "customcert activity" page logged in as "student1"
    # And I press "View certificate"
    # And I am on the "Course certificate" "customcert activity" page
    # And I click on "button#btn-ai" "css_element"
    # Then I should see "Insufficient AI credits." in the "#ai-response" "css_element"
    # And I should see "Your license is not allowed to perform this request." in the "#ai-response" "css_element"
    # And I should see "An error occurred while generating the content. Please try again later." in the "#ai-response" "css_element"
