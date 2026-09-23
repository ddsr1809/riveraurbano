-- 003: textos de ubicación según el mapa real (Lote A junto a CEDIS Coppel, Lote B junto a bp)
SET NAMES utf8mb4;
UPDATE textos SET valor = 'Dos terrenos en renta sobre Calle Novena, a pasos de Calzada Cetys y Carretera al Aeropuerto, en Mexicali, B.C. Ideales para comercio, servicios o estacionamiento. Mira el video aéreo y contáctanos por WhatsApp.' WHERE clave = 'meta.descripcion' AND idioma = 'es';
UPDATE textos SET valor = 'Two lots for lease on Calle Novena, steps from Calzada Cetys and the Airport Highway in Mexicali, Baja California. Ideal for retail, services or parking. Watch the aerial video and contact us on WhatsApp.' WHERE clave = 'meta.descripcion' AND idioma = 'en';
UPDATE textos SET valor = 'Dos terrenos frente a frente sobre Calle Novena, a pasos de Calzada Cetys. Mira el video aéreo y agenda una visita.' WHERE clave = 'og.descripcion' AND idioma = 'es';
UPDATE textos SET valor = 'Two facing lots on Calle Novena, steps from Calzada Cetys. Watch the aerial video and book a visit.' WHERE clave = 'og.descripcion' AND idioma = 'en';
UPDATE textos SET valor = 'Dos predios frente a frente sobre Calle Novena, a pasos del cruce con Calzada Cetys y Carretera al Aeropuerto. Una zona de paso diario en Mexicali, lista para tu negocio.' WHERE clave = 'hero.sub' AND idioma = 'es';
UPDATE textos SET valor = 'Two facing lots on Calle Novena, steps from the Calzada Cetys and Airport Highway intersection. A busy everyday route in Mexicali, ready for your business.' WHERE clave = 'hero.sub' AND idioma = 'en';
UPDATE textos SET valor = 'Croquis de ubicación: los dos terrenos están sobre Calle Novena, al sur de Calzada Cetys. El Lote A está del lado poniente, junto al CEDIS de Coppel y un car wash; el Lote B está del lado oriente, detrás de la gasolinera bp.' WHERE clave = 'croquis.titulo' AND idioma = 'es';
UPDATE textos SET valor = 'Location sketch: both lots are on Calle Novena, south of Calzada Cetys. Lot A is on the west side, next to the Coppel distribution center and a car wash; Lot B is on the east side, behind the bp gas station.' WHERE clave = 'croquis.titulo' AND idioma = 'en';
UPDATE textos SET valor = 'Lado poniente de Calle Novena, junto al CEDIS de Coppel' WHERE clave = 'lote.a.donde' AND idioma = 'es';
UPDATE textos SET valor = 'West side of Calle Novena, next to the Coppel distribution center' WHERE clave = 'lote.a.donde' AND idioma = 'en';
UPDATE textos SET valor = 'Lado oriente de Calle Novena, junto a la gasolinera bp' WHERE clave = 'lote.b.donde' AND idioma = 'es';
UPDATE textos SET valor = 'East side of Calle Novena, next to the bp gas station' WHERE clave = 'lote.b.donde' AND idioma = 'en';
UPDATE textos SET valor = 'Por aquí pasa el tráfico que entra y sale de Calzada Cetys y de la Carretera al Aeropuerto, y el CEDIS de Coppel, la gasolinera y el car wash ya generan movimiento todo el día. Estos giros sacan provecho de ese flujo:' WHERE clave = 'propuesta.intro' AND idioma = 'es';
UPDATE textos SET valor = 'Traffic to and from Calzada Cetys and the Airport Highway passes right by, and the Coppel distribution center, the gas station and the car wash keep the area busy all day. These businesses make the most of that flow:' WHERE clave = 'propuesta.intro' AND idioma = 'en';
UPDATE textos SET valor = 'Lote B (junto a la bp)' WHERE clave = 'form.lote.b' AND idioma = 'es';
UPDATE textos SET valor = 'Lot B (next to bp)' WHERE clave = 'form.lote.b' AND idioma = 'en';
