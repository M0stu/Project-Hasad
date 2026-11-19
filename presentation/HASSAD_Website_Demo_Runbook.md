# HASSAD Website Demo Runbook

**Purpose:** controlled Arabic-first live demo supporting the 15-slide defense  
**Evidence boundary:** the website proves **prototype behavior**; it does not, by itself, prove EVM, final quality rates, full acceptance, legal certification, or closure.  
**Data rule:** synthetic data only. Never expose real identities, phone numbers, passwords, pricing, or contact details.

---

# 1) Demo Architecture and Persona Separation

| Browser session | Persona | Purpose | Privacy boundary |
|---|---|---|---|
| Private Session A | **Supplier** | Intake, account, supply request, status | Must not reveal Buyer identity or credentials |
| Private Session B | **Buyer** | Marketplace filters, batch, quotation, reservation | Must not reveal Supplier private contact data |
| Private Session C | **Admin** | Inspection, stock edit/audit, analytics, report export | Restricted governance view; no secrets on screen |

**Required environment controls from `docs/DEMO-GUIDE-EN.md`:**

- Dedicated demo database only.
- `HASAD_APP_ENV=demo`
- `HASAD_DEMO_MODE=1`
- `HASAD_DEMO_ALLOW_RESET=yes` only when an intentional reset is required.
- Supplier, Buyer, and Admin identifiers/passwords supplied through environment/configuration outside version control.
- Arabic interface selected before presentation.

> Never display or read credential values aloud. The runbook records variable names, not secrets.

---

# 2) Pre-Demo Preparation

## T−24 hours

1. Confirm the repository version and database are the approved demo build.
2. Apply `htdocs/database/migrations/20261119_001_defense_demo.sql` to an upgraded demo database, or import the fresh `htdocs/database/schema.sql`.
3. Set the demo environment and Persona credentials outside version control.
4. Seed synthetic data using:

```text
php tools/demo_data.php seed
```

5. Confirm the command is idempotent and refuses non-demo environments.
6. Open Supplier, Buyer, and Admin in three separate private sessions.
7. Select the Arabic interface in every session.
8. Verify all four material categories are visible:
   - Rice straw — قش الأرز
   - Wheat straw — تبن القمح
   - Corn residues — مخلفات الذرة
   - Sugarcane bagasse — مصاصة القصب
9. Capture fallback screenshots or a short local video for each critical proof point.
10. Disable notifications, browser autofill pop-ups, bookmarks bar, and password-manager overlays.

## T−30 minutes

- Run the happy path once without resetting.
- Confirm no personally identifying information appears.
- Confirm CR-001 filter combinations return controlled results.
- Confirm CR-007 edit uses an **unreserved** listing.
- Confirm CR-004 dashboard loads and labels Actual, Committed, Variance, and 30-day Estimate at Completion correctly.
- Confirm CR-010 produces an Excel-compatible SpreadsheetML `.xls` file.
- Place fallback evidence in a local, offline folder.
- Keep the reset command ready but do not run it during the live story unless necessary.

---

# 3) Slide-to-Demo Storyboard

The defense deck has 15 story slides. The table distinguishes:

- **Narrative slide:** where the project-management point belongs.
- **Live demo cue:** where the repository guide recommends the screen action.
- **Governance callback:** where the presenter reconnects the screen to the controlled Change Request.

