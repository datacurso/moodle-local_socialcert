<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the conditions under which the share panel is injected.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_socialcert;

use core\hook\output\before_footer_html_generation;
use mod_customcert\certificate;

/**
 * Tests for local_socialcert\hook_callbacks.
 *
 * Real core hook instances are built with a real core renderer, and the tests assert whether
 * the callback contributes HTML to the footer or not.
 *
 * @package     local_socialcert
 * @category    test
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_socialcert\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * MDL-INT-002: the panel is injected at the end of the activity view for an authenticated
     * non guest user when the page has an associated course module.
     */
    public function test_panel_is_injected_on_the_certificate_view_for_an_authenticated_user(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $output = $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view');

        $this->assertStringContainsString('local-socialcert', $output);
        $this->assertStringContainsString('data-cmid="' . $customcert->cmid . '"', $output);
    }

    /**
     * MDL-INT-002: the panel is not injected for guest users.
     */
    public function test_panel_is_not_injected_for_guest_users(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $this->setGuestUser();

        $output = $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view');

        $this->assertSame('', $output);
    }

    /**
     * MDL-INT-002: the panel is not injected on other pages of the same activity, such as the
     * issues report, because the page type does not match the activity view.
     */
    public function test_panel_is_not_injected_on_other_pages_of_the_same_activity(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $output = $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-report');

        $this->assertSame('', $output);
    }

    /**
     * MDL-INT-002: the panel is not injected on the view of a different activity type.
     */
    public function test_panel_is_not_injected_on_other_activity_types(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Welcome']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $output = $this->run_footer_hook($course, $page->cmid, 'mod-page-view');

        $this->assertSame('', $output);
    }

    /**
     * MDL-INT-002: the plugin adds no pages, menus or blocks of its own, so its only
     * integration point is the hook callback declared in db/hooks.php.
     *
     * The expected number of callbacks went from two to one on purpose: the callback that declared
     * the stylesheet was registered against a hook class that does not exist in Moodle 4.5, so it
     * never ran, and it was redundant anyway (see MDL-INT-021).
     */
    public function test_plugin_adds_no_pages_menus_or_blocks(): void {
        global $CFG;
        $this->resetAfterTest();

        $plugindir = $CFG->dirroot . '/local/socialcert';

        // No lib.php means no navigation or menu extension callbacks.
        $this->assertFileDoesNotExist($plugindir . '/lib.php');
        // No entry point page of its own.
        $this->assertFileDoesNotExist($plugindir . '/index.php');
        // No bundled blocks.
        $this->assertDirectoryDoesNotExist($plugindir . '/blocks');
        // Only the footer output hook is declared.
        $callbacks = [];
        require($plugindir . '/db/hooks.php');
        $this->assertCount(1, $callbacks);
        $this->assertSame(before_footer_html_generation::class, $callbacks[0]['hook']);
    }

    /**
     * MDL-INT-021: every hook the plugin declares exists in this Moodle version, so no declaration
     * is dead, and the styles of the panel do not depend on any of them.
     *
     * Previously the plugin declared \core\hook\output\before_standard_html_head_generation, which
     * does not exist in Moodle 4.5 (the real hook is before_standard_head_html_generation), so the
     * callback that added styles.css never ran. The declaration is gone because Moodle already adds
     * the styles.css of every plugin to the CSS of the theme, which is the mechanism that has been
     * styling the panel all along.
     */
    public function test_declared_hooks_exist_and_the_panel_styles_need_no_callback(): void {
        global $CFG;

        $plugindir = $CFG->dirroot . '/local/socialcert';

        $callbacks = [];
        require($plugindir . '/db/hooks.php');

        foreach ($callbacks as $callback) {
            $this->assertTrue(
                class_exists($callback['hook']),
                "The declared hook '{$callback['hook']}' does not exist in this Moodle version, " .
                'so its callback would never run.'
            );
            $this->assertTrue(
                is_callable($callback['callback']),
                'Every declared callback must be callable.'
            );
        }

        // The stylesheet Moodle picks up automatically, and the callback that used to declare it.
        $this->assertFileExists($plugindir . '/styles.css');
        $this->assertFalse(
            method_exists(hook_callbacks::class, 'before_standard_html_head_generation'),
            'The callback of the non existent head hook must not survive.'
        );
        $this->assertStringNotContainsString(
            'requires->css',
            (string) file_get_contents($plugindir . '/classes/hook_callbacks.php'),
            'The plugin must not declare its own stylesheet explicitly.'
        );

        // Every rule of the stylesheet is scoped under the root class of the panel, which is what
        // makes the automatic aggregation of the theme safe: the plugin styles the panel and
        // nothing else of the site.
        foreach (self::get_css_selectors($plugindir . '/styles.css') as $selector) {
            $this->assertStringContainsString(
                '.local-socialcert',
                $selector,
                "The selector '{$selector}' is not scoped under the root class of the panel."
            );
        }
    }

    /**
     * MDL-INT-007: the panel is only injected for users who can receive the certificate.
     *
     * Previously skipped: the callback rendered the panel for every authenticated non guest user,
     * so a teacher opening the activity to read the issues report got the panel in its error state
     * underneath it. The callback now demands mod/customcert:receiveissue.
     */
    public function test_panel_is_not_injected_for_a_user_who_cannot_receive_the_certificate(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);

        $context = \context_module::instance($customcert->cmid);

        // A teacher of the course: they manage the activity but never receive its certificate.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse(has_capability('mod/customcert:receiveissue', $context));
        $this->assertSame('', $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view'));

        // A user whose role has the capability prevented, even though they are enrolled.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $studentroleid = $this->get_role_id('student');
        role_change_permission($studentroleid, $context, 'mod/customcert:receiveissue', CAP_PREVENT);
        $this->setUser($student);
        $this->assertFalse(has_capability('mod/customcert:receiveissue', $context));
        $this->assertSame('', $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view'));
    }

    /**
     * MDL-INT-007: the panel is injected for the student, who is the user who receives the
     * certificate of the activity.
     */
    public function test_panel_is_injected_for_a_user_who_can_receive_the_certificate(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertTrue(has_capability('mod/customcert:receiveissue', \context_module::instance($customcert->cmid)));

        $output = $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view');

        $this->assertStringContainsString('local-socialcert', $output);
    }

    /**
     * MDL-INT-015: the panel is not injected for a role without the capability of the plugin.
     *
     * The callback demands local/socialcert:viewsharepanel on top of the checks it already made, so
     * an administrator can take the panel away from a role without touching mod_customcert. The
     * default archetype of the capability is the student, which is also the only archetype of
     * mod/customcert:receiveissue, so the same student is asserted with and without the capability:
     * the panel disappears because of the capability alone.
     */
    public function test_panel_is_not_injected_without_the_capability_of_the_plugin(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $context = \context_module::instance($customcert->cmid);
        $this->setUser($student);

        // By default the student holds the capability, so the panel is there.
        $this->assertTrue(has_capability('local/socialcert:viewsharepanel', $context));
        $this->assertStringContainsString(
            'local-socialcert',
            $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view')
        );

        // With the capability prevented for the role, nothing is injected any more.
        role_change_permission($this->get_role_id('student'), $context, 'local/socialcert:viewsharepanel', CAP_PREVENT);
        $this->assertFalse(has_capability('local/socialcert:viewsharepanel', $context));
        $this->assertTrue(
            has_capability('mod/customcert:receiveissue', $context),
            'The user still receives the certificate: only the capability of the plugin was taken away.'
        );
        $this->assertSame('', $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view'));
    }

    /**
     * MDL-INT-006: the panel is not shown on the required time notice page.
     *
     * Previously skipped: both intermediate pages keep the 'mod-customcert-view' page type and the
     * same course module, so the callback injected the panel in its error state on both of them.
     * The callback now evaluates the very same conditions mod/customcert/view.php evaluates before
     * replacing the page with the notice.
     */
    public function test_panel_is_not_injected_on_the_required_time_notice_page(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timed = $generator->create_module('customcert', [
            'course'       => $course->id,
            'name'         => 'Timed certificate',
            'requiredtime' => 60,
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        // The student has spent no time in the course, so view.php answers with the notice.
        $this->assertSame('', $this->run_footer_hook($course, $timed->cmid, 'mod-customcert-view'));

        // The very same student and the very same activity do get the panel as soon as the activity
        // stops demanding a minimum time, which is what proves the notice is what keeps the panel
        // away and not the user.
        $DB->set_field('customcert', 'requiredtime', 0, ['id' => $timed->id]);
        $this->assertStringContainsString(
            'local-socialcert',
            $this->run_footer_hook($course, $timed->cmid, 'mod-customcert-view')
        );
    }

    /**
     * MDL-INT-006: the panel is not shown on the issue deletion confirmation page.
     *
     * The confirmation is only reachable by a user who can manage the activity, and correction of
     * MDL-INT-007 already keeps teachers and managers away from the panel because they cannot
     * receive the certificate. The site administrator is the case this check really covers: they
     * hold every capability, so they both receive certificates and delete issues.
     */
    public function test_panel_is_not_injected_on_the_issue_deletion_confirmation_page(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $issueid = certificate::issue_certificate($customcert->id, $student->id);

        $this->setAdminUser();

        // Without the deletion parameters the administrator gets the panel.
        $this->assertStringContainsString(
            'local-socialcert',
            $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view')
        );

        // While the deletion is waiting for confirmation the panel stays out of the page.
        $_GET['deleteissue'] = $issueid;
        try {
            $this->assertSame('', $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view'));

            // Once the deletion is confirmed view.php redirects, so the confirmation is over and the
            // panel is no longer suppressed.
            $_GET['confirm'] = 1;
            $this->assertStringContainsString(
                'local-socialcert',
                $this->run_footer_hook($course, $customcert->cmid, 'mod-customcert-view')
            );
        } finally {
            unset($_GET['deleteissue'], $_GET['confirm']);
        }
    }

    /**
     * Selectors declared by a stylesheet, ignoring at rules and keyframe steps.
     *
     * @param string $path Absolute path of the stylesheet.
     * @return string[] Declared selectors.
     */
    private static function get_css_selectors(string $path): array {
        $css = (string) file_get_contents($path);

        // Comments first, then the keyframe blocks, whose steps are percentages and not selectors.
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = (string) preg_replace('~@keyframes\s+[\w-]+\s*\{(?:[^{}]|\{[^{}]*\})*\}~s', '', $css);

        // The text between the end of the previous block (or the opening of an at rule such as
        // @media) and the opening brace of a rule is its selector list. Selector lists containing
        // an at sign are the at rules themselves, which declare no selector of their own.
        preg_match_all('~(?:^|[{}])\s*([^{}@]+?)\s*\{~s', $css, $matches);

        $selectors = [];
        foreach ($matches[1] as $selectorlist) {
            foreach (explode(',', $selectorlist) as $selector) {
                $selector = trim($selector);
                if ($selector !== '') {
                    $selectors[] = $selector;
                }
            }
        }

        return $selectors;
    }

    /**
     * ID of a role by its short name.
     *
     * @param string $shortname Short name of the role.
     * @return int Role ID.
     */
    private function get_role_id(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * MDL-INT-008: browsing site pages without an associated course module produces no
     * debugging notices originating from the plugin.
     *
     * Previously skipped: before_footer_html_generation() dereferenced $PAGE->cm->id before
     * validating the page type, so every page without a course module emitted a notice while
     * developer debugging was on. The callback now validates the page type first, so the case is
     * implemented and asserts the silent behaviour on a page with no course module.
     */
    public function test_no_debugging_notices_are_emitted_on_pages_without_a_course_module(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();

        set_debugging(DEBUG_DEVELOPER);
        $this->assertTrue((bool) $CFG->debugdeveloper, 'Developer debugging is the precondition of the case.');

        $this->setUser($this->getDataGenerator()->create_user());

        // A site page with no course module associated, such as the user dashboard.
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/my/index.php');
        $PAGE->set_pagetype('my-index');

        $hook = new before_footer_html_generation($PAGE->get_renderer('core'));

        hook_callbacks::before_footer_html_generation($hook);

        $this->assertSame('', $hook->get_output(), 'No panel may be injected on a page without a module.');
        $this->assertDebuggingNotCalled();
    }

    /**
     * Runs the footer hook callback against a page configured for the given course module and
     * page type, and returns the HTML the callback contributed.
     *
     * @param \stdClass $course Course record owning the course module.
     * @param int       $cmid Course module ID to set on the page.
     * @param string    $pagetype Page type to simulate.
     * @return string HTML collected by the hook.
     */
    private function run_footer_hook(\stdClass $course, int $cmid, string $pagetype): string {
        global $PAGE;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        // The course module and course must be set before the theme is initialised.
        $PAGE->set_url('/mod/customcert/view.php', ['id' => $cmid]);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagetype($pagetype);

        $renderer = $PAGE->get_renderer('core');
        $hook = new before_footer_html_generation($renderer);

        hook_callbacks::before_footer_html_generation($hook);

        return $hook->get_output();
    }
}
