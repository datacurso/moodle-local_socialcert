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

namespace local_socialcert\ci;

/**
 * Guards the GitHub Actions workflows shipped with this plugin against script injection.
 *
 * SOCIALCERT-SEC-001: the release workflow wrote the workflow_dispatch "tag" input straight into
 * a bash step, as "${{ github.event.inputs.tag }}", in a job whose environment held the moodle.org
 * publishing token. GitHub replaces the expression with the raw text before bash runs, so a tag
 * such as 1.1.4$(cmd) ran cmd, with the token one environment variable away. That workflow was
 * removed; these tests keep the pattern, and the token, from coming back.
 *
 * @package     local_socialcert
 * @category    test
 * @coversNothing
 * @copyright   2026 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class workflow_security_test extends \advanced_testcase {
    /**
     * Expression contexts an outside party can set: dispatch and call inputs, the event payloads of
     * releases, pull requests, issues, comments, reviews, discussions, pushes, workflow runs and
     * repository dispatches, the whole payload at once, and branch or tag names.
     *
     * Matched case-insensitively, as GitHub resolves context names, against an expression whose
     * bracket accesses were already rewritten to dots (see normalise_expression()).
     */
    private const UNTRUSTED_CONTEXT = '/(?<![\w.])(?:inputs\.'
        . '|github\.event\.(?:inputs|release|pull_request|issue|comment|head_commit|commits|workflow_run'
        . '|review|review_comment|discussion|discussion_comment|client_payload|pages)\b'
        . '|github\.event(?![\w.])|github\.head_ref\b|github\.ref(?:_name)?\b)/i';

    /**
     * No workflow step hands untrusted event data to the shell as code.
     *
     * The safe form passes the value through a step-level env: mapping and reads it as "$VAR",
     * where bash treats it as data. The detector only looks inside run: steps and github-script
     * scripts, so that form passes.
     */
    public function test_no_workflow_interpolates_untrusted_input_into_a_shell_step(): void {
        $findings = [];
        foreach (self::workflow_files() as $path) {
            $source = file_get_contents($path);
            $lines = self::split_lines($source);
            foreach (self::find_untrusted_interpolations($source) as $lineno) {
                $findings[] = basename($path) . ':' . $lineno . ': ' . trim($lines[$lineno - 1]);
            }
        }

        $this->assertSame(
            [],
            $findings,
            "A run: step interpolates untrusted input with \${{ }}. GitHub pastes the raw text into the "
                . "script before the shell parses it, so the value runs as code. Pass it through env: and "
                . "read it as \"\$VAR\" instead.\n" . implode("\n", $findings)
        );
    }

    /**
     * No workflow references the moodle.org publishing token.
     *
     * Releases reach the plugins directory by hand, so any workflow that asks for it again is
     * putting a credential within reach of a job that does not need it.
     */
    public function test_no_workflow_exposes_the_moodle_org_token(): void {
        $offenders = [];
        foreach (self::workflow_files() as $path) {
            if (self::exposes_publishing_token(file_get_contents($path))) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, 'These workflows can reach secrets.MOODLE_ORG_TOKEN: ' . implode(', ', $offenders));
    }

    /**
     * The token check sees every spelling that hands the secret to a job.
     *
     * @dataProvider token_cases_provider
     * @param string $yaml A workflow snippet.
     * @param bool $expected Whether the snippet exposes the token.
     */
    public function test_token_check_finds_every_spelling(string $yaml, bool $expected): void {
        $this->assertSame($expected, self::exposes_publishing_token($yaml));
    }

    /**
     * Workflow snippets and whether each one exposes the moodle.org token.
     *
     * @return array
     */
    public static function token_cases_provider(): array {
        return [
            'dotted reference' => ["env:\n  TOKEN: \${{ secrets.MOODLE_ORG_TOKEN }}\n", true],
            'bracket reference' => ["env:\n  TOKEN: \${{ secrets['MOODLE_ORG_TOKEN'] }}\n", true],
            'other letter case' => ["env:\n  TOKEN: \${{ Secrets.moodle_org_token }}\n", true],
            'every secret passed to a reusable workflow' => [
                "jobs:\n  release:\n    uses: ./.github/workflows/x.yml\n    secrets: inherit\n",
                true,
            ],
            'every secret serialised' => ["run: echo '\${{ toJSON(secrets) }}'\n", true],
            'an unrelated secret' => ["env:\n  TOKEN: \${{ secrets.GITHUB_TOKEN }}\n", false],
            'the name in a comment only' => ["# Publishing to moodle.org is manual; there is no MOODLE_ORG_TOKEN here.\n", false],
        ];
    }

    /**
     * The detector flags the injection pattern and leaves the safe forms alone.
     *
     * @dataProvider detector_cases_provider
     * @param string $yaml A workflow snippet.
     * @param int[] $expected The 1-based lines the detector must report.
     */
    public function test_detector_finds_exactly_the_injectable_lines(string $yaml, array $expected): void {
        $this->assertSame($expected, self::find_untrusted_interpolations($yaml));
    }

    /**
     * Workflow snippets and the lines the detector must flag in each.
     *
     * @return array
     */
    public static function detector_cases_provider(): array {
        return [
            'dispatch tag pasted into a run block' => [
                "steps:\n"
                    . "  - name: Release\n"
                    . "    run: |\n"
                    . "      if [[ ! -z \"\${{ github.event.inputs.tag }}\" ]]; then\n"
                    . "        TAGNAME=\"\${{ github.event.inputs.tag }}\"\n"
                    . "      fi\n",
                [4, 5],
            ],
            'input pasted into a single-line run' => [
                "steps:\n"
                    . "  - run: echo \${{ inputs.tag }}\n",
                [2],
            ],
            'pull request title in a folded run block' => [
                "steps:\n"
                    . "  - run: >-\n"
                    . "      echo \"\${{ github.event.pull_request.title }}\"\n",
                [3],
            ],
            'the same input passed as data through env' => [
                "steps:\n"
                    . "  - name: Release\n"
                    . "    env:\n"
                    . "      TAG: \${{ github.event.inputs.tag }}\n"
                    . "    run: |\n"
                    . "      TAGNAME=\"\$TAG\"\n",
                [],
            ],
            'matrix and secrets in with and env' => [
                "steps:\n"
                    . "  - uses: shivammathur/setup-php@v2\n"
                    . "    with:\n"
                    . "      php-version: \${{ matrix.php }}\n"
                    . "  - run: moodle-plugin-ci install\n"
                    . "    env:\n"
                    . "      TOKEN: \${{ secrets.X }}\n",
                [],
            ],
            'the run block ends where the indentation does' => [
                "steps:\n"
                    . "  - run: |\n"
                    . "      echo start\n"
                    . "\n"
                    . "      echo \${{ matrix.php }}\n"
                    . "    with:\n"
                    . "      tag: \${{ inputs.tag }}\n",
                [],
            ],
            'bracket access to the same inputs' => [
                "steps:\n"
                    . "  - run: echo \${{ inputs['tag'] }}\n"
                    . "  - run: echo \${{ github.event.inputs[\"tag\"] }}\n"
                    . "  - run: echo \${{ github['event']['inputs']['tag'] }}\n",
                [2, 3, 4],
            ],
            'context names in another letter case' => [
                "steps:\n"
                    . "  - run: echo \${{ GitHub.Event.Inputs.Tag }}\n",
                [2],
            ],
            'branch and tag names, other events and the whole payload' => [
                "steps:\n"
                    . "  - run: echo \${{ github.ref_name }}\n"
                    . "  - run: echo \${{ github.ref }}\n"
                    . "  - run: echo \${{ github.event.workflow_run.head_branch }}\n"
                    . "  - run: echo \${{ github.event.review.body }}\n"
                    . "  - run: echo \${{ github.event.discussion.title }}\n"
                    . "  - run: echo \${{ github.event.client_payload.tag }}\n"
                    . "  - run: echo \${{ github.event.commits[0].message }}\n"
                    . "  - run: echo '\${{ toJSON(github.event) }}'\n",
                [2, 3, 4, 5, 6, 7, 8, 9],
            ],
            'trusted github context values' => [
                "steps:\n"
                    . "  - run: echo \${{ github.repository }} \${{ github.sha }} \${{ github.run_id }}\n"
                    . "  - run: echo \${{ github.event_name }} \${{ github.repository_owner }}\n",
                [],
            ],
            'plain multi-line run scalars' => [
                "steps:\n"
                    . "  - run:\n"
                    . "      echo \${{ inputs.tag }}\n"
                    . "  - run: echo start\n"
                    . "      \${{ inputs.tag }}\n"
                    . "  - name: Next\n",
                [3, 5],
            ],
            'quoted run key' => [
                "steps:\n"
                    . "  - \"run\": echo \${{ inputs.tag }}\n",
                [2],
            ],
            'github-script pastes the input into JavaScript' => [
                "steps:\n"
                    . "  - uses: actions/github-script@v7\n"
                    . "    with:\n"
                    . "      script: |\n"
                    . "        console.log('\${{ inputs.tag }}')\n",
                [5],
            ],
        ];
    }

    /**
     * Returns the workflow files to audit, or skips when the package does not ship them.
     *
     * @return string[]
     */
    private static function workflow_files(): array {
        $dir = __DIR__ . '/../../.github/workflows';
        if (!is_dir($dir)) {
            self::markTestSkipped('This copy of the plugin ships no .github/workflows directory.');
        }

        $files = array_merge(glob($dir . '/*.yml') ?: [], glob($dir . '/*.yaml') ?: []);
        sort($files);
        return $files;
    }

    /**
     * Finds the lines of run: steps and github-script scripts that interpolate untrusted context
     * with ${{ }}.
     *
     * A line-based guard, not a YAML parser. It understands a run: or script: key (bare or quoted)
     * with a value on the same line, a plain scalar continued on deeper lines, and a "|" or ">"
     * block scalar (with chomping or indentation indicators). The value ends at the first
     * non-blank line indented no deeper than the key. It does not see commands written in flow
     * style ({run: ...}), as quoted multi-line scalars, through YAML anchors, or in composite
     * actions outside .github/workflows; nor an expression split over two lines; nor untrusted
     * data that reaches the shell indirectly, for example through a step output. Expressions in
     * env:, other with: inputs or if: are not reported: those are not parsed as code.
     *
     * @param string $yaml Workflow source.
     * @return int[] 1-based numbers of the offending lines, in order.
     */
    private static function find_untrusted_interpolations(string $yaml): array {
        $found = [];
        $blockindent = null;

        foreach (self::split_lines($yaml) as $index => $line) {
            $indent = strlen($line) - strlen(ltrim($line, ' '));

            if ($blockindent !== null) {
                if (trim($line) === '' || $indent > $blockindent) {
                    $code = $line;
                } else {
                    $blockindent = null;
                }
            }

            if ($blockindent === null) {
                if (!preg_match('/^(\s*(?:-\s+)?)(["\']?)(?:run|script)\2:\s*(.*)$/', $line, $match)) {
                    continue;
                }
                $blockindent = strlen($match[1]);
                if (preg_match('/^[|>][-+0-9]*\s*(#.*)?$/', $match[3])) {
                    continue;
                }
                $code = $match[3];
            }

            preg_match_all('/\$\{\{(.*?)\}\}/', $code, $expressions);
            foreach ($expressions[1] as $expression) {
                if (preg_match(self::UNTRUSTED_CONTEXT, self::normalise_expression($expression))) {
                    $found[] = $index + 1;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Tells whether a workflow hands the moodle.org publishing token to a job.
     *
     * It catches a dotted or bracket reference in any letter case, the whole secrets context
     * serialised with toJSON(), and "secrets: inherit", which passes every repository secret on to
     * a reusable workflow. The name alone, in a comment, does not count.
     *
     * @param string $yaml Workflow source.
     * @return bool
     */
    private static function exposes_publishing_token(string $yaml): bool {
        if (preg_match('/^\s*secrets\s*:\s*inherit\b/im', $yaml)) {
            return true;
        }

        preg_match_all('/\$\{\{(.*?)\}\}/', $yaml, $expressions);
        foreach ($expressions[1] as $expression) {
            $expression = self::normalise_expression($expression);
            if (preg_match('/(?<![\w.])secrets\.moodle_org_token\b|tojson\(\s*secrets\s*\)/i', $expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rewrites quoted bracket accesses to dots, so inputs['tag'] reads as inputs.tag.
     *
     * @param string $expression The text between ${{ and }}.
     * @return string
     */
    private static function normalise_expression(string $expression): string {
        return preg_replace('/\[\s*([\'"])([^\'"]+)\1\s*\]/', '.$2', $expression);
    }

    /**
     * Splits on the line breaks a YAML file can use, and nothing else.
     *
     * A bare \R would also break on form feeds, vertical tabs and the byte 0x85, which turns up
     * inside multibyte UTF-8 characters, and would shift every line number after it.
     *
     * @param string $source File contents.
     * @return string[]
     */
    private static function split_lines(string $source): array {
        return preg_split('/\r\n|\r|\n/', $source);
    }
}
