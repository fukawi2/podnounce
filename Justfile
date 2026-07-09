# PodNounce deployment helpers.
#
# Run these from a server-side clone of this repo. They push committed code to
# the live document root.
#
# The target docroot is site-specific, so it is NOT hard-coded here. Set it via
# the PODNOUNCE_DOCROOT environment variable (e.g. export it in your shell
# profile on the server), or override it per-invocation:
#
#   just docroot=/var/www/podnounce deploy
#
# Requires: just, rsync, and sufficient privileges to write to the docroot.

# Live document root that the web server serves from.
docroot := env_var_or_default("PODNOUNCE_DOCROOT", "")

# Code paths tracked in git that should be pushed to the live site.
# Deliberately excludes runtime data (uploads/, tmp/) and podnounce.conf so a
# deploy can never clobber uploaded media or the local configuration.
# composer.json/lock are included so `just deps` can install matching vendors.
sources := "app views assets index.php composer.json composer.lock"

# Ownership to apply to deployed files (user:group). Override as needed.
owner := "root:www-data"

# List available recipes.
default:
    @just --list

# Preview what a deploy would change, without writing anything (dry-run).
deploy-check: _require-docroot
    rsync --archive --verbose --checksum --ignore-times --dry-run {{sources}} {{docroot}}/

# Uses sudo because the live tree is typically root-owned; --chown keeps that
# ownership consistent rather than flipping deployed files to the calling user.
#
# Sync committed code to the live document root.
deploy: _require-docroot
    sudo rsync --archive --verbose --checksum --ignore-times --chown={{owner}} {{sources}} {{docroot}}/

# Pull the latest committed code, then deploy it.
update: && deploy
    git pull --ff-only

# Install/refresh PHP dependencies in the live document root from composer.lock.
# Needed after a deploy that changes dependencies (eg adding the S3 SDK).
deps: _require-docroot
    cd {{docroot}} && sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Migrate locally-stored media to S3 and reclaim disk (see zMaintenance).
# This runs the migration in-process via the PHP CLI against the live docroot,
# so it needs no admin login. Ensure [s3] is configured in podnounce.conf first.
migrate-s3: _require-docroot
    cd {{docroot}} && sudo -u www-data php -r 'chdir(__DIR__); require "vendor/autoload.php"; $f3=\Base::instance(); $f3->set("AUTOLOAD","app/"); $f3->set("UI","views/"); $f3->set("UPLOADS","uploads/"); $f3->config("app/routes.cfg"); $f3->config("podnounce.conf"); $p=sprintf("pgsql:host=%s;port=%s;dbname=%s",$f3->get("database.host"),$f3->get("database.port"),$f3->get("database.dbname")); $f3->set("DB",new DB\SQL($p,$f3->get("database.username"),$f3->get("database.password"))); (new zMaintenance)->MigrateToS3();'

# Guard: fail early with a helpful message if the docroot has not been set.
_require-docroot:
    @test -n "{{docroot}}" || { echo "PODNOUNCE_DOCROOT is not set. Export it or pass docroot=... on the command line."; exit 1; }

# vim: set ft=just :
