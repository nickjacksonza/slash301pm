# Slash 301 PM — Demo Guide

**For:** Stakeholder demo (agency leadership + client-side guests)
**App:** `projects.slash301.com/slash301pm/?v=10`
**Switch users via:** Top-right name dropdown

---

## What This Is

Slash 301 PM manages the full creative production workflow for a digital agency — from the moment a brief is written to the moment a client approves the final work.

It's built for agencies running 5–20 people across multiple client brands simultaneously. Every person in the process — from copywriter to client — logs in and sees exactly what's theirs, nothing more.

---

## The Agency: Slash 301 Creative

A mid-size digital production agency managing social, digital, and campaign work for ten hotel brands.

### Agency Team (10 people)

| Name | Role | What They Do in the Tool |
|------|------|--------------------------|
| **Priya Nair** | COO | Full visibility. Oversees all jobs and both review queues. |
| **Dominic Walsh** | ECD | Creative oversight. Reviews internally alongside CDs. |
| **Sarah Chen** | PM | Creates briefs, assigns teams, monitors job progress. |
| **Lena Müller** | PM | Second PM — handles the hotel portfolio overflow. |
| **Morgan Davis** | Traffic | Schedules capacity, assigns tasks, triggers team notifications. |
| **Ryan Okafor** | Traffic | Second Traffic — covers capacity planning for the hotel accounts. |
| **Isabelle Fontaine** | CD | Creative Director — internal review queue (approve/reject copy + media). |
| **James Park** | CD | Second CD — handles internal review when Isabelle is across multiple jobs. |
| **Alex Thompson** | Copywriter | Writes copy, marks tasks done. Sees only assigned jobs. |
| **Chloe Mwangi** | Copywriter | Second copywriter — shares the workload across campaigns. |
| **Kim Lee** | Designer | Uploads media assets, marks tasks done. |
| **Tariq Hassan** | Designer | Second designer — covers high-volume hotel content periods. |
| **Jordan Blake** | QA | Spot-checks before client-facing delivery. |

---

## The Clients: Ten Hotel Brands

Each hotel has two contacts in the system: a **Marketing Manager** (day-to-day feedback) and a **Brand Director** (final sign-off). They see only their own brand's jobs.

| # | Hotel Brand | Marketing Manager | Brand Director |
|---|-------------|-------------------|----------------|
| 1 | **The Meridian Collection** | Sophie Vandenberg | Charles Meridian III |
| 2 | **Harbour & Helm Hotels** | Luca Ferretti | Nina Bjornstad |
| 3 | **The Ashford Grand** | Preethi Subramaniam | Oliver Ashford |
| 4 | **Coastal & Co. Resorts** | Mia Johansson | Rafael Costa |
| 5 | **The Blackwood Boutique** | Yuki Tanaka | Harriet Blackwood |
| 6 | **Solaris Hotels & Spa** | Diego Reyes | Amara Osei |
| 7 | **The Northgate Group** | Fionnuala O'Brien | Callum Northgate |
| 8 | **Vela Luxury Resorts** | Ananya Krishnan | Marco Bianchi |
| 9 | **The Copperleaf Collection** | Zara Adeyemi | Pieter de Vries |
| 10 | **Aurum City Hotels** | Ben Whitfield | Valentina Aureli |

---

## What the Tool Does — By Role

### Traffic (Morgan Davis / Ryan Okafor)
- Opens the **Dashboard** — sees all active jobs grouped by status: Today, In Progress, In Review, Ready to Schedule
- Goes to **Capacity** — sees workload calendar, identifies who's free, assigns tasks to copywriters/designers
- Clicks "Send by Email" on a person's calendar to notify them a job is theirs
- Sees the full **Work** tab — can filter by hotel brand, campaign, status
- Has visibility into both review queues (Internal and Client)

### PM (Sarah Chen / Lena Müller)
- Clicks **+ New Brief** from Dashboard or Work tab
- Fills in: hotel brand, campaign name, job name, creative direction, due dates, assigns PM/Traffic/Copywriter/Designer/CD and client contact
- Job is created with an auto-generated job number (e.g. `MERC-001` for Meridian Collection)
- Monitors job status from the Work tab

### Copywriter (Alex Thompson / Chloe Mwangi)
- Opens **Work** tab — sees only jobs assigned to them
- Clicks a job, opens the detail panel, expands the Copy task
- Types the copy into the Copy Content field
- Ticks the checkbox → marks Copy as Done
- Job auto-routes to Internal Review once both Copy and Media are Done

