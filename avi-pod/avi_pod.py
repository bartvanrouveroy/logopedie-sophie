#!/usr/bin/env python3
"""
avi_pod.py — zet AVI-boekjes (of andere bundels) om naar drukklare A5-bestanden
voor print-on-demand (Lulu, Peecho) of een gewone drukker.

Gebruik:
    python3 avi_pod.py <pdf-of-map> [-o uitvoermap] [--modus lulu|zelfomslag]
                       [--formaat 148x210] [--afloop 3.175]

Per boekje maakt het:
  lulu        <naam>_binnenwerk.pdf   binnenpagina's, aantal deelbaar door 4
              <naam>_cover.pdf        omslag in één stuk: achterkant + voorkant (geniet, geen rug)
  zelfomslag  <naam>_drukklaar.pdf    voorkant, binnenwerk, achterkant in één bestand (Peecho, drukker)
En voor de hele reeks: overzicht.csv met de aantallen per boekje.

Werkwijze:
  - Pagina 1 van het origineel wordt de voorkant, de rest het binnenwerk.
  - Elke pagina wordt vectorieel geschaald (tekst blijft scherp) tot ze het
    volledige formaat plus afloop vult. A4 en A5 hebben dezelfde verhouding,
    dus er gaat enkel ±3 mm rand verloren: houd tekst minstens 10 mm van de rand.
  - Ontbreken er pagina's om tot een viervoud te komen, dan komen er
    "Notities"-pagina's bij vóór de laatste pagina.
  - Een achterkant wordt automatisch gemaakt met titel, niveau en praktijkgegevens.
  - MediaBox = met afloop, TrimBox = eindformaat, BleedBox = met afloop.
"""
import argparse, csv, io, re, sys
from pathlib import Path

from pypdf import PdfReader, PdfWriter, PageObject, Transformation
from pypdf.generic import RectangleObject
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor

MM = 72 / 25.4

PRAKTIJK = {
    'naam': 'Logopedie Sophie',
    'regel1': 'Sophie Houben · erkend logopediste',
    'regel2': 'Paters Adonsstraat 25 · 3500 Hasselt',
    'web': 'www.logopediesophie.be',
}
KLEUR = {'ink': HexColor('#333333'), 'blauw': HexColor('#4E7392'), 'zand': HexColor('#EAD6A8'),
         'bruin': HexColor('#8C6C3F'), 'lijn': HexColor('#CDC4B3'), 'licht': HexColor('#F6F2EA')}


def boxes(page, w, h, b):
    page.mediabox = RectangleObject([0, 0, w + 2 * b, h + 2 * b])
    page.bleedbox = RectangleObject([0, 0, w + 2 * b, h + 2 * b])
    page.trimbox = RectangleObject([b, b, b + w, b + h])
    page.cropbox = RectangleObject([0, 0, w + 2 * b, h + 2 * b])


def fit_page(src, w, h, b):
    """Schaal een bronpagina zodat ze formaat + afloop volledig vult, gecentreerd."""
    sw, sh = float(src.mediabox.width), float(src.mediabox.height)
    ox, oy = float(src.mediabox.left), float(src.mediabox.bottom)
    fw, fh = w + 2 * b, h + 2 * b
    s = max(fw / sw, fh / sh)
    tx = (fw - sw * s) / 2 - ox * s
    ty = (fh - sh * s) / 2 - oy * s
    out = PageObject.create_blank_page(width=fw, height=fh)
    out.merge_transformed_page(src, Transformation().scale(s, s).translate(tx, ty))
    boxes(out, w, h, b)
    return out, s


def rl_page(w, h, b, draw):
    """Maak één pagina met ReportLab op formaat + afloop."""
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=(w + 2 * b, h + 2 * b))
    draw(c, w, h, b)
    c.showPage(); c.save()
    p = PdfReader(io.BytesIO(buf.getvalue())).pages[0]
    boxes(p, w, h, b)
    return p


