# Update Notes

This file is a working update log for changes that may become commits. Use it to decide what a pending commit contains before approving any `git commit` or push.

## 2026-08-15 - Meta Ads Manager, Phase E: Events Manager (Pixel Health + Audiences)

Reason:

- Owner asked why there was no Meta Pixel setup option and Events Manager. Clarified this already existed as a separate feature (Storefront → Meta CAPI, which configures the Pixel and only ever *sends* events) but the owner specifically wanted a dashboard reachable from this app showing real data pulled *from* Meta's own Events Manager tool, plus Audience management — neither of which existed anywhere in the codebase. Entered plan mode, researched Meta's real Pixel Stats and Custom Audiences Graph API endpoints (WebSearch/WebFetch against developers.facebook.com), and got explicit approval before implementing.

What happened:

- New **Events Manager** page (Ads cluster): account + Pixel + date-window selector (Pixel options are read live from the company's already-configured `StorefrontSetting`/Meta CAPI pixels — no new pixel-credential field was added anywhere). A **Pixel Health** panel shows real Pixel identity (`GET /{pixel_id}`) and real event-volume-by-type (`GET /{pixel_id}/stats?aggregation=event`) side by side with this app's own local `storefront_meta_events` send-attempt log for the same window — two distinct real sources, labeled "What Meta recorded" vs "What we attempted to send," never merged. Meta's public docs don't fully specify the stats endpoint's row shape, so it's parsed defensively and falls back to a plain "not recognized" notice rather than guessing.
- A real **Audiences** manager: **Sync Audiences** pulls existing Website/Lookalike Custom Audiences from Meta into a new local `meta_audiences` table; **New Audience** creates a Website Visitors audience (from a configured Pixel, retention window, optional URL-contains filter) or a Lookalike (sourced only from this app's own Website-subtype audiences, editable target country defaulting to `BD`, 1–20% size); **Delete** calls Meta first and only removes the local row once Meta confirms — the same "Meta first, then local" discipline as Phase B's Pause/Resume. "Customer List" (bulk hashed-PII-upload) audiences are deliberately not supported — a bigger, separate privacy-sensitive ask.
- `MetaMarketingApiClient` gained `pixel()`, `pixelEventStats()`, `customAudiences()`, `websiteAudienceRule()` (shared, pure rule-builder — used both for the real API call and for what's persisted locally, so the two can never drift apart), `createWebsiteCustomAudience()`, `createLookalikeAudience()`, `deleteAudience()`.
- New `MetaAudienceSyncService::sync()` upserts by `meta_id`; deliberately lets exceptions propagate (no per-audience-sync failure column exists, and reusing the account's own `last_sync_error` would conflate two independent sync loops) — the page's action catches and reports.
- `MetaAdAccountResource`'s Access Token helper text gained one clause noting it should also have Pixel access under Business Settings for this page to work.

Important changed files:

- `database/migrations/2026_08_15_030000_create_meta_audiences_table.php`
- `app/Models/MetaAudience.php`, `app/Models/User.php` (MODEL_MODULES)
- `app/Services/MetaMarketingApiClient.php`, `app/Services/MetaAudienceSyncService.php`
- `app/Filament/Pages/MetaEventsManager.php`, `resources/views/filament/pages/meta-events-manager.blade.php`
- `app/Filament/Resources/MetaAdAccounts/MetaAdAccountResource.php`
- `tests/Feature/MetaMarketingApiClientTest.php` (extended), `tests/Feature/MetaAudienceSyncServiceTest.php` (new), `tests/Feature/MetaEventsManagerPageTest.php` (new), `tests/Feature/MultiCompanyIsolationTest.php` (extended)
- `CHANGELOG.md`, `PROJECT_GUIDE.md`

Verification:

- `php artisan test tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAudienceSyncServiceTest.php tests/Feature/MetaEventsManagerPageTest.php tests/Feature/MultiCompanyIsolationTest.php`: 40 passed (216 assertions).
- Also ran the full existing Meta Ads Manager suite alongside it (`MetaAdsDashboardPageTest`, `MetaAdsSyncServiceTest`, `MetaAdsAiAssistantServiceTest`, `MetaAdsAiAssistantPageTest`, `CreateMetaAdCampaignPageTest`, `MetaAdsCreationServiceTest`, `MetaAdAccountResourceTest`): 76 passed total (416 assertions) — confirms Phases A–D are unaffected.
- Full `php artisan test`: 742 passed, 5 failed — the same pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); the +20 passing tests are exactly this phase's new tests, zero regressions.
- `npm run build` ran clean (new Blade view's Tailwind classes all already exist elsewhere in the compiled bundle; no arbitrary-value classes were introduced).
- **Honesty caveat, stated plainly**: the Pixel Stats endpoint's exact response row shape isn't fully documented by Meta publicly, so it's handled defensively rather than guaranteed to render a clean table — a deliberate regression test (`test_pixel_health_panel_does_not_crash_when_stats_returns_an_unrecognized_shape`) confirms the page degrades gracefully instead of erroring. Custom Audience creation/list/delete are well-documented, higher-confidence calls. Interactive browser verification against a real Meta account was not completed this round — logging into the local admin panel to click through Sync/New Audience/Delete live was blocked by this session's own safety guardrails around typing a password into a login field (same limitation noted in the credential-hint-icon entry below); verification instead relies on the Livewire test suite above, which fully mounts and renders the page, calls every action, and asserts on real (`Http::fake()`-simulated) request shapes and outcomes.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-15 - Meta Ad Account credential fields: "where do I find this" hint icons

Reason:

- Owner reported "Test Connection" failing with Meta's `OAuthException` code 190, "Invalid OAuth access token - Cannot parse access token." Diagnosed via `tinker` (length/format only, never printing the actual secret): the stored Access Token on the real "ZamZam International" account is 32 characters and matches a 32-char hex shape — the format of a Meta **App Secret**, not a real OAuth access token (those are long, usually 100+ characters, and start with `EAA`). This is a data-entry problem, not a code bug — most likely the wrong value was pasted into the Access Token field at some point. Owner then asked for an **(i)** info icon on every credential field showing exactly where in Meta's UI to find the correct value, to prevent this mix-up going forward.

What happened:

- Added `->hintIcon(Heroicon::OutlinedInformationCircle)->hintIconTooltip(...)` to all 5 credential fields on `MetaAdAccountResource`'s form (App ID, App Secret, Access Token, Ad Account ID, Facebook Page ID). Each tooltip names the exact Meta screen (developers.facebook.com → My Apps → Settings > Basic for App ID/Secret; business.facebook.com/settings → System Users for the Access Token; → Accounts > Ad Accounts / Accounts > Pages for the IDs) and, for Access Token, what it should look like (long, starts with `EAA`).
- No behavior change — purely an inline help addition. Existing `mutateFormDataBeforeFill`/`mutateFormDataBeforeSave` credential round-trip logic (previous entry below) is untouched.

Important changed files:

- `app/Filament/Resources/MetaAdAccounts/MetaAdAccountResource.php`
- `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaAdAccountResourceTest.php`: 4 passed (28 assertions) — form still fills/saves/validates correctly with the new hint icons present.
- Browser-verification was not completed this round — logging into the local admin panel to visually confirm the tooltip render was blocked by this session's own safety guardrails (typing a password into a login field), so this is confirmed via the Livewire form test above plus reading `vendor/filament/forms/src/Components/Concerns/HasHint.php` to confirm `hintIcon()`/`hintIconTooltip()` are real, stable Filament 5 APIs. Worth a quick manual glance next time the owner is in the Edit Ad Account screen.
- Root-cause finding on the real account (the actual bug the owner asked about) was reported directly to the owner in chat with fix steps (get a real long-lived System User token from Meta Business Settings and re-enter it) — no code fix possible for that part since it's a real credential value only the owner can supply.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-15 - Fix: Meta Ad Account edit form losing credentials on save

Reason:

- While verifying Phase D, editing the real "ZamZam International" Meta Ad Account showed App Secret/Access Token blank and failed required-field validation. Root cause: `MetaAdAccount::$hidden = ['credentials']` (added in Phase A) excludes `credentials` from Filament's default `attributesToArray()`-based form fill (`EditRecord::fillFormWithDataAndCallHooks()`), so the edit form was blank on every load — and saving anyway would have overwritten the real encrypted credentials with blanks. Confirmed live: the save correctly failed validation, so nothing was actually lost, but the account could not be edited at all (not even to toggle Active, or set the new AI budget guardrails) without re-entering all four real values from scratch. The owner asked to fix this directly rather than leave it as a flagged follow-up.
- A second, related bug surfaced during the fix's own verification: saving a Max Daily Budget (AI guardrail) without a Min Daily Budget also failed validation. Root cause: Laravel's built-in `gte:field` rule (`Illuminate\Validation\Concerns\ValidatesAttributes::validateGte()`) cannot treat a blank compared-to field as "no floor" — it falls through to a same-type check between a number and `null` and always fails.

What happened:

- `EditMetaAdAccount` gained `mutateFormDataBeforeFill()`/`mutateFormDataBeforeSave()` — the exact same pattern already established in this codebase for `EditConversationChannel` (which has the identical `$hidden`-secrets problem on flat columns), adapted for `credentials` being one nested array attribute rather than separate columns. On fill: the non-secret sub-fields (`app_id`, `ad_account_id`, `page_id`) round-trip normally since they aren't sensitive; `app_secret`/`access_token` are always blanked. On save: if either secret is left blank, the existing encrypted value is merged back in from the record rather than being overwritten with null.
- `MetaAdAccountResource`'s `app_secret`/`access_token` fields are now `required()` only when creating a new account (`$record === null`), with helper text explaining they're "Never shown again once saved — leave blank to keep the existing value" when editing.
- Replaced `->gte('ai_daily_budget_min')` on the Max Daily Budget field with a custom closure-based rule that only compares the two values when both are actually filled — Min Daily Budget is meant to be optional ("leave blank for no floor"), and the built-in rule couldn't express that.

Important changed files:

- `app/Filament/Resources/MetaAdAccounts/Pages/EditMetaAdAccount.php`
- `app/Filament/Resources/MetaAdAccounts/MetaAdAccountResource.php`
- `tests/Feature/MetaAdAccountResourceTest.php` (new)
- `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaAdAccountResourceTest.php`: 4 passed (28 assertions) — blank-secrets-preserve-credentials, typing-new-secrets-overwrites, max-budget-alone-without-min, max-budget-below-min-still-rejected.
- `php artisan test tests/Feature/MetaAdsDashboardPageTest.php tests/Feature/MetaAdsCreationServiceTest.php tests/Feature/CreateMetaAdCampaignPageTest.php tests/Feature/MetaAdsAiAssistantServiceTest.php tests/Feature/MetaAdsAiAssistantPageTest.php tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAdsSyncServiceTest.php tests/Feature/MultiCompanyIsolationTest.php`: 56 passed (348 assertions) — confirms all four Meta Ads Manager phases are unaffected.
- Full `php artisan test`: 722 passed, 5 failed — the same pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); the +4 passing tests are exactly this fix's new tests, zero regressions.
- **Browser-verified against the real "ZamZam International" account** (Main Company, id 5): opened Edit — App ID/Ad Account ID/Page ID showed their real values, App Secret/Access Token were correctly blank. Set only a Max Daily Budget (no Min) and saved without touching any credential field — got a real **Saved** confirmation (this exact save had failed twice before the fix). Confirmed via `tinker` immediately after: the real App Secret and Access Token were still intact, and the new Max Daily Budget persisted. Both the guardrail value and the credentials were then reverted/left untouched respectively, so the account is back to its original state (guardrails unset) — the real credentials were never edited or exposed at any point.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-15 - Meta Ads Manager, Phase D: AI Marketing Assistant

Reason:

- Owner approved proceeding to Phase D, the last of the previously-approved 4-phase Meta Ads plan: an AI tool that looks at real stock data and recommends which product(s) are worth advertising, with real reasoning, budget/duration suggestions, and draft ad copy — never spending on its own.
- Re-entered plan mode to pin down two design questions the original stub left open: where budget guardrails live (per-Ad-Account columns, not the shared CRM AI settings page — budgets are currency-specific per account), and how "which campaign would be most effective" should be answered (a ranked top pick + up to 2 alternatives, each with its own real reasoning, rather than a single forced answer).

What happened:

- `meta_ad_accounts` gained `ai_daily_budget_min`/`ai_daily_budget_max`/`ai_max_duration_days`. **The assistant is switched off entirely for an account until `ai_daily_budget_max` is set** — there is no invented default spending ceiling. New `meta_ad_proposals` table stores every AI-drafted recommendation.
- New `MetaAdsAiAssistantService` — a grounded, tool-calling agent in the same shape as the existing WhatsApp `AiReplyService`, reusing the exact same per-company `AiSettingsService`/`AiLlmClient` provider+model+API-key configuration (zero new credential UI). Tools: `get_candidate_products` (real stock/margin/trailing-30-and-60-day sales velocity from `OrderItem`/`Order`), `get_product_ad_history` (real past `MetaAd` performance for that product, if any), `get_account_summary` (real recent account totals + the account's own guardrails), `submit_recommendation` (one `recommended` pick + up to 2 `alternatives`, each with its own reasoning, plus a `comparison_reasoning`), and `report_no_candidates` for "nothing worth advertising right now."
- Two safety nets, deliberately different in kind from `AiReplyService`'s "every ৳ must match a tool result" check (that check exists because that text is sent straight to a paying customer — here `reasoning_text` is internal, owner-facing only): (1) any `product_id` the model proposes that wasn't actually returned by `get_candidate_products` this run gets the whole submission rejected, no rows created — no hallucinated products; (2) `daily_budget`/`duration_days`/age-range/gender/call-to-action are all hard-clamped in code against the account's own configured guardrails regardless of what the model outputs.
- Regenerating recommendations for an account marks its previous `draft` proposals `dismissed` (kept, not deleted, as an audit trail) before inserting the new ones.
- New **AI Assistant** page in the Ads cluster: lists an account's recommendations (Product, Recommended badge, Daily Budget, Duration, Status, a "Reasoning" modal), a **Generate Recommendations** header action, and per-row **Review & Launch** (opens Phase C's `CreateMetaAdCampaign` pre-filled from the proposal — `?proposalId=...`) / **Dismiss** actions.
- `CreateMetaAdCampaign` gained proposal-prefill: opened with a `proposalId`, it pre-fills Account/Product/Budget/Audience/Ad-copy from the draft (with an on-page "Prefilled from an AI recommendation — review before launching" banner) and, on successful submit, marks the proposal `launched` and links the real `meta_ad_campaign_id` it just created. **The AI never calls Meta and never activates anything itself** — every object Phase C creates still always lands PAUSED, and only Phase B's explicit Resume ever starts real spending.
- `suggested_duration_days` is advisory only, shown to the owner as guidance text — Phase C's create form has no start/stop-time field, so it is never wired into a real Meta field; extending Phase C's scheduling is a separate future ask, not silently bundled in here.

Important changed files:

- `database/migrations/2026_08_15_010000_add_ai_guardrails_to_meta_ad_accounts_table.php`, `database/migrations/2026_08_15_020000_create_meta_ad_proposals_table.php`
- `app/Models/MetaAdProposal.php` (new), `app/Models/MetaAdAccount.php` (guardrail fields, `aiGuardrailsConfigured()`)
- `app/Filament/Resources/MetaAdAccounts/MetaAdAccountResource.php` (AI Marketing Assistant Guardrails section)
- `app/Services/MetaAdsAiAssistantService.php` (new)
- `app/Filament/Pages/MetaAdsAiAssistant.php` (new), `resources/views/filament/pages/meta-ads-ai-assistant.blade.php` (new)
- `app/Filament/Pages/CreateMetaAdCampaign.php` (proposal prefill + launched-status handoff)
- `app/Models/User.php` (`MetaAdProposal::class => 'marketing'`)
- `tests/Feature/MetaAdsAiAssistantServiceTest.php` (new), `tests/Feature/MetaAdsAiAssistantPageTest.php` (new), `tests/Feature/CreateMetaAdCampaignPageTest.php` (extended), `tests/Feature/MultiCompanyIsolationTest.php` (extended)
- `PROJECT_GUIDE.md` and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaAdsAiAssistantServiceTest.php tests/Feature/MetaAdsAiAssistantPageTest.php tests/Feature/CreateMetaAdCampaignPageTest.php tests/Feature/MultiCompanyIsolationTest.php`: 22 passed (201 assertions) — covers the guardrail/AI-not-configured gates, the happy path with budget/duration clamping, hallucinated-product rejection, regenerate-dismisses-old-drafts, `report_no_candidates`, proposal prefill, and launched-status handoff.
- `php artisan test tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAdsSyncServiceTest.php tests/Feature/MetaAdsDashboardPageTest.php tests/Feature/MetaAdsCreationServiceTest.php`: 30 passed (119 assertions) — confirms Phase A/B/C are unaffected.
- Full `php artisan test`: 718 passed, 5 failed — the same pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); the +12 passing tests are exactly this change's new tests, zero regressions.
- Browser-verified against the real ad account and the real Anthropic API: with no `ai_daily_budget_max` configured, **Generate Recommendations** correctly refused before any HTTP call fired, with a clear on-screen message. With guardrails set but no AI provider/key configured on the company, it correctly refused with a message pointing at the AI Assistant Settings page, again before any HTTP call. With a fake Anthropic key temporarily configured, the real call fired against `api.anthropic.com` and came back a genuine `401 authentication_error` ("API key is invalid"), which was caught and shown as a clear danger notification with zero `meta_ad_proposals` rows created and no crash. All test data (a throwaway Ad Account and the temporary fake AI key) was removed/reverted afterward — the real "ZamZam International" account and its real credentials were never touched.
- **A real, pre-existing bug found in passing (not part of Phase D, not fixed here)**: editing an existing Meta Ad Account in Filament shows the App ID/App Secret/Access Token/Ad Account ID fields blank and fails required-field validation on save, because `MetaAdAccount::$hidden = ['credentials']` (added in Phase A) excludes `credentials` from the form's fill — unlike `CourierProvider`, which this resource was modeled after and which does not hide its own `credentials`. Confirmed live against the real "ZamZam International" account: Save correctly failed validation both times and nothing was lost, but the account currently cannot be edited at all (not even to toggle Active, or set the new AI budget guardrails) without re-entering all four real credential values from scratch. Flagged as a separate follow-up task, not addressed in this change.

Commit status:

- Not committed yet - awaiting owner's approval. All four phases of the Meta Ads Manager plan are now implemented.

## 2026-08-14 - Meta Ads Manager, Phase C: create a real Campaign + Ad Set + Ad from a Product

Reason:

- Owner approved proceeding straight to Phase C of the previously-approved 4-phase Meta Ads plan: creating new campaigns/ad sets/ads from ZamZam, tied to a real Product.
- Researching Meta's actual object-creation requirements surfaced a real gap the earlier plan didn't account for: an ad's creative always attaches to a real **Facebook Page** (`object_story_spec.page_id`) — nothing like it existed anywhere in this app (the `page_id` field on `StorefrontSettingResource` is an unrelated *content page* selector for the footer menu). Re-entered plan mode specifically to work this out before writing code, rather than guessing at Meta's field shapes.

What happened:

- `MetaAdAccount` gained a `page_id` (Facebook Page ID) field inside its existing encrypted `credentials` blob — no migration needed. Only required for creating ads; existing accounts keep viewing/syncing/pausing without it (`canCreateAds()` helper).
- `MetaMarketingApiClient` gained the 5 real write calls needed to create objects: `createCampaign()`, `createAdSet()`, `uploadAdImage()` (raw bytes, base64-encoded — never a public-URL fetch, so it works even when the storefront isn't publicly reachable), `createAdCreative()`, `createAd()`. Complex params (`targeting`, `object_story_spec`, `creative`, `special_ad_categories`) are JSON-encoded before sending, matching Meta's documented convention. **Every create call hard-codes `status: PAUSED`**, regardless of anything the caller passes — nothing this feature creates can ever start spending without a later, separate, explicit Resume (Phase B).
- Deliberately scoped to exactly one objective (`OUTCOME_TRAFFIC` / `LINK_CLICKS` / `IMPRESSIONS`) — the one well-documented combination that needs no pixel/conversions setup — and the ad image always comes from the selected **Product's own image** (no separate manual upload path). Both are explicit, documented scope limits.
- New `MetaAdsCreationService` orchestrates the chain (Campaign → Ad Set → Ad Image → Ad Creative → Ad). Each local row is persisted **immediately after its own Meta call succeeds** — deliberately not one wrapped DB transaction — so a mid-chain failure (e.g. the ad-set call gets rejected) leaves the local database reflecting exactly what's really sitting on Meta, never silently losing track of an already-created PAUSED campaign. The destination link reuses `StorefrontCartRecord::recoveryUrl()`'s own absolute-URL pattern (`'https://'.$company->domain.route(...)`).
- New **New Campaign** page (`CreateMetaAdCampaign`, reached from a header action on the Meta Ads Dashboard): Campaign name/budget, Audience (age range, gender, Bangladesh-only geo), and Ad (Product picker that auto-fills headline/primary text/price-based copy — editable — plus a Call To Action). Submitting redirects back to the dashboard with the new campaign in view.
- **Bug caught by the page's own test, not by hand**: with only one ad account configured (the common single-business case), the Ad Account selector is hidden — and Filament doesn't dehydrate a hidden field's value by default, so the account was silently missing from the submitted form state every time. Fixed with `->dehydratedWhenHidden()`. Without this, campaign creation would have failed 100% of the time for any owner with just one connected account.

Important changed files:

- `app/Models/MetaAdAccount.php` (`page_id`/`pageId()`/`canCreateAds()`), `app/Models/MetaAdSet.php`, `app/Models/MetaAd.php` (`SOURCE_META`/`SOURCE_ERP` constants)
- `app/Filament/Resources/MetaAdAccounts/MetaAdAccountResource.php` (Facebook Page ID field)
- `app/Services/MetaMarketingApiClient.php` (5 new write methods + shared `write()` helper, refactored `updateStatus()`/`updateBudget()` onto it)
- `app/Services/MetaAdsCreationService.php` (new)
- `app/Filament/Pages/CreateMetaAdCampaign.php` (new), `resources/views/filament/pages/create-meta-ad-campaign.blade.php` (new)
- `app/Filament/Pages/MetaAdsDashboard.php` (New Campaign header action)
- `tests/Feature/MetaMarketingApiClientTest.php` (extended), `tests/Feature/MetaAdsCreationServiceTest.php` (new), `tests/Feature/CreateMetaAdCampaignPageTest.php` (new)
- `PROJECT_GUIDE.md` and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAdsSyncServiceTest.php tests/Feature/MetaAdsDashboardPageTest.php tests/Feature/MetaAdsCreationServiceTest.php tests/Feature/CreateMetaAdCampaignPageTest.php tests/Feature/MultiCompanyIsolationTest.php`: 40 passed (274 assertions) — covers success + mid-chain-failure + missing-Page-ID + missing-product-image paths, and confirms Phase A/B still work after the shared `write()` refactor.
- Full `php artisan test`: 706 passed, 5 failed — the same pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); zero new regressions.
- Browser-verified against the real Meta API (dummy-credential throwaway account + Facebook Page ID + a real product with an image): filled the New Campaign form, confirmed the Product picker auto-fills headline/primary text from real product name/price, submitted — Meta genuinely rejected the create call (fake credentials), zero local rows were persisted (correct: the very first step failed), and the page stayed intact with the form data preserved, no crash. Test account removed afterward.
- **Honesty about verification limits** (documented in the plan and here, not glossed over): without a real connected ad account + Page, the full success path (Meta actually accepting all 5 calls) could only be verified structurally (`Http::fake()` request-shape assertions matching Meta's published API docs), not confirmed end-to-end against Meta's live validation. The owner should test the complete flow against a real account before relying on it.

Commit status:

- Not committed yet - awaiting owner's approval. Phase D (AI marketing assistant) remains a separate, later, owner-approved phase.

## 2026-08-14 - Meta Ads Manager, Phase B: Pause/Resume and Daily Budget editing

Reason:

- Owner approved proceeding straight to Phase B of the previously-approved 4-phase Meta Ads plan: write-back management (Pause/Resume + budget editing) on top of the read-only Phase A dashboard shipped earlier the same day.

What happened:

- `MetaMarketingApiClient` gained `updateStatus()` (POST `/{object_id}` with `status`, works for campaigns/ad sets/ads alike) and `updateBudget()` (POST `/{object_id}` with `daily_budget`/`lifetime_budget`, converting our major-currency-unit values to Meta's minor-unit/cents contract — the mirror image of `MetaAdsSyncService::minorToMajor()`).
- `MetaAdsDashboard` gained a **Pause**/**Resume** row action (label/icon/color react to the record's current status, `requiresConfirmation()`) at all three drill levels, and an inline-editable **Daily Budget** column (`TextInputColumn`) at the Campaign and Ad Set levels only — Ads have no budget of their own in Meta's model, so that level keeps a plain read-only column. **Lifetime Budget** stays read-only everywhere: a campaign/ad set uses one budget type or the other in Meta's own model, and Daily Budget is the common case.
- Both write paths follow the same rule: call Meta first, and only touch the local row (or let the input keep the new value) once Meta actually confirms success. A failed or rejected call leaves both Meta and the local database exactly as they were and shows a danger notification — never a silent or partial update. This was verified against the real Meta API, not just mocked: a throwaway test account with dummy credentials got a real rejection from `graph.facebook.com` for both a Pause click and a Daily Budget edit, and in both cases the local row was correctly left unchanged.

Important changed files:

- `app/Services/MetaMarketingApiClient.php` (`updateStatus()`, `updateBudget()`)
- `app/Filament/Pages/MetaAdsDashboard.php` (Pause/Resume action, editable Daily Budget column, `updateBudget()`/`toggleStatus()` helpers)
- `tests/Feature/MetaMarketingApiClientTest.php`, `tests/Feature/MetaAdsDashboardPageTest.php`
- `PROJECT_GUIDE.md` and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAdsDashboardPageTest.php`: 16 passed (59 assertions), covering successful and failed status/budget round trips and the Ad-level read-only column.
- Full `php artisan test`: 692 passed, 5 failed — the same pre-existing, unrelated baseline failures (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); zero new regressions.
- Browser-verified against the real dev database and the real Meta API: seeded a throwaway campaign under a dummy-credential account, clicked Pause (confirmed the modal, confirmed the action) and edited Daily Budget inline — both produced a real rejection from Meta and the local row (and the input's displayed value, after a fresh reload) correctly stayed unchanged. Test data removed afterward.

Commit status:

- Not committed yet - awaiting owner's approval. Phase C (campaign/ad set/ad creation, always landing PAUSED) and Phase D (AI marketing assistant) remain separate, later, owner-approved phases.

## 2026-08-14 - Products mobile header action menu

Reason:

- The owner asked to clean up the Products mobile header by moving all five wrapped header buttons into one navigation menu on the right side of the page title.

What happened:

- Added one icon-only native Filament `ActionGroup` for mobile containing Import CSV, Sample CSV, Bulk Update Stock, Export CSV, and New product.
- Kept the existing five individual header buttons at the desktop `lg` breakpoint and above.
- Reused the same permission checks, URLs, upload form, import handler, icons, and labels in both presentations.
- Added Products-only responsive header alignment so the native menu sits beside the title instead of below it; other Filament page headers are unchanged.

Important changed files:

- `app/Filament/Resources/Products/Pages/ListProducts.php`
- `resources/css/filament/admin/theme.css`
- `tests/Feature/ProductHeaderActionsResponsiveTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/ProductHeaderActionsResponsiveTest.php tests/Feature/ProductStatsOverviewTest.php tests/Feature/ProductCsvTest.php tests/Feature/BulkUpdateStockPageTest.php`: 16 passed (111 assertions).
- Scoped PHP syntax checks and `vendor\bin\pint --test` passed.
- `php artisan view:cache`, scoped `git diff --check`, and `npm.cmd run build` passed; the compiled theme contains the Products-only mobile header selector.
- In-app browser connection was unavailable in this session, so verification used rendered Livewire HTML, action registration, responsive CSS assertions, and the production asset build.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-14 - Meta Ads Manager, Phase A: account integration, sync, and a read-only Campaigns/Ad Sets/Ads dashboard

Reason:

- The owner asked to visit Nuport's `/ads/meta` page and build an equivalent Meta Ads dashboard for managing Campaigns/Ad Sets/Ads. Nuport's own page turned out to be integration + analytics only (App ID/Secret/Access Token/Ad Account ID settings, then an analytics screen — no campaign-creation UI). Asked to clarify scope, the owner wants: (1) managing existing campaigns, (2) creating new campaigns/ad sets/ads from ZamZam, and (3) an AI assistant that analyzes stock/margin data to draft ad copy, suggest budget/duration, and can launch campaigns. Given the size (5 new tables, a write-capable external API with real money at stake, an AI agent) and this codebase's own precedent for shipping large AI/integration features in checkpointed phases (the WhatsApp AI auto-reply feature's steps 13.0/13.3/14), this was scoped into 4 phases with owner sign-off on the phased plan. **This entry covers Phase A only**: real account integration, a sync pipeline, and a read-only dashboard. Two safety rules are locked in for every later phase: nothing this feature creates on Meta is ever launched automatically (always created PAUSED, a separate explicit Activate action is the only thing that starts real spending), and the future AI assistant only ever reasons over this app's own real stock/sales/ad-performance data — never fabricated "market analysis".

What happened:

- New tables `meta_ad_accounts` (encrypted `credentials`: app_id/app_secret/access_token/ad_account_id, mirrors `CourierProvider`'s `text` + `encrypted:array` shape so it works on MySQL too), `meta_ad_campaigns`, `meta_ad_sets`, `meta_ads` — all company-scoped (`BelongsToCompany` + `CompanyScope`, added to `MultiCompanyIsolationTest`).
- `MetaMarketingApiClient` (read-only): wraps the real Meta Graph Marketing API (`config('services.meta.graph_api_version')`, currently v25.0) — `campaigns()`/`adSets()`/`ads()` each request the object fields plus a nested `insights.date_preset(...)` edge in one call (spend/impressions/clicks/ctr/cpc/reach/actions), and `verifyCredentials()` powers a **Test Connection** action. Mirrors `SteadfastCourierClient`'s shape (blank-credential guard, `Http::...->throw()->json()`).
- `MetaAdsSyncService` + `meta-ads:sync` console command: pulls Campaigns → Ad Sets → Ads with insights for an account, upserts local rows by `meta_id`, and stamps `last_synced_at`/`last_sync_error`/`sync_failure_count` on the account — a failing account never throws out or blocks other accounts, mirroring `SyncCourierStatuses`.
- New **Ads** cluster: `MetaAdAccountResource` (credential entry, Test Connection, mirrors `CourierProviderResource`) and `MetaAdsDashboard` (a `Page implements HasTable`, mirrors `BulkUpdateStock`/`CourierMerchantDashboard`) — an account selector, a Sync Window (`date_preset`) selector, a **Sync Data** action, real Spend/Impressions/Clicks/CTR/CPC stat cards (CTR/CPC are weighted aggregates, never a naive average of each row's own ratio), and a Campaigns → Ad Sets → Ads table you drill into via `#[Url]`-bound `campaignId`/`adSetId` with a clickable breadcrumb — all within one page, no full navigation.
- New `marketing.view`/`marketing.create`/`marketing.update`/`marketing.delete` permissions (`User::CUSTOM_PERMISSION_OPTIONS`/`MODEL_MODULES`), granted to the Manager role by default (Super Admin already has everything).
- Live-verified against the real Meta Graph API with a throwaway test account (dummy credentials, deleted afterward): **Test Connection** and **Sync Data** both actually reached `graph.facebook.com` and got back a real `OAuthException` ("Invalid OAuth access token"), which was caught and stamped exactly as designed — proving the request shape is correct and failures never crash the page.

Important changed files:

- `database/migrations/2026_08_14_04-070000_*.php` (4 new tables)
- `app/Models/MetaAdAccount.php`, `MetaAdCampaign.php`, `MetaAdSet.php`, `MetaAd.php`
- `app/Services/MetaMarketingApiClient.php`, `MetaAdsSyncService.php`
- `app/Console/Commands/SyncMetaAdsData.php`
- `app/Filament/Clusters/Ads.php`, `app/Filament/Resources/MetaAdAccounts/*`, `app/Filament/Pages/MetaAdsDashboard.php`, `resources/views/filament/pages/meta-ads-dashboard.blade.php`
- `app/Models/User.php` (new `marketing.*` permissions)
- `tests/Feature/MetaMarketingApiClientTest.php`, `MetaAdsSyncServiceTest.php`, `MetaAdsDashboardPageTest.php`, `MultiCompanyIsolationTest.php`
- `PROJECT_GUIDE.md` and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/MetaMarketingApiClientTest.php tests/Feature/MetaAdsSyncServiceTest.php tests/Feature/MetaAdsDashboardPageTest.php tests/Feature/MultiCompanyIsolationTest.php`: 22 passed.
- Full `php artisan test`: 681 passed, 5 failed — the same pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); zero new regressions.
- Browser-verified end-to-end against the real dev database (migrations applied via plain `php artisan migrate`, no `--fresh`, no seeders): created and deleted a throwaway Meta Ad Account, confirmed Test Connection and Sync Data both reach the real Meta API (see above), confirmed the dashboard's empty state, stat cards, and full 3-level Campaigns → Ad Sets → Ads drill-down with breadcrumb navigation using seeded rows (removed afterward, demo data left unchanged).

Commit status:

- Not committed yet - awaiting owner's approval. Phase B (Pause/Resume + budget editing), Phase C (campaign/ad set/ad creation), and Phase D (AI marketing assistant) are separate, later, owner-approved phases.

## 2026-08-14 - Products stat currency formatting and mobile typography

Reason:

- The owner asked the Products stat cards to use the Taka symbol instead of `BDT`, suppress `.00` for whole amounts, keep decimal output only for fractional amounts, and reduce only the mobile label/value sizes to 12px/16px.

What happened:

- Added `ProductStatsOverview::formatCurrency()`: whole values render like `৳ 2,149,140`; fractional values are rounded to currency precision and render like `৳ 360,175.50`.
- Currency cards call the formatter instead of hard-coding `BDT` and two decimals.
- The custom Filament card labels now use `!text-xs lg:!text-sm` (12px mobile, 14px desktop); values use `!text-base lg:!text-[20px]` (16px mobile, 20px desktop). Grid, padding, icons, and desktop layout remain unchanged.

Important changed files:

- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php`
- `resources/views/filament/resources/products/widgets/product-stats-overview.blade.php`
- `tests/Feature/ProductStatsOverviewTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/ProductStatsOverviewTest.php tests/Feature/BusinessOverviewLayoutTest.php`: 6 passed (45 assertions), including exact integer/fractional Taka formatting and rendered responsive typography classes.
- Scoped PHP syntax checks, `vendor\\bin\\pint --test`, `php artisan view:cache`, scoped `git diff --check`, and `npm.cmd run build` passed.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-14 - Bulk Update Stock now stages blank inputs and saves them together

Reason:

- The owner asked to remove **Upload Stock CSV** and **Stock CSV Sample** from the Bulk Update Stock header, replace them with one button that saves this page's changes, and keep every New Stock field empty by default.

What happened:

- Replaced both header actions with one native Filament **Save changes** action.
- Every New Stock `TextInputColumn` now renders blank. Entered values are validated and staged in the page's `stockUpdates` Livewire property; editing or leaving a field does not update the database.
- Save changes validates the complete draft again, resolves only products visible through the current company scope, locks all selected products in one database transaction, and applies only nonblank changed rows through `Product::setStockFromProductForm()`. Stock movements therefore remain ledger-safe, blank rows stay untouched, and all fields clear after a successful save.
- Saving without entering any value shows a warning instead of performing a write.

Important changed files:

- `app/Filament/Resources/Products/Pages/BulkUpdateStock.php`
- `tests/Feature/BulkUpdateStockPageTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/BulkUpdateStockPageTest.php tests/Feature/ProductStockCsvTest.php tests/Feature/ProductStockFormTest.php tests/Feature/StockMovementTest.php tests/Feature/MultiCompanyIsolationTest.php`: 29 passed (223 assertions).
- Coverage confirms default blank inputs, no database write before Save changes, removal of both previous header actions, multi-row transactional save, blank-row preservation, stock-movement creation, and company isolation.
- Scoped PHP syntax checks, `vendor\\bin\\pint --test`, `php artisan view:cache`, and scoped `git diff --check` passed.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-14 - Products header stats use the main Dashboard's full-width layout

Reason:

- The owner reported that the Products header cards were compressed into a narrow block and asked for the same overall layout as the app's main Dashboard cards.

What happened:

- Root cause: `ProductStatsOverview` is a hand-built plain Filament `Widget`, and its custom Blade view did not use Filament's native `<x-filament-widgets::widget>` root wrapper. Adding `columnSpan = 'full'` in PHP alone therefore had no DOM element on which Filament could emit the span CSS, leaving the inner 5-column grid compressed inside one of the page header's two desktop columns.
- Added both parts of Filament's native widget contract: `protected int|string|array $columnSpan = 'full'` on the widget and `<x-filament-widgets::widget>` around the custom Blade grid. The wrapper now renders `--col-span-lg: 1 / -1`, so each row's 5 cards divide the entire available Products-page width equally. The existing card design remains unchanged: 2 cards per row on mobile, 20px values, and 10px card padding.
- Added a rendered-Livewire regression test covering the native wrapper, emitted full-span CSS, and responsive sizing classes.

Important changed files:

- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php`
- `resources/views/filament/resources/products/widgets/product-stats-overview.blade.php`
- `tests/Feature/ProductStatsOverviewTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/ProductStatsOverviewTest.php tests/Feature/BusinessOverviewLayoutTest.php`: 5 passed (39 assertions), covering both the Products cards and the main Dashboard layout used as the reference. The Products assertion verifies the rendered native wrapper emits `--col-span-lg: 1 / -1`.
- Scoped PHP syntax checks and `vendor\\bin\\pint --test` passed.
- `php artisan view:cache` passed.
- `npm.cmd run build` passed and rebuilt the Filament theme/assets.
- Automated browser visual QA could not connect to the in-app browser in this environment; the layout contract is covered by the rendered Livewire assertion for `--col-span-lg: 1 / -1` plus the Blade responsive-grid assertions.

Commit status:

- Not committed yet - awaiting owner's approval.

## 2026-08-14 - Damage stock movement type, Total Shortage stat, and a real Bulk Update Stock screen

Reason:

- The owner reviewed the previous round (3 inventory stat cards + a CSV-upload Bulk Update Stock modal) and asked for three follow-ups: (1) we don't track Wastage, but a real **Damage** concept can be tracked instead; (2) **Total Shortage** is wanted after all, defined precisely — "at a glance the total shortage count; clicking it shows which product has how many pieces in stock and how many short"; (3) **Bulk Update Stock** shouldn't be a CSV-upload modal as the primary flow — it should open a searchable/filterable list of every product with the current stock, the shortfall, and an inline **New Stock** field, editable in place.

What happened:

- Added a real `'damage'` type to `StockMovement::TYPES`, wired everywhere `'sale'` was already special-cased so damage reduces stock the same way: `StockMovementService::signedStockSum()` (raw SQL), `normalizeQuantity()`, `signedQuantityFor()`, and `validate()` (now requires a `reason` for damage too, plus a damage-specific "insufficient stock" message). `StockMovementForm`'s quantity/reason helper text and both Stock-Movements-table Type-badge colors (`StockMovementsTable.php`, `StockMovementsRelationManager.php`) branch for damage the same way they already did for adjustment/sale. Previously damage was informally recorded as a generic `adjustment` (see the pre-existing test at `tests/Feature/StockMovementTest.php`, reason "Damaged stock removal") — it now has its own reportable type.
- **Total Shortage** reuses the *exact* predicate the Products table's existing "Low stock" filter already uses (`whereColumn('stock', '<=', 'reorder_level')`) — not a new rule, just surfaced as a stat. **Total Damage** sums `StockMovement` rows of the new type. Both added to `ProductStatsOverview::stats()`, bringing the grid to 10 cards (still 5/2 desktop/mobile, unchanged styling).
- Built a new custom Filament page, `app/Filament/Resources/Products/Pages/BulkUpdateStock.php` (`Page implements HasTable`), registered on `ProductResource::getPages()` at `/admin/inventory/products/bulk-stock` — same architecture as `CourierMerchantDashboard`. Every product in one searchable (name/SKU/ID/category) table, with In Stock, Reorder Level, Short By (computed), and an inline-editable **New Stock** `TextInputColumn`. Editing New Stock never writes `stock` directly — it calls `Product::setStockFromProductForm()`, the same ledger-safe method the product edit form and the stock-CSV importer already use, so a real `StockMovement` (opening/adjustment) is always created. Verified live: editing 166 → 170 on a real demo product produced an `adjustment` movement (`quantity: 4`, reason "Product form stock correction") and the value persisted after a fresh page reload; reverted back to 166 afterward.
- `#[Url] public bool $shortageOnly` scopes the table's base query (not Filament's own filter-state) so the Total Shortage stat card can deep-link here via a plain `?shortageOnly=1` query string. Deliberately not Filament's own `?tableFilters=...` mechanism — `CourierMerchantDashboard`'s own code comment documents that as not applying from a cold GET request in this install; a plain Livewire `#[Url]` property (the same technique that page's own `providerFilter` already uses) sidesteps it entirely. Verified live: `?shortageOnly=1` correctly shows "No products" (matching the current Total Shortage count of 0 in demo data).
- `ProductCsvService::importStock()`/`sampleStock()` (unchanged) and their header actions moved from `ListProducts` onto `BulkUpdateStock::getHeaderActions()` — still available as a power-user path for very large catalogs, now contextually placed on the screen where bulk stock editing happens. `ListProducts`'s own `bulkUpdateStock` action is now a plain link to the new page (matching the existing `exportCsv`/`downloadSampleCsv` plain-link pattern) instead of a FileUpload modal.

Important changed/added files:

- `app/Models/StockMovement.php`, `app/Services/StockMovementService.php`, `app/Filament/Resources/StockMovements/Schemas/StockMovementForm.php`, `app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php`, `app/Filament/Resources/Products/RelationManagers/StockMovementsRelationManager.php`
- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php`, `resources/views/filament/resources/products/widgets/product-stats-overview.blade.php` (dynamic `<a>`/`<div>` tag per stat, driven by an optional `url` key)
- `app/Filament/Resources/Products/Pages/BulkUpdateStock.php` (new), `resources/views/filament/resources/products/pages/bulk-update-stock.blade.php` (new), `app/Filament/Resources/Products/ProductResource.php` (registered the page)
- `app/Filament/Resources/Products/Pages/ListProducts.php` (header actions simplified)
- `tests/Feature/StockMovementTest.php` (damage sign/reason coverage), `tests/Feature/ProductStatsOverviewTest.php` (extended), `tests/Feature/BulkUpdateStockPageTest.php` (new)

Verification:

- `php artisan test tests/Feature/StockMovementTest.php tests/Feature/ProductStatsOverviewTest.php tests/Feature/ProductStockCsvTest.php tests/Feature/ProductCsvTest.php tests/Feature/BulkUpdateStockPageTest.php tests/Feature/PurchaseTest.php tests/Feature/ProductVariantTest.php tests/Feature/ProductStockFormTest.php tests/Feature/MultiCompanyIsolationTest.php`: 50 passed (278 assertions).
- Full `php artisan test`: 668 passed, 5 failed — the same 5 pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); zero new regressions (668 = the prior 660 baseline + these 8 new tests).
- Browser-verified against real demo data: all 10 cards render (Total Shortage 0, Total Damage 0 — correct, no demo product is currently short or damaged), 10px padding / 20px value font intact, Total Shortage renders as a real `<a href="…/bulk-stock?shortageOnly=1">` while every other card stays a plain `<div>`; Bulk Update Stock page loads with the 4 expected header actions moved correctly, the table renders with working search/columns, an inline stock edit round-tripped through a real `StockMovement` and was reverted; the Stock Movement create form shows "Damage" in the Type dropdown with the correct quantity/reason helper text and required-reason behavior; mobile-preset (375px) confirmed 2-per-row across all 10 cards.
- No new Tailwind arbitrary-value classes were introduced, so `npm run build` was not required.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Products page: 3 more inventory stat cards + Bulk Update Stock action

Reason:

- The owner visited the competitor ERP's Inventory page (`app.nuport.io/inventory-products`) and asked for its header metric cards to be added next to the existing 5 Products stat cards, plus an equivalent to its Action menu's "Bulk Update Inventory" tool.

What happened:

- Browsed the competitor's Inventory page and its Action menu, and cross-checked each of its 6 header cards against our actual schema. Only 3 map onto real, already-tracked data — **Total Available Quantity**, **Total Stock Value**, **Total Purchase Cost** — and were built. The other 3 (**Total Shortage Quantity**, **Total Wastage**, **Total Expired**) have no backing concept anywhere in this app (no backorder tracking, no wastage `StockMovement` type, no product expiry-date field) and were deliberately not built, per `CLAUDE.md`'s rule against inventing business rules — documented as a finding for the owner to prioritize, and explained directly when the owner asked why they were skipped.
- Extended `ProductStatsOverview::stats()` from 5 to 8 entries, reusing the exact same blade grid/CSS from the prior 5/2-column, 20px-value, 10px-padding entry below — no new styling work needed. Added a `formatCurrency`/`isCurrency` flag to the blade so the two new money cards render as `BDT 1,234.00` instead of a bare integer.
- Correctness detail: a variant-bearing product's own `stock` is already a live mirror of the sum of its variants' stock (`ProductVariant::syncProductStock()`), so the new quantity/value aggregates exclude `has_variants = true` products' own stock/cost_price and only add their variants on top, to avoid double-counting. Added `ProductVariant::effectiveCostPrice()` (mirrors the existing `effectiveSalePrice()` parent-fallback pattern) for the value calculation.
- Total Purchase Cost sums `PurchaseItem.subtotal + PurchaseItem.allocated_cost` (the item's own cost plus its share of allocated landed costs — `allocated_cost` alone is only the landed-cost *share*, not the full spend) across `received`-status purchases only; draft/cancelled purchases are excluded.
- Investigated the competitor's "Bulk Update Inventory" action directly in the browser: it opens a small modal that only asks for a Warehouse, then completes with no further visible step (almost certainly a warehouse-scoped CSV/export the sandboxed browser can't confirm the exact shape of). Found we already have an equivalent full **Import CSV** action; the owner confirmed they want a separate, focused **Bulk Update Stock** action instead — a 2-column `sku, stock` CSV, purpose-built for correcting stock counts without touching prices/names.
- Added `ProductCsvService::importStock()`/`sampleStock()` (reusing the existing `import()`'s header-parsing/transaction/error-aggregation helpers). Each row is matched against `ProductVariant` first (a variant's own `stock` is a live counter, updated directly — its `saved` hook already re-syncs the parent), then `Product` (updated via the existing `setStockFromProductForm()`, the same method the product edit form uses, which creates the correct `opening`/`adjustment` `StockMovement`). A variant-bearing product's own SKU is rejected with a row error explaining its stock is derived from its variants.
- Added the `products.stock.sample` route + `ProductCsvController::stockSample()`, and two new Products-list header actions: **Bulk Update Stock** and **Stock CSV Sample**.

Important changed/added files:

- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php`, `resources/views/filament/resources/products/widgets/product-stats-overview.blade.php`
- `app/Models/ProductVariant.php` (`effectiveCostPrice()`)
- `app/Services/ProductCsvService.php` (`importStock()`, `sampleStock()`, `STOCK_HEADINGS`)
- `app/Http/Controllers/Admin/ProductCsvController.php`, `routes/web.php`
- `app/Filament/Resources/Products/Pages/ListProducts.php`
- `tests/Feature/ProductStatsOverviewTest.php` (extended), `tests/Feature/ProductStockCsvTest.php` (new), `tests/Feature/ProductCsvTest.php` (extended auth-check)

Verification:

- `php artisan test tests/Feature/ProductStatsOverviewTest.php tests/Feature/ProductStockCsvTest.php tests/Feature/ProductCsvTest.php tests/Feature/ProductStockFormTest.php tests/Feature/ProductVariantTest.php tests/Feature/ProductCarouselTest.php tests/Feature/PurchaseTest.php tests/Feature/MultiCompanyIsolationTest.php`: 40 passed (262 assertions).
- Full `php artisan test`: 660 passed, 5 failed — the same 5 pre-existing, unrelated baseline failures from before this change (`ReleaseNotesTest` x3, `StorefrontCustomerAdvanceAndComplaintTest` x1, `StorefrontIncompleteCheckoutRecoveryTest` x1); zero new regressions.
- Browser-verified against the real demo data: all 8 cards render correctly (Total Available Quantity 1,275; Total Stock Value BDT 1,251,680.00; Total Purchase Cost BDT 360,175.00), `getComputedStyle` confirmed 8 cards / 10px padding / 20px value font intact; desktop screenshot shows 5+3 layout, mobile-preset (375px) shows 2 per row throughout. The two new header actions render with the correct labels/helper text; the Stock CSV Sample link returns 200 with the exact `sku,stock` content. The actual file-upload step of Bulk Update Stock could not be driven through browser automation (no native file-picker support in this tool), so that path relies on `ProductStockCsvTest`'s 7 passing tests, which exercise the identical service method directly.
- No new Tailwind arbitrary-value classes were introduced, so `npm run build` was not required this time.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Category icon picker selection persistence and Add Icon action

Reason:

- The owner reported that searching and selecting a category icon immediately restored the full icon list, the selected value did not reliably remain saved, and the modal had no explicit action button to apply the icon.

What happened:

- Confirmed root cause after inspecting the local app's compiled Blade output: `@js($icon['value'])` was embedded inside a quoted Blade-component attribute, where it was not compiled. The browser therefore received a literal invalid Alpine expression such as `choose(@js($icon['value']))`, so clicking an icon could not select it at all. Separately, the delegated search script reset the search on every icon click.
- Icon values now render through a safe `data-zz-category-icon-value="heroicon-..."` attribute and the valid static Alpine expression `choose($el.dataset.zzCategoryIconValue)`. Regression assertions inspect the rendered Livewire HTML so a literal uncompiled `@js($icon...)` cannot return unnoticed.
- The picker now keeps one Livewire-entangled committed `state` plus a modal-local `pendingIcon`. Clicking an icon only updates/highlights `pendingIcon`, so the filtered results remain visible and no form-state request is made yet.
- Added a native Filament **Add Icon** primary action in a sticky modal footer. It is disabled until an icon is selected and is the only icon-tile workflow that copies `pendingIcon` into the form state. Added a secondary **Cancel** action that closes without changing the existing icon; **Use category initial** still explicitly clears the field.
- The delegated search helper now resets only when the picker opens, not when an icon is selected.
- Added edit-page persistence coverage proving the selected Heroicon is saved to the category record.

Important changed files:

- `resources/views/filament/forms/components/category-icon-picker.blade.php`
- `resources/views/filament/partials/category-icon-search.blade.php`
- `tests/Feature/CategoryMediaTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/CategoryMediaTest.php`: 4 passed (50 assertions).
- Scoped PHP syntax and Pint checks passed; scoped `git diff --check` passed.
- `php artisan view:cache` and `npm.cmd run build` both passed.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Main Dashboard Business Overview compact 5/2 card layout

Reason:

- The owner asked for the main Dashboard's Business Overview cards to show 5 per row on desktop, 2 per row on mobile, with 20px number/value text and 10px card padding.

What happened:

- Kept `BusinessOverview` as Filament's native `StatsOverviewWidget` and overrode its native schema grid columns to `default => 2` and `lg => 5`.
- Added the scoped `zz-business-overview-stat` class to this widget's stats only. The Filament admin theme sets the root card padding to exactly `10px` and the value font size to exactly `20px` with a matching line-height; no other stat widget is affected.
- Added a regression test that checks the PHP grid configuration, every stat's scoped class, the exact CSS rules, and the rendered Livewire HTML's 2/5-column CSS variables.

Important changed/added files:

- `app/Filament/Widgets/BusinessOverview.php`
- `resources/css/filament/admin/theme.css`
- `tests/Feature/BusinessOverviewLayoutTest.php`
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `CHANGELOG.md`

Verification:

- `php artisan test tests/Feature/BusinessOverviewLayoutTest.php`: 1 passed (18 assertions).
- Scoped PHP syntax checks and `vendor\\bin\\pint --test` passed.
- `npm.cmd run build` passed and compiled the updated Filament theme bundle.
- Automated browser visual QA could not run because the in-app browser connection was unavailable in this environment; the responsive CSS variables are covered by the server-rendered Livewire assertion instead.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Product stat cards: exact 5/2-column grid, 20px value, 10px padding

Reason:

- The owner reviewed the stat cards from the previous entry below and asked for an exact layout: 5 cards per row on desktop, 2 per row on mobile, 20px value font size, 10px card padding.

What happened:

- Rebuilt `ProductStatsOverview` from a `StatsOverviewWidget`/`Stat::make()` widget into a hand-built `Widget` + Blade view (`resources/views/filament/resources/products/widgets/product-stats-overview.blade.php`), reusing Filament's own native `fi-wi-stats-overview-stat*` CSS classes on custom markup — the exact same technique `CourierMerchantDashboard`'s status cards already use (see the comment there), which was necessary because `Stat::make()` doesn't expose pixel-level padding/font-size control or a 5-column grid.
- Grid: `grid grid-cols-2 gap-4 lg:grid-cols-5` (2 cols below `lg`, 5 at `lg`+). Padding: `!p-[10px]` on the card's outer `.fi-wi-stats-overview-stat` element (verified via computed styles that this element, not `-content`, carries Filament's default 24px padding). Value font size: `!text-[20px]` on `.fi-wi-stats-overview-stat-value`.
- These are new Tailwind arbitrary-value utility classes that didn't exist in the previously compiled CSS bundle, so `npm run build` was required — confirmed via browser computed-style checks (`getComputedStyle`) before and after the rebuild: font-size went from Filament's default 30px to the requested 20px, outer padding from 24px to 10px.
- Browser-verified directly this time (the owner's session was already logged in): desktop screenshot shows 5 cards in one row, mobile-preset (375px) screenshot shows exactly 2 per row, both confirmed via computed styles, not just visually.

Important changed/added files:

- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php` — rewritten (`Widget`, not `StatsOverviewWidget`; public `stats()` returns label/value/icon/color rather than `Stat` objects).
- `resources/views/filament/resources/products/widgets/product-stats-overview.blade.php` — new.
- `tests/Feature/ProductStatsOverviewTest.php` — updated to call the new public `stats()` method directly instead of reflecting into `getStats()`.

Verification:

- `php artisan test tests/Feature/ProductStatsOverviewTest.php`: 1 passed (5 assertions).
- `npm run build`: succeeded.
- Manual browser pass: desktop (5-column) and mobile-preset (2-column) screenshots, `getComputedStyle` checks for `font-size`/`padding` on the live page, no server errors in `preview_logs` after the rebuild.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Products list: 5 stat cards (Total/New/Active/Inactive SKU, Total Variants)

Reason:

- The owner opened Nuport's "Products & Pricing" list page and asked for the same 5 stat cards it shows at the top (Total SKU, Total Variants, New SKU, Active SKU, Inactive SKU) on our Products page, plus a general "explore the rest" review. Plan approved in plan mode. Everything else found on Nuport's products pages (role-based Distributor/Retailer pricing, per-product order-usage table, per-product trend charts, a much more granular multi-warehouse inventory pipeline, an "Internal ID" field) was documented as findings in the plan file but deliberately **not built** — those are real product/scope decisions for the owner to prioritize, not something to invent per `CLAUDE.md`.

What happened:

- Nuport counts every SKU-bearing row: a variant-less product is one row, a variant-bearing product is one "Base Product" row plus one row per variant. Mapped onto our schema with no migration: `Total SKU = Product::count() + ProductVariant::count()`, `Total Variants = ProductVariant::count()`, `New SKU` = both counted with `created_at` in the last 7 days (Nuport's own value was 0, so the exact window wasn't visible — 7 days is a reasonable, easily-adjustable default), `Active/Inactive SKU` = both counted by `is_active`.
- New `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php` (`StatsOverviewWidget`, mirrors the existing `StorefrontRecoveryOverview` pattern), registered via `ListProducts::getHeaderWidgets()`.

Important changed/added files:

- `app/Filament/Resources/Products/Widgets/ProductStatsOverview.php` — new.
- `app/Filament/Resources/Products/Pages/ListProducts.php` — `getHeaderWidgets()`.
- `tests/Feature/ProductStatsOverviewTest.php` — new, asserts all 5 counts against a mix of active/inactive products and variants, backdated vs recent, plus a second company to prove `CompanyScope` isolation.

Verification:

- `php artisan test tests/Feature/ProductStatsOverviewTest.php`: 1 passed (5 assertions).
- Regression: `php artisan test tests/Feature/ProductCsvTest.php tests/Feature/ProductStockFormTest.php tests/Feature/ProductCarouselTest.php tests/Feature/ProductVariantTest.php`: 16 passed (64 assertions), no existing product test file needed updating.
- `php -l` on every new/changed PHP file.
- Manual browser pass on the Products list was not completed — same missing local `ADMIN_PASSWORD` limitation as the order-detail-page work below; please confirm the cards render correctly on your own login.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Order detail page: Payments/Costs ledgers, Profit, Activity feed, Prev/Next nav

Reason:

- The owner opened a competitor ERP's (Nuport) order detail page side by side with ours and asked for a plan to add every metric there that our app doesn't have. After browsing that page and reading our actual `Order`/`OrderItem`/`Customer`/`Product` code, most of it turned out to already exist (customer type, source, weight, variant, courier margin fields) and just needed surfacing; the genuine gaps were an order-level payment ledger, an order-level cost ledger, and a narrated activity feed. Plan approved in plan mode; implemented Phase 1 (ledgers) and Phase 2 (display upgrades, activity feed, prev/next nav) from that plan. Phase 3 items (attachments, internal-notes thread, requested-vs-delivered qty, delivery type) were explicitly left out pending owner confirmation they're real requirements, per `CLAUDE.md`'s rule against inventing business rules.

What happened:

- **Payments History ledger** — new `OrderPayment` model/table (`type`, `method`, `amount`, `note`, `received_by`, `paid_at`). `Order::paid_amount`/`due_amount` are now derived from `SUM(order_payments.amount)` via `Order::recalculatePaidAmount()` (mirrors how `CustomerPayment` already syncs `Customer::current_balance`), recomputed whenever a payment row is added/edited/deleted. Order creation still seeds one `advance` payment row from whatever was typed into the existing one-field "Paid Amount" input (`Order::booted()`'s new `created` hook), so the create flow is unchanged. Managed via a new **Payments History** `RelationManager` on the order view page. The `OrderForm` `paid_amount` field is now read-only once the order exists — corrections go through the ledger instead of typing a new total, so the two write paths can't disagree.
- **Associated Costs ledger** — new `OrderCost` model/table (`cost_head`: purchase/courier_delivery/cod/other, `amount`, `note`), managed via a new **Associated Costs** `RelationManager`, matching Nuport's exact 4 cost heads (confirmed live in the browser).
- **Profit figure** — `Order::profit()` = `total_amount` − per-line COGS (`OrderItem::unit_cost`, already existed) − `Order::totalCost()` (the new ledger). Shown on `OrderInfolist`'s Totals section alongside a new Total Weight figure.
- **Display upgrades** (no schema changes) — `OrderInfolist` now shows the customer's type badge, a click-to-WhatsApp phone link (same `wa.me` normalization `QuotationsTable`'s "Share on WhatsApp" already uses), and each line item's variant label and product weight.
- **Narrated Activity feed** — new `OrderActivityFeedService` turns the existing raw `AuditLog` rows (plus courier `CourierStatusLog` history) into short sentences ("Payment of BDT 500.00 added by Rahim.") on a new Activity section on the order view page. `AuditLogService::record()` already accepted an arbitrary action string, so two new non-CRUD events were added with no schema change: `'viewed'` (logged once per user per 5 minutes from `ViewOrder::mount()`) and `'printed'` (logged from both `orders.print` and `orders.print.bulk` routes).
- **Previous/Next navigation** — two header icon-button `Action`s on `ViewOrder` resolve the id-adjacent order (company-scoped via the existing `CompanyScope`) and link to it, matching Nuport's `< >` arrows.

Important changed/added files:

- `database/migrations/2026_08_14_010000_create_order_payments_table.php`, `2026_08_14_020000_create_order_costs_table.php` — new tables.
- `app/Models/OrderPayment.php`, `app/Models/OrderCost.php` — new models (`BelongsToCompany` + `CompanyScope`).
- `app/Models/Order.php` — `payments()`/`costs()` relations, `recalculatePaidAmount()`, `totalCost()`, `profit()`, the `created` auto-seed hook.
- `app/Filament/Resources/Orders/RelationManagers/PaymentsRelationManager.php`, `CostsRelationManager.php` — new (mirrors the existing `CostItemsRelationManager`/`StatusTransitionsRelationManager` pattern), registered in `OrderResource::getRelations()`.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — `paid_amount` read-only on edit.
- `app/Filament/Resources/Orders/Schemas/OrderInfolist.php` — customer type/WhatsApp, item weight/variant, Total Weight/Associated Costs/Profit, new Activity section.
- `app/Services/OrderActivityFeedService.php` — new.
- `app/Filament/Resources/Orders/Pages/ViewOrder.php` — `mount()` viewed-logging, Previous/Next actions.
- `routes/web.php` — 'printed' logging on both print routes.
- `app/Providers/AppServiceProvider.php` — registers `AuditObserver` for the two new models.
- `tests/Feature/MultiCompanyIsolationTest.php` — adds `OrderPayment::class`, `OrderCost::class`.
- `tests/Feature/OrderLedgersTest.php` — new, 8 tests covering the auto-seed, add/edit/delete recomputation, validation guards, and profit math.

Verification:

- `php artisan test tests/Feature/OrderLedgersTest.php`: 8 passed (22 assertions).
- `php artisan test tests/Feature/MultiCompanyIsolationTest.php tests/Feature/OrderBulkPrintTest.php tests/Feature/OrderFormTest.php tests/Feature/OrderStatusWorkflowTest.php tests/Feature/OrderTrashWorkflowTest.php tests/Feature/SalesOrderTest.php`: all passed, no regressions.
- `php -l` on every new/changed PHP file.
- Applied the two new migrations to the local dev database (`php artisan migrate`, additive only).
- Manual browser pass on the order view page was not completed — the local dev admin login credentials weren't available in this session (no `ADMIN_PASSWORD` in `.env`), so the new Payments History/Associated Costs/Activity/Prev-Next UI hasn't been visually confirmed yet. Backend logic is fully covered by the automated tests above; please give it a look on your own login.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Orders trash workflow and permanent cleanup

Reason:

- The Orders bulk menu exposed **Delete selected** as an immediate deletion. The owner requested **Move to trash**, a Trash button beside Bulk actions, a popup listing trashed orders, row-by-row or select-all restoration, and a way to permanently delete that trash.

Important changed files:

- `database/migrations/2026_08_14_030000_add_soft_deletes_to_orders_table.php` - adds `orders.deleted_at`.
- `app/Models/Order.php` - enables `SoftDeletes`; confirmed permanent deletion cleans restrictive courier-booking children before deleting the order; restored orders rebuild their stock and customer-balance effects through the existing order workflow sync.
- `app/Filament/Resources/Orders/Tables/OrdersTable.php` - relabels/configures the bulk action as **Move to trash** and adds the default Filament **Trash** toolbar action, count badge, embedded trash-table modal, destructive confirmation, notifications, and **Delete permanently** behavior. The action is hidden in All Companies mode and every mutation query fails closed without one active company.
- `app/Livewire/OrderTrashTable.php` and `resources/views/livewire/order-trash-table.blade.php` - render the popup list through Filament's native Table UI with per-row checkboxes, header Select all, search/sort/pagination, row-level **Restore**, and confirmed **Restore selected** bulk action. Both single and bulk restore rebuild stock/customer-balance effects, report success/failure, and remain explicitly company-scoped.
- `app/Filament/Resources/Orders/OrderResource.php` - applies the existing Super Admin sensitive-delete permission to force deletion.
- `tests/Feature/OrderTrashWorkflowTest.php` - covers soft deletion, active-list hiding, toolbar/modal configuration, company isolation, row-specific restoration, checkbox-driven single/multi selection restoration, restored stock effects, courier-booking cleanup, permanent deletion, permission visibility, and All Companies fail-closed behavior.
- `PROJECT_GUIDE.md` - documents behavior, permissions, isolation, cleanup, and verification.

Verification:

- `php artisan test tests/Feature/OrderTrashWorkflowTest.php`: 6 passed (70 assertions).
- Restore regression suite: `OrderBulkPrintTest`, `SalesOrderTest`, and `OrderStatusWorkflowTest`: 13 passed (59 assertions). The earlier courier regression remains 27 passed (126 assertions).
- Scoped Laravel Pint check passes for all changed PHP files.

Commit status:

- Not committed. Commit and push require explicit owner approval.

## 2026-08-14 - Courier filter uses Filament default Select UI

Reason:

- The Courier Merchant Dashboard's provider filter rendered as a browser-native dropdown and did not match Filament's admin UI. The owner requested Filament's default dropdown design.

Important changed files:

- `app/Filament/Pages/CourierMerchantDashboard.php` - added a live, non-native Filament `Select` schema bound to `providerFilter`, constrained with Filament's `Width::ExtraSmall`, and visible only when more than one provider is active.
- `resources/views/filament/pages/courier-merchant-dashboard.blade.php` - removed the raw select/input-wrapper markup and renders the page's Filament form schema only for multiple active providers.
- `tests/Feature/CourierIntegrationTest.php` - verifies the Select is non-native, live, visibly labelled, compact-width, contains the aggregate plus provider options, preserves filtering, and becomes hidden after only one provider remains active.
- `CHANGELOG.md` and `PROJECT_GUIDE.md` - document the Filament-only UI pattern.

Verification:

- `php artisan test --compact tests/Feature/CourierIntegrationTest.php`: 27 passed (126 assertions), including compact width, multiple-active visibility, single-active hiding, and live filtering.
- Changed PHP files pass `php -l`; scoped `git diff --check` passes.
- In-app browser visual verification could not start in the current sandbox session; no alternate browser tool was used. Automated rendering/schema verification remains green.

Commit status:

- Not committed. Commit and push require explicit owner approval.

## 2026-08-14 - Fix print/print-to-PDF right-edge clipping + modern invoice font

Reason:

- Owner tested printing invoices (including the new bulk print feature) and attached a real "Print to PDF" output showing every invoice's right edge cut off — not usable. Also asked for the invoice font to be a lighter, more modern typeface (design/layout otherwise fine).

What happened:

- Root cause: `orders/partials/invoice-styles.blade.php` set both a CSS `@page { margin: 10mm 12mm; }` **and** built the same 10mm/12mm margin into `.invoice`'s own padding for screen display, with a `@media print` override that swapped `.invoice` to `width: 100%; padding: 0` to rely solely on the `@page` margin while printing. Print destinations (a real printer, "Microsoft Print to PDF", Chrome's own "Save as PDF") don't all honor a CSS `@page` margin consistently — some ignore it, some add their own default margin on top — so depending on the destination the rendered content could end up wider than the actual printable area, clipping the right edge. This matches exactly what the attached PDF showed.
- Fix: `@page { margin: 0; size: A4; }` — no external page margin for any print destination to disagree about. `.invoice` no longer gets a print-only `width`/`padding` override, so it keeps its normal 210mm-wide box with the 10mm/12mm margin baked into its own padding in *every* context (screen and print alike) — what's on screen is now exactly what prints, regardless of print destination. `min-height` in print goes back to the full A4 height since there's no external margin to subtract anymore.
- Font: `body`'s `font-family` changed from `Arial, Helvetica, sans-serif` to `'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Helvetica, Arial, sans-serif` — a lighter, more modern look on whichever OS is doing the printing, still falling back to the original Arial/Helvetica if neither is available. No layout/weight/size changes, per the owner's "design is fine" note.
- Both fixes live in the shared `orders/partials/invoice-styles.blade.php` partial (from the same-day bulk-print refactor), so they automatically apply to both the single-order print (`orders.print`) and the new bulk print (`orders.print.bulk`) — no duplicate edit needed.

Verification:

- `php artisan test --filter=InvoiceDesignTest`: 8 passed, including the two tests that assert print/mobile CSS specifics (`mobile_layout_does_not_override_the_a4_print_layout`, `print_typography_matches_the_compact_reference_scale`) — confirms the CSS restructuring didn't change any asserted value.
- `php artisan test --filter=OrderBulkPrintTest`: 4 passed — confirms the bulk print route/action still work after the shared-partial edit.
- Parsed the live page's CSSOM in the browser to confirm the compiled rules are exactly as intended: `@page { margin: 0px; size: a4; }` and `.invoice { border: 0px; box-shadow: none; margin: 0px; min-height: var(--page-height); }` (no `width`/`padding` override left in the print block) — and confirmed the computed `body` font-family resolves to the new stack.
- Actually triggering a real print/print-to-PDF from this sandboxed browser tool blocks on the native OS print dialog (confirmed indirectly — the tooling hung exactly as expected when `window.print()` fired for the bulk print route, proving the auto-print trigger itself still works). The owner's original bug report was itself a real "Print to PDF" output, so the fix should be confirmed by the owner re-printing on their own machine.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Orders list: bulk "Print invoices" (select orders, print all invoices in one click)

Reason:

- Owner asked for one more Orders list bulk-action metric: select multiple orders and print all their invoices in one click, with the print preview shown in the PC/mobile's default print app (i.e. the browser/OS print dialog, same as the existing single-order print).

What happened:

- Refactored the single-order print view (`resources/views/orders/print.blade.php`) so its CSS and per-invoice markup live in two shared partials — `orders/partials/invoice-styles.blade.php` and `orders/partials/invoice.blade.php` (wrapped in a `.invoice-page` div so a new `.invoice-page + .invoice-page { page-break-before: always; }` rule can force a page break between orders on the bulk page, harmless as a no-op on the single-order page). No visual/behavioral change to the existing single-order print — re-verified against `InvoiceDesignTest`, `CompanySettingsTest`, and `PhaseThreeAdminPagesTest` (all still passing) plus a live browser check.
- New route `orders.print.bulk` (`GET /admin/orders/print-bulk?orders=1,2,3`, `web.php`) and new view `orders/print-bulk.blade.php`: resolves the comma-separated order IDs (via `Order::query()`, so `CompanyScope` silently drops any ID outside the current company rather than leaking or erroring), renders every resolved order's invoice through the shared partial, and always auto-triggers `window.print()` on load (bulk print is only ever reached by explicitly clicking the button, so no `?print=1` opt-in like the single-order view). 404s if nothing valid remains after company-scoping.
- New `OrdersTable` bulk action **Print invoices**. First attempt used `->url()` + `->openUrlInNewTab()` (mirroring the row-level "print" action) — caught live in the browser that this was broken: a bulk action's trigger link is rendered once and does not re-evaluate as checkboxes are (de)selected client-side, so the pre-rendered `href` kept pointing at whatever selection existed at the table's last full render (usually `?orders=` empty, i.e. a 404). Fixed by switching to `->action()` (same mechanism `bookCourierBulk` already uses, so `$records` always reflects the true live selection) plus `$livewire->js('window.open(...)')` to open the new tab from the server response. No `->schema()`/`->requiresConfirmation()`, so it's still one click.

Verification:

- New `tests/Feature/OrderBulkPrintTest.php`, 4 tests: bulk route renders every selected order's invoice and includes the auto-print trigger; only includes orders belonging to the current company (two-company isolation check); 404s on no valid order IDs; the Filament bulk action exists/has the right label+icon and calls `OrdersTable::printInvoicesBulk()` with exactly the selected orders (verified directly via reflection + a `$livewire` test double capturing the `js()` call, since asserting on a Livewire "js" effect isn't part of the public testing API).
- `php artisan test --filter=OrderBulkPrintTest`: 4 passed. Re-ran `InvoiceDesignTest` (8 passed), `CompanySettingsTest` (23 passed), `PhaseThreeAdminPagesTest` (1 passed), and `CourierIntegrationTest` (27 passed) to confirm the shared-partial refactor and the same `OrdersTable.php` file didn't regress anything already covered.
- Manually verified live on the local dev server: selected 2 orders, opened Bulk actions → Print invoices — confirmed (via a `window.open` interception) it opened `orders/print-bulk?orders=1,2` reflecting exactly the current selection; navigated there directly and confirmed the tab title read "2 Invoices" and the native print dialog opened automatically (the browser automation tooling itself blocked on it, which is the expected proof it fired). Re-confirmed the single-order print view (`/admin/orders/1/print`) still renders correctly after the partial refactor.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Unified "Book courier" popup (row + bulk), courier pre-selected and changeable

Reason:

- Immediate follow-up to the default-courier/bulk-booking work: owner asked that both the row-level and bulk "Book courier" actions open a popup showing the default courier pre-selected, changeable before confirming, and clarified that the Orders list no longer needs separate per-courier buttons (Book Steadfast/Pathao/RedX/E-Courier) — just one "Book courier" button that does all of it.

What happened:

- **Row-level**: the five separate `Action::make()` buttons in `OrdersTable` (bookCourier/bookSteadfast/bookPathao/bookRedx/bookECourier) are now one `bookCourier` action. Its popup schema (`unifiedBookingForm()`) has a single `Select::make('courier_provider_id')` (`->live()`, defaulting to the order's own assigned courier or the company default) whose selection reactively shows/hides each driver's extra fields via `Get`-based `->visible()` closures — verified live in the browser that switching the select from Steadfast to Pathao correctly swapped "Alternative Phone"/"Recipient Email" out for "Store ID"/"City ID"/"Zone ID"/"Area ID".
- Found and fixed a real field-name collision while merging the four driver-specific forms into one schema: `recipient_city`/`recipient_area`/`delivery_type` are used by more than one driver with different meanings (e.g. Pathao's `recipient_city` is a numeric ID, E-Courier's is a plain city name; Steadfast's `delivery_type` is home/point delivery, Pathao's is normal/on-demand) — two Filament form components can't safely share one state path in the same schema. Fixed by prefixing every driver-specific field (`sf_`/`ph_`/`rx_`/`ec_`) and adding `OrdersTable::bookingPayloadFor(CourierProvider, array $data)` to strip the prefix back off into the flat shape `CourierService::create*Booking()` already expects — those service methods themselves needed no changes.
- **Bulk**: redesigned from "each order books through its own assigned provider" (the previous entry's design) to "one courier chosen in the popup, applied to every selected order" — a single `Select::make('courier_provider_id')`, pre-filled from `CourierProvider::defaultForCompany()`, changeable before confirming. Its options are restricted to `BULK_SAFE_DRIVERS` (Steadfast, Manual only) since Pathao/RedX/E-Courier need per-order fields that can't be shared across a batch of different recipients — rather than list them and then silently skip everything (confusing), they're simply not offered as bulk options at all; the row-level action is where those belong.
- Rewrote both affected automated tests to match the new semantics (the old bulk test asserted "each order's own assigned provider" behavior, which no longer applies) and added a new test for the unified row action switching drivers.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 27 passed — includes `orders_list_bulk_book_courier_uses_the_one_courier_chosen_in_the_popup_for_the_whole_batch` (asserts the popup pre-selects the default and applies it to every selected order) and `orders_list_book_courier_action_is_unified_and_books_through_the_selected_driver` (asserts the old per-driver actions no longer exist, the popup defaults to the company's default courier, and switching to a different courier before submitting books through that one instead).
- Manually verified live on the local dev server: Orders list rows now show a single "Book courier" button (confirmed via page text — no more Book Steadfast/Pathao/RedX/E-Courier); its popup pre-selects "Demo Steadfast (sandbox) (Steadfast) — Default" with the Steadfast-specific fields showing, and switching the select to "Pathao (Pathao)" correctly swapped in Pathao's fields (Store ID/City ID/Zone ID/Area ID) live; the bulk "Book courier" popup (selecting all 10 visible demo orders) showed the same default-preselected courier select with the expected helper text about Pathao/RedX/E-Courier not being offered — cancelled rather than confirming, to avoid mutating demo order data during a UI check (the actual booking behavior is already covered end-to-end by the automated tests using `Http::fake()`).

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Default courier + Orders list bulk booking

Reason:

- Follow-up to the multi-courier dashboard: owner asked for a way to mark one courier as the default, have website/storefront orders automatically use it (still changeable per order before booking), and be able to select multiple orders from the list and book them all with one click.

What happened:

- Migration `2026_08_14_000000_add_default_courier_and_order_courier_preference`: `courier_providers.is_default` (boolean) + `orders.courier_provider_id` (nullable FK, `nullOnDelete`).
- `CourierProvider`: `is_default` fillable/cast; a `saved` model event enforces at most one default per company (setting one unsets all others in the same company); new `CourierProvider::defaultForCompany(?int $companyId)` resolves the default, falling back to the company's sole active provider if none is explicitly flagged (so a single-courier company doesn't need to remember to flip a toggle).
- `Order`: new `courier_provider_id` fillable + `courierProvider()` relation; the existing `creating` model event (already defaulting `order_number`/`status`/`shipping_zone` etc.) now also pre-fills `courier_provider_id` from `CourierProvider::defaultForCompany()` whenever it isn't explicitly set. Confirmed this one hook covers **both** origins the owner asked about — storefront checkout (`CheckoutController`) and admin-created orders both call `Order::query()->create()`, so neither needed its own separate wiring.
- `CourierProviderResource`: new "Set as default courier" toggle (with explanatory helper text) next to "Active", plus a "Default" column on the list.
- `OrderForm`: new native Filament `Select::make('courier_provider_id')` ("Courier") next to Delivery Status — pre-filled by the model event on new orders, left enabled (unlike the Order/Delivery Status selects beside it) so it stays editable right up until the order is actually booked, per the owner's explicit ask.
- `OrdersTable`: new **Book courier** bulk action. Books every selected, not-yet-booked order through its own assigned courier (or the company's default). Steadfast/Manual bookings need no extra input beyond what the existing `steadfastPayload()`/`createManualBooking()` already derive from the Order/Customer, so those go straight through; Pathao/RedX/E-Courier require structured per-order fields (city/zone/area IDs, delivery area names, ...) that don't exist on the Order model — rather than invent plausible-looking values for a batch of different recipients (which CLAUDE.md's "never invent placeholder business rules" rules out, and which could genuinely mis-route a real parcel), those are counted and skipped with a clear reason in the completion notification, alongside already-booked/no-courier/failed counts.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 26 passed — 2 new tests (`default_courier_is_enforced_to_one_per_company_and_pre_fills_new_orders`, `orders_list_bulk_book_courier_books_through_each_orders_assigned_provider_and_skips_the_rest`) plus the 24 from the earlier multi-courier dashboard work, unaffected.
- Manually verified live on the local dev server: toggled "Set as default courier" on the Steadfast sandbox provider and saved — Providers list "Default" column flipped to Yes; opened an existing order and confirmed the new "Courier" select is enabled (unlike the disabled Order/Delivery Status selects) and offers both configured providers, with the default one labelled "(Default)"; selected multiple demo orders on the Orders list and confirmed the "Book courier" bulk action appears with the expected confirmation copy — cancelled rather than confirming, to avoid mutating demo order data during a UI check (the actual booking logic is already covered end-to-end by the automated test using `Http::fake()`).

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Multi-courier Merchant Dashboard: courier selector + All Couriers aggregation

Reason:

- Owner added a second courier provider (Pathao, alongside the existing Steadfast sandbox) and asked two things: how to tell which courier a booking went through once there's more than one, and for the dashboard to show combined totals across all couriers by default while still being able to drill into one specific courier's data.

What happened:

- `CourierMerchantDashboard` was Steadfast-only everywhere (`activeProvider()` hardcoded `DRIVER_STEADFAST`, every query filtered by that driver). Reworked to be provider-agnostic: a new "Courier" `<select>` (`providerFilter`, `#[Url(as: 'courier')]`, bookmarkable) drives every section — Balance, Delivery Performance, Booking Status Summary, Recent Consignments, Recent Returns, and the Manage quick-link counts. Default is **All Couriers** (every section aggregates across every provider the company has); picking one scopes everything to just that provider.
- `providerFilter` is a **string** sentinel (`'all'` or the provider id as text), not `?int` — confirmed a `?int` property throws a TypeError when Livewire assigns the "All Couriers" option's value to it, since only numeric strings coerce to `int` on a typed property assignment; this mirrors the Inbox page's existing `$assignedFilter` string-sentinel pattern. `selectedProviderId()` is the one place that turns it into a real `?int` for every query.
- Recent Consignments and the Booking Status Summary modal both gained a **Courier** column/badge per row, directly answering "কিভাবে বুঝব কোন কুরিয়ারে বুকিং হয়েছে" (how do I know which courier a booking went through).
- Balance: `AbstractCourierAdapter::balance()` returns `null` for couriers with no balance endpoint wired up (everything except Steadfast today) — All Couriers mode silently skips those; explicitly selecting one still says "This courier doesn't expose a balance check" rather than showing nothing.
- The nested **Manage** quick-links widget (`CourierQuickLinksWidget`) is provider-scoped too — it now accepts `providerId` via `@livewire(Widget::class, ['providerId' => ...], key('courier-quick-links-'.($id ?? 'all')))`. The `key()` changing when the selection changes is required: confirmed a nested `@livewire()` component is its own independent Livewire instance that does **not** re-`mount()` just because the parent page re-renders with a different array value — only a changed key forces a fresh instance.
- **Found live, not by a test**: `CourierReportService::providerPerformance()`'s rows are raw `stdClass` from a manual `->select()`, not Eloquent models. Storing that in a public Livewire property (`public Collection $performance`) rendered correctly on first load but silently reset to an empty collection after any subsequent Livewire round-trip — caught by clicking a Booking Status Summary card, closing the modal, and noticing "Delivery Performance" had gone from real numbers to "No bookings yet." with nothing else on the page having changed. A fresh full page load rendered it correctly again, confirming it was specifically a wire:snapshot hydration issue with plain `stdClass` objects, not a query bug. Fixed by not storing `performance`/`balances`/`recentReturns` as properties at all — they're now plain methods recomputed fresh on every render, the same pattern `statusCounts()`/`providers()` already used. Re-verified live after the fix: open → close the same modal, "Delivery Performance" now keeps its real numbers.
- Verified the sandbox `create_order` → status-sync → dashboard flow end-to-end for the first time this session (earlier verification had only covered `get_balance`/`payments()`/`payment()`): created one clearly-labeled test order (`MAIN-20260814-0001`, customer "ZamZam Sandbox Verify") and booked it through the real Steadfast sandbox — got back a real tracking code (`SFR260814ST2EB54DBBD`) and consignment id, synced its status successfully, and confirmed it appeared live on the dashboard (Booking Status Summary, margin, Manage counts). Whether to keep it or clean it up is still an open question put to the owner — it currently remains in the local dev database.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 24 passed (3 new tests — All Couriers aggregation + per-provider scoping via `statusCounts()`, the Booking Status Summary modal showing both providers' names, and the balance section correctly marking Pathao as unsupported).
- Manually verified live on the local dev server with two real providers (Steadfast sandbox + Pathao): default load aggregates both; selecting Pathao shows all-zero counts and "This courier doesn't expose a balance check"; selecting Steadfast shows its real sandbox booking; the Manage section's counts changed correctly on each selection (proving the `key()` remount fix works); the modal-hydration bug is confirmed fixed by the same open/close/reload sequence that first exposed it.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Courier dashboard card redesign: native Filament styling, 5/2 grid, color tints

Reason:

- Owner asked (screenshot of steadfast.com.bd's own consignment list) whether all of Steadfast's data/dashboard could be controlled from ZamZam directly. Confirmed Steadfast's public API has no "list all consignments" endpoint (checked three independent sources), so any list our app shows can only ever be consignments booked *through* ZamZam itself. Owner chose a process rule over new code: book everything from ZamZam ERP going forward, not steadfast.com.bd directly. Documented in `PROJECT_GUIDE.md` and saved as a memory.
- Owner then flagged that the Booking Status Summary cards "lost Filament's default design" (compared to the reference Manage section, which already used a native `Stat` widget) and asked for: description text removed from under each count, a 5-cards-per-row desktop / 2-per-row mobile layout matching a reference screenshot, the same treatment + matching font sizes applied to Manage, and — in follow-up messages mid-turn — the Manage cards' descriptions removed too, and finally a distinct semi-transparent color per card in both sections.

What happened:

- Root cause of the "lost design": the hand-built Booking Status Summary cards used approximated Tailwind utilities (`text-2xl`, `p-4`, `border`) instead of Filament's actual compiled card styles (`text-3xl` value, `p-6`, `ring-1`/`shadow-sm`), so they visually drifted from the Manage section's native `StatsOverviewWidget` cards.
- Fixed by having the hand-built `<button wire:click="mountAction(...)">` cards reuse Filament's own compiled `.fi-wi-stats-overview-stat*` class names directly (shipped in `vendor/filament/filament/dist/theme.css`, loaded on every panel page regardless of which PHP component emits the class) instead of approximating them — now pixel-identical to native `Stat` cards. A real `Stat`/`StatsOverviewWidget` couldn't be used for these cards because `Stat::make()->url()` only supports plain navigation, not a `wire:click` modal action (established earlier this session).
- Removed the description text/badge from every Booking Status Summary card, and (per the owner's follow-up message) every Manage card too — `CourierQuickLinksWidget::getStats()` no longer calls `->description(...)`. The Webhook Logs failed-webhook alert signal now lives entirely in the card switching to `danger` color rather than in explanatory text.
- Both grids now use `grid-cols-2 lg:grid-cols-5` (2 per row on mobile, 5 on desktop, matching the owner's reference screenshot). `CourierQuickLinksWidget::getColumns()` overrides the base `StatsOverviewWidget`'s auto-computed column count (which picks 3 or 4 based on stat count) to force the same breakpoints as the Booking Status Summary grid, so both sections read as one consistent layout with matching font sizes automatically (both now literally share the same `fi-wi-stats-overview-stat-value`/`-label` CSS classes).
- Added `CourierMerchantDashboard::statCardTint(string $color): array` — maps a Filament color name (`primary`/`info`/`success`/`warning`/`danger`/`gray`, the only names Filament registers by default) to `['!bg-{color}-50 dark:!bg-{color}-400/10', 'hover:!bg-{color}-100 dark:hover:!bg-{color}-400/20']`. The `!` (Tailwind important) prefix is required because Filament's `.fi-wi-stats-overview-stat` class already bakes in `bg-white`/`dark:bg-gray-900` at the same utility specificity — an unmarked override isn't guaranteed to win the cascade. Applied to the Booking Status Summary buttons directly in Blade, and to Manage's native `Stat` cards via `->extraAttributes(['class' => ...])` (`Stat` has `HasExtraAttributes` in its inheritance chain, which the vendor `stat.blade.php` merges onto the root element). Changed `in_progress`'s color from `warning` to `info` and Manage's "Status Logs" from `gray` to `info` so no two adjacent cards in either grid share an identical tint by default.
- Verified live that `primary`'s tint correctly tracks the active company's own dynamic brand color (`DynamicColorService` sets `--primary-*` via an inline `<style>` per request) rather than a build-time-baked default — confirmed by inspecting the computed CSS custom properties in the browser, which is why "Main Company"'s primary-tinted cards render green (its configured dashboard color) rather than the fallback amber.
- Required an `npm run build` after each Blade/PHP change — the new Tailwind utilities (`lg:grid-cols-5`, `!bg-primary-50`, etc.) don't exist in the already-compiled `public/build/assets/theme-*.css` until Tailwind's `@source`-scanning rebuild picks up the new literal class-name strings.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 21 passed, twice (once after the layout/description changes, again after the color-tint changes) — no test asserted the removed description text, so nothing needed updating.
- Manually verified live on the local dev server (`localhost:8931`) at desktop (1280px) and mobile (375px) viewports, and in dark mode: both grids show 5/2 columns correctly, no description text remains, every card has a distinct tint with legible text, and clicking a Booking Status Summary card still opens the correct filtered modal (regression-checked, since the button's classes were rewritten).
- Full `php artisan test` (plain, no `--env`): 625 passed, 5 failed — the same 5 pre-existing, unrelated failures as every prior run this week (`ReleaseNotesTest` ×3, `StorefrontCustomerAdvanceAndComplaintTest`/`StorefrontIncompleteCheckoutRecoveryTest` ×1 each). No regressions from this round's card-design changes.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Clickable Booking Status Summary cards + Payment Details redesign

Reason:

- Owner asked for the Booking Status Summary cards to be clickable, opening a popup with that status's own bookings, and separately asked (via the `/web-design-guidelines` skill) for the Payment Details popup — which had been plain flattened "key: value" text — to be redesigned using Filament's own default UI components.

What happened:

- **Payment Details modal redesign**: `CourierPaymentHistory::paymentDetailEntries()` rewritten from one flat list of `TextEntry` rows into structured `Section`s — "Settlement Summary" (money-formatted amounts, a colored status badge, columns(4) grid), "Timeline" (formatted dates), "Consignments" (a `RepeatableEntry` — tracking code, invoice, status badge, COD amount, recipient, phone, address — one mini-card per consignment instead of a comma-joined string), and a collapsed "Additional Details" fallback for any field outside the confirmed shape. Found and fixed a real bug in the process: the summary/timeline entries weren't given an explicit `->state()`, so Filament tried to resolve them from the mounted table row instead of the fetched payment detail — they rendered as blank rather than 40,541.00 until fixed.
- **Booking Status Summary cards made clickable**: first attempt linked each card (`Stat::make()->url(...)`) to `CourierBookingResource` with `?tableFilters[status][values][]=...` — cross-checked against Filament's `SelectFilter` source for the correct `value`/`values` query-string keys, but confirmed live in the browser (real click-through, then a manually-crafted URL to rule out a click-timing issue) that this Filament install doesn't apply table filters from a cold GET request at all — the filter badge stayed at 0 and every row still showed. Abandoned that approach rather than ship something that only looks like it works. Rebuilt as a page-level modal instead: `CourierMerchantDashboard::bookingStatusAction()` + `bookingStatusEntries()`, using the same `Section`/`RepeatableEntry` technique as the Payment Details redesign, latest 50 bookings with a "showing X of Y" note when more exist. `Filament\Pages\Page` already implements `HasActions`/`InteractsWithActions` (via `BasePage`), so no extra scaffolding was needed on the page. The now-unused `App\Filament\Pages\CourierWidgets\CourierBookingStatusWidget` (`StatsOverviewWidget`, which has no click/modal support) was deleted; the cards are now hand-built in the page's own Blade view using `<x-filament::icon>`/`<x-filament::badge>` (which resolve dynamic `:color` correctly, unlike raw interpolated Tailwind classes) wrapped in a semantic `<button wire:click="mountAction(...)">`.
- Along the way, `CourierBookingResource`'s table gained a multi-select **Status** filter and a new **Provider** filter — independently useful even though the dashboard cards no longer rely on URL deep-linking.
- Per the owner's earlier explicit request, moved the "Manage" quick-links section to sit directly after "Booking Status Summary" (was previously after "Recent Returns"/the consignments table).

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 21 passed — includes a regression test asserting money-formatted values actually render in the Payment Details modal (non-breaking-space-aware, since Filament's `->money()` inserts `\u{00A0}` between currency code and amount, not a regular space — confirmed byte-for-byte via `xxd` after a same-looking assertion failed), and a test proving the booking-status modal actually filters (`mountAction('bookingStatus', ['key' => 'delivered'])` shows only delivered bookings) as opposed to the abandoned deep-link approach.
- Manually verified live on the local dev server: created 2 real local test bookings (delivered + cancelled) to click through — Booking Status Summary reflects live counts, clicking "Delivered" opens a modal with only that one booking; Payment Details modal shows the redesigned Settlement Summary/Timeline/Consignments layout against the real settlement data fetched earlier. Also caught and fixed a duplicate "Consignments" heading (`RepeatableEntry::label('')` doesn't hide the label the way `->hiddenLabel()` does) by re-checking the live render after the first pass.
- Full `php artisan test` (plain, no `--env`): 625 passed, 5 failed — the same 5 pre-existing, unrelated failures as every prior run this week (`ReleaseNotesTest` ×3, `StorefrontCustomerAdvanceAndComplaintTest`/`StorefrontIncompleteCheckoutRecoveryTest` ×1 each). No regressions from this round's changes.
- Cleaned up the 2 local debug bookings created for manual verification (`DEBUG-DELIVERED`/`DEBUG-CANCELLED`) — local dashboard is back to its pre-verification state.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-14 - Steadfast Payments fix verified live on the local dev server

Reason:

- Owner asked whether connecting real Steadfast API credentials to the local dev server would exercise the corrected endpoint paths from the previous entry (it does — same working tree, no deploy needed). Owner logged into the local admin panel and added the real API Key/Secret Key to the local "Demo Steadfast (sandbox)" provider.

What happened:

- Checked the local Courier Merchant Dashboard: balance loaded (`get_balance`), but Payments still showed no data at first — traced this to the provider's **Active** toggle being off (not a credentials or endpoint problem). Owner enabled it.
- Re-checked: Steadfast Balance now shows a real live value; the Payments page returned **10 real settlement rows** (`payment_id` like `SFC-20293988`, amount, method, due/paid bills, charges, total, status, timestamps) — confirms the `/payments` path fix from 2026-08-13 is correct.
- Opened the "View" drill-down on a row: `SteadfastCourierClient::payment()` (`/payments/{payment_id}`) returned real consignment data (consignment_id, invoice, tracking_code, recipient name/phone/address, cod_amount, status) — confirms the new drill-down feature works end-to-end.
- Found the real response shape wraps the settlement under a top-level `payment` key (not `data`); updated `CourierPaymentHistory::paymentDetailEntries()` to prefer it, and made the value formatter recursive so nested arrays (like `consignments[]`) render as readable "key: value" lines instead of raw JSON.
- Updated docblocks on `SteadfastCourierClient` and `CourierPaymentHistory`, plus `PROJECT_GUIDE.md` and `CHANGELOG.md`, with the now-confirmed field names.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 20 passed.
- Manually verified end-to-end on the local dev server (`localhost:8931`) with the owner's real Steadfast credentials — both the Payments list and the drill-down modal render real live data correctly.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-13 - Distinct Courier submenu icons

Reason:

- The Courier Dashboard and Providers submenu items both rendered the same truck icon, making them difficult to distinguish at a glance.

Important changed files:

- `app/Filament/Pages/CourierMerchantDashboard.php` - Dashboard now uses Filament's `OutlinedRectangleGroup` icon.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` - Providers retains Filament's courier-appropriate `OutlinedTruck` icon while Dashboard changes to a distinct panel icon.
- `tests/Feature/CourierIntegrationTest.php` - verifies both semantic icons and ensures they remain distinct.
- `CHANGELOG.md` and `PROJECT_GUIDE.md` - document the navigation convention.

Verification:

- `php artisan test --compact tests/Feature/CourierIntegrationTest.php`: 20 passed (85 assertions), including 3 assertions for the distinct icon mapping.
- Changed PHP files pass `php -l`; scoped `git diff --check` passes.
- `pint --test` reported pre-existing line-ending/strict-type/import-order findings in overlapping files; no automatic formatting was applied so the separate in-progress courier work remains untouched.

Commit status:

- Not committed. Commit and push require explicit owner approval.

## 2026-08-13 - Steadfast endpoint fix (live-verified) + Booking Status Summary + Payments drill-down

Reason:

- Owner recorded a screen-capture video ("steadfast courier merchant dashboard journey") of the Steadfast mobile app's merchant UX for reference, and separately added real Steadfast API credentials to the staging Courier Provider for the first time. Reviewed the video frame-by-frame (ffmpeg scene/interval extraction, since there's no native video-reading tool) and found 3 UX ideas ZamZam didn't have yet; owner selected all 3 to build now.

What happened:

- **Live bug found and fixed**: with real credentials now live on staging, checked the Courier Merchant Dashboard and Payments page in the browser. Balance loaded correctly (`get_balance` is genuinely correct), but Payments returned an HTTP 404 — the guessed `/payment` path was wrong. Cross-checked against two independent open-source Steadfast API wrapper packages (`nayemuf/steadfast-courier`, `sabitahmadumid/laravel-steadfast`, both fetched fresh from GitHub) which agree on the real paths. Fixed in `SteadfastCourierClient`: `payments()` → `/payments` (was `/payment`), `returnStatus()` → `/get_return_request/{id}` (was `/return/{id}`); added `returnRequests()` (`GET /get_return_requests`) and `payment()` (`GET /payments/{id}`, single settlement with its consignments).
- **Booking Status Summary** (new `App\Filament\Pages\CourierWidgets\CourierBookingStatusWidget`, native `StatsOverviewWidget`): a stat-card breakdown on the Courier Merchant Dashboard modelled on Steadfast's own "Parcel Summary" screen — All / In Progress / Delivered / Partial Delivered / Returned / Cancelled (+rate %) / Failed, counted from ZamZam's own local `CourierBooking` rows (Steadfast's app-only "In Review" / "Waiting Approval" / "Amount Changed" states aren't exposed by the public API, so those are deliberately left out rather than faked).
- **Payments drill-down**: each row on the Payments page now has a "View" action that fetches `SteadfastCourierClient::payment()` (10-minute cache) and renders the settlement's fields generically (unknown response shape) via Filament `TextEntry` schema in a read-only modal — mirrors the Steadfast app's own "Payment Details" screen. The exact key identifying a payment in the list response isn't confirmed, so several common candidates are tried (`id`, `payment_id`, `invoice_id`, `invoice`, `reference_id`, `sf_id`); the action stays hidden if none resolve.
- **Explicitly NOT built**: "Bill Payment" (pay a due balance to Steadfast) and "Tickets" (merchant support tickets), both seen in the video's app UI. Confirmed via the official Steadfast API PDF summary and both open-source wrapper packages that neither is part of the public REST API — building against a guessed endpoint here risked sending malformed requests, so this was skipped and documented as a known gap in `PROJECT_GUIDE.md` instead of faked.
- `PROJECT_GUIDE.md`, `CHANGELOG.md` updated with the corrected endpoint paths and both new features.

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 19 passed (added endpoint-path regression test, Booking Status Summary count test, Payments drill-down Livewire test using `mountTableAction` + `assertMountedActionModalSee`).
- `php artisan test --filter=MultiCompanyIsolationTest`: 7 passed.
- Manually verified live on staging (`staging-app.zamzamint.com`) with the owner's real Steadfast credentials: balance loads correctly; the `/payment` 404 was caught this way before the fix (not yet re-verified live after the fix — needs a deploy to staging first).
- Full `php artisan test` run pending as of this note (courier + isolation suites both green in isolation).

Commit status:

- Not committed yet — awaiting owner's approval. Live re-verification of the payments fix on staging requires a deploy.

## 2026-08-13 - Remove duplicate Courier Delivery Cost form section

Reason:

- The owner confirmed that "Courier Delivery Cost" duplicates "Set Delivery Fees" and requested that only "Set Delivery Fees" remain on Courier Provider create/edit forms.

Important changed files:

- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` - removed the duplicate Filament section and its Inside/Outside/Suburb inputs.
- `tests/Feature/CourierIntegrationTest.php` - verifies the create page shows "Set Delivery Fees" and does not show "Courier Delivery Cost".
- `CHANGELOG.md` and `PROJECT_GUIDE.md` - document the single-section form behavior and preservation of existing booking economics data.

Data compatibility:

- No database columns or saved booking records are deleted. Previously stored `settings.delivery_costs` values remain readable for historical margin reporting, but the duplicate fields are no longer exposed for new edits.

Verification:

- `php artisan test --compact tests/Feature/CourierIntegrationTest.php`: 16 passed (71 assertions), including the provider-form visibility regression check.
- Changed PHP files pass `php -l`; scoped `git diff --check` passes.

Commit status:

- Approved by the owner for commit and push to `staging` on 2026-08-13; included in this change's commit.

## 2026-08-13 - MySQL courier credential storage hotfix

Reason:

- Staging rejected Steadfast provider creation with MySQL error 3140 because `courier_providers.credentials` was declared as JSON while Laravel's `encrypted:array` cast writes opaque Base64 ciphertext.

Important changed files:

- `database/migrations/2026_06_22_003000_create_courier_tables_and_delivery_status.php` - fresh installations now create `credentials` as nullable text.
- `database/migrations/2026_08_13_000000_change_courier_provider_credentials_to_text.php` - existing installations convert the JSON column to text without deleting values; rollback intentionally does not restore the incompatible JSON type.
- `tests/Feature/CourierIntegrationTest.php` - verifies text column metadata, encrypted-at-rest storage, and decrypted model round-trip.
- `CHANGELOG.md` and `PROJECT_GUIDE.md` - document the production schema requirement and deployment migration.

Security note:

- The submitted exception page contained provider credentials in its captured Livewire request body. Rotate the exposed Steadfast API key/secret and fraud-check password before re-entering them after deployment.

Verification:

- `php artisan test --compact tests/Feature/CourierIntegrationTest.php`: 16 passed (71 assertions), including text-column metadata, ciphertext-at-rest, and decrypted credential round-trip.
- Corrective migration and courier test files pass `php -l`; scoped `git diff --check` passes.
- `php artisan migrate --pretend --path=database/migrations/2026_08_13_000000_change_courier_provider_credentials_to_text.php` completes without error in the local environment.

Commit status:

- Approved by the owner for commit and push to `staging` on 2026-08-13; included in this hotfix commit.

## 2026-08-13 - Staging batch: courier, storefront settings, company selection, and invoice print

Reason:

- Publish the approved application changes to `staging` while explicitly excluding local skills, local settings/temp artifacts, ERP design references, and `Refarence Only Not For Commit`.

Important changed files:

- Courier dashboard/returns/payments/margin implementation under `app/Filament/Pages/Courier*`, `app/Filament/Resources/Courier*`, `app/Services/Courier*`, `app/Services/SteadfastCourierClient.php`, `app/Models/CourierReturn.php`, and the two `2026_08_12_*` migrations.
- Storefront settings navigation and shared banner implementation under `app/Filament/Resources/StorefrontSettings`, `resources/views/filament/resources/storefront-settings`, and `resources/views/storefront/partials/image-banner.blade.php`.
- Company-selection persistence in `CompanySwitchController`, `SetCurrentCompany`, the user edit page, and `CompanySelectionPersistenceTest`.
- Invoice print/PDF views and `InvoiceDesignTest`: A4 print no longer triggers the mobile breakpoint; typography follows the compact reference scale; configured order-company logos render in the main header and courier slip using only the reference invoice's physical dimensions (the reference logo asset is not used).
- `PROJECT_GUIDE.md`, `CHANGELOG.md`, and relevant feature tests updated for the combined batch.

Verification:

- Changed feature-test batches: 105 passed, 984 assertions.
- `npm.cmd run build`: passed (Vite production build and deployment metadata generation).
- Full suite was attempted twice but exceeded the command timeout. The longer run reproduced the already-documented baseline failures: three stale `ReleaseNotesTest` assertions still expect published version `1.23.0` while `CHANGELOG.md` publishes `2.0.1`, plus the two previously documented storefront flow failures. No changed-feature batch failed.

Commit status:

- Approved by the owner for commit and push to `origin/staging`.

## 2026-08-12 - Courier Merchant Dashboard: Steadfast return/payment/margin extension

Reason:

- Owner reviewed a competitor ERP (Nuport) for courier-module UX ideas and asked for a single ZamZam dashboard screen where staff manage everything courier-related (booking, balance, returns, payment/settlement) without needing a separate Steadfast login. Explored the codebase first and found a full production-grade multi-provider courier system already existed (`CourierProvider`/`CourierBooking`/`CourierStatusLog`/`CourierWebhookLog`, live Steadfast/Pathao/RedX/E-Courier clients, signed+queued webhooks, scheduled sync+alerting) — this was a targeted extension of that, not a rebuild. Full plan approved via plan mode before implementation; explicitly decided to build a native dashboard against Steadfast's official REST API rather than embedding/iframing Steadfast's own website (that pattern doesn't exist anywhere in the codebase, most portals block iframing, and it would mean storing a raw third-party login).

What happened:

- `SteadfastCourierClient`: added `createReturnRequest()`, `returnStatus()`, `payments()` (endpoint paths not published in a canonical spec — flagged in a docblock to confirm against a live call once real credentials exist).
- `CourierProviderInterface` + `AbstractCourierAdapter` + `SteadfastCourierAdapter`: added `returns()`/`paymentHistory()` to the adapter contract (default `unsupported()` for Pathao/RedX/E-Courier, so they're unaffected); `AbstractCourierAdapter::verifyWebhook()` now honors a per-provider `settings.webhook_signature_required` toggle in case Steadfast turns out not to sign webhook requests.
- New `courier_returns` table + `CourierReturn` model (`BelongsToCompany`, added to `MultiCompanyIsolationTest`) + `CourierService::requestReturn()`; new read-only `CourierReturnResource` under the Courier cluster and a "Request return" action on Bookings.
- New nullable `delivery_fee_charged`, `delivery_cost`, `cod_charge_amount`, `margin` columns on `courier_bookings`, populated by `CourierService::applyMargin()` at booking-creation time from a new "Courier Delivery Cost" section on `CourierProviderResource` (mirrors the existing "Set Delivery Fees" section) — shown as toggleable Bookings columns and a "Delivery Economics" infolist section; `CourierReportService` now sums `margin`.
- Order form: the "Courier Fraud Check" result now renders as a Total/Delivered/Undelivered/Confidence table and runs automatically (24h cache) when a customer is selected, not only on manual click.
- New `CourierPaymentHistory` page (Payments, Courier cluster) — fetches Steadfast's payment/settlement history live, 10-minute cache, columns derived from whatever keys the response returns since the shape isn't confirmed yet.
- New `CourierMerchantDashboard` page (Dashboard, first item in the Courier cluster) — live balance, performance/margin stats, recent consignments table, recent returns preview, quick links out to Providers/Bookings/Status Logs/Webhook Logs/Payments.
- `PROJECT_GUIDE.md`'s "Courier and Delivery Integration" section updated with all of the above plus an updated "Not implemented yet" list (Pathao/RedX/E-Courier return+payment endpoints; a locally-persisted payment history table).
- UX follow-up (owner feedback, 2 rounds): `CourierBookingResource`/`CourierStatusLogResource`/`CourierWebhookLogResource`/`CourierReturnResource`/`CourierPaymentHistory` all set `$shouldRegisterNavigation = false` — removed from the main Courier cluster sidebar (routes stay reachable) since only Dashboard and Providers should show there. Their quick-links now live as cards inside the Courier Merchant Dashboard's "Manage" section — first as a hand-rolled Tailwind card grid, then converted (per explicit owner ask "ফিলামেন্টের ডিফল্ট কার্ড UI লেয়াউট এইখানে এপ্লাই কর") to a native Filament `StatsOverviewWidget`/`Stat` widget (`App\Filament\Pages\CourierWidgets\CourierQuickLinksWidget`, deliberately kept outside the auto-discovered `app/Filament/Widgets/` directory so it renders only on this page, not the main admin Dashboard) — each card shows a live count/state and links to its resource, matching the panel's standard stat-card styling used elsewhere (e.g. `CourierHealthWidget`).

Verification:

- `php artisan test --filter=CourierIntegrationTest`: 15 passed (66 assertions) — covers return-request, payment-history, margin-calculation, and the widget/nav-hiding change (admin-page-render test still asserts all 3 dashboard-linked screens render OK).
- `php artisan test --filter=MultiCompanyIsolationTest`: 7 passed (confirms `CourierReturn` satisfies the company-scope contract).
- Full `php artisan test` (plain, no `--env`): 617 passed, 5 failed — the 5 failures are the pre-existing, unrelated `ReleaseNotesTest` (×3, hardcoded to expect CHANGELOG version `1.23.0`) and `StorefrontCustomerAdvanceAndComplaintTest`/`StorefrontIncompleteCheckoutRecoveryTest` (×1 each, tied to the earlier OrderFlow storefront-parity commit `a6d07277`) — confirmed unrelated to this session's courier work in a prior isolated run and reproduced identically here, so no regression from the courier/widget changes.
- Owner still needs to: (1) generate a real Steadfast API Key/Secret from their merchant portal and enter them on Courier → Providers → Steadfast; (2) once live, confirm the actual return/payment endpoint paths and outgoing webhook signature scheme against Steadfast's real responses, adjusting `SteadfastCourierClient`/`AbstractCourierAdapter` if they differ from what's implemented here.

Commit status:

- Not committed yet — awaiting owner's approval.

## 2026-08-11 - CI workflow: bump deploy.yml's PHP 8.2 to 8.4

Reason:

- The v2.0.0 production push (below) succeeded on Coolify, but GitHub Actions' `CI` workflow (`.github/workflows/deploy.yml`, triggers on push to `main`) failed at `composer install` — it was still pinned to PHP 8.2 via `shivammathur/setup-php`, which no longer satisfies `composer.json`'s `^8.4` requirement from the stack upgrade. Confirmed this doesn't affect Coolify's actual deploy (it listens to the push webhook directly, independent of CI status) — production was already live and healthy when this was caught.

What happened:

- `.github/workflows/deploy.yml`: `php-version: '8.2'` → `'8.4'`.
- `03_STACK_UPGRADE_PLAN.md`: marked Step 6 complete with a verification summary.

Verification:

- Config-only change; will be confirmed by the next push to `main` showing a green CI run.

Commit status:

- Committed and pushed to `main` (v2.0.1) with the owner's approval.

## 2026-08-11 - Stack Upgrade Plan, Step 6: final verification + production deploy

Reason:

- `03_STACK_UPGRADE_PLAN.md` Step 6, the last step before pushing the Laravel 13/Livewire 4/Filament 5/PHP 8.4 stack to production. Owner used staging for several days ("staging এ প্রবলেম পাই নি") and then asked to proceed to production.

What happened:

- Confirmed scope: only the 6 commits already on `staging` (`fef119e0` PHP 8.4, `d2f6ca40`/`e19a459a` nginx template fixes, `b04fd428` Laravel 13/Filament 5/Livewire 4, `dd214102` Node 22 fix, `117ebc8f` storefront checkout/customer experience) go to `main`. A separate, much larger set of uncommitted working-tree changes (OrderFlow v1.9.0 parity work, see the entry below) is explicitly **excluded** — it hasn't been pushed to staging or soak-tested yet.
- **Completed the CRITICAL, deploy-blocking production MySQL fix** (`03_STACK_UPGRADE_PLAN.md` Step 6, item 1): identified that the app's actual `DB_HOST` points at container `iom7u0wab3i2ucilif2kl1ms` (Coolify resource `mysql-database-iom7u0wab3i2ucilif2kl1ms`, databaseId 1) — a different, older resource than the "mysql-database" card shown by default in the Coolify UI, which is unused by production. Took a full `mysqldump` backup of the production database first (stored on the production host). Mounted `/etc/mysql/conf.d/native-password.cnf` (`[mysqld]\nmysql_native_password=ON`) via Coolify's Persistent Storage, restarted the database resource, then `ALTER USER 'mysql'@'%' IDENTIFIED WITH mysql_native_password` (same password, run as root since the app-level user lacks `CREATE USER`) + `FLUSH PRIVILEGES`. Verified via `information_schema.plugins` (plugin ACTIVE) and, more importantly, via the real production Laravel app container running `php artisan tinker --execute="DB::connection()->getPdo();"` — confirmed working, matching the identical fix already verified on staging.
- Re-ran the full `php artisan test` suite on PHP 8.4.24 immediately before the production push: **608 passed, 3,547 assertions, zero failures.**
- Added a `[2.0.0]` **Major Version Update** entry to `CHANGELOG.md` (per Step 6's instruction to categorize the final commit as a major bump) and bumped `.env.production.example`'s `APP_VERSION`/`APP_RELEASE_TYPE`/`APP_RELEASE_DATE` to match — the live Coolify production app's env vars still need the same update, done separately from this git push.

Verification:

- Full test suite (above), production DB connection verified through the real app process (not the `mysql` CLI), production backup file confirmed non-empty and contains real `CREATE TABLE` statements before any change was made.

Commit status:

- Not committed yet — awaiting owner's final approval to merge `staging` into `main` and push.

## 2026-08-11 - OrderFlow v1.9.0 parity for the Laravel storefront

Reason:

- Port the application-relevant OrderFlow v1.9.0 capabilities from the reference-only WordPress plugin into the multi-company Laravel storefront without committing the reference package itself.

What changed:

- Added checkout protection, risk-aware payment eligibility, incomplete-checkout recovery, order-status workflow/audit, and the related Filament management resources using Filament's default UI patterns.
- Added Meta Pixel and Conversions API tracking with consent controls, encrypted attribution, advanced matching, multiple Pixels, browser/custom events, Purchase timing based on courier risk, status-based events, event deduplication, retry/audit records, and recovered-order events.
- Added `storefront:retry-meta-events`, scheduled every ten minutes, with capped retry attempts and per-company isolation.
- Added the supporting migrations, models, services, queued jobs, storefront views/routes, feature tests, and updated `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `ECOMMERCE_PLAN.md`.
- WordPress/WooCommerce-specific hooks were intentionally excluded because they do not apply to this Laravel application. `Refarence Only Not For Commit/orderflow-v1.9.0/` remains reference-only and is not part of the commit.

Important changed files:

- `app/Services/StorefrontMetaTrackingService.php`, `StorefrontMetaConversionsService.php`, `StorefrontMetaDispatchService.php` and the new Meta queue jobs.
- `app/Services/StorefrontCheckoutPolicyService.php`, `StorefrontPaymentEligibilityService.php`, `OrderStatusWorkflowService.php`, and their controller/model integrations.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`, `StorefrontMetaEvents/`, `StorefrontCheckoutAttempts/`, `StorefrontCartRecords/`, and Order resource extensions.
- `database/migrations/2026_08_09_000000_add_storefront_checkout_protection.php` through `2026_08_11_000000_complete_storefront_meta_orderflow_parity.php`.
- `resources/views/storefront/partials/meta-consent.blade.php`, `meta-pixel.blade.php`, and the related storefront pages.
- `tests/Feature/StorefrontCheckoutPolicyTest.php`, `StorefrontRiskPaymentEligibilityTest.php`, `StorefrontIncompleteCheckoutRecoveryTest.php`, `OrderStatusWorkflowTest.php`, `StorefrontMetaTrackingTest.php`, and multi-company regression coverage.

Verification:

- Full test suite: **607 passed, 3,529 assertions**.
- `vendor/bin/pint --test --dirty`: passed.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed.
- `php artisan migrate --pretend --no-interaction`: passed.
- `php artisan schedule:list`: confirmed `storefront:retry-meta-events` runs every ten minutes.

Commit status:

- Approved by the owner for commit and push to `staging` on 2026-08-11.

## 2026-08-01 - nixpacks.toml: bump NIXPACKS_NODE_VERSION 20 to 22

Reason:

- The Step 4+5 (Filament 5/Livewire 4) push deployed cleanly to staging, but the build log showed: "Vite requires Node.js version 20.19+ or 22.12+. Please upgrade your Node.js version." Nixpacks' `nodejs_20` package resolves to 20.18.1, just under `package.json`'s own `engines.node: ">=20.19.0"` floor and `vite:^7.3.5`'s requirement. The build still completed (non-fatal warning), but leaving it unaddressed risks a future Nixpacks/vite bump turning this into a hard failure.

What happened:

- `nixpacks.toml`'s `[variables]` block: `NIXPACKS_NODE_VERSION = "20"` → `"22"`. Nixpacks only supports major-version selection (no patch-level pin), so `22` is the fix rather than trying to force `20.19` specifically.

Verification:

- Config-only change, not testable locally (this repo's local dev/build doesn't go through Nixpacks) — will be confirmed by the next staging deploy's build log showing the Vite warning gone and a Node 22.x version in the `npm ci`/`npm run build` output.

Commit status:

- Not committed yet — awaiting owner approval.

## 2026-08-01 - Stack Upgrade Plan, Step 4+5: Livewire 3 to 4, Filament 4 to 5

Reason:

- `03_STACK_UPGRADE_PLAN.md` Steps 4 and 5, next after Step 3 (Laravel 13, verified). The plan calls for doing these two together since Filament v5 pulls Livewire v4 in as a dependency automatically. Owner asked to proceed with the next step, configure/commit later, before pushing to `main`.

What happened:

- `composer require "filament/filament:^5.0" --with-all-dependencies` under PHP 8.4.24: clean resolution, `filament/filament` (and all `filament/*` sub-packages: actions, forms, infolists, notifications, query-builder, schemas, support, tables, widgets) v4.12.5 → v5.7.5, with `livewire/livewire` v3.8.3 → v4.3.4 pulled in automatically as required. No dependency conflicts. `php artisan filament:upgrade` completed successfully (republished vendor JS/CSS/font assets, cleared config/route/view caches).
- Read Livewire's official 3.x-to-4.x breaking-change list and checked every item against this codebase:
  - `Route::livewire()` migration: not applicable — no `Route::get(SomeComponent::class)` style full-page Livewire routes exist; all `Route::get()` calls in `routes/web.php` point to controllers.
  - `wire:model.blur` / `.change` needing a `.live` prefix: no matches anywhere in `resources/`.
  - `config/livewire.php` key renames (`layout` → `component_layout`, `lazy_placeholder` → `component_placeholder`): this app's published config only defines `temporary_file_upload` (disk/rules/directory for the dedicated `livewire-tmp` disk), never used the renamed keys, so nothing to change.
  - `<livewire:...>` tags must now self-close: only one manual usage exists (`resources/views/filament/partials/mobile-notifications-menu-item.blade.php`), already self-closed.
  - The 7 manual `Livewire::component('app.filament.resources...', SomeClass::class)` registrations in `AppServiceProvider::boot()` (for `ListProducts`, `CreateProduct`, `EditProduct`, `ListCategories`, `CreateCategory`, `EditCategory`, and the notifications component) — this registration mechanism itself wasn't removed or changed in v4, left as-is.
- Filament's own 4-to-5 upgrade guide page didn't fully render through automated fetching (the docs site appears to require JS execution that a static fetch can't reproduce), so verification leaned on the empirical path instead: full automated test suite, then a real browser session against every area the plan specifically flagged as highest-risk.

Verification:

- Full `php artisan test` (no `--env` flag) under PHP 8.4.24 + Laravel 13.23.0 + Filament 5.7.5 + Livewire 4.3.4: **556 passed, 2,981 assertions, zero regressions** — identical count to the pre-Filament-5 baseline.
- Updated `.claude/launch.json`'s dev-server `runtimeExecutable` from bare `php` (which still resolves to the system's old PHP 8.3.30 on PATH) to the full PHP 8.4.24 binary path, so the local preview server actually runs the target version instead of immediately fatal-erroring on Composer's platform check.
- Manual browser smoke test against a real `php artisan serve` session (logged in as the local demo super-admin, `demo@example.com`): Dashboard (all stats-overview/table widgets rendered with live data, no stuck "Loading..." states once the page settled), Products List, Create Product (single-column layout intact, both FileUpload fields — Featured Image and Gallery Images — rendered their drag-and-drop UI correctly), Inbox (Channels/Conversations filter panel, search, status/assignment dropdowns), Cloud Storage Settings (typed into the Access Key ID text input and toggled the "Enable R2 for new uploads" switch — both Livewire bindings updated correctly, confirming no `wire:model.defer` regression), and Create Quotation (clicked "Add item" on the Repeater — a new row with its own Product/Quantity/Unit price/Subtota/delete-icon appeared correctly). Zero browser console errors and zero server log errors across the entire session.
- Did not exercise an actual file upload (drag-and-drop / native file picker isn't reachable through the browser automation tooling used here) — instead relied on `ImageOptimizerTest` and `CloudStorageSettingsTest` (both use fake uploads to exercise the real `saveUploadedFileUsing()` hook) passing as part of the full suite above.

Commit status:

- Owner approved committing and pushing this + the Step 3 changes together to `staging` (not `main` — production DB fix still pending, per Step 6). Changed files: `composer.json`, `composer.lock`, `.claude/launch.json` (local dev tooling only, not app behavior), republished `public/js/filament/**` vendor assets (from `filament:upgrade`), `03_STACK_UPGRADE_PLAN.md`, `CHANGELOG.md`, this file. No `app/` code changes were needed for this step.

## 2026-08-01 - Stack Upgrade Plan, Step 3: Laravel 12 to 13

Reason:

- `03_STACK_UPGRADE_PLAN.md` Step 3, next in the stack-upgrade sequence after Step 2 (PHP 8.4, verified end-to-end on staging). Owner asked to proceed with the next step and configure/commit later, before pushing to `main`.

What happened:

- `composer why-not laravel/framework "^13.0"` showed the plan's three suspected blockers (barryvdh/laravel-dompdf, openspout/openspout, filament/filament) were all fine — the actual blocker was `laravel/tinker` v2.11.1, which only supports `illuminate/*` up to `^12.0`. Laravel's own 13.x upgrade guide confirms `laravel/tinker:^3.0` as a required companion bump.
- `composer require "laravel/framework:^13.0" "laravel/tinker:^3.0" --with-all-dependencies` under real PHP 8.4.24: clean resolution, no conflicts with `filament/filament` v4.12.5's dependency chain. Result: `laravel/framework` v12.64.0 → v13.23.0, `laravel/tinker` v2.11.1 → v3.0.2, `symfony/*` packages v7.4.x → v8.1.x (console, error-handler, finder, http-foundation, http-kernel, mailer, mime, process, routing, uid, var-dumper), `brick/math` 0.14.8 → 0.18.0.
- Post-update `php artisan package:discover` failed with "bootstrap/cache directory must be present and writable" even though the directory existed with normal permissions. Root cause: PHP's `is_writable()` on Windows can return a false negative for a directory whose Windows read-only DOS attribute is set, independent of actual ACL permissions — confirmed by a manual `file_put_contents()` test into the same directory succeeding despite `is_writable()` returning `false`. Fixed locally with `attrib -R` on `bootstrap/cache` (a one-time local-environment fix, not a code or repo change — Linux/Coolify containers are unaffected, this is a Windows-only PHP quirk).
- Bumped `phpunit/phpunit` from `^11.5.3` to `^12.0` per the upgrade guide's "High Impact: Updating Dependencies" item (Laravel 13 / PHPUnit 12 pairing). Installed 12.5.33 cleanly, no test syntax changes needed.
- Read Laravel's official 13.x upgrade guide in full and checked every item against this codebase:
  - **Request Forgery Protection (High Impact):** `VerifyCsrfToken` renamed to `PreventRequestForgery`. Found one direct reference in `app/Providers/Filament/AdminPanelProvider.php`'s panel `->middleware([...])` array; updated the `use` import and the array entry to `PreventRequestForgery::class`. (The old name remains a working deprecated alias, so this wasn't a functional break, but the guide recommends updating direct references.) Separately, ~15 storefront test files use `$this->withoutMiddleware(ValidateCsrfToken::class)` — verified empirically via the full test run that this still correctly excludes the renamed middleware; no changes needed there.
  - **bootstrap/app.php ordering:** confirmed `SetCurrentCompany::class` is still `prepend`-ed before `SubstituteBindings::class` — the CLAUDE.md multi-company isolation invariant survived the upgrade untouched.
  - **Cache serializable_classes (Medium Impact):** this app's `config/cache.php` doesn't define the key. Read `vendor/laravel/framework`'s `CacheManager`/`FileStore` source directly to confirm: the restrictive `unserialize(..., ['allowed_classes' => ...])` branch only activates when the config value is non-null; an absent key means Laravel falls back to unrestricted `unserialize()`, i.e. old behavior, i.e. no functional change. Left as a documented, optional future hardening item (would need to allow-list `App\Models\StorefrontSlide` and similar, since `Cache::remember()` there stores Eloquent model objects) rather than pulled into this upgrade's scope.
  - **Database upsert / MySQL DELETE with JOIN / Domain route registration precedence:** none apply — no `->upsert(` calls and no `Route::domain(` usage anywhere in this codebase.
  - **Cache/session key prefix defaults (Low Impact):** this app's `config/cache.php` and `config/session.php` already hardcode their own `Str::slug(..., '_')` fallback (predates this upgrade), so Laravel's changed *skeleton* default doesn't apply — confirmed by reading both files directly.

Verification:

- Full `php artisan test` (no `--env` flag) under real PHP 8.4.24 + Laravel 13.23.0 + PHPUnit 12.5.33: **556 passed, 2,981 assertions, zero regressions** (identical pass count to the pre-upgrade PHP 8.4 baseline).
- `php artisan test --filter=MultiCompanyIsolationTest` run separately per the plan's specific call-out: 7 passed, 122 assertions.
- `npm run build`: succeeded, `public/build/deployment.json` written, no console/build errors.

Commit status:

- Owner approved committing and pushing this + the Step 4+5 changes together to `staging` (not `main` — production DB fix still pending, per Step 6). Changed files: `composer.json`, `composer.lock`, `app/Providers/Filament/AdminPanelProvider.php`, `03_STACK_UPGRADE_PLAN.md`, this file. `bootstrap/cache`'s Windows read-only attribute fix is a local filesystem change only, nothing to commit for it.

## 2026-08-01 - Stack Upgrade Plan, Step 1: remove courier-fraud-checker-bd

Reason:

- `03_STACK_UPGRADE_PLAN.md` Step 1: `shahariar-ahmad/courier-fraud-checker-bd` is pinned in `composer.json` with no version constraint (`"*"`) and has an individual maintainer, making it a risk to the planned PHP 8.4/Laravel 13/Filament 5 upgrade (`composer update` could get stuck resolving it, or it could simply stop being compatible). The plan calls for removing it first, in isolation, before touching PHP/Laravel/Filament versions.

Important changed files:

- `app/Services/CourierFraud/CourierFraudClient.php` (new) - the `checkByPhone(string $phone, array $credentials): ?array` contract shared by all three clients.
- `app/Services/CourierFraud/PathaoFraudClient.php`, `SteadfastFraudClient.php`, `RedxFraudClient.php` (new) - direct `Illuminate\Support\Facades\Http` replacements for the package's `PathaoService`/`SteadfastService`/`RedxService`, replicating each one's exact HTTP flow (endpoints, auth, cookie/CSRF handling for Steadfast, cached access token for RedX) but taking credentials per-call instead of reading them from package config, and returning `null` on any failure instead of an `['error' => ...]` array.
- `app/Services/ExternalCourierFraudService.php` - `DRIVER_SERVICE_MAP`/`DRIVER_METHOD_MAP`/`applyConfig()` (package-config-based dispatch) replaced with `DRIVER_CLIENT_MAP` resolving the new clients via the container; the 24h cache, graceful per-courier failure, and `CustomerRiskEvent` audit logging are all unchanged.
- `composer.json`/`composer.lock` - `shahariar-ahmad/courier-fraud-checker-bd` removed via `composer remove`; `bootstrap/cache/packages.php`/`services.php` regenerated via `composer install` to clear the stale cached provider manifest (a leftover `vendor/shahariar-ahmad` directory that Windows initially failed to delete - likely locked by antivirus/indexer - was removed on retry).
- `tests/Unit/Services/CourierFraud/PathaoFraudClientTest.php`, `SteadfastFraudClientTest.php`, `RedxFraudClientTest.php` (new) - 14 tests covering success and failure paths per client via `Http::fake()`.
- `CHANGELOG.md` - new `[1.22.1] - 2026-08-01` Maintenance Update entry (Technical Notes only, no user-facing change). `.env.example`/`.env.production.example` - `APP_VERSION`/`APP_RELEASE_TYPE`/`APP_RELEASE_DATE` bumped to match.
- `tests/Feature/ReleaseNotesTest.php` - the three assertions coupled to the top `CHANGELOG.md` entry (`published_version`, the installed-version banner's version/date, and the pending-upgrade-available version) updated from `1.22.0`/`2026-07-23` to `1.22.1`/`2026-08-01` to match the new top entry.
- `03_STACK_UPGRADE_PLAN.md` - Step 1 checklist items marked done.

Behavior and safety:

- Drop-in replacement only - no behavior change. The existing `ExternalCourierFraudCheckTest` suite (6 tests, written against the old package-backed service) passes unmodified against the new clients, confirming the cache/logging/graceful-failure contract held.
- Credentials remain admin-configured per company (`CourierProvider.credentials['fraud_check']`), never touched here.

Verification:

- `php artisan test --filter=CourierFraud` - 20 passed (30 assertions): the 14 new client tests plus the existing `ExternalCourierFraudCheckTest` (6 tests) all green.
- `php artisan test --filter=ReleaseNotesTest` - 4 passed (50 assertions), confirming the CHANGELOG version-bump-coupled assertions were updated correctly.
- Full `php artisan test` - 545 passed (2,875 assertions), no regressions.
- `composer remove shahariar-ahmad/courier-fraud-checker-bd` succeeded; `grep -rl "shahariar-ahmad\|CourierFraudCheckerBd" vendor/composer/ app/` shows no remaining references outside the new clients' own explanatory doc comments.

Commit status:

- Committed by the owner directly (bundled with unrelated finance/accounts work, commit `0aa90018` "feat: enhance finance accounts and voucher workflows") - already on `main`/production before Step 2 started.

## 2026-08-01 - Stack Upgrade Plan, Step 2: PHP 8.2 to 8.4 target

Reason:

- `03_STACK_UPGRADE_PLAN.md` Step 2: raise the minimum PHP version to 8.4 (longest remaining security-support window; Laravel 13 requires 8.3+ minimum anyway) before the Laravel 13/Filament 5 upgrade in Steps 3-5.

Important changed files:

- `composer.json` - `"php": "^8.2"` -> `"^8.4"`.
- `docs/deployment.md` - Server Requirements line updated from "PHP 8.2 or newer" to "PHP 8.4 or newer".
- `CHANGELOG.md` - folded into the still-open `[1.22.1]` Maintenance Update entry (Step 1 and Step 2 are one uncommitted batch); `.env.example`/`.env.production.example` dates corrected to the actual current date, `2026-08-01` (an earlier pass in this same session had mistakenly written `2026-07-30` in the Step 1 entry/env files/plan doc/test assertions - all corrected back to `2026-08-01` in this pass, re-verified with `php artisan test --filter=ReleaseNotesTest`).
- `03_STACK_UPGRADE_PLAN.md` - Step 2 checklist items marked, noting which are done locally versus deferred to staging.

Behavior and safety:

- No dependency versions changed yet (Step 3-5 still pending) - only the declared minimum PHP version and docs.
- `nixpacks.toml` (the Coolify build config actually present in this repo - there is no Dockerfile) has no hardcoded PHP version; Nixpacks' PHP provider auto-detects it from `composer.json`'s `require.php`, so no separate Coolify build-file edit was needed.
- Static-scanned `app/` for the PHP 8.4 implicit-nullable-parameter deprecation (`SomeType $x = null` without a leading `?`) - zero matches; the few `mixed $x = null` hits found are not affected (`mixed` already includes `null`).

Verification:

- Confirmed the real local blocker rather than assuming: `composer update --dry-run` against the new constraint fails cleanly with "Root composer.json requires php ^8.4 but your php version (8.3.30) does not satisfy that requirement" - this machine (Laragon) only has PHP 8.3.30 installed, no PHP 8.4 binary anywhere on it.
- Did not force through with `--ignore-platform-reqs` - that would produce a false "pass" without proving anything about real PHP 8.4 compatibility (extension availability, runtime behavior).
- Asked the owner how to proceed; owner chose to defer `composer update` + the full extension/test-suite verification to the Coolify staging server, where Nixpacks will provision real PHP 8.4 from the updated `composer.json` on the next staging build/rebuild.
- `php artisan test --filter=ReleaseNotesTest` - 4 passed (50 assertions) after the date correction, confirming CHANGELOG.md still parses correctly and the top-entry-coupled assertions match.

Commit status:

- Committed to a new `staging` branch (commit `fef119e0`, "feat: raise PHP requirement to 8.4, poll mobile notifications only when visible") and pushed to `origin/staging` for a Coolify staging Resource the owner is setting up (this repo had no staging branch/resource before now - `main` tracks `origin/main` directly, which is the production Coolify app). Also bundled in the same commit at the owner's request: two pre-existing unrelated uncommitted local changes (`resources/views/livewire/mobile-database-notifications-menu-item.blade.php` + its test, `wire:poll.15s` -> `wire:poll.visible.15s` so the mobile notifications menu item stops polling while its tab is backgrounded). Not on `main`/production - by design, per the plan's own precondition to verify in staging before touching production.

## 2026-08-01 (continued) - Local PHP 8.4.24 install, real verification, and a staging nginx crash-loop fix

Reason:

- Owner asked to actually update the local PC's PHP version rather than only defer to staging, so `composer update` and the full test suite could be genuinely verified locally instead of only declared safe by inspection.
- Separately, the owner's first Coolify staging build (a fresh Resource pointed at the new `staging` branch) crash-looped; the owner pasted the container logs.

Important changed files:

- No repo files changed by the local PHP install itself - `C:/laragon/bin/php/php-8.4.24-Win32-vs17-x64/` was added as a new, separate PHP version alongside the existing 8.3.30 (which is untouched), following Laragon's own folder convention.
- `composer.lock` - refreshed for real via `composer update` under the new PHP 8.4.24 binary (Laravel 12.62.0 -> 12.64.0, Filament 4.11.7 -> 4.12.5, Livewire 3.8.1 -> 3.8.3, and other patch/minor bumps; nothing pinned to Laravel 13/Filament 5 yet, per plan ordering - Step 3 hasn't started).
- `nginx.template.conf` - fixed a real, pre-existing bug: the `IS_LARAVEL` and `NIXPACKS_PHP_FALLBACK_PATH` `location /` blocks were two independent `$if` blocks. Since this repo's `nixpacks.toml` sets `NIXPACKS_PHP_FALLBACK_PATH="/index.php"` and `IS_LARAVEL` auto-detects true for this project, Nixpacks rendered **both** blocks into the final `nginx.conf`, and nginx refuses to start with two identical `location "/"` contexts ("duplicate location"). Fixed by nesting the fallback block inside the Laravel block's `else`, so only one ever renders - matching Nixpacks' own upstream template shape. Root cause confirmed directly from the pasted container log line `nginx: [emerg] duplicate location "/" in /nginx.conf:57`.
- `tests/Feature/LivewireTemporaryUploadConfigurationTest.php` - new `test_nixpacks_template_never_renders_two_independent_root_location_blocks()` asserts the fallback `$if` is textually nested inside the Laravel block's `else (`, so this exact regression (someone "simplifying" the nesting back to two siblings) fails the suite instead of only surfacing on the next fresh Coolify build.
- `CHANGELOG.md` - appended to the same still-open `[1.22.1]` entry; `03_STACK_UPGRADE_PLAN.md` Step 2 checklist items flipped from "deferred to staging" to done, with the nginx finding noted.

Behavior and safety:

- This bug is **not caused by the PHP 8.4 change** - it would break any from-scratch Nixpacks build of this exact repo state, staging or production, PHP 8.2/8.3/8.4 alike. It surfaced now only because this staging Resource's build is the first completely fresh build since `nginx.template.conf` was added; the running production container was never rebuilt from scratch against this file.
- The wall of `[laravel:warn] ... environment variable, but it is not set` lines in the pasted log are expected/benign - they're Laravel enumerating optional integrations (SQS, AWS, Postmark, Slack, Firebase, R2, Redis, etc.) that a fresh staging environment simply hasn't configured yet; none of them are fatal on their own.
- One warning in the log **is** real and still needs the owner's action in the Coolify UI (not a code fix): `Your app key is not set!` - the new staging Resource needs its own `APP_KEY` environment variable (generate with `php artisan key:generate --show` or `openssl rand -base64 32`), separate from production's key.

Verification:

- Downloaded the official PHP 8.4.24 Windows build (TS, VS17, x64) from `windows.php.net`, verified its sha256 against `releases.json` before extracting (`7b57fc98...cedff3174`) - matched exactly.
- Replicated the working 8.3.30 `php.ini`'s extension set (gd, intl, mbstring, exif, mysqli, openssl, pdo_mysql, pdo_sqlite, sodium, sqlite3, xsl, zip, curl, fileinfo) into the new install; `php -m` confirms all load; `php -v` clean (also silenced two now-deprecated `session.sid_length`/`session.sid_bits_per_character` ini directives that PHP 8.4 warns about on startup).
- `composer update` under the real PHP 8.4.24 binary: succeeded on retry (first attempt hit the same transient Windows antivirus/indexer file-lock seen during the Step 1 package removal - "Could not delete ...composer/tmp-....zip"; composer is safely re-runnable after this). `composer validate` clean, no lock-file-out-of-date warning.
- Full `php artisan test` under PHP 8.4.24: **555 passed, 2,974 assertions**, zero regressions, zero deprecation notices.
- `php artisan test --filter=LivewireTemporaryUploadConfigurationTest`: 3 passed (12 assertions), confirming the nginx regression test itself is correct and passes against the fixed template.

Commit status:

- Committed and pushed to `staging` (`d2f6ca40`). **This nesting fix turned out to be broken** - see the next entry below, which corrects it after the owner's next staging redeploy crash-looped again with a different (new) symptom.

## 2026-08-01 (continued again) - nginx fix correction: Nixpacks' template renderer cannot parse nested `$if`

Reason:

- Owner redeployed staging with the `d2f6ca40` nesting fix above. The build itself succeeded this time (no `duplicate location` error, container reached "Started"), but the app container then exited immediately afterward, and the staging URL redirected to the Coolify dashboard instead of the app - a different, new symptom from the first crash loop.
- The deploy log's "Executing post-deployment command" step showed no output, which was a red herring; the real problem was the container itself exiting right after start.

Root cause:

- Nixpacks' template renderer (`scripts/config/template.mjs`, dumped in full by Coolify's own debug log) resolves `$if(COND) (...) else (...)` with a **single-pass, non-greedy regex**: `` /\$if\s*\((\w+)\)\s*\(([^]*?)\)\s*else\s*\(([^]*?)\)/gm ``. Non-greedy `[^]*?` stops each capture group at the *first* literal `)` it encounters - it cannot parse balanced/nested parentheses.
- The `d2f6ca40` fix nested a second `$if(NIXPACKS_PHP_FALLBACK_PATH)` inside the first `$if(IS_LARAVEL)`'s `else (...)` branch. As plain text this reads as correctly nested, and the earlier regression test (a raw substring match on the source file) passed - but Nixpacks' actual regex engine mis-captures it: the "otherwise" group closes at the first `)` inside the nested `$if(NIXPACKS_PHP_FALLBACK_PATH)`, leaving the rest of the nested block (its own `location /` body, its `else ()`, and the outer block's final closing `)`) as **literal, unresolved text** in the rendered `nginx.conf` - stray `(`, `)`, and `else ()` tokens inside the `server {}` block. nginx cannot parse this and exits immediately on container start, which is exactly the "app exited" / dashboard-redirect symptom reported.
- Confirmed by literally running Nixpacks' own `replaceStr()` function (copied verbatim into a throwaway Node script) against the real `nginx.template.conf` with `IS_LARAVEL=yes` and `NIXPACKS_PHP_FALLBACK_PATH` both empty and set (matching the two real Coolify environments - "Production Environment Variables" leaves it empty, `nixpacks.toml` sets it to `/index.php`) - both renders produced the same broken output with orphaned `(`, `) else ()` fragments.

Important changed files:

- `nginx.template.conf` - removed the `$if`/`else` conditional around the root `location /` block entirely. This repo's `nixpacks.toml` and Nixpacks' own auto-detection mean `IS_LARAVEL` is always `"yes"` for this project, so the non-Laravel fallback-path branch is unreachable dead code; it's simply omitted now instead of conditioned, which sidesteps the renderer's nesting limitation altogether rather than working around it. Comment updated to explain why no conditional may be reintroduced here.
- `tests/Feature/LivewireTemporaryUploadConfigurationTest.php` - replaced the old substring-matching regression test (which the broken `d2f6ca40` fix passed despite being wrong) with `test_nixpacks_template_renders_without_leftover_conditional_syntax()`, which actually renders the template via a faithful PHP port of Nixpacks' exact algorithm (same non-greedy regex, same recursion-into-captured-branch behavior) under two realistic environment combinations, and asserts exactly one `location / {` block and zero leftover `$if(`/`else (` text. This is the right level of test for this class of bug - checking raw source text is not enough, since broken nesting can still look correct as plain text.
- `CHANGELOG.md` - the `[1.22.1]` entry's nginx bullet corrected to describe both the original bug and this correction to the first attempted fix.

Verification:

- Rendered the fixed `nginx.template.conf` with the real Nixpacks `replaceStr()` algorithm (Node script, not a simulation) under `IS_LARAVEL=yes`, `NIXPACKS_PHP_FALLBACK_PATH=''` (matching Coolify's current staging config): output has exactly one `location / {` block, zero occurrences of `$if(`, clean valid nginx syntax throughout.
- `php artisan test --filter=LivewireTemporaryUploadConfigurationTest` - 3 passed (17 assertions).
- Full `php artisan test` under PHP 8.4.24: 556 passed, 2,981 assertions, zero regressions.

Commit status:

- Committed and pushed by owner directly to `staging` (`e19a459a`, "fix: nginx template's earlier duplicate-location fix was itself broken").

## 2026-08-01 (continued once more) - Staging fully live: MySQL 8.4 `caching_sha2_password` blocker found and fixed, first super-admin created

Reason:

- After the `e19a459a` nginx fix, staging's container started cleanly, but `php artisan migrate --force` (the Coolify post-deployment command) failed with `SQLSTATE[HY000] [2054] The server requested authentication method unknown to the client [caching_sha2_password]`, and visiting the site produced the same error from `SetCurrentCompany` middleware's `Schema::hasTable('companies')` check.

Root cause:

- MySQL 8.0.4+ (including staging's `mysql:8` / 8.4.11 image) creates new accounts with the `caching_sha2_password` auth plugin by default. Nixpacks' PHP 8.4 Nix package (`php84.withExtensions`) ships `mysqlnd` without the OpenSSL/RSA support that plugin needs to negotiate a password over a plaintext connection — a compile-time limitation of that specific Nix derivation, not something fixable via PHP config or SQL. Confirmed the server side was fine: `root`, using the same `caching_sha2_password` plugin, logs in without issue via the native `mysql` CLI (a full client built with proper OpenSSL linkage) — only PHP's bundled driver is affected.
- Checked **production's real database** (read-only): `SELECT VERSION()` → `8.4.9`; `information_schema.plugins` shows `mysql_native_password` present but `DISABLED`; production's own DB user's `plugin` column is `caching_sha2_password`. **This means production will hit the exact same fatal error the moment it is rebuilt under PHP 8.4 by Nixpacks — this is a hard blocker for the whole stack-upgrade plan's PHP 8.4 step reaching production, not just a staging quirk.**
- `mysql_native_password` cannot be enabled via `SET PERSIST` (unknown system variable in this build) or `INSTALL PLUGIN` (`ERROR 1125: Function already exists` — it's a *built-in*, not a loadable `.so`, and `UNINSTALL PLUGIN` refuses built-ins too). It can only be flipped from `DISABLED` to `ACTIVE` via a server-startup config option.

Fix (verified working on staging; **must be applied to production before production is ever rebuilt on PHP 8.4** — see the `⚠️ CRITICAL` note added to `03_STACK_UPGRADE_PLAN.md`'s Step 2):

- Coolify → MySQL Database Resource → Persistent Storage → Files → add file mount, destination `/etc/mysql/conf.d/native-password.cnf`, content:
  ```
  [mysqld]
  mysql_native_password=ON
  ```
- Restart the Database Resource (brief DB downtime — on production this needs a maintenance window).
- In `mysql>`: confirm `SELECT plugin_name, plugin_status FROM information_schema.plugins WHERE plugin_name = 'mysql_native_password';` now shows `ACTIVE`, then `ALTER USER '<db_user>'@'%' IDENTIFIED WITH mysql_native_password BY '<existing password>'; FLUSH PRIVILEGES;` (no password change, no data touched — only the auth method).

No application code changed for this fix — it is entirely Coolify/MySQL server configuration, done through the Coolify UI and a `mysql` shell, not through this repository.

Verification:

- `php artisan migrate --force` on staging: succeeded, all tables created against the previously-empty `default` database.
- Set `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars on the staging App Resource, ran `php artisan db:seed --force`: created the default company + first super-admin user via `DatabaseSeeder`.
- Logged into `https://staging-app.zamzamint.com/admin` with the seeded credentials: dashboard loads correctly (Business Overview, sidebar navigation, all widgets render with zero-state data as expected for a fresh company).
- Staging is now a fully working, end-to-end PHP 8.4 + Laravel 12.64 + Filament 4.12.5 environment.

Commit status:

- No code changes in this entry — nothing to commit. Only `03_STACK_UPGRADE_PLAN.md` and this file were updated (documentation), not yet committed; will bundle with the next code commit or push separately with owner approval.

## 2026-07-26 - Browser-side image pre-compression before Livewire/R2 upload

Reason:

- Server-side WebP optimization only begins after the browser has uploaded an original image to Livewire, wasting mobile bandwidth and making large camera photos more likely to fail before R2 is reached.

Important changed files:

- `app/Filament/Concerns/OptimizesUploadedImages.php` - adds a reusable native Filament/FilePond browser transform: JPEG/PNG sources are resized with `contain`, capped to 1600px standard or 800px compact, never upscaled, and permitted up to the aligned 12 MB source ceiling. Its scoped FilePond filter excludes SVG, GIF, and WebP to preserve vectors and animated formats.
- Product/category/company/storefront Filament forms - opt into standard or compact browser pre-compression. Product variation images now also use the existing server `ImageOptimizerService`, closing the prior final-optimization gap.
- `app/Services/ImageOptimizerService.php` - server fallback now truly caps the longest edge for tall portrait images as well as landscape images.
- `tests/Feature/BrowserImagePrecompressionTest.php` and `tests/Feature/ImageOptimizerTest.php` - cover the native Field configuration, format exclusions, source limit, and tall-image server cap.
- `PROJECT_GUIDE.md` and `docs/deployment.md` - document the browser → Livewire temporary storage → server WebP → R2 pipeline and the aligned 12 MB limits.

Behavior and safety:

- Browser pre-compression reduces the upload payload for JPEG/PNG but does not replace server-side validation or WebP conversion. A bypassed/unsupported browser still follows the safe server path.
- No crop is applied, and small images are never upscaled. SVG, GIF, and WebP remain unmodified until the established server compatibility rules are applied.

Verification:

- `php artisan test tests/Feature/BrowserImagePrecompressionTest.php tests/Feature/ImageOptimizerTest.php` - 8 passed, 28 assertions.
- Related upload/storage and affected form suites - 57 passed, 394 assertions.
- Full `php artisan test` - 504 passed, 2,703 assertions.
- Targeted Pint and `git diff --check` passed.

Commit status:

- Pending user approval; not committed or pushed.

## 2026-07-26 - Production Livewire upload path and R2 activation hardening

Reason:

- A 1.4 MB storefront banner failed immediately with Filament's generic `data.image.… failed to upload` message despite a successful R2 connection test and active public custom domain.
- Nixpacks' default Nginx template has no `client_max_body_size`, so its 1 MB default rejects that file at the proxy before Laravel, image optimization, or R2 storage is reached.
- The Cloud Storage screen could make a verified-but-disabled public R2 setup look ready without clearly explaining that a successful test intentionally does not activate new R2 writes.

Important changed files:

- `nginx.template.conf` - adds the Nixpacks-compatible Laravel Nginx template with a 16 MB request-body limit, timeout protection, and PHP-FPM 12 MB file / 16 MB POST limits.
- `config/livewire.php` and `config/filesystems.php` - pin Livewire's browser-stage uploads to a dedicated local `livewire-tmp` disk instead of inheriting any global filesystem-disk change.
- `composer.json` - declares `ext-gd` so Nixpacks installs the GD/WebP capability required after temporary upload, when final media is optimized and written.
- `app/Filament/Pages/CloudStorageSettings.php` - distinguishes configuration incomplete, verified-but-disabled, and active R2 write states in the native Filament status field.
- `.env.example`, `.env.production.example`, `PROJECT_GUIDE.md`, and `docs/deployment.md` - document the two-stage upload boundary, required environment values, Coolify volume/permission requirements, activation sequence, and HTTP-status diagnosis.

Behavior and deployment notes:

- The red FilePond error shown while selecting a file is a Livewire temporary-upload failure, not proof of an R2 failure. The final `r2_public` write only happens after the form is submitted.
- A successful bucket test still requires **Enable R2 for new uploads** to be turned on and **Save settings** to be clicked. The setting is intentionally not enabled by a test click.
- Keep `FILESYSTEM_DISK=local` and `LIVEWIRE_TEMPORARY_UPLOAD_DISK=livewire-tmp`; do not point Livewire's temporary stage at R2 without intentionally implementing the separate CORS/presigned browser-upload flow.

Verification:

- Focused temporary-upload, Cloud Storage, image optimizer, and company-storage suite passed: 30 tests, 230 assertions.
- Full application suite passed: 501 tests, 2,688 assertions.
- Targeted Pint, PHP syntax checks, `composer validate --no-check-publish`, and `git diff --check` passed. Composer reported the pre-existing unbound `courier-fraud-checker-bd` version warning.
- Nixpacks template structure is covered by a regression assertion. Its directives are based on Nixpacks' official PHP/Laravel template, with only the request/PHP upload limits added.

Commit status:

- Pending user approval; not committed or pushed.

## 2026-07-23 - Click-controlled app upgrades and native Firebase update push

Reason:

- A newly deployed server build was already appearing as the current Release Notes version, and same-origin Filament SPA navigation could fetch the new UI before the user confirmed **Upgrade App**.
- Filament database notifications only reach an open or later-reopened admin session; the Android app needs a real Firebase Cloud Messaging alert when it is backgrounded or closed.
- Native registration tokens require encrypted storage, authenticated ownership, per-device/deployment deduplication, retry handling, and safe stale-token cleanup.
- Release Notes needs durable installed release metadata that changes only after the user explicitly confirms Upgrade App.

Important changed files:

- `resources/js/app-updater.js` and `resources/views/filament/partials/app-updater.blade.php` - keep a pending deployment sticky, block same-origin admin SPA navigation across the update boundary, and make the confirmed Upgrade App POST the only acknowledgement/reload path.
- `app/Services/AppReleaseStateService.php`, `app/Filament/Pages/ReleaseNotes.php`, and `resources/views/filament/pages/release-notes.blade.php` - show the user's acknowledged **Installed version** separately from an **Update available** release and keep pending changelog entries out of installed history.
- `resources/js/push-notifications.js`, `capacitor.config.json`, and the Android Capacitor project files - request native Android notification permission, create the `app-updates` channel, register the FCM token, and forward received/tapped update messages to the existing upgrade prompt without acknowledging or reloading.
- `database/migrations/2026_07_23_120000_create_native_push_tracking_tables.php` - adds encrypted-token device tracking, per-device update-push delivery state, and acknowledged version/commit/build metadata on users.
- `app/Http/Controllers/Admin/PushDeviceController.php` and `routes/web.php` - add throttled authenticated register/unregister endpoints; token refresh is idempotent and a token follows the currently signed-in user.
- `app/Services/FirebaseHttpV1Sender.php` and `config/native_push.php` - implement service-account OAuth and FCM HTTP v1 delivery without committing credentials, including bounded transient retries and provider-confirmed stale-token classification.
- `app/Services/AppUpdatePushService.php` and `app/Console/Commands/NotifyLatestRelease.php` - send one visible high-priority push per active device/deployment after database notification synchronization, retry transient failures, and disable unregistered tokens.
- `app/Models/PushDevice.php`, `app/Models/AppUpdatePushDelivery.php`, and `app/Support/FirebasePushResult.php` - model protected device state and typed provider outcomes.
- `app/Models/User.php` and `app/Services/AppUpdateService.php` - initialize and update the explicitly acknowledged release version, commit, and build time.
- `.github/workflows/deploy.yml`, `.env.example`, and `.env.production.example` - inject the ignored Android Firebase client file from a GitHub secret during APK builds and document disabled-by-default Firebase server settings plus read-only secret-file/base64 credential options.
- `tests/Node/app-updater.test.mjs`, `tests/Node/push-notifications.test.mjs`, `tests/Feature/ReleaseNotesTest.php`, `tests/Feature/PushDeviceRegistrationTest.php`, `tests/Feature/AppUpdatePushTest.php`, and `tests/Unit/Services/FirebaseHttpV1SenderTest.php` - cover the click boundary, native client bridge, installed/available rendering, ownership, encryption, rotation, deduplication, retry, stale cleanup, payloads, and disabled configuration.
- `PROJECT_GUIDE.md`, `docs/deployment.md`, and `CHANGELOG.md` - document Firebase provisioning, the first-device-registration limitation, the explicit acknowledgement contract, and the infrastructure boundary.

Behavior and deployment notes:

- A detected update preserves the already-loaded screen and prevents a pending Filament SPA navigation from replacing it. The update notification, modal, Release Notes action, or push tap can reveal the available release but cannot acknowledge it.
- Only the authenticated **Upgrade App** POST records the acknowledged deployment/version and performs the cache-cleared reload. Release Notes labels an unacknowledged deployment as available rather than installed.
- FCM service-account JSON must be mounted outside the repository and referenced by `FIREBASE_CREDENTIALS`; `FIREBASE_CREDENTIALS_JSON_BASE64` is the secret-value fallback.
- Set `FIREBASE_PUSH_ENABLED=true` and `FIREBASE_PROJECT_ID` only after the matching Android Firebase client is configured.
- The push payload advertises the deployment but never acknowledges it. Only the existing explicit Upgrade App POST changes installed release state.
- Run `php artisan migrate --force`, then `php artisan release:notify-deploy` after the new build is healthy. The existing five-minute scheduler remains the retry/catch-up path.
- The first FCM-enabled Android release cannot push to an existing installation until that installation opens the updated app, signs in, grants notification permission, and registers its token; subsequent deployments can reach that registered device.
- This click boundary protects only the loaded client screen, not an already-replaced PHP backend. A refresh, sign-in, Android process restart/WebView eviction, deep link, or app reopen can load the current server before acknowledgement. True use of the old full application until approval requires immutable blue/green releases, restart-safe per-installation sticky routing, shared session/database/storage, and backward-compatible migrations; a single replaced Coolify container cannot provide it.
- Native sending can be stopped without deleting registrations by setting `FIREBASE_PUSH_ENABLED=false` and rebuilding the config cache. Code rollback should retain the additive update/push migrations; blindly rolling them back would delete device delivery ledgers and acknowledged release metadata.

Verification:

- Focused native-push, release-notification, Release Notes, and app-upgrade suite passed: 41 tests, 244 assertions.
- Full application suite passed: 498 tests, 2,675 assertions.
- Deployment-metadata, browser-updater, and native-push client suites passed: 5, 9, and 9 tests respectively.
- Production Vite build passed and generated a ready deployment manifest.
- Capacitor correctly discovered all four Android plugins, including `@capacitor/push-notifications`. Local Android sync/build could not finish because this Windows workspace had locked generated Android folders and no configured Java/JAVA_HOME; the GitHub Actions Android job uses JDK 21 and remains the reproducible APK verification path.

Commit status:

- Pending user approval; not committed or pushed.

## 2026-07-23 - Single storefront-domain editor and sticky settings actions

Reason:

- Storefront Domain and Domain verified were writable from both Company Management and Site Settings, creating two competing admin save paths for the same company columns.
- Saving a different hostname could accidentally carry the previous hostname's verified status.
- Company and Site Settings submit buttons needed to remain available while long forms are scrolled.

Important changed files:

- `app/Filament/Resources/Companies/CompanyResource.php` - removes the writable domain and verification controls from Company create/edit while retaining read-only table/view status.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` - keeps Site Settings as the sole domain editor, resets the visible verification toggle when the hostname changes, and explains the two-step verification flow.
- `app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php` and `EditStorefrontSetting.php` - make company/settings writes transactional, reset verification for a changed normalized hostname, and expose Save changes in the sticky native Filament page header beside related actions.
- `app/Filament/Resources/Companies/Pages/CreateCompany.php` and `EditCompany.php` - move the submit action into the existing sticky Filament page header without duplicating a footer save.
- `app/Filament/Pages/CompanySettings.php` and its Blade view - move View companies and the selected-company Save changes action into the sticky header; All Companies mode keeps save hidden.
- `tests/Feature/CompanySettingsTest.php` and `tests/Feature/PhaseFourAdminPagesTest.php` - cover the single-editor boundary, preservation of read-only domain state, hostname-change verification reset, second-save verification, Storefront create/edit persistence, and header action placement.
- `PROJECT_GUIDE.md` and `CHANGELOG.md` - document the ownership and sticky-action contract.

Behavior notes:

- Canonical values remain in `companies.domain` and `companies.domain_verified`; no migration or public routing behavior changed.
- Company list/view can still display domain readiness, but only Site → Settings can edit it.
- A different hostname is always saved as unverified. After DNS and server routing are confirmed, turn on Domain verified and save again.

Verification:

- Focused Company Settings and Storefront admin suite passed: 27 tests, 228 assertions.
- Full application suite passed: 479 tests, 2,574 assertions.

Commit status:

- Included in the 2026-07-23 all-changes release commit approved by the user.

## 2026-07-23 - R2 draft connection testing and local demo login diagnosis

Reason:

- The Cloud Storage **Test** buttons ignored values typed into the current Filament form and tested only previously persisted settings, which produced a misleading generic “fill in” error.
- Example public bucket/domain placeholders could be mistaken for saved values.
- The local demo login was unavailable because the active `database/demo.sqlite` contained no users after the previously reported accidental test migration reset.

Important changed files:

- `app/Filament/Pages/CloudStorageSettings.php` - validates the exact public/private test requirements, stages the current draft without changing R2's enable state, preserves a blank stored-secret field, and makes example placeholders explicit.
- `app/Services/StorageSettingsService.php` - reports the specific missing or inconsistent R2 settings and uses accurate connection-test cleanup wording.
- `resources/views/filament/pages/cloud-storage-settings.blade.php` - explains through native Filament button tooltips that a test stages the draft but does not enable R2.
- `tests/Feature/CloudStorageSettingsTest.php` - covers unsaved public/private drafts, exact disk configuration, encrypted-secret preservation, missing-field failures, public-domain probing, and the no-activation guarantee.
- `PROJECT_GUIDE.md` and `CHANGELOG.md` - document the corrected R2 test contract.

Local-only recovery:

- The active local demo database had zero users; its schema and SQLite integrity were healthy.
- The idempotent `DemoDataSeeder` was used against the configured `demo` connection to recreate `demo@example.com`, its active Super Admin access, company membership, and deterministic demo records without replacing the SQLite file.
- Existing recovery candidates and forensic copies remain untouched and untracked.

Verification:

- Focused Cloud Storage and company-storage suites passed: 22 tests, 204 assertions.
- Full application suite passed: 475 tests, 2,505 assertions.
- Targeted Pint, Blade cache compilation, cache cleanup, and `git diff --check` passed.
- Local demo authentication was verified with the seeded password hash and Laravel's configured authentication guard.

Commit status:

- Included in the 2026-07-23 all-changes release commit approved by the user.

## 2026-07-23 - User-controlled app upgrade, update alerts, and profile settings

Reason:

- A deployed build should not silently hard-reload an already-open admin app; users need a clear, deliberate upgrade control after saving unfinished work.
- The existing Filament notification bell had no reliable deployment-alert delivery, and its mobile unread badge was stale.
- The avatar menu did not expose the signed-in user's native Filament profile page.
- Standalone Artisan commands using `--env=testing` needed a persistent-database safety guard.

Important changed files:

- `app/Support/AppDeployment.php` and `scripts/write-deployment-metadata.mjs` - create and validate a combined commit/source/assets artifact identity, build time, actual Vite manifest hash, and fail-closed readiness.
- `resources/js/app-updater.js` and `resources/views/filament/partials/app-updater.blade.php` - poll the no-cache deployment endpoint, require two matching newer-build observations, keep confirmed state sticky, reject older rolling nodes, warn about unfinished work, and reload only after explicit consent.
- `app/Http/Controllers/Admin/AppUpgradeController.php` and `routes/web.php` - expose authenticated sync/upgrade actions, exact confirmed-deployment validation, safe admin return URLs, no-store responses, cache clearing, and enriched `/health/version` metadata.
- `app/Services/AppUpdateService.php`, `app/Notifications/AppUpdateAvailable.php`, `app/Models/AppUpdateDelivery.php`, and the new migration - add monotonic rolling-deploy ordering, synchronous Filament-format notifications, per-user acknowledgement, strict delivery deduplication, obsolete-update cleanup, and notification/acknowledgement race protection.
- `app/Http/Middleware/SyncAppUpdates.php` and `app/Console/Commands/NotifyLatestRelease.php` - provide non-blocking request-time delivery for the current user plus scheduled/manual catch-up delivery for missing active users.
- `app/Providers/Filament/AdminPanelProvider.php` - enables native Profile Settings, the highlighted conditional Upgrade App action immediately above Sign out, eager/polling database notifications, and reload guards.
- `app/Livewire/MobileDatabaseNotificationsMenuItem.php` and its view - add a native Filament dropdown item with live unread polling and accessible labels without nested dropdown markup.
- `.env.testing`, `routes/console.php`, and `TestingEnvironmentSafetyTest` - force default and demo testing connections to in-memory SQLite and make `demo:refresh` respect its configured safe path.
- `.github/workflows/deploy.yml`, `package.json`, and `vite.config.js` - run deployment/updater Node tests in CI and generate deployment metadata after every production asset build.
- `CHANGELOG.md`, `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, `docs/deployment.md`, and `docs/update-safety.md` - document the feature, deployment contract, rolling-node safeguards, and the frontend-shell-only limitation.

Behavior and deployment notes:

- Existing users see one update notification and Upgrade App for the first ready deployment after the migration; users created on the current build start acknowledged.
- The POST carries the exact deployment ID shown to the user. A request routed to an older/mismatched/unready node cannot acknowledge or clear caches.
- The open browser/Capacitor frontend shell is not automatically hard-reloaded. The deployed PHP backend still changes immediately; whole-stack old-version pinning requires sticky blue/green infrastructure and forward-compatible migrations.
- Production must run `npm run build`, `php artisan migrate --force`, and preferably `php artisan release:notify-deploy` after the new build is healthy.

Verification:

- Deployment metadata Node suite passed: 5 tests.
- Browser updater Node suite passed: 6 tests.
- Focused deployment, upgrade, notification, mobile badge, profile, release-note, and testing-safety suites passed.
- Production Vite build passed; generated deployment metadata matched the actual Vite manifest and resolved `ready=true`.
- Route, Blade, and configuration cache builds passed, followed by `php artisan optimize:clear`.
- Targeted Pint and `git diff --check` passed.
- Full application suite passed: 470 tests, 2,456 assertions.
- Final focused deployment, rolling-node, upgrade, notification, mobile, profile, release-note, and testing-safety suite passed: 32 tests, 174 assertions.
- Browser-control visual QA could not start because the installed runtime rejected its environment metadata (`sandboxPolicy` missing); native Filament rendered assertions and feature tests cover the interaction contract.

Commit status:

- Included in the 2026-07-23 all-changes release commit approved by the user.

## 2026-07-22 - Cloudflare R2 credential guidance in Cloud Storage

Reason:

- Every R2 credential and topology field needed concise, field-level instructions showing exactly where to obtain or configure its value in Cloudflare.
- Essential setup guidance must remain usable by keyboard and touch users without turning the settings form into a wall of helper text.

Important changed files:

- `app/Filament/Pages/CloudStorageSettings.php` - adds a native Filament R2 setup-guide action and accessible information actions beside all eight configuration fields, with current Cloudflare dashboard steps and official documentation links.
- `tests/Feature/CloudStorageSettingsTest.php` - verifies the setup modal content, exact field-to-help-action placement, icon-button accessibility configuration, credential guidance, and private-bucket safety guidance.
- `PROJECT_GUIDE.md` - documents the in-product R2 credential-help contract.

Verification:

- Focused R2 setup-guide and field-help test passed: 1 test, 41 assertions.
- Full Cloud Storage settings suite passed: 5 tests, 71 assertions.
- Full application suite passed: 446 tests, 2,330 assertions.
- Targeted Pint formatting, PHP syntax checks, `php artisan view:cache`, and `git diff --check` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Keep Company Settings visible in All Companies mode

Reason:

- The Company Management selector hid Company Settings whenever Super Admin selected `All Companies`, leaving only the Companies item visible.

Important changed files:

- `app/Filament/Pages/CompanySettings.php` - separates page/navigation permission from the requirement for a specific active company while retaining the selected-company save guard.
- `resources/views/filament/pages/company-settings.blade.php` - shows a native Filament select-company empty state in `All Companies` mode and renders the form only for one selected company.
- `tests/Feature/CompanySettingsTest.php` - covers selector visibility, the safe empty state, normal selected-company rendering, and existing permission boundaries.
- `PROJECT_GUIDE.md` - documents the visible-page/non-writable-form contract for `All Companies` mode.

Verification:

- Focused Company Settings and admin navigation suite passed: 29 tests, 207 assertions.
- Full application suite passed: 445 tests, 2,288 assertions.
- Targeted Pint formatting, `php artisan view:cache`, and `git diff --check` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Site labels and native Filament page UI

Reason:

- The storefront cluster repeated `Storefront` across its sidebar and child selector labels; the admin-facing module should read as `Site` with concise child names.
- Reports, Release Notes, Backups, and Product Setup used page-specific markup and styling instead of the project's required Filament-default admin patterns.

Important changed files:

- `app/Filament/Clusters/Storefront.php` - changed the admin navigation label and cluster breadcrumb to `Site` while retaining the `storefront` slug.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`, `StorefrontPages/StorefrontPageResource.php`, and `StorefrontPayments/StorefrontPaymentResource.php` - shortened selector labels to `Settings`, `Pages`, and `Payments`; `Hero Slides` and `Homepage Carousels` remain unchanged.
- `app/Filament/Pages/Reports.php` and `resources/views/filament/pages/reports.blade.php` - moved report results to a dynamic Filament table with native filters, tabs, metric sections, empty states, and CSV/PDF header actions.
- `app/Filament/Pages/ReleaseNotes.php` and `resources/views/filament/pages/release-notes.blade.php` - render release metadata and changelog content with Filament sections, badges, buttons, and empty states.
- `app/Filament/Pages/Backups.php` and `resources/views/filament/pages/backups.blade.php` - replaced custom cards/forms/tables/modal styling with schema sections, native header actions, a Filament modal form, and infolist backup tables.
- `app/Filament/Pages/ProductSetup.php` and `resources/views/filament/pages/product-setup.blade.php` - replaced custom onboarding/license/checklist UI with schema sections, Filament form controls/actions, and an infolist checklist.
- Relevant navigation and page feature tests cover the display labels, native components, existing actions, and the removal of page-local CSS.
- `PROJECT_GUIDE.md` - documents the display-only Site name, unchanged storefront route/domain vocabulary, and the native Filament page contracts.

Route and compatibility notes:

- Canonical admin URLs remain `/admin/storefront/...`; no route, resource class, model, database table, or public storefront-domain name was renamed.
- Existing report exports, backup downloads, setup saves/license activation, and release-document links retain their current actions and authorization behavior.

Verification:

- Focused Site navigation-label test passed: 1 test, 7 assertions.
- Combined Site navigation, Reports, Release Notes, Backups, and Product Setup regression suite passed: 38 tests, 277 assertions.
- Full application suite passed: 444 tests, 2,278 assertions.
- Targeted Pint formatting, `php artisan view:cache`, and `git diff --check` passed.
- Browser-control visual QA could not start because the installed runtime rejected its environment metadata (`sandboxPolicy` missing); rendered Filament feature assertions cover the native component contract.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Unified Filament navigation clusters for business modules

Reason:

- Storefront, CRM, Finance, Sales, Purchasing, Inventory, Accounts, Reports, and Settings needed the same one-sidebar-item/page-selector behavior already used by Courier, Customer Success, and Company Management.
- The implementation must stay inside Filament's native navigation patterns, remain responsive, preserve permissions, and avoid breaking existing bookmarks or operational download/export URLs.

Important changed files:

- `app/Filament/Clusters/Storefront.php`, `Crm.php`, `Finance.php`, `Sales.php`, `Purchasing.php`, `Inventory.php`, `Accounts.php`, `Reports.php`, and `Settings.php` - added ordered native Filament clusters with top sub-navigation, responsive mobile selectors, icons, exact CRM breadcrumb text, and direct-root access guards.
- `app/Filament/Clusters/CompanyManagement.php`, `Courier.php`, and `CustomerSuccess.php` - aligned sidebar ordering and added the same direct-root authorization guard.
- The corresponding Filament resources/pages - replaced legacy navigation groups with ordered cluster membership. Storefront Payments now follows Homepage Carousels; Audit Logs and Release Notes have unique Settings order values.
- Hidden Shipment and Container resources remain outside Purchasing so they cannot accidentally expose its sidebar item to unauthorized roles. Expense Categories, Transaction Ledgers, and User Roles remain hidden selector support pages while their generated internal links use the relevant cluster route.
- `app/Providers/Filament/AdminPanelProvider.php` - removed the obsolete explicit navigation-group registry; Filament discovers and renders the cluster items directly.
- `app/Http/Controllers/Admin/LegacyAdminClusterRedirectController.php` and `routes/web.php` - authenticated legacy child URLs preserve deep record paths and query strings while redirecting to canonical cluster routes. Existing order print/PDF, report export, CSV sample/export, backup download, and attachment routes remain unchanged.
- `resources/views/chat-order/success.blade.php` and `closed.blade.php` - staff return links now target the canonical CRM Inbox URL.
- Existing feature tests now exercise canonical nested routes; `tests/Feature/AdminNavigationClustersTest.php` covers all class mappings, top selectors, root destinations, rendered selector labels, permission filtering, legacy query/deep-link compatibility, and custom-route precedence.
- `PROJECT_GUIDE.md`, `docs/backup-restore.md`, and `CHANGELOG.md` - documented the navigation contract and canonical admin paths.

Verification:

- `AdminNavigationClustersTest` passed: 8 tests, 79 assertions.
- Full application suite passed: 440 tests, 2,199 assertions.
- Targeted Pint validation passed for the cluster implementation and affected tests.
- `php artisan view:cache` and `git diff --check` passed.
- Route discovery confirms canonical nested routes for all nine modules plus the authenticated compatibility route.
- Browser-control visual QA could not start because the installed runtime rejects its environment metadata (`sandboxPolicy` missing); native Filament component rendering and feature assertions verify the desktop/mobile selector contract.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Company Management Filament cluster

Reason:

- Company Management should behave like the existing Courier and Customer Success pages: one sidebar destination with Filament-native page selection instead of separate menu entries.

Important changed files:

- `app/Filament/Clusters/CompanyManagement.php` - added the native cluster with top sub-navigation, matching Courier and Customer Success.
- `app/Filament/Resources/Companies/CompanyResource.php` and `app/Filament/Pages/CompanySettings.php` - registered Companies and Company Settings as ordered cluster children.
- `app/Providers/Filament/AdminPanelProvider.php` - removed the obsolete standalone Company Management navigation group.
- `routes/web.php` - preserved the former Companies and Company Settings entry URLs as authenticated redirects.
- `tests/Feature/CompanySettingsTest.php` - verifies cluster membership, top page selector, root redirect, child rendering, legacy redirects, and existing permission/company-context behavior.
- `PROJECT_GUIDE.md` - documents the cluster and canonical child routes.

Verification:

- `CompanySettingsTest` passed: 19 tests, 111 assertions.
- Route discovery exposes the cluster root plus Companies and Company Settings child routes under `/admin/company-management`.
- Full application suite passed: 432 tests, 2,120 assertions.
- Pint passed for every changed PHP file.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Invalid legacy company logos no longer crash Filament

Reason:

- A legacy global logo path had been copied into `companies.logo` before company-scoped storage validation was introduced. Clearing the global AppSetting restored `/admin/login`, but `/admin/companies` still threw a 500 while resolving the copied malformed company logo.

Important changed files:

- `app/Services/CompanySettingsService.php` - added a lightweight company-name resolver and made optional logo URL/PDF-path presentation fail closed when a stored path violates the company storage contract.
- `app/Support/CompanyMedia.php` - invalid or cross-company public media values now render as no image instead of crashing Filament tables and infolists.
- `app/Providers/Filament/AdminPanelProvider.php` - brand text no longer eagerly builds the full media-backed company profile.
- `tests/Feature/CompanySettingsTest.php` - covers malformed global branding, selected-company branding, and the Companies table while preserving the bad value for deliberate recovery.
- `PROJECT_GUIDE.md` - documents strict storage enforcement and fail-closed optional media rendering.

Verification:

- Reproduction tests failed with the production exception before the fix and passed afterward.
- `CompanySettingsTest` passed: 17 tests, 99 assertions.
- `CompanyStorageServiceTest` passed: 13 tests, 91 assertions, including strict traversal and cross-company rejection.
- Full application suite passed: 430 tests, 2,108 assertions.
- Pint passed for all changed PHP files.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Inbox thread containment, latest-message scroll, collapsible conversations, and compact product cards

Reason:

- Real desktop Inbox screenshots showed a long order URL forcing the outgoing bubble beyond the thread column, the initial thread position becoming unreliable after a large lazy-loaded image, the catalog image overwhelming the product message, and the fixed Conversations pane consuming thread width when the operator wanted to focus on chat.

Important changed files:

- `resources/views/filament/pages/inbox.blade.php` — every grid pane and message bubble now has explicit minimum-width/overflow containment; long text and links wrap anywhere; short threads anchor to the bottom; multi-phase synchronization after open/reload/navigation/layout changes keeps the newest message visible; lazy image loads preserve bottom stickiness; older messages remain above and independently scrollable; order-form images render as clickable 192×128 thumbnails while normal chat images retain a sensible larger cap. The desktop Conversations pane now uses Filament icon buttons to collapse into a persistent customer-profile rail with unread badges, while the thread automatically expands into the released width; mobile remains fully expanded.
- `app/Services/Meta/MetaGraphService.php` — root-relative local public-disk media is expanded with a public `APP_URL` before Meta delivery. Loopback/private-IP URLs are omitted so WhatsApp/Messenger still receive the order text instead of rejecting the entire message or receiving an unusable local URL.
- `tests/Feature/InboxPageTest.php` and `tests/Feature/MetaMessagingReliabilityTest.php` — rendered thumbnail/wrapping contracts, chronological newest-last history, latest-message resync hooks, collapsible profile-rail controls, absolute outbound media, and localhost text-only fallback are covered.
- `PROJECT_GUIDE.md` — the current thread behavior, collapsible desktop rail, and outbound media requirement are documented.

Verification:

- Full application suite — 428 passed, 2,100 assertions.
- Inbox suite — 15 passed, 86 assertions.
- `php artisan view:cache` and a static parse of the Inbox Alpine component — passed.
- `npm run build` — passed; compiled theme includes `overflow-wrap:anywhere`, `break-all`, `min-width:0`, the compact thumbnail utilities, and the responsive collapsed-rail layout classes.
- Browser-control visual QA could not start because the installed runtime still rejects its environment metadata (`sandboxPolicy` missing); the user-provided screenshot guided the fix and rendered-component/CSS assertions cover the affected contracts.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-21 - WhatsApp Cloud reliability and channel-based modern Inbox

Reason:

- WhatsApp callback verification had been configured, but incoming messages never appeared and outgoing attempts had no durable failure state. The Inbox also needed a channel-first, responsive workflow comparable to Meta Business Suite while remaining entirely within Filament's default component system.
- Live read-only diagnostics confirmed the public callback, verify token, and Phone Number ID mapping work. The saved Meta access token is expired (`OAuthException` code `190`, subcode `463`), which definitively blocks live outgoing messages and media until the owner replaces it. Callback verification alone also does not subscribe the app to the WABA or enable the `messages` field.

Important changed files:

- `app/Services/Meta/MetaGraphService.php`, `MetaGraphException.php`, `config/services.php`, `.env.example`, and `.env.production.example` — centralized configurable Graph `v25.0`, bearer-token requests, health/WABA subscription helpers, safe errors, no automatic retry for non-idempotent sends, and Meta-only size-limited media downloads.
- `database/migrations/2026_07_21_000300_add_meta_diagnostics_to_conversation_channels_table.php`, `app/Models/ConversationChannel.php`, and the Conversation Channel Filament resource/pages — WABA ID, connection-test/callback/subscription/webhook/inbound/outbound/error diagnostics, tenant-locked ownership, truthful `Configured` versus `Inbound confirmed` states, copyable callback, encrypted-secret preservation, and **Test & Subscribe**.
- `app/Http/Controllers/MetaWebhookController.php` and `app/Jobs/StoreIncomingMessageJob.php` — dotted handshake parameters, exact raw HMAC, all-entry/all-change company routing, Phone Number ID/WABA pairing, synchronous transactional core persistence before `200`, atomic dedupe/unread updates, monotonic message times/statuses, and durable queued media/AI work.
- `app/Services/Crm/ConversationMessengerService.php`, `app/Jobs/MarkConversationReadJob.php`, `DownloadConversationMediaJob.php`, `AiReplyService.php`, and `ChatOrderController.php` — durable `sending → sent/failed` archive for Meta delivery, truthful `internal` state for local/manual activity, sanitized retryable failure bubbles, atomic retry claim, reply-window enforcement, nonblocking durable read-receipt jobs, capped secure media handling, and AI handoff on delivery failure.
- `app/Filament/Pages/Inbox.php`, `resources/views/filament/pages/inbox.blade.php`, `resources/css/filament/admin/theme.css`, `AdminPanelProvider.php`, and `vite.config.js` — Filament-default channel tabs; URL-backed filters; paginated/list-thread-details desktop layout; mobile list/thread navigation; newest-50 history loading; accessible message log, forms, times, focus and reduced-motion behavior; reply/internal-note composer; retry, assignment, unread, status, AI and channel-health controls; company currency/timezone; and independent scrolling.
- `app/Models/User.php` — explicit `crm.view`/`crm.manage`; built-in access is limited to Super Admin, Manager, and Sales Staff.
- `StorefrontSettingResource.php` and `StorefrontNotificationService.php` — abandoned-cart templates may select the company's active WhatsApp Chat Channel so Inbox and storefront automation share one token/Phone Number ID; legacy encrypted values remain fallback-only until selected.
- Meta, ingest, channel resource, Inbox, AI, private-media, storefront-reminder, and permission feature tests — regression coverage for the repaired integration, tenant boundaries, failure archive/retry, nonblocking read, secure media, role privacy, and modern Inbox behavior.
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `ECOMMERCE_PLAN.md` — current architecture, setup/recovery steps, permissions, deployment behavior, and verification documented.

Verification:

- Full application suite — 427 passed, 2,089 assertions.
- Latest Inbox + Meta reliability suites — 30 passed, 149 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed; custom Filament theme contains the responsive utilities used by the Inbox.
- Browser-plugin visual QA could not start because the installed runtime rejected its environment metadata (`sandboxPolicy` missing); responsive/accessibility contracts were covered by Blade compilation, guideline review, rendered-component assertions, and the production CSS build.

Deployment and owner action:

- Run `php artisan migrate --force`, rebuild frontend assets, and clear/cache configuration/views.
- Replace the expired token with a permanent Meta System User token having `whatsapp_business_messaging` and `whatsapp_business_management`; confirm Phone Number ID and WABA ID; keep the Meta app Live; **Verify and Save** the callback; enable the WhatsApp `messages` field; then save and run **Test & Subscribe** in CRM → Chat Channels.
- Send a real customer WhatsApp message and confirm **Last Webhook** and **Last Inbound** update. The application cannot generate or renew the owner's Meta token and Meta offers no API proof for the dashboard's `messages` checkbox.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-21 - Company-isolated R2 storage and company-wise invoicing

Reason:

- Use one centrally managed Cloudflare R2 connection without mixing tenant objects, expose storefront media through a public CDN bucket, keep chat/voucher files private, migrate legacy objects without deletion, and make invoice identity/layout settings unambiguously company-specific.

Important changed files:

- `app/Services/StorageSettingsService.php`, `config/filesystems.php`, and `app/Filament/Pages/CloudStorageSettings.php` — distinct public/private R2 buckets, encrypted shared credentials, private-access attestation, connection-gated activation, custom-domain verification, and locked active topology.
- `app/Models/Company.php`, `app/Services/CompanyStorageService.php`, `app/Support/CompanyMedia.php`, and `app/Support/StorageUrl.php` — immutable company storage UUIDs, safe public/private prefixes, tenant-authorized writes, outage-tolerant dual reads, verified CDN preference manifests, and no unsupported R2 object ACLs.
- `app/Services/CompanyStorageMigrator.php`, `app/Console/Commands/MigrateCompanyStorage.php`, `database/migrations/2026_07_21_000000_add_storage_key_to_companies_table.php`, and `2026_07_21_000100_create_legacy_private_storage_paths_table.php` — dry-run/copy/checksum migration, destination preflight, resumable conflict handling, case-sensitive private legacy ownership registry, and source retention.
- `app/Http/Controllers/Admin/ConversationMediaController.php`, `VoucherAttachmentDownloadController.php`, `app/Jobs/DownloadConversationMediaJob.php`, and voucher/inbox models/views — authenticated company-aware private downloads, inactive-session denial, and private company-prefix uploads.
- Product/category/company/storefront Filament resources, storefront views, `CompanySettingsService`, `WooCommerceImportService`, and `DemoDataSeeder` — company-scoped public media writes and dual-read previews across local and R2 storage.
- `app/Filament/Pages/CompanySettings.php`, `resources/views/filament/pages/company-settings.blade.php`, `app/Services/CompanySettingsService.php`, `OrderPdfController.php`, and invoice print/PDF views — default Filament company settings UI, locked mounted-company saves, order-company-specific print/PDF settings, and nested invoice controls.
- `app/Filament/Resources/Companies/CompanyResource.php`, `app/Rules/AccessibleCompany.php`, and `database/migrations/2026_07_21_000200_add_unique_invoice_prefix_to_companies_table.php` — safe post-create company-logo uploads, Filament-compatible company authorization, normalized database-unique invoice prefixes, and inactive-company reactivation for Super Admin.
- Storage, private attachment, public media, company settings, invoicing, and migration feature tests — regression coverage for tenant boundaries, topology/attestation, backfills, copy verification, inactive users, context drift, invoice isolation, and the real PDF controller path.
- `PROJECT_GUIDE.md` — architecture, deployment sequence, important files, and verification commands.

Verification:

- Focused storage, storefront, settings, and invoicing suite — 71 passed, 482 assertions.
- `php artisan test --compact` — 388 passed, 1,879 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed.
- `vendor/bin/pint --dirty` and `git diff --check` — passed.

Deployment notes:

- Run `php artisan migrate` first.
- In Cloud Storage, save configuration while disabled, test the public custom domain and the attested private bucket, then enable R2.
- Run `php artisan storage:migrate-company-files --company={slug} --scope=all` and review the dry-run before the production `--execute --force` run. Source objects are intentionally retained.
- Select a specific company before opening Company Settings and verify its invoice prefix/contact/layout settings; `All Companies` cannot edit these settings.

## 2026-07-20 - Storefront release-blocker, privacy, and responsive UX hardening

Reason:

- The storefront audit found broken variable-product quick add/mobile CTA behavior, inconsistent zero-stock preorder quantities, ambiguous manual-payment inputs, publicly enumerable phone-only order history, unsigned checkout-success pages, and several mobile/keyboard accessibility gaps.

Important changed files:

- `app/Http/Controllers/Storefront/CartController.php`, `resources/views/storefront/partials/product-card.blade.php`, `products/show.blade.php`, and `cart/show.blade.php` — variable products now open option selection; desktop/mobile variant CTAs stay synchronized; zero-stock preorders and cart MOQ/ceiling rules match `StorefrontCart`; pending and duplicate-submit states added.
- `app/Http/Controllers/Storefront/CheckoutController.php`, `resources/views/storefront/checkout/show.blade.php`, and `checkout/success.blade.php` — one shared bKash/Nagad sender/transaction input set, valid enabled-method fallback, semantic/error-focused checkout, signed production success/tracking URLs, and owner-or-signature authorization.
- `app/Http/Controllers/Storefront/AccountOrdersController.php`, `OrderTrackController.php`, and `resources/views/storefront/account/orders.blade.php` — production history/reorder requires the authenticated owning customer; disabled-account stores redirect to manual tracking; public tracking still uses order number + phone, while signed or owner access keeps phone out of URLs. Phone-only history remains only in the local/admin preview.
- `resources/views/storefront/layout.blade.php`, `home.blade.php`, `products/index.blade.php`, `products/show.blade.php`, `contact/show.blade.php`, and `resources/css/app.css` — accessible mobile menu/theme state/current navigation, global focus/touch treatment, brand-color contrast, FAQ/product-tab semantics, two-column mobile catalog, image dimensions, and safe-area spacing.
- `tests/Feature/ProductVariantTest.php`, `StorefrontFoundationTest.php`, `StorefrontManualPaymentTest.php`, `StorefrontMenuTest.php`, `StorefrontPreorderPaymentTest.php`, `StorefrontReorderTest.php`, and `StorefrontCustomerAuthTest.php` — regression coverage for the repaired purchase, privacy, payment, and accessibility contracts.
- `n8n Workflows/*.json` and `n8n Workflows/README.md` — 30 embedded Meta authorization/access-token values replaced with four `$env` expressions; credential/instance/workflow/webhook metadata and pinned data removed; required host variables and post-import credential/webhook checks documented.
- `PROJECT_GUIDE.md` — current routes, authorization rules, important files, and verification commands documented.

Verification:

- `php artisan test --compact` — 344 passed, 1,568 assertions.
- Storefront-focused suite — 82 passed, 472 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed.
- Live QA at 390px mobile and 1440px desktop — no horizontal overflow, console errors, or failed requests; mobile menu focus/Escape, two-column catalog, synchronized variable-product CTAs, and keyboard product tabs verified.
- Pre-push secret/size audit — no credential literals remain in the five n8n JSON exports, no sensitive-name files found, and no changed file is 10 MB or larger.
- `php artisan test --compact tests/Feature/ReleaseNotesTest.php` — 3 passed, 23 assertions.
- `git diff --check` — passed.

## 2026-07-18 - Merge Storefront Settings banners into Hero Slides (v1.20.0)

Reason:

- Owner flagged the duplication: Hero Slides and the Storefront Settings "Banner images" section were two parallel homepage-banner systems (slides = full-width scheduled hero with text/CTA; banners = fallback side-card with product links, only shown when no slides existed). Merged into Hero Slides as the single system.

Changed files:

- `database/migrations/2026_07_18_120000_merge_storefront_banners_into_slides.php` (new) — adds `storefront_slides.product_id` FK; converts existing banner images (desktop+mobile paired by position, product tags kept, dropped tags for deleted products) into slides for companies without slides; drops `banner_images`/`banner_images_mobile`.
- `app/Models/StorefrontSlide.php` — `product_id` fillable + `product()` relation.
- `app/Models/StorefrontSetting.php` — banner fillables/casts and `bannerSlides()` removed.
- `app/Filament/Resources/StorefrontSlides/StorefrontSlideResource.php` — company-filtered "Link to product" select (company select now live).
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — banner repeaters + `bannerRepeater()` removed (pointer note to Hero Slides); readiness check "Banner uploaded" → "Hero slide added" (active slide exists).
- `resources/views/storefront/home.blade.php` — slide image now clickable (CTA URL wins, else product page); banner fallback removed. `resources/views/storefront/partials/banner-carousel.blade.php` deleted. `layout.blade.php` og:image now from the first active slide.
- `tests/Feature/StorefrontBannerTest.php` — rewritten around slides (4 tests). `ReleaseNotesTest` bumped to v1.20.0.

Also in this batch — WooCommerce sync gaps (owner-reported: only simple base products, missing info):

- `app/Services/WooCommerceImportService.php` — variable products now fetch and upsert their variations as `ProductVariant` rows (SKU or options-signature match; stock never imported); full `description` preferred over `short_description`; extra images → `gallery_images` (first sync only); `Brand` attribute → `brand`; parent gets `has_variants` + `variant_attributes`.
- `tests/Feature/WooCommerceImportTest.php` — new variable-product test (variations created with options/prices, brand + full description, re-sync doesn't duplicate).
- Owner should re-run "Sync WooCommerce" on the live store after deploy to backfill variations/descriptions/galleries.

Also in this batch — homepage redesign + mobile hero fix (owner-reported: mobile banner cropped; wants MoveOn-style homepage):

- `resources/views/storefront/home.blade.php` — hero stage is `aspect-[3/4]` on phones when any slide has a mobile image (uncropped portrait banners), else 16:9; round category icon scroller under the hero (replaces the old big category cards); featured grid now 2-col on mobile; "Explore more products" dense grid (products 9-23); steps section moved to the bottom; brand-colored "Ready to order?" CTA band before the footer.
- `HomeController`/`PreviewController` — homepage products 12 → 23.
- "How to order" steps → 4 cards with the same 3D gradient badges (violet search / blue cart / green clipboard / amber package); number chips removed, replaced by a looping sequence animation (card lift + badge pulse + halo ping per step, desktop gradient progress line with glowing runner dot), IntersectionObserver-gated (paused until scrolled into view) and prefers-reduced-motion aware; 2-col mobile, 4-col desktop.
- Trust strip redesigned as three premium cards with 3D-style gradient icon badges (glossy highlight, colored glow; truck/return-arrow/banknotes SVGs) — texts stay admin-managed.
- Verified in the browser preview (mobile 375px + desktop): hero 375x500 with the mobile image, all sections render, no horizontal overflow, no console errors.

Also in this batch — admin-managed header/footer navigation menus (owner request):

- `database/migrations/2026_07_19_100000_add_menus_to_storefront_settings.php` (new) — nullable JSON `header_menu` + `footer_menu` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillables + array casts.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — "Navigation Menus" section with two reorderable repeaters (`menuRepeater()`); item = label + link type (shop/category/page/track/account/reseller/custom URL) + company-scoped category/page selects + open-in-new-tab toggle.
- `resources/views/storefront/layout.blade.php` — `$resolveMenu` closure (batched category/page slug lookups, preview-aware URLs, skips broken/empty items); desktop nav renders the header menu (Categories dropdown always kept; defaults Shop all/Track/Account when menu empty); new mobile hamburger drawer (menu links + category grid, icon swap + aria-expanded); footer "Quick links" column from footer menu (falls back to the automatic published-pages list).
- `tests/Feature/StorefrontMenuTest.php` (new) — 3 tests: all link types resolve in the header (custom URL new-tab, defaults suppressed), footer menu replaces the auto pages list, broken/empty items skipped with defaults restored.

Also in this batch — redesigned printable invoice + advanced invoice settings (owner request: match the Zamzam International invoice PDF, dynamic, multi-page):

- `resources/views/orders/print.blade.php` — full redesign matching the sample: centered company name + hotline header with logo left; Bill To left / Code 128 barcode + Invoice No / Delivery Partner (latest courier booking's provider) / Date right; bordered item table (SL, Image, Item Name, Weight, Unit Price, Qty, Amount); Sub Total → optional Discount/VAT/Delivery Charge/Paid → Grand Total → black Due Amount bar; contact block (Facebook/email/website/address); gray contact strip (footer hotline · Facebook page · WhatsApp); thank-you banner; dashed scissor cut-line + courier slip (mini header, Bill To, barcode, black due chip). Multi-page: `thead` repeats per printed page (`table-header-group`), rows/blocks use `page-break-inside: avoid` so the footer blocks + cut-slip flow to the last page.
- `app/Support/Code128.php` (new) — dependency-free Code 128-B inline-SVG barcode generator (checksum per ISO/IEC 15417).
- `app/Services/CompanySettingsService.php` — `INVOICE_DEFAULTS`, `invoice()`, `saveInvoice()` (stored per company in `companies.settings['invoice']`).
- `app/Filament/Pages/CompanySettings.php` + `resources/views/filament/pages/company-settings.blade.php` — new "Invoice Settings" card: header/footer hotlines, Facebook URL+label, WhatsApp, website, thank-you message, toggles (image column, weight column, barcode, cut-slip).
- `database/migrations/2026_07_19_120000_add_weight_kg_to_products.php` (new) + `Product` model + Product form — optional `weight_kg`; WooCommerce sync now imports Woo `weight`.
- `routes/web.php` — print route also loads `latestCourierBooking.provider` and passes invoice settings.
- `tests/Feature/InvoiceDesignTest.php` (new, 4 tests) — barcode/weight/delivery-partner/cut-slip render, toggles hide sections, Code128 validity, per-company settings. `CompanySettingsTest` invoice assertions updated to the new markup.
- Verified in browser: barcode SVG ×2 (invoice + slip), totals block, contact strip, thank-you, cut-slip all render on demo order MAIN-20260717-0002.

Also in this batch — storefront primary-color fix (owner-reported, with screenshots: header/footer buttons weren't using the configured brand color):

- Root cause: the site-wide "primary" CTA button pattern (`bg-gray-950 ... hover:bg-[var(--storefront-brand)] ... dark:bg-white dark:text-gray-950`) only ever showed the actual configured brand color (`--storefront-brand`, e.g. Main Company's `#0a68f5`) on `:hover` — the resting state was always black in light mode and white in dark mode, so the header WhatsApp button, Buy Now, Add/Update cart, checkout submit, track-order, reseller-apply, and category-filter chips never visibly matched the footer's already-correct brand-colored "Chat on WhatsApp" button.
- Changed every solid CTA button/chip across `layout.blade.php` (header WhatsApp + Cart hover border), `home.blade.php` (hero CTA), `products/index.blade.php` (category filter chips), `products/show.blade.php` (Buy Now, desktop + mobile sticky bar), `cart/show.blade.php` (Update, Browse products, Continue to checkout, disabled placeholder), `checkout/show.blade.php` (Place order), `checkout/success.blade.php` (Track this order + Continue shopping hover), `account/orders.blade.php` (Show orders, Track order), `reseller/apply.blade.php` (Submit), `track/show.blade.php` (Track order), and `partials/product-card.blade.php` (quick add-to-cart hover) to use `bg-[var(--storefront-brand)]` as the resting background with a simple `hover:opacity-90`, removing the black/white swap. Secondary/outline actions (Add to cart, Ask on WhatsApp) were left as neutral outline buttons to keep the primary/secondary hierarchy.
- Verified in the browser against Main Company's real configured brand color (`#0a68f5`): header WhatsApp button, Buy Now, Continue to checkout, and category chips all render solid blue at rest in both light and dark mode (previously only visible on hover). `php artisan test --filter=Storefront` — 58 passed (283 assertions), no regressions. `npm run build` succeeds.

Also in this batch — Contact Us page + professional storefront pages template (owner request: build About/Contact/Terms/Privacy/Return & Refund/Advance Payment pages to a "high professional design", referencing a MoveOn Contact page screenshot):

- New `App\Http\Controllers\Storefront\ContactController` + `resources/views/storefront/contact/show.blade.php` (bespoke, matching the reference): brand-gradient hero; four method cards (Email Us, Chat on WhatsApp, Help Center → on-page FAQ anchor, Call Us) each auto-hidden when its underlying data isn't configured; "Our Location" (company address + "Find on Map" Google-Maps-search link, hidden with no address); FAQ accordion reusing the existing `CompanyFaq` list (Settings → CRM → FAQs) — no separate FAQ content to maintain; "Still Have Questions?" CTA (WhatsApp, falling back to email). Routes `storefront.contact` (`/contact`) and `storefront.preview.contact`.
- `database/migrations/2026_07_19_130000_add_contact_fields_to_storefront_settings.php` (new) — `contact_email`, `contact_hours` on `storefront_settings` (everything else on the Contact page reuses fields that already existed: `whatsapp_number`, `phone_number`, `Company::address`). New "Support email"/"Support hours" fields added next to WhatsApp/phone in `StorefrontSettingResource`.
- Generic admin-authored pages (`storefront_pages` — About, Terms & Conditions, Privacy Policy, Return & Refund Policy, Advance Payment Policy, or any custom page) got a full template redesign in `resources/views/storefront/pages/show.blade.php`: breadcrumb, optional cover image banner, "Last updated" date, and a "Still have questions? Contact us" box linking to the new Contact page. `database/migrations/2026_07_19_140000_add_cover_image_to_storefront_pages.php` (new) — nullable `cover_image`; `StorefrontPageResource` gets a `FileUpload` for it (WebP-optimized, same pipeline as Hero Slides) and its `content` field switched from a plain textbox to Filament's `RichEditor` (headings, bold, lists, links, tables), rendered through `RichContentRenderer` (auto-sanitized HTML). Existing plain-text pages (no `<` in the stored content) keep rendering through the old paragraph-per-line path, so nothing needed re-entering.
- New `.storefront-richtext` CSS block in `resources/css/app.css` styles the rich content (headings/lists/links/blockquote/table) — no `@tailwindcss/typography` package added, hand-written to match the existing "no new JS/CSS dependency unless necessary" pattern.
- Footer "Contact" column gets a new "Contact us" link to the new page (owner note: the demo/local database already has an older generic `storefront_pages` record with slug `contact` from earlier demo seeding — it still works at `/pages/contact` and doesn't collide with the new `/contact` route, but the owner may want to unpublish or repurpose it to avoid two different "Contact" links).
- `tests/Feature/StorefrontContentPagesTest.php` (new, 4 tests): Contact page renders all cards/location/FAQ when configured; Contact page gracefully hides every optional section when nothing is configured (only the hero remains); a rich-HTML page renders its cover image, formatted content, and the Contact CTA; a legacy plain-text page (pre-existing content, no HTML) still renders exactly as before.
- Verified in the browser (Main Company, temporary local-only sample address/email/hours/2 FAQs, reverted afterward — no fabricated business data was left in the database): hero gradient, floating cards, gradient icon badges, location card, FAQ accordion (Alpine `x-show`/`x-transition`, no new JS dependency), CTA band all render correctly in light and dark mode, desktop and mobile (375px); an existing demo About page (plain-text legacy content) renders breadcrumb + "Last updated" + Contact CTA correctly through the legacy path.
- Full `php artisan test` — 327 passed (1421 assertions). `npm run build` succeeds (CSS 74.5KB → 78.4KB gzip 12.7KB, no new JS dependency).

Also in this batch — invoice A4 size + footer pinned to bottom of the last page (owner request):

- `resources/views/orders/print.blade.php` — `.invoice` is now sized to an exact A4 sheet (`--page-width: 210mm`, `--page-height: 297mm` CSS variables, matching the existing `@page { size: A4 }`) as a flex column. The contact strip, thank-you note, and courier cut-slip are grouped in a new `.invoice-footer` wrapper with `margin-top: auto`, which pushes that whole group flush against the bottom padding of the page instead of floating right under the totals table on a short invoice. Print media query switches `.invoice` to `width: 100%` / `min-height: calc(page height - 2 × page margin)` (the actual printable area within `@page`'s own 10mm/12mm margins); the small-screen media query reverts to a fluid `width: 100%` so phone viewing isn't forced to A4 proportions.
- Verified with the dev server + browser: logged in as `demo@example.com`, opened `/admin/orders/1/print`, and read the computed layout via `getBoundingClientRect()` — `.invoice` renders at 793.7px × 1122.5px (= 210mm × 297mm at 96dpi) and the footer's bottom edge lands exactly at the page's bottom padding, confirming the footer is flush to the bottom for a normal (single-page) invoice. All demo orders only have 2 items each, so a real multi-page invoice couldn't be observed from existing data; injected extra rows client-side (not saved) to confirm the known limitation: once an order's items overflow onto a second printed page, the footer follows immediately after the last row instead of being pinned to the second page's bottom — no browser's print engine supports CSS running-footers/paged-media, so this can't be solved with pure CSS. This matches the invoice's pre-existing behavior for long orders and isn't a regression; it's just not literally "always" for the rare multi-page case.
- `php artisan test --filter="InvoiceDesignTest|CompanySettingsTest|PhaseThreeAdminPagesTest"` — 14 passed (83 assertions). Full `php artisan test` — 327 passed (1421 assertions). No frontend build needed (invoice page isn't part of the Vite pipeline — plain `<style>` tag).

Also in this batch — storefront Web Interface Guidelines audit + fixes (owner asked to install `vercel-labs/agent-skills@web-design-guidelines` and run it against the storefront):

- Reviewed all 13 `resources/views/storefront/**/*.blade.php` files against Vercel's Web Interface Guidelines (fetched live from `vercel-labs/web-interface-guidelines`), then fixed every finding the owner asked to fix:
  - `layout.blade.php`: skip-to-content link (`#main-content`); Categories dropdown rebuilt with Alpine (`x-data="{ open: false }"`, `@click`, `:aria-expanded`, `@click.outside`, Escape-to-close) instead of CSS `:hover`-only, so keyboard users can reach it; `color-scheme` set on `<html>` alongside the existing dark-mode toggle, plus light/dark `<meta name="theme-color">`; a conditional `<link rel="preconnect">` for the "public" storage disk's own host (only emitted when it differs from the request host — so it stays silent on local disk, activates automatically once R2 is turned on); `padding-bottom: env(safe-area-inset-bottom)` on the fixed mobile bottom nav; `aria-label`/`aria-hidden` on the icon-only call button; a small shared `data-confirm` submit-guard script (any form with `data-confirm="..."` now shows a native confirm dialog before submitting).
  - `home.blade.php`: added an `sr-only <h1>` inside the hero-slides branch (the visible slide heading is only an `<h2>`, so the homepage previously had zero `<h1>` elements whenever slides were configured — the common case in production); `text-balance` on both hero headings (slide heading + no-slides fallback heading) since they're admin-controlled, variable-length text.
  - `products/show.blade.php`: `fetchpriority="high"` on the main gallery image (the page's likely LCP element); the two quantity `<input>`s (main + per-variant) had `outline-none` with no focus replacement at all — added `focus:ring-1 focus:ring-[var(--storefront-brand)]` back; `[touch-action:manipulation]` on the four stepper +/- buttons; safe-area padding on the mobile sticky "Buy now" bar.
  - `partials/product-card.blade.php`: the icon-only quick-add button had `title` but no `aria-label` — added `aria-label="Add {{ product name }} to cart"`.
  - `cart/show.blade.php`: the "Remove" line-item form now carries `data-confirm="Remove {{ product }} from your cart?"` (destructive action, previously fired with no confirmation); stepper touch-action.
  - `checkout/show.blade.php`: name/phone/email/address fields gained `autocomplete`, phone got `type="tel"`, email got `spellcheck="false"`; the submit button now disables itself and shows "Placing order…" on submit (guards against double-order from a double-click); a `beforeunload` guard warns before leaving the page once any field has been typed into; the Alpine-computed delivery-charge/total spans got `aria-live="polite"` so screen readers hear the updated total when the delivery-area radio changes; the order-summary line item name got `min-w-0 truncate`/`shrink-0` so a long product name can't push the price off the row.
  - `account/orders.blade.php`, `reseller/apply.blade.php`, `track/show.blade.php`: phone inputs standardized to `type="tel"` + `autocomplete="tel"` (a couple were plain `type="text"` with no autocomplete); reseller's name field got `autocomplete="name"`.
  - `contact/show.blade.php`: the `#faq` anchor (jump target from the "Help Center" card) got `scroll-mt-24` so it no longer lands underneath the sticky header when clicked.
  - `contact/show.blade.php`, `pages/show.blade.php`, `track/show.blade.php`: straight apostrophes in visible copy ("Can't", "you're", "couldn't") replaced with curly ones (`&rsquo;`).
  - Deliberately **not** changed (called out to the owner as low-value churn, not part of the fix pass): rewriting `number_format()`/Carbon date formatting to some `Intl`-equivalent, and wrapping every `{{ $company->name }}` in `translate="no"` — both are guideline line-items but disproportionate to rewrite across a single-locale (Bangladesh) storefront for a marginal, unconfirmed benefit.
- Verified in the browser (Main Company preview, logged in as `demo@example.com`): exactly one `<h1>` on the homepage (confirmed via `document.querySelectorAll('h1').length === 1`), Categories dropdown opens on click with `aria-expanded` toggling `true`/`false`, quantity input keeps its `focus:ring` class, main product image has `fetchpriority="high"`, quick-add buttons show descriptive `aria-label`s, theme-color metas and colorScheme both present — no console errors on any page checked.
- `php artisan test --filter=Storefront` — 62 passed (321 assertions). Full `php artisan test` — 327 passed (1421 assertions), no regressions. `npm run build` succeeds (CSS 78.35KB → 78.79KB for the new `text-balance`/`scroll-mt-24`/arbitrary `[touch-action:manipulation]` classes).

Also in this batch — header search bar + customer accounts (owner request: "header এ সার্চ বার নাই, মডার্ণ সার্চ বার যুক্ত কর" + a customer profile icon with login/profile options, dynamic, with admin-panel control where needed):

- Confirmed there was previously **no storefront customer authentication at all** — "Account" only meant an anonymous phone-number order lookup (`MatchesCustomerPhone`). Building a real profile icon meant building real login/registration first; asked the owner how customers should sign in (phone+OTP vs phone/email+password vs keep guest-only) before writing any of it — chose **phone or email + password**, owner's explicit pick.
- `database/migrations/2026_07_20_025125_add_auth_fields_to_customers_table.php` (new) — `password`, `remember_token`, `password_reset_code`, `password_reset_expires_at` on `customers` (all nullable; a null `password` still means "not a login account", same as any admin/checkout-created customer today). `database/migrations/2026_07_20_025128_add_customer_accounts_enabled_to_storefront_settings_table.php` (new) — `customer_accounts_enabled` boolean, default on.
- `app/Models/Customer.php` — implements `Authenticatable` (new `customer` guard in `config/auth.php`, provider model `Customer`); `password`/`remember_token`/`password_reset_code` hidden; new `isRegistered()` helper. No new model, so no `MultiCompanyIsolationTest` change needed — `Customer` was already `BelongsToCompany`-scoped.
- New `App\Services\CustomerAccountService`: `register()` reuses an existing phone-matched Customer row (so a walk-in/checkout customer who later signs up keeps their order history instead of getting a second, empty account) and blocks re-registration on a phone/email a *registered* account already uses; `attemptLogin()` accepts a phone or an email in one field; `sendPasswordResetCode()`/`resetPassword()` implement a 6-digit SMS code (hashed at rest, 15-minute expiry) reusing `StorefrontNotificationService::sendSms()` — the same admin-configured gateway abandoned-cart reminders already use. New `StorefrontNotificationService::smsConfigured()` helper.
- New `App\Http\Controllers\Storefront\AccountAuthController` (login/register/logout/forgot-password/reset-password) and `AccountProfileController` (profile view/update, password change), new routes under `/account/login`, `/account/register`, `/account/forgot-password`, `/account/reset-password`, `/account/profile`, `/account/password`, `/account/logout` (login/register/forgot-password throttled). `AccountOrdersController` now shows a logged-in customer's orders immediately — no phone re-entry — while the existing guest phone-lookup keeps working unchanged for anyone who doesn't create an account.
- New views: `storefront/account/login.blade.php` (single page, Alpine-tabbed Login/Create account), `forgot-password.blade.php`, `reset-password.blade.php`, `profile.blade.php` (name/email/address edit, password change, reseller-status/apply link, member-since).
- `layout.blade.php`: new modern search bar (desktop inline between nav and the icon cluster; a persistent full-width row on tablet/mobile) submitting to the products page (`?q=`, kept through sort/pagination); new profile-icon dropdown in the header icon cluster (guest: Log in / Create account / Track an order / Find my orders; logged in: name+phone header, My profile, My orders, Track an order, Become a reseller/Reseller status, Log out) built the same click+keyboard-accessible Alpine pattern as the existing Categories dropdown; mobile bottom-nav "Account" icon and the mobile menu's account line now point at login vs. profile depending on auth state. Everything is skipped/falls back to the old guest-only links when `customer_accounts_enabled` is off or in the admin storefront preview (customer login only exists on a real domain).
- `ProductIndexController`/`PreviewController::products()` — new `q` param (`name`/`sku` `LIKE`), `products/index.blade.php` shows a "Results for '…'" heading and a no-matches empty state, and the sort dropdown now keeps the search term via a hidden field.
- `checkout/show.blade.php` — name/phone/email/address now pre-fill from the logged-in customer (still fully editable, `old()` still wins on a validation error).
- `StorefrontSettingResource` — new "Enable customer login & registration" toggle (Storefront Publishing section) so the owner can turn the whole feature off per company and fall back to guest-only phone lookup.
- New `tests/Feature/StorefrontCustomerAuthTest.php` (10 tests): register→auto-login, wrong-password rejection, registration reuses an existing unregistered Customer row instead of duplicating, duplicate-phone registration blocked, profile info + password change (wrong current password rejected), forgot/reset-password full round trip (SMS code captured via `Http::fake` and used to reset), forgot-password's honest "not available" message when no SMS gateway is configured, `customer_accounts_enabled=false` 404s the login/register routes, cross-company isolation (same phone+password on a different company's domain does not log in), product search filters correctly.
- Full `php artisan test` — 337 passed (1484 assertions), no regressions. `npm run build` succeeds. Browser-verified live on a domain-routed company (Main Company's `domain` temporarily pointed at `localhost` for testing, reverted after): search returns correct results and keeps the query through sort; registered, was auto-logged-in and redirected to a pre-filled profile; header icon showed the "R" initial avatar; checkout pre-filled name/phone/email; logged out (redirected to login on visiting the profile page); logged back in with the **email** instead of the phone. Test customer row and the temporary domain change were both cleaned up afterward — no fabricated data left in the demo database.

Also in this batch — removed the header announcement bar (owner request):

- `layout.blade.php` — dropped the "Official storefront - live catalog, direct ordering" strip that sat above the header on every storefront page (unrelated to the "Official storefront" hero eyebrow tag on the homepage, which stays). `StorefrontFoundationTest`'s assertion on that exact string was removed. `php artisan test --filter=Storefront` — 72 passed (383 assertions). `npm run build` succeeds.

Also in this batch — `/web-design-guidelines` review of the login/register/logout profile-icon flow (owner re-described the exact behavior already built, invoked via the design-guidelines skill; reviewed the account auth views specifically rather than treating it as a new feature request):

- `account/login.blade.php:43,72` (confirmed in-browser, not just read from the code): `autofocus` on both the login and register panels' first field silently did nothing — `x-cloak` hides both tab `<div>`s (`display:none`) at initial parse, and the browser's one-time autofocus pass skips elements that aren't focusable at that moment; by the time Alpine boots and reveals the correct panel, autofocus has already been processed and discarded. Verified with `document.activeElement` — was `<body>` on both `/account/login` and `/account/register` before the fix. Fixed by replacing the native attribute with `x-init="$nextTick(() => ...)"` + `x-ref` on each panel's first field, focusing it once Alpine has settled — and now also re-focuses the newly-shown panel's field when the user clicks between the two tabs (previously nothing happened on tab switch either).
- `account/login.blade.php:12-32` — the Login/Create account toggle used `role="tab"`/`role="tablist"`/`aria-selected`, i.e. real ARIA Tabs semantics, but the panels had no matching `role="tabpanel"`/`aria-controls`, and there was no arrow-key navigation — a half-implemented pattern is worse than none, since screen readers announce "tab" then keyboard behaviour doesn't match. Simplified to what the widget actually is: a two-option toggle (`role="group"` + `aria-pressed` on each button), which is fully correct without needing a keyboard model this simple toggle doesn't need.
- `account/login.blade.php:92`, `reset-password.blade.php:30`, `profile.blade.php:66` — the three password-creation fields (register, reset, change-password) enforce `minlength="8"` but never showed that requirement as visible text; added "At least 8 characters." under each, wired via `aria-describedby`.
- `php artisan test --filter=Storefront` — 72 passed (383 assertions), no regressions. `npm run build` succeeds. Browser-verified the autofocus fix directly (`document.activeElement.id` is now `identifier` on `/account/login` and `name` on `/account/register`) and the toggle buttons' `aria-pressed` state.

Deploy: `php artisan migrate` **and `npm run build`** required (new Tailwind classes + JS-independent Alpine markup, no new JS bundle dependency).

Commit status: pending owner approval.

## 2026-07-18 - Automatic image optimization (WebP) + R2 storage groundwork

Reason:

- Pre-existing WIP found in the working tree (image optimizer wired into forms but its composer packages were never installed — uploads would have fataled). Owner asked to complete it.

Changed files:

- `app/Services/ImageOptimizerService.php` + `app/Filament/Concerns/OptimizesUploadedImages.php` (new, were untracked) — resize + EXIF-strip + WebP re-encode on upload; SVG/animated-GIF passthrough.
- Form opt-ins (were already edited): ProductForm (featured + gallery), CategoryForm, CompanyResource logo, StorefrontSettingResource logos/banners, StorefrontSlideResource images.
- `composer.json` + `composer.lock` — `intervention/image` ^3.9 and `league/flysystem-aws-s3-v3` ^3.25 now actually installed (lock updated; this was the missing piece).
- `config/filesystems.php` + `.env.example` — inactive `r2` disk + `R2_*` vars (activation needs a Cloudflare bucket/token from the owner; no app code references the disk yet).
- `tests/Feature/ImageOptimizerTest.php` (new) — 5 tests: resize to 1600px cap, no upscaling, 800px compact cap, SVG untouched, GIF untouched.
- `CHANGELOG.md` [1.19.0]; `ReleaseNotesTest` bumped to v1.19.0 / 2026-07-18.

Also in this batch — Chat Channel not saving on live:

- Root cause: with "All Companies" selected, `BelongsToCompany` falls back to the default company on create, so the channel saved under "Main Company" and never showed under the owner's real company; retrying then hit the global `provider+external_id` unique index as a 500. Reproduced/ruled out DB issues against a scratch MySQL 8.4 database (model-level creates succeed, encrypted casts fine).
- `app/Filament/Resources/ConversationChannels/ConversationChannelResource.php` — required Company selector visible only in All-Companies mode (Courier Provider pattern); Company column in the table in that mode; `unique` validation on external_id scoped to provider with a human-readable message.
- `tests/Feature/ConversationChannelResourceTest.php` (new) — 3 tests.

Verification: `ImageOptimizerTest` 5 passed (13 assertions) — real GD WebP encode through Livewire's temporary-upload path; GD WebP support confirmed on this machine; r2 disk config shape validated with throwaway creds. Full `php artisan test` — 315 passed (1318 assertions) including the Chat Channel fix below.

Deploy notes: run `composer install` on the live server after pulling; PHP GD extension with WebP support required (standard on most hosts). No migration.

Commit status: Not committed yet — awaiting owner approval.

## 2026-07-17 - Chat-order UX polish, WhatsApp Business-style Inbox, realtime chat, catalog with images, app pull-to-refresh

Reason:

- Owner's live testing feedback, batch 1: (a) random "Error while loading page" toast in the app's Inbox; (b) order links in chat bubbles were plain text; (c) thank-you page needed a back-to-inbox button; (d) order form/thank-you pages should feel premium/modern; (e) the Capacitor app has no way to reload a page.
- Batch 2: (f) Inbox should feel like WhatsApp Business (desktop + mobile) while keeping Filament UI; (g) catalog sending with product images; (h) sending an order form closed the open chat back to the empty state; (i) sent messages took ~10s (poll) to appear; (j) order form quantity change didn't recalculate the total; (k) new messages didn't surface/scroll like WhatsApp.

Changed files:

- `resources/views/chat-order/{layout,show,success,closed}.blade.php` — full modern redesign (Hind Siliguri font, gradients, animated checkmark, dark mode); success/closed pages show "ইনবক্সে ফিরে যান" for logged-in staff, "ফিরে যান" (history back) for customers; `show` adds product image thumbnails + vanilla-JS live grand-total recalculation on quantity change.
- `resources/views/filament/pages/inbox.blade.php` — WhatsApp Business-style rewrite: avatars + last-message previews + unread pills in the list, mobile list ⇄ full-screen thread with back button, chat wallpaper, date separators, delivery ticks, image bubbles, auto-scroll/follow-scroll (Alpine + MutationObserver), pill composer (Enter sends, auto-grow), "+" catalog panel with image preview; `wire:poll.visible.5s`; root `data-zz-no-reload`.
- `app/Filament/Pages/Inbox.php` — `deselectConversation()` (mobile back), catalog panel state, order-form message now carries product name/price + image; scroll-bottom dispatches after send/select.
- `app/Models/ConversationMessage.php` — `bodyHtml()` linkify helper + new `mediaImageUrl()`.
- `app/Models/Conversation.php` — `latestMessage()` `latestOfMany` relation for list previews.
- `app/Services/Crm/ConversationMessengerService.php` — optional media URL: WhatsApp image+caption message, Messenger image then text; archives `media_path`/`media_mime`.
- `app/Providers/Filament/AdminPanelProvider.php` — Chrome-style pull-to-refresh script (Capacitor/Android WebView only); `notificationsSent` reload now skips pages with `data-zz-no-reload` (fixes the Inbox closing after "Order link sent.").
- `tests/Feature/InboxPageTest.php` — new (reply archiving + state kept, catalog image on link/message, `mediaImageUrl()` resolution).
- `CHANGELOG.md` [1.18.0]; `ReleaseNotesTest` at v1.18.0.

Verification: `ChatOrderLinkTest|ConversationIngestTest|AiAutoReplyTest` (24 passed) + new `InboxPageTest` (3 passed); `view:cache` compiles clean; browser-verified locally: live total recalculates instantly (qty 2→5: ৳4,400→৳11,000), product image renders on the order form. Full `php artisan test` — 307 passed (1282 assertions). Smoke-test data removed from the demo DB.

Commit status: Committed and pushed with owner approval on 2026-07-18 (`859eed09`, v1.18.0).

## 2026-07-17 - Hotfix: orders.status enum breaks order creation on MySQL (live 500)

Reason:

- Owner reported: submitting the chat order form (`/o/{token}`) on the live server returns 500, while local works. Root cause: `orders.status` is still the original `enum('pending','processing','completed','cancelled')` from the 2026-05-25 migration; the app writes `'draft'`/`'confirmed'`. SQLite (local) ignores enum constraints; MySQL strict mode (production per `docs/deployment.md`) rejects the insert.

Changed files:

- New migration `database/migrations/2026_07_17_030000_change_orders_status_to_string.php` — `orders.status` → `string(20)` default `draft`.
- `CHANGELOG.md` [1.17.1] hotfix entry; `ReleaseNotesTest` bumped to v1.17.1.

Verification: full `php artisan test` — 304 passed (1266 assertions).

Deploy notes: run `php artisan migrate` on the live server after pulling. If the 500 persists afterwards, check `storage/logs/laravel.log` for the actual exception.

Commit status: Committed and pushed with owner approval on 2026-07-17 (`86879f4c`, v1.17.1). Confirmed fixed on live: order ZZIN-20260717-0001 succeeded.

## 2026-07-17 - Lead/CRM module (steps 1–14), banners, single-column admin layout

Reason:

- Owner attached `02_LEAD_CRM_MODULE_PLAN.md` and asked to implement it step by step. All plan steps (1–11, 13–14) are done: Lead/Quotation core, Conversation Inbox + click-to-order links, and the AI auto-reply assistant with CTWA 72h window. Also includes the earlier pending multi-image product-taggable storefront banners and the app-wide single-column Filament form layout.

Changed files (high level):

- New migrations: `2026_07_16_090000` (banner tags), `2026_07_17_000000` (lead/quotation tables), `2026_07_17_010000` (conversations/chat-order tables), `2026_07_17_020000` (AI + CTWA fields, `company_faqs`).
- New models: Lead, LeadActivity, Quotation, QuotationItem, ConversationChannel, Conversation, ConversationMessage, ChatOrderLink, CompanyFaq (+ Customer/Order/StorefrontSetting additions).
- New services: `app/Services/Crm/` (LeadConversionService, ConversationMessengerService, AiSettingsService, AiLlmClient, AiReplyService); jobs StoreIncomingMessageJob, DownloadConversationMediaJob, AiAutoReplyJob; command `quotations:mark-expired`.
- Controllers/routes: MetaWebhookController (`/webhooks/meta`, CSRF-exempt), QuotationPublicController (`/quotation/{number}`), ChatOrderController (`/o/{token}`).
- Filament: Leads, Quotations, ConversationChannels, CompanyFaqs resources; Inbox + AI Assistant Settings pages; 37 existing form/infolist files switched to single-column sections.
- Views: `resources/views/quotations/`, `chat-order/`, `filament/pages/{inbox,ai-assistant-settings}.blade.php`, storefront `banner-carousel` partial + home/layout updates.
- Tests: LeadTest, LeadConversionTest, QuotationTest, ConversationIngestTest, ChatOrderLinkTest, AiAutoReplyTest, StorefrontBannerTest; isolation contract extended; ReleaseNotesTest bumped to v1.17.0.
- Docs: `CHANGELOG.md` [1.17.0]; `PROJECT_GUIDE.md` Lead/CRM section; plan file status marks.

Verification: full `php artisan test` — 304 passed (1266 assertions). Browser-verified: Leads/Quotations pages, Inbox (Filament-styled), manual conversation → order link → public form → order created, AI Assistant Settings and FAQs pages. Smoke-test data cleaned from the demo DB afterwards.

Deploy notes: run `php artisan migrate`; a queue worker must be running (webhook ingest + AI replies are queued); scheduler already required (`quotations:mark-expired` daily 00:30). Meta webhook URL + verify token per channel are set in the new Conversation Channels resource; AI provider key in AI Assistant settings — all encrypted, admin-configurable.

Commit status: Committed and pushed with owner approval on 2026-07-17.

## 2026-07-15 - WooCommerce sync button

Reason:

- Owner reported that saving WooCommerce Consumer Key/Secret/Site URL in Storefront Settings did nothing — the import only ever ran via `php artisan woocommerce:import-products {company-slug}` from a server terminal; there was no admin-panel action to trigger it.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new `syncWooCommerceAction()` / `hasWooCommerceCredentials()` helpers; "Sync WooCommerce" added to the list `recordActions`.
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php` — same action added to the edit page's header actions, so it's reachable from the form the owner was actually looking at.
- `CHANGELOG.md` — new `[1.16.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.16.0.

Verification: full `php artisan test` — 264 passed (1127 assertions). Manually verified in the browser: button stays hidden while a record's WooCommerce fields are empty, appears once a site URL + consumer key + secret are saved, and disappears again once cleared (test credentials were saved and then removed during verification — no import was actually run against a live site).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 4 (Polish) — plan complete

Reason:

- Final phase of `STOREFRONT_REDESIGN_PLAN.md` (Phases 1-3 landed earlier today). Phase 4 covers section 8's polish items: offer countdown, scroll animation, Best Sellers/New Arrivals (already delivered in Phase 1), and a performance budget check.

Changed files:

- New migration `database/migrations/2026_07_14_140000_add_offer_countdown_to_storefront_settings_table.php` — `offer_title`, `offer_discount_percent`, `offer_ends_at` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillable/casts, `hasActiveOffer()` helper.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Offer Countdown" section.
- `resources/views/storefront/home.blade.php` — sitewide flash-sale countdown banner (Alpine-powered live ticking countdown, auto-hides once expired); `x-reveal` scroll-animation attribute added to the category grid, featured products, and each carousel section.
- `resources/js/app.js` — small custom `Alpine.directive('reveal', ...)` (IntersectionObserver-based fade-up, respects `prefers-reduced-motion`, no-ops without `IntersectionObserver` support) — no new dependency.
- Tests: `tests/Feature/StorefrontOfferCountdownTest.php` (new).
- `CHANGELOG.md` — new `[1.15.0]` Minor Feature entry, marking the 4-phase plan complete; `tests/Feature/ReleaseNotesTest.php` bumped to v1.15.0.

Deliberately deferred (flagged, not silently skipped):

- Per-product-scoped offers — the plan's admin table originally described a scoped "Offer/Flash sale" resource; this phase ships a single sitewide countdown banner instead (title + %  + end time on the existing Storefront Settings), which covers the core UX ask without a new table/resource. A per-product scoped version would be its own follow-up.
- A formal Lighthouse audit — no Lighthouse/CI tooling is wired into this repo; verified the build stays within the plan's JS budget and that images already lazy-load with explicit dimensions (from Phases 1-2), but did not fabricate a Lighthouse score without actually running one.

Verification: full `php artisan test` — 264 passed (1127 assertions). `npm run build` — 88.96 kB JS / 32.92 kB gzip (unchanged from Phase 1, still within the plan's <60KB gzip budget — the reveal directive added no new dependency). Manually verified in the browser preview on the demo Main Company storefront: countdown banner ticks live (04h 59m 34s → decreasing), disappears when `offer_ends_at` is in the past, and the `x-reveal`'d sections correctly lose their `opacity-0`/`translate-y-3` classes once scrolled into view. No console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. No new JS dependency.

Commit status: Not committed. Commit and push require explicit owner approval.

**This completes all 4 phases of `STOREFRONT_REDESIGN_PLAN.md`.** Nothing has been committed across any of the 4 phases yet — 15 CHANGELOG-documented versions of work ([1.12.0] through [1.15.0], following [1.11.0] Voucher module and [1.10.0] audit remediation) are sitting in the working tree awaiting review and commit approval.

## 2026-07-14 - Storefront redesign Phase 3 (One-Page Checkout & Payments)

Reason:

- Continuing the phased `STOREFRONT_REDESIGN_PLAN.md` implementation (Phases 1-2 landed earlier today). Phase 3 covers section 5 of the plan (checkout/payments), scoped down after research: the ZiniPay gateway already covers online pre-order payments, and a real bKash/Nagad gateway API integration needs merchant credentials this task doesn't have — so this phase adds delivery-area charges and a manual bKash/Nagad "Send Money + TrxID" flow with admin verification, on top of the existing COD/ZiniPay paths.

Changed files:

- New migration `database/migrations/2026_07_14_130000_add_checkout_settings_to_storefront_settings_table.php` — `cod_enabled`, `delivery_charge_inside/outside`, `manual_bkash_number/instructions`, `manual_nagad_number/instructions` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillable/casts, `cod_enabled` defaults true on create.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Checkout & Delivery" section.
- `app/Http/Controllers/Storefront/CheckoutController.php` — `createOrder()` validates `delivery_area`/`payment_method`/`sender_number`/`trx_id` (all optional, defaulting to `inside`/`cod` so existing callers keep working); computes the delivery charge from the setting and stores it on the order's existing `shipping_zone`/`shipping_fee` fields; creates a `pending` `StorefrontPayment` for manual bKash/Nagad with the sender number and TrxID.
- `resources/views/storefront/checkout/show.blade.php` — redesigned with an Alpine-powered delivery-area toggle and payment-method radio cards (COD / bKash / Nagad, each hidden until the admin configures its number), with a live-updating delivery charge + total in the order summary.
- `resources/views/storefront/checkout/success.blade.php` — manual bKash/Nagad payments get their own "we are verifying your payment" wording, distinct from the existing pre-order online-advance message.
- New Filament resource `app/Filament/Resources/StorefrontPayments/` (Storefront > Storefront Payments) — admin list with Verify/Reject actions for pending manual payments.
- Tests: `tests/Feature/StorefrontManualPaymentTest.php` (new).
- `CHANGELOG.md` — new `[1.14.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.14.0.

Deliberately deferred (flagged, not silently skipped):

- Real bKash/Nagad gateway API (automatic, no TrxID entry) — needs actual merchant API credentials the store doesn't have configured; the existing ZiniPay integration continues to serve the pre-order online-advance use case.
- bn/en `lang/` localization — no `lang/` directory exists in this repo; this is a cross-cutting change touching every storefront view, not something to bolt onto the checkout phase alone.
- Returning-customer address autofill by phone (plan's "phase 2" nice-to-have within section 5) — not started.

Verification: full `php artisan test` — 261 passed (1120 assertions). `npm run build` succeeds (no new JS dependency). Manually verified in the browser preview on the demo Main Company storefront: Buy Now → checkout page shows delivery-area cards and live total; selecting bKash reveals the sender-number/TrxID fields and the configured Send Money number. No console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. Admin must configure delivery charges and/or bKash/Nagad numbers in Storefront Settings before those options appear at checkout (COD works with no configuration, matching prior behaviour).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 2 (Product Page)

Reason:

- Continuing the phased `STOREFRONT_REDESIGN_PLAN.md` implementation (Phase 1 landed earlier today). Phase 2 covers section 4 of the plan (product page). Research first: the gallery, tiered/wholesale pricing table, variant option table, and related-products grid were already built in an earlier session — only the remaining gaps needed work.

Changed files:

- `app/Http/Controllers/Storefront/CartController.php` — `addToCart()` recognizes an optional `buy_now=1` field; when present, redirects to checkout instead of back. New `redirectToCheckout()` helper resolves the live vs. preview checkout route from the request's bound `company` route parameter.
- `resources/views/storefront/products/show.blade.php` — added a "Buy now" button next to "Add to cart" (single-variant products only); added a mobile-only sticky action bar (`fixed bottom-16`, sits above the existing bottom nav) with the same two actions wired to the main form via `form="product-purchase-form"`; moved the product description into a new Alpine-powered Description / Shipping & Return tab section below the buy box.
- Tests: `tests/Feature/StorefrontBuyNowTest.php` (new).
- `CHANGELOG.md` — new `[1.13.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.13.0.

Deliberately deferred (flagged, not silently skipped):

- Specification tab — no key-value spec field exists on `Product`; would need its own migration and admin UI, not bolted onto this pass.
- Import shipping-cost-breakdown panel (plan's optional item for imported products) — no admin-configurable air/sea per-kg rate fields exist yet; `Purchase::CHINA_TO_BD_COST_FIELDS` is a purchasing-side-only concept today with no storefront equivalent.

Verification: full `php artisan test` — 257 passed (1104 assertions). `npm run build` succeeds (no new JS dependency this phase). Manually verified in the browser preview on the demo Main Company storefront (`/storefront/main-company/product/barcode-scanner`): Buy now button, sticky mobile bar, and tab switching all confirmed working (tab switching verified via a dispatched click event after a browser-automation quirk affected a synthetic `.click()` call — Alpine's reactivity and `@click` binding both confirmed correct). No console errors.

Deploy notes: no new migration, no new JS dependency — safe to deploy without a rebuild step beyond the usual `npm run build`.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 1 (Foundation & Home)

Reason:

- Owner asked to implement `STOREFRONT_REDESIGN_PLAN.md`. The plan itself is phased (4 phases) with tests/build/changelog and owner approval before each commit, so this pass covers Phase 1 only: hero slider, category images, trust strip, product card v2, Alpine.js, and home-data caching. Phases 2-4 (product page redesign, one-page checkout, performance polish) are separate follow-up work, not started.

Changed files:

- New migration `database/migrations/2026_07_14_120000_create_storefront_slides_table.php` — `storefront_slides` table, `categories.image`, `storefront_settings.trust_strip_delivery/return/payment`.
- New model `app/Models/StorefrontSlide.php` (`BelongsToCompany`, added to `MultiCompanyIsolationTest`), `activeNow()` scope (is_active + optional start/end window), `forCompany()` cached lookup.
- `app/Models/Category.php` — `image` fillable; cache-bust hook.
- `app/Models/StorefrontSetting.php` — `trust_strip_*` fillable; cache-bust hook.
- New Filament resource `app/Filament/Resources/StorefrontSlides/` (Storefront > Hero Slides).
- `app/Filament/Resources/Categories/Schemas/CategoryForm.php` — image upload field.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Trust Strip" section.
- `app/Http/Controllers/Storefront/HomeController.php` + `app/Http/Controllers/Storefront/PreviewController.php` — pass `slides` to the homepage view.
- `resources/views/storefront/home.blade.php` — Alpine-powered hero slider (autoplay, dots, `prefers-reduced-motion` guard, fetchpriority on the first slide) with graceful fallback to the existing static banner when no slides exist; trust strip section; category cards now show images with a mobile horizontal-scroll row.
- `resources/views/storefront/partials/product-card.blade.php` — discount badge + struck-through compare price when `sale_price < price`; quick-add button no longer hover-only (mobile has no hover); lazy-loaded images.
- `resources/js/app.js` + `package.json` — added Alpine.js.
- `resources/css/app.css` — `[x-cloak]` rule.
- Tests: `tests/Feature/StorefrontSlideTest.php` (new); `tests/Feature/MultiCompanyIsolationTest.php` (StorefrontSlide contract).
- `CHANGELOG.md` — new `[1.12.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.12.0.

Deliberately deferred (flagged, not silently skipped):

- Full Intervention Image/WebP resize pipeline (plan's performance section) — today's image uploads use the same plain `FileUpload` pattern as every other image field in the app; a proper resize/WebP/srcset pipeline is cross-cutting and belongs in its own change, not bolted onto just the new fields.
- Caching of the products/categories homepage queries — only the new slides list is cached (10 min, invalidated on save). Product/category listings change too often (stock, availability) to risk staleness without more design; flagged for a follow-up.
- Flash Sale/offer countdown strip, Best Sellers/New Arrivals as distinct homepage sections beyond the existing `ProductCarousel` mechanism, scroll-reveal animations — plan items 3.3/3.4 nice-to-haves, left for Phase 4 polish.

Verification: full `php artisan test` — 255 passed (1099 assertions). `npm run build` — 88.55 kB JS / 32.75 kB gzip (plan budget: <60KB gzip JS). Manually verified in the browser preview: hero slide, trust strip line, and category image grid all render correctly on the demo Main Company storefront (`/storefront/main-company`); no console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. `npm run build` required (new JS dependency).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Voucher & Fund Control module

Reason:

- Owner asked to implement `05_VOUCHER_FUND_CONTROL_MODULE_PLAN.md`. Two decisions the plan flagged as needing explicit owner confirmation were confirmed before building: (1) existing direct Customer/Supplier Payment and Expense creation stays fully supported alongside vouchers (voucher is optional, not mandatory); (2) `capital_investment` vouchers stay Mudarabah-ready (route through `resulting_model_type`) rather than fully separate, since the Mudarabah investor module doesn't exist yet.

Changed files:

- New migration `database/migrations/2026_07_14_000000_create_voucher_and_fund_control_tables.php` — `fund_sources`, `vouchers`, `voucher_attachments`, `fund_transfers`, plus `purchases.funding_sources` (JSON).
- New models: `app/Models/FundSource.php`, `app/Models/Voucher.php`, `app/Models/VoucherAttachment.php`, `app/Models/FundTransfer.php` — all `BelongsToCompany`, added to `MultiCompanyIsolationTest`'s contract.
- New services: `app/Services/VoucherService.php` (submit/verify/approve/reject/cancel + the transaction-type → accounting-effect matrix, Rule 1 enforced), `app/Services/FundTransferService.php` (double-entry ledger transfer).
- `app/Models/TransactionLedger.php` — added `voucher_credit`/`voucher_debit`/`fund_transfer` ledger types.
- `app/Models/Purchase.php` — `funding_sources` fillable/cast.
- `app/Models/User.php` — new `voucher.*`/`fund_source.manage`/`fund_transfer.*`/`finance.dashboard` permissions, mapped onto existing roles, plus `canX()` helper methods.
- New Filament resources: `app/Filament/Resources/Vouchers/`, `app/Filament/Resources/FundSources/`, `app/Filament/Resources/FundTransfers/` (Verify/Approve/Reject/Cancel/Print Receipt actions).
- Money Receipt: `app/Http/Controllers/Admin/VoucherReceiptController.php` + `resources/views/vouchers/receipt.blade.php`, reached via a signed `vouchers.receipt` route (no login required, signature can't be guessed) — added to `routes/web.php`.
- Tests: `tests/Feature/VoucherWorkflowTest.php`, `tests/Feature/AccountingRulesTest.php` (Rule 1 + fund-transfer + over-funding guard), `tests/Feature/VoucherIsolationTest.php`.
- `CHANGELOG.md` — new `[1.11.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.11.0 / Minor Feature / 2026-07-14.

Deliberately deferred (flagged, not silently skipped):

- Module plan step 9 (automatic voucher creation from Purchase/Expense/SupplierPayment/Order events) — wiring this in without risking a double-booked financial record (a manual voucher plus an auto one for the same event) needs its own careful pass; not rushed into this change.
- Purchase form's funding-sources repeater UI (nice-to-have; the `purchases.funding_sources` JSON column exists and the actual fund deduction/validation already works correctly through per-source `inventory_purchase` vouchers referencing `purchase_id`).
- Threshold-based approval routing and the shared `ApprovalGateService` — that service belongs to the not-yet-built Task/Approval Workflow module; `VoucherService` ships with simple inline approval logic per the plan's own documented fallback, with a comment marking the future migration path.

Verification: full `php artisan test` — 252 passed (1084 assertions). No frontend asset build needed (Filament PHP resources + Blade-only views).

Deploy notes: new migration — run `php artisan migrate` on deploy. No queue/cron/env changes.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-13 - Code audit remediation (security + reliability hardening)

Reason:

- Owner asked to resolve the findings in `CODE_AUDIT_REPORT.md` one by one.

Changed files:

- `app/Models/Concerns/GeneratesSequentialNumber.php` (new) — retries the INSERT on a UNIQUE violation of a document-number column, regenerating the number each attempt. Applied to `Order` and `Purchase` (`app/Models/Order.php`, `app/Models/Purchase.php`). **Audit M-1.**
- `app/Http/Controllers/Storefront/Concerns/MatchesCustomerPhone.php` (new) — shared +880/0/formatting-tolerant customer-phone match.
- `app/Http/Controllers/Storefront/OrderTrackController.php` + `resources/views/storefront/track/show.blade.php` — order tracking now requires a matching phone as a second factor (order number alone is guessable). **Audit M-2.**
- `app/Http/Controllers/Storefront/AccountOrdersController.php` + `resources/views/storefront/account/orders.blade.php` — removed the customer outstanding-balance figure from the phone-only history page; refactored the phone match onto the shared trait. **Audit M-3.**
- `app/Scopes/CompanyScope.php` — documented the context contract (none=fail-closed, all/cleared=unscoped). Runtime behaviour intentionally unchanged (storefront guest binding depends on cleared=unscoped + ownership checks). **Audit M-4.**
- `app/Services/StockMovementService.php` — stock recompute now sums signed quantity in SQL instead of loading all movements into PHP. **Audit L-3.**
- `app/Services/StorefrontCart.php` — named `PREORDER_STOCK_CEILING` constant (**L-4**); replaced inline FQ class refs with imports (**L-6**).
- `app/Models/CustomerBlacklist.php` — documented the deliberate `CompanyScope` omission. **Audit L-2.**
- `config/app.php` + `app/Support/AdminPassword.php` — seeder admin password now read via `config('app.seed_admin_password')` not raw `env()`. **Audit L-6.**
- `.env.production.example` (new), `docs/deployment.md`, `.env.example` — production hardening guidance: MySQL/Postgres not SQLite, non-sync queue + worker, `APP_ENV=production`/`APP_DEBUG=false`. **Audit H-1, H-2, L-1** (deploy/ops; app defaults unchanged by design).
- Tests: `tests/Feature/SequentialNumberConcurrencyTest.php` (new, M-1), `tests/Feature/OrderFormTest.php` (new, L-5 form-layer), `MultiCompanyIsolationTest::test_company_context_boundary_states` (new, M-4 guard), updated `StorefrontFoundationTest`/`StorefrontB2bTest` for the new tracking/balance behaviour.
- `CHANGELOG.md` — new `[1.10.0]` Security entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.10.0 / Security / 2026-07-13.
- `CODE_AUDIT_REPORT.md` — marked each finding with its resolution.

Verification: full `php artisan test` — 238 passed (1042 assertions). No frontend asset build needed (Blade-only view changes).

Deploy notes: no new migration. H-1/H-2 are ops actions — set a real DB and a non-sync queue with a worker per `.env.production.example`.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-12 - Fix Purchase "Save changes" always failing when an item is added

Reason:

- Owner reported (with a mobile screenshot): on Create/Edit Purchase, adding a product item then clicking "Save changes" always shows "Error while loading page", and the item disappears again after reloading — nothing was actually being saved.

Changed files:

- `app/Filament/Resources/Purchases/Schemas/PurchaseForm.php` — added `->dehydrated(false)` to the `allocated_cost` and `landed_unit_cost` read-only fields inside the `items` Repeater.
- `tests/Feature/PurchaseTest.php` — new regression test `test_create_purchase_form_saves_items_without_null_cost_column_error`, driving the actual `CreatePurchase` Livewire page via `Livewire::test()->fillForm()->call('create')`, since the existing Purchase tests all created `PurchaseItem` rows directly and never exercised the Filament form/repeater save path where this bug lived. Verified the test fails with the exact reported `NOT NULL constraint failed: purchase_items.allocated_cost` error when the fix is reverted, and passes with it applied.
- `CHANGELOG.md` — new `[1.9.4]` Critical Fix Update entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.4 / Critical Fix / 2026-07-12.

Root cause:

- `purchase_items.allocated_cost` and `landed_unit_cost` are `NOT NULL` columns with a schema-level `DEFAULT 0`, but both values are only ever computed after the record is saved, by `PurchaseWorkflowService::syncLandedCosts()` (called from `Purchase::saved` → `syncTotalsAndStock()`). The two form fields for them are read-only display fields that are never populated client-side, so Filament's repeater-relationship save included them as explicit `null` values in the insert. SQLite (like most databases) only applies a column's `DEFAULT` when the column is *omitted* from the insert list — an explicit `NULL` bypasses it — so every insert hit the `NOT NULL` constraint and the whole Livewire request 500'd, which is why the item appeared to "vanish" on reload (it was never actually persisted).
- Reproduced locally via the browser preview: confirmed the exact `500` on `livewire/update` and the matching `SQLSTATE[23000]... NOT NULL constraint failed: purchase_items.allocated_cost` entry in `storage/logs/laravel.log`.

Notes:

- With `dehydrated(false)`, these two fields are excluded from the saved payload entirely, so the DB `DEFAULT 0` applies cleanly on insert, and the existing post-save sync then fills in the real computed values — the exact same code path that already ran correctly on every subsequent update of an existing purchase.
- Verified in browser: created a test purchase with one item via the actual Create Purchase form — saved successfully (200 OK, redirected to the purchase's View page), and `allocated_cost`/`landed_unit_cost` were correctly computed afterward. Test purchase deleted from the demo database afterward.
- `php artisan test` — full suite, 234 passed (1017 assertions), no regressions.
- `npm run build` not run — PHP-only change, no frontend assets touched.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Customer Success pages merged into one page with header tabs

Reason:

- Owner asked to apply the same Courier-style tab consolidation to the Customer Success group (Risk Profiles, Blacklists, Risk Reviews, Risk Events — 4 separate sidebar pages), and to confirm the mobile dropdown's z-index fix applies here too. Then asked to also fold the separate "Risk Rule Settings" page (which had its own `Customer Success` sidebar group, outside the 4 resources) into the same cluster/tab bar.

Changed files:

- `app/Filament/Clusters/CustomerSuccess.php` (new) — same pattern as `app/Filament/Clusters/Courier.php`: `SubNavigationPosition::Top` tabs.
- `app/Filament/Resources/CustomerRiskProfiles/CustomerRiskProfileResource.php`, `CustomerBlacklists/CustomerBlacklistResource.php`, `CustomerRiskReviews/CustomerRiskReviewResource.php`, `CustomerRiskEvents/CustomerRiskEventResource.php` — replaced `$navigationGroup = 'Customer Success'` with `$cluster = CustomerSuccess::class`; added concise `$navigationLabel`s (Risk Profiles, Blacklists, Risk Reviews, Risk Events) for the tab bar.
- `app/Filament/Pages/CustomerRiskSettings.php` — same treatment: `$navigationGroup = 'Customer Success'` replaced with `$cluster = CustomerSuccess::class` and `$navigationLabel = 'Risk Settings'`. `Filament\Pages\Page` supports `$cluster` natively (confirmed in `vendor/filament/filament/src/Pages/Page.php`), same mechanism as resources.
- `tests/Feature/CustomerRiskTest.php` — updated all 5 hardcoded URLs (`/admin/customer-risk-profiles`, `-blacklists`, `-reviews`, `-events`, `-settings`) to their `/admin/customer-success/...` cluster-prefixed equivalents.
- `CHANGELOG.md` — `[1.9.3]` patch entry updated to describe all 5 tabs; `tests/Feature/ReleaseNotesTest.php` stays at v1.9.3 / Patch / 2026-07-11.

Notes:

- The mobile z-index fix (`.fi-dropdown-panel { z-index: 30 }`, added for the Courier cluster in v1.9.1) needed no changes — it's a generic rule that applies to every Filament dropdown panel, confirmed in browser at 375×812: opening the "Risk Profiles ▾" dropdown shows all options clearly above the header.
- Verified in browser at 1400×900: sidebar shows one "Customer Success" entry; 5 tabs (Risk Profiles, Blacklists, Risk Reviews, Risk Events, Risk Settings) render across the header, "Risk Settings" tab renders the actual settings form correctly.
- `php artisan test` — full suite, 233 passed (1010 assertions), no regressions.
- `npm run build` not run — Filament/PHP-only change.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Auto-notify on deploy + mobile header gap fix

Reason:

- Owner asked that whenever a new version is deployed to the server, users get a notification (sourced from the release notes / CHANGELOG) — no manual "post an announcement" step.
- Owner also pointed out (with a mobile screenshot) that the gap between the header search box and the profile avatar was too wide, and asked for the avatar to sit further left, about 10px, closer to the search box.

Changed files:

- `app/Console/Commands/NotifyLatestRelease.php` (new) — `php artisan release:notify-deploy`. Reads `App\Support\AppRelease::latestPublished()['version']` (the CHANGELOG's top entry — same source the existing Release Notes page already parses) and compares it against an `AppSetting` key (`release.last_notified_version`). First run ever just records the baseline (no notification, so existing installs don't get spammed about every past release retroactively the moment this ships); any later run where the version differs sends a `Notification::make()->success()->sendToDatabase()` to every active user and updates the baseline.
- `bootstrap/app.php` — scheduled the command `everyFiveMinutes()->withoutOverlapping()->onOneServer()`, alongside the existing `backup:database` / `storefront:send-abandoned-cart-reminders` / `couriers:sync-statuses` schedule entries.
- `tests/Feature/ReleaseNotificationTest.php` (new) — 4 tests: baseline-only first run, real notification + baseline update on a version change, no duplicate on a second run for the same version, and a missing/empty-CHANGELOG fallback that doesn't error.
- `app/Providers/Filament/AdminPanelProvider.php` — added `column-gap: 0.375rem` to `.fi-topbar-end` inside the existing `@media (max-width: 640px)` block (same block as the mobile notifications-in-profile-menu change from earlier today), reducing the default `1rem` gap and pulling the avatar in from the screen edge by the same amount.
- `CHANGELOG.md` — added `[1.9.2]` minor entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.2 / Minor Version Update / 2026-07-11.

Notes:

- Ran `php artisan release:notify-deploy` once against the real demo database to confirm it executes cleanly end-to-end (recorded the current CHANGELOG version, `1.9.1` at the time, as the baseline; sent no notifications, which is correct first-run behavior) — then deleted that `app_settings` row afterward so the real first production run starts from a clean baseline rather than my local test run.
- Verified the header gap fix in browser at 375×812: gap between search and avatar visibly tighter, avatar sits further from the screen edge; at 1400×900 (desktop) the gap is unchanged (still the default 16px) since the CSS is scoped to the mobile media query.
- `php artisan test` — full suite, 233 passed (1010 assertions), no regressions.
- `npm run build` not run — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Courier pages merged into one page with header tabs

Reason:

- Owner pointed out the Courier group had four separate sidebar pages (Providers, Bookings, Status Logs, Webhook Logs) and asked for one page with the four as header tabs instead — click a tab, it loads, no navigating away to a different sidebar item.

Changed files:

- `app/Filament/Clusters/Courier.php` (new) — Filament's built-in Cluster feature (not a hand-rolled tab UI); `$subNavigationPosition = SubNavigationPosition::Top` renders the clustered resources as tabs across the page header instead of a nested sidebar list.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php`, `CourierBookings/CourierBookingResource.php`, `CourierStatusLogs/CourierStatusLogResource.php`, `CourierWebhookLogs/CourierWebhookLogResource.php` — replaced `$navigationGroup = 'Courier'` with `$cluster = Courier::class` on each (Filament automatically hides clustered resources from the main sidebar, showing only the cluster's single nav item).
- `app/Providers/Filament/AdminPanelProvider.php` — added `->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')`.
- `tests/Feature/CourierIntegrationTest.php` — updated 3 hardcoded URLs from `/admin/courier-*` to `/admin/courier/courier-*` (Filament's standard cluster URL prefix); confirmed no other code referenced the old paths.
- `CHANGELOG.md` — added `[1.9.1]` patch entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.1 / Patch / 2026-07-11.

Notes:

- Verified in browser at 1400×900: sidebar shows one "Courier" entry; the Courier Providers list page shows 4 tabs (Providers, Bookings, Status Logs, Webhook Logs) across the top; navigating directly to each of the 4 new URLs renders correctly.
- `php artisan test` — full suite, 229 passed (997 assertions) after fixing the 2 URL-dependent tests that initially failed with 404s.
- `npm run build` not run — Filament/PHP-only change, no frontend asset changes.

## 2026-07-11 - Fixed mobile tab-dropdown hidden behind sticky header (same Courier change)

Reason:

- Owner reported (with a mobile screenshot) that on the new Courier tab bar, the mobile "Providers ▾" dropdown opened but showed no options — the panel was rendering underneath the page's sticky header instead of above it.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — added `.fi-dropdown-panel { z-index: 30; }` to the existing `STYLES_AFTER` `<style>` block (same block that made the page header `position: sticky; z-index: 20` in an earlier session — that z-index was the root cause, since the dropdown panel had no explicit z-index of its own and lost the stacking fight).

Notes:

- Verified in browser at 375×812 (mobile preset): the "Providers ▾" dropdown now shows all four options (Providers, Bookings, Status Logs, Webhook Logs) clearly above the header; tapping "Bookings" correctly navigates and updates the dropdown label.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — CSS is inlined via a PHP render hook, not a bundled asset.

## 2026-07-11 - Mobile header: notification bell tucked into profile avatar

Reason:

- Owner asked, for mobile, to move the notification bell icon into the profile/avatar icon on the right side of the header, and add 10px of right padding after the avatar (the header was cramped with hamburger, company switcher, search, bell, and avatar all fighting for space on a 375px-wide screen).

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — added a `@media (max-width: 640px)` block to the existing `STYLES_AFTER` `<style>` (same block used for the sticky header and company switcher sizing): `.fi-topbar-end` gets `position: relative; padding-inline-end: 10px`, and `.fi-topbar-database-notifications-btn` (Filament's own bell button class) is absolutely positioned over the avatar's top-right corner at 75% scale.
- CSS-only change — no Blade/Livewire markup touched, so the bell still opens the real notifications panel and the avatar still opens the real user menu; only their visual position/size changed on narrow screens.

Notes:

- Verified in browser at 375×812: bell now sits as a small badge on the avatar's top-right corner, with visible padding before the screen edge; clicking the bell still opens "No notifications" panel correctly; clicking the avatar still opens the user menu (Demo Admin / theme switcher / Sign out) correctly. Desktop (1400×900) is unchanged — bell and avatar still show as separate full-size icons.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — CSS is inlined via a PHP render hook, not a bundled asset.

## 2026-07-11 - Corrected: notification bell moved inside the profile dropdown, not overlaid on the avatar

Reason:

- Owner clarified the previous fix (bell as a small badge floating on the avatar's corner) was not what they meant by "insert into the profile icon" — they meant the actual dropdown menu that opens when tapping the avatar on mobile (the one showing "Demo Admin" / theme switcher / Sign out); the bell should be a row inside that menu.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — replaced the absolute-position badge CSS with: `.fi-topbar-database-notifications-btn { display: none }` inside the `@media (max-width: 640px)` block (hides the topbar bell entirely on mobile) plus a new `PanelsRenderHook::USER_MENU_PROFILE_AFTER` hook rendering a real menu item. Also fixed a CSS ordering bug caught while testing: the `.zz-mobile-notifications-item { display: none }` base rule must come *before* the media query, not after, or it wins the cascade at every width and the item never shows.
- `resources/views/filament/partials/mobile-notifications-menu-item.blade.php` (new) — a `<x-filament::dropdown.list.item>` with a bell icon and unread-count badge (`auth()->user()->unreadNotifications()->count()`), whose click handler dispatches `$dispatch('open-modal', { id: 'database-notifications' })` — Filament's own mechanism for opening that exact notifications modal (confirmed by reading `vendor/filament/support/resources/views/components/modal/index.blade.php`), so it's the same panel the topbar bell opens, not a duplicate/fake one. Only visible below 640px via the `.zz-mobile-notifications-item` class.

Notes:

- Verified in browser at 375×812: opening the avatar dropdown now shows "Demo Admin" header, then a "Notifications" row with a bell icon, then the theme switcher (sun/moon/system) row, then "Sign out" — tapping "Notifications" opens the real "No notifications" panel. At 1400×900 (desktop) the topbar bell still shows normally and the menu item stays hidden.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — Blade/CSS-only change.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-09 - Dynamic shipping fee from Set Delivery Fees + dashboard cleanups

Reason:

- Owner asked to hide the Filament "Welcome / Sign out" account widget from the Dashboard (redundant, looked bad next to the other cards).
- Owner pointed out the Courier Provider create/edit form had a duplicate-looking pair of sections ("Set Delivery Fees" and "Courier Delivery Cost" — identical Delivery Type + Inside/Outside/Suburb layout). Confirmed via search that neither `settings.delivery_fees.*` nor `settings.courier_costs.*` was read anywhere else in the codebase (pure UI-only fields). Owner chose to drop "Courier Delivery Cost" and asked whether "Set Delivery Fees" actually does anything dynamically across the app/storefront — it didn't (saved but never read) — owner asked to make it dynamic.
- Clarified two business-rule questions before implementing (owner answers, not invented): (1) the Inside/Outside/Suburb zone for a new order is auto-detected from the customer's address; (2) since no courier is booked yet at order-creation time, the company's first active courier provider's fee is used; (3) since customer addresses are free text with no city/area column, zone matching is driven by an owner-managed keyword list per zone (ERP Settings → Shipping Zones), not a hardcoded city list.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — removed `AccountWidget` from `->widgets()`.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` — removed the "Courier Delivery Cost" section.
- `database/migrations/2026_07_09_000000_add_shipping_fee_to_orders_table.php` (new) — `orders.shipping_zone` (nullable string), `orders.shipping_fee` (decimal, default 0). Migrated locally.
- `app/Services/ShippingFeeService.php` (new) — `determineZone()` matches a free-text address (case-insensitive substring) against `companies.settings.shipping_zones[zone]` keyword lists; `feeFor()` combines the matched zone with the company's first active `CourierProvider`'s `settings.delivery_fees[zone]`.
- `app/Models/Order.php` — `shipping_zone`/`shipping_fee` added to `$fillable`/casts; `creating` hook auto-populates them via `ShippingFeeService` when not already set (covers storefront checkout orders, which bypass the Filament form).
- `app/Services/OrderWorkflowService.php` — `sync()`'s `total_amount` calculation now adds `shipping_fee`.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — added a live "Shipping Fee" field (with a helper text showing the auto-detected zone) to the Totals section, recomputed whenever the customer changes; folded into the live total preview. Staff can still override the value manually.
- `app/Filament/Pages/CompanySettings.php`, `app/Services/CompanySettingsService.php`, `resources/views/filament/pages/company-settings.blade.php` — added a "Shipping Zones" section (Inside/Outside/Suburb comma-separated area lists), persisted under `companies.settings.shipping_zones`.
- `tests/Feature/ShippingFeeServiceTest.php` (new) — zone matching, fee lookup (with/without a configured courier), and an end-to-end order-creation test asserting `shipping_zone`/`shipping_fee`/`total_amount`.
- `CHANGELOG.md` — added `[1.9.0]` minor entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.0 / Minor Version Update / 2026-07-09.

Notes:

- Fallback behavior when nothing matches: if the address doesn't match any configured keyword, or the company has no active courier provider yet, `shipping_fee` defaults to 0 and the order form shows a note to set it manually — no fee is invented.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — no frontend asset changes (Filament/Blade only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - Android WebView network-error resilience (net::ERR_SOCKET_NOT_CONNECTED)

Reason:

- Owner reported the Android app occasionally shows `net::ERR_SOCKET_NOT_CONNECTED` (or similar) at `https://app.zamzamint.com/`, especially around Wi-Fi ↔ mobile data switching or dual-SIM data toggling — a native WebView/socket issue, not a Laravel backend bug. Owner supplied a detailed 8-step Kotlin-oriented implementation plan; adapted it to this app's actual Java + Capacitor (not raw WebView) structure since `MainActivity.java extends BridgeActivity` — Capacitor manages its own `WebViewClient` (`com.getcapacitor.BridgeWebViewClient`) internally, so a plain `WebViewClient` subclass would have silently broken plugin bridging and local-server URL interception.

Changed files:

- `android/app/src/main/java/com/zamzamint/erp/ResilientBridgeWebViewClient.java` (new) — extends Capacitor's `BridgeWebViewClient` (not `WebViewClient`) so `super.onReceivedError()`/`shouldInterceptRequest()`/etc. keep working. Retries only main-frame failures for a specific transient `net::ERR_*` code set (connect, timeout, host lookup, connection reset/refused, network changed, connection closed, socket-not-connected, name-not-resolved, internet-disconnected) up to 3 times, 2.5s apart; on the 4th failure loads a local `file:///android_asset/error.html?target=<real-url>` instead of retrying forever. Resets the retry counter once the real target URL finishes loading successfully.
- `android/app/src/main/java/com/zamzamint/erp/NetworkMonitor.java` (new) — thin wrapper around `ConnectivityManager.registerNetworkCallback`; calls back on `onAvailable()`.
- `android/app/src/main/java/com/zamzamint/erp/MainActivity.java` — overrides `load()` (called by `BridgeActivity.onCreate()` after the bridge is built) to swap in `ResilientBridgeWebViewClient` via the public `Bridge.setWebViewClient()` API, enable `domStorageEnabled`/`LOAD_DEFAULT` cache mode, and register `NetworkMonitor` — when connectivity returns while the local error page is showing, it reloads the real app automatically (no manual tap needed unless all 3 retries were already exhausted first).
- `android/app/src/main/assets/error.html` (new) — static "Connection Problem" page with a Retry button; reads the real target URL from a `?target=` query param (set by `ResilientBridgeWebViewClient`, itself read from `capacitor.config.json`'s `server.url` via `CapConfig.getServerUrl()` — never hardcoded).
- `android/app/src/main/AndroidManifest.xml` — added `ACCESS_NETWORK_STATE` permission (required for `ConnectivityManager.registerNetworkCallback`).
- `CHANGELOG.md` — added `[1.8.1]` patch entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.8.1 / Patch / 2026-07-08.

Notes:

- Deliberately did **not** implement step 7 (Coolify/Traefik `keepalive_timeout` tuning) from the owner's plan — that's server-side infrastructure, out of scope for this Laravel-repo-side fix, and the plan itself marks it optional/last-resort if the app-side fix isn't enough.
- **Not verified with a real build** — this environment has no local JDK/Android SDK (matches the project's existing pattern of using the `build-android` GitHub Actions CI job instead of local Android Studio). Code was reviewed carefully against Capacitor's actual `Bridge`/`BridgeWebViewClient`/`BridgeActivity` source (`node_modules/@capacitor/android`) to confirm method signatures (`Bridge.setWebViewClient(BridgeWebViewClient)`, `BridgeActivity.load()`, `CapConfig.getServerUrl()`) and Java 21 compatibility (`capacitor.build.gradle` already sets `sourceCompatibility 21`, so lambdas/method references used here are fine) — but the actual manual test checklist from the owner's plan (Wi-Fi↔mobile switch, airplane mode, weak-network simulation, dual-SIM toggle, background/foreground) still needs to be run on a real device or emulator after the next CI build.
- No Laravel/PHP files touched — `php artisan test` not re-run for backend logic, but `ReleaseNotesTest` was re-verified after the version bump (3 passed, 23 assertions).
- `npm run build` not applicable (native Android/Java changes only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - Auto-reload on save, User Roles moved under Users, per-company dashboard color (revised architecture)

Reason:

- Owner asked for three UI/settings changes: (1) any Filament save/create/delete should auto-reload the page instead of leaving stale form state visible; (2) the "User Roles" page shouldn't have its own sidebar entry — it should be reached from the Users page instead; (3) each company should be able to pick its own admin dashboard color.
- **First attempt (superseded same day):** stored the color as a `CompanySettingsService` setting and set it once via `->colors()` on the panel. Owner reported it only affected list/resource pages — `Product Setup`, `ERP Settings`, `Backups`, `Release Notes`, and `Reports` kept the old color — and asked for a real multi-shade palette generator, not one flat color. Root cause: `->colors()` bakes the palette into the panel config, which isn't necessarily re-evaluated identically across every page type, and a single color isn't the same as a full 50–950 shade ladder.
- Owner then supplied a concrete 8-step architecture (dedicated `companies.dashboard_color` column, `ColorPicker` on `CompanyResource`, a `DynamicColorService` shade generator, and a `PANELS_HEAD`-style render hook injecting CSS custom properties from `CompanyContext` on every request) and asked it to be followed exactly. Implemented that instead.

Changed files:

- `database/migrations/2026_07_08_000000_add_dashboard_color_to_companies_table.php` (new) — `companies.dashboard_color` (varchar(7), default `#F59E0B`), intentionally separate from `StorefrontSetting.theme_color` (customer-facing branding vs admin-panel readability).
- `app/Models/Company.php` — added `dashboard_color` to `$fillable`.
- `app/Filament/Resources/Companies/CompanyResource.php` — added `ColorPicker::make('dashboard_color')` to the form, plus `ColorColumn`/`ColorEntry` on the table/infolist so it's visible without opening edit.
- `app/Services/DynamicColorService.php` (new) — `generateShades(string $hex): array` (50..950 keyed shades) and `cssVariables()`, delegating to Filament's own `Color::generatePalette()` (OKLCH-based) rather than hand-rolling HSL math, so shades render identically to any other Filament `Color::*` palette.
- `app/Providers/Filament/AdminPanelProvider.php` — reverted `->colors()` to the static `Color::Amber` fallback (used for "All Companies" mode); added a `HEAD_END` render hook that reads `CompanyContext` fresh per request and injects `:root { --primary-50: ...; ...; --primary-950: ...; }` from the active company's `dashboard_color` — this is what makes every page type pick it up and switch instantly on company change, no reload/redeploy. Also kept the `SCRIPTS_AFTER` auto-reload-on-save hook and the User Roles/Manage Roles nav changes from the same day (see below).
- `app/Filament/Resources/UserRoles/UserRoleResource.php` — `$shouldRegisterNavigation = false` (dropped from sidebar, still routable).
- `app/Filament/Resources/Users/Pages/ListUsers.php` / `UserRoles/Pages/ListUserRoles.php` — "Manage Roles" / "Back to Users" header actions.
- **Reverted** the first attempt: `app/Services/CompanySettingsService.php` (removed `PRIMARY_COLOR`/`DEFAULT_PRIMARY_COLOR`/`primaryColorHex()`), `app/Filament/Pages/CompanySettings.php` + its Blade view (removed the "Dashboard Color" section — that page is now business profile/branding only, still renamed to "ERP Settings"), `tests/Feature/CompanySettingsTest.php` (removed the 3 primary-color tests).
- `tests/Feature/DashboardColorTest.php` (new) — 5 tests: color injected for the active company, switching company changes the injected shades, "All Companies" falls back to default, shade ladder has all 11 keys, invalid hex falls back to default.
- `CHANGELOG.md` — rewrote the `[1.8.0]` entry to describe the corrected architecture; `tests/Feature/ReleaseNotesTest.php` stays at v1.8.0 / Minor Version Update / 2026-07-08 (no version bump needed, nothing committed yet).

Notes:

- Verified live in a browser end-to-end: set a company's `dashboard_color` via the actual `ColorPicker` on the Company edit form (through Livewire, not just tinker), switched the active company via the real topbar switcher, and confirmed `getComputedStyle(document.documentElement).getPropertyValue('--primary-500')` matched the new color's OKLCH hue on **every** previously-broken page (Product Setup, ERP Settings, Backups, Release Notes, Reports) plus the Dashboard and Customers list — not just resource pages. Reset the test company's color back to `#F59E0B` afterward so no demo data was left altered.
- Discovered along the way: the local dev/demo split matters — `.env` has `DB_CONNECTION=demo`, and an earlier round of manual `tinker` verification calls had accidentally targeted a different, non-demo sqlite file, temporarily setting a company's name to "Test". That was corrected and confirmed to never have touched the real demo data (`Main Company`, `Garments Machinery Company`, etc. were intact throughout).
- The auto-reload-on-save and User Roles nav changes are unchanged from the first attempt and already verified working.
- Full suite verified: `php artisan test` (no `--env` flag) — 225 passed, 983 assertions.
- `npm run build` not required — no frontend asset changes (Blade/PHP only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - App own-domain root redirect + courier fraud check follow-up fixes

Reason:

- Owner reported (while manually testing on local server) that `app.zamzamint.com` (loaded by both the browser and the Android app shell) should show the login page when signed out and the dashboard directly when signed in, instead of the generic marketing homepage that `/` shows today.
- Also fixed 3 bugs the owner found while testing yesterday's courier fraud check feature: (1) phone numbers stored as `+880...`/`880...` always returned "no history" because the third-party package only accepts local `01...` format; (2) the manual "Courier Fraud Check" button kept showing a stale cached result after adding a new courier's credentials; (3) owner asked for the result to display inline next to the button (color-coded) instead of only in a notification toast.

Changed files:

- `app/Http/Controllers/Storefront/HomeController.php` — added `isAppOwnDomain()` check; when the resolved storefront company is null and the request host matches `config('app.admin_host')`, redirects to `/admin` instead of rendering `marketing.home`.
- `config/app.php` + `.env.example` — added `admin_host` config key sourced from new `ADMIN_APP_HOST` env var (left unset locally, so `/` still shows the marketing page in local/testing — verified via existing `test_local_root_keeps_marketing_homepage`).
- `app/Services/ExternalCourierFraudService.php` — added `normalizePhone()` (converts `+880`/`880` to local `01...` format before calling the package, which throws on non-local format); added a `bypassCache` parameter to `checkByPhone()`; only caches a result when at least one courier actually answered.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — manual "Courier Fraud Check" button now passes `bypassCache: true`; replaced the notification-toast result with an inline `Flex`+`Html` status next to the button, color-coded (green/red/gray) against the existing `external_fraud_low_ratio_threshold` setting.
- `tests/Feature/ExternalCourierFraudCheckTest.php` — added phone-normalization test and cache-bypass test.
- `tests/Feature/StorefrontFoundationTest.php` — added `test_app_own_domain_root_redirects_to_admin_panel`.
- `CHANGELOG.md` — added `[1.7.1]` patch entry for the domain redirect; updated `[1.7.0]` with the two courier fraud check fixes; `tests/Feature/ReleaseNotesTest.php` bumped to v1.7.1 / Patch / 2026-07-08.

Notes:

- `ADMIN_APP_HOST` must be set to `app.zamzamint.com` in the production `.env` for the redirect to take effect there; nothing changes for customer storefront custom domains or any other unmatched host.
- Full suite verified: `php artisan test` (no `--env` flag) — 220 passed, then re-verified after the version bump (`ReleaseNotesTest`: 3 passed, 23 assertions).
- `npm run build` not required — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-07 - External courier fraud check (Part 3.8)

Reason:

- Owner added a new master-plan section (Part 3.8) requesting a cross-courier fraud/delivery-history lookup by phone number, live and visible for admin staff, silent/background for storefront checkout. Owner explicitly chose to install `shahariar-ahmad/courier-fraud-checker-bd` (a third-party Composer package) after reviewing that it logs into each courier's own merchant panel (Pathao, Steadfast, RedX) rather than calling an official documented API — no official public fraud-check API could be found for these couriers.

Changed files:

- `composer.json` / `composer.lock` — added `shahariar-ahmad/courier-fraud-checker-bd` v2.0.2.
- `app/Services/ExternalCourierFraudService.php` (new) — cache (24h TTL per phone per company), fail-safe (never throws, skips unconfigured/failing couriers), logs every real external call to `customer_risk_events`.
- `app/Jobs/CheckExternalCourierFraudJob.php` (new) — storefront-side async check; if the cross-courier success ratio is below a configurable threshold, requests a manager review via the existing `CustomerRiskService::requestReview()` gate (same mechanism already used for high-risk/blacklisted orders).
- `app/Http/Controllers/Storefront/CheckoutController.php` — dispatches the job (`->afterCommit()`) right after order creation; customer never sees this.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — added a "Courier Fraud Check" button next to the customer select; staff click it to see a live notification with per-courier success/cancel/total and overall ratio.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` — added an optional "External Fraud Check (Merchant Panel Login)" section (Pathao/Steadfast/RedX), separate from existing booking API credentials, since this feature needs the courier's website login, not their API keys.
- `app/Services/CustomerRiskSettingsService.php` + `app/Filament/Pages/CustomerRiskSettings.php` — added `external_fraud_low_ratio_threshold` (default 50%), configurable on the existing Risk Rule Settings page.
- `tests/Feature/ExternalCourierFraudCheckTest.php` (new) — 4 tests covering: missing-credentials skip, combined stats + audit log, cache dedupe, low-ratio triggers manager review.
- `CHANGELOG.md` — added `[1.7.0]` entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.7.0 / Minor Version Update / 2026-07-07.

Notes:

- Pathao has no official public fraud-check API; this feature (and the third-party package) logs into the courier's own website, which is more fragile than a documented API and may break if a courier changes its site. This is a known, accepted trade-off per the owner's explicit choice.
- Full suite verified: `php artisan test` (no `--env` flag) — 217 passed, then re-verified after the version bump (`ReleaseNotesTest`: 3 passed, 23 assertions).
- `npm run build` not required — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix Android app status bar overlap (Android 15 edge-to-edge)

Reason:

- The `[1.6.3]` StatusBar plugin fix (`overlaysWebView: false`) did not fix the overlap on the owner's real test device. Investigated the plugin's Android source (`node_modules/@capacitor/status-bar/android/.../StatusBar.java`) — it only sets legacy `SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN` view flags, which Android 15 (API 35) ignores because API 35 force-enables edge-to-edge layout for apps that target it. `android/variables.gradle` has `targetSdkVersion = 35`, confirming the device is hitting this.

Changed files:

- `android/app/src/main/res/values/styles.xml` — added `android:windowOptOutEdgeToEdgeEnforcement="true"` to `AppTheme` and `AppTheme.NoActionBar` to opt back out of forced edge-to-edge on Android 15; added the `tools:` namespace needed for the `tools:targetApi="35"` guard.
- `CHANGELOG.md` — added `[1.6.4]` patch entry, noting this opt-out attribute may not be honored on a future Android version (would need CSS safe-area-inset padding on the server side instead, at that point).
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.4.

Notes:

- No PHP behavior changed; verified `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions).
- Requires a new APK build + reinstall to test (native theme change, not a web deploy).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix Android app status bar overlap

Reason:

- App loaded successfully on a real device (JDK 21 fix worked), but the admin panel's header was hidden behind/overlapping the phone's status bar (clock, signal, battery icons), making the nav menu, search, and company selector hard to tap.

Changed files:

- `capacitor.config.json` — added `plugins.StatusBar` config (`overlaysWebView: false`, `style: DARK`, `backgroundColor: #000000`) so the WebView renders below the status bar instead of underneath it.
- `android/app/src/main/assets/capacitor.config.json` — manually mirrored the same change (local `npx cap sync android` hit the known Windows EPERM issue again; CI's own `cap sync` on the Linux runner will regenerate this correctly from the root config on every build regardless).
- `CHANGELOG.md` — added `[1.6.3]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.3.

Notes:

- No PHP behavior changed; verified `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions).
- Requires a new APK build (this is a native config change, not a web deploy) — rebuild via GitHub Actions and reinstall on the test device to see the fix.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix build-android CI: JDK 21 required by Capacitor 7

Reason:

- After the gradlew permission fix, the `build-android` job progressed further but failed with `error: invalid source release: 21` compiling `capacitor-android` — Capacitor 7's Android library targets Java 21, but CI's JDK was set to 17.

Changed files:

- `.github/workflows/deploy.yml` — bumped `actions/setup-java` to `java-version: '21'`.
- `CHANGELOG.md` — added `[1.6.2]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.2.

Notes:

- Verified: `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions). No PHP/app behavior changed.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix build-android CI: gradlew permission denied

Reason:

- First `build-android` CI run (commit `690e70b2`) failed with `Permission denied` on `./gradlew` (exit 126). Windows checkouts don't preserve the Unix executable bit, so `android/gradlew` was committed as `100644` instead of `100755`.

Changed files:

- `android/gradlew` — restored executable bit via `git update-index --chmod=+x`.
- `.github/workflows/deploy.yml` — added `chmod +x ./gradlew` before running it in the `build-android` job, as a safety net.
- `CHANGELOG.md` — added `[1.6.1]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertions to v1.6.1 / Patch.

Notes:

- Full suite not required to be rerun for this fix beyond `ReleaseNotesTest` (verified: 3 passed, 23 assertions) since no other app behavior changed.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Android app shell (Capacitor)

Reason:

- Owner wants the ERP admin panel available as a mobile app, starting with Android (storefront app deferred). Since the panel is already fully responsive and cloud-hosted, the fastest and lowest-maintenance path is a Capacitor WebView shell pointed at the live URL, rather than a separate native codebase.

Changed files:

- `capacitor.config.json` (new) — app id `com.zamzamint.erp`, loads `https://app.zamzamint.com`
- `mobile-shell/` (new) — placeholder web asset dir Capacitor requires, plus `mobile-shell/README.md` with build/setup instructions
- `android/` (new) — generated native Android project (Capacitor scaffold)
- `package.json` — added `@capacitor/core`, `@capacitor/android`, `@capacitor/app`, `@capacitor/status-bar`, `@capacitor/splash-screen`, `@capacitor/cli` (v7, matched to this machine's Node 20) and `mobile:sync` / `mobile:open` / `mobile:build` scripts
- `.github/workflows/deploy.yml` — added `build-android` CI job (Node + JDK 17, `cap sync`, `gradlew assembleDebug`, uploads the APK as a workflow artifact) so an APK can be built and downloaded without local Android Studio; also fixed this workflow's test step which ran `php artisan test --env=testing` (the same flag now forbidden by the new CLAUDE.md rule — harmless here since CI has no real demo database, but corrected for consistency)
- `CHANGELOG.md` (1.6.0 minor), `tests/Feature/ReleaseNotesTest.php` (v1.6.0)

Notes:

- This dev machine has no Android Studio/JDK installed, so no APK has been built or tested yet. The owner will install Android Studio locally to build and test.
- `npx cap sync android` hit intermittent `EPERM` errors on this machine (antivirus locking newly written files mid-copy) — the generated `android/app/src/main/assets/capacitor.config.json` was verified correct regardless; documented as a known issue in `mobile-shell/README.md`.
- Full suite re-verified after the version bump: `php artisan test` (no `--env` flag) — 213 passed (936 assertions).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Agent rule: tests must not touch demo data + Phase 8 verification

Reason:

- Owner asked for a hard rule that test runs never refresh/reseed the demo database. Investigating this uncovered that `php artisan test --env=testing` bypasses `phpunit.xml`'s env overrides and runs `RefreshDatabase` against the real `database/demo.sqlite` — this had already wiped the owner's demo data during a prior session. Ran `php artisan demo:refresh` to restore it.
- Also planned to build master plan Phase 8 (duplicate order / same-phone-multiple-name / high-COD-first-order detection); exploration found all three were already implemented in `CustomerRiskService` and gating courier booking via `assertCourierBookingAllowed` — just missing direct test coverage for two of the factors, and the master plan doc was stale.

Changed files:

- `CLAUDE.md` (new rule: never `--env` flag when testing; never demo:refresh/seed/migrate:fresh during testing)
- `tests/Feature/CustomerRiskTest.php` (2 new tests: `phone_multiple_names` and `recent_duplicate_order` factors)
- `business_dashboard_master_plan_v2_custom_storefront.md` (Phase 8 marked complete with detail; stale Phase 5 MOQ/tiered-pricing "unconfirmed" note corrected — it's implemented and tested)
- `CHANGELOG.md` (1.5.1 patch), `tests/Feature/ReleaseNotesTest.php` (v1.5.1)

Verification:

- `php artisan test` (no --env flag) — 213 passed (936 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-05 - Production Courier Monitoring & Alerting

Reason:

- Last remaining master plan Part 2 item: in production nobody would notice a broken courier API, a permanently failed webhook, or a booking stuck in transit. Adds scheduled status syncing plus persistent admin alerts.

Changed files:

- `app/Console/Commands/SyncCourierStatuses.php` (new — `couriers:sync-statuses`, per-company loop with CompanyContext, cooldown/batch-limit/failure-streak from admin-configurable provider settings, stale-booking detection)
- `app/Services/CourierAlertService.php` (new — Filament database notifications to active super admins + owning-company managers, deduped once per subject per day via cache)
- `app/Jobs/ProcessCourierWebhook.php` (`failed()` hook alerts admins after all retries are exhausted)
- `app/Filament/Widgets/CourierHealthWidget.php` (new dashboard stats), `CourierProviderResource` (Monitoring & Alerts settings section + Last Sync / Sync Failures columns)
- `app/Models/CourierProvider.php` (MONITORING_DEFAULTS + `monitoringSetting()`), `CourierBooking.php` (ACTIVE_STATUSES, `last_synced_at`)
- `app/Providers/Filament/AdminPanelProvider.php` (`->databaseNotifications()` bell)
- `bootstrap/app.php` (schedule: every 30 minutes, withoutOverlapping, onOneServer)
- Migrations: courier monitoring fields + `notifications` table
- `tests/Feature/CourierMonitoringTest.php` (new — 6 tests), `tests/Feature/ReleaseNotesTest.php` (v1.5.0), `CHANGELOG.md` (1.5.0)

Deploy notes:

- Run `php artisan migrate` (new `notifications` table is required by the admin panel bell). Scheduler + queue worker must be running.

Verification:

- `php artisan test --env=testing --filter=CourierMonitoringTest` — 6/6
- Full suite: 211 passed (934 assertions).

Commit status: Approved by owner; committed and pushed.

## 2026-07-05 - Live Pathao/RedX/E-Courier Couriers + Steadfast Balance UI

Reason:

- Master plan Part 2 remaining items. Owner asked to build the live courier clients now (API contracts researched from official docs) and show the Steadfast balance in admin; credentials stay admin-configurable so the owner plugs in merchant keys later.

Changed files:

- `app/Services/PathaoCourierClient.php`, `app/Services/RedxCourierClient.php`, `app/Services/ECourierClient.php` (new — verified endpoints: Pathao aladdin issue-token/orders/reference lists with cached bearer token; RedX v1.0.0-beta parcel/track/info/areas with API-ACCESS-TOKEN header; E-Courier order-place/track/cancel with API-KEY/API-SECRET/USER-ID headers)
- `app/Services/CourierService.php` (create/sync/normalize methods for the three couriers + shared `storeBooking`/`assertProviderUsable` helpers)
- `app/Services/Couriers/PathaoCourierAdapter.php`, `RedxCourierAdapter.php`, `ECourierAdapter.php` (now real adapters); `PendingLiveCourierAdapter.php` deleted
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` (driver-aware encrypted credential fields, sandbox helper text, Steadfast Balance action)
- `app/Filament/Resources/Orders/Tables/OrdersTable.php` (Book Pathao / Book RedX / Book E-Courier actions with courier-specific fields)
- `app/Filament/Resources/CourierBookings/CourierBookingResource.php` (sync action now covers all API drivers via CourierManager)
- `tests/Feature/LiveCourierAdaptersTest.php` (new — 5 tests), `tests/Feature/CourierIntegrationTest.php` (pending-adapter test now asserts credentials-required), `tests/Feature/ReleaseNotesTest.php` (v1.4.0)
- `CHANGELOG.md` (1.4.0), master plan Part 2 / Phase 2 / Phase 7 checkmarks

Verification:

- `php artisan test --filter="LiveCourierAdaptersTest|CourierIntegrationTest"` — 17/17
- Full suite: 205 passed (910 assertions)
- Live sandbox verification pending owner's merchant credentials.

Commit status: Approved by owner; committed and pushed.

## 2026-07-05 - Fix ReleaseNotesTest after 1.3.0 changelog entry

- Adding the 1.3.0 CHANGELOG entry changed the latest published release shown on the admin Release Notes page, so `ReleaseNotesTest` (which asserted v1.2.0 / Released 2026-06-24 as latest) failed. Updated the test to assert v1.3.0 / Released 2026-07-05. 3/3 pass.
- Commit status: Committed and pushed (follow-up to the approved 1.3.0 release-notes commit, which otherwise left the suite red).

## 2026-07-05 - Part 0 Verification + Part 1.10 Cross-Cutting Isolation Audit (route-binding isolation fix)

Reason:

- Master plan Part 0 (pre-requisite stabilization) items were still marked unverified, and Part 1.10 (queue/scheduled/export/backup isolation audit) was pending. The audit found and fixed a real cross-company data exposure bug.

Security fix (the important part):

- `SetCurrentCompany` middleware ran **after** route model binding (`SubstituteBindings`), so on implicit-binding admin routes such as `/admin/orders/{order}/pdf` the `CompanyScope` could not constrain the binding query — an authenticated staff user of company A could download company B's order PDF by guessing an order ID. Fixed in `bootstrap/app.php` with `prependToPriorityList()` so company context is bound before any route model binding resolves.

Changed files:

- `bootstrap/app.php` (middleware priority: `SetCurrentCompany` before `SubstituteBindings`)
- `tests/Feature/CrossCuttingIsolationAuditTest.php` (new — 4 regression tests: product CSV export, customer CSV export, report CSV export scoped to current company; cross-company order PDF returns 404)
- `business_dashboard_master_plan_v2_custom_storefront.md` (Part 0.1/0.2, Part 1.10, Phase 0/1 checkmarks updated with evidence)

Audit findings (no code change needed):

- Queue: the only queued job `ProcessCourierWebhook` sets `CompanyContext` from the webhook log's provider company and clears it in `finally` — correct pattern.
- Scheduled commands: `storefront:send-abandoned-cart-reminders` loops per company setting with explicit `company_id` filters; `backup:database` is whole-database by design.
- Backup: no per-company restore feature exists; downloads are permission-gated.
- Verified as already complete in code (plan doc was stale): per-product landed cost allocation (`PurchaseWorkflowService::syncLandedCosts()`), invoice + report PDF export (`OrderPdfController`, `ReportPdfController`), scheduled daily backups with restore-drill verification (`backup:database` at 02:00 + `backup:verify`), and composer.json hardening (`block-insecure: true`, `minimum-stability: stable`, dompdf pinned `^3.1`).

Verification:

- `php artisan test --filter=CrossCuttingIsolationAuditTest` — 4/4 (the order-PDF test failed before the middleware fix, proving the bug)
- Full suite: 200 passed (894 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront Advanced Commerce: WooCommerce Import, ZiniPay Pre-order Payments, Reseller Applications, Abandoned Cart Reminders

Reason:

- Owner-confirmed business rules (via Q&A): ZiniPay gateway for online payments with COD limited to in-stock items and per-product pre-order advance percent; admin-approved resellers; automatic SMS + Meta Cloud WhatsApp abandoned-cart reminders; WooCommerce products-only import via REST API. All credentials are admin-configurable fields (owner will plug in keys later) — nothing is hardcoded.

Changed files:

- `database/migrations/2026_07_04_020000_add_woocommerce_credentials_to_storefront_settings_table.php` (new)
- `database/migrations/2026_07_04_030000_add_preorder_and_payment_support.php` (new)
- `database/migrations/2026_07_04_040000_add_reseller_fields_to_customers_table.php` (new)
- `database/migrations/2026_07_04_050000_create_storefront_cart_records_and_notification_settings.php` (new)
- `app/Models/StorefrontSetting.php`, `app/Models/Product.php`, `app/Models/Customer.php`, `app/Models/Order.php`
- `app/Models/StorefrontPayment.php`, `app/Models/StorefrontCartRecord.php` (new)
- `app/Services/WooCommerceImportService.php`, `app/Services/ZiniPayClient.php`, `app/Services/StorefrontNotificationService.php` (new)
- `app/Services/StorefrontCart.php` (persisted cart records + stable cart token)
- `app/Console/Commands/ImportWooCommerceProducts.php`, `app/Console/Commands/SendAbandonedCartReminders.php` (new)
- `app/Http/Controllers/Storefront/CheckoutController.php`, `app/Http/Controllers/Storefront/ResellerController.php` (new), `app/Http/Controllers/ZiniPayWebhookController.php` (new)
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` (ZiniPay, Abandoned Cart, WooCommerce sections)
- `app/Filament/Resources/Products/Schemas/ProductForm.php` (pre-order fields)
- `app/Filament/Resources/Customers/Schemas/CustomerForm.php`, `app/Filament/Resources/Customers/Tables/CustomersTable.php` (reseller status)
- `resources/views/storefront/` (product card/show pre-order states, checkout advance notice, success payment status, reseller apply page, footer link)
- `routes/web.php`, `bootstrap/app.php` (reseller + webhook routes, CSRF exception, hourly scheduler)
- `tests/Feature/WooCommerceImportTest.php`, `tests/Feature/StorefrontPreorderPaymentTest.php`, `tests/Feature/StorefrontResellerAndAbandonedCartTest.php` (new), `tests/Feature/MultiCompanyIsolationTest.php` (new models added to contract)

What changed:

- WooCommerce import: per-company base URL + encrypted consumer key/secret in storefront settings; `php artisan woocommerce:import-products {company-slug}` pulls published products (paged, retried), matches by SKU/slug (re-runs update, never duplicate), maps regular/sale price and first category, optionally downloads the first image. Stock intentionally stays 0 (ERP stock must come from stock movements).
- Pre-order + ZiniPay: `products.is_preorder` + `preorder_advance_percent` (per-product, default 100%); pre-order products can be ordered beyond stock; checkout computes the online advance (pre-order quantity beyond stock only) and redirects to ZiniPay hosted checkout (`/v1/payment/create`); webhook at `POST /webhooks/zinipay/{payment}` re-verifies via `/v1/payment/verify` (never trusts the webhook body) and amount-matches before marking `storefront_payments` completed. COD remains the flow for fully in-stock carts. Pre-order checkout is blocked with a clear error when online payment is not configured.
- Reseller: public `/reseller` application page (name, phone, business name, note) creates/updates a company-scoped Customer with `reseller_status = pending`; approved customers keep `approved` on re-application; admin approves from the Customer form's new Reseller section; Customers table shows a reseller badge. Price gating for approved resellers is deferred until customer login exists (documented).
- Abandoned carts: cart activity now also persists to `storefront_cart_records` (stable session token, converted on successful checkout, phone captured at checkout attempt); hourly `storefront:send-abandoned-cart-reminders` sends one SMS (generic GET-gateway URL template with placeholders) and one WhatsApp template message (Meta Cloud API) per stale cart, then marks it reminded.

Verification:

- `php artisan migrate --force` (4 migrations)
- `npm run build`
- New test files 12/12 passed; isolation contract extended with `ProductCarousel`, `StorefrontPayment`, `StorefrontCartRecord`
- Full suite `php artisan test` (196 passed, 882 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront B2B: Tiered Pricing, MOQ, and Customer Due Visibility

Reason:

- Master plan Part 4.6 B2B UX items 1, 2, and 4 were the last storefront features implementable without external credentials: per-product tiered wholesale pricing, minimum order quantity enforcement, and showing a customer's current due on the account orders page.

Changed files:

- `database/migrations/2026_07_04_010000_add_moq_and_tier_prices_to_products_table.php` (new)
- `app/Models/Product.php`
- `app/Filament/Resources/Products/Schemas/ProductForm.php`
- `app/Services/StorefrontCart.php`
- `app/Http/Controllers/Storefront/AccountOrdersController.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/account/orders.blade.php`
- `tests/Feature/StorefrontB2bTest.php` (new)
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- New nullable `products.moq` and `products.tier_prices` (JSON `{min_qty, price}` rows) columns; both optional so existing products behave exactly as before.
- `Product::effectiveMoq()`, `normalizedTiers()`, and `priceForQuantity()` helpers; tier prices override the sale price at matching quantities for non-variant lines only (variant lines keep their own variant price).
- Admin Product form gains a collapsible "Wholesale (B2B)" section with MOQ input and a tier-price repeater.
- `StorefrontCart` clamps add/update quantities up to the MOQ (0 still removes; stock cap wins when stock is below MOQ) and prices non-variant lines with `priceForQuantity()`, so tier pricing flows into checkout order items unchanged.
- Product page shows a "Wholesale pricing" range table (with the base price as the first row) and a "Minimum order" badge; the quantity input starts at and enforces the MOQ; product cards show an MOQ badge and quick-add uses the MOQ.
- Account orders page shows a "Current due" banner with the customer's `current_balance` (only when > 0, and only when the searched phone matched storefront orders in the current company — same access rule as order history).

Verification:

- `php artisan migrate --force`
- `npm run build`
- `php artisan test --filter=StorefrontB2bTest` (4/4 passed)
- Full suite `php artisan test` (184 passed, 822 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront Quick Reorder

Reason:

- Master plan Part 4.6 B2B UX item 5 (Quick Reorder) was the last remaining pure-code storefront feature. Customers can now re-add a previous storefront order's items to their cart in one click from the account orders page.

Changed files:

- `app/Http/Controllers/Storefront/AccountOrdersController.php`
- `routes/web.php`
- `resources/views/storefront/account/orders.blade.php`
- `tests/Feature/StorefrontReorderTest.php` (new)
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- New `POST /account/orders/{orderNo}/reorder` route (production domain) and `POST /storefront/{company-slug}/account/orders/{orderNo}/reorder` (local preview), both named routes.
- `AccountOrdersController::reorder`/`reorderPreview` validate that the submitted phone matches the order's customer (same matching rules as the order-history lookup), that the order belongs to the current storefront company, and that it is a storefront-source order — otherwise 404.
- Available products/variants from the order are added to the session cart via the existing `StorefrontCart` service (stock capping applies); discontinued/inactive items are skipped. A flash status reports how many items were added, and the customer is redirected to the cart.
- `Reorder` button added next to `Track order` on each order card in `account/orders.blade.php`, posting the searched phone as a hidden field.
- Master plan Part 4 pending list refreshed: carousel/variants marked done (they were completed in commit 2626e5f0 but the doc still listed the carousel as pending), Quick Reorder marked done, and tiered pricing/MOQ/due-visibility explicitly listed as blocked on business decisions.

Verification:

- `php artisan test --filter=StorefrontReorderTest` (2/2 passed)
- Full suite `php artisan test` (180 passed, 807 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Variant Stock Movement CI Fix

Reason:

- GitHub CI failed on `ProductVariantTest::test_confirmed_order_deducts_variant_stock_and_restores_on_cancel` because variant sale movements were still validated against the parent product movement ledger. Variable products keep stock from active variants, so the parent ledger can be empty even when the selected variant has stock.

Changed files:

- `app/Services/StockMovementService.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Stock movements with `product_variant_id` now validate projected stock against the selected variant's current stock plus the signed movement delta.
- Invalid variant/product combinations fail with a form validation message.
- The project guide now documents that variant stock movements are validated against variant stock, not parent product ledger stock.

Verification:

- `php artisan test --env=testing --filter=ProductVariantTest` passed: 6 tests, 19 assertions.
- `php artisan test --env=testing` passed: 178 tests, 798 assertions.

Commit status: User approved commit and push on 2026-07-04.

## 2026-07-04 - Storefront Variant Cart and CI Stabilization

Reason:

- Latest storefront merchandising work added variable product/cart behavior and exposed a CI-only failure where Storefront Settings domain sync tests failed under `php artisan test --env=testing`.

Changed files:

- `app/Filament/Resources/Orders/Schemas/OrderForm.php`
- `app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php`
- `app/Http/Controllers/Storefront/CartController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Models/StockMovement.php`
- `app/Services/OrderWorkflowService.php`
- `app/Services/StockMovementService.php`
- `app/Services/StorefrontCart.php`
- `database/migrations/2026_07_03_050000_add_product_variant_id_to_stock_movements.php`
- `database/seeders/DemoDataSeeder.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `tests/Feature/ProductVariantTest.php`
- `PROJECT_GUIDE.md`

What changed:

- Storefront carts now keep product variants as separate lines using product + variant keys.
- Variable product pages can submit multiple variant quantities in one add-to-cart request.
- Checkout stores `product_variant_id`, `variant_label`, variant price, and variant cost on order items.
- Sale stock movements can reference a variant and update/restore variant stock with signed movement deltas.
- Variable products keep parent stock synced from active variant stock without the product stock ledger overwriting it.
- Demo data now includes richer storefront sample products, variant products, pages, and courier provider records.
- Storefront Settings domain sync tests now set Livewire `data.*` state directly and the create/edit pages merge raw form state before syncing company domain fields, stabilizing `php artisan test --env=testing`.

Verification:

- `php artisan test --env=testing --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --env=testing` passed: 176 tests, 787 assertions.

Commit status: Pending commit and push requested by user.

## 2026-07-03 - Storefront "Top-Class Reference Pattern" (Part 4.6 remaining items)

Reason:

- Master plan Part 4.6 listed 7 accepted UI patterns from the SkyBuy/MoveOn reference analysis (mega menu, dual banner, header chat+call button, curated carousel, "how to order" explainer, sister-company cross-promotion, mobile bottom nav) as not yet implemented. This pass implements everything except the curated carousel, which needs a new Filament resource and was intentionally left out of scope for this change.

Changed files:

- `database/migrations/2026_07_03_010000_add_dual_banner_and_phone_to_storefront_settings_table.php` (new)
- `app/Models/StorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- Added `storefront_settings.banner_image_mobile` (nullable) and `storefront_settings.phone_number` (nullable) columns.
- Header mega menu: hover dropdown under "Categories" listing the company's active categories that have available products.
- Header call button: `tel:` link next to WhatsApp, shown only when `phone_number` is set.
- Dual banner: `home.blade.php` hero uses `<picture>`/`<source media="(max-width: 639px)">` to show `banner_image_mobile` on phones and the existing desktop banner otherwise.
- "How to order" explainer: static 4-step icon+text section on the homepage between the hero and category grid.
- Sister-company cross-promotion: footer section linking to other active companies with a published storefront and a domain.
- Mobile bottom nav: fixed `sm:hidden` bar (Home/Category/Cart with badge/Account); `<main>` and `<footer>` get bottom spacing so content isn't hidden under it.
- `StorefrontSettingResource` form: new "Call support number" field and a second, separate "Banner image (mobile)" upload alongside the existing desktop banner upload field.

Verification:

- `php artisan migrate --force`
- `npm run build`
- `php artisan test --filter=StorefrontFoundationTest` (20/20)
- `php artisan test --filter=PhaseFourAdminPagesTest` (3/3)
- Full suite `php artisan test` (169/169 passed, 767 assertions)
- Manual verification via local preview: mega menu dropdown, mobile bottom nav rendering (confirmed via computed `display: grid` at 375px width since the screenshot tool was unavailable this session), category/product data on the homepage.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Visual Redesign and Admin Homepage Content Settings

Reason:

- The storefront was functionally complete but visually read as a template rather than a considered Shopify-quality ecommerce site: no real light/dark toggle (dark: classes only followed OS preference), heavy `font-black`/`rounded-full`-everywhere styling with little hierarchy, no quick-add hover interaction or quantity stepper, no sort/filter on the product listing, no related products, and hero copy hardcoded in the blade file instead of admin-editable.

Changed files:

- `resources/css/app.css`
- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/index.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `resources/views/storefront/account/orders.blade.php`
- `resources/views/storefront/track/show.blade.php`
- `resources/views/storefront/pages/show.blade.php`
- `app/Http/Controllers/Storefront/ProductIndexController.php`
- `app/Http/Controllers/Storefront/ProductShowController.php`
- `app/Http/Controllers/Storefront/PreviewController.php`
- `app/Models/StorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `database/migrations/2026_07_03_000000_add_hero_and_theme_fields_to_storefront_settings_table.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added a real class-based dark mode toggle: `@custom-variant dark` in `app.css`, an inline pre-paint script plus a header sun/moon button in `layout.blade.php` that toggles `<html>.dark` and persists the choice to `localStorage`, defaulting to a new `storefront_settings.theme_mode` (`system`/`light`/`dark`) on first visit. Implemented in vanilla JS since no Alpine.js is loaded in the storefront Vite bundle.
- Replaced the `font-black` + `rounded-full`-everywhere visual language across all storefront views with a restrained hierarchy (semibold/medium weights, `rounded-lg`/`rounded-xl` components, thinner gray borders) closer to an actual Shopify storefront.
- Added a quick-add hover button on product cards, a vanilla-JS quantity stepper (`+`/`-`) on the product detail and cart pages, a sticky buy box on product detail, and a sticky order summary on cart/checkout.
- Added product listing sort (`?sort=price_asc|price_desc`, default newest) and category quick-filter chips.
- Added a "You may also like" related-products rail on product detail (same category, excludes current product, limit 4).
- Added admin-editable homepage hero fields (`hero_heading`, `hero_subheading`, `hero_cta_label`) and a `theme_mode` default select to the Storefront Settings form, under a new "Homepage Content" section; blank values fall back to the previous hardcoded copy.

Verification:

- `php artisan migrate --force` applied the new `storefront_settings` columns.
- `npm run build` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed (20 tests).
- `php artisan test --filter=PhaseFourAdminPagesTest` passed (3 tests).
- Manually verified via a local `php artisan serve` preview: home, product listing (with sort/filter chips), product detail (stepper + related products), cart, and checkout in both light and dark mode, and at mobile (375px) and desktop (1440px) widths.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Settings Form Synchronization

Reason:

- Storefront Settings list showed domain and readiness fields, but the edit page did not expose every underlying setting, creating a synchronization gap for admins.
- A duplicate domain such as `zamzamgadgetbd.com` could still hit the database unique constraint when assigned to the wrong company from the Storefront Settings form.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added a Filament default `Domain and Launch Readiness` section to the Storefront Settings form.
- Exposed `Storefront Domain` and `Domain verified` in Storefront Settings create/edit forms.
- Added read-only readiness, missing setup, visible products, and published pages summaries in the edit form.
- Synchronized `company_domain` and `company_domain_verified` form fields back to `companies.domain` and `companies.domain_verified` on create/save.
- Added tests that verify the edit form shows the same readiness-related options as the list and that saving Storefront Settings updates the company domain fields.
- Added duplicate-domain validation before saving company domain fields so admins get a form error instead of a 500.

Verification:

- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Admin Launch Dashboard

Reason:

- The storefront site polish existed on the public side, but admins still needed a Filament-default dashboard surface to see whether each storefront is ready to launch.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added launch-readiness columns to Storefront Settings using Filament default table UI.
- Added missing setup visibility for publish/domain/logo/banner/SEO/WhatsApp/pages/products.
- Added visible product count and published page count per storefront.
- Added domain verified visibility.
- Added default Filament record actions for Preview, Open Site, and Pages.
- Updated admin page test coverage for the new storefront dashboard surface.
- Updated `PROJECT_GUIDE.md` with the admin launch dashboard behavior and verification command.

Verification:

- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-02 - Storefront Part 4 Site Polish

Reason:

- WooCommerce migration is intentionally deferred until after the storefront site work is complete.
- Part 4 still needed customer-facing polish, launch readiness cleanup, and storefront verification without changing admin/dashboard UI.

Changed files:

- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `tests/Feature/StorefrontFoundationTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added SEO/Open Graph/Twitter metadata support from storefront settings.
- Added banner-image hero support and removed decorative gradient/orb styling from the storefront hero.
- Made the storefront header more mobile-safe by truncating long company names and hiding lower-priority actions on small screens.
- Added footer WhatsApp contact visibility for mobile users.
- Replaced internal/roadmap wording in cart and homepage copy with customer-facing storefront copy.
- Added clearer out-of-stock labels and disabled button text on product cards and product detail pages.
- Improved checkout success action wrapping for mobile screens.
- Added storefront test assertions for the public announcement and Open Graph metadata.
- Updated `PROJECT_GUIDE.md` with the polished storefront behavior and local smoke-check routes.

Verification:

- `php artisan test --filter=StorefrontFoundationTest` passed.
- `npm run build` passed.
- Local HTTP smoke checks returned `200` for `/storefront`, `/storefront/main-company/products`, `/storefront/main-company/cart`, `/storefront/main-company/track`, and `/storefront/main-company/account/orders`.
- Browser connector was unavailable due an environment metadata error, so screenshot-based visual QA could not be completed in this run.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-06-29 - Agent Rule: Filament Default Dashboard UI

Reason:

- Dashboard/admin UI should stay consistent and maintainable by using Filament's default UI system.

Changed files:

- `AGENTS.md`
- `UPDATE_NOTES.md`

What changed:

- Added a project rule that dashboard/admin UI must use Filament default components and patterns only.
- Clarified that even custom dashboard modules or elements should be built with Filament default UI rather than custom-styled dashboard components.

Verification:

- Documentation-only change; no tests required.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-06-29 - Courier Pending Live Adapter Guardrail

Reason:

- Part 4 storefront verification was complete, so the next safe Part 7 courier step was to prepare Pathao, RedX, and E-Courier adapter boundaries without pretending live API integrations are ready.
- Official merchant API credentials, request field mappings, and sandbox/live response samples are still required before enabling live Pathao, RedX, or E-Courier booking/sync/webhook flows.

Changed files:

- `app/Services/CourierManager.php`
- `app/Services/Couriers/PendingLiveCourierAdapter.php`
- `app/Services/Couriers/PathaoCourierAdapter.php`
- `app/Services/Couriers/RedxCourierAdapter.php`
- `app/Services/Couriers/ECourierAdapter.php`
- `tests/Feature/CourierIntegrationTest.php`
- `PROJECT_GUIDE.md`

What changed:

- Added explicit pending live adapters for Pathao, RedX, and E-Courier.
- Wired those adapters into `CourierManager`.
- Pending providers now fail live booking/sync/balance/webhook operations with a clear setup message instead of an ambiguous missing-adapter error.
- Added a feature test that confirms pending live providers resolve but reject booking until official API details are supplied.
- Updated `PROJECT_GUIDE.md` with the new courier adapter status and verification note.

Verification:

- `php artisan demo:refresh` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.
- `npm run build` passed.
- `php artisan test --filter=CourierIntegrationTest` passed.
- `php artisan test` passed: 167 tests, 737 assertions.

Commit status:

- Not committed. Commit and push require explicit user approval.
