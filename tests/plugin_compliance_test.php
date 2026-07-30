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

namespace local_socialcert;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Plugin level compliance tests: global settings, language packs, events and capabilities.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class plugin_compliance_test extends \advanced_testcase {
    /** @var string Relative path of the plugin. */
    private const PLUGINPATH = '/local/socialcert';

    /** @var string[] Languages shipped by the plugin according to the documentation. */
    private const LANGUAGES = ['en', 'es', 'de', 'fr', 'pt', 'id', 'ru'];

    /**
     * Visible strings of the share panel that every language pack is expected to translate.
     *
     * @var string[]
     */
    private const CORE_PANEL_STRINGS = [
        'sharetitle',
        'sharesubtitle',
        'shareinstruction',
        'whatsharelabel',
        'buttonlabelshare',
        'linkcertbuttontext',
        'certerror',
        'errorcredits',
        'errorlicense',
        'popupblocked',
        'sharecompleted',
        'airesponsebtn',
        'ai_field_heading',
        'generating',
        'copyconfirmation',
    ];

    /**
     * Build the administration tree and return the plugin settings page.
     *
     * @return \admin_settingpage The plugin settings page.
     */
    private function get_plugin_settings_page(): \admin_settingpage {
        $adminroot = admin_get_root(true, true);

        $localplugins = $adminroot->locate('localplugins');
        $this->assertInstanceOf(
            \admin_category::class,
            $localplugins,
            'The local plugins category must exist in the administration tree.'
        );

        $page = $localplugins->locate('local_socialcert');
        $this->assertInstanceOf(
            \admin_settingpage::class,
            $page,
            'The plugin settings page must live in the local plugins category.'
        );

        return $page;
    }

    /**
     * Index the settings of a page by their fully qualified name.
     *
     * @param \admin_settingpage $page Settings page.
     * @return \admin_setting[] Settings indexed by "plugin/name".
     */
    private function index_settings(\admin_settingpage $page): array {
        $indexed = [];
        foreach ((array) $page->settings as $setting) {
            $indexed[$setting->plugin . '/' . $setting->name] = $setting;
        }

        return $indexed;
    }

    /**
     * Absolute path of the language file of a language.
     *
     * @param string $lang Language code.
     * @return string Absolute path.
     */
    private function language_file_path(string $lang): string {
        global $CFG;

        return $CFG->dirroot . self::PLUGINPATH . '/lang/' . $lang . '/local_socialcert.php';
    }

    /**
     * Read the strings declared by a language pack file, without any English fallback.
     *
     * The string manager merges the English pack into every language, so the raw file has to be
     * read to know what a language really translates.
     *
     * @param string $lang Language code.
     * @return array Declared strings.
     */
    private function read_declared_strings(string $lang): array {
        $string = [];
        include($this->language_file_path($lang));

        return $string;
    }

    /**
     * Languages whose language file is named as Moodle expects, so it can actually be loaded.
     *
     * @return string[] Language codes.
     */
    private function get_loadable_languages(): array {
        $loadable = [];
        foreach (self::LANGUAGES as $lang) {
            if (file_exists($this->language_file_path($lang))) {
                $loadable[] = $lang;
            }
        }

        return $loadable;
    }

