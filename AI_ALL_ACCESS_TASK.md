# Task: Turn the AI All-Access coming-soon page into a live product

## Read first, in this order
1. CLAUDE.md
2. PROJECT_CONTEXT.md
3. docs/DEVELOPERJET_MASTER_EXECUTION.md
4. docs/EXECUTION_CHECKLIST.md and docs/HANDOFF.md
5. The current AI All-Access page: its route, controller and Blade view (route path /ai-all-access)

Follow every rule in those files. If this task conflicts with them, stop and report the conflict instead of guessing.

## Goal
Replace the "Coming soon / Request early access" page at /ai-all-access with a live sales page. A client buys a plan, gets an account, and uses a chat workspace with Claude, OpenAI (ChatGPT), Gemini and Grok models. DeveloperJet provides the API access. The admin assigns provider API keys to each client subscription. Clients never see or enter provider keys.

Plans, prices, names, features and limits are NOT hardcoded. They are stored in the database and edited by the admin from the admin panel. The sales page reads everything from the database.

## Hard rules
- Do not push to Git. Do not touch the VPS or any live server.
- Use payment gateway TEST mode only. No live keys.
- Preserve all existing data. Add new migrations only. Do not edit old migrations.
- Never print, log, commit or display full API keys or secrets. Put placeholders only in .env.example.
- Store provider keys with Laravel's encrypted cast. Show only the last 4 characters after saving. Never show the full key again.
- Mobile first. Check layout at 320px, 390px and 768px widths.
- Do not copy wording or design from other companies' products. Use the existing project styles.

## Phase 1: Plan
Write docs/AI_ALL_ACCESS_PLAN.md before coding. List the files to change, new migrations, routes, and tests. Then continue straight into Phase 2 without waiting for approval.

## Phase 2: Database-driven plans (placeholders)
- Create the ai_plans table and a seeder that inserts 3 placeholder plans so the page is never empty:
  - Plan 1 name: "AI Access Starter" | price: "Price to be confirmed" | seats: 1 | monthly message limit: 500 | models: Claude, ChatGPT, Gemini, Grok (set by admin per plan)
  - Plan 2 name: "AI Access Team" | price: "Price to be confirmed" | seats: 5 | monthly message limit: 2000
  - Plan 3 name: "AI Access Business" | price: "Price to be confirmed" | seats: 20 | monthly message limit: 8000
- Each plan has: name, slug, tagline, monthly_price (nullable decimal), yearly_price (nullable decimal), currency (INR), seats, monthly_message_limit, providers (json: which of claude, openai, gemini, grok are included), feature_list (json array of short text lines), is_featured (bool), sort_order, is_active.
- When a price is null, show "Price to be confirmed" and disable the Buy button.
- Do not create config/ai_access.php for plan data. Config may hold only the gateway settings and the list of provider names.

## Phase 3: Sales page (frontend)
Build the page in the same sections a visitor expects from a hosting or subscription sales page. Each section reads from the database or from a simple content file where marked:
1. Top bar: DeveloperJet logo, Products, Pricing, Documentation, Support, Login, Get Started. Keep existing links.
2. Hero: headline "One workspace for the AI models you already use." Subtext: DeveloperJet provides the workspace and the API access, and you choose a plan. Buttons: "Choose a plan" (scrolls to pricing) and "How it works".
3. Model strip: four cards for Claude, ChatGPT, Gemini and Grok. Each card shows a one-line description. Cards for providers not included in a plan are shown with an "Available on higher plans" label on the pricing section only.
4. "What you get": a grid of features such as model switching, side-by-side comparison, seats and permissions, chat history, ready templates, speech-to-text. Items come from a content file so they are easy to edit.
5. "How it works": four steps: 1) Choose a plan, 2) Pay securely, 3) Get your login and workspace, 4) Pick a model and start chatting.
6. Pricing: cards for every active plan, from the ai_plans table, sorted by sort_order. Featured plan has a badge. Monthly and yearly toggle. Each card shows the price, seats, message limit, included models, and the feature_list. Buy button goes to checkout.
7. Compare table: rows are Price, Seats, Monthly messages, Models, Support. Columns are the active plans. All values come from the database.
8. "Included / Not included": included items come from the plan data. Not included items are a fixed list: consumer chat subscriptions (ChatGPT Plus, Claude Pro, Gemini Advanced), signing in with personal chat accounts, and anything beyond the monthly message limit.
9. Fair use: a short note that usage above the monthly message limit needs an upgrade. Do not use the word "unlimited".
10. FAQ: accordion. Questions: How are provider keys managed? Who pays the AI provider? What happens when I reach my limit? What happens if I cancel? How do I add team members? How do I get support? Answers come from a content file.
11. Footer: links to Terms, Privacy, Refund policy, Support. If a page does not exist, leave the link out.
12. Remove the "Coming soon" badge, the "In development" labels, and the "Request early access" button. Remove the Provider A/B/C diagram.
13. Replace the old line "DeveloperJet does not resell or mark up model usage" with wording that matches the final billing model. Mark it with a TODO comment for the owner to confirm.

