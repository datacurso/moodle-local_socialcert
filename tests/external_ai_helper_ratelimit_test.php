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

use local_socialcert\fixtures\customcert_dependency_trait;
use local_socialcert\fixtures\stub_ai_services_api;
use local_socialcert\fixtures\testable_ai_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once(__DIR__ . '/fixtures/customcert_dependency_trait.php');

/**
 * Tests for the per user usage limit of the AI text generation external function.
 *
 * SOCIALCERT-SEC-002: a student could call the web service directly and every call reached the
 * paid provider (61 calls in about 5 seconds in the audit). The function now spends one unit of the
 * user quota of core_ai\rate_limiter, under a lock, after every other check has passed and before the
 * provider is contacted.
 *
 * @package    local_socialcert
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_socialcert\external\ai_helper
 */
final class external_ai_helper_ratelimit_test extends \externallib_advanced_testcase {
    use customcert_dependency_trait;

    /** @var string Error code of a call refused because the quota is spent. */
    private const LIMITCODE = 'error_ratelimit_exceeded';

    /** @var string Error code of a call refused because another call of the user holds the lock. */
    private const BUSYCODE = 'error_ratelimit_busy';

    /** @var int Default hourly limit per user. */
    private const DEFAULTLIMIT = 10;

    /**
     * Start every test with an empty quota cache, because the cache outlives the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \cache::make('core', 'ai_ratelimit')->purge();
    }

    /**
     * Load the external function wired to a stubbed provider client.
     *
     * @return string Fully qualified name of the testable subclass.
     */
    private function load_helper(): string {
        global $CFG;

        $this->require_customcert();
        if (\core_component::get_component_directory('aiprovider_datacurso') === null) {
            $this->markTestSkipped('The aiprovider_datacurso plugin is not installed on this site.');
        }

        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/stub_ai_services_api.php');
        require_once($CFG->dirroot . '/local/socialcert/tests/fixtures/testable_ai_helper.php');
        testable_ai_helper::$client = new stub_ai_services_api();
        set_config('aiassistant_enabled', 1, 'local_socialcert');

        return testable_ai_helper::class;
    }

