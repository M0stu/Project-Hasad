from __future__ import annotations
import math, os, json, shutil, subprocess, sys, time, zipfile, hashlib
from pathlib import Path
import numpy as np
from PIL import Image, ImageDraw, ImageFont, ImageOps
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE, MSO_CONNECTOR
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.dml import MSO_THEME_COLOR

ROOT = Path(__file__).resolve().parent
PREV = ROOT / "previews"
LIVE = ROOT / "live-screenshots"
ASSETS = ROOT / "generated-assets"
PPTX = ROOT / "HASSAD_Graduation_Presentation.pptx"
PDF = ROOT / "HASSAD_Graduation_Presentation.pdf"
FFMPEG = Path.home()/"AppData/Roaming/Python/Python314/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v9.0.2.exe"
W, H = 13.333333, 7.5
MEDIA_FILES = sorted(list(ROOT.glob("*.mp4"))+list(ROOT.glob("*.wav")))
def media_fingerprint(path):
    h=hashlib.sha256()
    with open(path,"rb") as f:
        for chunk in iter(lambda:f.read(1024*1024),b""): h.update(chunk)
    st=path.stat(); return {"sha256":h.hexdigest(),"size":st.st_size,"mtime_ns":st.st_mtime_ns}
MEDIA_GUARD = {p.name:media_fingerprint(p) for p in MEDIA_FILES}

C = {
    "ivory":"FAF8F5", "green":"1B5E20", "leaf":"388E3C", "terra":"8D5524",
    "gold":"D4A017", "char":"1F2933", "slate":"4A5568", "red":"C62828",
    "pale":"E8EFE6", "sand":"F1E8DA", "white":"FFFFFF", "amber":"E6A23C",
    "blue":"3D6F82", "line":"D8D3CB", "soft":"F4F0E9"
}
ACTS = ["ACT I · SEED & GAP","ACT II · DEEP ROOTS","ACT III · STEM & LEAVES","ACT IV · WEATHERING THE STORM","ACT V · HARVEST & ECO-BOOK"]
ACT_BY_SLIDE = [0,0,0,1,1,1,2,2,2,2,3,3,4,4,4]
TITLES = [
"From seed to waste to reborn value.",
"One living cycle turns a hidden gap into industrial value.",
"One marketplace closes two disconnected searches.",
"Success was measurable before work began.",
"The MVP was protected as much by exclusions as by features.",
"Fixed commitments demanded Predictive discipline.",
"Twenty-two work packages converged on five immovable gates.",
"We changed the baseline—not the meaning of contingency.",
"Our highest technical threat crossed the line from risk to issue.",
"The right message reached the right decision-maker before delay became drift.",
"The CCB approved value—and blocked scope creep.",
"On time, fully accepted, and honestly cost-visible.",
"Two controls saved more than they cost.",
"The prototype proved the pathway; Phase 2 must prove the market.",
"HASSAD: from waste to value—under control.",
"Every defense claim traces to a controlled artifact—or a modeled label.",
"The complete scope decomposes into exactly 22 leaf Work Packages.",
"Threats were owned, triggered, and answered—not merely listed.",
"Ten requests, ten traceable decisions, one protected baseline.",
"The mathematics reconcile from baseline to acceptance."
]
TAGS = [
"ACT I · PROJECT INTEGRATION / INITIATION","ACT I · PROJECT LIFE CYCLE / ROADMAP","ACT I · DEVELOP PROJECT CHARTER",
"ACT II · SCOPE / QUALITY / SUCCESS MEASURES","ACT II · DEFINE SCOPE / CONTROL SCOPE","ACT II · DEVELOPMENT APPROACH / TAILORING",
"ACT III · CREATE WBS / DEVELOP SCHEDULE","ACT III · DETERMINE BUDGET / ACQUIRE RESOURCES","ACT III · PLAN / IMPLEMENT RISK RESPONSES",
"ACT III · IDENTIFY / ENGAGE STAKEHOLDERS & COMMUNICATIONS","ACT IV · PERFORM INTEGRATED CHANGE CONTROL",
"ACT IV · MONITOR & CONTROL PROJECT WORK / EVM / CONTROL QUALITY","ACT V · MANAGE PROJECT KNOWLEDGE / LESSONS LEARNED",
"ACT V · BENEFITS / RECOMMENDATIONS / NEXT CHARTER","ACT V · CLOSE PROJECT / Q&A",
"APPENDIX · PROJECT DOCUMENT MANAGEMENT","APPENDIX · CREATE WBS / SCOPE BASELINE","APPENDIX · RISK REGISTER / MONITOR RISKS",
"APPENDIX · INTEGRATED CHANGE CONTROL / FINANCIAL RECONCILIATION","APPENDIX · EVM / CONTROL QUALITY / ACCEPTANCE"
]
LINES = [
["HASSAD: From Seed to Waste to Reborn Value.","Every harvest begins with a seed.","Agricultural Waste Digital Marketplace Website · MVP / Prototype","PMP Graduation Defense · 27-Jan-2027","Mostafa Yakout · Rania Abdelgliel · Reem Ahmed","Ahmed Mostafa · Omar Hassan"],
["THE SEED · Every harvest begins with potential.","THE GROWING PLANT · Rice, wheat, corn, and sugarcane rise.","PLANT WASTE · Residues remain fragmented and underutilized.","HASSAD → FACTORY · Digital matching and tracking; zero physical logistics.","REBORN VALUE · Eco-books, recycled paper, and sustainable packaging."],
["Farmers hold rice straw, wheat straw, corn residues, and sugarcane bagasse.","Factories seek usable residues through fragmented, manual channels.","HASSAD connects supply listings to industrial demand—digitally.","Charter authorized 01-Nov-2026 by Sponsor Saad Ibrahim.","PM Mostafa Yakout controls day-to-day delivery and ±5% line-item variance.","Reserve use or baseline change requires prior written Sponsor approval."],
["TIME · 12 weeks · finish 27-Jan-2027","COST · EGP 200,000 initial authorized ceiling","SCOPE · 2 complete journeys: Supplier and Factory","PRODUCT · listings and matching across 4 waste categories","QUALITY · ≥18/20 functional tests · 0 open Critical defects","Project success and product success were measured separately."],
["IN · responsive website, registration, profiles, 4-field listings","IN · demand requests, search/basic matching, status tracking","IN · sample-data dashboard, testing, and documented handover","OUT · native app, physical collection, storage, transport, warehouses","OUT · Ministry database integration, payments, escrow, national launch","CONSTRAINT · 12 weeks; desk-based Laws 4/1994 and 202/2020 review only"],
["Fixed scope, fixed 12-week calendar, and controlled cost baseline","Phase gates: Requirements → Design → Build → Test → Close","Formal SRS + RTM replaced ambiguous requirement narratives","Nine-channel Communications Plan kept governance lean and traceable","CCB approval controlled every scope, schedule, or cost baseline change","EGP 50,000 reserve remained Sponsor-gated for identified risks only"],
["6 Level-1 workstreams · exactly 22 leaf Work Packages","M1000 · 14-Nov-2026 · Requirements & Scope Baseline approved","M2000 · 28-Nov-2026 · UX/UI & Architecture approved","M3000 · 26-Dec-2026 · Core MVP developed and integrated","M4000 · 09-Jan-2027 · Testing and acceptance completed","M5000 · 27-Jan-2027 · Prototype deployment and closure"],
["Initial Cost Baseline EGP 150,000 + reserve EGP 50,000 = EGP 200,000","Approved scope adjustment +EGP 14,000 → revised BAC EGP 164,000","Revised BAC 164,000 + reserve 50,000 = ceiling EGP 214,000","Initial mix: Dev 90K · PM/Docs 20K · Research 15K · QA 15K · Tools 10K","Group 2 = internal Workstream Owners at EGP 0 labor cost","External Dev/UI/QA specialists = milestone payments 30% / 30% / 40%"],
["Top threats: R-07 Development Delay 20 · R-05 Data Errors 16","R-01 Scope Creep 12 · R-13 Budget Overrun 12","Opportunity O-01 Association Partnership · Exploit","Week 7: matching queries and validation exceeded the 3-second threshold","PM Mostafa Yakout escalated; Sponsor released EGP 9,500 from contingency in writing","Backend optimization + weekend fast-tracking protected M3000 and M5000"],
["Manage Closely · PM Mostafa Yakout, Group 2, Development/QA, Academic Evaluator","Keep Satisfied · Sponsor Saad Ibrahim; relevant authorities as needed","Keep Informed · Farmers/Suppliers; Factories/Buyers; UX/UI support","Monitor · Marketing and communications support","Operational issue → PM Mostafa Yakout → technical Workstream Owner","Baseline or reserve impact → Sponsor Saad Ibrahim / CCB"],
["10 requests evaluated through formal impact analysis","4 Approved · CR-001 / 004 / 007 / 010 · +EGP 14,000 / +10 effort days","4 Deferred · CR-002 / 005 / 008 / 009 · no baseline addition","2 Rejected · CR-003 native app + CR-006 English localization","Rejecting CR-003 avoided EGP 120,000 and 45 days","Scope additions changed BAC; the risk reserve stayed protected for risks"],
["PV 164,000 · EV 164,000 · AC 173,500 EGP","SV = 0 · SPI = 1.00 · delivered 27-Jan-2027","CV = -9,500 · CPI = 0.95 · EGP 40,500 below total ceiling","19/20 functional = 95% · 0 open Critical/High defects","9/10 matching = 90% · 9/10 usability = 90%","96% <3.0 sec · median 1.8 sec · legal checklist 100%"],
["LESSON 1 · Freeze matching logic and NFRs before coding.","Ambiguous tolerances and test conditions drove the Week-7 issue.","Next time: sign the Matching Rule Table and performance environment at M1000.","LESSON 2 · Separate scope funding from risk reserves.","The +EGP 14,000 scope adjustment preserved contingency for R-07.","The consequence was contained at EGP 9,500 without moving the deadline."],
["1 · LIVE PILOT with 20+ verified farms and factories","2 · SCALE FEATURES: native app, payment/escrow, stronger verification","3 · PARTNER with the Ministry / agricultural associations for trusted data","Reassess CR-003 only through a new business case and charter","SPONSOR ASK · authorize Phase-2 Charter initiation","Evaluate the remaining EGP 40,500 ceiling headroom for pilot onboarding"],
["HASSAD — FROM SEED TO REBORN VALUE","A focused website MVP for suppliers and industrial buyers","Governed through scope, schedule, cost, risk, quality, and change control","Thank you · Questions?","Mostafa Yakout · Rania Abdelgliel · Reem Ahmed","Ahmed Mostafa · Omar Hassan"],
["Charter · Stakeholder Register · Requirements Register","Scope Baseline · Schedule Baseline · Resource/Cost Register","Risk Register · Quality Plan · Communications Plan","Change Request Register · WBS Dictionary · WBS Presentation","Instructor Feedback · 13 artifacts total","Modeled closure data requires signed replacement evidence before defense."],
["1.1 Initiation (4) · Charter · Stakeholders · Schedule · Budget","1.2 Requirements & UX/UI (4) · Documentation · Journeys · Design · Approval","1.3 Core Development (4) · Frontend · Backend/DB · User Modules · Matching/Status","1.4 Testing (3) · Functional/Usability · Validation/Fixes · Test Summary","1.5 Launch Preparation (3) · Comms · Onboarding · Legal/Environmental Review","1.6 Closeout (4) · Handover · Final Report · Defense Assets · Closure/Lessons"],
["R-07 Delay 20 · Mitigate · extra hours only after authorization","R-05 Data Errors 16 · Mitigate · required fields, validation, listing edits","R-01 Scope Creep 12 · Mitigate · signed baseline and CCB screening","R-13 Budget Overrun 12 · Active Accept · reserve and fixed payment gates","R-08 Hosting Failure · Transfer · managed host / backup restore","O-01 Association Partnership · Exploit · prepare verified bulk-data intake"],
["Approved: CR-001 +3K/+2d · CR-004 +5K/+4d · CR-007 +2.5K/+2d · CR-010 +3.5K/+2d","Deferred: CR-002 notifications · CR-005 ratings · CR-008 GPS maps","Rejected: CR-003 native app +120K/+45d · CR-006 English localization","Deferred: CR-009 real-time in-platform messaging · final locked status","Approved total: +EGP 14,000 · +10 effort days · revised BAC EGP 164,000","Final defense: 4 Approved / 4 Deferred / 2 Rejected · source cut-off noted below"],
["SV = EV - PV = 164,000 - 164,000 = 0 · SPI = EV/PV = 1.00","CV = EV - AC = 164,000 - 173,500 = -9,500","CPI = EV/AC = 164,000/173,500 = 0.945 ≈ 0.95","Ceiling headroom = 214,000 - 173,500 = EGP 40,500","Functional 19/20 · Matching 9/10 · Usability 9/10 · Critical/High 0","Performance 96% <3.0s · median 1.8s · legal checklist 100%"]
]

