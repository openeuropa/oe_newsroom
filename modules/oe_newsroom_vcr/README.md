# OpenEuropa Newsroom VCR

_Do not enable in production._

This submodule intercepts outgoing http requests, either to record real requests
and responses, or to replay pre-recorded responses and assert pre-recorded
requests.

The module is mostly designed for tests, but can be installed in a local
development environment and experimented with in the API explorer.

## Why?

Different packages exist that provide a vcr-style functionality for testing, or
to mock responses from a web service.

This custom VCR implementation provides some unique concepts.

### Recording and replay mode

The VCR can operate in three modes: passthrough, recording, and replay.

- In "passthrough" mode, requests go to the real-world destination unintercepted.
- In "recording" mode, requests and responses from the http client are intercepted
  and recorded, but then returned unchanged.
- In "replay" mode, requests and responses are loaded from a prior recording.\
  Requests are asserted to be as previously recorded, and responses are returned
  as previously recorded.

The "passthrough" mode is the default when one simply installs the module.

The "replay" mode is typically enabled (started and stopped) in PhpUnit tests.

The "recording" mode is activated in a test instead of "replay" mode, when the
developer sets an environment variable `UPDATE_TESTS=1`.

### History-based selection

Responses are chosen by the sequence in which they were recorded.

There is no "matching" logic that would select responses based on request data.

This allows a really long interaction chain, with different responses for the
same requests based on state.

### Cross-process progress

The recording data and replay progress are stored in the Drupal database, so
that different processes have access to it.

This allows it to run in a functional test.

### Separation of VCR and test fixtures

The main VCR mechanism reads from and writes to the database, and knows nothing
about test fixtures.

A PhpUnit test that uses the VCR is responsible for storing that data in a
fixture file.

This also allows further processing by the test code that can happen completely
outside the main VCR mechanism.

### Reality check

All API requests and responses in the test fixtures were at one point recorded
with a real Newsroom connection.

A developer can always run with `UPDATE_TESTS=1` to see if the recorded fixtures
are still aligned with current behavior of Newsroom.

### Data stabilization.

When writing to a file tracked by git, recorded data can be stabilized,
simplified and sanitized and placeholderized, eliminating any traces of the
specific Newsroom universe and configuration that was used.

This allows different developers to run in recording mode with their own
universe and connection details, while (ideally) producing the same recording
data in the fixture file.
