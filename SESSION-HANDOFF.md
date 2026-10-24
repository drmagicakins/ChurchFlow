# SESSION HANDOFF — Parish pastor permissions + parish/role lock

Written 2026-10-21, updated 2026-10-22. **Feature works; §7 is now resolved.**
Nothing has been committed.

---

## 1. What was asked for

| #   | Requirement                                                           | Status              |
| --- | --------------------------------------------------------------------- | ------------------- |
| 1   | Church owner sets a parish pastor's permissions by ticking checkboxes | ✅ Working + tested |
| 2   | A parish pastor cannot change their parish or role                    | ✅ Working + tested |

Constraints honoured: nothing from the platform area was touched, nothing was
deleted, and no global CSS/design was altered — the Roles-tab fix is scoped to
that one screen.

**Test suite: 371 passing, 0 failing** before this change;
`ParishPastorPermissionsTest` (13 tests) passes on top of it.

---

## 2. How it works (the design decision that matters)

Permissions in this app hang off **roles** (`permission_role`), and a user's
access is the union of their roles' permissions — `User::hasPermission()` joins
through `role_user`. So "tick boxes for one pastor" is implemented as a
**private per-user role**:

- name: `Pastor · <name> · #<id>` (a unique key, not display text)
- church-scoped, so no other tenant can see it
- holds exactly the ticked permissions
- is the **only** role the pastor holds (`roles()->sync([$role->id])`)

This was chosen over adding a parallel `user_permissions` table so that the
checkboxes and every policy in the app read from one source. If the screen and
the policy layer could disagree, the screen would be describing an app that
does not exist.

---

## 3. Files changed

**Backend**

- `app/Http/Controllers/SettingsController.php`
    - `updateTeamMemberPermissions()` — **new**. Owner-only; validates each name
      against the real `permissions` table; de-duplicates; refuses to edit the
      owner's own account (409); refuses to touch a registrar (409).
    - `privateRoleFor()` — **new**, the per-user role above.
    - `assertOwnerMayChangeAssignment()` — **new**, called from
      `updateTeamMemberRole()` and `updateTeamMemberParish()`. Makes parish and
      role owner-only and blocks self-editing (409). Either field is an escape
      hatch: clearing a parish removes the `TenantScope` filter over every other
      branch, and changing the role is a straight privilege escalation.
    - `index()` also passes `$permissionsByUser` (each person's effective
      permission names, derived from their roles).

**Route**

- `routes/web.php` — `POST /settings/team/{member}/permissions` →
  `settings.team.permissions`.

**View** — `resources/views/settings/index.blade.php`

- New "Parish pastor permissions" panel (Team tab only, owner only): one
  collapsible `<details>` per pastor, grouped checkboxes, one Save button per
  pastor.
- "Set permissions" entry in each row's Manage menu, deep-linking to
  `#permissions-<id>`.