def draw_notes(c, w, h, b):
    x0, x1 = b + 14 * MM, b + w - 14 * MM
    c.setFillColor(KLEUR['bruin']); c.setFont('Helvetica-Bold', 18)
    c.drawString(x0, b + h - 24 * MM, 'Notities')
    c.setStrokeColor(KLEUR['lijn']); c.setLineWidth(0.6)
    y = b + h - 38 * MM
    while y > b + 20 * MM:
        c.line(x0, y, x1, y); y -= 10 * MM


def make_back(titel, niveau):
    def draw(c, w, h, b):
        fw, fh = w + 2 * b, h + 2 * b
        c.setFillColor(KLEUR['licht']); c.rect(0, 0, fw, fh, fill=1, stroke=0)
        c.setFillColor(KLEUR['zand']); c.rect(0, fh - b - 30 * MM, fw, 30 * MM + b, fill=1, stroke=0)
        c.setFillColor(KLEUR['ink']); c.setFont('Helvetica-Bold', 16)
        c.drawCentredString(fw / 2, fh - b - 18 * MM, titel)
        if niveau:
            c.setFont('Helvetica', 12); c.drawCentredString(fw / 2, fh - b - 25 * MM, niveau)
        c.setFillColor(KLEUR['ink']); c.setFont('Helvetica', 10.5)
        tekst = ['Een leesboekje uit de praktijk van Logopedie Sophie.',
                 'Korte zinnen en woorden op het juiste AVI-niveau,',
                 'om thuis samen verder te oefenen.']
        y = fh / 2 + 10 * MM
        for t in tekst:
            c.drawCentredString(fw / 2, y, t); y -= 6 * MM
        c.setFillColor(KLEUR['blauw']); c.setFont('Helvetica-Bold', 13)
        c.drawString(b + 12 * MM, b + 30 * MM, PRAKTIJK['naam'])
        c.setFillColor(KLEUR['ink']); c.setFont('Helvetica', 9)
        c.drawString(b + 12 * MM, b + 24 * MM, PRAKTIJK['regel1'])
        c.drawString(b + 12 * MM, b + 19 * MM, PRAKTIJK['regel2'])
        c.drawString(b + 12 * MM, b + 14 * MM, PRAKTIJK['web'])
        # vrije zone voor een eventuele streepjescode van de drukker
        c.setStrokeColor(KLEUR['lijn']); c.setDash(2, 2)
        c.rect(b + w - 12 * MM - 50 * MM, b + 10 * MM, 50 * MM, 28 * MM, fill=0, stroke=1)
        c.setDash()
    return draw


def guess_meta(src_path, reader):
    stem = src_path.stem
    meta_t = reader.metadata.title if reader.metadata and reader.metadata.title else None
    if meta_t and meta_t.strip().lower() in ('untitled', 'anonymous', ''):
        meta_t = None
    titel = meta_t or re.sub(r'[_\-]+', ' ', stem).strip()
    m = re.search(r'AVI[\s_\-]*(Start|Plus|[ME]\d)', stem + ' ' + titel, re.I)
    niveau = f'AVI {m.group(1).upper() if m.group(1)[0] in "meME" else m.group(1).capitalize()}' if m else ''
    nr = re.search(r'boekje[\s_\-]*(\d+)', titel, re.I)
    if nr:
        niveau = (niveau + ' · ' if niveau else '') + f'boekje {nr.group(1)}'
    kort = re.sub(r'^\s*AVI\s*(Start|Plus|[ME]\d)\s*', '', titel, flags=re.I)
    kort = re.sub(r'^\s*boekje\s*\d+\s*', '', kort, flags=re.I).strip()
    return (kort or titel), niveau


