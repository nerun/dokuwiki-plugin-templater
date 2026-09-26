# Tests

Run these tests inside a DokuWiki checkout with its PHPUnit dependencies installed.
From the DokuWiki root:

```sh
bash lib/plugins/templater/_test/pretest.sh
_test/vendor/bin/phpunit -c _test/phpunit.xml --test-suffix Test.php,.test.php lib/plugins/templater/_test
```

Add `--group plugin_templater_odt` to run only the four ODT integration tests.
They generate real ODT archives and check their MIME type, XML body, parameter
replacement, section heading removal, surrounding text, and repeated inclusion.
Temporary exports are stored in the DokuWiki test suite's temporary directory.

The existing `.github/workflows/dokuwiki.yml` calls DokuWiki's official workflow,
which automatically runs `_test/pretest.sh` before PHPUnit. The hook installs
the ODT plugin at a pinned revision for reproducible CI runs; an existing local
ODT installation is kept. Missing ODT is a test failure, not a silent skip.
The dependency checkout excludes ODT's own legacy `_test` directory, whose tests
are incompatible with current PHPUnit; ODT's runtime files are unchanged.
During rendering, the tests ignore deprecation notices originating in ODT and
its known duplicate `SIMPLE_TEST` definition. Other errors, including warnings
from Templater, remain visible to PHPUnit.
Update the pinned revision deliberately when testing a newer ODT version.

Keep `_test` at the plugin root: this is where DokuWiki discovers plugin tests
and the pre-test hook. `.github/workflows` contains workflow definitions only.
