/* riveraurbano.com — interacción del sitio. Textos y datos llegan en window.SITIO (desde la BD). */
(function () {
  "use strict";
  const S = window.SITIO || { textos: {} };
  const t = k => S.textos[k] || k;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const linkWhats = msg => `https://wa.me/${S.whatsapp}?text=${encodeURIComponent(msg)}`;

  // Menú móvil
  const menuBtn = $(".menu-btn"), nav = $("#nav");
  const cerrarMenu = () => {
    nav.classList.remove("abierto");
    menuBtn.setAttribute("aria-expanded", "false");
    menuBtn.setAttribute("aria-label", t("nav.abrir_menu"));
  };
  menuBtn.addEventListener("click", () => {
    const abierto = nav.classList.toggle("abierto");
    menuBtn.setAttribute("aria-expanded", String(abierto));
    menuBtn.setAttribute("aria-label", t(abierto ? "nav.cerrar_menu" : "nav.abrir_menu"));
  });
  $$("a", nav).forEach(a => a.addEventListener("click", cerrarMenu));
  document.addEventListener("keydown", e => { if (e.key === "Escape") cerrarMenu(); });

  // Lotes: pestañas + croquis
  function mostrarLote(id) {
    $$(".pestana").forEach(b => b.setAttribute("aria-selected", String(b.dataset.lote === id)));
    $$(".ficha").forEach(f => f.hidden = f.id !== "ficha-" + id);
    $$(".croquis .lote").forEach(r => r.classList.toggle("activo", r.dataset.lote === id));
  }
  $$(".pestana").forEach(b => b.addEventListener("click", () => mostrarLote(b.dataset.lote)));
  $$(".croquis .lote").forEach(r => {
    r.addEventListener("click", () => mostrarLote(r.dataset.lote));
    r.addEventListener("keydown", e => {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); mostrarLote(r.dataset.lote); }
    });
  });

  // Video del dron: YouTube (si hay ID en la BD) o archivo propio
  const marco = $("#marcoVideo");
  if (S.youtubeId) {
    const f = document.createElement("iframe");
    f.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(S.youtubeId)}?rel=0`;
    f.title = S.videoTitulo || "";
    f.allow = "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture";
    f.allowFullscreen = true; f.loading = "lazy";
    marco.replaceChildren(f);
  } else {
    const v = $("#videoDron"), fuentes = $$("source", v);
    const sinVideo = () => $("#videoSinArchivo").classList.add("visible");
    fuentes[fuentes.length - 1].addEventListener("error", sinVideo);
    if (v.networkState === HTMLMediaElement.NETWORK_NO_SOURCE) sinVideo();
  }

  // Video de fondo en la portada (respeta "reducir movimiento" y el ahorro de datos)
  const vp = $("#videoPortada");
  const ahorro = navigator.connection && navigator.connection.saveData;
  if (vp && S.videoPortada && !ahorro && !matchMedia("(prefers-reduced-motion: reduce)").matches) {
    vp.src = S.videoPortada;
    vp.addEventListener("canplay", () => vp.play().catch(() => {}), { once: true });
    vp.addEventListener("error", () => vp.remove());
    vp.load();
  } else if (vp) { vp.remove(); }

  // Formulario → WhatsApp o correo
  const form = $("#formulario");
  let via = "whats";
  $$("button[type=submit]", form).forEach(b => b.addEventListener("click", () => { via = b.dataset.via; }));
  form.addEventListener("submit", e => {
    e.preventDefault();
    let ok = true;
    ["f-nombre", "f-tel"].forEach(id => {
      const input = $("#" + id), campo = input.closest(".campo");
      const bien = input.value.trim().length > 1;
      campo.classList.toggle("invalido", !bien);
      input.setAttribute("aria-invalid", String(!bien));
      if (!bien && ok) { input.focus(); ok = false; }
    });
    if (!ok) return;
    const d = Object.fromEntries(new FormData(form));
    const lineas = [
      t("msg.encabezado"),
      `${t("msg.nombre")}: ${d.nombre}`,
      `${t("msg.telefono")}: ${d.telefono}`,
      d.empresa && `${t("msg.empresa")}: ${d.empresa}`,
      `${t("msg.lote")}: ${d.lote}`,
      `${t("msg.uso")}: ${d.uso}`,
      `${t("msg.plazo")}: ${d.plazo}`,
      d.mensaje && `${t("msg.mensaje")}: ${d.mensaje}`,
    ].filter(Boolean).join("\n");
    if (via === "correo") {
      const asunto = t("correo.asunto_form").replace("{nombre}", d.nombre);
      location.href = `mailto:${S.correo}?subject=${encodeURIComponent(asunto)}&body=${encodeURIComponent(lineas)}`;
    } else {
      window.open(linkWhats(lineas), "_blank", "noopener");
    }
  });
})();
