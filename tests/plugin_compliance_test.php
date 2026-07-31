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

use core\event\base as event_base;
use local_socialcert\event\ai_text_generated;
use local_socialcert\event\certificate_shared;
use local_socialcert\external\log_share;
use local_socialcert\output\main_panel;
use mod_customcert\certificate;

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
     * Every key the panel renders is listed here: the hero of the section, the notices it can show,
     * the assistant card with its accessible names, and the strings amd/src/actions.js asks the
     * string manager for while a generation runs or when the assistant becomes available.
     *
     * @var string[]
     */
    private const CORE_PANEL_STRINGS = [
        // Hero of the panel and the share action.
        'sharetitle',
        'sharesubtitle',
        'shareinstruction',
        'whatsharelabel',
        'buttonlabelshare',
        'linkcertbuttontext',
        // Notices of the panel: the two actionable states of the disabled share button, the legacy
        // single notice, the verification warning and the availability announcement of the browser.
        'certerror',
        'certerrordownload',
        'certerrornoorg',
        'verifywarning',
        'sharenowavailable',
        'popupblocked',
        'sharecompleted',
        // Assistant card: call to action, controls, accessible names and rejection messages.
        'ai_actioncall',
        'ai_field_heading',
        'airesponsebtn',
        'airegionlabel',
        'ailogoalt',
        'avatarlabel',
        'copyarticlebuttontext',
        'copytextlabel',
        'copyconfirmation',
        'generating',
        'errorcredits',
        'errorgeneric',
        'errorlicense',
    ];

    /**
     * Function words that unambiguously belong to one single shipped language.
     *
     * The packs are checked against an objective signal instead of an impression: each list holds
     * words that exist in that language and in none of the other six, so finding one of them inside
     * another pack proves the pack carries text of a foreign language. They are matched as whole
     * words (letter boundaries), so 'certificat' does not match the Portuguese 'certificado' nor the
     * Spanish 'certificado'.
     *
     * English has no list of its own because every translation legitimately keeps English product
     * names ('Share Certificate AI', 'Provider AI', 'LinkedIn Add-to-Profile') and the markup of the
     * Datacurso store links, so English words are not a discriminating signal. Russian is detected
     * by script instead, in {@see self::test_language_packs_are_written_in_their_own_language()}.
     *
     * @var array<string, string[]>
     */
    private const LANGUAGE_MARKERS = [
        'es' => ['aún', 'enlace', 'ningún', 'tienes'],
        'de' => ['und', 'für', 'nicht', 'Ihre', 'Zertifikat'],
        'fr' => ['votre', 'vous', 'nous', 'veuillez', 'avec', 'sur', 'pour', 'une', 'aucune', 'certificat', 'partager'],
        'pt' => ['não', 'você', 'compartilhar'],
        'id' => ['untuk', 'yang', 'tidak', 'sertifikat'],
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
     * Whether a text contains a word, matched between letter boundaries and ignoring case.
     *
     * The boundaries are letter based instead of the \b of the regular expressions so an accented
     * word is matched as a whole word too, and so a marker is never found inside a longer word of
     * another language ('certificat' inside 'certificado', for instance).
     *
     * @param string $text Text to search in.
     * @param string $word Word to look for.
     * @return bool True when the word appears as a whole word.
     */
    private function contains_word(string $text, string $word): bool {
        $pattern = '/(?<!\p{L})' . preg_quote($word, '/') . '(?!\p{L})/ui';

        return (bool) preg_match($pattern, $text);
    }

    /**
     * Placeholders of a language string, in order of appearance.
     *
     * @param string $value String value.
     * @return string[] Placeholders such as '{$a}' or '{$a->name}'.
     */
    private function get_placeholders(string $value): array {
        preg_match_all('/\{\$a(?:->[a-z0-9_]+)?\}/i', $value, $matches);

        return $matches[0];
    }

    /**
     * Opening anchor tags of a language string, in order of appearance.
     *
     * The store links of the credit and license messages travel inside the strings, so the whole
     * opening tag is compared: a translation that dropped the link, its URL or its target attribute
     * would no longer match the English one.
     *
     * @param string $value String value.
     * @return string[] Opening anchor tags.
     */
    private function get_anchor_tags(string $value): array {
        preg_match_all('/<a\b[^>]*>/i', $value, $matches);

        return $matches[0];
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
     * MDL-INT-013: Every language pack translates the core strings of the panel.
     *
     * Every documented language ships a file named as Moodle expects, so all seven are asserted
     * here. The strings are read from the file, without the English fallback the string manager
     * merges in, so a pack that simply did not declare a key cannot pass.
     */
    public function test_loadable_language_packs_translate_the_core_panel_strings(): void {
        $loadable = $this->get_loadable_languages();
        $this->assertSame(
            self::LANGUAGES,
            $loadable,
            'Every documented language must ship a file Moodle can load.'
        );

        foreach ($loadable as $lang) {
            $strings = $this->read_declared_strings($lang);
            foreach (self::CORE_PANEL_STRINGS as $key) {
                $this->assertArrayHasKey($key, $strings, "The '{$lang}' pack must declare '{$key}'.");
                $this->assertNotEmpty($strings[$key], "The '{$lang}' string '{$key}' must not be empty.");
            }
        }
    }

    /**
     * MDL-INT-013: Every pack sits where the string manager looks for it, French included.
     *
     * Previously skipped for French only: the file was named lang/fr/loclal_socialcert.php, so
     * Moodle never loaded it and French fell back to English. The check now covers the seven
     * languages, and it does not hardcode the expected path: it rebuilds it exactly as
     * core_string_manager_standard::load_component_strings() does for the legacy location of a
     * contributed plugin, '{plugin directory}/lang/{lang}/{plugintype}_{pluginname}.php', so a file
     * named anything else is reported as unreachable.
     *
     * Comparing the answer of the string manager against the file is only possible for English:
     * get_language_dependencies() returns nothing for a language whose pack is not installed on the
     * site, and the test site (like the plugin CI) ships English only, so Moodle would never read
     * the other six files. What every pack declares is therefore asserted by reading the file, in
     * the cases above and below.
     */
    public function test_french_language_pack_is_loadable(): void {
        [$plugintype, $pluginname] = \core_component::normalize_component('local_socialcert');
        $location = \core_component::get_plugin_directory($plugintype, $pluginname);
        $this->assertDirectoryExists((string) $location, 'The plugin directory must be known to Moodle.');

        foreach (self::LANGUAGES as $lang) {
            $expected = "{$location}/lang/{$lang}/{$plugintype}_{$pluginname}.php";
            $this->assertFileExists(
                $expected,
                "Moodle reads the '{$lang}' pack from '{$expected}'; a file named otherwise never loads."
            );
            $this->assertSame(
                realpath($expected),
                realpath($this->language_file_path($lang)),
                "The '{$lang}' pack must be the file the string manager reads."
            );

            $declared = $this->read_declared_strings($lang);
            $this->assertNotEmpty($declared, "Including the '{$lang}' pack must declare its strings.");
        }

        $loaded = get_string_manager()->load_component_strings('local_socialcert', 'en');
        $declared = $this->read_declared_strings('en');
        foreach (self::CORE_PANEL_STRINGS as $key) {
            $this->assertArrayHasKey($key, $loaded, "The string manager must load '{$key}'.");
            $this->assertSame($declared[$key], $loaded[$key], "The string manager must answer the text of '{$key}'.");
        }
    }

    /**
     * MDL-INT-013: Every language pack is written in its own language.
     *
     * Previously skipped: the Portuguese pack held most of its texts in French and the English pack
     * carried a leftover Spanish string ('noissue').
     *
     * The criterion is objective, not an impression. {@see self::LANGUAGE_MARKERS} lists, per
     * language, function words that exist in that language and in none of the other six, matched as
     * whole words between letter boundaries. A pack must contain every marker of its own language
     * (proof it is written in it) and none of the markers of the other languages (proof it carries
     * no foreign text): the Portuguese pack, for instance, must not contain 'votre', 'vous', 'sur',
     * 'pour', 'avec', 'partager' nor 'certificat' as a whole word, while 'certificado' is untouched
     * by the check. Russian is asserted by script: it is the only pack that may use Cyrillic, and it
     * has to use it.
     */
    public function test_language_packs_are_written_in_their_own_language(): void {
        foreach (self::LANGUAGES as $lang) {
            $text = implode("\n", $this->read_declared_strings($lang));

            foreach (self::LANGUAGE_MARKERS as $other => $words) {
                foreach ($words as $word) {
                    if ($other === $lang) {
                        $this->assertTrue(
                            $this->contains_word($text, $word),
                            "The '{$lang}' pack no longer uses the '{$lang}' word '{$word}'; "
                            . 'the marker list has to be reviewed or the pack is not written in its language.'
                        );
                        continue;
                    }

                    $this->assertFalse(
                        $this->contains_word($text, $word),
                        "The '{$lang}' pack contains the '{$other}' word '{$word}': it carries text "
                        . 'of a foreign language.'
                    );
                }
            }

            if ($lang === 'ru') {
                $this->assertMatchesRegularExpression(
                    '/\p{Cyrillic}/u',
                    $text,
                    'The Russian pack must be written in Cyrillic.'
                );
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\p{Cyrillic}/u',
                $text,
                "The '{$lang}' pack must not contain Russian text."
            );
        }
    }

    /**
     * MDL-INT-013: Every language pack declares the whole English key set.
     *
     * Previously skipped: 'linktext' was missing in every translation, 'copyarticlebuttontext' in
     * Spanish, 'ai_actioncall' in Indonesian and Russian, and 'enableai', 'enableai_desc' and
     * 'errorgeneric' in Russian (the last one declared under the wrong key
     * 'error_generating_resource').
     *
     * The parity is asserted in both directions, because a key a translation declares and English
     * does not is dead weight nothing can ever read. The placeholders and the store links of each
     * string are compared with the English ones too: a translation that dropped '{$a}' or the anchor
     * of the credits message would render a broken text.
     */
    public function test_every_language_pack_declares_the_full_english_key_set(): void {
        $english = $this->read_declared_strings('en');
        $englishkeys = array_keys($english);

        foreach (self::LANGUAGES as $lang) {
            if ($lang === 'en') {
                continue;
            }

            $strings = $this->read_declared_strings($lang);
            $keys = array_keys($strings);

            $this->assertSame(
                [],
                array_values(array_diff($englishkeys, $keys)),
                "The '{$lang}' pack does not translate every English string."
            );
            $this->assertSame(
                [],
                array_values(array_diff($keys, $englishkeys)),
                "The '{$lang}' pack declares strings the English pack does not know about."
            );

            $sorted = $keys;
            sort($sorted, SORT_STRING);
            $this->assertSame($sorted, $keys, "The keys of the '{$lang}' pack must be sorted.");

            foreach ($english as $key => $value) {
                $this->assertNotEmpty($strings[$key], "The '{$lang}' string '{$key}' must not be empty.");
                $this->assertSame(
                    $this->get_placeholders($value),
                    $this->get_placeholders($strings[$key]),
                    "The '{$lang}' string '{$key}' must keep the placeholders of the English one."
                );
                $this->assertSame(
                    $this->get_anchor_tags($value),
                    $this->get_anchor_tags($strings[$key]),
                    "The '{$lang}' string '{$key}' must keep the links of the English one."
                );
            }
        }
    }

    /**
     * MDL-INT-013: The plugin name is translated in every language.
     *
     * Previously skipped: every pack repeated the English name 'Share Certificate AI'.
     */
    public function test_plugin_name_is_translated_in_every_language(): void {
        $englishname = $this->read_declared_strings('en')['pluginname'];
        $this->assertSame('Share Certificate AI', $englishname);

        $names = [];
        foreach (self::LANGUAGES as $lang) {
            if ($lang === 'en') {
                continue;
            }

            $name = $this->read_declared_strings($lang)['pluginname'];
            $this->assertNotSame(
                $englishname,
                $name,
                "The '{$lang}' pack must translate the name of the plugin."
            );
            $this->assertNotContains(
                $name,
                $names,
                "The '{$lang}' pack repeats the name of another language."
            );
            $names[$lang] = $name;
        }

        $this->assertCount(
            count(self::LANGUAGES) - 1,
            $names,
            'Every language other than English must carry its own name of the plugin.'
        );
    }

    /**
     * MDL-INT-014: User actions are recorded in the platform logs.
     *
     * Previously skipped: the plugin declared no events at all. It now ships
     * \local_socialcert\event\certificate_shared and \local_socialcert\event\ai_text_generated.
     *
     * The share is asserted end to end here, through the local_socialcert_log_share web service the
     * panel calls when it opens the LinkedIn window, because that is the only server side moment of
     * an action that happens in the browser. The wiring of the AI event is asserted where the AI
     * external function is exercised, in
     * external_ai_helper_test::test_successful_generation_records_the_ai_event(); what is asserted
     * here is the contract both event classes have to honour to be usable in the log reports.
     */
    public function test_user_actions_are_logged_as_events(): void {
        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        $sink = $this->redirectEvents();
        log_share::execute($scenario->cmid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events, 'Sharing on LinkedIn must record exactly one event.');

        $event = reset($events);
        $this->assertInstanceOf(certificate_shared::class, $event);
        $this->assertSame('local_socialcert', $event->component);
        $this->assertSame((int) $scenario->student->id, (int) $event->userid, 'The event belongs to the user who shared.');
        $this->assertSame((int) $scenario->course->id, (int) $event->courseid, 'The event must name the course.');
        $this->assertSame($scenario->context->id, (int) $event->contextid);
        $this->assertSame($scenario->cmid, (int) $event->contextinstanceid, 'The event must name the activity.');
        $this->assertSame(CONTEXT_MODULE, (int) $event->contextlevel);
        $this->assertSame('linkedin', $event->other['network']);
        $this->assertNotEmpty($event->other['certid'], 'The credential code identifies what was shared.');
        $this->assertNotEmpty($event->get_description());
        $this->assertStringContainsString('/mod/customcert/view.php', $event->get_url()->out(false));

        // Both events are traceability of a user action that stores nothing in Moodle, so they are
        // read operations at the participating level, and neither of them owns a database table.
        foreach ([certificate_shared::class, ai_text_generated::class] as $classname) {
            $info = $classname::get_static_info();
            $this->assertSame('local_socialcert', $info['component'], "Wrong component for {$classname}.");
            $this->assertSame('r', $info['crud'], "Wrong crud value for {$classname}.");
            $this->assertSame(event_base::LEVEL_PARTICIPATING, $info['edulevel'], "Wrong edulevel for {$classname}.");
            $this->assertNull($info['objecttable'], "{$classname} must not claim a database table of its own.");
            $this->assertNotEmpty($classname::get_name(), "Missing event name for {$classname}.");
        }

        $this->assertSame(get_string('eventcertificateshared', 'local_socialcert'), certificate_shared::get_name());
        $this->assertSame(get_string('eventaitextgenerated', 'local_socialcert'), ai_text_generated::get_name());
    }

    /**
     * MDL-INT-015: The plugin defines its own capabilities to restrict features by role.
     *
     * Previously skipped: there was no db/access.php at all, so the feature could not be restricted
     * by role. The plugin now declares local/socialcert:viewsharepanel and
     * local/socialcert:useaiassistant.
     *
     * The chosen defaults are asserted on purpose: the panel already demanded
     * mod/customcert:receiveissue, whose only archetype is the student, so allowing only the student
     * archetype keeps exactly the same users on the feature as before.
     */
    public function test_plugin_defines_its_own_capabilities(): void {
        global $DB;

        $this->resetAfterTest();

        $capabilities = $this->read_declared_capabilities();
        $this->assertEqualsCanonicalizing(
            ['local/socialcert:viewsharepanel', 'local/socialcert:useaiassistant'],
            array_keys($capabilities),
            'The plugin must declare one capability for the share panel and one for the AI assistant.'
        );

        foreach ($capabilities as $name => $definition) {
            $this->assertSame(
                CONTEXT_MODULE,
                $definition['contextlevel'],
                "The capability '{$name}' must live at activity level, where the feature is used."
            );
            $this->assertSame('read', $definition['captype'], "The capability '{$name}' writes nothing in Moodle.");
            $this->assertSame(
                ['student' => CAP_ALLOW],
                $definition['archetypes'],
                "The defaults of '{$name}' must not change who reaches the feature today."
            );
            $this->assertArrayNotHasKey(
                'riskbitmask',
                $definition,
                "The capability '{$name}' carries no risk: nothing is published for other users."
            );

            $record = $DB->get_record('capabilities', ['name' => $name]);
            $this->assertNotEmpty($record, "The capability '{$name}' must be installed on the site.");
            $this->assertSame('local_socialcert', $record->component);

            $stringkey = str_replace('local/', '', $name);
            $this->assertTrue(
                get_string_manager()->string_exists($stringkey, 'local_socialcert'),
                "Missing language string '{$stringkey}' for the capability '{$name}'."
            );
        }
    }

    /**
     * MDL-INT-015: The two capabilities really restrict the panel and the assistant.
     *
     * The point of the case is that the feature becomes restrictable by role, so the effect of
     * preventing each capability is asserted separately: the assistant can be taken away while the
     * sharing survives, and taking the panel away removes the whole feature.
     */
    public function test_own_capabilities_restrict_the_panel_and_the_assistant(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('organizationid', '12345', 'local_socialcert');
        set_config('enableai', 1, 'local_socialcert');

        $scenario = $this->create_certificate_scenario();
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->setUser($scenario->student);
        certificate::issue_certificate($scenario->customcert->id, $scenario->student->id);

        // The defaults leave the student with both features, exactly as before the capabilities.
        $this->assertTrue(has_capability('local/socialcert:viewsharepanel', $scenario->context));
        $this->assertTrue(has_capability('local/socialcert:useaiassistant', $scenario->context));
        $this->assertTrue(main_panel::get_share_state($scenario->cmid, (int) $scenario->student->id)['enableai']);

        // Preventing the assistant leaves the sharing untouched.
        role_change_permission($studentroleid, $scenario->context, 'local/socialcert:useaiassistant', CAP_PREVENT);
        $this->assertFalse(has_capability('local/socialcert:useaiassistant', $scenario->context));
        $state = main_panel::get_share_state($scenario->cmid, (int) $scenario->student->id);
        $this->assertFalse($state['enableai'], 'A role without the capability must not get the assistant.');
        $this->assertTrue($state['hasissue'], 'The sharing does not depend on the capability of the assistant.');
        $this->assertNotNull($state['shareurl']);

        // Preventing the panel is what removes the feature from the activity for that role.
        role_change_permission($studentroleid, $scenario->context, 'local/socialcert:viewsharepanel', CAP_PREVENT);
        $this->assertFalse(has_capability('local/socialcert:viewsharepanel', $scenario->context));
    }

    /**
     * Capability definitions declared by db/access.php, read straight from the file.
     *
     * @return array Capability definitions indexed by capability name.
     */
    private function read_declared_capabilities(): array {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . self::PLUGINPATH . '/db/access.php');

        return $capabilities;
    }

    /**
     * Creates a course with a custom certificate activity and an enrolled student.
     *
     * @return \stdClass Object with the course, customcert, cmid, context and student.
     */
    private function create_certificate_scenario(): \stdClass {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(['fullname' => 'Machine Learning 101']);
        $customcert = $generator->create_module('customcert', [
            'course' => $course->id,
            'name'   => 'AI Fundamentals',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        return (object) [
            'course'     => $course,
            'customcert' => $customcert,
            'cmid'       => (int) $customcert->cmid,
            'context'    => \context_module::instance((int) $customcert->cmid),
            'student'    => $student,
        ];
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
