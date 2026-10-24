# Kaoscetak Lite Designer — development branch v0.2.0-dev

**NOT FOR PUBLIC INSTALLATION.** Source undergoing audit and automated tests; no release ZIP has been approved.

## Focus
- Product WooCommerce -> button `Custom Desain Kaos` -> fullscreen frontend editor. The underlying `kld_design` custom post type is private storage with its WordPress UI hidden; it is **not** the customer editor.
- Admin can preview the product editor **without** Google Drive connected (product still must be enabled and configured).
- PNG/JPEG original max 10 MiB; lightweight browser preview; master intended to be stored privately in Google Drive without PHP relaying large files.
- Print categories A5, A4, A3 and A3+ are limits, NOT compulsory actual design sizes. Category pricing remains fixed when resized.
- Independent front/back image + multiline text. Real image/text placement and physical cm are recorded. Server rejects invalid tier, bounds, order quantities, prices and unsafe placements.
- Payment/ready-production events are intended to create the Drive order folder and a small PDF guide; not a high-res DTF print file.

## Tests in this branch
- `php tests/test_rules.php`: deterministic unit tests of tariff matrix and geometry with stubbed WordPress functions.
- `python tests/test_editor_browser.py`: Chromium frontend test without WordPress. Requires `playwright` installed and chromium binary (`CHROMIUM_PATH=/path/to/chromium` optional).
- `php -l` for all PHP and `node --check` for editor JavaScript.

## Not verified / BLOCKED
- Full installation + activation on real WordPress with WooCommerce, Flatsome, HPOS and real product variations.
- Google OAuth flow and direct browser upload CORS with the Kaoscetak Drive account.
- End-to-end owner token/link, public sharing/revoke/fork, permanent design management, repeat discount 5% and its anti-abuse verification.
- Non-Cotton 24s geometry and print safe-area configuration; basic dimension defaults apply only to 24s.
- PDF creation in a real WooCommerce order; order fulfillment processing and failed/retry Drive uploads.
- Benchmark on Android RAM 4 GB.

**Public switch stays OFF.** Do not claim `FINAL`, `DEPLOY_READY` or `VERIFIED` based on browser + unit tests alone. The live Lumise plugin must remain active and untouched.