| Slide | Persona / screen | Live action | Proof point | CR / material connection | Time cap |
|---:|---|---|---|---|---:|
| 1 | None | No live action | Title, team, Sponsor | Do not open the site yet | 0 sec |
| 2 | None | No live action | Five-act roadmap | Announce that screen evidence will appear only at selected proof points | 0 sec |
| 3 | Supplier | Sign in using configured training credential; open **My account** | Arabic-first access and HASSAD-mediated privacy boundary | Persona foundation; no direct CR | 20 sec |
| 4 | Supplier | Submit a rice-straw supply request; choose pickup or hub drop-off | Structured intake: category, quantity, governorate, traceable status | Rice straw; product-success criterion | 30 sec |
| 5 | Admin | Schedule and inspect the request; choose preparation form, measured weight, grade, moisture | Controlled inspection data supports planned decisions | Rice straw; scope boundary and data quality | 30 sec |
| 6 | None | Return to slides | Predictive governance and tailored controls | Explain that the workflow is traced to SRS/RTM and phase acceptance | 0 sec |
| 7 | None | No live action | WBS/schedule baseline | Demo is evidence for selected WBS outputs, not the whole schedule | 0 sec |
| 8 | Buyer | Open marketplace; multi-select two governorates, two material categories, and one preparation form | Combined filtering over exactly four approved categories | **CR-001**; use rice + wheat first | 35 sec |
| 9 | Buyer | Open a batch; show quantity and server-calculated quotation; place a reservation | Transactional reservation and no-oversell invariant | Rotate to corn or sugarcane material record | 35 sec |
| 10 | None | Return to slides | Stakeholder and escalation path | Supplier → Buyer → Admin responsibilities | 0 sec |
| 11 | Admin | Open **Consignment Stock**; edit an unreserved listing; show audit trail | Optimistic lock, before/after audit, republication gate, preserved quantity/reservation | **CR-007**; governance callback to all approved CRs | 40 sec |
| 12 | Admin | Open **Executive analytics** | Actual, Committed, Variance, 30-day Estimate at Completion logic | **CR-004**; do not describe forecast as intelligent automation or guarantee | 35 sec |
| 13 | None | No live action | Lessons learned | Tie observed controls to matching-rule and reserve lessons | 0 sec |
| 14 | Admin | Open **Reports**; export stock workbook | SpreadsheetML `.xls`; formula-leading text neutralized | **CR-010**; Phase-2 decision evidence | 30 sec |
| 15 | None | Close/hide browser and return to Q&A slide | Clean finish | Keep site ready for panel deep dives | 0 sec |

**Maximum planned live-screen time:** approximately **4 minutes 15 seconds** if every cue is shown.  
**Recommended defense use:** show only the four CR proof points in the main 20-minute talk, or move the full workflow to Q&A. The speaking script already fills 20:00; any live action inserted into that timing must replace an equal amount of spoken explanation.

---

# 4) Four Approved Change Requests — Exact Evidence Map

| CR | Controlled change | Cost / effort decision | Primary narrative slide | Repository live cue | Persona | What to show | What to say |
|---|---|---:|---:|---:|---|---|---|
| **CR-001** | Advanced multi-select filtering across 27 governorates and waste sub-categories | EGP 3,000 / +2 days | 11 (Change Control); cost context on 8 | 8 | Buyer | Select two governorates, two categories, one preparation form | "ده امتداد مُعتمد للبحث الأساسي، وتمويله من Cost Baseline Adjustment، مش من Risk Reserve." |
| **CR-004** | Executive activity/impact dashboard | EGP 5,000 / +4 days | 11; outcome support on 12 | 12 | Admin | Actual, Committed, Variance, 30-day Estimate at Completion | "دي decision dashboard منفصلة؛ الـforecast rule-based ومش ضمانًا تجاريًا." |
| **CR-007** | Listing edit/update | EGP 2,500 / +2 days | 11 | 11 | Admin | Edit unreserved listing; audit before/after; republication gate | "التعديل محكوم بقفل وتدقيق، ولا يغير الكمية المحجوزة أو reservation من الخلف." |
| **CR-010** | Excel-compatible export | EGP 3,500 / +2 days | 11; next-step support on 14 | 14 | Admin | Export `.xls`; open only if time permits | "التصدير يدعم التدقيق، والنص الذي يبدأ كصيغة يتم تحييده لحماية الملف." |
| **Total** | 4 approved scope enhancements | **EGP 14,000 / +10 developer-days** | 8 and 11 | 8, 11, 12, 14 | Buyer/Admin | Four controlled proof points | "الـ10 أيام اتنفذت بالتوازي في ديسمبر، والـEGP 50,000 Risk Reserve لم يُستخدم لتمويل Scope." |

## Reconciliation note

The presentation blueprint discusses all four approvals together on Slide 11 because that is the **Integrated Change Control** climax. The repository guide places the actual screen demonstrations at Slides 8, 11, 12, and 14. Both mappings are valid when stated explicitly: one is the governance narrative; the other is the live proof cue.

---

# 5) Four-Material Coverage Matrix

Every material must be visible at least once across intake, filter, marketplace, or Admin evidence. Do not imply that one successful record proves the complete matching test matrix.

| Material | Arabic label | Supplier record | Buyer/filter action | Admin verification | Suggested slide cue |
|---|---|---|---|---|---:|
| Rice straw | قش الأرز | Create the main live supply request | Include in first filter pair | Inspect grade and moisture | 4–5, 8 |
| Wheat straw | تبن القمح | Use a pre-seeded listing | Include in first filter pair | Confirm quantity/status in stock | 8, 11 |
| Corn residues | مخلفات الذرة | Use a pre-seeded listing | Open batch and quotation or include in second filter pair | Confirm reservation-preserved quantity | 9, 11 |
| Sugarcane bagasse | مصاصة القصب | Use a pre-seeded listing | Include in second filter pair | Confirm analytics/report row | 12, 14 |

