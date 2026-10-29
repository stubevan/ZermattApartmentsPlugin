#!/usr/bin/env bash
# Deploy a git ref (tag, or origin/<branch>) of this plugin to zermattapartments.ch.
#   bin/deploy.sh v1.0
#   bin/deploy.sh origin/main
# The live plugin folder is a git checkout of the public GitHub repo; this fetches and checks out <ref>,
# lint-checks the PHP with the site's PHP version, and goes back to the previous ref if that fails.
# Roll back by running it again with the previous ref it prints.
set -euo pipefail

REF="${1:?usage: bin/deploy.sh <tag | origin/branch>}"
HOST="${ZA_HOST:-zermattapartments}"
WP_DIR="wordpress"
PLUGIN_DIR="$WP_DIR/wp-content/plugins/zermatt-plugin"
SITE_URL="https://zermattapartments.ch/"

cd "$(dirname "$0")/.."

# 1. Local checks: clean tree, ref exists on GitHub.
if [[ -n "$(git status --porcelain)" ]]; then
	echo "Working tree not clean; commit or stash first." >&2
	exit 1
fi
git fetch --quiet --tags origin
if ! git rev-parse --verify --quiet "$REF^{commit}" >/dev/null; then
	echo "Unknown ref: $REF" >&2
	exit 1
fi
SHA="$(git rev-parse "$REF^{commit}")"
# On GitHub = contained in a remote-tracking branch, or a ref on GitHub (e.g. a pushed tag) points at it.
if [[ -z "$(git branch -r --contains "$SHA" 2>/dev/null)" ]] && ! git ls-remote origin | grep -q "^$SHA"; then
	echo "$REF ($SHA) is not on GitHub yet; push it first." >&2
	exit 1
fi
echo "Deploying $REF ($SHA) to $HOST"

# 2. Remote: fetch, check out, lint, roll back on failure. The script goes over stdin to avoid quoting problems.
ssh -o BatchMode=yes "$HOST" bash -s -- "$PLUGIN_DIR" "$SHA" <<'REMOTE'
set -euo pipefail
cd "$1"
SHA="$2"
PREV="$(git rev-parse HEAD)"
echo "Live was: $PREV ($(git describe --tags --always "$PREV"))"
git fetch --quiet --tags origin
echo "Changes:"
git log --oneline "$PREV..$SHA" || true
git checkout --quiet --detach "$SHA"
FAILED=0
while IFS= read -r f; do
	php8.3-cli -l "$f" >/dev/null || { echo "Lint failed: $f"; FAILED=1; }
done < <(git ls-files '*.php' | grep -v '^tools/')
if [[ $FAILED -ne 0 ]]; then
	echo "Rolling back to $PREV"
	git checkout --quiet --detach "$PREV"
	exit 1
fi
echo "Live now: $(git rev-parse HEAD) ($(git describe --tags --always HEAD))"
echo "ROLLBACK: bin/deploy.sh $PREV"
REMOTE

# 3. Post-deploy checks.
ssh -o BatchMode=yes "$HOST" "cd $WP_DIR && wp --skip-plugins=wordfence plugin get zermatt-plugin/zermatt-apartments-plugin.php --fields=status,version && wp --skip-plugins=wordfence zermatt deposit-email status"
CODE="$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL")"
echo "Home page: HTTP $CODE"
[[ "$CODE" == "200" ]] || { echo "Home page check failed" >&2; exit 1; }
