#!/usr/bin/env bash
# Owner-run Hostinger staging deployment for SANRO Studio PR #50.
# Run only from the Hostinger terminal as u606139982.
# Uses Hostinger's existing weekly automatic backups; does not create a backup.
set -Eeuo pipefail
umask 077

readonly EXPECTED_HOST_FQDN='id-dci-web1631.main-hosting.eu'
readonly EXPECTED_HOST_SHORT='id-dci-web1631'
readonly EXPECTED_ACCOUNT='u606139982'
readonly STAGE_HOST='saddlebrown-peafowl-616656.hostingersite.com'
readonly WP_ROOT="/home/${EXPECTED_ACCOUNT}/domains/${STAGE_HOST}/public_html"
readonly SOURCE_REPO='https://github.com/arioguswara-oss/Plugin-Kaoscetak.git'
readonly SOURCE_SHA='e876035b650692b382e0dd293a88c99acb18cfb0'
readonly PLUGIN_SLUG='sanro-studio'
readonly MU_FILE='sanro-studio-staging-pr50.php'
readonly PRODUCT_SKU='SANRO-STUDIO-STAGE-QA'

fail() { printf 'BLOCKED: %s\n' "$*" >&2; exit 2; }
note() { printf '%s\n' "$*"; }

note 'SANRO STUDIO PR #50 — HOSTINGER STAGING APPLY'
note 'Preflight begins read-only; no credential input is used.'

# Read-only target identity and WordPress checks. No commands below this block
# may write until every guard has passed.
actual_host="$(hostname -f 2>/dev/null || true)"
actual_short="$(hostname -s 2>/dev/null || true)"
actual_user="$(id -un 2>/dev/null || true)"
actual_root="$(realpath "$WP_ROOT" 2>/dev/null || true)"
[[ "$actual_host" == "$EXPECTED_HOST_FQDN" || "$actual_short" == "$EXPECTED_HOST_SHORT" ]] \
  || fail "unexpected Hostinger host: ${actual_host:-unknown}"
[[ "$actual_user" == "$EXPECTED_ACCOUNT" && "$EUID" -ne 0 ]] \
  || fail "must run as ${EXPECTED_ACCOUNT}, without root privileges"
[[ -d "$WP_ROOT" && ! -L "$WP_ROOT" && "$actual_root" == "$WP_ROOT" ]] \
  || fail 'staging WordPress root does not match the pinned path'
[[ -f "$WP_ROOT/wp-config.php" ]] || fail 'wp-config.php missing from staging root'
command -v wp >/dev/null 2>&1 || fail 'WP-CLI is unavailable'
command -v git >/dev/null 2>&1 || fail 'git is unavailable'
command -v zip >/dev/null 2>&1 || fail 'zip is unavailable'

wp_home="$(wp option get home --path="$WP_ROOT" --skip-plugins --skip-themes 2>/dev/null || true)"
wp_siteurl="$(wp option get siteurl --path="$WP_ROOT" --skip-plugins --skip-themes 2>/dev/null || true)"
[[ "$wp_home" == "https://${STAGE_HOST}" || "$wp_home" == "https://${STAGE_HOST}/" ]] \
  || fail 'WordPress home URL does not match the approved staging host'
[[ "$wp_siteurl" == "https://${STAGE_HOST}" || "$wp_siteurl" == "https://${STAGE_HOST}/" ]] \
  || fail 'WordPress site URL does not match the approved staging host'
wp db check --path="$WP_ROOT" >/dev/null \
  || fail 'staging database read-only health check failed'
wp plugin is-active woocommerce --path="$WP_ROOT" >/dev/null 2>&1 \
  || fail 'WooCommerce is not active on staging'
curl --silent --show-error --location --max-time 15 \
  --output /dev/null --write-out '%{http_code} %{ssl_verify_result}\n' \
  "https://${STAGE_HOST}/" | awk '$1 ~ /^[23][0-9][0-9]$/ && $2 == 0 { ok=1 } END { exit !ok }' \
  || fail 'staging HTTPS probe failed'

