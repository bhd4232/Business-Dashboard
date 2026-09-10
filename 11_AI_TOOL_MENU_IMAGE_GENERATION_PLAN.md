# 11 — AI Tool Menu: Image Generation & Prompt Enhancer (Module Plan)

Status: **Phases 0–5 built & verified**, incl. Phase 4's background-removal and image-to-image regeneration. Only follow-up left: upload-your-own reference image from the form (vs. regenerating an existing library image). Written from a planning conversation in Claude Cowork; intended as the pre-execution reference for Claude Code Plan Mode.

**Execution deviation from §4/§11:** provider/model/key config was NOT added as tabs inside `Integrations.php`. It lives on dedicated super-admin pages inside the AI Tools cluster instead — **Image Providers**, **Prompt Enhancer**, **Image Governance** — so the whole feature is self-contained and does not depend on another session's in-flight `Integrations.php` / `AiSettingsService` refactor. `PromptEnhancerConfigService` and `ImageProviderSettingsService` each own their own `companies.settings->ai_tools->*` key directly; `AiSettingsService` is untouched.

## 1. Overview

The Business Dashboard gets a new **Tool Menu** — a central hub for AI-powered utility tools, separate from the per-module AI features that already exist (Ad Assistant, Landing Page Builder, Auto-Messaging). The first tool built is **Image Generation**, paired from day one with a shared, reusable **Prompt Enhancer** that will also serve future tools.

**In scope for this document:**
- The Tool Menu hub shell (navigation cluster + card grid + permission gating)
- The Image Generation tool end-to-end (provider config, generation, storage, gallery, attach-to-record actions)
- The Prompt Enhancer as a shared component, wired into Image Generation's prompt box

**Explicitly out of scope (future documents):** Video Generation and Content Creation tools. This plan only reserves their Tool Menu card slots and permission keys so role setup done now doesn't need revisiting later.

### 1.1 Why this is genuinely new territory

