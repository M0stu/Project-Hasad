# HASSAD Graduation Defense Deliverables

## Current presentation package

- `HASSAD_Graduation_Presentation.pptx` — 20-slide, 16:9 English defense deck: 15 timed main slides plus 5 appendix slides.
- `HASSAD_Graduation_Presentation.pdf` — Microsoft PowerPoint COM export for reliable sharing and printing.
- `previews/slide-01.png` through `slide-20.png` — 1920×1080 PowerPoint renders.
- `previews/HASSAD_Contact_Sheet.png` — visual review sheet for all 20 slides.
- `build_hassad_presentation.py` — reusable presentation generator for the PPTX, native PowerPoint motion, PDF, previews, contact sheet, and validation.
- `validation_report.json` — structural, content, transition, animation, overflow, evidence, privacy, and media-preservation checks.

## Seed-to-Value story and five-member team

Slides 1–2 introduce the project through a native-vector circular story:

**Seed → Growing Plant → Plant Waste → HASSAD Digital Bridge / Factory → Reborn Eco-Book and sustainable products**

A botanical growth tracker continues across all 15 main slides:

1. **Act I — Seed & Gap**
2. **Act II — Deep Roots**
3. **Act III — Stem & Leaves**
4. **Act IV — Weathering the Storm**
5. **Act V — Harvest & Eco-Book**

The student roster is limited to these five members, in this order:

1. **Mostafa Yakout** — Lead Presenter; Project Management and Stakeholder Engagement
2. **Rania Abdelgliel** — Co-Lead Presenter; Requirements, Scope, and UX/UI
3. **Reem Ahmed** — Schedule, Planning, and Cost Baseline
4. **Ahmed Mostafa** — Quality Assurance, Documentation, and Closeout
5. **Omar Hassan** — Technical Architecture, Backend, Risk, and Integration

**Saad Ibrahim, CEO, SAAD Company** remains the external Project Sponsor and is not part of the student roster.

The Arabic speaking script (`.md` and `.docx`) and defense Q&A preparation have been updated for the five-member roster and the Seed-to-Value opening. The storyboard and website demo runbook remain reference documents.

## Native PowerPoint motion

Motion is written by installed Microsoft PowerPoint through its COM object model, which produces schema-valid OOXML timing and transition structures.

- **Transitions: 20/20 slides.**
- **Fade Smoothly, 700 ms:** opening slides and movement within an Act.
- **Push Left, 800 ms:** Act or section boundaries on Slides 4, 7, 11, 13, and 16.
- **Native entrance timing trees:** Slides 2, 7, 8, 11, and 12.
- **Slide 2:** progressive Seed-to-Book reveal with restrained Fade/Wipe entrances.
- **Slides 7, 8, 11, and 12:** narrative-led entrances for WBS, cost, change control, and EVM elements.

The installed PowerPoint COM interface does not expose a stable Morph constant. The opening therefore uses a validated **Fade Smoothly** fallback rather than claiming Morph. `validation_report.json` records the allocation, durations, transition count, intended timing-tree slides, clean PowerPoint opening/export, and zero text overflow.

## Existing media files — preserved, not rebuilt

The following existing media files are preserved artifacts:

- `HASSAD_Defense_20min_Music.mp4`
- `HASSAD_Defense_20min_NoMusic.mp4`
- `HASSAD_Trailer_2min_Music.mp4`
- `HASSAD_Trailer_2min_NoMusic.mp4`
- `HASSAD_Ambient_20min.wav`
- `HASSAD_Ambient_2min.wav`

The current `main()` workflow in `build_hassad_presentation.py` **does not call the video/audio rendering functions**. It regenerates only the presentation, PowerPoint exports, previews, contact sheet, and validation report. Media SHA-256 hashes, byte sizes, and modification timestamps are checked before and after the run to prove preservation.

Because the videos were intentionally not re-rendered during the Seed-to-Value presentation edit, treat them as previously produced rehearsal/media assets; the PPTX is the authoritative current visual deck.

## Exact 20-minute presentation clock

| Slide | Screen time | Clock |
|---:|---:|---:|
| 1 | 82 sec | 00:00–01:22 |
| 2 | 65 sec | 01:22–02:27 |
| 3 | 78 sec | 02:27–03:45 |
| 4 | 70 sec | 03:45–04:55 |
| 5 | 75 sec | 04:55–06:10 |
| 6 | 78 sec | 06:10–07:28 |
| 7 | 80 sec | 07:28–08:48 |
| 8 | 78 sec | 08:48–10:06 |
| 9 | 123 sec | 10:06–12:09 |
| 10 | 85 sec | 12:09–13:34 |
| 11 | 103 sec | 13:34–15:17 |
| 12 | 123 sec | 15:17–17:20 |
| 13 | 60 sec | 17:20–18:20 |
| 14 | 60 sec | 18:20–19:20 |
| 15 | 40 sec | 19:20–20:00 |

The screen holds include the opening breath, visual holds, speaker handoffs, and Act transitions. Slides 16–20 are appendix material and are not included in the timed run.

## How to present

1. Open `HASSAD_Graduation_Presentation.pptx` in Microsoft PowerPoint and use Presenter View with the updated Arabic speaking script.
2. Start on Slide 1 and keep the opening frame silent for two seconds.
3. On Slide 2, advance through the five Seed-to-Book entrance groups in story order.
4. Follow the botanical tracker and exact clock above. Allow the Push transitions to signal new Acts.
5. Use Slides 16–20 only when the panel asks for traceability, WBS, risk, change, or EVM detail.
6. Follow `HASSAD_Website_Demo_Runbook.md` for the website demonstration.
7. If an existing MP4 is used for rehearsal, remember that it was preserved rather than rebuilt for the latest deck.

## Data and evidence transparency

- Controlled charter, registers, plans, WBS, dates, scope, and approved decisions are marked **[VERIFIED BASELINE]**.
- Artifact logs end in Week 3. The Week-7 issue, EGP 9,500 contingency draw, closure EVM, final quality results, completion status, and related lessons remain labeled **[MODELED COMPLETION]** or mixed evidence.
- Replace modeled figures only with dated, owner-signed closure records, cost ledgers, test logs, acceptance evidence, and authorization records.
- UI panels use a restrained mix: illustrative frames remain labeled as mockups, while privacy-conscious live crops are labeled **LIVE PROTOTYPE EVIDENCE**.
- Live feature evidence does not convert modeled closure figures into verified actuals.
- Approved change arithmetic remains separate from risk-reserve arithmetic: revised BAC EGP 164,000; total ceiling EGP 214,000; modeled AC EGP 173,500; modeled headroom EGP 40,500.

## Presentation-only rebuild

Run from this folder:

```powershell
python build_hassad_presentation.py
```

Requirements: Python with `python-pptx`, Pillow, NumPy, pywin32, installed Microsoft PowerPoint, and optional `rapidocr-onnxruntime` for privacy redaction checks. The current `main()` sequence is:

```text
save_deck → apply_native_motion → export_with_powerpoint → make_contact_sheet → validate
```

It overwrites the PPTX, PDF, preview PNGs, contact sheet, and validation report. It does **not** regenerate MP4 or WAV files, does not update this README automatically, and does not edit the website repository or source-content documents.