### Designer (Kim Lee / Tariq Hassan)
- Same flow — opens their assigned job, expands the Media task
- Adds the asset file path or URL
- Ticks the checkbox → marks Media as Done
- When both tasks are Done, job automatically appears in the Internal Review queue

### CD (Isabelle Fontaine / James Park)
- Opens **Reviews → Internal Review** tab
- Sees all jobs ready for internal review — copy text and media asset side by side
- Can **Approve** (routes to Client Review), **Not Approved**, or **Give Feedback** (returns for revisions with feedback saved on the job)
- After client feedback comes back, sees the job in the "Client Feedback Requiring Action" section
- Can **Action & Submit to Client** (resubmit) or **Assign to...** (send to a specific copywriter/designer)

### Client (e.g. Sophie Vandenberg / Charles Meridian III)
- Opens **Reviews → Client Review** tab
- Sees *only* The Meridian Collection's jobs — no other brands visible
- Each card shows: copy text, media asset preview, campaign name, due date, brief context
- Three actions: **Approve**, **Not Approved**, **Add Feedback**
- Feedback returns the job for revisions; Approve → status becomes Approved (External)

### COO (Priya Nair)
- Full Dashboard with all active jobs across all hotel brands
- Access to both review queues (Internal and Client — all brands)
- People management via **More → People**

---

## The Core Workflow (30-second version)

```
Brief created → Tasks assigned → Copy written → Media uploaded
     → Auto-routes to Internal Review
     → CD approves → Auto-routes to Client Review
     → Client approves → "Approved (External)" on Traffic dashboard
```

No manual status changes. No email chains. No chasing.

---

## Demo Script A — Typical Workflow (5 minutes)

*Best for: a quick orientation. Shows the core loop with the most common roles.*

1. **Dashboard as Morgan Davis (Traffic)** — show the active job list, "Ready to Schedule" section
2. **Switch to Sarah Chen (PM)** — create a new brief for The Meridian Collection
3. **Switch to Alex Thompson (Copywriter)** — show "My Work" with only assigned jobs, write copy
4. **Switch to Kim Lee (Designer)** — upload media, mark Done (watch job disappear from Work, reappear in Reviews)
5. **Switch to Isabelle Fontaine (CD)** — Internal Review queue, give feedback on copy
6. **Switch to Alex Thompson** — revise copy, re-mark Done
7. **Switch to Isabelle** — approve
8. **Switch to Sophie Vandenberg (Client)** — Client Review, see only Meridian jobs, approve
9. **Switch to Morgan Davis** — Dashboard shows "Approved (External)"

**Key talking point at each switch:** "This is all the same tool — they just each see their own slice of it."

---

## Demo Script B — Grand Opening: The Meridian Collection (10 minutes)

*Best for: a full stakeholder demo. Every role touched. First-time approval throughout — no revisions, no friction. The story: the agency is launching a new campaign for The Meridian Collection's grand opening of their flagship London property.*

**Campaign:** `The Meridian London — Grand Opening`
**Job:** `MERC-001 — Grand Opening Social Launch`
**Outcome:** Approved externally, first pass, every role in the chain.

### The Story

The Meridian Collection is opening their new London flagship. Sophie Vandenberg (Marketing Manager) has been briefed by Charles Meridian III (Brand Director) to get a social launch campaign live by end of month. She's already aligned with the agency — now the machine turns.

---

### Step-by-step