Every AI feature currently in the app (`AiLlmClient`, `AiSettingsService`'s three existing tools) is **text generation** — chat replies, ad copy, landing page copy. Image Generation is the app's first real image-generation capability, so while the *configuration pattern* (per-company, per-tool, admin-supplied API key, encrypted at rest) carries over directly, the *client code* cannot simply reuse `AiLlmClient` — image APIs are not chat-completion shaped. The Prompt Enhancer, by contrast, **is** text generation and reuses `AiLlmClient` as-is.

## 2. Decisions Locked In

These were confirmed during planning and should not be re-litigated without going back to the user:

| Decision | Choice |
|---|---|
| Primary use cases | Product/catalog images, marketing/ad creatives, offer/landing page banners, general-purpose use, reference images for a future AI video-generation tool |
| Image provider strategy | **Multiple** provider profiles per company, admin-configured with their own API keys (BYO key, matching existing `AiSettingsService` philosophy); the user picks which configured profile to use at generation time |
| Access control | **Role-based** — gated by permission, not open to all staff by default |
| Prompt Enhancer trigger | **Manual** "✨ Enhance" button next to the prompt box; shows a before/after comparison; user explicitly accepts or reverts — never silent/automatic |
| Prompt guide authorship | **Both** — hardcoded expert-written defaults ship in code; admin can override any context's guide text from Filament without a code change |
| Prompt Enhancer model-awareness | **Provider-aware** — the enhancement adapts its style to whichever image provider/model is selected downstream (e.g. natural-language phrasing for OpenAI vs. keyword/tag style for Stability-family models) |
| Prompt Enhancer permission | **Bundled** with the parent tool's permission — no separate permission key. Anyone who can use Image Generation automatically gets its Enhance button. |

## 3. How This Fits Existing Architecture

- **`AiSettingsService`** already stores per-company, per-tool AI config at `companies.settings->ai_tools->{tool}`, with the API key encrypted via `Crypt` and a documented legacy-fallback pattern (a tool that was never individually configured falls back to the old shared `settings->ai` blob). This plan adds two more tool keys — `image_generation` and `prompt_enhancer` — following the same storage convention, though `image_generation`'s shape must become a **list** of provider profiles rather than one flat config (see §6.1).
- **`AiLlmClient`** is a format-agnostic (Anthropic Messages API / OpenAI Chat Completions shape) text client. `prompt_enhancer` reuses it directly with zero new HTTP-client code. `image_generation` needs new provider adapters entirely (§7).
- **`Integrations` page** (`app/Filament/Pages/Integrations.php`) is already the single place every integration's *configuration* lives, while dedicated pages like `MetaAdsAiAssistant.php` are where the AI is actually *used*. This plan keeps that split: provider/model config for both new tools lives on Integrations; the Tool Menu hub and Image Generation's working UI are new, separate pages.
- **`PaymentGatewayResolver.php`** is an existing precedent for "pick an implementation by a stored key" — `ImageProviderResolver` (§7) follows the same shape for picking an image provider adapter by `api_format`.
- **CLAUDE.md rules that apply here:** external credentials must be admin-configurable encrypted settings, never hardcoded (already the plan); every new company-owned model must use `BelongsToCompany` + `CompanyScope` and be registered in `MultiCompanyIsolationTest`; tests must never touch demo/dev data and must mock all HTTP calls; nothing gets committed/pushed without explicit owner approval.

## 4. New Filament Structure

| Type | Path | Purpose |
|---|---|---|
| Cluster | `app/Filament/Clusters/AiTools.php` | New top-level navigation cluster ("AI Tools" or "Tools") |
| Page | `app/Filament/Pages/ToolMenu.php` | The hub: a card grid, one card per tool. Cards the current user lacks permission for are hidden, not just disabled. Unbuilt tools (Video Generation, Content Creation) show as disabled cards with a "Coming Soon" badge. |
| Page | `app/Filament/Pages/ImageGeneration.php` | The actual tool: prompt form, Enhance button, provider/aspect-ratio/variation controls, results gallery, attach actions |
| Page (Filament sub-page or modal) | `app/Filament/Pages/PromptGuides.php` | Admin-only override screen for per-context/per-provider guide text (§6.1) |
| Integrations tab | inside existing `Integrations.php` | New tab(s): "Image Generation" (provider profile list) and "Prompt Enhancer" (single model config) |

`ToolMenu::canAccess()` requires `ai_tools.menu`; `ImageGeneration::canAccess()` requires `ai_tools.image_generation`; `PromptGuides::canAccess()` stays super-admin-only, consistent with how `Integrations::canManageAi()` already restricts AI settings to super admins.

## 5. Permissions

| Key | Gates | Notes |
|---|---|---|
| `ai_tools.menu` | Seeing/entering the Tool Menu hub at all | Individual cards are still filtered by their own key below |
| `ai_tools.image_generation` | Using the Image Generation tool (view + generate + attach) | Prompt Enhancer rides along automatically — no separate key |
| `ai_tools.video_generation` *(reserved)* | Future Video Generation tool | Not implemented yet; key reserved now so role configuration done today doesn't need revisiting |
| `ai_tools.content_creation` *(reserved)* | Future Content Creation tool | Not implemented yet; same reason |

Wire these into `User::ROLE_PERMISSIONS` (built-in roles) and expose them in the `UserRole` custom-role permission picker, exactly like existing keys such as `settings.manage` or `reports.view`.

## 6. Data Model

### 6.1 `companies.settings` JSON additions

```
settings.ai_tools.image_generation = [
  {
    "id": "uuid-or-slug",
    "label": "OpenAI - high quality",       // admin-chosen display name
    "api_format": "openai" | "google" | "stability" | "custom",
    "base_url": "",                          // blank = that format's default endpoint
    "model": "gpt-image-1",
    "default_size": "1024x1024",
    "is_default": true,
    "api_key": "<encrypted>"
  },
  ...
]

settings.ai_tools.prompt_enhancer = {
  // same flat shape as messaging/ad_assistant/landing_page today
  "enabled": true,
  "provider": "Anthropic (Claude)",
  "api_format": "anthropic" | "openai",
  "base_url": "",
  "model": "claude-haiku-4-5-20251001",
  "api_key": "<encrypted>"
}

settings.prompt_guides = {
  // present only where an admin has overridden a default — sparse map
  "image_generation.product_photo": { "guide": "...", "updated_by": 12, "updated_at": "..." },
  "image_generation.ad_creative": { ... },
  ...
}

settings.image_brand_style = {
  "note": "Warm, premium, natural light — avoid harsh studio flash..."
}
```

`image_generation` breaks from the other tools' flat-object shape because the product requirement is genuinely "admin configures several, user picks one," not "admin configures the one active provider." `ImageProviderSettingsService` (§7) encapsulates this list's CRUD so nothing else touches the raw JSON shape directly — same encapsulation discipline `AiSettingsService` already provides for the other tools.

`image_brand_style` is a per-company default style note, parallel to the `brand_voice` field the text tools already have. It gets folded into every Prompt Enhancer call automatically (§8) so the four ZamZam Group companies can each keep a distinct visual style without the user having to restate it per prompt.

### 6.2 New table: `generated_images`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | FK, indexed | `BelongsToCompany` |
| `user_id` | FK | who generated it |
| `tool` | string | e.g. `image_generation` (future-proofs reuse by other tools) |
| `context` | string | `product_photo` \| `ad_creative` \| `landing_banner` \| `video_reference` \| `general` |
| `original_prompt` | text, nullable | raw prompt before enhancement, if Enhance was used |
| `prompt` | text | final prompt actually sent to the provider |
| `provider_profile_id` | string | which configured profile was used (matches the `id` in §6.1) |
| `api_format` | string | `openai` \| `google` \| `stability` \| `custom` |
| `model` | string | |
| `reference_image_path` | string, nullable | uploaded reference image, if any |
| `aspect_ratio` | string, nullable | |
| `variations_requested` | unsigned int, default 1 | |
| `output_paths` | json | array of stored file paths (one generation can yield several images) |
| `linked_type` / `linked_id` | nullable morphs | polymorphic attach target: `Product`, `OfferPage`, `MetaAd`, or null |
| `is_video_reference` | boolean, default false | flagged via the "Use as video reference" action |
| `status` | string | `queued` \| `processing` \| `completed` \| `failed` |
| `error_message` | text, nullable | |
| `generated_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

New files: `app/Models/GeneratedImage.php` (uses `BelongsToCompany` + `CompanyScope`) and its migration. **Must** be added to `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract` per CLAUDE.md.

## 7. New Service Classes

```
app/Services/ImageGeneration/
  ImageGenerationClient.php          // interface: generate(prompt, opts): array{paths[], raw}
  ImageProviderResolver.php          // picks the adapter by api_format — same shape as PaymentGatewayResolver.php
  ImageProviderSettingsService.php   // CRUD over the provider-profile list in companies.settings (§6.1)
  Providers/
    OpenAiImageProvider.php          // OpenAI Images API
    GoogleImageProvider.php          // Google Gemini/Imagen predict endpoint
    StabilityImageProvider.php       // Stability text-to-image API
    CustomOpenAiCompatibleProvider.php // for any OpenAI-images-compatible self-hosted/third-party endpoint

app/Services/PromptEnhancement/
  PromptEnhancementService.php       // builds the system prompt (context guide + provider style note + brand style note) and calls AiLlmClient
  PromptGuideRepository.php          // resolves guide text: admin override (settings.prompt_guides) -> hardcoded default fallback

config/prompt_guides.php             // hardcoded default guide text per context, and per-provider style notes
```

`ImageGenerationClient` is intentionally a thin contract (not reusing `AiLlmClient`'s `chat()` shape) — the inputs (prompt, size, reference image, variation count) and outputs (binary/URL image data) are different enough that force-fitting the text shape would hurt more than it helps. Each provider adapter is `Http::fake()`-mocked in tests, mirroring the existing rule that `AiLlmClient` is "always mocked with `Http::fake()` in tests — never called live there."

`PromptEnhancementService::enhance(string $rawPrompt, string $contextKey, ?string $targetApiFormat, Company $company): string` is the whole public surface. Internally: `PromptGuideRepository` resolves the context guide text (admin override or hardcoded default), a provider-style note is appended if `$targetApiFormat` is known, the company's `image_brand_style.note` is appended if set, then `AiLlmClient::chat()` runs with that assembled system prompt and the raw prompt as the user message, returning the enhanced prompt text.

## 8. Jobs & Notifications

- `app/Jobs/GenerateImageJob.php` — queued (image generation commonly takes 10–60s, so this must not block the request). Resolves the chosen provider via `ImageProviderResolver`, calls `ImageGenerationClient::generate()`, optimizes each output through the existing `ImageOptimizerService`, stores them through the existing `CompanyStorageService`, writes/updates the `GeneratedImage` row, and notifies the requesting user on completion or failure via the existing `BusinessNotificationService`.
- The `ImageGeneration` Livewire page polls (or listens for a broadcast event, if one is easy to add) to refresh the gallery once the job completes, so the user doesn't have to manually reload.

## 9. UI/UX Flow

1. User opens **Tool Menu** (visible only with `ai_tools.menu`) and sees permission-filtered cards.
2. Clicks **Image Generation** (`ai_tools.image_generation` required).
3. Fills the prompt box, optionally uploads a reference image, picks a context (Product Photo / Ad Creative / Landing Banner / Video Reference / General), an aspect-ratio preset, a variation count, and a configured provider profile (or leaves the company default selected).
4. Optionally clicks **✨ Enhance** — sees the original vs. enhanced prompt side by side, accepts (replaces the textarea) or reverts.
5. Clicks **Generate** — a `GeneratedImage` row is created with `status=queued`, `GenerateImageJob` is dispatched, and the UI shows a pending state.
6. On completion, the gallery shows the resulting image(s) with actions: **Download**, **Save to Library**, **Attach to Product**, **Attach to Offer Banner**, **Attach to Ad Creative**, **Use as Video Reference**.

## 10. Attach-to-Record Integrations

| Action | Effect |
|---|---|
| Attach to Product | Adds the image to the product's existing image-gallery relation |
| Attach to Offer/Landing Page | Sets the offer page's banner/hero image field |
| Attach to Meta Ad | Attaches as an ad creative image, feeding into the existing Meta Ads creative flow |
| Use as Video Reference | Sets `is_video_reference = true` on the row; no Video Generation tool exists yet, so this only needs the flag plus a "reference images" listing the future tool can query — no UI beyond marking it today |

## 11. Phased Delivery Plan

**Phase 0 — Foundation**
- `AiTools` cluster, `ToolMenu` page (card grid, permission-filtered, "Coming Soon" cards for the other two tools)
- Add `ai_tools.menu`, `ai_tools.image_generation`, and the two reserved keys to the permission system

**Phase 1 — Image Generation core**
- `ImageProviderSettingsService` + Integrations "Image Generation" tab (add/edit/remove provider profiles, mark one default, encrypted keys)
- `generated_images` migration + `GeneratedImage` model (register in `MultiCompanyIsolationTest`)
- `ImageGenerationClient` contract + `ImageProviderResolver` + the three provider adapters (start with OpenAI + one more; adding further adapters later is cheap by design)
- `GenerateImageJob` + `ImageGeneration` page (form + queued generation + results gallery, no attach actions yet)

**Phase 2 — Prompt Enhancer (shared)**
- `prompt_enhancer` tool entry in `AiSettingsService`/Integrations
- `config/prompt_guides.php` with default guides for all five contexts and style notes for all three providers
- `PromptGuideRepository` + `PromptEnhancementService`
- `PromptGuides` admin override page
- Reusable "✨ Enhance" Livewire component wired into `ImageGeneration`'s prompt box (before/after UI)
- `image_brand_style` field wired into the enhancement call

**Phase 3 — Attach & handoff integrations** — ✅ built & verified
- `GeneratedImageAttacher` service (featured image / gallery / offer cover banner; `linked` morph stamped on each attach)
- Per-image "Add to a product" (featured or gallery) and "Set as an offer banner" actions + a per-generation "Mark as video reference" action on the Image Generation gallery
- Meta Ad handoff: the Meta Ads creative flow already derives its image from the advertised product's featured image, so "attach to Meta Ad" is realised as the "Set as the featured image" option — no separate dead ad-creative field was added
- `GeneratedImage::scopeVideoReferences()` — the queryable listing the future Video Generation tool needs

**Phase 4 — Productivity features** — partially built (library shipped; the two image-editing items deferred)
- ✅ Searchable generation library — `ImageLibrary` page in the AiTools cluster: thumbnail + prompt + context/provider/creator columns, filters (context, status, created-by, favourites, video-references, attached-to-a-record), row actions: **Reuse prompt** (opens Image Generation prefilled via `?from=<id>`), **favourite toggle**, **mark video reference**
- ✅ Favourites — `generated_images.is_favorite` + `scopeFavorites()`; a starred generation doubles as a reusable prompt template (no separate templates table), and the ✨ gallery on the tool page has a star toggle too
- ✅ Batch generation — already delivered in Phase 1 (the "How many images?" 1–4 control; `GenerateImageJob` loops the count)
- ✅ Background removal — `ImageGenerationRequest` gained an `operation` + `referenceImage`; `GeneratedImage.operation` column; Stability `remove-background` endpoint; per-image **Remove bg** action, shown only where a Stability provider is configured. New image, original kept.
- ✅ Image-to-image / reference regeneration — per-image **Regenerate** action (prompt + subtle/balanced/strong strength + 1–4 variations), feeding the finished output back as a reference. OpenAI + custom via `/v1/images/edits`, Stability via the SD3 `image-to-image` mode. Imagen has no simple img2img endpoint, so Google is generate-only (`supportsOperation()` on each adapter; `ImageProviderResolver::formatsSupporting()`; the job rejects an unsupported op cleanly).
- ⏸️ Upload-your-own reference from scratch (vs. regenerating an existing library image) — small follow-up: needs a FileUpload→company-storage step on the form.

**Phase 5 — Governance & polish** — ✅ built & verified (all mechanism, off by default)
- ✅ Usage/cost dashboard — `ImageGovernance` page (super-admin, AiTools cluster): this-month image count + estimated spend, broken down by person and by provider. Estimate uses each provider profile's admin-set "approx. cost per image" (a field on the Image Providers page); `GenerateImageJob` stamps `generated_images.estimated_cost` per completion. No billing API is called.
- ✅ Monthly cap per role — `ImageGovernanceService` + the same governance page. Per built-in role, 0 = unlimited (default). Enforced in `ImageGeneration::generate()` against the user's summed variation count for the calendar month (failed generations excluded); the tool also shows "N images left this month".
- ✅ Approval workflow — company flags roles whose output needs sign-off; those generations land `review_status = pending` (image still generates) and `GeneratedImageAttacher` blocks attach until a holder of the new `ai_tools.image_generation.review` permission approves/rejects them from the Image Library. Rejection carries a note back to the creator.
- ✅ Audit logging — explicit `AuditLogService::record()` on generate (`ai_image.generated` — prompt/provider/model/variations), attach (`ai_image.attached`), and review (`ai_image.review_approved` / `ai_image.review_rejected`). Shows in the existing Audit Logs resource. No shared-observer change.
- Per-user (vs per-role) caps and a spend budget ceiling are the natural follow-ups if the owner wants finer control.

## 12. Testing Plan

Per CLAUDE.md's verification rules:
- Add `GeneratedImage` to `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract`
- `Http::fake()` every provider adapter and every `AiLlmClient`/Prompt Enhancer call in feature tests — never a live call in tests, matching the existing rule for `AiLlmClient`
- Feature tests: permission gating on `ToolMenu`/`ImageGeneration`/`PromptGuides`; `GenerateImageJob` writes the expected `GeneratedImage` row and output paths; each attach action updates the correct target record; `PromptEnhancementService` assembles the expected system prompt (assert on the request body captured by `Http::fake()`) for a couple of context/provider combinations
- Run the affected test files plus the full `php artisan test` (no `--env` flag, per CLAUDE.md), and `npm run build` since this touches frontend assets

## 13. Open Questions / Follow-Ups

- Exact provider/model shortlist for the first release — the adapter pattern makes adding more later cheap, so shipping with just two (e.g. OpenAI + one more) and expanding is reasonable
- Confirm timing for Phase 5 governance features (cost dashboard, caps, approval workflow) before starting them — they were accepted into the plan but not prioritized against Phases 1–4
- Video Generation and Content Creation each need their own planning pass when their turn comes; this document only reserves their permission keys and Tool Menu card slots so nothing here has to be redone