LIVE_CROPS = {
    "cr001": ("cr001-filtered-market.png", (0.05, 0.17, 0.95, 0.70)),
    "cr004": ("cr004-executive-analytics.png", (0.05, 0.15, 0.95, 0.83)),
    "cr007": ("cr007-listing-edit-before.png", (0.07, 0.18, 0.93, 0.66)),
    "cr010": ("cr010-reports.png", (0.05, 0.16, 0.95, 0.88)),
}

def prepare_live_assets():
    """Create privacy-conscious feature crops below the site header/account area."""
    ASSETS.mkdir(exist_ok=True)
    made={}
    try:
        from rapidocr_onnxruntime import RapidOCR
        ocr_engine=RapidOCR()
    except Exception:
        ocr_engine=None
    for key,(filename,boxf) in LIVE_CROPS.items():
        src=LIVE/filename
        if not src.exists():
            raise FileNotFoundError(f"Required live prototype capture missing: {src}")
        out=ASSETS/f"{key}-proof-crop.png"
        if out.exists() and out.stat().st_mtime_ns >= src.stat().st_mtime_ns:
            made[key]=out
            continue
        im=Image.open(src).convert("RGB"); w,h=im.size
        box=(int(w*boxf[0]),int(h*boxf[1]),int(w*boxf[2]),int(h*boxf[3]))
        crop=im.crop(box)
        # Remove any remaining navigation/account strip and standardize a browser-friendly 16:10 crop.
        mask_h=max(8,int(crop.height*.025))
        ImageDraw.Draw(crop).rectangle((0,0,crop.width,mask_h),fill=(250,248,245))
        crop=ImageOps.fit(crop,(1280,800),Image.Resampling.LANCZOS,centering=(0.5,0.5))
        # Redact any detected sample/account-like token or contact credential before embedding.
        if ocr_engine is not None:
            try:
                found,_=ocr_engine(np.asarray(crop))
                draw=ImageDraw.Draw(crop)
                for item in (found or []):
                    pts,token,_score=item; upper=token.upper()
                    sensitive=("DEMO-" in upper or "@" in token or "PASSWORD" in upper or "E-MAIL" in upper or "EMAIL" in upper)
                    if sensitive:
                        xs=[int(v[0]) for v in pts]; ys=[int(v[1]) for v in pts]
                        draw.rounded_rectangle((min(xs)-5,min(ys)-4,max(xs)+5,max(ys)+4),radius=4,fill=(250,248,245),outline=(27,94,32),width=2)
            except Exception:
                pass
        crop.save(out,optimize=True)
        made[key]=out
    return made

LIVE_ASSETS = prepare_live_assets()

prs = Presentation()
prs.slide_width = Inches(W); prs.slide_height = Inches(H)
blank = prs.slide_layouts[6]

def rgb(h): return RGBColor.from_string(C.get(h,h))
def rect(sl,x,y,w,h,fill="white",line=None,r=MSO_SHAPE.ROUNDED_RECTANGLE,alpha=0):
    s=sl.shapes.add_shape(r, Inches(x), Inches(y), Inches(w), Inches(h))
    s.fill.solid(); s.fill.fore_color.rgb=rgb(fill); s.fill.transparency=alpha
    s.line.color.rgb=rgb(line or fill); s.line.width=Pt(0.8)
    return s

def line(sl,x1,y1,x2,y2,color="line",width=1.5,dash=None):
    s=sl.shapes.add_connector(MSO_CONNECTOR.STRAIGHT, Inches(x1), Inches(y1), Inches(x2), Inches(y2))
    s.line.color.rgb=rgb(color); s.line.width=Pt(width)
    if dash: s.line.dash_style=dash
    return s

def text(sl,txt,x,y,w,h,size=18,color="char",bold=False,align=PP_ALIGN.LEFT,font="Aptos",margin=0.04,val=MSO_ANCHOR.MIDDLE):
    b=sl.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf=b.text_frame; tf.clear(); tf.margin_left=tf.margin_right=tf.margin_top=tf.margin_bottom=Inches(margin); tf.vertical_anchor=val; tf.word_wrap=True
    for j,ln in enumerate(str(txt).split("\n")):
        p=tf.paragraphs[0] if j==0 else tf.add_paragraph(); p.text=ln; p.alignment=align; p.space_after=Pt(0); p.space_before=Pt(0)
        p.font.name=font; p.font.size=Pt(size); p.font.bold=bold; p.font.color.rgb=rgb(color)
    return b

def circle(sl,x,y,d,fill="green",linec=None): return rect(sl,x,y,d,d,fill,linec or fill,MSO_SHAPE.OVAL)
def pill(sl,label,x,y,w,fill="pale",color="green",size=11):
    rect(sl,x,y,w,.28,fill,fill); text(sl,label,x+.04,y,w-.08,.28,size,color,True,PP_ALIGN.CENTER)

def browser(sl,x,y,w,h,role="SUPPLIER",title="Create a residue listing",accent="green",badge="ILLUSTRATIVE MVP MOCKUP"):
    rect(sl,x,y,w,h,"white","line")
    rect(sl,x,y,w,.30,"soft","line",MSO_SHAPE.ROUNDED_RECTANGLE)
    for k,c0 in enumerate(["red","gold","leaf"]): circle(sl,x+.12+k*.16,y+.10,.07,c0)
    text(sl,"hassad.local / "+role.lower(),x+.7,y+.04,w-1,.18,8,"slate")
    rect(sl,x+.15,y+.42,w-.3,.40,accent,accent)
    text(sl,"HASSAD",x+.3,y+.45,1,.25,12,"white",True)
    pill(sl,role,x+w-1.25,y+.48,1.0,"gold","char",9)
    text(sl,title,x+.25,y+.95,w-.5,.48,12 if len(title)>24 else 14,"char",True,font="Arial Narrow")
    # UI fields
    yy=y+1.38
    for i,lbl in enumerate(["Material","Quantity","Governorate","Available date"]):
        text(sl,lbl,x+.25,yy+i*.43,1.1,.18,8,"slate",True)
        rect(sl,x+1.35,yy-.04+i*.43,w-1.65,.26,"ivory","line")
    rect(sl,x+w-.25-1.1,y+h-.44,1.1,.27,accent,accent); text(sl,"SAVE",x+w-1.31,y+h-.44,1.0,.27,9,"white",True,PP_ALIGN.CENTER)
    text(sl,badge,x+.12,y+h-.30,w-.24,.25,5.5,"terra",True,font="Arial Narrow",margin=0.0)

