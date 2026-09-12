# CRM and AI sales automation audit

Date: 2026-09-09

Scope: current working tree, including existing uncommitted changes. Static review of CRM models, messaging and AI services, queue jobs, Inbox presence/composer logic, follow-up command, broadcast delivery, and chat/quotation conversion. No application behavior changed. Production configuration, live Meta delivery, model answer quality, and browser layout were not tested.

## Assessment

The module already has a useful foundation: leads and activities, quotations, customer/order conversion, channel-based inbox, product/FAQ/delivery tools, chat checkout links, follow-up reminders, broadcasts, company context in queue jobs, encrypted AI settings, source-message deduplication, and human escalation. AI currently primarily reacts to inbound text. It does not yet implement a complete qualification, sales progression, and measured follow-up workflow.

## Findings

Findings below are code-path observations. Concurrency and customer-facing leakage are risks inferred from those paths, not observed production incidents. Existing passing feature tests do not prove these scenarios safe.

### High: human presence expires before renewal

- `app/Models/Conversation.php:24`: presence TTL is 30 seconds.
- `app/Filament/Pages/Inbox.php:487`: polling refuses renewal until 60 seconds have passed.
- An open, visible thread therefore has an unprotected interval after presence expires.
- Renew well before expiry; test continuous visible polling across 30, 45, and 60 seconds.

### High: handoff does not persist exclusive human ownership

- `app/Services/Crm/AiReplyService.php:266`: escalation only sets `status = pending` and notifies staff.
- `maybeReply()` does not reject pending conversations. A subsequent ordinary inbound message can invoke AI again.
- `sendAiReply()` also does not refresh/recheck human presence, human pause, or AI enablement after a potentially long LLM run.
- Add explicit automation state and a deliberate resume action, plus a final eligibility check immediately before dispatch. Verify complaint -> normal question remains handed off, and staff takeover during inference prevents sending.

### High: internal notes enter customer-reply context

- `app/Filament/Pages/Inbox.php:596` saves notes as outgoing messages with type `note` and delivery status `internal`.
- `app/Services/Crm/AiReplyService.php:302` loads the latest messages without excluding notes, internal activity, or failed outbound attempts, and treats all outgoing rows as assistant statements.
- Private staff text is consequently submitted to the LLM and could influence or appear in a customer reply. Notes also affect consecutive-reply counting.
- Build customer-visible context explicitly; keep staff context separate and purpose-limited. Test the exact LLM request payload excludes internal notes and failed messages.

### High: concurrency protection is per message, not conversation

- `app/Jobs/AiAutoReplyJob.php:25` and `:59` use source-message identity for uniqueness and locking.
- Two separate inbound messages in one conversation can run simultaneously, using overlapping latest-ten-message context and independently sending replies.
- Add a conversation-level lock and a short debounce with a durable inbound watermark. Revalidate that the planned response is still current. Do not simply drop the second job when a lock is occupied.
- AI metadata is saved after transport delivery (`AiReplyService.php:243`), leaving a crash gap where an accepted send may lack source identity. Persist origin/source metadata before the external call and reconcile uncertain delivery outcomes.

### High: price verification is incomplete

- `app/Services/Crm/AiReplyService.php:549` checks only recognized currency patterns against a flat list of amounts.
- An answer such as `BDT 999` or a spelled-out amount can escape detection; a price from product A may be assigned to product B if that amount is in the list.
- Stock, discounts, delivery promises, and reply URLs have no comparable code-level evidence validation. `used_product_ids` is declared but not checked.
- Return structured claims containing product/variant IDs, quantity, unit price, delivery quote, and allowed order-link ID; validate these with authoritative services and render monetary facts deterministically. Treat model confidence as a signal, not proof.

### High: opt-out enforcement is inconsistent

- `app/Console/Commands/SendLeadFollowUpReminders.php:22` and `:59` do not check `opted_out_at` before customer follow-up delivery.
- `BroadcastService::buildRecipients()` checks opt-out at audience construction, but `sendToRecipient()` does not refresh it at actual send time. Opting out after a list is built can therefore be missed.
- Centralize contact/channel suppression and check it immediately before each business-initiated send. Maintain staff reminders independently of permission to message customers.

### High: conversion can create duplicate orders under concurrent requests

- `app/Http/Controllers/ChatOrderController.php:39` checks link usability before its transaction; the transaction does not lock/recheck the link before creating an order.
- `app/Services/Crm/LeadConversionService.php:60` similarly checks `converted_order_id` before entering its conversion transaction.
- Concurrent requests can both pass the initial check and create separate orders. A transaction alone does not serialize that decision.
- Lock and recheck the source record inside the transaction; add a durable unique conversion key and test concurrency on the production database engine.

### Medium: AI checkout cannot select variants

- Product lookup returns variants, but `create_order_link` accepts only product ID and quantity (`AiReplyService.php:337`, `:515`). Its prefill uses the parent selling price without a variant ID.
- The checkout controller already supports `product_variant_id`, making this a specific AI-tool integration gap.
- Ask for required options, validate the selected variant, and use the same pricing/availability service as checkout. Extend to multiple items and offers after single-item behavior is reliable.

### Medium: qualification and follow-up have limited state

