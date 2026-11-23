# Sales team operations — Business Automation leads

Last verified: 2026-11-21 (automated tests on an isolated SQLite database; no production verification).
Targeted suites: `LeadWorkflowTest` 9 passed, `BusinessAutomationTest` 14 passed. Full suite at that check: **391 passed / 3310 assertions**.

## 1. Who can do what

All staff sign in at the same configurable URL (`/staff/{slug}/login`, default slug `developerjet`) on the separate `staff` guard. Permissions are enforced server-side on every lead action; the sidebar only hides links, it never grants access.

| Role | Leads permissions (default) | What that means |
|---|---|---|
| Super Admin | everything (implicit) | Complete authorised administration, including assigning any owner and changing the staff login URL. |
| Administrator | `leads.*` | Sees every lead; assigns sales and technical owners; works leads; exports leads. |
| Sales Staff | `leads.view`, `leads.edit` | Sees only leads they own (sales owner, technical owner or follow-up owner); works their own leads; cannot assign other people and cannot see unassigned leads. |
| Technical Support | `leads.view` | May read a lead they are assigned to as technical owner; cannot edit, change status, add notes or assign. Cannot see supplier costs or revenue (`supplier_costs.view` is not granted). |
| Developer | `leads.view` | Read-only visibility on owned leads, as for Technical Support. |
| Billing Staff, SEO Manager | none for leads | Cannot open the leads list or a lead detail page (403). |

`leads.assign` is the dividing line: with it a member sees every lead; without it they see only leads they own (`AutomationRequest::visibleTo()`). Converting a lead additionally requires `projects.create`. Leads export (`leads.export`) is a separate permission and the CSV excludes phone numbers.

Technical Support must not see supplier costs or financial margins by default: the provider-offers list hides the "Our cost" column and cost values without `supplier_costs.view`, the cost field is stripped from the edit form, and a submitted cost value is ignored server-side (tested in `LeadWorkflowTest`). No role except Super Admin / Administrator is granted payment secrets or root credentials.

## 2. Working the queue

Admin → Automation leads (`admin.leads.index`). Filters: status (including `open`), mine, due follow-ups, free-text search by reference, name, email or company. Counters show new, open, due and accepted leads visible to the signed-in member.

The admin dashboard shows a sales panel for members with `leads.view`: new unassigned leads (only when they can assign) and "my Business Automation leads" due for follow-up.

Lead detail (`admin.leads.show`) shows the customer and request (reference, name, email, phone, business type, company, website, size, tools, integrations, timeline, budget, plan, requirements, notes), the linked customer account if any, the quotation, owners, follow-up, project link and the full internal timeline. Internal notes and contact records are shown here only; they are never shown to the customer.

## 3. Day-to-day workflow

1. **New request** arrives (guest or signed-in customer). Notifications are queued automatically; nothing needs to be done manually to acknowledge it.
2. **Review** the requirements. Move New → Reviewing when work starts.
3. **Assign** a sales owner (and optionally a technical owner) — needs `leads.assign`. Assigning from New/Reviewing moves the lead to Assigned to Sales automatically. Only staff who can view leads (`leads.view`, active staff) can be assigned.
4. **Discuss requirements.** Use "Request more information" to email the customer a question; the lead moves to Requirements discussion and the message is recorded as a customer-visible event. Record calls and meetings with "Contact customer" (internal record, channel included).
5. **Prepare the quotation.** Enter an optional amount (rupees; stored as paise) and a scope summary, then save. This moves the lead to Quotation prepared. A scope-only quotation (no amount) is allowed.
6. **Send the quotation** (status → Quotation sent). The customer is emailed, sees the amount and scope on their dashboard, and accepts or declines there. Staff may also record a decision the customer communicated by email/phone (status → Customer accepted / Customer declined); that path records `decision_source = recorded_by_staff`, while a dashboard decision records `customer_dashboard`.
7. **Convert** an accepted lead (needs `projects.create`). If the lead is a guest lead, first link it to the verified customer account with the same email; conversion is refused until then. Conversion opens a project in the existing digital-services project workflow and marks the lead Converted to project. It is idempotent: converting again returns the same project and creates nothing new.
8. **Close** a lead when work is finished or abandoned. A closed lead can be reopened to Reviewing.

Illegal moves are rejected: a quotation cannot be sent before it is prepared, a lead cannot jump to Converted, and a quotation cannot be prepared once a decision has been recorded.

## 4. Follow-ups

Each lead can have one follow-up owner and date. Setting a follow-up to someone other than yourself (or the current sales owner) requires `leads.assign`. The queue's "due" filter and the dashboard panel list open leads whose follow-up date is today or earlier. Follow-ups are recorded in the timeline and audit log.

## 5. Notes, privacy and audit

- Internal notes (`type = note`) and contact records (`type = contact`) are internal only. They are stored as `AutomationRequestEvent` rows with visibility `internal` and are filtered out of every customer view and customer email (tested: the words of a private note never appear in a customer email).
- Customer-visible events (information requests, milestone updates, conversion) carry visibility `customer`.
- Every action is audited (`audit_logs`: `lead.submitted`, `lead.assigned`, `lead.status_changed`, `lead.note`, `lead.contact`, `lead.follow_up`, `lead.info_requested`, `lead.quote_prepared`, `lead.customer_accepted` / `lead.customer_declined`, `lead.converted`, `lead.account_linked`).
- Do not put passwords, API keys or credentials in a note: requirement text is redacted at submission, but a note is staff-typed and is stored as written, so keep credentials out of it entirely.

## 6. Exports

Admin → Exports → leads (needs `leads.export`; Sales Staff without it get 403). The export is scoped to the leads the member may see and excludes phone numbers, ticket bodies/notes and other PII beyond the allow-list. See `docs/ADMIN_OPERATIONS_GUIDE.md` for the general export rules (CSV only, streamed, formula-injection protection, every download audited).

## 7. What sales never does here

- Never charges a customer from a lead: acceptance records agreement only. Invoices and payments are raised separately, through the normal billing workflow, after acceptance.
- Never promises a price, delivery date or result that is not in the written quotation.
- Never edits another member's lead without owning it or holding `leads.assign`.