| # | Switch to | Tab/Action | One-liner |
|---|-----------|------------|-----------|
| 1 | **Priya Nair** (COO) | Dashboard | Open with the COO's view — all ten hotel accounts, every job status visible at a glance. *"This is what the whole agency looks like on any given morning."* |
| 2 | **Sarah Chen** (PM) | Work → + New Brief | PM creates the brief: Meridian Collection, new campaign "Grand Opening London", job "Social Launch Package", creative direction, due date, full team assigned. Job number `MERC-001` auto-generates. |
| 3 | **Morgan Davis** (Traffic) | Capacity | Traffic opens the capacity calendar, confirms Alex and Kim have availability this week, clicks "Send by Email" for both. *"No spreadsheet. No Slack message. They're notified."* |
| 4 | **Ryan Okafor** (Traffic) | Work tab | Second Traffic checks the Work tab — MERC-001 is visible, status Inbox. Ryan updates it to This Week. *"Two Traffic people, one shared view — no duplication."* |
| 5 | **Taylor Swift** (Producer) | Dashboard | Producer sees MERC-001 on their Dashboard under active jobs. Checks the brief and due date — everything's aligned before production starts. *"Producer oversight without needing a meeting."* |
| 6 | **Dominic Walsh** (ECD) | Work → MERC-001 detail | ECD opens the job detail, reviews the brief and creative direction. Satisfied — no changes needed. *"ECD can see the brief before a single word is written."* |
| 7 | **Alex Thompson** (Copywriter) | Work → MERC-001 → Copy task | Alex opens their Work tab — only their jobs visible. Expands the Copy task, writes the grand opening copy, marks Done. *"Copywriter sees exactly one job. No noise."* |
| 8 | **Chloe Mwangi** (Copywriter) | Work tab | Chloe checks her Work tab — nothing assigned to her on this job. *"She's on other accounts. MERC-001 isn't hers — she can't even see it."* |
| 9 | **Kim Lee** (Designer) | Work → MERC-001 → Media task | Kim opens the job, uploads the hero image for the grand opening, marks Media Done. Job auto-routes to Internal Review — no one has to move it. |
| 10 | **Tariq Hassan** (Designer) | Work tab | Tariq's Work tab — MERC-001 not listed (not assigned). *"Designer isolation works the same way."* |
| 11 | **Jordan Blake** (QA) | Work → MERC-001 | QA opens the job, spot-checks copy and media in the detail panel. Happy with both. *"QA gets eyes on it before internal review — no surprises for the CD."* |
| 12 | **Isabelle Fontaine** (CD) | Reviews → Internal Review | Internal Review queue shows MERC-001. Isabelle sees the copy and media side by side, reads the brief context. Everything looks right — clicks **Approve**. Job auto-routes to Client Review. |
| 13 | **James Park** (CD) | Reviews → Internal Review | James checks his Internal Review queue — MERC-001 is gone (Isabelle already handled it). His queue shows only what's waiting for him. *"No double-handling."* |
| 14 | **Sophie Vandenberg** (Client — Marketing Manager) | Reviews → Client Review | Sophie logs in and sees only Meridian jobs. MERC-001 is there: grand opening copy, hero image, brief, due date. She reviews — looks great. Clicks **Approve**. |
| 15 | **Charles Meridian III** (Client — Brand Director) | Reviews → Client Review | Charles opens Client Review — MERC-001 is gone. Sophie already approved it. *"He can see it was handled. He doesn't need to re-approve."* *(Optional: show his view is also scoped to Meridian only.)* |
| 16 | **Morgan Davis** (Traffic) | Dashboard | Traffic's Dashboard: MERC-001 is now under **"Ready to Schedule"** with status **Approved (External)**. Copy: Done. Media: Done. Due date intact. *"That's the whole loop. Brief to approved, every person in the chain, zero emails."* |

---

### Talking points for the room

- **"Every role sees a different tool"** — same URL, same login page, completely different experience based on who you are
- **"Nothing moves manually"** — every status transition in this demo was triggered by someone doing their actual job, not by someone updating a spreadsheet
- **"Clients are isolated"** — Sophie saw exactly one brand. Charles saw exactly one brand. Neither saw TechStart's logo or anyone else's copy
- **"The Traffic view is the control tower"** — Morgan opened and closed this demo. That's intentional — Traffic is where production starts and ends
- **"This is ten hotel brands running simultaneously"** — this was one job. The system handles all ten brands in parallel, with the same isolation, the same routing, the same review queues

---

## Current Demo Data (as of v=10)

The app has seed data for three hotel brands with jobs in various stages:

| Brand | Jobs | Status |
|-------|------|--------|
| Acme Corp | SUMM-001 (Hero Video), SUMM-002 (Social Package), SUMM-003 (Email Campaign) | In Progress / Today / Inbox |
| TechStart | BRAN-001 (Logo Design), BRAN-002 (Brand Guidelines) | In Review / On Hold |
| Ralph Wiggum Inc | RWSP-001 (St Patrick's Day) | Approved (External) ✓ |

> **Note:** Before a real demo, seed data should be replaced with hotel brand names matching the ten brands above. This is a data-only change in `localStorage` (or the seed data in `data.js`).

---

*DEMO.md — Slash 301 PM v4.2 | Created February 19, 2026 | Scripts: A (Typical, 5 min) + B (Grand Opening full cast, 10 min)*