## Phase 4: Admin plan editor
- Admin panel page: list plans, create, edit, activate or deactivate, and reorder.
- Editable fields: name, slug, tagline, monthly price, yearly price, seats, monthly message limit, included providers (checkboxes), feature list (one line per row), featured flag, sort order, active flag.
- Validation: name and slug are required. Prices must be empty or positive numbers. Slug must be unique.
- Every change is logged in the existing AuditLog model.
- Only admin users can access this page.

## Phase 5: Data model for subscriptions
Create migrations and Eloquent models for:
- ai_subscriptions: user_id, ai_plan_id, billing_cycle (monthly or yearly), status (pending, active, past_due, cancelled, expired), gateway_subscription_id, current_period_start, current_period_end, grace_until
- ai_seats: ai_subscription_id, user_id, role (owner or member), is_active
- ai_provider_keys: ai_subscription_id, provider (claude, openai, gemini, grok), encrypted key, last4, is_active, rotated_at
- ai_conversations and ai_messages: store provider, model, input_tokens and output_tokens on each message
- ai_usage_logs: ai_subscription_id, user_id, provider, model, tokens, created_at

## Phase 6: Payment and provisioning
- Create checkout for each plan using the gateway's test mode (Razorpay if the project already has it, otherwise follow the existing payment setup).
- Webhook route: verify the signature, store the raw event, and process it idempotently. The same event must never activate a subscription twice.
- On successful payment: activate the subscription, create the user if needed, create the owner seat, send a login setup email with Laravel Mail, and notify the admin.
- On failed payment: mark past_due with a 3-day grace period. After that, suspend.
- On cancellation: keep access until the end of the paid period.
- Add a daily command (ai:expire-subscriptions) and register it in routes/console.php.

## Phase 7: Client workspace
- Route: /ai/workspace, behind login and an active subscription check.
- Show: model selector (only providers assigned to this subscription and included in the plan), chat box, conversation list, and usage this month against the limit.
- Create an AiProviderService with one adapter per provider using Laravel's Http client. Keys are decrypted only inside this service. Set timeouts and show friendly error messages.
- Check the monthly limit before every API call. When the limit is reached, show a clear message.
- Streaming is optional. A normal request/response is enough for the first version.

## Phase 8: Admin subscription panel
- List all AI subscriptions with client, plan, status and usage.
- Assign or rotate a provider key per subscription using a write-only form. After saving, show only the last 4 characters.
- Suspend and reactivate subscriptions.
- Log every key change and suspension in the existing AuditLog model.
- Only admin users can access these pages.

## Phase 9: Tests and report
- Feature tests for: pricing page renders from the database, placeholder plans show "Price to be confirmed", admin can edit a plan and the page updates, webhook signature check, webhook idempotency, purchase activates a subscription, limit enforcement, key masking, admin-only access, expiry command.
- Run the full test suite and fix failures.
- Update docs/EXECUTION_CHECKLIST.md and docs/HANDOFF.md after each phase.
- Final report: what changed, what is test-only, and what the owner must provide: plan names and prices (via admin panel), provider API keys, live payment keys, and a check of each provider's terms for resale.

## Output rule
Do not stop to ask questions in the middle of the work. If a value is missing (for example, a price), use the placeholder from Phase 2, mark it clearly, and continue.