## Fast four-material proof sequence — 45 seconds

1. In Buyer marketplace, open category filter.
2. Point to all four options without reading every field.
3. Select **rice straw + wheat straw** with two governorates; apply.
4. Clear and select **corn residues + sugarcane bagasse**; apply.
5. Open one result and say: "الظهور هنا دليل Demo على الفلترة؛ نسبة matching النهائية لا تُقبل إلا من scenario log."

## Deep-dive sequence — Q&A only

- Run one controlled buyer scenario per material.
- Record expected versus actual result.
- Confirm quantity and availability behavior.
- Confirm no-oversell after reservation.
- Show Admin stock/audit record.
- Do not claim statistical accuracy from four demonstrations.

---

# 6) Persona Scripts and Click Paths

## A. Supplier — Slides 3–4

### Objective
Prove Arabic-first access, privacy boundary, structured request intake, and traceable status.

### Click path

1. Sign in using the configured synthetic Supplier credential.
2. Open **My account**.
3. Briefly point to the Arabic interface and role-specific data.
4. Start a new supply request.
5. Select **Rice straw / قش الأرز**.
6. Enter a synthetic quantity and governorate.
7. Choose pickup or hub drop-off.
8. Submit and point to the traceable status.

### Presenter line

"الـSupplier بيدخل بيانات منظمة بدل التواصل المتفرق. إحنا بنثبت هنا رحلة الـPrototype فقط؛ بيانات الشخص صناعية وتجريبية، والهوية لا تظهر للـBuyer خارج حدود الصلاحية."

### Stop condition
Stop after the status is visible. Do not browse personal settings or reveal contact details.

## B. Buyer — Slides 8–9

### Objective
Prove CR-001, controlled batch review, server quotation, reservation, and no-oversell behavior.

### Click path

1. Open marketplace.
2. Select two governorates.
3. Select two categories: rice straw and wheat straw.
4. Select one preparation form.
5. Apply filters and point to narrowed results.
6. Optionally repeat with corn residues and sugarcane bagasse.
7. Open a batch.
8. Point to available quantity and server-calculated quotation.
9. Place one controlled reservation.
10. Confirm the available/reserved quantity behavior.

### Presenter line

"CR-001 أضاف multi-select منظم، مش مجرد text search. والـreservation بيتحسب على السيرفر ويحمي من over-selling؛ لكن الشاشة لا تستبدل test logs أو acceptance records."

### Stop condition
Stop immediately after the reservation confirmation. Do not open Supplier private identity or contact details.

## C. Admin — Slides 5, 11, 12, 14

### Objective
Prove controlled inspection, CR-007 auditability, CR-004 executive analytics, and CR-010 export.

### Click path 1 — Inspection

1. Open the submitted Supplier request.
2. Schedule inspection.
3. Select preparation form.
4. Enter measured weight, grade, and moisture using synthetic values.
5. Save and point to the controlled status.

### Click path 2 — CR-007

1. Open **Consignment Stock**.
2. Choose an **unreserved** listing.
3. Edit one permitted field.
4. Save.
5. Show before/after audit trail.
6. Point to optimistic lock and republication gate behavior.
7. Confirm preserved quantity/reservation behavior.

### Click path 3 — CR-004

1. Open **Executive analytics**.
2. Point to Actual.
3. Point to Committed.
4. Point to Variance.
5. Point to the 30-day Estimate at Completion.
6. State that the forecast is rule-based.

### Click path 4 — CR-010

1. Open **Reports**.
2. Choose stock workbook export.
3. Export the SpreadsheetML `.xls` file.
4. If time allows, open the file and point to structured rows.
5. State that formula-leading text is neutralized.

### Presenter line

"الـAdmin مش مجرد مستخدم بصلاحية أكبر؛ هو نقطة control للـinspection والaudit والreporting. كل شاشة هنا مرتبطة بقرار Change معتمد، لكن قرار الاعتماد نفسه مثبت في CCB record مش في الواجهة."

---

# 7) Main-Talk vs. Q&A Demo Options

## Option A — Main 20-minute talk stays intact

