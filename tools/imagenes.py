"""
riveraurbano.com — Genera el croquis y las imágenes del sitio a partir de una sola escena.
Coordenadas tomadas de la captura de Google Maps (norte arriba).

  python3 tools/imagenes.py assets/img     -> poster-*.jpg, og-image-*.jpg, vista-aerea-*.svg
  python3 tools/imagenes.py --croquis      -> imprime el SVG del croquis para index.php
"""
import random, subprocess, sys, os

A = "#1F2B33"; Y = "#F5B400"; SAGE = "#6F8466"; ROAD = "#3A454C"; WALK = "#D8D4C8"; LOT = "#CDBB90"
TIERRA = "#C7B98F"; EDIF = "#E4E2DC"; EDIF_B = "#BDB8AD"; GRIS = "#5E5B54"; PATIO = "#B9B4A6"
F = "Archivo,Arial,Helvetica,sans-serif"

TXT = {
    "es": {"a": "Lote A", "b": "Lote B", "t1": "Terrenos", "t2": "en renta en", "t3": "Calle Novena",
           "sub": "Mexicali, B.C. · a pasos de Calzada Cetys"},
    "en": {"a": "Lot A", "b": "Lot B", "t1": "Land", "t2": "for lease on", "t3": "Calle Novena",
           "sub": "Mexicali, B.C. · steps from Calzada Cetys"},
}

# Eje de Calle Novena (x, y); al norte de Cetys se llama Calz. Abelardo L. Rodríguez
NOVENA = [(447, -60), (440, 100), (425, 240), (395, 395), (385, 460), (323, 670), (300, 760)]
ANCHO_NOVENA = 66
CETYS_Y1, CETYS_Y2 = 212, 264

# Terrenos (siguen la banqueta de la Novena)
LOTE_A = [(272, 460), (352, 460), (332, 530), (312, 600), (292, 668), (270, 668)]             # junto a CEDIS Coppel
LOTE_B = [(428, 395), (458, 395), (455, 678), (354, 678), (376, 600), (396, 530), (417, 460)]  # junto a bp

def pts(p): return " ".join(f"{x},{y}" for x, y in p)
def centro(p): return (sum(x for x, _ in p) / len(p), sum(y for _, y in p) / len(p))

def camino(p):
    d = f"M{p[0][0]},{p[0][1]}"
    for i in range(len(p) - 1):
        p0 = p[i - 1] if i else p[i]; p1, p2 = p[i], p[i + 1]; p3 = p[i + 2] if i + 2 < len(p) else p2
        c1 = (p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6)
        d += f" C{c1[0]:.1f},{c1[1]:.1f} {c2[0]:.1f},{c2[1]:.1f} {p2[0]},{p2[1]}"
    return d

def etiqueta_calle(x, y, texto, rot=0, tam=13):
    w = len(texto) * tam * 0.58 + 16
    return (f'<g transform="translate({x},{y}) rotate({rot})" pointer-events="none"><rect x="{-w/2:.0f}" y="{-tam*0.95:.0f}" '
            f'width="{w:.0f}" height="{tam*1.9:.0f}" rx="5" fill="#fff"/><text y="{tam*0.36:.1f}" text-anchor="middle" '
            f'font-family="{F}" font-weight="700" font-size="{tam}" fill="{A}">{texto}</text></g>')

