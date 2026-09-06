# Public site language consolidation

Status: **complete**. Originally written 2026-09-06 as a forward-looking plan; this document now
describes what was actually built, which diverged from the original plan in a few places (noted
below) as the user made explicit calls during implementation. Kept as a historical record of the
reasoning, not as a task list, since there's no remaining work it describes.

## What changed

`en/`, `fr/`, `ar/` (three separately-maintained copies of every public page, each with hardcoded
text baked into the HTML) were replaced with a single `site/` folder: one file per page, rendering
in whichever language is active via `t('some.key')` pulling from `app/translations/{en,fr,ar}.php`,
the same pattern `backoffice/` already used. Language is chosen dynamically via `?lang=`/session
state (`language()`, `supportedLanguages()`), not a URL folder.

### Divergence from the original plan

The plan below (section "5. Routing / URL decision") framed two options and said the choice
needed the user's explicit sign-off. It got that sign-off, and the user chose neither of the two
options as originally framed:
- **Not** "keep `/en/`/`/fr/`/`/ar/` URLs as thin routers over a merged `public/` file" (Option A).
- **Not** simply "flatten URLs to `?lang=` and 301-redirect old paths" as its own separate step.

Instead: physical files live in `site/` (not a `public/` folder as the plan sketched), and
`bin/dev_router.php`/`.htaccess` transparently map bare top-level URLs (`/cars.php`, `/`) to their
file under `site/`, so the **URLs visitors see never changed at all** - no redirect needed, since
the old `/en/`, `/fr/`, `/ar/` URLs were retired outright rather than preserved. This is a third
option the plan didn't enumerate: flatten the *files* while keeping the *root-level URL shape*,
via a router-level mapping rather than either per-language stub files or a URL-visible folder
change.

## What was actually done, phase by phase

1. **Asset consolidation** - `css/`, `js/`, `lib/` moved to `assets/public/{css,js,lib}` (confirmed
   byte-identical across the three old folders beforehand). Zero risk, exactly as planned.
2. **Translation catalog additions** - a `public.*` namespace was added to
   `app/translations/{en,fr,ar}.php`: `public.nav.*`, `public.footer.*`, `public.breadcrumb.*`,
   `public.content.*` (index.php's sections), and - in a later pass, once the deferred item below
   was picked back up - `public.shared_counter.*`, `public.shared_team.*`,
   `public.shared_testimonials.*`, `public.shared_banner.*` (for content blocks that repeat
   byte-for-byte across multiple pages) plus `public.about_page.*`, `public.service_page.*`,
   `public.contact_page.*`, `public.team_page.*`, `public.testimonial_page.*`,
   `public.blog_page.*` for page-specific copy. `about.php`'s and `index.php`'s content had already
   diverged (different Vision/Mission text, a typo preserved in one but not the other) before this
   work started, so they deliberately do **not** share translation keys even where topically
   similar - conflating them would have silently merged two different pages' copy.
3. **Page merge** - `index.php`, `cars.php`, `selection.php`, `about.php`, `service.php`,
   `contact.php`, `team.php`, `testimonial.php`, `blog.php`, `feature.php`, `reserve.php`,
   `404.php` were each merged into one file in `site/`, all `t()`-driven. The open question this
   plan raised - "leave in English until translated, or machine-translate as a placeholder" - was
   resolved by writing real English, French, and Arabic copy directly for every page rather than
   leaving a gap or a machine-translation placeholder.
4. **Header/footer merge** - one shared `site/header_p.php`/`site/footer_p.php`, replacing three
   copies of `resolveTenantAgency()`/`agencyColorPalette()`/`agencyColorStyleBlock()`. Arabic
   renders `dir="rtl"`.
5. **Auth-adjacent pages** (`login.php`, `signup.php`, `forgot_password.php`,
   `reset_password.php`, `send_reset_link.php`, `confirm_reservation.php`'s legacy per-language
   copies, `connectDB.php` shims) - audited and found to be **dead duplicates** already superseded
   by `account/`'s consolidated versions (confirmed via a full-codebase reference search: nothing
   linked to them). Deleted rather than merged, since there was nothing in them worth keeping.
6. **`en/`/`fr`/`ar/` folders removed** entirely once every file inside them was either merged or
   confirmed dead.

## Known remaining gaps (not part of this plan's original scope)

- `feature.php` and `reserve.php` (moved into `site/` intact) are still English-only; they weren't
  in scope for the about/service/contact/team/testimonial/blog translation pass and were not
  linked from anywhere in the site's own navigation to begin with (confirmed via reference search
  before the merge) - lower priority than the other six pages, which were.
- No end-to-end manual browser test has been performed for the RTL Arabic layout or the language
  switcher on every merged page - verification so far has been `php bin/php_syntax_check.php`,
  the business-rule test suite, direct `t()` calls confirming correct string resolution per
  language, and (where the database was reachable) `curl`-level HTTP checks. A real click-through
  in a browser, particularly for Arabic RTL rendering, is still worth doing before considering the
  public site fully verified.