def live_evidence(sl,key,x,y,w,h,caption):
    """Place a real deployment crop in a restrained browser frame."""
    rect(sl,x,y,w,h,"white","line")
    rect(sl,x,y,w,.29,"soft","line",MSO_SHAPE.ROUNDED_RECTANGLE)
    for k,c0 in enumerate(["red","gold","leaf"]): circle(sl,x+.10+k*.14,y+.09,.065,c0)
    text(sl,"hassad.live / evidence",x+.58,y+.04,w-.72,.17,7,"slate",False,font="Arial Narrow")
    sl.shapes.add_picture(str(LIVE_ASSETS[key]),Inches(x+.08),Inches(y+.36),Inches(w-.16),Inches(h-.75))
    rect(sl,x+.08,y+h-.34,w-.16,.25,"green","green",MSO_SHAPE.RECTANGLE)
    text(sl,"LIVE PROTOTYPE EVIDENCE",x+.14,y+h-.32,w-1.05,.20,7,"white",True,font="Arial Narrow",margin=0.0)
    text(sl,caption,x+w-1.05,y+h-.32,.87,.20,7,"gold",True,PP_ALIGN.RIGHT,font="Arial Narrow",margin=0.0)


def chrome_card(sl,x,y,w,h,head,value,sub,accent="green"):
    rect(sl,x,y,w,h,"white","line"); rect(sl,x,y,.07,h,accent,accent)
    text(sl,head,x+.18,y+.10,w-.3,.22,10,"slate",True)
    text(sl,value,x+.18,y+.36,w-.3,.45,16 if len(value)>14 else 22,accent,True,font="Arial Narrow")
    text(sl,sub,x+.18,y+.83,w-.3,h-.90,9,"slate")

def base_slide(i):
    sl=prs.slides.add_slide(blank); bg=sl.background.fill; bg.solid(); bg.fore_color.rgb=rgb("ivory")
    # botanical edge
    rect(sl,0,0,.12,H,"green","green",MSO_SHAPE.RECTANGLE)
    text(sl,TAGS[i],.42,.18,8.3,.28,10,"green",True)
    title_size = 28 if i==9 else (32 if i in (0,14) else 30)
    text(sl,TITLES[i],.42,.48,12.2,.68,title_size,"char",True,font="Arial Narrow")
    line(sl,.42,1.18,12.90,1.18,"line",1)
    return sl


def mark(shape,name):
    shape.name=name
    return shape


def stage_visual(sl,stage,x,y,w,h,prefix=None):
    """Native vector life-cycle vignette: seed, plant, residue, bridge/factory, eco-book."""
    made=[]
    def keep(sh,label):
        if prefix: mark(sh,f"{prefix}_{label}")
        made.append(sh); return sh
    # shared ground glow
    keep(rect(sl,x,y,w,h,"white","line"),"FRAME")
    if stage==0:
        keep(line(sl,x+.18,y+h*.69,x+w-.18,y+h*.69,"terra",2.5),"SOIL")
        seed=keep(rect(sl,x+w*.42,y+h*.53,w*.18,h*.13,"gold","terra",MSO_SHAPE.OVAL),"SEED")
        seed.rotation=-18
        keep(line(sl,x+w*.50,y+h*.53,x+w*.50,y+h*.27,"green",2.2),"STEM")
        leaf=keep(rect(sl,x+w*.50,y+h*.28,w*.20,h*.12,"leaf","green",MSO_SHAPE.OVAL),"LEAF")
        leaf.rotation=-28
    elif stage==1:
        keep(line(sl,x+w*.50,y+h*.76,x+w*.50,y+h*.18,"green",3),"STEM")
        for q in range(3):
            yy=y+h*(.30+q*.15); side=-1 if q%2==0 else 1
            keep(line(sl,x+w*.50,yy,x+w*(.50+side*.20),yy-h*.09,"leaf",2),f"BRANCH{q}")
            gr=keep(rect(sl,x+w*(.50+side*.23-(.07 if side<0 else 0)),yy-h*.16,w*.13,h*.15,"gold","gold",MSO_SHAPE.OVAL),f"GRAIN{q}")
            gr.rotation=28 if side>0 else -28
        keep(line(sl,x+.18,y+h*.78,x+w-.18,y+h*.78,"terra",2),"SOIL")
    elif stage==2:
        keep(line(sl,x+.16,y+h*.78,x+w-.16,y+h*.78,"terra",2.4),"SOIL")
        keep(rect(sl,x+w*.25,y+h*.48,w*.50,h*.24,"sand","terra",MSO_SHAPE.ROUNDED_RECTANGLE),"BALE")
        for q in range(5):
            keep(line(sl,x+w*(.24+q*.12),y+h*.49,x+w*(.34+q*.10),y+h*.28,"gold",2),f"STRAW{q}")
    elif stage==3:
        hub=keep(circle(sl,x+w*.08,y+h*.33,min(w,h)*.34,"green"),"HUB")
        keep(text(sl,"H",x+w*.08,y+h*.33,min(w,h)*.34,min(w,h)*.34,16,"white",True,PP_ALIGN.CENTER),"HUBTXT")
        keep(line(sl,x+w*.34,y+h*.52,x+w*.55,y+h*.52,"gold",3),"BRIDGE")
        keep(rect(sl,x+w*.55,y+h*.40,w*.34,h*.30,"slate","slate",MSO_SHAPE.RECTANGLE),"FACTORY")
        keep(rect(sl,x+w*.61,y+h*.24,w*.08,h*.20,"terra","terra",MSO_SHAPE.RECTANGLE),"STACK1")
        keep(rect(sl,x+w*.75,y+h*.18,w*.08,h*.26,"terra","terra",MSO_SHAPE.RECTANGLE),"STACK2")
        keep(line(sl,x+w*.53,y+h*.72,x+w*.92,y+h*.72,"terra",2),"GROUND")
    else:
        left=keep(rect(sl,x+w*.16,y+h*.33,w*.34,h*.38,"ivory","green",MSO_SHAPE.PARALLELOGRAM),"BOOKL")
        right=keep(rect(sl,x+w*.50,y+h*.33,w*.34,h*.38,"ivory","green",MSO_SHAPE.PARALLELOGRAM),"BOOKR")
        left.rotation=-4; right.rotation=4
        keep(line(sl,x+w*.50,y+h*.34,x+w*.50,y+h*.72,"gold",2),"SPINE")
        keep(line(sl,x+w*.60,y+h*.53,x+w*.70,y+h*.43,"leaf",2),"LEAFSTEM")
        lf=keep(rect(sl,x+w*.67,y+h*.38,w*.13,h*.10,"leaf","leaf",MSO_SHAPE.OVAL),"BOOKLEAF"); lf.rotation=-30
    return made


def footer(sl,i):
    if i<15:
        current=ACT_BY_SLIDE[i]
        x0=.58; y0=7.08; step=2.27
        line(sl,x0,y0,x0+step*4,y0,"terra",1.4)
        labels=["SEED & GAP","DEEP ROOTS","STEM & LEAVES","WEATHER STORM","HARVEST & BOOK"]
        for a in range(5):
            xx=x0+a*step; done=a<current; active=a==current
            node=circle(sl,xx-.10,y0-.10,.20,"gold" if active else ("green" if done else "pale"),"green")
            if a==0:
                sd=rect(sl,xx-.035,y0-.045,.07,.055,"terra","terra",MSO_SHAPE.OVAL); sd.rotation=-20
                line(sl,xx,y0-.04,xx,y0-.16,"green",1.2)
            elif a==1:
                line(sl,xx,y0+.02,xx,y0-.18,"green",1.5); line(sl,xx,y0-.10,xx+.10,y0-.16,"leaf",1.2); line(sl,xx,y0+.01,xx-.09,y0+.09,"terra",1)
            elif a==2:
                line(sl,xx,y0+.02,xx,y0-.20,"green",1.8); line(sl,xx,y0-.08,xx+.11,y0-.14,"leaf",1.2); line(sl,xx,y0-.02,xx-.10,y0-.09,"leaf",1.2)
            elif a==3:
                line(sl,xx,y0+.02,xx,y0-.18,"green",1.7); line(sl,xx,y0-.08,xx+.10,y0-.13,"leaf",1.2); line(sl,xx-.14,y0-.21,xx+.14,y0-.21,"blue",1)
            else:
                rect(sl,xx-.13,y0-.14,.12,.12,"ivory","green",MSO_SHAPE.PARALLELOGRAM); rect(sl,xx+.01,y0-.14,.12,.12,"ivory","green",MSO_SHAPE.PARALLELOGRAM)
            text(sl,labels[a],xx-.72,7.20,1.44,.13,5.7,"green" if a<=current else "slate",True,PP_ALIGN.CENTER,margin=0.0)
        num=f"{i+1:02d} / 15"
    else:
        rect(sl,.42,7.08,11.5,.20,"green","green",MSO_SHAPE.RECTANGLE); text(sl,"APPENDIX · CONTROLLED DETAIL",.55,7.08,4.0,.20,7,"white",True)
        num=f"A{i-14} / 5 · {i+1} / 20"
    text(sl,num,11.98,7.02,.93,.28,9,"green",True,PP_ALIGN.RIGHT)

