# HASSAD Graduation Defense Deliverables

## Primary files

- `HASSAD_Graduation_Presentation.pptx` — 20-slide, 16:9 English defense deck (15 timed main slides + 5 appendix slides).
- `HASSAD_Graduation_Presentation.pdf` — PowerPoint-rendered PDF for reliable sharing and printing.
- `previews/slide-01.png` through `slide-20.png` — 1920×1080 PowerPoint renders.
- `previews/HASSAD_Contact_Sheet.png` — visual review sheet for all 20 slides.
- `HASSAD_Defense_20min_Music.mp4` — exact 20:00 timed defense cut with low original ambient music and clean narration space.
- `HASSAD_Defense_20min_NoMusic.mp4` — exact 20:00 timed defense cut with a silent AAC track.
- `HASSAD_Trailer_2min_Music.mp4` — exact 02:00 trailer cut with original ambient music.
- `HASSAD_Trailer_2min_NoMusic.mp4` — exact 02:00 trailer cut with a silent AAC track.
- `HASSAD_Ambient_20min.wav` and `HASSAD_Ambient_2min.wav` — original, code-synthesized instrumental beds; no external copyrighted source.
- `build_hassad_presentation.py` — reusable source generator for the deck, PowerPoint exports, previews, audio, videos, and validation.
- `validation_report.json` — automated structural, content, visual-overflow, and media checks.

Existing Arabic speaking script, Q&A preparation, storyboard, and website demo runbook remain unchanged.

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

These screen holds include the opening breath, visual holds, speaker handoffs, and Act transitions from the authoritative storyboard. Slides 16–20 are appendix material and are not included in the timed run.

## How to present

1. Open the PowerPoint file in Microsoft PowerPoint and use Presenter View with the existing Arabic speaking script.
2. Start on Slide 1 and keep the first frame silent for two seconds.
3. Follow the five-Act progress bar and the exact handoff clock above. The deck intentionally uses stable native shapes rather than fragile embedded motion.
4. Use the music video only as a rehearsal or pre-recorded visual bed. It contains no narration. Record live narration separately or present live.
5. For a quiet room or later voice-over, use the no-music version; it includes a silent AAC stream for editor compatibility.
6. During Q&A, move to Slides 16–20 only when the panel asks for traceability, WBS, risk, change, or EVM detail.
7. For the website demonstration, follow `HASSAD_Website_Demo_Runbook.md`; the deck’s UI frames are presentation mockups, not execution proof.

## Data and evidence transparency

- Controlled charter, registers, plans, WBS, dates, scope, and approved decisions are shown as **[VERIFIED BASELINE]**.
- Artifact logs end in Week 3. The Week-7 issue, EGP 9,500 contingency draw, closure EVM, final quality results, completion status, and related lessons are labeled **[MODELED COMPLETION]** or mixed evidence.
- Replace modeled figures only with dated, owner-signed closure records, cost ledgers, test logs, acceptance evidence, and authorization records.
- UI panels use a restrained mix: illustrative frames remain clearly labeled, while privacy-conscious live crops are labeled **LIVE PROTOTYPE EVIDENCE**. Live feature proof does not convert modeled closure figures into verified actuals.
- The approved change arithmetic is separated from risk reserve arithmetic: revised BAC EGP 164,000; total ceiling EGP 214,000; modeled AC EGP 173,500; modeled headroom EGP 40,500.

## Rebuild

Run from this folder:

```powershell
python build_hassad_presentation.py
```

Requirements: Python with `python-pptx`, Pillow, NumPy, pywin32, installed Microsoft PowerPoint, and the bundled `imageio-ffmpeg` binary. If available, `rapidocr-onnxruntime` is used to redact detected sample/account-like tokens from live crops before embedding. The generator overwrites generated deliverables but does not edit the website repository or existing source-content files. PowerPoint exports the PDF and PNGs. No slide-transition XML is injected because file integrity takes priority; continuity is carried by the five-Act bar, recurring geometry, and exact video timing.
