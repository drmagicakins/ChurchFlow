# ChurchFlow — Development Workflow

Canonical workflow for any engineer (human or AI) working in this repository.
**This file did not previously exist.** It records the rules that were already implicit in `AGENTS.md` and the
phase notes, so they are not re-derived (and re-litigated) each session.

---

## 1. The rule above all others

`AGENTS.md` is binding. Read it before touching anything. In one sentence:
**work only on what was explicitly asked, and preserve everything else.**

If you find yourself about to change something outside the request, stop and ask.

---

## 2. Start of every session

```powershell
git status --short --branch      # what is already uncommitted? preserve it
git log -3 --oneline --decorate  # what was the last change, and why
php artisan test                 # is the baseline green before I touch anything?
```

Record the baseline. If the suite is not green **before** your change, say so — otherwise you will be blamed
for a failure you did not cause, or worse, credited with a fix that was never verified.

### Files that must never be modified unless the task is explicitly about them

- `app/Http/Middleware/SecurityHeaders.php` (CSP nonce + Vite nonce)
- `app/Http/Middleware/IdentifyTenant.php` and the priority ordering in `bootstrap/app.php`
- `app/Scopes/TenantScope.php`, `app/Traits/BelongsToTenant.php`
- `app/Policies/BaseTenantPolicy.php`
- Any file under `tests/` — **never weaken or delete a test to make a change pass**

---

## 3. Before editing

1. **Read the actual implementation**, not just the README. Documentation in this repo has drifted before
   (`README.md` claimed 371 tests when 381 passed).
2. **Trace the chain:** route → controller → middleware/policy → service/model → view → test.
3. **Find the root cause**, not the symptom. Phase 14's confirm-dialog bug presented as "cancel is broken" and
   was actually "Alpine never booted".
4. **Write down the smallest change** that fixes it.
5. If the change touches schema, security, billing or more than one shared component — **present the plan and
   wait for approval.**

---

## 4. Making the change

- Prefer `edit` (targeted replacement) over rewriting a file. Never recreate an existing file to apply a change.
- Do not reformat, rename or "tidy" surrounding code.
- Do not upgrade dependencies as a side effect.
- No destructive Git or database commands without explicit permission: no `reset --hard`, no `clean -fd`, no
  force push, no `migrate:fresh` on a populated database.

---

## 5. Verifying the change

### Tier 1 — always

```powershell
git diff                     # read every line you are about to report
php artisan test             # full suite, not just the file you touched
```

### Tier 2 — when a page changed

A green PHP suite proves the server rendered. It does **not** prove the page works. Three real bugs in this
repository were only visible in a browser:

| Bug                             | Symptom in tests       | Reality                               |
| ------------------------------- | ---------------------- | ------------------------------------- |
| CSP blocked every inline script | all 200s               | nothing ran                           |
| `@vite` bundle had no nonce     | all 200s               | Alpine never booted, Cancel was inert |
| `$filters` defaulted to `[]`    | passed per-phase tests | one page 500'd unconditionally        |

Load the page in a real browser and check: console errors, failed requests, CSP violations, and that clicking
a control actually does something.

### Tier 3 — when money or tenancy changed

- An isolation test for any new model (Church A cannot read Church B).
- An idempotency test for any new money movement.
- A negative test: an unauthorized user is refused **server-side**, not merely hidden in the UI.

---

## 6. Writing tests that are worth having

- A test that has never been seen to fail is not evidence. Break the fix deliberately once, watch the test go
  red, restore it, watch it go green. Phase 14 recorded doing exactly this for the Vite-nonce test.
- Prefer a negative assertion (`403`, `0 rows`) over a positive one that would pass even if the control were
  absent.
- Name tests for the behaviour, not the method.

---

## 7. Finishing

Report, every time:

1. What was requested.
2. What changed, with exact files and sections.
3. Tests run and their **actual** output.
4. Confirmation from the diff that nothing unrelated was touched.
5. Anything that needs approval.
6. Whether the handoff was updated.

Do not claim something works because it should. State what you verified and what you did not.

---

## 8. Committing

- **Never commit or push unless asked.**
- Before a substantial change, recommend a checkpoint: `git stash` or a branch — **not** a commit to `main`.
- One logical change per commit; the message explains _why_, not just _what_.

---

## 9. Documentation rules

- New findings go in `docs/PROJECT_AUDIT.md`; inventory changes in `docs/FEATURE_INVENTORY.md`; sequencing in
  `docs/PRODUCT_ROADMAP.md`.
- Root `SESSION-HANDOFF.md` is the project-wide continuation record. Its parish-pastor section is **history** —
  keep it, clearly labelled, do not delete it.
- Any number you write into a document must be reproducible by a command you actually ran.

---

## 10. Known traps in this repository

| Trap                                      | Detail                                                                                                                             |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Dev server caches PHP                     | `php artisan serve` started before edits keeps serving old code. Restart after editing controllers.                                |
| `GET /health` vs `/health.json`           | Different routes. `getJson('/health')` asserts JSON against an HTML page.                                                          |
| Health probes log by design               | They omit `context['exception']`, deliberately, so they don't poison `decodeResponseJson()`.                                       |
| `'unsafe-inline'` is ignored with a nonce | Do not "fix" CSP by adding it; stamp the nonce instead.                                                                            |
| Blade `{{ }}` on a slot default           | A default of `[]` throws `htmlspecialchars()` for every caller. Guard with `is_string()`/`Htmlable`.                               |
| Browser automation helper                 | The bundled patchright blocks inline scripts; `window.Alpine === undefined` under it is the harness, not a bug. Use real Chromium. |
| `rg`/`grep` over `vendor`                 | Exclude `vendor/` and `node_modules/`; they are untracked and large.                                                               |

---

## 11. The one question to ask before every edit

> "Did the developer ask me to change this?"

If the answer is anything other than a clear yes, stop and ask.