- Keep the 15-slide speaking script exactly as timed.
- Use static screenshots on Slides 8, 11, 12, and 14.
- Run the live website only after Slide 15 or when requested.
- Best for time discipline and low technical risk.

## Option B — Insert a 90-second live proof montage

To keep the total at 20:00, replace equal spoken time:

- Slide 8: 20 sec CR-001.
- Slide 11: 25 sec CR-007.
- Slide 12: 25 sec CR-004.
- Slide 14: 20 sec CR-010.

**Do not add 90 seconds on top of the script.** Shorten spoken examples, not the EVM formulas, governance distinction, or closing Ask.

## Option C — Full workflow in Q&A

Recommended when the panel asks about product evidence:

1. Supplier request.
2. Admin inspection.
3. Buyer filter/reservation.
4. Admin edit/audit.
5. Analytics.
6. Export.

Target: 4–5 minutes outside the formal 20-minute narrative.

---

# 8) Failure Handling

| Failure | Immediate response | Fallback evidence | Presenter line |
|---|---|---|---|
| Login fails | Do not retry passwords on screen | Pre-authenticated backup session or screenshot | "هننتقل للدليل الاحتياطي حفاظًا على الوقت والخصوصية." |
| Database unavailable | Stop live navigation | Local video/screenshots | "تعطل البيئة لا يغير الـcontrolled workflow؛ ده التسجيل المحلي لنفس النسخة." |
| Filter returns no result | Clear filters once; use seeded pair | CR-001 screenshot | "هنستخدم الـseeded scenario المعتمد بدل تعديل البيانات أثناء الدفاع." |
| Reservation conflict | Do not force update | No-oversell evidence screenshot | "الرفض هنا متوقع لحماية الكمية؛ هنفتح سجلًا متاحًا." |
| CR-007 edit blocked | Verify listing is unreserved | Audit-trail screenshot | "القيد نفسه Control؛ التعديل مسموح فقط في الحالة الصحيحة." |
| Dashboard slow | Wait no more than 5 sec | Static analytics screenshot | "هننتقل للنسخة الاحتياطية ونكمل منطق Actual/Committed/Variance." |
| Export download blocked | Do not change browser security live | Pre-generated synthetic `.xls` screenshot | "الـexport متحقق مسبقًا في بيئة الـDemo؛ هنوضح البنية من الدليل المحلي." |
| Internet loss | Continue deck offline | Local evidence pack | "العرض والـevidence محليان؛ نكمل بدون اعتماد على الاتصال." |

**Ten-second rule:** if recovery is not obvious within 10 seconds, move to fallback. Never troubleshoot infrastructure in front of the panel.

---

# 9) Reset and Reseed

Reset is destructive and permitted only in an environment explicitly marked as demo.

```text
php tools/demo_data.php reset
php tools/demo_data.php seed
```

Rules:

1. Confirm `HASAD_APP_ENV=demo`.
2. Confirm `HASAD_DEMO_MODE=1`.
3. Confirm `HASAD_DEMO_ALLOW_RESET=yes`.
4. Reset only between rehearsals or after a failed test run.
5. Reseed before the next presentation.
6. Never point these commands at production or a database containing real data.

---

# 10) Final Go / No-Go Checklist

## Go only if

- [ ] Three Persona sessions are separated.
- [ ] Arabic interface is active.
- [ ] Synthetic data is clearly recognizable.
- [ ] All four material categories are present.
- [ ] CR-001 filter combinations work.
- [ ] CR-007 has an unreserved editable listing and visible audit trail.
- [ ] CR-004 analytics labels are correct.
- [ ] CR-010 export works or has verified fallback evidence.
- [ ] No credentials or private identity fields are exposed.
- [ ] Local fallback evidence opens without internet.
- [ ] Presenter can return to the deck in one click.

## No-Go conditions

- Real customer or contact data appears.
- Credentials are visible or stored in presentation files.
- The database environment cannot be confirmed as demo.
- A reset would touch non-demo data.
- The presenter would need to edit code, configuration, or database records live.

In a No-Go condition, use screenshots/video and continue the governance narrative. A controlled fallback is stronger than an uncontrolled live system.

---

# 11) Closing Evidence Statement

Use this sentence before leaving the website:

"اللي شاهدناه يثبت إن الـPrototype ينفذ الرحلات والـControls المحددة باستخدام synthetic data. أما أرقام EVM والجودة والقبول والإغلاق، فمرجعها سجلات المشروع المعتمدة، مش الشاشة وحدها."
