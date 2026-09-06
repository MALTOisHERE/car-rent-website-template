# Public site language consolidation: implementation plan

Status: **planning only, not started**. Written 2026-09-06 as a reference for a future session, in response to the user asking why the public site still has three duplicated language folders when the backoffice solved this properly with a dynamic `t()`/translation-catalog system years (in this project's timeline) earlier.

## The idea

Replace `en/`, `fr/`, `ar/` (currently three separately-maintained copies of every public page, each with hardcoded text baked into the HTML) with a single set of page files that render in whichever language is active, the same pattern `backoffice/` already uses: `t('some.key')` pulling from `app/translations/{en,fr,ar}.php`, plus `language()`/`languageSwitchUrl()` for the active-language state and switch links.

## Why this exists today (root cause, not a design choice)

The public site (`en/fr/ar`) is the original template this project started from, era-appropriate procedural PHP where "supporting 3 languages" meant copy-pasting the whole page 3 times and hand-translating each copy. `backoffice/` was built much later in this project's history with a real i18n system designed in from the start. `CLAUDE.md` already flags this as a known, deliberate gap ("static public marketing pages remain template-heavy... deliberately not redesigned, core agency workflows were prioritized").

This session made the inconsistency worse in one specific way worth naming honestly: the new per-agency content system (`app/agency_content.php`'s `pc()`/`agencyPageContent()`, built earlier today) added a *second*, unrelated reason for text to be dynamic (per-agency overrides) on top of the *existing* problem (per-language duplication), without fixing the second problem. Every `pc($content, 'key', 'literal English/French/Arabic text')` call still hardcodes its fallback text three times, once per language folder, exactly like before. Fixing this plan's problem will also let those fallbacks collapse to `pc($content, 'key', t('public.key'))`, one authoritative string per language, not three files that can drift out of sync.

## Current state (as of this plan)

- `en/`, `fr/`, `ar/` each contain: `index.php`, `about.php`, `service.php`, `contact.php`, `team.php`, `testimonial.php`, `blog.php`, `cars.php`, `selection.php`, `header_p.php`, `footer_p.php`, plus login/signup/password-reset pages and a handful of others, all independent files.
- Confirmed today: the static assets (`css/`, `js/`, `lib/`) are byte-identical across all three folders (`diff -rq` shows zero differences; same file counts: 2 CSS files, 1 JS file, 26 lib files each). These are pure duplication with zero divergence, the safest, highest-confidence part of this plan to execute first.
- The PHP templates are *not* uniformly translated: `fr/index.php` and `ar/index.php` are genuinely translated (real French/Arabic copy), but `fr/cars.php`, `ar/cars.php`, `fr/selection.php`, `ar/selection.php` (and likely others, not fully audited) are literally the English placeholder text copy-pasted with only code comments translated. Any consolidation has to treat these two cases differently, one is "move existing correct translations into the catalog," the other is "there is no French/Arabic copy yet, only English, decide whether to machine-translate, leave in English, or flag for human translation."
- `header_p.php`/`footer_p.php` are now triplicated *and* each carries today's session's new logic (`resolveTenantAgency()`, `agencyColorPalette()`, `agencyColorStyleBlock()`) copy-pasted three times, another reason to merge sooner rather than later, so future changes to that logic don't need to be applied three times by hand (this already happened once today, and a fourth `header_p.php` clone was avoided by luck, not design).
- No routing layer exists for the public site at all beyond physical folders. `bin/dev_router.php` just maps URL path to file path directly.

## Proposed architecture

### 1. Asset consolidation (do first, zero risk)

Move `css/`, `js/`, `lib/` to one shared location (e.g. `assets/public/` alongside the existing `assets/` folder used for `connectDB.php`), delete the two duplicate copies, and update the handful of `<link>`/`<script>` tags per page to the new shared path. Since the files are byte-identical today, this is pure risk-free cleanup, no behavior change, and should happen regardless of whether the rest of this plan proceeds.

### 2. Translation catalog additions

Add a `public.*` key namespace to `app/translations/{en,fr,ar}.php` (following the existing `nav.*`/`field.*`/`page.*` namespacing convention already used for the backoffice) for every piece of static chrome and copy across all 7 page types: nav labels, breadcrumbs, section headings, body paragraphs, button labels. Where `fr`/`ar` already have real translated copy (e.g. `index.php`), use that as the catalog value. Where they don't (e.g. `cars.php`'s untranslated copy-paste), this plan defers the actual translation decision to whoever executes it, flagged explicitly rather than silently machine-translated.

### 3. Merge each page type into one file

For each of `index.php`, `about.php`, `service.php`, `contact.php`, `team.php`, `testimonial.php`, `blog.php`, `cars.php`, `selection.php` (and the auth-adjacent pages: `login.php`, `signup.php`, `forgot_password.php`, `reset_password.php`, `confirm_reservation.php` if they're also triplicated), produce one merged file that:
- Calls `t('public.xxx')` wherever today's three files have per-language hardcoded text.
- Keeps the existing `pc($content, 'key', ...)` calls for agency-content overrides from today's work, but changes their fallback argument from a hardcoded literal to `t('public.xxx')`, so the "no agency override" case reads from the single translation catalog instead of whichever language-folder file happens to be running.
- Uses `language()` (already available via `app/i18n.php`, wired into `header_p.php` earlier today) instead of `$active`-style per-folder logic where relevant.

### 4. Merge `header_p.php` / `footer_p.php`

One shared header/footer, `dir="rtl"` and logical CSS properties applied when `language() === 'ar'`, matching the pattern `backoffice/_layout.php` already established. This removes the triplicated `resolveTenantAgency()`/`agencyColorPalette()` block this session just added three times.

### 5. Routing / URL decision (open question, needs a decision before this phase)

Two real options:
- **Keep `/en/`, `/fr/`, `/ar/` URLs** (for existing bookmarks/links/SEO) but make them thin routers: `en/index.php` becomes `$_SESSION['lang']='en'; require '../public/index.php';` (or similar), so the merged file underneath is truly singular while the URL surface stays stable. Lowest risk, no broken links, but leaves three tiny stub files per page.
- **Flatten URLs entirely** (`/index.php?lang=en` or session/cookie-only, matching how the backoffice already works) and 301-redirect the old `/en/*`, `/fr/*`, `/ar/*` paths. Cleaner end state, but is a real SEO/bookmark-breaking change that needs the user's explicit sign-off before touching it, not something to decide unilaterally.

## Suggested phase order

1. **Asset consolidation** (`css`/`js`/`lib`). Zero-risk, do any time.
2. **Merge one page type as the reference implementation** (`index.php`, the same page used as the reference for the agency-content CMS work earlier today, for consistency) plus `header_p.php`/`footer_p.php`. Proves the `t()` + `pc()` combination pattern end-to-end before repeating it 8 more times.
3. **Merge the remaining 8 page types**, applying the proven pattern.
4. **Decide and implement the routing/URL question** (section 5 above), the one part of this plan that is a product decision, not just a code migration, and should not be started without the user picking one of the two options first.

## Open questions for the next session

- For the pages where `fr`/`ar` currently have no real translation (`cars.php`, `selection.php`, confirmed today; others not yet audited), what should the catalog value be: leave in English until someone translates it properly, or machine-translate as a placeholder? Silently machine-translating and shipping it as if it were reviewed copy would be worse than honestly leaving it in English with a `// TODO: needs French translation` marker.
- Should the merged pages also become agency-subdomain-aware for chrome/labels (not just content), e.g. does the nav bar itself ever need per-agency label overrides, or is `t()` + `pc()` together already sufficient for everything this site will ever need to vary?
- Auth-adjacent pages (`login.php`, `signup.php`, etc.) were not audited for this plan, confirm whether they're triplicated the same way before assuming they're in scope.
- Should this happen before or after the remaining 6 page types of the agency-content CMS work (`about/service/contact/team/testimonial/blog`) from `AGENCY_SUBDOMAINS_PLAN.md`'s follow-on work get wired up? Doing the i18n merge first would mean writing the CMS wiring once per page instead of once per page per language (a real efficiency argument for sequencing this plan *before* finishing the remaining CMS pages, worth the user's explicit call since it reorders already-agreed-to work).