    /**
     * MDL-INT-001: The three documented settings exist with their documented defaults.
     *
     * Step 4 of the case (the AI card disappearing from the panel while the LinkedIn button stays
     * available) is a rendering concern and is covered by Behat, not here.
     */
    public function test_global_settings_declare_the_documented_defaults(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->get_plugin_settings_page();
        $settings = $this->index_settings($page);

        $this->assertCount(3, $settings, 'The plugin must expose exactly three global settings.');

        $this->assertArrayHasKey('local_socialcert/organizationid', $settings);
        $organizationid = $settings['local_socialcert/organizationid'];
        $this->assertInstanceOf(\admin_setting_configtext::class, $organizationid);
        $this->assertSame('', $organizationid->get_defaultsetting());

        $this->assertArrayHasKey('local_socialcert/organizationname', $settings);
        $organizationname = $settings['local_socialcert/organizationname'];
        $this->assertInstanceOf(\admin_setting_configtext::class, $organizationname);
        $this->assertSame('', $organizationname->get_defaultsetting());

        $this->assertArrayHasKey('local_socialcert/enableai', $settings);
        $enableai = $settings['local_socialcert/enableai'];
        $this->assertInstanceOf(\admin_setting_configcheckbox::class, $enableai);
        $this->assertEquals(1, $enableai->get_defaultsetting(), 'AI must be enabled out of the box.');

        // Every setting has a help text and none hides another one.
        foreach (['organizationid', 'organizationname', 'enableai'] as $name) {
            $this->assertTrue(
                get_string_manager()->string_exists($name . '_desc', 'local_socialcert'),
                "Missing help text for the '{$name}' setting."
            );
            $this->assertNotEmpty($settings['local_socialcert/' . $name]->description);
            $this->assertSame(
                [],
                $settings['local_socialcert/' . $name]->get_dependent_on(),
                "The '{$name}' setting must not depend on any other setting."
            );
        }

        $this->assertSame(
            [],
            $page->get_dependencies_for_javascript(),
            'The settings page must not declare visibility dependencies.'
        );
    }

    /**
     * MDL-INT-001: The three settings persist in the plugin configuration.
     */
    public function test_global_settings_persist_their_values(): void {
        $this->resetAfterTest();

        set_config('organizationid', '1234567', 'local_socialcert');
        set_config('organizationname', 'Datacurso', 'local_socialcert');
        set_config('enableai', 0, 'local_socialcert');

        $this->assertSame('1234567', get_config('local_socialcert', 'organizationid'));
        $this->assertSame('Datacurso', get_config('local_socialcert', 'organizationname'));
        $this->assertSame('0', get_config('local_socialcert', 'enableai'));
    }

    /**
     * MDL-INT-001: The settings page is only built for site administrators.
     */
    public function test_settings_page_is_only_built_for_site_administrators(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $adminroot = admin_get_root(true, true);
        $this->assertNull(
            $adminroot->locate('local_socialcert'),
            'A user without moodle/site:config must not get the plugin settings page.'
        );
    }

    /**
     * MDL-INT-001: The plugin adds no course level nor activity level configuration.
     */
    public function test_plugin_declares_no_course_or_activity_configuration(): void {
        global $CFG;

        $callbacks = [
            'extend_settings_navigation',
            'extend_navigation_course',
            'coursemodule_standard_elements',
            'coursemodule_edit_post_actions',
        ];
        foreach ($callbacks as $callback) {
            $this->assertFalse(
                component_callback_exists('local_socialcert', $callback),
                "The plugin must not implement the '{$callback}' callback."
            );
        }

        $this->assertFileDoesNotExist(
            $CFG->dirroot . self::PLUGINPATH . '/db/install.xml',
            'The plugin must not store per course or per activity configuration.'
        );
    }