# Refuse conflicting constants rather than silently overriding wp-config.php.
wp eval '
$on = ["SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE", "SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS", "SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS", "SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER", "SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER", "SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH", "SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS", "SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION", "SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT", "SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT"];
foreach ($on as $name) { if (defined($name) && constant($name) === true) { fwrite(STDERR, "blocked_feature_flag=" . $name . "\n"); exit(3); } }
$must_on = ["SANRO_STUDIO_ENABLE_BUYER_EDITOR", "SANRO_STUDIO_ENABLE_DRAFT_API", "SANRO_STUDIO_ENABLE_WC_PRICING", "SANRO_STUDIO_ENABLE_PRIVATE_MASTERS", "SANRO_STUDIO_ENABLE_MASTER_UPLOAD", "SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS", "SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD"];
foreach ($must_on as $name) { if (defined($name) && constant($name) !== true) { fwrite(STDERR, "conflicting_feature_flag=" . $name . "\n"); exit(4); } }
$must_off = ["SANRO_STUDIO_ENABLE_STAGING_CART", "SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT", "SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK", "SANRO_STUDIO_ENABLE_LEGACY_COMPAT"];
foreach ($must_off as $name) { if (defined($name) && constant($name) === true) { fwrite(STDERR, "conflicting_legacy_flag=" . $name . "\n"); exit(5); } }
' --path="$WP_ROOT" >/dev/null \
  || fail 'feature flags conflict with the staging-only configuration'

note "target_host=${actual_host:-$actual_short}"
note "target_account=$actual_user"
note "target_root=$actual_root"
note "target_home=$wp_home"
note "source_sha=$SOURCE_SHA"
note 'database=WP-CLI read-only check passed; owner-provided staging/production separation evidence applies'
note 'automatic_backup=Hostinger weekly policy; no new backup requested or created'

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/sanro-studio-pr50.XXXXXXXX")"
cleanup() { rm -rf -- "$TMP_DIR"; }
trap cleanup EXIT

# Fetch and validate the exact PR source before changing the staging site.
git -C "$TMP_DIR" init -q
git -C "$TMP_DIR" remote add origin "$SOURCE_REPO"
git -C "$TMP_DIR" fetch --quiet --no-tags --depth=1 origin "$SOURCE_SHA"
git -C "$TMP_DIR" checkout --quiet --detach FETCH_HEAD
[[ "$(git -C "$TMP_DIR" rev-parse HEAD)" == "$SOURCE_SHA" ]] \
  || fail 'fetched source SHA does not match the approved PR #50 SHA'
[[ -f "$TMP_DIR/plugins/sanro-studio/sanro-studio.php" ]] \
  || fail 'SANRO Studio bootstrap is absent from the pinned source'

