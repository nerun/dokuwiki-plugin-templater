#!/usr/bin/env bash
set -euo pipefail

# Called by the official DokuWiki workflow from the plugin directory.
plugin_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
odt_dir="${plugin_dir}/../odt"
odt_revision=0576770ddd7d88557a08c6c9b55cb996bce8ce48

# Keep an existing local installation; CI starts without this dependency.
if [[ ! -d "$odt_dir" ]]; then
    git init "$odt_dir"
    # ODT's own legacy tests are not part of Templater's suite and cannot be
    # loaded by current PHPUnit. Install its runtime files only.
    git -C "$odt_dir" sparse-checkout set --no-cone '/*' '!/_test/'
    git -C "$odt_dir" fetch --depth=1 https://github.com/LarsGit223/dokuwiki-plugin-odt.git "$odt_revision"
    git -C "$odt_dir" checkout --detach FETCH_HEAD
fi

test -f "$odt_dir/renderer/page.php"
