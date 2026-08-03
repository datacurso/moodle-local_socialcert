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

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\null_provider;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\metadata\types\external_location;
use core_privacy\manager;

/**
 * Tests for the plugin privacy declaration.
 *
 * Both cases in this file assert the
 * CORRECT expected behaviour: classes/privacy/provider.php used to declare the namespace of a
 * different plugin (local_whatsapp), so \local_socialcert\privacy\provider could not be autoloaded
 * and the platform saw the plugin as having no privacy declaration at all. The provider now lives
 * in the plugin namespace and declares both the null provider (it stores no personal data locally)
 * and the metadata provider with one external location per third party destination.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /** @var string Fully qualified name of the expected privacy provider. */
    private const PROVIDERCLASS = 'local_socialcert\\privacy\\provider';

    /**
     * MDL-INT-011: The plugin ships a privacy provider under its own namespace.
     *
     * The null_provider assertion is kept: the plugin owns no table and no user preference, so the
     * declaration "stores no personal data" is accurate and is what makes the platform report the
     * component as compliant without request providers. The external transmissions are declared
     * through the metadata provider, which the plugin implements as well and which is asserted by
     * the MDL-INT-012 cases below.
     */
    public function test_plugin_declares_a_privacy_provider(): void {
        $this->assertTrue(
            class_exists(self::PROVIDERCLASS),
            'local_socialcert must ship a privacy provider in its own namespace.'
        );

        $interfaces = class_implements(self::PROVIDERCLASS);
        $this->assertArrayHasKey(
            null_provider::class,
            $interfaces,
            'The provider must declare that the plugin stores no personal data.'
        );

        $reason = call_user_func([self::PROVIDERCLASS, 'get_reason']);
        $this->assertSame('privacy:metadata', $reason);
        $this->assertTrue(
            get_string_manager()->string_exists($reason, 'local_socialcert'),
            'The null provider reason must point at an existing language string.'
        );
    }

    /**
     * MDL-INT-011: The platform recognises the declaration in the site privacy registry.
     */
    public function test_privacy_declaration_is_recognised_by_the_platform(): void {
        $manager = new manager();

        $this->assertTrue(
            $manager->component_is_compliant('local_socialcert'),
            'The plugin must pass the platform privacy validation.'
        );
        $this->assertSame(
            'privacy:metadata',
            $manager->get_null_provider_reason('local_socialcert'),
            'The site privacy registry must show the plugin declaration.'
        );
    }

    /**
     * MDL-INT-012: The metadata declares the data sent to the Datacurso AI service.
     */
    public function test_metadata_declares_the_transmission_to_the_ai_service(): void {
        $collection = $this->get_metadata_collection();
        $locations = $this->get_external_locations($collection);

        $aikeys = $this->get_privacy_field_keys($locations, 'datacurso');
        $this->assertNotEmpty(
            $aikeys,
            'An external location must be declared for the Datacurso AI service.'
        );

        // The field names are an implementation detail, so each documented data point is matched
        // against a set of accepted keywords.
        $expected = [
            'certificate name' => ['cert'],
            'course name' => ['course'],
            'organization' => ['org'],
            'user identifier' => ['user'],
            'site data' => ['site'],
        ];
        foreach ($expected as $datapoint => $keywords) {
            $this->assertTrue(
                $this->keys_match_any($aikeys, $keywords),
                "The AI service transmission must declare the {$datapoint}. Declared: "
                    . implode(', ', $aikeys)
            );
        }
    }

    /**
     * MDL-INT-012: The metadata declares the credential data sent to LinkedIn.
     */
    public function test_metadata_declares_the_transmission_to_linkedin(): void {
        $collection = $this->get_metadata_collection();
        $locations = $this->get_external_locations($collection);

        $linkedinkeys = $this->get_privacy_field_keys($locations, 'linkedin');
        $this->assertNotEmpty(
            $linkedinkeys,
            'An external location must be declared for LinkedIn with the credential fields.'
        );
    }

    /**
     * Fetch the privacy metadata collection declared by the plugin.
     *
     * @return collection The declared metadata.
     */
    private function get_metadata_collection(): collection {
        $this->assertTrue(
            class_exists(self::PROVIDERCLASS),
            'local_socialcert must ship a privacy provider in its own namespace.'
        );

        $interfaces = class_implements(self::PROVIDERCLASS);
        $this->assertArrayHasKey(
            metadata_provider::class,
            $interfaces,
            'The provider must implement the metadata provider to declare external transmissions.'
        );

        $collection = call_user_func(
            [self::PROVIDERCLASS, 'get_metadata'],
            new collection('local_socialcert')
        );
        $this->assertInstanceOf(collection::class, $collection);

        return $collection;
    }

    /**
     * Extract every external location declared in a metadata collection.
     *
     * @param collection $collection Declared metadata.
     * @return external_location[] The declared external locations.
     */
    private function get_external_locations(collection $collection): array {
        $locations = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof external_location) {
                $locations[] = $item;
            }
        }

        $this->assertGreaterThanOrEqual(
            2,
            count($locations),
            'Two external transmissions must be declared: the Datacurso AI service and LinkedIn.'
        );

        return $locations;
    }

    /**
     * Collect the privacy field keys of the external locations matching a destination name.
     *
     * @param external_location[] $locations Declared external locations.
     * @param string $needle Lowercase fragment of the destination name.
     * @return string[] The declared field keys.
     */
    private function get_privacy_field_keys(array $locations, string $needle): array {
        $keys = [];
        foreach ($locations as $location) {
            if (strpos(\core_text::strtolower($location->get_name()), $needle) === false) {
                continue;
            }
            $keys = array_merge($keys, array_keys($location->get_privacy_fields()));
        }

        return $keys;
    }

    /**
     * Whether at least one declared key contains one of the accepted keywords.
     *
     * @param string[] $keys Declared field keys.
     * @param string[] $keywords Accepted keywords.
     * @return bool True when a keyword is found.
     */
    private function keys_match_any(array $keys, array $keywords): bool {
        foreach ($keys as $key) {
            $lowerkey = \core_text::strtolower($key);
            foreach ($keywords as $keyword) {
                if (strpos($lowerkey, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