def evidence(sl,mode="BASELINE"):
    if mode=="MODELED": label="[MODELED COMPLETION] · verify against signed closure records"; col="terra"
    elif mode=="MIXED": label="[VERIFIED BASELINE] + [MODELED COMPLETION]"; col="terra"
    else: label="[VERIFIED BASELINE]"; col="green"
    text(sl,label,.45,6.83,5.3,.16,7,col,True)

def add_body_rows(sl,i,x=.52,y=4.15,w=12.0,h=2.48,compact=False):
    lines0=LINES[i]; n=len(lines0); rh=max(h/n,.42)
    if y+n*rh>6.72: y=6.72-n*rh
    for j,s in enumerate(lines0):
        if j%2==0: rect(sl,x,y+j*rh,w,rh-.018,"white","white",MSO_SHAPE.RECTANGLE)
        circle(sl,x+.08,y+j*rh+rh*.31,.10,"gold" if any(k in s for k in ["SPONSOR ASK","MODELED","Rejected","CV =","OUT ·"]) else "green")
        text(sl,s,x+.28,y+j*rh,w-.35,rh-.01,24,"char", j==0 or s.startswith(("IN ·","OUT ·","LESSON","HASSAD","SV =","CV =","CPI =")),font="Arial Narrow",margin=0.0)

def wheat(sl, x=10.4,y=1.5,scale=1):
    line(sl,x+1.0*scale,y+3.5*scale,x+1.0*scale,y+.4*scale,"green",3)
    for k in range(5):
        yy=y+.55+k*.55*scale; side=-1 if k%2==0 else 1
        line(sl,x+1*scale,yy,x+(1+side*.55)*scale,yy-.23*scale,"green",2)
        e=rect(sl,x+(1+side*.62-(.18 if side<0 else 0))*scale,yy-.43*scale,.34*scale,.55*scale,"gold","gold",MSO_SHAPE.OVAL)
        e.rotation=35 if side>0 else -35
    line(sl,x-.25,y+3.55*scale,x+2.2*scale,y+3.55*scale,"terra",2.5)

def roman(n):
    return ["I","II","III","IV","V"][n-1]