def escena(nombre_a, nombre_b, attrs_a="", attrs_b="", detalle=True):
    random.seed(11)
    s = [f'<defs><clipPath id="sinCruce"><rect x="-400" y="{CETYS_Y2+8}" width="1600" height="900"/>'
         f'<rect x="-400" y="-200" width="1600" height="{CETYS_Y1-8+200}"/></clipPath></defs>',
         f'<rect x="-400" y="-200" width="1600" height="1100" fill="{TIERRA}"/>']
    if detalle:
        for _ in range(1400):
            x, y = random.uniform(-400, 1200), random.uniform(-200, 900)
            s.append(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{random.uniform(.6, 2.2):.1f}" fill="#B9AA7E" opacity=".55"/>')
    s.append('<rect x="640" y="40" width="80" height="100" fill="#B5A873"/>')
    s.append('<rect x="-400" y="690" width="1600" height="40" fill="#A9A06E"/>')
    # Edificios de referencia
    s.append(f'<rect x="300" y="140" width="70" height="52" fill="#C98C6E" stroke="{EDIF_B}" stroke-width="1.5"/>')
    s.append(f'<rect x="130" y="285" width="136" height="86" fill="{PATIO}"/>')
    for i in range(10): s.append(f'<line x1="{140+i*12}" y1="300" x2="{140+i*12}" y2="318" stroke="#fff" stroke-width="1.2"/>')
    s.append(f'<rect x="133" y="375" width="135" height="240" fill="{EDIF}" stroke="{EDIF_B}" stroke-width="2"/>')
    s.append(f'<rect x="270" y="280" width="95" height="172" fill="{PATIO}"/>')
    s.append(f'<rect x="298" y="296" width="46" height="96" fill="#C0694F" stroke="{EDIF_B}" stroke-width="1.5"/>')
    s.append(f'<rect x="440" y="272" width="125" height="118" fill="{PATIO}"/>')
    s.append(f'<rect x="466" y="286" width="58" height="42" fill="#F4F4F0" stroke="{EDIF_B}" stroke-width="1.5"/>')
    s.append(f'<rect x="508" y="336" width="54" height="50" fill="{EDIF}" stroke="{EDIF_B}" stroke-width="1.5"/>')
    s.append('<rect x="508" y="336" width="54" height="5" fill="#2E7D4F"/><rect x="508" y="341" width="54" height="3" fill="#E8792B"/>')
    s.append(f'<rect x="680" y="300" width="80" height="290" rx="10" fill="#F1F1EE" stroke="{EDIF_B}" stroke-width="1.5"/>')
    for x, y in [(60,60),(90,120),(150,70),(210,110),(250,40),(530,60),(580,120),(600,30),(700,180),(60,300),(80,420),
                 (70,560),(40,640),(620,430),(580,520),(660,620),(520,460),(600,650),(250,160),(170,190),(560,190)]:
        s.append(f'<circle cx="{x}" cy="{y}" r="{random.uniform(7,12):.0f}" fill="{SAGE}" opacity=".85"/>')
    # Calles
    d = camino(NOVENA)
    s.append(f'<rect x="-400" y="{CETYS_Y1-5}" width="1600" height="{CETYS_Y2-CETYS_Y1+10}" fill="{WALK}"/>')
    s.append(f'<path d="{d}" fill="none" stroke="{WALK}" stroke-width="{ANCHO_NOVENA+10}"/>')
    s.append(f'<path d="{d}" fill="none" stroke="{ROAD}" stroke-width="{ANCHO_NOVENA}"/>')
    s.append(f'<rect x="-400" y="{CETYS_Y1}" width="1600" height="{CETYS_Y2-CETYS_Y1}" fill="{ROAD}"/>')
    s.append(f'<line x1="-400" y1="238" x2="385" y2="238" stroke="{Y}" stroke-width="3"/>')
    s.append(f'<line x1="470" y1="238" x2="1200" y2="238" stroke="{Y}" stroke-width="3"/>')
    for yy in (225, 251):
        for x1, x2 in ((-400, 380), (475, 1200)):
            s.append(f'<line x1="{x1}" y1="{yy}" x2="{x2}" y2="{yy}" stroke="#fff" stroke-width="1.5" stroke-dasharray="12 10" opacity=".7"/>')
    s.append(f'<path d="{d}" fill="none" stroke="{Y}" stroke-width="3" clip-path="url(#sinCruce)"/>')
    cols = ["#FFFFFF", "#A63A3A", "#2E5E8C", "#222", "#C9C9C9"]
    for i, x in enumerate([40, 160, 560, 640, 760, -60, 290]):
        s.append(f'<rect x="{x}" y="{216 if i%2 else 243}" width="18" height="9" rx="2" fill="{cols[i%5]}"/>')
    for i, (x, y, r) in enumerate([(370, 480, -16), (402, 500, -16), (410, 345, -12), (338, 660, -16)]):
        s.append(f'<rect x="{x-4}" y="{y-9}" width="9" height="18" rx="2" fill="{cols[(i+2)%5]}" transform="rotate({r} {x} {y})"/>')
    # Terrenos
    for poli, attrs in ((LOTE_A, attrs_a), (LOTE_B, attrs_b)):
        s.append(f'<polygon points="{pts(poli)}" fill="{LOT}" stroke="{Y}" stroke-width="3.5" stroke-dasharray="10 6" stroke-linejoin="round" {attrs}/>')
    s.append('<rect x="420" y="455" width="28" height="44" fill="#A8A397" opacity=".8" pointer-events="none"/>')
    lugar = lambda x, y, t, tam=12: (f'<text x="{x}" y="{y}" text-anchor="middle" font-family="{F}" font-weight="700" '
                                     f'font-size="{tam}" fill="{GRIS}" pointer-events="none">{t}</text>')
    s.append(lugar(200, 500, "CEDIS Coppel", 15))
    s.append(lugar(318, 440, "Car Wash", 11))
    s.append(lugar(495, 312, "bp", 13))
    s.append(lugar(535, 369, "7-Eleven", 9))
    s.append(lugar(335, 206, "Farmacias Roma", 10))
    s.append(etiqueta_calle(215, 238, "Calzada Cetys"))
    s.append(etiqueta_calle(585, 238, "Carr. Aeropuerto"))
    s.append(etiqueta_calle(342, 585, "Calle Novena", -73, 11))
    s.append(etiqueta_calle(454, 110, "Calz. Abelardo L. Rodríguez", -86, 11))
    for poli, nombre, dx, dy in ((LOTE_A, nombre_a, 4, -8), (LOTE_B, nombre_b, 18, 40)):
        cx, cy = centro(poli); cx += dx; cy += dy
        w = 72
        s.append(f'<g pointer-events="none"><rect x="{cx-w/2:.0f}" y="{cy-14:.0f}" width="{w}" height="28" rx="14" fill="{A}"/>'
                 f'<text x="{cx:.0f}" y="{cy+5:.0f}" text-anchor="middle" font-family="{F}" font-weight="800" font-size="15" fill="{Y}">{nombre}</text></g>')
    s.append(f'<g transform="translate(600,{CETYS_Y2+46})" pointer-events="none"><circle r="15" fill="#fff" opacity=".9"/>'
             f'<path d="M0,-10 L6,6 L0,2 L-6,6Z" fill="{A}"/><text y="-19" text-anchor="middle" font-family="{F}" '
             f'font-weight="800" font-size="12" fill="{A}">N</text></g>')
    return "\n".join(s)

VB_POSTER = "-30 196 880 495"      # 16:9
VB_CROQUIS = "120 185 540 520"

def svg(vb, w, h, contenido):
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" width="{w}" height="{h}">{contenido}</svg>'

def og(L):
    T = TXT[L]
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
<rect width="1200" height="630" fill="{A}"/>
<svg x="600" y="0" width="600" height="630" viewBox="220 200 360 378" preserveAspectRatio="xMidYMid slice">{escena(T["a"], T["b"])}</svg>
<rect x="70" y="80" width="64" height="64" fill="none" stroke="{Y}" stroke-width="6" stroke-dasharray="14 8"/><circle cx="134" cy="80" r="11" fill="{Y}"/>
<text x="160" y="130" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="44" fill="#fff">Rivera Urbano</text>
<text font-family="Arial,Helvetica,sans-serif" font-weight="800" font-size="64" fill="#fff"><tspan x="70" y="280">{T["t1"]}</tspan><tspan x="70" y="355">{T["t2"]}</tspan><tspan x="70" y="430" fill="{Y}">{T["t3"]}</tspan></text>
<text x="70" y="510" font-family="Arial,Helvetica,sans-serif" font-size="26" fill="#C9D1D6">{T["sub"]}</text>
<text x="70" y="565" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="30" fill="{Y}">riveraurbano.com</text></svg>'''

def croquis_php():
    at = lambda l: (f'class="lote{" activo" if l == "a" else ""}" data-lote="{l}" tabindex="0" role="button" '
                    f'aria-label="<?= e(\'croquis.ver_lote\', [\'lote\' => t(\'lote.{l}.nombre\')]) ?>"')
    cont = escena("<?= e('lote.a.nombre') ?>", "<?= e('lote.b.nombre') ?>", at("a"), at("b"), detalle=False)
    return (f'<svg viewBox="{VB_CROQUIS}" role="img" aria-labelledby="croquisTitulo">\n'
            f'<title id="croquisTitulo"><?= e(\'croquis.titulo\') ?></title>\n{cont}\n</svg>')

if __name__ == "__main__":
    if sys.argv[1:] == ["--croquis"]:
        print(croquis_php()); sys.exit()
    import cairosvg
    OUT = sys.argv[1]; os.makedirs(OUT, exist_ok=True)
    for L in ("es", "en"):
        T = TXT[L]
        aerea = svg(VB_POSTER, 1600, 900, escena(T["a"], T["b"]))
        open(f"{OUT}/vista-aerea-{L}.svg", "w").write(aerea)
        cairosvg.svg2png(bytestring=aerea.encode(), write_to="/tmp/p.png", output_width=1600)
        subprocess.run(["convert", "/tmp/p.png", "-quality", "82", f"{OUT}/poster-{L}.jpg"], check=True)
        cairosvg.svg2png(bytestring=og(L).encode(), write_to="/tmp/o.png", output_width=1200)
        subprocess.run(["convert", "/tmp/o.png", "-quality", "85", f"{OUT}/og-image-{L}.jpg"], check=True)
    print("ok")