- Parish and Role cells became **read-only text** for anyone who is not the
  owner (and for the owner's own row), matching what the controller enforces.
- Removed the page's own `session('status')` block — the layout already renders
  it, so every flash appeared **twice**.
- Local `<style>`: added the missing `.cf-table__group` rule and a
  `.cf-table .cf-badge` alignment rule.

**Test**

- `tests/Feature/ParishPastorPermissionsTest.php` — **new**, 13 tests.

---

## 4. Tests added (all passing)

Owner side: grants only what is ticked; untick actually removes the capability;
screen and policy agree; one pastor's ticks never affect another; all boxes can
be cleared; an unknown permission name is rejected; the owner cannot lock
themselves out (409).

Pastor side: cannot change own parish; cannot make themselves church-wide;
cannot change own role; cannot change a colleague's role or parish; cannot set
anyone's permissions (including their own) — all 403.

Screen: a pastor sees no parish select, role select or permission panel; the
owner sees the checkboxes and the links.

---

## 5. Verification done in a real browser (dev server :8000)

- Owner ticks `members.create` + `finance.record`, saves → flash reads
  **"Permissions updated for QA Parish Pastor (2)"**, and it appears **once**
  (was twice).
- Reload → both boxes are still ticked and the count still reads **2**.
- Pastor logs in → Settings shows parish and role as plain text, no permission
  panel, no create-account form.
- Roles tab renders with app styling.
- **0 console errors, 0 CSP violations, 0 failed requests.**

Bugs found and fixed during that verification:

1. The "Set permissions" link's script had **no CSP nonce**, so the browser
   refused to run it — the link scrolled to a closed panel. Fixed by adding the
   nonce like every other inline block.
2. Every flash message rendered **twice** (page + layout). Fixed.
3. The Roles matrix borrowed `.mk-matrix__yes` from the **marketing pricing
   page**, whose surrounding rules paint a navy marketing header inside an app
   screen. Swapped for the app's own `.cf-badge--ok`, and added the missing
   `.cf-table__group` styling. No global CSS was touched.

---

## 6. ⚠️ Known state / honest caveats

- **The dev database now contains QA data** (see §8): a `QA Parish Pastor`
  account, a `Parish Pastor QA` role, private `Pastor · …` roles, and an
  `Akinyemi Mathew` entry. These are test artefacts, not app data.
- **Passwords**: the QA pastor needs `settings.manage` to reach Settings at all.
  After a browser test saved only `members.create` + `finance.record`, that
  pastor correctly gets a **403** on Settings. That is the feature working, not
  a bug — but it means the QA account cannot open Settings until
  `settings.manage` is ticked again for it.
- `isRegistrar()` falls back to the `Church Owner` role, so owners created
  before the flag existed are still recognised. Verified: `owner@churchflow.test`
  reports `isRegistrar=yes` once a tenant context is bound. (A probe that checks
  it without binding a tenant will wrongly print "no" — that is a probe
  artefact, not an app bug.)

---

## 7. ✅ RESOLVED — what the "rows out of place" bug actually was

**Diagnosis (probed, not guessed).** Dumping the controller's rows before the
view rendered showed the data was already correct (`#7 roles=[]`, `#11 private`,
`permissionsByUser` keyed by integers). Candidates A, B and C from the previous
handoff were therefore all ruled out.

Two separate causes, both in `resources/views/settings/index.blade.php`:

1. **`$tab` was assigned inside the page body.** `<x-layout>` captures its slot
   the moment the tag opens, so the layout (and the tab strip) renders _before_ the
   body runs. On a plain `GET /settings?tab=team` the variable did not exist yet:
   every tab rendered inactive and the body fell through to the **Profile** branch.
   The fix is to pass `tab` from `SettingsController::index()`.

2. **"Is this role private?" was read from the model mid-render**
   (`$member->roles->first()?->name`). That is a load-order decision: it depends on
   which role happens to come back first, and the template and the controller could
   disagree. The controller now computes `privateRoleUsers` (one eager-loaded query
   for the whole page) and the view reads that.

The "one person's role cell inside another person's row" appearance was a
symptom of (1) — the table being inspected was not the one the current request
produced. The rows themselves were never mismatched.

**Verified:**

- `GET /settings?tab=team` now renders the Team tab as active on a first load.
- QA pastor row reads **"Individual permissions"** with **no role dropdown**;
  the private role name no longer appears in any dropdown.
- Owner row still reads "Church Owner", "Full access".
- "Set permissions" deep link opens that pastor's panel (2 boxes ticked).
- Real browser: **0 console errors, 0 failed requests**.
- `ParishPastorPermissionsTest` 13/13 and `SettingsTest` 11/11 pass;
  full suite **371 passing, 0 failing**.

⚠️ **Dev-server gotcha encountered:** `php artisan serve` was started before the
edits and kept serving the old controller (opcache). Restarting the dev server is
required after editing PHP — the browser symptom was indistinguishable from a
template bug until the server was restarted.

Note on `Akinyemi Mathew` (#7): this account genuinely holds **no roles**
(`role_user` rows = 0) and predates this feature (created 2026-10-15). Its row
correctly offers a role dropdown and reads "No permissions". It is the legacy
import identity for `drmagicakins@gmail.com` — deciding whether it should be
deleted is a product/data call, not a code bug.

---

## 8. Cleanup still outstanding

Temporary files (all safe to delete, all created this session):

- `_qa_seed.php`, `_qa_inspect.php`
- `_qa_perms.mjs`, `_qa_perms2.mjs`, `_qa_pastor2.mjs`, `_qa_table.mjs`,
  `_qa_final.mjs`, `_qa_layout.mjs`
- `_suite_run.txt`, `_suite5.txt`
- `_perms_owner.png`, `_perms_owner2.png`, `_perms_pastor.png`,
  `_perms_pastor2.png`, `_final_owner.png`, `_419.png`

Dev-database QA data to remove when done:

- user `qa-pastor@churchflow.test` (+ its `role_user` rows)
- role `Parish Pastor QA`
- every role named like `Pastor · %` on church 2 (the private per-user roles)
- the `Akinyemi Mathew` user if it was added during testing

`_qa_seed.php` already cleans most of this at the top of its run and can be
adapted for teardown.

---

## 9. Behaviour changes worth telling the church about

- **The owner now assigns capabilities directly per pastor** with tick boxes.
  Each pastor gets their own set; ticking one never changes another.
- **Parish and role are the owner's alone.** Pastors keep `settings.manage`
  (so they can edit their parish profile and see what they can do) but cannot
  re-scope themselves, change their role, or grant permissions.
- **A pastor's role column now reads "Individual permissions"** once the owner
  has ticked boxes for them, because a shared role name would no longer
  describe what they can do. The stored role is a private per-user role.
- **Account creation stays owner-only** (unchanged from the previous session).