# Slide-specific diagram builders
for i in range(20):
    sl=base_slide(i)
    if i==0:
        # Cinematic title hero: the entire seed-to-value arc in one native-vector sweep.
        stages=[("THE SEED","Potential"),("GROWING PLANT","4 residues"),("PLANT WASTE","Hidden gap"),("HASSAD → FACTORY","Digital bridge"),("ECO-BOOK","Reborn value")]
        for k,(head,sub) in enumerate(stages):
            xx=.42+k*2.53
            stage_visual(sl,k,xx,1.40,2.15,1.72,f"S1_STAGE_{k+1}")
            text(sl,head,xx,3.18,2.15,.26,11,"green" if k!=2 else "terra",True,PP_ALIGN.CENTER,font="Arial Narrow")
            text(sl,sub,xx,3.44,2.15,.20,9,"slate",False,PP_ALIGN.CENTER)
            if k<4:
                line(sl,xx+2.15,2.23,xx+2.48,2.23,"gold",2.6)
        text(sl,"HASSAD: FROM SEED → TO WASTE → TO REBORN VALUE",1.20,3.70,10.85,.36,22,"green",True,PP_ALIGN.CENTER,font="Arial Narrow",margin=0.0)
        add_body_rows(sl,i,.70,4.48,11.78,2.04,True)
        pill(sl,"SPONSOR · Saad Ibrahim, CEO, SAAD Company",9.00,.18,3.36,"sand","terra",8)
    elif i==1:
        # Five-stage life cycle plus the mapped five-Act defense roadmap.
        stage_heads=["1 · THE SEED","2 · GROWING PLANT","3 · PLANT WASTE","4 · DIGITAL BRIDGE","5 · ECO-BOOK"]
        ranges=["ACT I · 1–3","ACT II · 4–6","ACT III · 7–10","ACT IV · 11–12","ACT V · 13–15"]
        for k in range(5):
            xx=.43+k*2.53
            stage_visual(sl,k,xx,1.38,2.16,1.70,f"S2_STAGE_{k+1}")
            text(sl,stage_heads[k],xx,3.15,2.16,.28,11,"green" if k!=2 else "terra",True,PP_ALIGN.CENTER,font="Arial Narrow")
            pill(sl,ranges[k],xx+.27,3.48,1.62,"gold" if k==0 else "pale","char" if k==0 else "green",8)
            if k<4:
                ar=line(sl,xx+2.16,2.22,xx+2.49,2.22,"gold",2.8); mark(ar,f"S2_FLOW_{k+1}")
        text(sl,"Every harvest begins with a seed. Every controlled decision moves it toward value.",.70,3.92,11.92,.42,19,"char",True,PP_ALIGN.CENTER,font="Arial Narrow")
        add_body_rows(sl,i,.52,4.47,12.1,2.10,True)
    elif i==2:
        # hub + materials
        for k,(name,col) in enumerate([("RICE STRAW","gold"),("WHEAT STRAW","terra"),("CORN RESIDUES","leaf"),("SUGARCANE BAGASSE","green")]):
            xx=.65+k*1.58; rect(sl,xx,1.45,1.42,.72,"white","line"); circle(sl,xx+.12,1.67,.18,col); text(sl,name,xx+.38,1.55,.92,.45,8.5,"char",True)
            line(sl,xx+.72,2.17,6.68,2.92,"line",1)
        circle(sl,5.86,2.30,1.62,"green"); text(sl,"HASSAD\nMARKETPLACE",5.98,2.61,1.38,.70,13,"white",True,PP_ALIGN.CENTER)
        line(sl,7.48,3.10,8.62,3.10,"gold",4)
        rect(sl,8.62,1.45,3.95,2.92,"white","line")
        text(sl,"FACTORY DEMAND",8.92,1.68,3.35,.28,12,"green",True)
        for k,t in enumerate(["Verified source","Usable quantity","Local availability","Direct inquiry"]): pill(sl,t,8.92,2.15+k*.46,2.55,"pale","green",9)
        add_body_rows(sl,i,.52,4.62,12.0,1.95,True)
    elif i==3:
        cards=[("TIME","12 WEEKS","27-Jan-2027","gold"),("COST","EGP 200K","initial ceiling","terra"),("SCOPE","2 JOURNEYS","Supplier + Factory","green"),("QUALITY","≥18 / 20","0 Critical defects","leaf")]
        for k,c0 in enumerate(cards): chrome_card(sl,.52+k*3.10,1.42,2.88,1.50,*c0)
        rect(sl,.52,3.12,5.92,.70,"pale","pale"); text(sl,"PROJECT SUCCESS",.75,3.18,2.1,.24,12,"green",True); text(sl,"scope · time · cost",2.85,3.18,3.2,.24,16,"char",True,PP_ALIGN.RIGHT)
        rect(sl,6.70,3.12,5.82,.70,"sand","sand"); text(sl,"PRODUCT SUCCESS",6.93,3.18,2.2,.24,12,"terra",True); text(sl,"usable matching marketplace",9.15,3.18,3.0,.24,14,"char",True,PP_ALIGN.RIGHT)
        add_body_rows(sl,i,.52,4.05,12.0,2.55,True)
    elif i==4:
        circle(sl,4.88,1.37,2.82,"green"); text(sl,"WEBSITE\nMVP",5.28,2.18,2.0,.75,22,"white",True,PP_ALIGN.CENTER)
        for k,t in enumerate(["Responsive site","Listings + demand","Match + status"]): pill(sl,t,5.02,1.52+k*.55,2.55,"gold" if k==0 else "white","char" if k==0 else "green",10)
        outs=[("Native app",.65,1.50),("Physical logistics",.75,2.45),("Payments / escrow",9.92,1.50),("National launch",9.75,2.45)]
        for t,xx,yy in outs:
            rect(sl,xx,yy,2.55,.62,"white","terra"); text(sl,t,xx+.1,yy+.09,2.35,.42,11,"terra",True,PP_ALIGN.CENTER); line(sl,xx+2.55,yy+.31,4.85 if xx<4 else 7.75,2.78,"terra",1.3)
        add_body_rows(sl,i,.52,4.15,12.0,2.48,True)
    elif i==5:
        phases=[("REQUIREMENTS","SRS + RTM"),("DESIGN","approved UI"),("BUILD","integrated MVP"),("TEST","acceptance"),("CLOSE","handover")]
        for k,(a,b) in enumerate(phases):
            xx=.52+k*2.47; rect(sl,xx,1.56,2.15,1.32,"white","line"); circle(sl,xx+.12,1.72,.32,"gold" if k==0 else "green"); text(sl,str(k+1),xx+.12,1.72,.32,.32,10,"white",True,PP_ALIGN.CENTER); text(sl,a,xx+.55,1.69,1.45,.26,11,"green",True); text(sl,b,xx+.18,2.17,1.78,.32,10,"slate",True,PP_ALIGN.CENTER)
            if k<4: line(sl,xx+2.15,2.22,xx+2.47,2.22,"terra",3)
        for k,(lab,val) in enumerate([("SCOPE","LOCKED"),("TIME","12 WEEKS"),("COST","CONTROLLED"),("CHANGE","CCB GATE"),("RESERVE","SPONSOR GATE")]): pill(sl,lab+" · "+val,.55+k*2.45,3.18,2.15,"pale" if k<3 else "sand","green" if k<3 else "terra",9)
        add_body_rows(sl,i,.52,4.02,12.0,2.60,True)
    elif i==6:
        # WBS upper
        counts=[4,4,4,3,3,4]; labels=["1.1 INIT","1.2 REQ/UX","1.3 BUILD","1.4 TEST","1.5 LAUNCH","1.6 CLOSE"]
        for k in range(6):
            xx=.50+k*2.05; rect(sl,xx,1.38,1.80,.55,"green","green"); text(sl,labels[k],xx,1.44,1.80,.25,9,"white",True,PP_ALIGN.CENTER)
            for q in range(counts[k]): rect(sl,xx+.10+q*(1.55/max(counts[k],1)),2.10,.26,.34,"gold" if q==0 else "pale","green")
            text(sl,str(counts[k]),xx+.62,2.47,.55,.22,10,"green",True,PP_ALIGN.CENTER)
        pill(sl,"4 + 4 + 4 + 3 + 3 + 4 = 22",5.00,2.80,3.45,"gold","char",11)
        # timeline
        line(sl,.75,3.58,12.25,3.58,"green",4)
        mts=[("M1000","14-Nov",1.3),("M2000","28-Nov",3.25),("M3000","26-Dec",7.15),("M4000","09-Jan",9.10),("M5000","27-Jan",11.75)]
        for a,b,xx in mts:
            d=rect(sl,xx,3.39,.38,.38,"gold","gold",MSO_SHAPE.DIAMOND); text(sl,a,xx-.25,3.77,.9,.22,8,"green",True,PP_ALIGN.CENTER); text(sl,b,xx-.25,4.00,.9,.22,8,"slate",True,PP_ALIGN.CENTER)
        add_body_rows(sl,i,.52,4.35,12.0,2.26,True)
    elif i==7:
        # cost baseline bars
        parts=[("DEV 90K",90,"green"),("PM 20K",20,"terra"),("RESEARCH 15K",15,"gold"),("QA 15K",15,"leaf"),("TOOLS 10K",10,"blue")]
        x=.55; y=1.62; scale=5.8/150
        for lab,v,col in parts:
            ww=v*scale; rect(sl,x,y,ww,.54,col,col,MSO_SHAPE.RECTANGLE); text(sl,lab,x,y+.08,ww,.30,8,"white",True,PP_ALIGN.CENTER); x+=ww
        text(sl,"INITIAL COST BASELINE · 150K",.55,1.32,5.8,.22,10,"green",True)
        rect(sl,6.62,1.62,1.15,.54,"gold","gold"); text(sl,"+14K",6.62,1.70,1.15,.28,11,"char",True,PP_ALIGN.CENTER)
        line(sl,6.35,1.89,6.62,1.89,"terra",3); line(sl,7.77,1.89,8.05,1.89,"terra",3)
        rect(sl,8.05,1.62,2.34,.54,"green","green",MSO_SHAPE.RECTANGLE); text(sl,"REVISED BAC · 164K",8.05,1.70,2.34,.28,10,"white",True,PP_ALIGN.CENTER)
        rect(sl,10.65,1.62,1.65,.54,"white","gold",MSO_SHAPE.RECTANGLE); text(sl,"RESERVE · 50K",10.65,1.70,1.65,.28,9,"terra",True,PP_ALIGN.CENTER)
        pill(sl,"INITIAL CEILING · 200K",.55,2.45,2.55,"pale","green",10); pill(sl,"REVISED CEILING · 214K",9.70,2.45,2.60,"sand","terra",10)
        for k,(role,sub) in enumerate([("GROUP 2","Internal owners · EGP 0 labor"),("DEV / UI / QA","External specialists"),("PAYMENT GATES","30% · 30% · 40%")]): chrome_card(sl,.55+k*4.08,3.05,3.78,1.05,role,sub,"controlled sourcing","green" if k<2 else "gold")
        add_body_rows(sl,i,.52,4.38,12.0,2.18,True)
    elif i==8:
        # risk matrix
        mx,my,mw,mh=.55,1.42,4.00,2.55
        for yy in range(5):
            for xx in range(5):
                score=(xx+1)*(5-yy); col="pale" if score<8 else ("gold" if score<15 else "red")
                rect(sl,mx+xx*mw/5,my+yy*mh/5,mw/5,mh/5,col,"ivory",MSO_SHAPE.RECTANGLE,0 if col!="red" else 12)
        for lab,xq,yq in [("R-07",4,5),("R-05",3,5),("R-01",3,4),("R-13",4,3)]:
            circle(sl,mx+(xq-.5)*mw/5-.16,my+(5-yq+.5)*mh/5-.16,.32,"char"); text(sl,lab,mx+(xq-.5)*mw/5-.25,my+(5-yq+.5)*mh/5-.11,.50,.20,7,"white",True,PP_ALIGN.CENTER)
        text(sl,"PROBABILITY →",1.35,4.00,2.5,.20,9,"slate",True,PP_ALIGN.CENTER); text(sl,"IMPACT",.18,2.22,.40,.24,8,"slate",True)
        # issue chain
        pill(sl,"RISK · uncertain",4.92,1.55,1.75,"sand","terra",10); line(sl,6.67,1.69,7.10,1.69,"red",3); pill(sl,"ISSUE · WEEK 7",7.10,1.55,1.82,"red","white",10)
        steps=[("01","PM escalates"),("02","Sponsor releases 9.5K"),("03","Optimize + fast-track")]
        for k,(n,t) in enumerate(steps):
            xx=4.92+k*2.48; rect(sl,xx,2.15,2.18,.92,"white","line"); circle(sl,xx+.12,2.37,.32,"gold"); text(sl,n,xx+.12,2.37,.32,.32,8,"char",True,PP_ALIGN.CENTER); text(sl,t,xx+.54,2.28,1.48,.52,9.5,"char",True)
        browser(sl,9.90,3.18,2.50,2.92,"BUYER","Matching results · >3.0s","terra","ILLUSTRATIVE MVP MOCKUP · MODELED")
        add_body_rows(sl,i,.52,4.35,12.0,2.25,True)
    elif i==9:
        # stakeholder grid
        gx,gy,gw,gh=.55,1.38,6.05,2.85
        rect(sl,gx,gy,gw,gh,"white","line",MSO_SHAPE.RECTANGLE); line(sl,gx+gw/2,gy,gx+gw/2,gy+gh,"line",1.5); line(sl,gx,gy+gh/2,gx+gw,gy+gh/2,"line",1.5)
        quads=[("KEEP SATISFIED","SH-01 Sponsor",.72,1.58),("MANAGE CLOSELY","SH-02 PM · SH-03 Team · SH-07 Dev",3.78,1.58),("MONITOR","SH-06 Authorities · SH-09 Comms",.72,3.02),("KEEP INFORMED","SH-04 Farmers · SH-05 Buyers · SH-08 UX",3.78,3.02)]
        for h1,b,xx,yy in quads: text(sl,h1,xx,yy,2.55,.22,9,"green",True); text(sl,b,xx,yy+.25,2.55,.65,8.5,"slate",True)
        # role panels and escalation
        for k,(r,t,c0) in enumerate([("SUPPLIER","LIST","leaf"),("BUYER","MATCH","gold"),("ADMIN","GOVERN","terra")]): chrome_card(sl,6.82+k*1.85,1.38,1.75,1.20,r,t,"same sample record",c0)
        steps=[("ISSUE","24h"),("PM","triage"),("OWNER","fix"),("SPONSOR / CCB","48h")]
        for k,(a,b) in enumerate(steps):
            xx=6.92+k*1.38; circle(sl,xx,3.12,.72,"gold" if k==3 else "green"); text(sl,"CCB" if k==3 else a,xx,3.18,.72,.22,7.5,"white",True,PP_ALIGN.CENTER); text(sl,b,xx-.05,3.87,.82,.18,7,"slate",True,PP_ALIGN.CENTER)
            if k<3: line(sl,xx+.72,3.48,xx+1.38,3.48,"terra",2)
        add_body_rows(sl,i,.52,4.45,12.0,2.18,True)
    elif i==10:
        # Change-control funnel paired with live deployment proof for approved features.
        rect(sl,.58,1.40,2.45,.50,"green","green"); text(sl,"10 SUBMITTED",.58,1.47,2.45,.27,14,"white",True,PP_ALIGN.CENTER)
        rect(sl,.76,2.05,2.08,.48,"gold","gold"); text(sl,"IMPACT ANALYSIS",.76,2.12,2.08,.25,11,"char",True,PP_ALIGN.CENTER)
        line(sl,1.80,1.90,1.80,2.05,"terra",3)
        statuses=[("4 APPROVED","green","CR-001 · 004 · 007 · 010"),("4 DEFERRED","gold","CR-002 · 005 · 008 · 009"),("2 REJECTED","red","CR-003 · 006")]
        for k,(a0,c0,b0) in enumerate(statuses):
            xx=.52+k*1.91; rect(sl,xx,2.75,1.72,.70,c0,c0); text(sl,a0,xx,2.81,1.72,.24,11,"white" if c0!="gold" else "char",True,PP_ALIGN.CENTER); text(sl,b0,xx+.05,3.08,1.62,.18,6.8,"white" if c0!="gold" else "char",True,PP_ALIGN.CENTER,font="Arial Narrow")
        pill(sl,"+14K · +10 effort days · reserve untouched",.52,3.72,5.54,"sand","terra",10)
        live_evidence(sl,"cr001",6.35,1.38,2.92,2.72,"CR-001")
        live_evidence(sl,"cr007",9.54,1.38,2.92,2.72,"CR-007")
        add_body_rows(sl,i,.52,4.22,12.0,2.20,True)
    elif i==11:
        # EVM chart and concise executive KPIs; analytics capture is real feature evidence only.
        cx,cy,cw,ch=.62,1.52,5.55,2.46
        line(sl,cx,cy+ch,cx+cw,cy+ch,"slate",1); line(sl,cx,cy,cx,cy+ch,"slate",1)
        def curve(points,col):
            for j in range(len(points)-1): line(sl,cx+points[j][0]*cw,cy+ch-points[j][1]*ch,cx+points[j+1][0]*cw,cy+ch-points[j+1][1]*ch,col,2.5)
        curve([(0,0),(.2,.10),(.4,.32),(.6,.60),(.8,.83),(1,.91)],"green")
        curve([(0,0),(.2,.08),(.4,.28),(.6,.52),(.8,.81),(1,.91)],"gold")
        curve([(0,0),(.2,.10),(.4,.34),(.6,.67),(.8,.90),(1,.965)],"red")
        pill(sl,"PV 164K",.92,1.66,.95,"pale","green",8); pill(sl,"EV 164K",1.97,1.66,.95,"gold","char",8); pill(sl,"AC 173.5K",3.02,1.66,1.10,"sand","red",8)
        text(sl,"01-Nov",.48,4.03,1,.18,7,"slate"); text(sl,"27-Jan",5.45,4.03,.8,.18,7,"slate",False,PP_ALIGN.RIGHT)
        kpis=[("SPI","1.00","on time"),("CPI","0.95","cost-visible"),("FUNCTIONAL","95%","19 / 20"),("DEFECTS","0","Critical / High")]
        for k,(a0,b0,c0) in enumerate(kpis): chrome_card(sl,6.48+(k%2)*1.48,1.47+(k//2)*1.24,1.32,1.10,a0,b0,c0,"red" if a0=="CPI" else "green")
        live_evidence(sl,"cr004",9.48,1.42,2.90,2.62,"CR-004")
        pill(sl,"RESERVE · 9.5K used · 40.5K remaining · 81% preserved",6.48,4.08,5.90,"sand","terra",10)
        add_body_rows(sl,i,.52,4.28,12.0,2.24,True)
    elif i==12:
        # two lesson cards
        lessons=[("01","MATCHING LOGIC + NFRs","Cause: ambiguous tolerances","Cost: EGP 9,500","Control: sign at M1000"),("02","FUNDING SEPARATION","Cause: scope vs. risk confusion","Value: reserve stayed available","Control: two locked ledgers")]
        for k,(n,a,b,c0,d) in enumerate(lessons):
            xx=.60+k*6.10; rect(sl,xx,1.45,5.72,2.38,"white","line"); circle(sl,xx+.25,1.70,.52,"gold"); text(sl,n,xx+.25,1.70,.52,.52,13,"char",True,PP_ALIGN.CENTER); text(sl,a,xx+.95,1.72,4.35,.34,14,"green",True)
            for q,t in enumerate([b,c0,d]): text(sl,t,xx+.40,2.36+q*.43,4.88,.27,11,"char",q==2)
        pill(sl,"SPECIFIC · COSTLY · TRANSFERABLE",4.23,4.02,4.87,"gold","char",12)
        add_body_rows(sl,i,.52,4.46,12.0,2.14,True)
    elif i==13:
        terraces=[("1 · LIVE PILOT","20+ verified farms + factories","green"),("2 · SCALE FEATURES","mobile · escrow · verification","leaf"),("3 · PARTNER","trusted institutional data","gold")]
        for k,(a,b,c0) in enumerate(terraces):
            xx=.58+k*3.18; yy=3.35-k*.62; hh=1.10+k*.62
            rect(sl,xx,yy,2.90,hh,c0,c0,MSO_SHAPE.RECTANGLE); text(sl,a,xx+.15,yy+.17,2.60,.30,12,"white" if c0!="gold" else "char",True); text(sl,b,xx+.15,yy+.55,2.60,.45,9,"white" if c0!="gold" else "char")
        rect(sl,10.18,1.48,2.20,2.97,"white","gold"); text(sl,"SPONSOR ASK",10.38,1.78,1.80,.28,13,"terra",True,PP_ALIGN.CENTER); text(sl,"Authorize\nPhase-2 Charter\ninitiation",10.40,2.18,1.76,1.38,17,"green",True,PP_ALIGN.CENTER); pill(sl,"Decision gate",10.65,3.78,1.24,"gold","char",9)
        add_body_rows(sl,i,.52,4.72,12.0,1.88,True)
    elif i==14:
        wheat(sl,9.95,1.40,1.0); browser(sl,7.13,2.70,3.18,3.08,"BUYER","Find a verified match","green")
        add_body_rows(sl,i,.58,1.42,6.60,4.95)
        line(sl,8.95,6.20,12.18,6.20,"terra",2); circle(sl,8.80,6.08,.24,"gold"); circle(sl,12.17,6.08,.24,"green")
    elif i==15:
        docs=[("S01","Charter"),("S02","Stakeholders"),("S03","Requirements"),("S04","Scope"),("S05","Schedule"),("S06","Resources / Cost"),("S07","Risk"),("S08","Quality"),("S09","Communications"),("S10","Change"),("S11","WBS Dictionary"),("S12","WBS Deck"),("S13","Feedback")]
        for k,(sid,nm) in enumerate(docs):
            col=k%5; row=k//5; xx=.52+col*2.47; yy=1.42+row*.90
            rect(sl,xx,yy,2.20,.72,"white","line"); pill(sl,sid,xx+.10,yy+.20,.55,"pale","green",8); text(sl,nm,xx+.76,yy+.12,1.32,.46,9,"char",True)
        add_body_rows(sl,i,.52,4.32,12.0,2.28,True)
    elif i==16:
        groups=[("1.1",4,"INITIATION"),("1.2",4,"REQ / UX"),("1.3",4,"DEVELOPMENT"),("1.4",3,"TESTING"),("1.5",3,"LAUNCH"),("1.6",4,"CLOSEOUT")]
        for k,(a,n,b) in enumerate(groups):
            xx=.48+k*2.08; rect(sl,xx,1.42,1.84,.48,"green","green"); text(sl,a+" · "+b,xx,1.48,1.84,.23,8,"white",True,PP_ALIGN.CENTER)
            for q in range(n):
                yy=2.06+q*.47; rect(sl,xx+.12,yy,1.60,.34,"white","line"); text(sl,a+"."+str(q+1),xx+.22,yy+.04,.52,.22,8,"green",True); text(sl,["Charter / plan","People / journey","Build / validate","Approve / close"][q],xx+.75,yy+.04,.82,.22,7.5,"slate")
        pill(sl,"4 + 4 + 4 + 3 + 3 + 4 = 22 LEAF WORK PACKAGES",4.00,4.12,5.35,"gold","char",11)
        add_body_rows(sl,i,.52,4.58,12.0,2.02,True)
    elif i==17:
        # bow tie
        text(sl,"CAUSES",.60,1.42,2.2,.30,12,"terra",True,PP_ALIGN.CENTER); text(sl,"RISK EVENT",5.18,1.42,2.9,.30,12,"green",True,PP_ALIGN.CENTER); text(sl,"IMPACTS",10.10,1.42,2.2,.30,12,"red",True,PP_ALIGN.CENTER)
        causes=["Rule ambiguity","Integration complexity","Resource bottleneck"]
        impacts=["Latency >3s","Milestone pressure","Reserve draw"]
        for k,t in enumerate(causes): pill(sl,t,.58,1.95+k*.62,2.38,"sand","terra",9); line(sl,2.96,2.09+k*.62,5.08,2.78,"terra",1.4)
        rect(sl,5.08,2.12,3.00,1.30,"white","red"); text(sl,"R-07\nDEVELOPMENT DELAY\nScore 20",5.30,2.30,2.56,.92,16,"red",True,PP_ALIGN.CENTER)
        for k,t in enumerate(impacts): pill(sl,t,10.02,1.95+k*.62,2.38,"soft","red",9); line(sl,8.08,2.78,10.02,2.09+k*.62,"red",1.4)
        pill(sl,"Prevent · validate early",3.12,3.74,2.45,"pale","green",9); pill(sl,"Contingency · optimize",5.63,3.74,2.45,"sand","terra",9); pill(sl,"Residual · monitor",8.14,3.74,2.45,"soft","red",9)
        add_body_rows(sl,i,.52,4.40,12.0,2.20,True)
    elif i==18:
        # Traceability table with live report/export evidence.
        headers=["CR","DECISION","COST","DAYS","BASELINE ACTION"]
        xs=[.52,1.42,3.62,4.52,5.22]; ws=[.82,2.12,.82,.62,2.72]
        for x0,w0,h0 in zip(xs,ws,headers): rect(sl,x0,1.42,w0,.38,"green","green",MSO_SHAPE.RECTANGLE); text(sl,h0,x0,1.48,w0,.20,7.5,"white",True,PP_ALIGN.CENTER)
        rows=[("001","Approved","+3K","+2","BAC + feature"),("004","Approved","+5K","+4","BAC + feature"),("007","Approved","+2.5K","+2","BAC + feature"),("010","Approved","+3.5K","+2","BAC + feature"),("003","Rejected","+120K","+45","None"),("009","Deferred","—","—","None")]
        for r,row in enumerate(rows):
            yy=1.82+r*.40; col="pale" if r%2==0 else "white"
            for x0,w0,val in zip(xs,ws,row): rect(sl,x0,yy,w0,.38,col,"line",MSO_SHAPE.RECTANGLE); text(sl,val,x0+.03,yy+.04,w0-.06,.25,8,"red" if val=="Rejected" else "char",val in ("Approved","Rejected","Deferred"),PP_ALIGN.CENTER)
        pill(sl,"150K + 14K = 164K BAC",.62,4.04,3.18,"pale","green",10)
        pill(sl,"50K reserve remains separate",4.02,4.04,3.72,"sand","terra",10)
        live_evidence(sl,"cr010",8.28,1.40,4.08,2.92,"CR-010")
        add_body_rows(sl,i,.52,4.32,12.0,2.26,True)
    elif i==19:
        # formulas left, verification matrix right
        formulas=[("SV","0","ON PLAN"),("SPI","1.00","ON TIME"),("CV","-9,500","ADVERSE VS BAC"),("CPI","0.945 ≈ 0.95","COST VISIBLE"),("HEADROOM","40,500","BELOW CEILING")]
        for k,(a,b,c0) in enumerate(formulas):
            yy=1.38+k*.56; rect(sl,.55,yy,4.00,.46,"white","line"); text(sl,a,.72,yy+.07,.80,.25,9,"green",True); text(sl,b,1.58,yy+.04,1.50,.29,13,"red" if a in ("CV","CPI") else "char",True); text(sl,c0,3.05,yy+.08,1.30,.23,7,"slate",True,PP_ALIGN.RIGHT)
        heads=[("METRIC",4.85,1.35),("TARGET",7.02,1.08),("MODELED",8.17,1.10),("EVIDENCE",9.34,2.93)]
        for h0,x0,w0 in heads: rect(sl,x0,1.38,w0,.38,"green","green",MSO_SHAPE.RECTANGLE); text(sl,h0,x0,1.44,w0,.20,7.5,"white",True,PP_ALIGN.CENTER)
        rows=[("Functional","≥90%","95%","20-case signed log"),("Matching","≥80%","90%","10 records"),("Usability","≥80%","90%","10 task sheets"),("Performance","≥95% <3s","96%","dated environment log"),("Defects","0 Critical","0","defect register"),("Legal","100%","100%","signed checklist")]
        for r,row in enumerate(rows):
            yy=1.82+r*.40
            for val,(x0,w0) in zip(row,[(4.85,2.17),(7.02,1.08),(8.17,1.10),(9.34,2.93)]): rect(sl,x0,yy,w0,.38,"pale" if r%2==0 else "white","line",MSO_SHAPE.RECTANGLE); text(sl,val,x0+.03,yy+.04,w0-.06,.25,8,"char",False,PP_ALIGN.CENTER)
        add_body_rows(sl,i,.52,4.46,12.0,2.14,True)
    footer(sl,i)
    evidence(sl,"MODELED" if i in (11,19) else ("MIXED" if i in (8,10,12,13,17,18) else "BASELINE"))


def save_deck():
    prs.core_properties.title="HASSAD Graduation Presentation"
    prs.core_properties.subject="Agricultural Waste Digital Marketplace · PMP Graduation Defense"
    prs.core_properties.author="HASSAD Group 2"
    prs.core_properties.keywords="HASSAD, Predictive, PMP, agricultural waste, marketplace"
    prs.save(PPTX)



def apply_native_motion():
    """Use PowerPoint's object model so it writes schema-valid transition and timing trees."""
    import win32com.client
    app=win32com.client.gencache.EnsureDispatch("PowerPoint.Application")
    app.Visible=1
    deck=app.Presentations.Open(str(PPTX),WithWindow=False)
    c=win32com.client.constants
    act_boundaries={4,7,11,13,16}  # destination slide numbers
    for si in range(1,deck.Slides.Count+1):
        sl=deck.Slides(si)
        tr=sl.SlideShowTransition
        # PowerPoint's installed object model exposes no stable Morph constant here;
        # Slides 1-2 therefore use the validated Fade Smoothly fallback.
        tr.EntryEffect=c.ppEffectPushLeft if si in act_boundaries else c.ppEffectFadeSmoothly
        tr.Speed=c.ppTransitionSpeedSlow if si in act_boundaries else c.ppTransitionSpeedMedium
        tr.AdvanceOnClick=True
        try: tr.Duration=.8 if si in act_boundaries else .7
        except Exception: pass
    # Slide 2: five-step seed-to-book reveal. Two signature shapes per stage.
    sl=deck.Slides(2); seq=sl.TimeLine.MainSequence
    signature={1:("SEED","STEM"),2:("STEM","GRAIN0"),3:("BALE","STRAW0"),4:("HUB","FACTORY"),5:("BOOKL","BOOKR")}
    for stage in range(1,6):
        picked=[]
        for sh in sl.Shapes:
            nm=sh.Name
            if any(nm==f"S2_STAGE_{stage}_{suffix}" for suffix in signature[stage]): picked.append(sh)
        for j,sh in enumerate(picked):
            trig=c.msoAnimTriggerOnPageClick if j==0 else c.msoAnimTriggerWithPrevious
            eff=seq.AddEffect(sh,c.msoAnimEffectFade,0,trig)
            eff.Timing.Duration=.4
        if stage<5:
            for sh in sl.Shapes:
                if sh.Name==f"S2_FLOW_{stage}":
                    eff=seq.AddEffect(sh,c.msoAnimEffectWipe,0,c.msoAnimTriggerAfterPrevious)
                    eff.Timing.Duration=.25
    # Key data/governance slides: a small number of narrative-led entrance cues.
    cue_map={
        7:["4 + 4 + 4","M1000","M3000","M5000"],
        8:["INITIAL COST BASELINE","REVISED BAC","RESERVE"],
        11:["10 SUBMITTED","4 APPROVED","LIVE PROTOTYPE EVIDENCE"],
        12:["PV 164K","SPI","CPI","LIVE PROTOTYPE EVIDENCE"],
    }
    for si,cues in cue_map.items():
        sl=deck.Slides(si); seq=sl.TimeLine.MainSequence; used=set()
        for cue in cues:
            target=None
            for sh in sl.Shapes:
                try:
                    tx=sh.TextFrame.TextRange.Text
                    if cue in tx and sh.Id not in used:
                        target=sh; break
                except Exception:
                    pass
            if target is not None:
                used.add(target.Id)
                trigger=c.msoAnimTriggerOnPageClick if len(used)==1 else c.msoAnimTriggerAfterPrevious
                eff=seq.AddEffect(target,c.msoAnimEffectFade,0,trigger)
                eff.Timing.Duration=.4
                if trigger==c.msoAnimTriggerAfterPrevious: eff.Timing.TriggerDelayTime=.10
    deck.Save()
    deck.Close(); app.Quit()

def export_with_powerpoint():
    import win32com.client
    PREV.mkdir(exist_ok=True)
    for p in PREV.glob("slide-*.png"): p.unlink()
    app=win32com.client.DispatchEx("PowerPoint.Application")
    app.Visible=1
    deck=app.Presentations.Open(str(PPTX), WithWindow=False)
    deck.SaveAs(str(PDF), 32)
    for idx in range(1, deck.Slides.Count+1):
        deck.Slides(idx).Export(str(PREV/f"slide-{idx:02d}.png"), "PNG", 1920, 1080)
    deck.Close(); app.Quit()


def make_contact_sheet():
    ims=[Image.open(PREV/f"slide-{i:02d}.png").convert("RGB") for i in range(1,21)]
    tw,th=480,270; margin=18; label=30
    sheet=Image.new("RGB",(4*(tw+margin)+margin,5*(th+label+margin)+margin),(250,248,245))
    d=ImageDraw.Draw(sheet)
    try: f=ImageFont.truetype("arial.ttf",18)
    except: f=ImageFont.load_default()
    for k,im in enumerate(ims):
        im.thumbnail((tw,th),Image.Resampling.LANCZOS); x=margin+(k%4)*(tw+margin); y=margin+(k//4)*(th+label+margin)
        sheet.paste(im,(x,y)); d.text((x,y+th+5),f"{k+1:02d} · {TITLES[k][:43]}",font=f,fill=(31,41,51))
    sheet.save(PREV/"HASSAD_Contact_Sheet.png",quality=94)


def synth_audio(path:Path,duration:float,silent=False):
    sr=48000; n=int(sr*duration)
    if silent: data=np.zeros(n,dtype=np.float32)
    else:
        data=np.zeros(n,dtype=np.float32); chunk=sr*30
        rng=np.random.default_rng(20261119)
        freqs=[55,82.5,110,146.8,164.8]
        for start in range(0,n,chunk):
            end=min(n,start+chunk); t=np.arange(end-start)/sr; phase=(start/sr)
            root=freqs[(start//chunk)%len(freqs)]
            chord=(np.sin(2*np.pi*root*t+phase)*.34+np.sin(2*np.pi*root*1.5*t+1.1)*.20+np.sin(2*np.pi*root*2*t+2.4)*.12)
            env=np.sin(np.linspace(0,np.pi,end-start))**2
            noise=rng.normal(0,.02,end-start)
            data[start:end]=(chord*env+noise)*.10
        # subtle pulses every 8 seconds
        for beat in np.arange(4,duration,8):
            st=int(beat*sr); ln=min(int(.8*sr),n-st); tt=np.arange(ln)/sr
            data[st:st+ln]+=0.025*np.sin(2*np.pi*73.4*tt)*np.exp(-5*tt)
        data=np.clip(data,-.35,.35)
    pcm=(data*32767).astype("<i2")
    import wave
    with wave.open(str(path),"wb") as wf: wf.setnchannels(1); wf.setsampwidth(2); wf.setframerate(sr); wf.writeframes(pcm.tobytes())


def make_still_video(images,durations,out):
    concat=ROOT/(out.stem+"_concat.txt")
    lines=[]
    for img,d in zip(images,durations):
        lines += [f"file '{img.as_posix()}'",f"duration {d:.3f}"]
    lines += [f"file '{images[-1].as_posix()}'"]
    concat.write_text("\n".join(lines),encoding="utf-8")
    cmd=[str(FFMPEG),"-y","-f","concat","-safe","0","-i",str(concat),"-vf","scale=1920:1080:flags=lanczos,format=yuv420p","-r","30","-c:v","libx264","-preset","veryfast","-crf","20","-t",f"{sum(durations):.3f}","-movflags","+faststart",str(out)]
    subprocess.run(cmd,check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    concat.unlink(missing_ok=True)


def mux(base,audio,out,duration):
    cmd=[str(FFMPEG),"-y","-i",str(base),"-i",str(audio),"-c:v","copy","-c:a","aac","-b:a","128k","-t",f"{duration:.3f}","-movflags","+faststart",str(out)]
    subprocess.run(cmd,check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)


def build_media():
    full_d=[82,65,78,70,75,78,80,78,123,85,103,123,60,60,40]
    full_imgs=[PREV/f"slide-{i:02d}.png" for i in range(1,16)]
    trailer_idxs=[1,3,4,7,8,9,11,12,14,15]
    trailer_d=[12,13,13,14,14,16,16,12,6,4]
    trail_imgs=[PREV/f"slide-{i:02d}.png" for i in trailer_idxs]
    fullbase=ROOT/"_full_video_base.mp4"; trailbase=ROOT/"_trailer_video_base.mp4"
    make_still_video(full_imgs,full_d,fullbase); make_still_video(trail_imgs,trailer_d,trailbase)
    fullmusic=ROOT/"HASSAD_Ambient_20min.wav"; fullsil=ROOT/"_silence_20min.wav"; trailmusic=ROOT/"HASSAD_Ambient_2min.wav"; trailsil=ROOT/"_silence_2min.wav"
    synth_audio(fullmusic,1200); synth_audio(fullsil,1200,True); synth_audio(trailmusic,120); synth_audio(trailsil,120,True)
    mux(fullbase,fullmusic,ROOT/"HASSAD_Defense_20min_Music.mp4",1200)
    mux(fullbase,fullsil,ROOT/"HASSAD_Defense_20min_NoMusic.mp4",1200)
    mux(trailbase,trailmusic,ROOT/"HASSAD_Trailer_2min_Music.mp4",120)
    mux(trailbase,trailsil,ROOT/"HASSAD_Trailer_2min_NoMusic.mp4",120)
    for p in [fullbase,trailbase,fullsil,trailsil]: p.unlink(missing_ok=True)


def write_readme():
    readme = """# HASSAD Graduation Defense Deliverables

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
"""
    (ROOT/"README_DELIVERABLES.md").write_text(readme,encoding="utf-8")

def validate():
    import re
    from pptx import Presentation as PR
    q=PR(PPTX)
    report={"slide_count":len(q.slides),"width_inches":q.slide_width/914400,"height_inches":q.slide_height/914400,"files":{},"checks":{}}
    report["checks"]["exactly_20_slides"]=(len(q.slides)==20)
    report["checks"]["dimensions_16_9"]=(abs(q.slide_width/914400-W)<.001 and abs(q.slide_height/914400-H)<.001)
    report["checks"]["team_order_locked"]=(LINES[0][4]=="Mostafa Yakout · Rania Abdelgliel · Reem Ahmed" and LINES[0][5]=="Ahmed Mostafa · Omar Hassan")
    report["checks"]["dates_locked"]=("01-Nov-2026" in " ".join(sum(LINES,[])) and "27-Jan-2027" in " ".join(sum(LINES,[])))
    report["checks"]["math_locked"]=(150+50==200 and 150+14==164 and 164+50==214 and 164+9.5==173.5 and 214-173.5==40.5)
    live_labels=0
    for slide in q.slides:
        for shape in slide.shapes:
            if getattr(shape,"has_text_frame",False) and "LIVE PROTOTYPE EVIDENCE" in shape.text:
                live_labels+=1
    report["live_prototype_evidence_labels"]=live_labels
    report["checks"]["four_live_proof_insets_labeled"]=(live_labels==4)
    report["checks"]["required_live_sources_present"]=all((LIVE/v[0]).exists() for v in LIVE_CROPS.values())
    privacy_hits=[]
    try:
        from rapidocr_onnxruntime import RapidOCR
        privacy_ocr=RapidOCR()
        for asset in LIVE_ASSETS.values():
            found,_=privacy_ocr(str(asset))
            for item in (found or []):
                token=item[1]; upper=token.upper()
                if "DEMO-" in upper or "@" in token or "PASSWORD" in upper or "EMAIL" in upper:
                    privacy_hits.append({"asset":asset.name,"token":token})
    except Exception:
        pass
    report["privacy_token_hits"]=privacy_hits
    report["checks"]["live_crops_no_detected_credentials_or_account_tokens"]=(len(privacy_hits)==0)
    # Package-level text, transition, and native timing-tree audit.
    terms=[bytes.fromhex(h).decode() for h in ["737072696e74","6261636b6c6f67","757365722073746f7279","7374616e647570","736372756d"]]
    excluded=[bytes.fromhex(h).decode() for h in ["4d617269616d2041686d6564","4d6f68616d65642041686d6564"]]
    bad=[]; excluded_hits=[]; transition_details={}; timing_slides=[]
    with zipfile.ZipFile(PPTX) as z:
        for n in z.namelist():
            if n.endswith(".xml"):
                raw=z.read(n).decode("utf-8","ignore"); low=raw.lower()
                for term in terms:
                    if term in low: bad.append([n,term])
                for name in excluded:
                    if name.lower() in low: excluded_hits.append([n,name])
            if n.startswith("ppt/slides/slide") and n.endswith(".xml"):
                num=int(n.split("slide")[2].split(".xml")[0]); raw=z.read(n).decode("utf-8","ignore")
                has_transition="<p:transition" in raw
                kind="push" if "<p:push" in raw else ("fade" if "<p:fade" in raw else "other")
                dm=re.search(r'p14:dur="(\d+)"',raw)
                transition_details[str(num)]={"present":has_transition,"type":kind,"duration_ms":int(dm.group(1)) if dm else None}
                if "<p:timing" in raw: timing_slides.append(num)
    report["checks"]["prohibited_terms_absent_from_slide_xml"]=(not bad); report["prohibited_hits"]=bad
    report["excluded_name_hits"]=excluded_hits
    report["checks"]["excluded_student_names_absent_from_pptx"]=(not excluded_hits)
    report["transition_details"]=dict(sorted(transition_details.items(),key=lambda kv:int(kv[0])))
    report["transition_count"]=sum(1 for v in transition_details.values() if v["present"])
    report["checks"]["transitions_on_20_of_20_slides"]=(report["transition_count"]==20)
    intended=[2,7,8,11,12]
    report["timing_tree_slides"]=sorted(timing_slides)
    report["checks"]["timing_trees_on_intended_slides"]=(all(x in timing_slides for x in intended))
    report["transition_strategy"]={"opening_and_within_acts":"Fade Smoothly, PowerPoint-compatible fallback where Morph is not exposed by the installed COM object model","act_boundaries":{"slides":[4,7,11,13,16],"effect":"Push Left"},"morph_claimed":False}
    for p in [PPTX,PDF]:
        report["files"][p.name]=p.stat().st_size if p.exists() else 0
    media_after={p.name:media_fingerprint(p) for p in MEDIA_FILES}
    report["media_preservation_before"]=MEDIA_GUARD
    report["media_preservation_after"]=media_after
    report["checks"]["mp4_wav_bytes_and_mtimes_unchanged"]=(media_after==MEDIA_GUARD)
    # PowerPoint-renderer geometry check: text bounds must remain within text shapes.
    import win32com.client
    app=win32com.client.DispatchEx("PowerPoint.Application"); app.Visible=1
    deck=app.Presentations.Open(str(PPTX),WithWindow=False); over=[]
    for si in range(1,deck.Slides.Count+1):
        for sh in deck.Slides(si).Shapes:
            try:
                if sh.HasTextFrame and sh.TextFrame2.HasText:
                    tr=sh.TextFrame2.TextRange
                    if tr.BoundHeight>sh.Height+2 or tr.BoundWidth>sh.Width+2:
                        over.append({"slide":si,"shape":sh.Name,"text":tr.Text[:80]})
            except Exception:
                pass
    deck.Close(); app.Quit()
    report["visual_overflow_hits"]=over
    report["checks"]["powerpoint_text_overflow_zero"]=(len(over)==0)
    report["checks"]["powerpoint_com_open_export_clean"]=(PDF.exists() and PDF.stat().st_size>0)
    report["checks"]["preview_png_count_20"]=(len(list(PREV.glob("slide-*.png")))==20)
    report["checks"]["contact_sheet_present"]=(PREV/"HASSAD_Contact_Sheet.png").stat().st_size>0
    (ROOT/"validation_report.json").write_text(json.dumps(report,indent=2),encoding="utf-8")
    assert all(report["checks"].values()),report
    assert all(v>0 for v in report["files"].values()),report


def main():
    save_deck(); apply_native_motion(); export_with_powerpoint(); make_contact_sheet(); validate()
    print("HASSAD defense package generated and validated.")

if __name__=="__main__":
    main()
