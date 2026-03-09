# Override Pool Calculator — Product Owner Discussion Document

**Prepared by:** Purvesh
**Date:** March 9, 2026
**Purpose:** Review, discussion, and sign-off before module goes to production
**Audience:** Product Owner / Manager

---

## 1. What Is This Feature?

The **Override Pool Calculator** is a new Laravel module (extension) added to the Grow Marketing backend. Its purpose is to **automatically calculate how much each eligible agent earns from the company's Override Pool** for a given year — including quarterly advance tracking and a final Q4 true-up settlement.

In plain terms:
- Agents who recruit other agents earn a share of a pool
- The bigger their team's sales, the higher their pool percentage
- This module does all that math, stores the results, and tracks money paid out throughout the year

---

## 2. Why a Module (Extension)?

Rather than embedding this logic directly into the core application, this is built as a **self-contained Laravel module** with its own:
- Database tables
- Routes and API endpoints
- Business logic service layer
- Models and tests

**Benefits:**
- Easy to enable/disable without touching core code
- Can be independently versioned and updated
- Keeps the core application clean
- Can be reused across other Grow products if needed

---

## 3. What Was Built (Current State)

### 3.1 Database Tables

| Table | Purpose |
|---|---|
| `override_pool_percentage_tiers` | Stores the tier rules (e.g., 0–400 sales → 12%) |
| `override_pool_quarterly_advances` | Records Q1, Q2, Q3 advances paid to each agent |
| `override_pool_calculations` | Stores full audit trail of every calculation run |

### 3.2 Calculation Logic

The system calculates **two components** per eligible agent:

**Part 1 — Override on Direct Recruits' Personal Sales**
```
Part 1 = Direct Recruits' Personal Sales × $50/sale × Agent's Pool %
```

**Part 2 — Override on Recruits' Downline Sales (Differential)**
```
For each direct recruit:
  Part 2 = Recruit's Downline Sales × $50/sale × (Agent Pool% − Recruit Pool%)
```

**Total Pool Payment = Part 1 + Part 2**

**Q4 True-Up (End of Year Settlement)**
```
Q4 True-Up = Total Pool Payment − (Q1 + Q2 + Q3 Advances)
```
> A negative Q4 means the agent was overpaid during the year.

### 3.3 Current Tier Structure (Pre-configured)

| Downline Sales Range | Pool Percentage |
|---|---|
| 0 – 400 sales | 12% |
| 401 – 600 sales | 16% |
| 601 – 1,400 sales | 20% |

> These are configurable via API — no code change needed to update them.

### 3.4 API Endpoints Available

| Method | Endpoint | What It Does |
|---|---|---|
| `GET` | `/api/v2/override-pool/calculate?year=2025` | Run full calculation for all agents for a year |
| `GET` | `/api/v2/override-pool/user/{id}?year=2025` | Get one agent's full breakdown |
| `POST` | `/api/v2/override-pool/advances` | Save Q1/Q2/Q3 advance amounts |
| `GET` | `/api/v2/override-pool/advances?year=2025` | View all advances + true-ups for a year |
| `GET` | `/api/v2/override-pool/dashboard?year=2025` | Summary widget data for dashboard |
| `GET` | `/api/v2/override-pool/tiers` | View current tier configuration |
| `POST` | `/api/v2/override-pool/tiers` | Update tier rules |

### 3.5 Dashboard Summary Widget

The dashboard endpoint returns:

| Field | Description |
|---|---|
| `eligible_agents_count` | Agents with at least one recruit |
| `overpaid_agents_count` | Agents where Q4 true-up is negative |
| `total_pool_amount` | Total pool payment across all agents |
| `total_advances` | Total Q1+Q2+Q3 paid out |
| `q4_trueup_total` | Net remaining to pay (or recover) in Q4 |

---

## 4. What We Need to Discuss / Decisions Needed

### 4.1 Pool Rate — Is $50/sale Correct?

The current system uses **$50 per sale** as the base pool rate. This is hardcoded as the default.

**Question for PO:** Is $50/sale the confirmed rate for all agents and all years? Should this be:
- Fixed permanently?
- Configurable per year?
- Configurable per agent tier or region?

> **Action Required:** Confirm the pool rate or define how it should vary.

---

### 4.2 Tier Structure — Are the Current Ranges Confirmed?

The current tiers are seeded as:
- 0–400 → 12%
- 401–600 → 16%
- 601–1,400 → 20%

**Questions for PO:**
- Are these the final agreed-upon tiers?
- What happens for agents with **more than 1,400 downline sales**? Currently there is no tier above 1,400 — the system returns "no tier matched" and skips those agents.
- Should there be an open-ended top tier (e.g., 1,401+ → 24%)?
- Who in the business should have permission to update tiers via the API?