- Lead fields include interest, estimated value, owner, stage, and next follow-up date, but the AI tools do not read/update a structured qualification record or schedule a sales task.
- AI context is limited to ten messages without a durable summary or confirmed preferences.
- Follow-up processing excludes unassigned leads and marks a reminder processed even when customer messaging is unavailable or unsuccessful. Staff notification and outbound delivery should have separate outcome records.
- Add an unassigned-lead queue, persisted qualification, and distinct retryable customer delivery attempts.

### Medium: execution and measurement budgets are incomplete

- Up to six AI rounds each allow a 60-second HTTP timeout; the AI job has no explicit timeout or failure callback. Worker settings were not inspected in production.
- Queue retry defaults are 90 seconds; `SendBroadcastJob` declares a 1,800-second timeout. Deployment must deliberately align retry/visibility and worker timeouts. Broadcast recipients are not atomically claimed before delivery.
- Successful AI replies store usage/tool traces, but skipped/failed/escalated runs lack a uniform run ledger and the messaging settings have no daily spend ceiling.
- Add per-run deadline, token/tool limits, company daily budget, rate limiting, explicit failure handoff, and run outcomes including latency and cost. Split large broadcasts into recipient jobs with claims and delivery reconciliation.

### Needs external verification: messaging windows

`Conversation::replyWindowHours()` gives every `ctwa_ad` conversation 72 hours measured from its latest inbound message. It does not track a distinct ad-entry eligibility/activation/expiry event. Validate this against the current Meta account/API rules before automating delayed follow-ups; this audit did not establish the live policy with an accessible primary Meta source.

## Recommended delivery sequence

### Phase 1: reliable automation controls

Implement presence renewal, persistent human handoff, final send eligibility, internal-note exclusion, conversation serialization, structured fact validation, contact suppression, and conversion idempotency first. Keep all new admin controls in native Filament forms, tables, actions, and notifications.

Acceptance: no customer send during human ownership; no internal note in LLM/customer context; no duplicate conversion; opted-out contacts receive no automated sales follow-up; supported price claims cannot bypass validation.

### Phase 2: sales qualification and assisted closing

- Persist product/variant interest, quantity, budget, delivery area, purchase timeframe, language, and missing required details with evidence message IDs.
- Ask one useful missing question at a time. Start with transparent scoring rules for hot/warm/cold leads; store the reasons and permit staff correction.
- Add guarded tools: read customer history, update qualification, propose next action, create variant-aware cart/link, and draft quotation. Use existing ERP services for prices, discounts, stock, and order transitions.
- Store a rolling summary plus confirmed facts; keep speculative model inferences distinct.
- Offer a staff-review mode showing suggested replies and the supporting product/FAQ data before enabling automatic sends for a scenario.

Acceptance: a Bengali or Banglish enquiry can progress through required option selection to the correct checkout; ambiguous requests prompt clarification; requested discounts outside configured rules go to staff.

### Phase 3: event-driven follow-up

- Trigger follow-up from link sent/opened, no checkout, unanswered quotation, and a promised callback time.
- Check purchase, new inbound activity, human ownership, opt-out, delivery eligibility, cooldown, and maximum attempts immediately before sending.
- Cancel obsolete scheduled actions after purchase, reply, or staff takeover. Use approved templates where required by the verified channel rules.
- Assign unattended high-intent leads and escalate overdue human tasks with a conversation summary and recommended next action.

Acceptance: buying or replying cancels stale reminders; each scheduled action has a recorded trigger, suppression reason or delivery result; retries do not duplicate sends.

### Phase 4: quality and business measurement

- Add native Filament reporting for qualified leads, link-open/checkout conversion, actual completed-order revenue, time to first response, handoff resolution, unsupported-claim rate, opt-outs, and AI cost per completed order.
- Separate draft orders and lead `won` status from realized revenue; current chat/quotation conversion marks leads won on draft-order creation.
- Evaluate anonymized Bengali/Banglish scenarios: typo product names, variants, multi-message bursts, refund requests, price manipulation, private notes, stale stock, provider outage, and duplicate checkout.
- Combine deterministic assertions with reviewed output quality. Run a limited cohort before broader rollout and compare against a baseline rather than assuming revenue improvement.

This incremental workflow-first recommendation is consistent with [Anthropic's agent architecture guidance](https://www.anthropic.com/engineering/building-effective-agents). Their [agent evaluation guidance](https://www.anthropic.com/engineering/demystifying-evals-for-ai-agents) also supports evaluating trajectories and outcomes rather than relying on model self-reported confidence.

## Verification

- First focused run: 83 tests passed, 345 assertions. Files: `AiAutoReplyTest`, `AiSettingsServiceTest`, `LeadTest`, `LeadConversionTest`, `LeadFollowUpReminderTest`, `ConversationIngestTest`, `MetaMessagingReliabilityTest`, `InboxPageTest`.
- Second focused run: 24 tests passed, 85 assertions. Files: `QuotationTest`, `ChatOrderLinkTest`, `BroadcastTest`, `InboxSmoothnessTest`. Combined: 107 passed, 430 assertions.
- These use the configured isolated SQLite in-memory test database; live LLM/Meta behavior and production concurrency are outside this result.
