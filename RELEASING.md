# Releasing a new version

Publishing to the Moodle plugins directory is a manual step done from a maintainer's machine. The
GitHub Actions workflow that used to do it was removed in version 1.1.7 (finding SOCIALCERT-SEC-001):
it inserted the manually typed tag name into a shell step that had the moodle.org publishing token
at hand, so anyone able to run the workflow could run commands with that token. A test
(`tests/ci/workflow_security_test.php`) fails if a workflow refers to `secrets.MOODLE_ORG_TOKEN`
again or interpolates untrusted input into a `run` or `script` step, so do not bring the workflow back.

## 1. Before tagging

- `version.php`: `$plugin->release` is the public version name, `$plugin->version` the build number
  (`YYYYMMDDXX`), `$plugin->supported` the range of Moodle versions tested.
- `CHANGES.md`: the top section is `## [<release>] - <date>` and opens with the compatibility note
  derived from `$plugin->supported`.
- The PHPUnit suite and the Moodle coding standard pass on the release commit, and the commit is on
  `main` with a clean working tree.

## 2. Tag and GitHub release

The tag is named exactly as `$plugin->release`, and the release notes are the top section of
`CHANGES.md`, compatibility note first. Extract that section into a file outside the repository, so
it is never committed:

```bash
TAG='<release>'
awk '/^## \[/{n++} n==1' CHANGES.md > "/tmp/release-notes-${TAG}.md"
gh release create "${TAG}" --target main --title "${TAG}" --notes-file "/tmp/release-notes-${TAG}.md"
```

Check that the tag points at the intended commit before going on.

## 3. Publish in the Moodle plugins directory

Open the plugin page, <https://moodle.org/plugins/local_socialcert>, signed in as a maintainer of
the plugin, and add a new version from the GitHub tag. The form asks for the data of the table
below; the release name, the build number and the supported Moodle versions come from the
`version.php` inside the ZIP, so check them on the versions page once the version is published.

| Field | Value |
| --- | --- |
| ZIP of the version | `https://api.github.com/repos/datacurso/moodle-local_socialcert/zipball/<tag>` |
| Alternative download URL | the same ZIP URL |
| Version control system | git |
| Repository URL | `https://github.com/datacurso/moodle-local_socialcert` |
| Repository tag | `<tag>` |
| Changelog URL | `https://github.com/datacurso/moodle-local_socialcert/commits/<tag>` |

The same data can be sent from a terminal with the `local_plugins_add_version` web service of
moodle.org, using a personal token created in your moodle.org profile. The call below is the one the
removed workflow made, with three changes that keep the token on your machine: the token is typed
without echo instead of written in a command, so it stays out of the shell history; it travels to
`curl` through standard input instead of an argument, so it does not show in the process list; and
the variable is cleared right after the call. Set `TAG` yourself and read it twice before sending:
the directory refuses a build number that is not greater than the last published one, but it does
not refuse a wrong tag that carries a valid build.

```bash
TAG='<release>'
REPO='datacurso/moodle-local_socialcert'
read -rsp 'moodle.org token: ' MOODLE_ORG_TOKEN; echo
printf '%s' "${MOODLE_ORG_TOKEN}" | curl -sS https://moodle.org/webservice/rest/server.php \
  --data-urlencode "wstoken@-" \
  --data-urlencode "wsfunction=local_plugins_add_version" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "frankenstyle=local_socialcert" \
  --data-urlencode "zipurl=https://api.github.com/repos/${REPO}/zipball/${TAG}" \
  --data-urlencode "vcssystem=git" \
  --data-urlencode "vcsrepositoryurl=https://github.com/${REPO}" \
  --data-urlencode "vcstag=${TAG}" \
  --data-urlencode "changelogurl=https://github.com/${REPO}/commits/${TAG}" \
  --data-urlencode "altdownloadurl=https://api.github.com/repos/${REPO}/zipball/${TAG}" \
  | jq -e '.id'
unset MOODLE_ORG_TOKEN
```

The pipeline ends with the `id` of the new version and exit code 0. Any other output is a failure:
the directory answers errors as a JSON object with an `exception` field and no `id`, which makes
`jq -e` exit with 1, and a transport error is printed by `curl`. Never read a parse error as a
successful publication.

The endpoint and the form are the ones the removed workflow used. The plugins directory now serves
its pages from `marketplace.moodle.com`; if the publishing form or the web service move with it,
follow the directory's own documentation rather than this file.

## 4. After publishing

- Check the versions page, <https://moodle.org/plugins/local_socialcert/versions>: the new version
  lists the right release name, build number and supported Moodle versions, and its download works.