    /**
     * Build a certificate activity and a student who has the certificate issued.
     *
     * @param \stdClass|null $fixture Existing fixture to add one more student to, or null for a new course.
     * @return \stdClass Object with cmid, customcert, course and student.
     */
    private function create_fixture(?\stdClass $fixture = null): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        if ($fixture === null) {
            $course = $generator->create_course();
            $customcert = $generator->create_module('customcert', ['course' => $course->id]);
            $fixture = (object) ['course' => $course, 'customcert' => $customcert, 'cmid' => (int) $customcert->cmid];
        } else {
            // A copy, so adding a student does not overwrite the student of the fixture passed in.
            $fixture = clone $fixture;
        }

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $fixture->course->id, 'student');
        $DB->insert_record('customcert_issues', (object) [
            'userid' => $student->id,
            'customcertid' => $fixture->customcert->id,
            'code' => 'CODE' . $student->id,
            'emailed' => 0,
            'timecreated' => time(),
        ]);
        $fixture->student = $student;

        return $fixture;
    }

    /**
     * Number of requests that reached the stubbed provider.
     *
     * @return int
     */
    private function provider_calls(): int {
        return count(testable_ai_helper::$client->requests);
    }

    /**
     * Call the function as a user and discard the debugging of a controlled rejection.
     *
     * @param string $helper Fully qualified name of the testable subclass.
     * @param \stdClass $user User making the call.
     * @param int $cmid Course module id.
     * @return array Payload returned by execute().
     */
    private function call_as(string $helper, \stdClass $user, int $cmid): array {
        $this->setUser($user);
        $result = $helper::execute($cmid);
        $this->resetDebugging();

        return $result;
    }

    /**
     * Assert that a payload is the refusal of an exhausted quota.
     *
     * @param array $result Payload returned by execute().
     * @return void
     */
    private function assert_limit_rejection(array $result): void {
        $this->assertFalse($result['ok'] ?? null, 'An exhausted quota must report ok=false.');
        $this->assertSame(self::LIMITCODE, $result['errorcode'] ?? null);
        $this->assertNotEmpty($result['message'] ?? '', 'The refusal must carry a message for the panel.');
        $this->assertArrayNotHasKey('json', $result, 'A refusal must not return a provider payload.');
    }

    /**
     * Assert that a payload is a generation the provider answered.
     *
     * @param array $result Payload returned by execute().
     * @return void
     */
    private function assert_generated(array $result): void {
        $this->assertArrayHasKey('json', $result, 'The call must reach the provider and return its payload.');
    }

    /**
     * SOCIALCERT-SEC-002: the call after the configured limit is refused and never reaches the provider.
     */
    public function test_call_over_the_limit_is_refused_before_the_provider(): void {
        $helper = $this->load_helper();
        set_config('airatelimit', 3, 'local_socialcert');
        $fixture = $this->create_fixture();

        for ($i = 0; $i < 3; $i++) {
            $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        }
        $this->assertSame(3, $this->provider_calls());

        $result = $this->call_as($helper, $fixture->student, $fixture->cmid);

        $this->assert_limit_rejection($result);
        $this->assertStringContainsString('3', $result['message'], 'The message must state the limit.');
        $this->assertSame(3, $this->provider_calls(), 'The refused call must not reach the provider.');
    }

    /**
     * SOCIALCERT-SEC-002: the quota belongs to each user, so one student cannot exhaust another one.
     */
    public function test_the_limit_is_per_user(): void {
        $helper = $this->load_helper();
        set_config('airatelimit', 2, 'local_socialcert');
        $first = $this->create_fixture();
        $second = $this->create_fixture($first);

        $this->assert_generated($this->call_as($helper, $first->student, $first->cmid));
        $this->assert_generated($this->call_as($helper, $first->student, $first->cmid));
        $this->assert_limit_rejection($this->call_as($helper, $first->student, $first->cmid));

        $this->assert_generated($this->call_as($helper, $second->student, $second->cmid));
        $this->assertSame(3, $this->provider_calls());
    }

    /**
     * SOCIALCERT-SEC-002: the quota is renewed once the one hour window of the core limiter has passed.
     */
    public function test_the_window_resets_after_one_hour(): void {
        $helper = $this->load_helper();
        set_config('airatelimit', 2, 'local_socialcert');
        // The clock is mocked before the first call, because core builds the limiter with it.
        $clock = $this->mock_clock_with_frozen(1800000000);
        $fixture = $this->create_fixture();

        $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        $this->assert_limit_rejection($this->call_as($helper, $fixture->student, $fixture->cmid));

        $clock->bump(HOURSECS + 1);

        $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        $this->assertSame(3, $this->provider_calls());
    }

    /**
     * SOCIALCERT-SEC-002: a call stopped by an earlier rule spends nothing of the quota.
     */
    public function test_calls_denied_by_earlier_checks_do_not_consume_quota(): void {
        $helper = $this->load_helper();
        set_config('airatelimit', 3, 'local_socialcert');
        $fixture = $this->create_fixture();
        // A second student of the same activity who never received the certificate.
        $noissue = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($noissue->id, $fixture->course->id, 'student');

        // Denied because the AI assistant is disabled, and because no certificate was issued.
        set_config('aiassistant_enabled', 0, 'local_socialcert');
        for ($i = 0; $i < 5; $i++) {
            $this->assertArrayNotHasKey('json', $this->call_as($helper, $fixture->student, $fixture->cmid));
        }
        set_config('aiassistant_enabled', 1, 'local_socialcert');
        for ($i = 0; $i < 5; $i++) {
            $denied = $this->call_as($helper, $noissue, $fixture->cmid);
            $this->assertSame('nocertificateissued', $denied['errorcode'] ?? null);
        }
        $this->assertSame(0, $this->provider_calls());

        for ($i = 0; $i < 3; $i++) {
            $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        }
        $this->assert_limit_rejection($this->call_as($helper, $fixture->student, $fixture->cmid));
        $this->assertSame(3, $this->provider_calls());
    }

    /**
     * SOCIALCERT-SEC-002: when the lock of the user cannot be obtained the call is refused (fail closed),
     * with a code of its own, because the quota was not spent and the panel must not say it was.
     *
     * The file lock factory is forced because the database factories are re entrant inside one
     * session, so a lock held by the test itself would not block the function; a flock taken through
     * another file handle does.
     */
    public function test_call_is_refused_when_the_lock_of_the_user_is_held(): void {
        global $CFG;

        $helper = $this->load_helper();
        set_config('airatelimit', 3, 'local_socialcert');
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $fixture = $this->create_fixture();

        $factory = \core\lock\lock_config::get_lock_factory('local_socialcert_ai_ratelimit');
        $lock = $factory->get_lock('user_' . $fixture->student->id, 0);
        $this->assertNotFalse($lock, 'The test must be able to hold the lock of the user.');

        try {
            $result = $this->call_as($helper, $fixture->student, $fixture->cmid);
        } finally {
            $lock->release();
        }

        // The quota is not spent, so the refusal must not claim it is: it carries its own code.
        $this->assertFalse($result['ok'] ?? null, 'A call without the lock must report ok=false.');
        $this->assertSame(self::BUSYCODE, $result['errorcode'] ?? null);
        $this->assertSame(get_string(self::BUSYCODE, 'local_socialcert'), $result['message'] ?? null);
        $this->assertArrayNotHasKey('json', $result, 'A refusal must not return a provider payload.');
        $this->assertSame(0, $this->provider_calls(), 'Without the lock the provider must not be called.');

        // The refused call spent nothing: the whole quota is still available once the lock is free.
        for ($i = 0; $i < 3; $i++) {
            $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        }
        $this->assert_limit_rejection($this->call_as($helper, $fixture->student, $fixture->cmid));
    }

    /**
     * Settings that must fall back to the default limit.
     *
     * @return array[] Raw value of airatelimit (null means the setting does not exist).
     */
    public static function default_limit_provider(): array {
        return [
            'unset' => [null],
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-4'],
        ];
    }

    /**
     * SOCIALCERT-SEC-002: a missing, empty, zero or negative setting means the default of 10 per hour.
     *
     * @dataProvider default_limit_provider
     * @param string|null $value Raw value of the setting, or null to leave it unset.
     */
    public function test_default_limit_applies_when_the_setting_is_not_usable(?string $value): void {
        $helper = $this->load_helper();
        if ($value === null) {
            unset_config('airatelimit', 'local_socialcert');
        } else {
            set_config('airatelimit', $value, 'local_socialcert');
        }
        $fixture = $this->create_fixture();

        for ($i = 0; $i < self::DEFAULTLIMIT; $i++) {
            $this->assert_generated($this->call_as($helper, $fixture->student, $fixture->cmid));
        }

        $result = $this->call_as($helper, $fixture->student, $fixture->cmid);
        $this->assert_limit_rejection($result);
        $this->assertStringContainsString((string) self::DEFAULTLIMIT, $result['message']);
        $this->assertSame(self::DEFAULTLIMIT, $this->provider_calls());
    }
}