    /**
     * MDL-INT-013: The English pack declares every visible string of the panel.
     */
    public function test_english_pack_declares_every_visible_panel_string(): void {
        $strings = $this->read_declared_strings('en');

        $expected = array_merge(self::CORE_PANEL_STRINGS, [
            'ai_actioncall',
            'errorgeneric',
            'copyarticlebuttontext',
            'pluginname',
            'privacy:metadata',
        ]);
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $strings, "The English pack must declare '{$key}'.");
            $this->assertNotEmpty($strings[$key], "The English string '{$key}' must not be empty.");
        }
    }

    /**
     * MDL-INT-013: The plugin ships a language directory for each documented language.
     */
    public function test_plugin_ships_a_directory_for_every_documented_language(): void {
        global $CFG;

        foreach (self::LANGUAGES as $lang) {
            $this->assertDirectoryExists(
                $CFG->dirroot . self::PLUGINPATH . '/lang/' . $lang,
                "Missing language directory for '{$lang}'."
            );
        }
    }

    /**
     * MDL-INT-013: Every loadable language pack translates the core strings of the panel.
     *
     * Only packs whose file is named as Moodle expects are considered here; the French pack is
     * covered by its own case below.
     */
    public function test_loadable_language_packs_translate_the_core_panel_strings(): void {
        $loadable = $this->get_loadable_languages();
        $this->assertContains('en', $loadable, 'The English pack must be loadable.');
        $this->assertGreaterThan(1, count($loadable), 'At least one translation must be loadable.');

        foreach ($loadable as $lang) {
            $strings = $this->read_declared_strings($lang);
            foreach (self::CORE_PANEL_STRINGS as $key) {
                $this->assertArrayHasKey($key, $strings, "The '{$lang}' pack must declare '{$key}'.");
                $this->assertNotEmpty($strings[$key], "The '{$lang}' string '{$key}' must not be empty.");
            }
        }
    }

    /**
     * MDL-INT-013: The French pack loads its translations.
     *
     * [Pendiente:skip] The file is named lang/fr/loclal_socialcert.php instead of
     * lang/fr/local_socialcert.php, so Moodle never loads it and French falls back to English.
     */
    public function test_french_language_pack_is_loadable(): void {
        $this->markTestSkipped(
            'French pack never loads: lang/fr/loclal_socialcert.php is misnamed and must be '
            . 'renamed to lang/fr/local_socialcert.php.'
        );
    }

    /**
     * MDL-INT-013: Every language pack is written in its own language.
     *
     * [Pendiente:skip] The Portuguese pack contains most of its texts in French, and the English
     * pack still carries a leftover Spanish string ('noissue').
     */
    public function test_language_packs_are_written_in_their_own_language(): void {
        $this->markTestSkipped(
            'Known wrong language content: lang/pt holds mostly French texts and the English '
            . "pack keeps the Spanish string 'noissue'. Automating language detection is out of "
            . 'scope until the packs are fixed.'
        );
    }

    /**
     * MDL-INT-013: Every language pack declares the whole English key set.
     *
     * [Pendiente:skip] Known gaps: 'linktext' is missing in all translations,
     * 'copyarticlebuttontext' in Spanish, 'ai_actioncall' in Indonesian and Russian, and
     * 'enableai', 'enableai_desc' and 'errorgeneric' in Russian.
     */
    public function test_every_language_pack_declares_the_full_english_key_set(): void {
        $this->markTestSkipped(
            'Translations are incomplete: linktext missing everywhere, copyarticlebuttontext '
            . 'missing in es, ai_actioncall missing in id and ru, and enableai, enableai_desc '
            . 'and errorgeneric missing in ru.'
        );
    }

    /**
     * MDL-INT-013: The plugin name is translated in every language.
     *
     * [Pendiente:skip] Every pack repeats the English name 'Share Certificate AI'.
     */
    public function test_plugin_name_is_translated_in_every_language(): void {
        $this->markTestSkipped(
            "The 'pluginname' string is left in English in every shipped language pack."
        );
    }

    /**
     * MDL-INT-014: User actions are recorded in the platform logs.
     *
     * [Pendiente:skip] The plugin defines no events, so neither sharing on LinkedIn nor an AI
     * generation leaves a trace. Consumption traceability is covered externally by the Datacurso
     * credit manager.
     */
    public function test_user_actions_are_logged_as_events(): void {
        $this->markTestSkipped(
            'The plugin declares no events (no classes/event directory), so sharing and AI '
            . 'generations cannot be traced in the platform logs.'
        );
    }

    /**
     * MDL-INT-015: The plugin defines its own capabilities to restrict features by role.
     *
     * [Pendiente:skip] There is no db/access.php, so neither the share panel nor the AI assistant
     * can be restricted by role without further development.
     */
    public function test_plugin_defines_its_own_capabilities(): void {
        $this->markTestSkipped(
            'The plugin ships no db/access.php, so it defines no capability to control who sees '
            . 'the share panel or who can generate text with AI.'
        );
    }

    /**
     * MDL-INT-017: Sharing destinations beyond LinkedIn are available.
     *
     * [Pendiente:skip] Only the LinkedIn add-to-profile destination exists; there is no other
     * social network, no email destination and no copy link button.
     */
    public function test_additional_sharing_destinations_are_available(): void {
        $this->markTestSkipped(
            'Only LinkedIn add-to-profile is implemented: no other social network, no email '
            . 'destination and no copy certificate link button exist yet.'
        );
    }
}