# Prepare private storage outside the webroot. Permissions are owner-only.
readonly PRIVATE_ROOT="${HOME}/.private/sanro-studio-staging"
readonly MASTER_DIR="${PRIVATE_ROOT}/masters"
readonly PREVIEW_DIR="${PRIVATE_ROOT}/previews"
case "$(realpath -m "$PRIVATE_ROOT")/" in
  "$WP_ROOT"/*) fail 'private storage path would be inside the webroot' ;;
esac
install -d -m 700 "$MASTER_DIR" "$PREVIEW_DIR"
chmod 700 "$PRIVATE_ROOT" "$MASTER_DIR" "$PREVIEW_DIR"
for private_dir in "$PRIVATE_ROOT" "$MASTER_DIR" "$PREVIEW_DIR"; do
  [[ ! -L "$private_dir" && -d "$private_dir" ]] || fail 'private storage path is unsafe'
  mode="$(stat -c '%a' "$private_dir")"
  [[ "$mode" == '700' ]] || fail "private storage permissions are not 700: $private_dir"
  real_private="$(realpath "$private_dir")"
  case "$real_private/" in "$WP_ROOT"/*) fail 'private storage resolved inside the webroot' ;; esac
done

# Build a plugin-root ZIP from the verified source tree.
mkdir -p "$TMP_DIR/package/sanro-studio"
cp -a "$TMP_DIR/plugins/sanro-studio/." "$TMP_DIR/package/sanro-studio/"
printf '%s\n' "$SOURCE_SHA" > "$TMP_DIR/package/sanro-studio/.sanro-studio-source-sha"
(cd "$TMP_DIR/package" && zip -qr "$TMP_DIR/sanro-studio.zip" sanro-studio)
unzip -tq "$TMP_DIR/sanro-studio.zip" >/dev/null || fail 'plugin package failed archive validation'

# Prepare a host/path guarded MU-plugin. It enables only the approved Studio
# flags on this exact staging WordPress root and keeps DTF, Drive and legacy
# staging-cart hooks explicitly OFF.
MU_DIR="$WP_ROOT/wp-content/mu-plugins"
MU_TARGET="$MU_DIR/$MU_FILE"
if [[ -e "$MU_TARGET" ]] && ! grep -q 'SANRO STAGING PR50 FLAGS' "$MU_TARGET"; then
  fail "refusing to overwrite unrelated MU-plugin: $MU_TARGET"
fi
mkdir -p "$MU_DIR"
MU_TMP="$MU_DIR/.${MU_FILE}.tmp"
cat > "$MU_TMP" <<PHP
<?php
/* SANRO STAGING PR50 FLAGS — generated by the guarded Owner deployment script. */
if (!defined('ABSPATH') || realpath(ABSPATH) !== '${WP_ROOT}') { return; }
if (!function_exists('home_url')) { return; }
\$stage_host = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
if (\$stage_host !== '${STAGE_HOST}') { return; }
\$flags = [
    'SANRO_STUDIO_ENABLE_BUYER_EDITOR' => true,
    'SANRO_STUDIO_ENABLE_DRAFT_API' => true,
    'SANRO_STUDIO_ENABLE_WC_PRICING' => true,
    'SANRO_STUDIO_ENABLE_PRIVATE_MASTERS' => true,
    'SANRO_STUDIO_ENABLE_MASTER_UPLOAD' => true,
    'SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS' => true,
    'SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD' => true,
    'SANRO_STUDIO_ENABLE_STAGING_CART' => false,
    'SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT' => false,
    'SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK' => false,
    'SANRO_STUDIO_ENABLE_LEGACY_COMPAT' => false,
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE' => false,
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS' => false,
    'SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS' => false,
    'SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER' => false,
    'SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER' => false,
    'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH' => false,
    'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS' => false,
    'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION' => false,
    'SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT' => false,
    'SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT' => false,
];
foreach (\$flags as \$name => \$value) {
    if (!defined(\$name)) { define(\$name, \$value); }
    elseif (constant(\$name) !== \$value) { return; }
}
if (!defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')) define('SANRO_STUDIO_PRIVATE_MASTER_DIR', '${MASTER_DIR}');
if (!defined('SANRO_STUDIO_PRIVATE_PREVIEW_DIR')) define('SANRO_STUDIO_PRIVATE_PREVIEW_DIR', '${PREVIEW_DIR}');
PHP
php -l "$MU_TMP" >/dev/null || fail 'staging MU-plugin failed PHP lint'
chmod 600 "$MU_TMP"

# Apply only to the pinned WordPress staging root. Hostinger's existing weekly
# automatic backup policy is used; this script creates no backup or live data.
wp plugin install "$TMP_DIR/sanro-studio.zip" --force --activate --path="$WP_ROOT"
printf '%s\n' "$SOURCE_SHA" > "$WP_ROOT/wp-content/plugins/$PLUGIN_SLUG/.sanro-studio-source-sha"
mv -f -- "$MU_TMP" "$MU_TARGET"
chmod 600 "$MU_TARGET"

# Runtime gates and storage are checked before creating the synthetic product.
wp eval '
$required = ["SANRO_STUDIO_ENABLE_BUYER_EDITOR", "SANRO_STUDIO_ENABLE_DRAFT_API", "SANRO_STUDIO_ENABLE_WC_PRICING", "SANRO_STUDIO_ENABLE_PRIVATE_MASTERS", "SANRO_STUDIO_ENABLE_MASTER_UPLOAD", "SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS", "SANRO_STUDIO_ENABLE_PREVIEW_UPLOAD"];
foreach ($required as $name) { if (!defined($name) || constant($name) !== true) { fwrite(STDERR, "required_flag_not_active=" . $name . "\n"); exit(11); } }
$off = ["SANRO_STUDIO_ENABLE_STAGING_CART", "SANRO_STUDIO_ENABLE_STAGING_CART_COMMIT", "SANRO_STUDIO_ENABLE_STAGING_CHECKOUT_HOOK", "SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE", "SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS", "SANRO_STUDIO_ENABLE_DTF_ADMIN_STATUS", "SANRO_STUDIO_ENABLE_DTF_IMAGE_RASTER", "SANRO_STUDIO_ENABLE_DTF_LAYER_RASTER", "SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH", "SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS", "SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION", "SANRO_STUDIO_ENABLE_DRIVE_MASTER_EXPORT", "SANRO_STUDIO_ENABLE_VERIFIED_DRIVE_ATTACHMENT"];
foreach ($off as $name) { if (!defined($name) || constant($name) !== false) { fwrite(STDERR, "forbidden_flag_not_off=" . $name . "\n"); exit(12); } }
if (!function_exists("sanro_studio_private_master_path_safe") || !sanro_studio_private_master_path_safe(SANRO_STUDIO_PRIVATE_MASTER_DIR)) { fwrite(STDERR, "private_master_storage_unavailable\n"); exit(13); }
if (!is_dir(SANRO_STUDIO_PRIVATE_PREVIEW_DIR) || is_link(SANRO_STUDIO_PRIVATE_PREVIEW_DIR) || (fileperms(SANRO_STUDIO_PRIVATE_PREVIEW_DIR) & 0077) !== 0) { fwrite(STDERR, "private_preview_storage_unavailable\n"); exit(14); }
if (!function_exists("sanro_studio_register_pricing_wc_hooks") || !function_exists("sanro_studio_register_pricing_integration_hooks")) { fwrite(STDERR, "pricing_integration_missing\n"); exit(15); }
' --path="$WP_ROOT" >/dev/null || fail 'post-apply feature or private-storage verification failed'

# Create/reuse an unpublished-in-catalog but directly accessible synthetic
# simple product. The published status is required for editor/config readiness.
SYNTHETIC_URL="$(wp eval '
$sku = "SANRO-STUDIO-STAGE-QA";
$id = function_exists("wc_get_product_id_by_sku") ? wc_get_product_id_by_sku($sku) : 0;
if (!$id) {
    $p = new WC_Product_Simple();
    $p->set_name("SANRO Studio Staging QA — Cotton Combed 24s");
    $p->set_sku($sku);
    $p->set_regular_price("45000");
    $p->set_status("publish");
    $p->set_catalog_visibility("hidden");
    $p->set_virtual(true);
    $id = $p->save();
}
update_post_meta($id, "_sanro_studio_pricing_profile", "cotton_combed_24s");
update_post_meta($id, "_sanro_studio_safe_areas", ["front" => ["width" => 30.0, "height" => 40.0], "back" => ["width" => 30.0, "height" => 40.0]]);
if (function_exists("sanro_studio_product_readiness")) {
    $ready = sanro_studio_product_readiness($id);
    if (empty($ready["ready"])) { fwrite(STDERR, "synthetic_product_not_ready=" . wp_json_encode($ready["issues"] ?? []) . "\n"); exit(21); }
}
echo get_permalink($id);
' --path="$WP_ROOT")"

note "APPLY=PASS source_sha=$SOURCE_SHA"
note "synthetic_product=$SYNTHETIC_URL"
note 'runtime_flags=buyer,draft_api,wc_pricing,private_master,private_preview ON; staging_cart,DTF,Drive OFF'
note 'browser_e2e=OWNER ACTION REQUIRED: open the synthetic URL in a staging browser session; test front/back design, save/reopen, quote, cart, Classic Checkout with a no-payment test method, then confirm the synthetic order lock and admin previews.'
note 'No production host, production database, payment transaction, or Hostinger security guard was changed.'