def convert(src_path, outdir, modus, w, h, b):
    reader = PdfReader(str(src_path))
    pages = list(reader.pages)
    if len(pages) < 2:
        raise ValueError('minstens 2 pagina\'s nodig (voorkant + binnenwerk)')
    titel, niveau = guess_meta(src_path, reader)
    front, scale = fit_page(pages[0], w, h, b)
    inner = [fit_page(p, w, h, b)[0] for p in pages[1:]]
    back = rl_page(w, h, b, make_back(titel, niveau))

    vast = 2 if modus == 'zelfomslag' else 0
    tekort = (-(len(inner) + vast)) % 4
    if tekort:
        notes = [rl_page(w, h, b, draw_notes) for _ in range(tekort)]
        inner = inner[:-1] + notes + inner[-1:] if len(inner) > 1 else inner + notes

    stem = src_path.stem
    files = []
    if modus == 'zelfomslag':
        wr = PdfWriter()
        for p in [front] + inner + [back]:
            wr.add_page(p)
        f = outdir / f'{stem}_drukklaar.pdf'; wr.write(str(f)); files.append(f.name)
        totaal = len(inner) + 2
    else:
        wr = PdfWriter()
        for p in inner:
            wr.add_page(p)
        f = outdir / f'{stem}_binnenwerk.pdf'; wr.write(str(f)); files.append(f.name)
        # omslag: achterkant links, voorkant rechts, geen rug bij nietjes
        cw, ch = 2 * w + 2 * b, h + 2 * b
        spread = PageObject.create_blank_page(width=cw, height=ch)
        spread.merge_transformed_page(back, Transformation().translate(0, 0))
        spread.merge_transformed_page(front, Transformation().translate(w, 0))
        spread.mediabox = RectangleObject([0, 0, cw, ch])
        spread.bleedbox = RectangleObject([0, 0, cw, ch])
        spread.trimbox = RectangleObject([b, b, cw - b, ch - b])
        wc = PdfWriter(); wc.add_page(spread)
        f2 = outdir / f'{stem}_cover.pdf'; wc.write(str(f2)); files.append(f2.name)
        totaal = len(inner)
    return {'origineel': src_path.name, 'titel': titel, 'niveau': niveau,
            'paginas_origineel': len(pages), 'paginas_drukklaar': totaal,
            'notitiepaginas': tekort, 'schaal': round(scale, 3), 'bestanden': ' + '.join(files)}


def main():
    ap = argparse.ArgumentParser(description='AVI-boekjes drukklaar maken voor print-on-demand')
    ap.add_argument('bron', help='een PDF of een map met PDF\'s')
    ap.add_argument('-o', '--uit', default='drukklaar')
    ap.add_argument('--modus', choices=['lulu', 'zelfomslag'], default='lulu')
    ap.add_argument('--formaat', default='148x210', help='eindformaat in mm, b x h (standaard A5)')
    ap.add_argument('--afloop', type=float, default=3.175, help='afloop in mm (standaard 3,175 = 1/8 inch)')
    a = ap.parse_args()

    w, h = (float(v) * MM for v in a.formaat.lower().split('x'))
    b = a.afloop * MM
    src = Path(a.bron)
    files = sorted(src.glob('*.pdf')) if src.is_dir() else [src]
    out = Path(a.uit); out.mkdir(parents=True, exist_ok=True)
    rows, fouten = [], 0
    for f in files:
        try:
            r = convert(f, out, a.modus, w, h, b); rows.append(r)
            print(f"✓ {f.name}: {r['paginas_origineel']} → {r['paginas_drukklaar']} pagina's"
                  + (f" (+{r['notitiepaginas']} notities)" if r['notitiepaginas'] else ''))
        except Exception as e:
            fouten += 1; print(f'✗ {f.name}: {e}', file=sys.stderr)
    if rows:
        with open(out / 'overzicht.csv', 'w', newline='', encoding='utf-8') as fh:
            wcsv = csv.DictWriter(fh, fieldnames=list(rows[0].keys()), delimiter=';'); wcsv.writeheader(); wcsv.writerows(rows)
    print(f'\nKlaar: {len(rows)} boekje(s) in {out}/' + (f', {fouten} fout(en)' if fouten else ''))
    if rows:
        s = min(r['schaal'] for r in rows)
        print(f'Schaal {s:.3f}: tekst van 20 pt op A4 wordt {20*s:.1f} pt op A5.')


if __name__ == '__main__':
    main()
