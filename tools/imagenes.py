"""Genera las ilustraciones del sitio (vista aérea, portada del video e imagen para redes) en español e inglés."""
import cairosvg, random, subprocess, sys, os
OUT = sys.argv[1]
A="#1F2B33"; Y="#F5B400"; SAGE="#6F8466"; ROAD="#3A454C"; WALK="#D8D4C8"; LOT="#CDBB90"
F="Arial,Helvetica,sans-serif"
TXT = {
 "es": {"a":"Lote A","b":"Lote B","t1":"Terrenos","t2":"en renta en","t3":"Calle Novena","sub":"Mexicali, B.C. · Calzada Cetys y Calle Novena"},
 "en": {"a":"Lot A","b":"Lot B","t1":"Land","t2":"for lease on","t3":"Calle Novena","sub":"Mexicali, B.C. · Calzada Cetys &amp; Calle Novena"},
}

def aerial(L, w=1600, h=900):
    random.seed(7)
    T=TXT[L]; s=[f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}">',
                 f'<rect width="{w}" height="{h}" fill="#C7B98F"/>']
    for _ in range(900):
        x,y=random.uniform(0,w),random.uniform(0,h)
        s.append(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{random.uniform(1,4):.1f}" fill="#B9AA7E" opacity=".5"/>')
    ny,nh=470,110; cx,cw=1180,120
    # banquetas
    s.append(f'<rect x="0" y="{ny-14}" width="{w}" height="{nh+28}" fill="{WALK}"/>')
    s.append(f'<rect x="{cx-14}" y="0" width="{cw+28}" height="{h}" fill="{WALK}"/>')
    # calles: Calzada Cetys cruza la Novena y continúa
    s.append(f'<rect x="0" y="{ny}" width="{w}" height="{nh}" fill="{ROAD}"/>')
    s.append(f'<rect x="{cx}" y="0" width="{cw}" height="{h}" fill="{ROAD}"/>')
    m=ny+nh/2; c=cx+cw/2
    s.append(f'<line x1="0" y1="{m}" x2="{cx-20}" y2="{m}" stroke="{Y}" stroke-width="4" stroke-dasharray="40 30"/>')
    s.append(f'<line x1="{cx+cw+20}" y1="{m}" x2="{w}" y2="{m}" stroke="{Y}" stroke-width="4" stroke-dasharray="40 30"/>')
    s.append(f'<line x1="{c}" y1="0" x2="{c}" y2="{ny-20}" stroke="#fff" stroke-width="4" stroke-dasharray="40 30"/>')
    s.append(f'<line x1="{c}" y1="{ny+nh+20}" x2="{c}" y2="{h}" stroke="#fff" stroke-width="4" stroke-dasharray="40 30"/>')
    for i in range(6):  # pasos peatonales
        s.append(f'<rect x="{cx+8+i*19}" y="{ny-12}" width="10" height="10" fill="#fff" opacity=".8"/>')
        s.append(f'<rect x="{cx+8+i*19}" y="{ny+nh+2}" width="10" height="10" fill="#fff" opacity=".8"/>')
    # Coppel (al norte del Lote A, pegado a Cetys)
    s.append(f'<rect x="640" y="40" width="510" height="205" fill="#E4E2DC" stroke="#BDB8AD" stroke-width="4"/>')
    for i in range(8): s.append(f'<rect x="{675+i*58}" y="70" width="30" height="30" fill="#C9C5BB"/>')
    # Lote A (entre Coppel y Novena, hasta Cetys)
    s.append(f'<rect x="330" y="265" width="820" height="185" fill="{LOT}" stroke="{Y}" stroke-width="6" stroke-dasharray="22 14"/>')
    # Lote B (del otro lado de la Novena)
    s.append(f'<rect x="280" y="600" width="600" height="270" fill="{LOT}" stroke="{Y}" stroke-width="6" stroke-dasharray="22 14"/>')
    # 7-Eleven en la esquina, pegado a Cetys
    s.append(f'<rect x="900" y="600" width="250" height="180" fill="#E4E2DC" stroke="#BDB8AD" stroke-width="4"/>')
    s.append(f'<rect x="900" y="600" width="250" height="16" fill="#2E7D4F"/><rect x="900" y="616" width="250" height="8" fill="#E8792B"/>')
    # árboles fuera de los lotes
    for x,y in [(80,90),(170,200),(60,330),(220,60),(120,640),(60,760),(190,820),(1380,90),(1480,210),(1400,340),(1530,420),(1360,650),(1500,720),(1420,840),(560,120),(470,60)]:
        s.append(f'<circle cx="{x}" cy="{y}" r="{random.uniform(20,32):.0f}" fill="{SAGE}" opacity=".85"/>')
    cols=["#FFFFFF","#A63A3A","#2E5E8C","#222","#C9C9C9"]
    for i,x in enumerate([60,250,620,700,930,1390,1520,1060]):
        s.append(f'<rect x="{x}" y="{ny+18 if i%2 else ny+nh-44}" width="56" height="26" rx="6" fill="{cols[i%5]}"/>')
    for i,y in enumerate([300,360,660,820]):
        s.append(f'<rect x="{cx+18+(i%2)*58}" y="{y}" width="26" height="56" rx="6" fill="{cols[(i+2)%5]}"/>')
    def tag(x,y,t,wd=170):
        return (f'<g transform="translate({x},{y})"><rect x="-{wd/2}" y="-30" width="{wd}" height="60" rx="30" fill="{A}"/>'
                f'<text x="0" y="11" text-anchor="middle" font-family="{F}" font-weight="700" font-size="32" fill="{Y}">{t}</text></g>')
    def street(x,y,t,rot=0,wd=230):
        return (f'<g transform="translate({x},{y}) rotate({rot})"><rect x="-{wd/2}" y="-24" width="{wd}" height="48" rx="8" fill="#fff"/>'
                f'<text x="0" y="10" text-anchor="middle" font-family="{F}" font-weight="700" font-size="28" fill="{A}">{t}</text></g>')
    s.append(tag(740,357,T["a"])); s.append(tag(580,735,T["b"]))
    s.append(f'<text x="895" y="165" text-anchor="middle" font-family="{F}" font-weight="700" font-size="34" fill="#6B6860">Coppel</text>')
    s.append(f'<text x="1025" y="710" text-anchor="middle" font-family="{F}" font-weight="700" font-size="30" fill="#6B6860">7-Eleven</text>')
    s.append(street(470,m,"Calle Novena")); s.append(street(c,170,"Calzada Cetys",-90,250))
    s.append('</svg>'); return "\n".join(s)

def og(L):
    T=TXT[L]; inner=aerial(L).split('>',1)[1].rsplit('</svg>',1)[0]
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
<defs><clipPath id="c"><rect x="600" y="0" width="600" height="630"/></clipPath></defs>
<rect width="1200" height="630" fill="{A}"/>
<g clip-path="url(#c)"><g transform="translate(280,0) scale(.7)">{inner}</g></g>
<rect x="70" y="80" width="64" height="64" fill="none" stroke="{Y}" stroke-width="6" stroke-dasharray="14 8"/><circle cx="134" cy="80" r="11" fill="{Y}"/>
<text x="160" y="130" font-family="{F}" font-weight="700" font-size="44" fill="#fff">Rivera Urbano</text>
<text font-family="{F}" font-weight="800" font-size="64" fill="#fff"><tspan x="70" y="280">{T["t1"]}</tspan><tspan x="70" y="355">{T["t2"]}</tspan><tspan x="70" y="430" fill="{Y}">{T["t3"]}</tspan></text>
<text x="70" y="510" font-family="{F}" font-size="24" fill="#C9D1D6">{T["sub"]}</text>
<text x="70" y="565" font-family="{F}" font-weight="700" font-size="30" fill="{Y}">riveraurbano.com</text></svg>'''

os.makedirs(OUT, exist_ok=True)
for L in ("es","en"):
    open(f"{OUT}/vista-aerea-{L}.svg","w").write(aerial(L))
    cairosvg.svg2png(bytestring=aerial(L).encode(), write_to="/tmp/p.png", output_width=1600)
    subprocess.run(["convert","/tmp/p.png","-quality","82",f"{OUT}/poster-{L}.jpg"],check=True)
    cairosvg.svg2png(bytestring=og(L).encode(), write_to="/tmp/o.png", output_width=1200)
    subprocess.run(["convert","/tmp/o.png","-quality","85",f"{OUT}/og-image-{L}.jpg"],check=True)
print("ok")
