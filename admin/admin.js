/* Panel de Rivera Urbano: avisos de cambios sin guardar, confirmaciones y subida de video con progreso. */
(function () {
  "use strict";

  // 1) Resaltar campos cambiados y avisar si se sale sin guardar
  document.querySelectorAll("form[data-avisar-cambios]").forEach(function (form) {
    var campos = form.querySelectorAll("[data-original]");
    var contador = form.querySelector("[data-contador]");
    var enviando = false;
    function revisar() {
      var n = 0;
      campos.forEach(function (c) {
        var cambio = c.value.replace(/\r\n/g, "\n").trim() !== c.dataset.original.trim();
        c.classList.toggle("cambiado", cambio);
        if (cambio) n++;
      });
      if (contador) {
        contador.textContent = n === 0 ? "Sin cambios" : n === 1 ? "1 cambio sin guardar" : n + " cambios sin guardar";
        contador.classList.toggle("hay", n > 0);
      }
      return n;
    }
    form.addEventListener("input", revisar);
    form.addEventListener("submit", function () { enviando = true; });
    window.addEventListener("beforeunload", function (e) {
      if (!enviando && revisar() > 0) { e.preventDefault(); e.returnValue = ""; }
    });
    // Ajustar alto de los textarea al escribir
    form.querySelectorAll("textarea").forEach(function (t) {
      t.addEventListener("input", function () { t.style.height = "auto"; t.style.height = t.scrollHeight + 4 + "px"; });
    });
  });

  // 2) Confirmaciones
  document.querySelectorAll("form[data-confirmar]").forEach(function (form) {
    form.addEventListener("submit", function (e) { if (!confirm(form.dataset.confirmar)) e.preventDefault(); });
  });

  // 3) Subida de video con barra de progreso
  function mb(b) { return (b / 1048576).toFixed(b > 104857600 ? 0 : 1) + " MB"; }
  document.querySelectorAll("form[data-subir]").forEach(function (form) {
    var input = form.querySelector("input[type=file]");
    var boton = form.querySelector("button");
    var caja = form.querySelector(".progreso");
    var barra = form.querySelector(".progreso-barra");
    var texto = form.querySelector(".progreso-texto");
    var limite = parseInt(form.dataset.limite, 10) || 0;
    var nombre = document.createElement("span");
    nombre.className = "nombre-archivo";
    form.insertBefore(nombre, caja);

    input.addEventListener("change", function () {
      var f = input.files[0];
      nombre.textContent = f ? f.name + " · " + mb(f.size) : "";
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var f = input.files[0];
      if (!f) { alert("Primero elige un video."); return; }
      if (limite && f.size > limite) { alert("El video pesa " + mb(f.size) + " y el máximo es " + mb(limite) + ". Comprímelo con HandBrake antes de subirlo."); return; }
      if (!/\.mp4$/i.test(f.name)) { alert("El video debe ser MP4."); return; }

      var datos = new FormData(form);
      datos.append("ajax", "1");
      var xhr = new XMLHttpRequest();
      xhr.open("POST", form.action);
      xhr.setRequestHeader("X-CSRF", form.querySelector("[name=csrf]").value);
      caja.hidden = false; boton.disabled = true; input.disabled = true;
      xhr.upload.addEventListener("progress", function (ev) {
        if (!ev.lengthComputable) return;
        var pct = Math.round(ev.loaded / ev.total * 100);
        barra.style.width = pct + "%";
        texto.textContent = pct < 100 ? "Subiendo " + pct + "% (" + mb(ev.loaded) + " de " + mb(ev.total) + ")" : "Procesando…";
      });
      function terminar(ok, msg) {
        boton.disabled = false; input.disabled = false;
        texto.textContent = msg;
        if (ok) { setTimeout(function () { location.reload(); }, 900); }
        else { barra.style.width = "0"; alert(msg); }
      }
      xhr.onload = function () {
        var r = null;
        try { r = JSON.parse(xhr.responseText); } catch (err) {}
        if (r) terminar(r.ok, r.mensaje);
        else terminar(false, "El servidor no aceptó el archivo. Puede ser demasiado grande o la sesión expiró; recarga la página e intenta de nuevo.");
      };
      xhr.onerror = function () { terminar(false, "Se perdió la conexión durante la subida. Vuelve a intentarlo."); };
      xhr.send(datos);
    });
  });
})();