> **Action Required:** Confirm tiers and define the top-tier boundary (or confirm it's open-ended).

---

### 4.3 Eligibility Criteria — What Makes an Agent Eligible?

Currently an agent is **eligible** if they have **at least one direct recruit** in the system.

**Questions for PO:**
- Should there be a minimum sales threshold to be eligible?
- Should eligibility be limited to a specific agent rank or role?
- Should new agents be excluded if they joined after a certain date in the year?

> **Action Required:** Confirm or expand eligibility rules.

---

### 4.4 Sales Counting Rules — What Counts as a "Sale"?

Currently a sale is counted when:
- The agent is `closer1_id` on the customer record
- The sale is **not cancelled**
- The sale is **not exempted**
- `customer_signoff` date falls within the calculation year

**Questions for PO:**
- Should cancellations within a grace period still count?
- Should `closer2_id` or other agent fields also contribute to sales count?
- Are there any product categories that should be excluded?
- What about pending/in-progress sales — count or exclude?

> **Action Required:** Confirm the exact sales counting rules with the finance/ops team.

---

### 4.5 Quarterly Advance Schedule — When Are Q1/Q2/Q3 Paid?

The system stores Q1, Q2, and Q3 advance amounts and deducts them at year-end.

**Questions for PO:**
- Is the advance schedule fixed (e.g., after each quarter ends)?
- Who enters the advance amounts — admin users, finance team, or is it automated?
- Should the system **lock** Q1/Q2/Q3 once entered (prevent edits) or allow corrections?
- Should the system send notifications when Q4 true-up is negative (overpaid agents)?

> **Action Required:** Define the advance payment workflow and who is responsible for data entry.

---

### 4.6 Who Can Trigger Calculations?

Currently the `/calculate` endpoint is open to any authenticated user.

**Questions for PO:**
- Should calculation be restricted to **admin roles only**?
- Should it run automatically (e.g., scheduled job at year-end) or always manual?
- Should running a new calculation overwrite the previous one, or keep history?

> **Action Required:** Define role-based access and whether calculations should be scheduled.

---

### 4.7 Audit & Compliance Requirements

The system stores a full audit trail per calculation (all intermediate values, JSON breakdown per recruit, timestamps).

**Questions for PO:**
- Is this level of audit trail sufficient for finance/compliance?
- Should agents be able to **view their own calculation breakdown** in the agent portal?
- Are there any export requirements (CSV/PDF) for the finance team?

> **Action Required:** Confirm audit trail requirements and export needs.

---

### 4.8 Historical Data / Backfill

The module is new. There is no historical data loaded yet.

**Questions for PO:**
- Do we need to backfill calculations for prior years (e.g., 2024, 2023)?
- If yes, is the historical sales data in the current database in the right format?
- Should historical quarterly advances also be imported?

> **Action Required:** Decide on backfill scope and timeline.

---

### 4.9 Frontend / UI Requirements

This module is backend-only today. There is no UI built yet.

**What the frontend would need to build:**
1. **Dashboard Widget** — Shows summary stats (5 data points, endpoint ready)
2. **Agent List View** — Table of all eligible agents with pool amounts and overpaid flags
3. **Agent Detail Page** — Full breakdown showing Part 1, Part 2, each recruit's contribution
4. **Advance Entry Form** — Input Q1/Q2/Q3 per agent per year
5. **Tier Configuration Screen** — Admin UI to update the tier table
6. **Year Selector** — All views need a year filter

**Questions for PO:**
- Which of these screens are in scope for the first release?
- Should the Q4 overpaid agents be highlighted in red in the UI?
- Is there a priority order for the UI screens?

> **Action Required:** Define UI scope for v1 and assign to frontend team.

---

## 5. What Is Ready Right Now

| Component | Status |
|---|---|
| Database migrations | Done — 3 tables created |
| Core calculation service | Done — fully tested |
| All 7 API endpoints | Done — auth-protected |
| Dashboard widget data | Done |
| Tier management API | Done |
| Quarterly advances + Q4 true-up | Done |
| Unit tests | Done — covers all edge cases |
| Code pushed to branch | Done (`override-pool-purvesh`) |
| Frontend / Admin UI | **Not started — needs scoping** |
| Scheduled auto-calculation | **Not implemented — decision needed** |
| Role-based permissions | **Not implemented — decision needed** |
| Export (CSV/PDF) | **Not implemented — decision needed** |
| Backfill of historical data | **Not done — decision needed** |

---

## 6. Suggested Next Steps

1. **PO reviews and answers questions in Section 4** above — ideally in a 30-minute sync
2. **Confirm pool rate and tier structure** with finance team
3. **Define sales counting rules** with ops team
4. **Scope the frontend UI** — which screens are v1 vs backlog
5. **Decide on role-based access** — who can run calculations, manage tiers, enter advances
6. **Plan backfill** — if prior year data is needed
7. Once confirmed — backend team can implement remaining items (permissions, scheduling, exports)

---

## 7. Risk & Notes

| Risk | Mitigation |
|---|---|
| Agents with 1,400+ downline sales have no tier match | Need a top-tier rule defined |
| Advance amounts can be edited after entry | Consider adding a lock/approval step |
| Calculation overwrites previous results | Add versioning if historical runs need to be kept |
| No rate/tier change history | If tiers change mid-year, old calculations won't reflect old tiers |
| Large downline trees could slow calculation | BFS traversal is optimized; can add queue/job for very large orgs |

---

*Document prepared for internal discussion. All figures and rules subject to confirmation by Product Owner and Finance team before production deployment.*
