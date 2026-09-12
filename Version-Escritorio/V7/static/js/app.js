
// =========================================================
// FECHAS AUTOMÁTICAS EN TODO EL SISTEMA
// =========================================================
// Completa con la fecha local de hoy cualquier campo <input type="date">
// que esté vacío. El usuario puede modificar la fecha manualmente después.
function lanrTodayLocal() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const d = String(now.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function fillEmptyDateInputs(root = document) {
  const fields = root.querySelectorAll ? root.querySelectorAll('input[type="date"]') : [];
  fields.forEach((input) => {
    if (!input.value && !input.disabled && !input.readOnly && input.name !== 'fecha_requerida_item[]') {
      input.value = lanrTodayLocal();
    }
  });
}

document.addEventListener("DOMContentLoaded", function () {
  fillEmptyDateInputs(document);

  // También cubre campos de fecha creados dinámicamente (por ejemplo, comprobantes).
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches && node.matches('input[type="date"]')) {
          if (!node.value && !node.disabled && !node.readOnly && node.name !== 'fecha_requerida_item[]') node.value = lanrTodayLocal();
        }
        fillEmptyDateInputs(node);
      });
    });
  });

  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  }
});

function showForm(t) {
  const reqForm = document.getElementById("reqForm");
  const spForm = document.getElementById("spForm");
  const optionReq = document.getElementById("optionReq");
  const optionSp = document.getElementById("optionSp");
  const pageTitle = document.getElementById("createPageTitle");
  const pageSubtitle = document.getElementById("createPageSubtitle");

  if (reqForm) reqForm.classList.toggle("hidden", t !== "req");
  if (spForm) spForm.classList.toggle("hidden", t !== "sp");
  if (optionReq) optionReq.classList.toggle("active", t === "req");
  if (optionSp) optionSp.classList.toggle("active", t === "sp");

  if (pageTitle) pageTitle.textContent = t === "req" ? "Nuevo trámite - Requerimiento de materiales" : "Nuevo trámite - Solicitud de pago";
  if (pageSubtitle) pageSubtitle.textContent = t === "req" ? "Registra los materiales que necesitas para la obra." : "Registra la información necesaria para gestionar el pago.";

  document.querySelectorAll('input[type="date"]').forEach((i) => {
    if (!i.value && i.name !== "fecha_requerida_item[]") i.value = lanrTodayLocal();
  });
}
function toggleReq(k, on) {
  let sec = document.getElementById("sec-" + k);
  sec.classList.toggle("hidden", !on);
  let body = document.getElementById("body-" + k);
  if (on && body.children.length === 0)
    addReqRow(
      "body-" + k,
      k === "ejec" ? "Ejecución de obra" : "Seguridad en obra",
    );
}
function updateFormat(v) {
  let f = (v.split("-")[0] || "").replace(/\D/g, "");
  let e = document.getElementById("formatCode");
  if (e) e.value = "F01A-LANR-" + f;
}
function addReqRow(id, sec) {
  let b = document.getElementById(id),
    n = b.children.length + 1,
    tr = document.createElement("tr");
  tr.innerHTML = `<td>${String(n).padStart(2, "0")}</td><td><input type='hidden' name='seccion[]' value='${sec}'><input name='descripcion[]' required></td><td><input type='number' step='0.01' min='0' name='cantidad[]' value='1' oninput='calcReq(this)'></td><td><input name='unidad[]' placeholder='UND'></td><td><input type='number' step='0.01' min='0' name='stock[]' oninput='calcReq(this)'></td><td><input class='buy' readonly></td><td><input name='justificacion[]'></td><td><button type='button' class='btn small' onclick='this.closest("tr").remove()'>×</button></td>`;
  b.appendChild(tr);
  calcReq(tr.querySelector("input[type=number]"));
}
function calcReq(el) {
  let tr = el.closest("tr"),
    nums = tr.querySelectorAll("input[type=number]"),
    q = parseFloat(nums[0].value) || 0,
    s = parseFloat(nums[1]?.value) || 0;
  tr.querySelector(".buy").value = Math.max(q - s, 0);
}
function toggleSP(v) {
  document.getElementById("spCommon").classList.toggle("hidden", !v);
  document
    .querySelectorAll(".thirdOnly")
    .forEach((e) => e.classList.toggle("hidden", v !== "Persona/Empresa"));
  document
    .querySelectorAll(".planOnly")
    .forEach((e) => e.classList.toggle("hidden", v !== "Planilla"));
  let b = document.getElementById("spBody");
  if (v && b.children.length === 0) addSPRow();
  document.querySelectorAll("input[type=date]").forEach((i) => {
    if (!i.value) i.value = lanrTodayLocal();
  });
}
function addSPRow() {
  let b = document.getElementById("spBody"),
    n = b.children.length + 1,
    tr = document.createElement("tr");
  tr.innerHTML = `<td>${n}</td><td><input name='concepto[]' required></td><td><input name='unidad_sp[]'></td><td><input type='number' name='cantidad_sp[]' step='0.01' value='1' oninput='calcSP(this)'></td><td><input type='number' name='costo[]' step='0.01' value='0' oninput='calcSP(this)'></td><td><input class='monto' readonly value='0.00'></td><td><button type='button' class='btn small' onclick='this.closest("tr").remove()'>×</button></td>`;
  b.appendChild(tr);
}
function calcSP(el) {
  let tr = el.closest("tr"),
    n = tr.querySelectorAll("input[type=number]");
  tr.querySelector(".monto").value = (
    (parseFloat(n[0].value) || 0) * (parseFloat(n[1].value) || 0)
  ).toFixed(2);
}

document.addEventListener("DOMContentLoaded", function () {
  const username = document.getElementById("username");

  const password = document.getElementById("password");

  const togglePassword = document.getElementById("togglePassword");

  const rememberUser = document.getElementById("rememberUser");

  const loginForm = document.getElementById("loginForm");

  // MOSTRAR / OCULTAR CONTRASEÑA

  if (togglePassword && password) {
    togglePassword.addEventListener("click", function () {
      const icon = togglePassword.querySelector("i");

      if (password.type === "password") {
        password.type = "text";

        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        password.type = "password";

        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });
  }

  // RECORDAR USUARIO

  if (username && rememberUser) {
    const savedUser = localStorage.getItem("lanr_usuario");

    if (savedUser) {
      username.value = savedUser;

      rememberUser.checked = true;
    }
  }

  if (loginForm) {
    loginForm.addEventListener("submit", function () {
      if (rememberUser && rememberUser.checked) {
        localStorage.setItem("lanr_usuario", username.value.trim());
      } else {
        localStorage.removeItem("lanr_usuario");
      }
    });
  }
});

function togglePasswordField(id, button) {
  const input = document.getElementById(id);
  const icon = button.querySelector("i");

  if (!input || !icon) return;

  if (input.type === "password") {
    input.type = "text";

    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    input.type = "password";

    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

function openReqModal() {
  const modal = document.getElementById("reqModal");

  if (!modal) return;

  modal.classList.remove("hidden");
  document.body.classList.add("modal-open");
}

function closeReqModal() {
  const modal = document.getElementById("reqModal");

  if (!modal) return;

  modal.classList.add("hidden");
  document.body.classList.remove("modal-open");
}

document.addEventListener("click", function (event) {
  const modal = document.getElementById("reqModal");

  if (!modal) return;

  if (event.target === modal) {
    closeReqModal();
  }
});

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeReqModal();
  }
});

// =========================================================
// MAYÚSCULAS AUTOMÁTICAS EN CAMPOS DEL SISTEMA
// =========================================================

document.addEventListener("input", function (e) {
  const el = e.target;

  if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement)) {
    return;
  }

  // =========================================
  // SOLO NÚMEROS
  // =========================================

  if (el.classList.contains("numbers-only")) {
    el.value = el.value.replace(/\D/g, "");
    return;
  }

  // =========================================
  // NO CONVERTIR CAMPOS MARCADOS
  // =========================================

  if (el.classList.contains("no-uppercase")) {
    return;
  }

  // =========================================
  // NO CONVERTIR CAMPOS DE ACCESO / CUENTA
  // =========================================

  if (el.closest(".login")) {
    return;
  }

  // =========================================
  // NO CONVERTIR DATOS PERSONALES / TÉCNICOS
  // =========================================

  const tiposExcluidos = [
    "password",
    "number",
    "date",
    "file",
    "checkbox",
    "radio",
    "email",
    "tel",
    "hidden",
  ];

  if (tiposExcluidos.includes(el.type)) {
    return;
  }

  // Usuario de acceso
  if (el.id === "username") {
    return;
  }

  // Contraseñas aunque el ojito cambie
  // temporalmente de password a text
  const autocomplete = (el.getAttribute("autocomplete") || "").toLowerCase();

  if (
    autocomplete === "current-password" ||
    autocomplete === "new-password" ||
    autocomplete === "username" ||
    autocomplete === "email"
  ) {
    return;
  }

  // Campos de solo lectura
  if (el.readOnly) {
    return;
  }

  // Los campos monetarios tienen su propio formato
  if (el.classList.contains("money-input")) {
    return;
  }

  // =========================================
  // CONVERTIR EL RESTO A MAYÚSCULAS
  // =========================================

  const inicio = el.selectionStart;
  const fin = el.selectionEnd;

  const valorMayuscula = el.value.toUpperCase();

  if (el.value !== valorMayuscula) {
    el.value = valorMayuscula;

    if (inicio !== null && fin !== null) {
      el.setSelectionRange(inicio, fin);
    }
  }
});

// =========================================================
// DINERO EN TIEMPO REAL: 1,000,000.50
// =========================================================

function formatMoneyLive(value) {
  if (!value) return "";

  value = value.replace(/,/g, "");
  value = value.replace(/[^\d.]/g, "");

  const punto = value.indexOf(".");

  if (punto !== -1) {
    value =
      value.substring(0, punto + 1) +
      value.substring(punto + 1).replace(/\./g, "");
  }

  let partes = value.split(".");
  let entero = partes[0] || "";
  let decimal = partes.length > 1 ? partes[1] : undefined;

  if (entero.length > 1) {
    entero = entero.replace(/^0+(?=\d)/, "");
  }

  entero = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

  if (decimal !== undefined) {
    decimal = decimal.substring(0, 2);
    return entero + "." + decimal;
  }

  return entero;
}

document.addEventListener("input", function (e) {
  const el = e.target;

  if (!el.classList.contains("money-input")) return;

  const valorAnterior = el.value;
  const cursorAnterior = el.selectionStart;

  const caracteresAntes = valorAnterior
    .substring(0, cursorAnterior)
    .replace(/,/g, "").length;

  el.value = formatMoneyLive(valorAnterior);

  let posicion = 0;
  let contador = 0;

  while (posicion < el.value.length && contador < caracteresAntes) {
    if (el.value[posicion] !== ",") {
      contador++;
    }

    posicion++;
  }

  try {
    el.setSelectionRange(posicion, posicion);
  } catch (e) {}
});

// Al salir: completa los dos decimales
document.addEventListener(
  "blur",
  function (e) {
    const el = e.target;

    if (!el.classList.contains("money-input")) return;

    const limpio = el.value.replace(/,/g, "");

    if (!limpio) return;

    const numero = parseFloat(limpio);

    if (isNaN(numero)) return;

    el.value = numero.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },
  true,
);

// =====================================================
// BUSCADOR DE TRÁMITES
// =====================================================

document.addEventListener("DOMContentLoaded", function () {
  const buscador = document.getElementById("requestSearch");
  const tipo = document.getElementById("tramiteTipo");

  // Si no estamos en la página de trámites, no hacemos nada
  if (!buscador || !tipo) return;

  function filterRequests() {
    const texto = buscador.value.trim().toLowerCase();

    const tipoSeleccionado = tipo.value;

    const filas = document.querySelectorAll(".tramite-row");

    filas.forEach(function (fila) {
      const codigo = fila.dataset.codigo || "";
      const numero = fila.dataset.numero || "";
      const tipoFila = fila.dataset.tipo || "";

      // Buscar por código O número
      const coincideTexto =
        texto === "" || codigo.includes(texto) || numero.includes(texto);

      // Filtrar REQ / SP
      const coincideTipo =
        tipoSeleccionado === "" || tipoFila === tipoSeleccionado;

      if (coincideTexto && coincideTipo) {
        fila.style.display = "";
      } else {
        fila.style.display = "none";
      }
    });
  }

  // Mientras escribe
  buscador.addEventListener("input", filterRequests);

  // Cuando cambia REQ / SP
  tipo.addEventListener("change", filterRequests);

  // Aplicar el filtro también al cargar la página.
  filterRequests();
});

// =====================================================
// MODAL - DETALLE COMPLETO DEL TRÁMITE
// =====================================================

function openDetailModal() {
  const modal = document.getElementById("detailModal");

  if (!modal) return;

  modal.classList.remove("hidden");
  document.body.classList.add("modal-open");
}

function closeDetailModal() {
  const modal = document.getElementById("detailModal");

  if (!modal) return;

  modal.classList.add("hidden");
  document.body.classList.remove("modal-open");
}

document.addEventListener("click", function (e) {
  const modal = document.getElementById("detailModal");

  if (modal && e.target === modal) {
    closeDetailModal();
  }
});

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeDetailModal();
  }
});

function toggleNotifications() {
  const panel = document.getElementById("notificationPanel");

  if (!panel) return;

  panel.classList.toggle("hidden");
}

document.addEventListener("click", function (event) {
  const panel = document.getElementById("notificationPanel");
  const wrapper = document.querySelector(".notification-wrapper");

  if (!panel || !wrapper) return;

  if (!wrapper.contains(event.target)) {
    panel.classList.add("hidden");
  }
});

/* =========================================================
   AVISOS TEMPORALES
========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  const flashes = document.querySelectorAll(".flash");

  flashes.forEach(function (flash, index) {
    setTimeout(
      function () {
        flash.classList.add("flash-hide");

        setTimeout(function () {
          flash.remove();
        }, 300);
      },
      4000 + index * 200,
    );
  });
});

/* =========================================================
   NUEVO TRÁMITE — CARDS NT-*
========================================================= */

function ntCardNum(containerId) {
  return document.getElementById(containerId).children.length + 1;
}

function ntRemove(btn, cid) {
  var c = document.getElementById(cid);
  if (c.children.length <= 1) {
    alert("El trámite debe tener al menos un ítem.");
    return;
  }
  btn.closest(".nt-card").remove();
  // renumerar
  var labels = c.querySelectorAll(".nt-card-label");
  labels.forEach(function (el, i) {
    var txt = el.childNodes[el.childNodes.length - 1];
    if (txt && txt.nodeType === 3) {
      txt.textContent = "Ítem " + (i + 1);
    }
  });
}

function ntCalcComprar(card) {
  var cant = parseFloat(card.querySelector("[data-cant]").value) || 0;
  var stock = parseFloat(card.querySelector("[data-stock]").value) || 0;
  card.querySelector("[data-comprar]").value = Math.max(cant - stock, 0);
}

function ntCalcMonto(card) {
  var cant = parseFloat(card.querySelector("[data-cant]").value) || 0;
  var costo = parseFloat(card.querySelector("[data-costo]").value) || 0;
  card.querySelector("[data-monto]").value = (cant * costo).toFixed(2);
}

function addReqCard(tipo) {
  var cid = "cards-" + tipo;
  var seccion = tipo === "ejec" ? "Ejecución de obra" : "Seguridad en obra";
  var icon = tipo === "ejec" ? "fa-helmet-safety" : "fa-shield-halved";
  var n = ntCardNum(cid);

  var card = document.createElement("div");
  card.className = "nt-card " + tipo;
  card.innerHTML =
    '<div class="nt-card-head">' +
    '<span class="nt-card-label">' +
    '<i class="fa-solid ' +
    icon +
    '"></i>Ítem ' +
    n +
    "</span>" +
    '<button type="button" class="nt-card-remove" title="Eliminar"' +
    " onclick=\"ntRemove(this,'" +
    cid +
    "')\">" +
    '<i class="fa-solid fa-xmark"></i>' +
    "</button>" +
    "</div>" +
    '<input type="hidden" name="seccion[]" value="' +
    seccion +
    '" />' +
    '<div class="nt-card-body req-layout">' +
    '<div class="nt-card-texts">' +
    '<div class="nt-field">' +
    "<label>Descripción *</label>" +
    '<textarea name="descripcion[]" required' +
    ' placeholder="Describe el material o insumo..."></textarea>' +
    "</div>" +
    '<div class="nt-field">' +
    "<label>Justificación</label>" +
    '<textarea name="justificacion[]"' +
    ' placeholder="¿Para qué se usará? Urgencia, ya está en obra..."></textarea>' +
    "</div>" +
    "</div>" +
    '<div class="nt-card-nums">' +
    '<div class="nt-field"><label>Cantidad</label>' +
    '<input type="number" step="0.01" min="0" name="cantidad[]" value="1" data-cant' +
    " oninput=\"ntCalcComprar(this.closest('.nt-card'))\" /></div>" +
    '<div class="nt-field"><label>Unidad</label>' +
    '<input name="unidad[]" value="UND" /></div>' +
    '<div class="nt-field"><label>Stock</label>' +
    '<input type="number" step="0.01" min="0" name="stock[]" value="0" data-stock' +
    " oninput=\"ntCalcComprar(this.closest('.nt-card'))\" /></div>" +
    '<div class="nt-field"><label>A comprar</label>' +
    '<input data-comprar class="ro" readonly value="1" /></div>' +
    "</div>" +
    "</div>";

  document.getElementById(cid).appendChild(card);
  card.querySelector("textarea").focus();
}

function addSPCard() {
  var n = ntCardNum("spCards");
  var card = document.createElement("div");
  card.className = "nt-card sp";
  card.innerHTML =
    '<div class="nt-card-head">' +
    '<span class="nt-card-label">' +
    '<i class="fa-solid fa-list-ul"></i>Ítem ' +
    n +
    "</span>" +
    '<button type="button" class="nt-card-remove" title="Eliminar"' +
    " onclick=\"ntRemove(this,'spCards')\">" +
    '<i class="fa-solid fa-xmark"></i>' +
    "</button>" +
    "</div>" +
    '<div class="nt-card-body sp-layout">' +
    '<div class="nt-field">' +
    "<label>Concepto *</label>" +
    '<textarea name="concepto[]" required' +
    ' placeholder="Describe el concepto o servicio..."></textarea>' +
    "</div>" +
    '<div class="nt-field"><label>Unidad</label>' +
    '<input name="unidad_sp[]" placeholder="GLB / UND" /></div>' +
    '<div class="nt-field"><label>Cantidad</label>' +
    '<input type="number" step="0.01" min="0" name="cantidad_sp[]" value="1" data-cant' +
    " oninput=\"ntCalcMonto(this.closest('.nt-card'))\" /></div>" +
    '<div class="nt-field"><label>Costo unit.</label>' +
    '<input type="number" step="0.01" min="0" name="costo[]" value="0" data-costo' +
    " oninput=\"ntCalcMonto(this.closest('.nt-card'))\" /></div>" +
    '<div class="nt-field"><label>Monto</label>' +
    '<input data-monto class="ro" readonly value="0.00" /></div>' +
    "</div>";

  document.getElementById("spCards").appendChild(card);
  card.querySelector("textarea").focus();
}

function toggleReqCards(tipo, on) {
  document.getElementById("sec-" + tipo).classList.toggle("hidden", !on);
  if (on && document.getElementById("cards-" + tipo).children.length === 0) {
    addReqCard(tipo);
  }
}

/* =========================================================
   FIX - NUEVO TRÁMITE
   REQUERIMIENTO + SOLICITUD DE PAGO
========================================================= */

(function () {
  window.secActive = window.secActive || {
    ejec: false,
    seg: false,
    ofi: false,
  };

  function unitOptionsHtml(units, selected, kind) {
    var source = Array.isArray(units) ? units.slice() : [];

    // Respaldo: si el arreglo global aún no fue cargado, leer directamente
    // las unidades que Flask dejó en el HTML de la página.
    if (source.length === 0) {
      var data = document.getElementById("requestUnitData") ||
                 document.getElementById("editRequestUnitData");
      if (data) {
        try {
          var raw = kind === "sp" ? data.dataset.spUnits : data.dataset.reqUnits;
          source = JSON.parse(raw || "[]");
        } catch (error) {
          source = [];
        }
      }
    }

    var html = '<option value="">Unidad</option>';
    source.forEach(function (u) {
      html += '<option value="' + u + '"' + (selected === u ? ' selected' : '') + '>' + u + '</option>';
    });
    return html;
  }

  /* =========================================================
     REQUERIMIENTO
  ========================================================= */

  function reqSectionName(tipo) {
    if (tipo === "ejec") return "Ejecución de obra";
    if (tipo === "seg") return "Seguridad en obra";
    return "Útiles de oficina";
  }

  function reqHeader(tipo) {
    var tr = document.createElement("tr");

    tr.className = "nt-sec-row " + tipo;

    tr.dataset.header = tipo;

    var label = reqSectionName(tipo);
    var icon = tipo === "ejec" ? "fa-helmet-safety" : (tipo === "seg" ? "fa-shield-halved" : "fa-paperclip");

    tr.innerHTML =
      '<td colspan="9">' +
      "<span>" +
      '<i class="fa-solid ' + icon + '"></i> ' + label +
      "</span>" +
      "</td>";

    return tr;
  }

  function reqPriorityClass(value) {
    if (value === "Urgente") return "urgent";
    if (value === "Prioritario") return "priority";
    return "normal";
  }

  function reqFormatDate(value) {
    if (!value) return "";
    var parts = value.split("-");
    return parts.length === 3 ? parts[2] + "/" + parts[1] + "/" + parts[0] : value;
  }

  function reqRefreshDetailsSummary(mainRow, detailRow) {
    if (!mainRow || !detailRow) return;
    var summary = mainRow.querySelector(".req-details-summary");
    if (!summary) return;

    var priority = detailRow.querySelector('[name="prioridad_item[]"]');
    var date = detailRow.querySelector('[name="fecha_requerida_item[]"]');
    var fileInput = detailRow.querySelector('input[type="file"]');
    var existingVisible = detailRow.querySelectorAll('.req-image-thumb.existing:not(.marked-remove)').length;
    var newCount = fileInput && fileInput.files ? fileInput.files.length : 0;
    var chips = [];

    if (priority && priority.value !== "Normal") {
      chips.push('<span class="req-mini-chip ' + reqPriorityClass(priority.value) + '">' + priority.value + '</span>');
    }
    if (date && date.value) {
      chips.push('<span class="req-mini-chip"><i class="fa-regular fa-calendar"></i> ' + reqFormatDate(date.value) + '</span>');
    }
    if (existingVisible + newCount > 0) {
      chips.push('<span class="req-mini-chip"><i class="fa-solid fa-paperclip"></i> ' + (existingVisible + newCount) + '</span>');
    }
    summary.innerHTML = chips.join("");
  }

  function reqRenderSelectedImages(detailRow) {
    var input = detailRow.querySelector('input[type="file"]');
    var preview = detailRow.querySelector('[data-image-preview]');
    var count = detailRow.querySelector('.req-image-count');
    if (!input || !preview || !count) return;

    preview.querySelectorAll('.req-image-thumb.new').forEach(function (node) {
      var url = node.dataset.objectUrl;
      if (url) URL.revokeObjectURL(url);
      node.remove();
    });

    var files = Array.from(input.files || []);
    files.forEach(function (file, index) {
      var url = URL.createObjectURL(file);
      var thumb = document.createElement('div');
      thumb.className = 'req-image-thumb new';
      thumb.dataset.objectUrl = url;
      thumb.innerHTML = '<img src="' + url + '" alt="Nueva referencia">' +
        '<button type="button" class="req-image-remove-new" data-file-index="' + index + '" title="Quitar imagen"><i class="fa-solid fa-xmark"></i></button>';
      preview.appendChild(thumb);
    });

    var existingVisible = preview.querySelectorAll('.req-image-thumb.existing:not(.marked-remove)').length;
    var total = existingVisible + files.length;
    count.textContent = total ? total + (total === 1 ? ' imagen' : ' imágenes') : 'Sin imágenes';
  }

  function reqRemoveSelectedFile(detailRow, index) {
    var input = detailRow.querySelector('input[type="file"]');
    if (!input || typeof DataTransfer === 'undefined') return;
    var dt = new DataTransfer();
    Array.from(input.files || []).forEach(function (file, i) {
      if (i !== index) dt.items.add(file);
    });
    input.files = dt.files;
    reqRenderSelectedImages(detailRow);
  }

  function reqSetupDetails(mainRow, detailRow) {
    if (!mainRow || !detailRow) return;
    var toggle = mainRow.querySelector('[data-req-details-toggle]');
    var close = detailRow.querySelector('[data-req-details-close]');
    var priority = detailRow.querySelector('[name="prioridad_item[]"]');
    var date = detailRow.querySelector('[name="fecha_requerida_item[]"]');
    var fileInput = detailRow.querySelector('input[type="file"]');

    function setOpen(open) {
      detailRow.classList.toggle('hidden', !open);
      mainRow.classList.toggle('details-open', open);
      if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (toggle) toggle.addEventListener('click', function () {
      setOpen(detailRow.classList.contains('hidden'));
    });
    if (close) close.addEventListener('click', function () { setOpen(false); });
    if (priority) priority.addEventListener('change', function () { reqRefreshDetailsSummary(mainRow, detailRow); });
    if (date) date.addEventListener('change', function () { reqRefreshDetailsSummary(mainRow, detailRow); });
    if (fileInput) fileInput.addEventListener('change', function () {
      var files = Array.from(fileInput.files || []);
      if (files.length > 5 && typeof DataTransfer !== 'undefined') {
        var dt = new DataTransfer();
        files.slice(0, 5).forEach(function (file) { dt.items.add(file); });
        fileInput.files = dt.files;
      }
      reqRenderSelectedImages(detailRow);
      reqRefreshDetailsSummary(mainRow, detailRow);
    });

    detailRow.addEventListener('click', function (event) {
      var removeNew = event.target.closest('.req-image-remove-new');
      if (removeNew) {
        reqRemoveSelectedFile(detailRow, Number(removeNew.dataset.fileIndex));
        reqRefreshDetailsSummary(mainRow, detailRow);
        return;
      }
      var existingLabel = event.target.closest('.req-image-remove-existing');
      if (existingLabel) {
        window.setTimeout(function () {
          var checkbox = existingLabel.querySelector('input[type="checkbox"]');
          var thumb = existingLabel.closest('.req-image-thumb.existing');
          if (thumb) thumb.classList.toggle('marked-remove', !!(checkbox && checkbox.checked));
          reqRenderSelectedImages(detailRow);
          reqRefreshDetailsSummary(mainRow, detailRow);
        }, 0);
      }
    });

    reqRenderSelectedImages(detailRow);
    reqRefreshDetailsSummary(mainRow, detailRow);
  }

  function reqDetailRowFor(mainRow) {
    var detail = mainRow ? mainRow.nextElementSibling : null;
    return detail && detail.classList.contains("req-details-row") ? detail : null;
  }

  function reqRowHasUserData(mainRow) {
    if (!mainRow) return false;
    var detailRow = reqDetailRowFor(mainRow);
    var desc = mainRow.querySelector('[name="descripcion[]"]');
    var qty = mainRow.querySelector('[name="cantidad[]"]');
    var unit = mainRow.querySelector('[name="unidad[]"]');
    var stock = mainRow.querySelector('[name="stock[]"]');
    var just = mainRow.querySelector('[name="justificacion[]"]');

    if (desc && desc.value.trim()) return true;
    if (qty && qty.value !== "" && Math.abs((parseFloat(qty.value) || 0) - 1) > 0.000001) return true;
    if (unit && unit.value) return true;
    if (stock && stock.value !== "" && Math.abs(parseFloat(stock.value) || 0) > 0.000001) return true;
    if (just && just.value.trim()) return true;

    if (detailRow) {
      var priority = detailRow.querySelector('[name="prioridad_item[]"]');
      var requiredDate = detailRow.querySelector('[name="fecha_requerida_item[]"]');
      var imageInput = detailRow.querySelector('input[type="file"]');
      if (priority && priority.value && priority.value !== "Normal") return true;
      if (requiredDate && requiredDate.value) return true;
      if (imageInput && imageInput.files && imageInput.files.length) return true;
    }
    return false;
  }

  var reqGuardConfirmAction = null;

  function reqOpenGuardModal(options) {
    var modal = document.getElementById("reqItemGuardModal");
    if (!modal) {
      if (options && typeof options.onConfirm === "function") {
        if (window.confirm(options.message || "¿Desea continuar?")) options.onConfirm();
      }
      return;
    }
    var title = document.getElementById("reqItemGuardTitle");
    var message = document.getElementById("reqItemGuardMessage");
    var cancel = modal.querySelector("[data-req-guard-cancel]");
    var confirmBtn = modal.querySelector("[data-req-guard-confirm]");
    var okBtn = modal.querySelector("[data-req-guard-ok]");

    if (title) title.textContent = (options && options.title) || "Información ingresada";
    if (message) message.textContent = (options && options.message) || "Esta acción puede eliminar información ingresada.";
    reqGuardConfirmAction = options && typeof options.onConfirm === "function" ? options.onConfirm : null;

    if (reqGuardConfirmAction) {
      if (cancel) cancel.classList.remove("hidden");
      if (confirmBtn) {
        confirmBtn.classList.remove("hidden");
        confirmBtn.textContent = (options && options.confirmText) || "Eliminar ítem";
      }
      if (okBtn) okBtn.classList.add("hidden");
    } else {
      if (cancel) cancel.classList.add("hidden");
      if (confirmBtn) confirmBtn.classList.add("hidden");
      if (okBtn) okBtn.classList.remove("hidden");
    }
    modal.classList.remove("hidden");
  }

  function reqCloseGuardModal() {
    var modal = document.getElementById("reqItemGuardModal");
    if (modal) modal.classList.add("hidden");
    reqGuardConfirmAction = null;
  }

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-req-guard-cancel], [data-req-guard-ok]")) {
      reqCloseGuardModal();
      return;
    }
    if (event.target.closest("[data-req-guard-confirm]")) {
      var action = reqGuardConfirmAction;
      reqCloseGuardModal();
      if (action) action();
    }
  });

  function reqRemoveRowPair(mainRow, detailRow, seccion) {
    var tipo = seccion === "Ejecución de obra" ? "ejec" : (seccion === "Seguridad en obra" ? "seg" : "ofi");
    var checkboxId = tipo === "ejec" ? "chkEjec" : (tipo === "seg" ? "chkSeg" : "chkOfi");

    if (detailRow) detailRow.remove();
    mainRow.remove();

    var tbody = document.getElementById("req-tbody");
    var quedanItems = tbody && Array.from(tbody.querySelectorAll(".nt-row")).some(function (row) {
      return row.dataset.seccion === seccion;
    });

    if (!quedanItems) {
      var checkbox = document.getElementById(checkboxId);
      if (checkbox && !checkbox.disabled) {
        checkbox.checked = false;
        window.toggleSeccion(tipo, false);
        return;
      }
    }
    renumReqCompat();
    updateReqAddButtonsCompat();
  }

  function buildReqRowCompat(seccion) {
    var tr = document.createElement("tr");
    var detailRow = document.createElement("tr");
    var itemKey = "item_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 9);

    tr.className = "nt-row req-main-row";
    tr.dataset.seccion = seccion;
    detailRow.className = "req-details-row hidden";
    detailRow.dataset.reqDetailsRow = "1";

    tr.innerHTML =
      '<td class="nt-td-n"><span class="nt-num">--</span></td>' +
      '<td class="nt-td-desc">' +
      '<input type="hidden" name="seccion[]" value="' + seccion + '">' +
      '<input type="hidden" name="item_key[]" value="' + itemKey + '">' +
      '<textarea class="nt-auto-grow" name="descripcion[]" required rows="1" placeholder="Descripción del material..."></textarea>' +
      '</td>' +
      '<td class="nt-td-cant"><input type="number" step="0.01" min="0.01" name="cantidad[]" required value="1"></td>' +
      '<td class="nt-td-und"><select name="unidad[]" required>' + unitOptionsHtml(window.LANR_REQ_UNITS || [], "", "req") + '</select></td>' +
      '<td class="nt-td-stk"><input type="number" step="0.01" min="0" name="stock[]" value=""></td>' +
      '<td class="nt-td-buy"><input class="ro buy" readonly value="1"></td>' +
      '<td class="nt-td-just"><textarea class="nt-auto-grow" name="justificacion[]" rows="1" placeholder="Justificación..."></textarea></td>' +
      '<td class="nt-td-details">' +
        '<button type="button" class="req-details-btn" data-req-details-toggle aria-expanded="false" title="Opcional: aquí puede indicar prioridad, fecha requerida e imágenes de referencia."><i class="fa-solid fa-sliders"></i><span>Detalles</span></button>' +
        '<div class="req-details-summary" aria-live="polite"></div>' +
      '</td>' +
      '<td class="nt-td-del"><div class="req-row-actions"><button type="button" class="nt-add-inline" title="Agregar ítem debajo" aria-label="Agregar ítem debajo"><i class="fa-solid fa-plus"></i></button><button type="button" class="nt-del" title="Eliminar ítem" aria-label="Eliminar ítem"><i class="fa-solid fa-xmark"></i></button></div></td>';

    detailRow.innerHTML =
      '<td colspan="9"><div class="req-details-panel">' +
        '<div class="req-details-heading"><div><strong>Información interna y referencias</strong>' +
        '<span>Complete solo lo necesario. Prioridad y fecha no se imprimen en el PDF.</span></div>' +
        '<button type="button" class="req-details-close" data-req-details-close aria-label="Cerrar detalles"><i class="fa-solid fa-xmark"></i></button></div>' +
        '<div class="req-details-grid">' +
          '<div class="req-detail-field"><label>Prioridad</label>' +
            '<select name="prioridad_item[]" class="req-item-priority"><option value="Normal" selected>Normal</option><option value="Prioritario">Prioritario</option><option value="Urgente">Urgente</option></select>' +
            '<small>Normal por defecto. Cámbiala solo si corresponde.</small></div>' +
          '<div class="req-detail-field"><label title="Opcional. Fecha máxima en la que se necesita el material.">Fecha requerida <span>(opcional)</span></label>' +
            '<input type="date" name="fecha_requerida_item[]" value="" autocomplete="off">' +
            '<small>Si queda vacía, no se guarda ninguna fecha.</small></div>' +
          '<div class="req-detail-field req-detail-images"><label title="Opcional. Adjunte imágenes que ayuden a identificar el material.">Imágenes de referencia <span>(opcional)</span></label>' +
            '<label class="req-image-btn"><i class="fa-regular fa-images"></i> Adjuntar imágenes' +
            '<input type="file" name="imagenes_' + itemKey + '" accept="image/jpeg,image/png,image/webp" multiple hidden></label>' +
            '<span class="req-image-count">Sin imágenes</span><div class="req-image-preview" data-image-preview></div>' +
            '<small>JPG, PNG o WEBP. Máximo 5 imágenes por ítem.</small></div>' +
        '</div>' +
      '</div></td>';

    tr._detailRow = detailRow;

    var qty = tr.querySelector('[name="cantidad[]"]');
    var stock = tr.querySelector('[name="stock[]"]');
    var del = tr.querySelector(".nt-del");
    var addInline = tr.querySelector(".nt-add-inline");

    function calc() {
      var q = parseFloat(qty.value) || 0;
      var st = parseFloat(stock.value) || 0;
      var comprar = tr.querySelector(".buy");
      comprar.value = Math.max(q - st, 0);
    }
    qty.addEventListener("input", calc);
    stock.addEventListener("input", calc);
    if (addInline) addInline.addEventListener("click", function () {
      window.addReqRowAfter(tr);
    });
    del.addEventListener("click", function () {
      if (reqRowHasUserData(tr)) {
        reqOpenGuardModal({
          title: "Eliminar ítem",
          message: "Este ítem contiene información ingresada. ¿Desea eliminarlo? Esta acción quitará también sus detalles e imágenes seleccionadas que aún no se hayan guardado.",
          confirmText: "Eliminar ítem",
          onConfirm: function () { reqRemoveRowPair(tr, detailRow, seccion); }
        });
        return;
      }
      reqRemoveRowPair(tr, detailRow, seccion);
    });
    tr.querySelectorAll("textarea.nt-auto-grow").forEach(function (ta) {
      ta.addEventListener("input", function () {
        this.style.height = "auto";
        this.style.height = this.scrollHeight + "px";
      });
    });

    reqSetupDetails(tr, detailRow);
    window.LANR_setupReqDetails = reqSetupDetails;
    window.LANR_refreshReqDetailsSummary = reqRefreshDetailsSummary;
    return tr;
  }

  function renumReqCompat() {
    var tbody = document.getElementById("req-tbody");

    if (!tbody) return;

    tbody.querySelectorAll(".nt-row").forEach(function (row, index) {
      var numero = row.querySelector(".nt-num");

      if (numero) {
        numero.textContent = String(index + 1).padStart(2, "0");
      }
    });
  }

  function ensureReqHeader(tipo) {
    var tbody = document.getElementById("req-tbody");

    if (!tbody) return null;

    var existing = tbody.querySelector(
      '.nt-sec-row[data-header="' + tipo + '"]',
    );

    if (existing) {
      return existing;
    }

    var header = reqHeader(tipo);

    var order = { ejec: 1, seg: 2, ofi: 3 };
    var nextHeader = Array.from(tbody.querySelectorAll(".nt-sec-row")).find(function (h) {
      return (order[h.dataset.header] || 99) > (order[tipo] || 99);
    });

    if (nextHeader) {
      tbody.insertBefore(header, nextHeader);
    } else {
      tbody.appendChild(header);
    }

    return header;
  }

  window.addReqRowToSec = function (seccion) {
    var tbody = document.getElementById("req-tbody");

    if (!tbody) return;

    var tipo = seccion === "Ejecución de obra" ? "ejec" : (seccion === "Seguridad en obra" ? "seg" : "ofi");

    ensureReqHeader(tipo);

    var tr = buildReqRowCompat(seccion);
    var order = { ejec: 1, seg: 2, ofi: 3 };
    var nextHeader = Array.from(tbody.querySelectorAll(".nt-sec-row")).find(function (h) {
      return (order[h.dataset.header] || 99) > (order[tipo] || 99);
    });

    if (nextHeader) {
      tbody.insertBefore(tr, nextHeader);
      if (tr._detailRow) tbody.insertBefore(tr._detailRow, nextHeader);
    } else {
      tbody.appendChild(tr);
      if (tr._detailRow) tbody.appendChild(tr._detailRow);
    }

    renumReqCompat();

    var descripcion = tr.querySelector('[name="descripcion[]"]');

    if (descripcion) {
      descripcion.focus();
    }
  };

  window.addReqRowAfter = function (mainRow) {
    var tbody = document.getElementById("req-tbody");
    if (!tbody || !mainRow) return;
    var seccion = mainRow.dataset.seccion;
    if (!seccion) return;
    var tr = buildReqRowCompat(seccion);
    var currentDetail = reqDetailRowFor(mainRow);
    var reference = currentDetail || mainRow;
    var nextNode = reference.nextSibling;
    tbody.insertBefore(tr, nextNode);
    if (tr._detailRow) tbody.insertBefore(tr._detailRow, nextNode);
    renumReqCompat();
    var descripcion = tr.querySelector('[name="descripcion[]"]');
    if (descripcion) descripcion.focus();
  };

  function updateReqAddButtonsCompat() {
    var area = document.getElementById("req-add-area");

    if (!area) return;

    area.innerHTML = "";

    var activos = [];
    if (window.secActive.ejec) activos.push({ tipo: "ejec", label: "Ejecución", clase: "btn-ejec" });
    if (window.secActive.seg) activos.push({ tipo: "seg", label: "Seguridad", clase: "btn-seg" });
    if (window.secActive.ofi) activos.push({ tipo: "ofi", label: "Oficina", clase: "nt-add-row" });

    if (activos.length > 1) {
      var wrap = document.createElement("div");
      wrap.className = "nt-add-split";
      activos.forEach(function (item) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = item.clase;
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Agregar a ' + item.label;
        btn.addEventListener("click", function () {
          window.addReqRowToSec(reqSectionName(item.tipo));
        });
        wrap.appendChild(btn);
      });
      area.appendChild(wrap);
    } else if (activos.length === 1) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "nt-add-row";
      btn.innerHTML = '<i class="fa-solid fa-plus"></i> Agregar a ' + activos[0].label;
      btn.addEventListener("click", function () {
        window.addReqRowToSec(reqSectionName(activos[0].tipo));
      });
      area.appendChild(btn);
    }
  }

  /* =========================================================
     ACTIVAR / DESACTIVAR SECCIÓN
  ========================================================= */

  window.toggleSeccion = function (tipo, on) {
    var tableSection = document.getElementById("sec-req-tabla");

    var tbody = document.getElementById("req-tbody");

    if (!tableSection || !tbody) {
      return;
    }

    if (!on) {
      var seccionIntento = reqSectionName(tipo);
      var filasSeccion = Array.from(tbody.querySelectorAll(".nt-row")).filter(function (row) {
        return row.dataset.seccion === seccionIntento;
      });
      var contieneDatos = filasSeccion.some(reqRowHasUserData);
      if (contieneDatos) {
        var checkboxIntento = document.getElementById(tipo === "ejec" ? "chkEjec" : (tipo === "seg" ? "chkSeg" : "chkOfi"));
        if (checkboxIntento) checkboxIntento.checked = true;
        window.secActive[tipo] = true;
        tableSection.classList.remove("hidden");
        reqOpenGuardModal({
          title: "No se puede quitar esta sección",
          message: "Esta sección contiene información ingresada. Para evitar pérdidas accidentales, elimine los ítems con la X. Si un ítem contiene datos, el sistema solicitará confirmación antes de eliminarlo."
        });
        return;
      }
    }

    window.secActive[tipo] = !!on;

    // Si ya seleccionó al menos un tipo de requerimiento,
    // quitar inmediatamente el aviso amarillo.
    if (window.secActive.ejec || window.secActive.seg || window.secActive.ofi) {
      var avisoTipo = document.getElementById("reqTypeWarning");

      if (avisoTipo) {
        avisoTipo.remove();
      }
    }

    tableSection.classList.toggle(
      "hidden",
      !(window.secActive.ejec || window.secActive.seg || window.secActive.ofi),
    );

    /* ======================================
         ACTIVAR
      ====================================== */

    if (on) {
      ensureReqHeader(tipo);

      var seccion = reqSectionName(tipo);

      var hasRow = Array.from(tbody.querySelectorAll(".nt-row")).some(
        function (row) {
          return row.dataset.seccion === seccion;
        },
      );

      if (!hasRow) {
        window.addReqRowToSec(seccion);
      }
    } else {
      /* ======================================
         DESACTIVAR
      ====================================== */
      var seccionOff = reqSectionName(tipo);

      tbody.querySelectorAll(".nt-row").forEach(function (row) {
        if (row.dataset.seccion === seccionOff) {
          var detail = row.nextElementSibling;
          if (detail && detail.classList.contains("req-details-row")) detail.remove();
          row.remove();
        }
      });

      var header = tbody.querySelector(
        '.nt-sec-row[data-header="' + tipo + '"]',
      );

      if (header) {
        header.remove();
      }

      renumReqCompat();
    }

    updateReqAddButtonsCompat();
  };

  /* =========================================================
     SOLICITUD DE PAGO
  ========================================================= */

  function renumSPCompat() {
    var tbody = document.getElementById("sp-tbody");

    if (!tbody) return;

    tbody.querySelectorAll(".nt-row").forEach(function (row, index) {
      var numero = row.querySelector(".nt-num");

      if (numero) {
        numero.textContent = index + 1;
      }
    });
  }

  window.spAddRow = function () {
    var tbody = document.getElementById("sp-tbody");

    if (!tbody) return;

    var tr = document.createElement("tr");

    tr.className = "nt-row";

    tr.innerHTML =
      '<td class="nt-td-n">' +
      '<span class="nt-num">1</span>' +
      "</td>" +
      '<td class="nt-td-conc">' +
      "<textarea " +
      'class="nt-auto-grow" ' +
      'name="concepto[]" ' +
      'rows="1" ' +
      'placeholder="Concepto o servicio...">' +
      "</textarea>" +
      "</td>" +
      '<td class="nt-td-unsp">' +
      '<select name="unidad_sp[]" required>' + unitOptionsHtml(window.LANR_SP_UNITS || [], "", "sp") + '</select>' +
      "</td>" +
      '<td class="nt-td-qsp">' +
      "<input " +
      'type="number" ' +
      'step="0.01" ' +
      'min="0.01" ' +
      'name="cantidad_sp[]" ' +
      'value="1">' +
      "</td>" +
      '<td class="nt-td-cost">' +
      "<input " +
      'type="number" ' +
      'step="0.01" ' +
      'min="0" ' +
      'name="costo[]" ' +
      'value="">' +
      "</td>" +
      '<td class="nt-td-mnt">' +
      "<input " +
      'class="ro monto" ' +
      "readonly " +
      'value="0.00">' +
      "</td>" +
      '<td class="nt-td-del">' +
      "<button " +
      'type="button" ' +
      'class="nt-del" ' +
      'title="Eliminar">' +
      '<i class="fa-solid fa-xmark"></i>' +
      "</button>" +
      "</td>";

    var qty = tr.querySelector('[name="cantidad_sp[]"]');

    var cost = tr.querySelector('[name="costo[]"]');

    function calc() {
      var cantidad = parseFloat(qty.value) || 0;

      var costo = parseFloat(cost.value) || 0;

      tr.querySelector(".monto").value = (cantidad * costo).toFixed(2);
    }

    qty.addEventListener("input", calc);

    cost.addEventListener("input", calc);

    tr.querySelector(".nt-del").addEventListener("click", function () {
      tr.remove();

      renumSPCompat();
    });

    var textarea = tr.querySelector("textarea");

    textarea.addEventListener("input", function () {
      this.style.height = "auto";

      this.style.height = this.scrollHeight + "px";
    });

    tbody.appendChild(tr);

    renumSPCompat();

    textarea.focus();
  };

  /* =========================================================
     CAMBIO DE TIPO DE SOLICITUD
  ========================================================= */

  window.toggleSP = function (value) {
    var common = document.getElementById("spCommon");

    if (common) {
      common.classList.toggle("hidden", !value);
    }

    document.querySelectorAll(".thirdOnly").forEach(function (element) {
      element.classList.toggle("hidden", value !== "Persona/Empresa");
    });

    document.querySelectorAll(".planOnly").forEach(function (element) {
      element.classList.toggle("hidden", value !== "Planilla");
    });

    var tbody = document.getElementById("sp-tbody");

    if (value && tbody && tbody.querySelectorAll(".nt-row").length === 0) {
      window.spAddRow();
    }
  };
})();

function addSpEvidence(button) {
  const field = button.closest(".field");
  const list = field.querySelector(".sp-evidence-list");

  const row = document.createElement("div");
  row.className = "sp-evidence-row";

  row.innerHTML = `
    <input
      type="file"
      name="comprobante_pago"
      accept=".pdf,.jpg,.jpeg,.png,.webp"
      required
    />

    <button
      type="button"
      class="btn"
      onclick="removeSpEvidence(this)"
    >
      <i class="fa-solid fa-xmark"></i>
    </button>
  `;

  list.appendChild(row);
}

function removeSpEvidence(button) {
  const row = button.closest(".sp-evidence-row");
  const list = row.closest(".sp-evidence-list");

  if (list.children.length === 1) {
    const input = row.querySelector('input[type="file"]');

    if (input) {
      input.value = "";
    }

    return;
  }

  row.remove();
}

document.addEventListener("DOMContentLoaded", function () {
  const reqForm = document.getElementById("reqForm");

  if (!reqForm) return;

  reqForm.addEventListener("submit", function (e) {
    const chkEjec = document.getElementById("chkEjec");
    const chkSeg = document.getElementById("chkSeg");
    const chkOfi = document.getElementById("chkOfi");

    const ejecSeleccionado = chkEjec && chkEjec.checked;
    const segSeleccionado = chkSeg && chkSeg.checked;
    const ofiSeleccionado = chkOfi && chkOfi.checked;

    /* SOLO mostrar el aviso amarillo
       si NO escogió ninguna sección */
    if (!ejecSeleccionado && !segSeleccionado && !ofiSeleccionado) {
      e.preventDefault();

      let aviso = document.getElementById("reqTypeWarning");

      if (!aviso) {
        aviso = document.createElement("div");
        aviso.id = "reqTypeWarning";

        aviso.style.marginTop = "10px";
        aviso.style.padding = "11px 14px";
        aviso.style.border = "1px solid #e4c46a";
        aviso.style.borderRadius = "9px";
        aviso.style.background = "#fff8df";
        aviso.style.color = "#805d00";
        aviso.style.fontSize = "11px";
        aviso.style.display = "flex";
        aviso.style.alignItems = "center";
        aviso.style.gap = "8px";

        aviso.innerHTML = `
          <i class="fa-solid fa-triangle-exclamation"></i>

          <span>
            Seleccione al menos un <strong>tipo de requerimiento</strong> para continuar.
          </span>
        `;

        const opciones = reqForm.querySelector(".req-options");

        if (opciones) {
          opciones.insertAdjacentElement("afterend", aviso);
        }
      }

      aviso.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });

      return;
    }

    /* Ya eligió tipo:
       quitar el aviso amarillo */
    const aviso = document.getElementById("reqTypeWarning");

    if (aviso) {
      aviso.remove();
    }

    /* De aquí en adelante dejamos trabajar
       la validación normal del navegador */
  });
});


/* =========================================================
   requests.js - consolidado en app.js
   ========================================================= */
(() => {
  'use strict';

  const unitData = document.getElementById('requestUnitData');
  if (unitData) {
    try {
      window.LANR_REQ_UNITS = JSON.parse(unitData.dataset.reqUnits || '[]');
      window.LANR_SP_UNITS = JSON.parse(unitData.dataset.spUnits || '[]');
    } catch (error) {
      window.LANR_REQ_UNITS = [];
      window.LANR_SP_UNITS = [];
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const officeOnly = document.querySelector('.req-options[data-office-only="1"]');
    if (!officeOnly || typeof window.toggleSeccion !== 'function') return;

    // La sección Oficina es la única permitida para Administración,
    // Logística y Tesorería. Se activa sin exigir otro clic al usuario.
    window.toggleSeccion('ofi', true);
  });
})();



/* =========================================================
   units.js - consolidado en app.js
   ========================================================= */
(() => {
  'use strict';

  let pendingFormId = null;

  const getModal = (id) => document.getElementById(id);

  function openModal(id) {
    const modal = getModal(id);
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.classList.add('units-modal-open');

    if (id === 'new-unit-modal') {
      const input = document.getElementById('new-unit-abbr');
      if (input) {
        window.setTimeout(() => input.focus(), 40);
      }
    }
  }

  function closeModal(id) {
    const modal = getModal(id);
    if (!modal) return;

    modal.classList.add('hidden');

    if (!document.querySelector('.units-modal:not(.hidden)')) {
      document.body.classList.remove('units-modal-open');
    }
  }

  function openEditModal(button) {
    const form = document.getElementById('edit-unit-form');
    const abbr = document.getElementById('edit-unit-abbr');
    const useSelect = document.getElementById('edit-unit-uso');

    if (!form || !abbr || !useSelect) return;

    form.action = button.dataset.action || '';
    abbr.value = button.dataset.abreviatura || '';
    useSelect.value = button.dataset.uso || 'REQ';

    openModal('edit-unit-modal');

    window.setTimeout(() => {
      abbr.focus();
      abbr.select();
    }, 50);
  }

  function openConfirmModal(button) {
    pendingFormId = button.dataset.formId || null;

    const type = button.dataset.confirmType || '';
    const unit = button.dataset.unit || 'esta unidad';

    const title = document.getElementById('unit-confirm-title');
    const text = document.getElementById('unit-confirm-text');
    const note = document.getElementById('unit-confirm-note');
    const submit = document.getElementById('unit-confirm-submit');

    if (!title || !text || !note || !submit) return;

    note.classList.add('hidden');
    note.textContent = '';
    submit.className = 'btn primary';

    if (type === 'delete') {
      title.textContent = 'Eliminar unidad';
      text.textContent = `¿Deseas eliminar ${unit}?`;
      note.textContent = 'Si ya fue utilizada, se conservará en el historial y quedará desactivada.';
      note.classList.remove('hidden');
      submit.className = 'btn units-confirm-danger';
      submit.innerHTML = '<i class="fa-solid fa-trash"></i> Eliminar';
    } else if (type === 'deactivate') {
      title.textContent = 'Desactivar unidad';
      text.textContent = `¿Deseas desactivar ${unit}?`;
      note.textContent = 'Dejará de aparecer en nuevos trámites, pero se conservará en registros anteriores.';
      note.classList.remove('hidden');
      submit.innerHTML = '<i class="fa-solid fa-ban"></i> Desactivar';
    } else {
      title.textContent = 'Activar unidad';
      text.textContent = `¿Deseas activar ${unit}?`;
      submit.innerHTML = '<i class="fa-solid fa-circle-check"></i> Activar';
    }

    openModal('unit-confirm-modal');
  }

  document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-modal-open]');
    if (openButton) {
      openModal(openButton.dataset.modalOpen);
      return;
    }

    const closeButton = event.target.closest('[data-modal-close]');
    if (closeButton) {
      closeModal(closeButton.dataset.modalClose);
      return;
    }

    const editButton = event.target.closest('.js-unit-edit');
    if (editButton) {
      openEditModal(editButton);
      return;
    }

    const confirmButton = event.target.closest('.js-unit-confirm');
    if (confirmButton) {
      openConfirmModal(confirmButton);
    }
  });

  const confirmSubmit = document.getElementById('unit-confirm-submit');
  if (confirmSubmit) {
    confirmSubmit.addEventListener('click', () => {
      const form = pendingFormId ? document.getElementById(pendingFormId) : null;
      if (form) form.submit();
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const visibleModal = document.querySelector('.units-modal:not(.hidden)');
    if (visibleModal) closeModal(visibleModal.id);
  });
})();



/* =========================================================
   change_password.html - JS movido desde HTML
   ========================================================= */
document.addEventListener("DOMContentLoaded", function () {
    const newPassword = document.getElementById("new");

    const confirmPassword = document.getElementById("confirm");

    if (!newPassword || !confirmPassword) return;

    const rules = {
      length: document.getElementById("ruleLength"),

      upper: document.getElementById("ruleUpper"),

      lower: document.getElementById("ruleLower"),

      number: document.getElementById("ruleNumber"),

      special: document.getElementById("ruleSpecial"),

      match: document.getElementById("ruleMatch"),
    };

    function setRule(element, valid) {
      if (!element) {
        return;
      }

      element.classList.toggle("valid", valid);

      const icon = element.querySelector("i");

      if (!icon) {
        return;
      }

      icon.className = valid
        ? "fa-solid fa-circle-check"
        : "fa-solid fa-circle";
    }

    function validatePassword() {
      const password = newPassword.value;

      const confirmation = confirmPassword.value;

      setRule(rules.length, password.length >= 8 && password.length <= 64);

      setRule(rules.upper, /[A-Z]/.test(password));

      setRule(rules.lower, /[a-z]/.test(password));

      setRule(rules.number, /\d/.test(password));

      setRule(rules.special, /[^A-Za-z0-9]/.test(password));

      setRule(
        rules.match,
        password.length > 0 &&
          confirmation.length > 0 &&
          password === confirmation,
      );
    }

    newPassword.addEventListener("input", validatePassword);

    confirmPassword.addEventListener("input", validatePassword);
  });


/* =========================================================
   reset_password.html - JS movido desde HTML
   ========================================================= */
document.addEventListener("DOMContentLoaded", function () {
    const newPassword = document.getElementById("new_password");

    const confirmPassword = document.getElementById("confirm_password");

    if (!newPassword || !confirmPassword) return;

    const rules = {
      length: document.getElementById("ruleLength"),

      upper: document.getElementById("ruleUpper"),

      lower: document.getElementById("ruleLower"),

      number: document.getElementById("ruleNumber"),

      special: document.getElementById("ruleSpecial"),

      match: document.getElementById("ruleMatch"),
    };

    function setRule(element, valid) {
      if (!element) {
        return;
      }

      element.classList.toggle("valid", valid);

      const icon = element.querySelector("i");

      if (!icon) {
        return;
      }

      icon.className = valid
        ? "fa-solid fa-circle-check"
        : "fa-solid fa-circle";
    }

    function validatePassword() {
      const password = newPassword.value;

      const confirmation = confirmPassword.value;

      setRule(rules.length, password.length >= 8 && password.length <= 64);

      setRule(rules.upper, /[A-Z]/.test(password));

      setRule(rules.lower, /[a-z]/.test(password));

      setRule(rules.number, /\d/.test(password));

      setRule(rules.special, /[^A-Za-z0-9]/.test(password));

      setRule(
        rules.match,
        password.length > 0 &&
          confirmation.length > 0 &&
          password === confirmation,
      );
    }

    newPassword.addEventListener("input", validatePassword);

    confirmPassword.addEventListener("input", validatePassword);
  });


/* =========================================================
   profile.html - JS movido desde HTML
   ========================================================= */
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("profileForm");

    if (!form) return;

    const editButton = document.getElementById("profileEditButton");

    const editActions = document.getElementById("profileEditActions");

    const cancelButton = document.getElementById("profileCancelButton");

    const editableFields = document.querySelectorAll(".profile-editable");

    const photoInput = document.getElementById("profilePhotoInput");

    const photoButton = document.getElementById("profilePhotoButton");

    const photoOptions = document.getElementById("profilePhotoOptions");

    const removePhotoInput = document.getElementById("removePhotoInput");

    const removePhotoButton = document.getElementById(
      "profileRemovePhotoButton",
    );

    const photoPreview = document.getElementById("profilePhotoPreview");

    const fullNameInput = document.getElementById("profileFullName");

    const displayName = document.getElementById("profileDisplayName");

    /* =====================================================
     ESTADO ORIGINAL
  ====================================================== */

    const originalValues = {};

    editableFields.forEach(function (field) {
      originalValues[field.id] = field.value;
    });

    const originalPhotoHTML = photoPreview.innerHTML;

    const originalDisplayName = displayName.textContent;

    const userHasOriginalPhoto = form.dataset.hasPhoto === "1";

    /* =====================================================
     MOSTRAR / OCULTAR QUITAR FOTO
  ====================================================== */

    function updateRemovePhotoButton() {
      const hasNewPhoto = photoInput.files.length > 0;

      const originalStillExists =
        userHasOriginalPhoto && removePhotoInput.value !== "1";

      removePhotoButton.hidden = !(hasNewPhoto || originalStillExists);
    }

    /* =====================================================
     INICIAL
  ====================================================== */

    function getInitial() {
      const name = fullNameInput.value.trim();

      if (!name) {
        return "?";
      }

      return name.charAt(0).toUpperCase();
    }

    function showInitial() {
      photoPreview.innerHTML = "";

      const span = document.createElement("span");

      span.textContent = getInitial();

      photoPreview.appendChild(span);
    }

    /* =====================================================
     ACTIVAR EDICIÓN
  ====================================================== */

    function enableEditMode() {
      editableFields.forEach(function (field) {
        field.disabled = false;
      });

      photoInput.disabled = false;

      photoButton.classList.add("visible");

      photoOptions.classList.add("visible");

      editButton.hidden = true;

      editActions.hidden = false;

      updateRemovePhotoButton();

      fullNameInput.focus();
    }

    /* =====================================================
     CANCELAR
  ====================================================== */

    function cancelEditMode() {
      editableFields.forEach(function (field) {
        field.value = originalValues[field.id];

        field.disabled = true;
      });

      photoInput.value = "";

      photoInput.disabled = true;

      removePhotoInput.value = "0";

      photoPreview.innerHTML = originalPhotoHTML;

      displayName.textContent = originalDisplayName;

      photoButton.classList.remove("visible");

      photoOptions.classList.remove("visible");

      editActions.hidden = true;

      editButton.hidden = false;
    }

    /* =====================================================
     NUEVA FOTO
  ====================================================== */

    photoInput.addEventListener("change", function () {
      const file = this.files[0];

      if (!file) {
        return;
      }

      removePhotoInput.value = "0";

      const reader = new FileReader();

      reader.onload = function (event) {
        photoPreview.innerHTML = "";

        const image = document.createElement("img");

        image.src = event.target.result;

        image.alt = "Vista previa de foto";

        photoPreview.appendChild(image);

        updateRemovePhotoButton();
      };

      reader.readAsDataURL(file);
    });

    /* =====================================================
     QUITAR FOTO
  ====================================================== */

    removePhotoButton.addEventListener("click", function () {
      photoInput.value = "";

      if (userHasOriginalPhoto) {
        removePhotoInput.value = "1";
      } else {
        removePhotoInput.value = "0";
      }

      showInitial();

      updateRemovePhotoButton();
    });

    /* =====================================================
     ACTUALIZAR NOMBRE
  ====================================================== */

    fullNameInput.addEventListener("input", function () {
      const name = this.value.trim();

      displayName.textContent = name || "Usuario";

      const hasNewPhoto = photoInput.files.length > 0;

      const photoRemoved = removePhotoInput.value === "1";

      if (photoRemoved || (!userHasOriginalPhoto && !hasNewPhoto)) {
        showInitial();
      }
    });

    /* =====================================================
     BOTONES
  ====================================================== */

    editButton.addEventListener("click", enableEditMode);

    cancelButton.addEventListener("click", cancelEditMode);

    /* =====================================================
     ENVIAR
  ====================================================== */

    form.addEventListener("submit", function () {
      editableFields.forEach(function (field) {
        field.disabled = false;
      });

      photoInput.disabled = false;
    });
  });


/* =========================================================
   users.html - JS movido desde HTML
   ========================================================= */
function openUserModal(id) {

  const modal = document.getElementById(id);

  if (!modal) {
    return;
  }

  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');

  document.body.classList.add('modal-open');
}


function closeUserModal(id) {

  const modal = document.getElementById(id);

  if (!modal) {
    return;
  }

  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');

  document.body.classList.remove('modal-open');
}


document.addEventListener('keydown', function(event) {

  if (event.key !== 'Escape') {
    return;
  }

  const modal = document.querySelector(
    '.user-modal.open'
  );

  if (modal) {

    modal.classList.remove('open');
    modal.setAttribute(
      'aria-hidden',
      'true'
    );

    document.body.classList.remove(
      'modal-open'
    );
  }

});


/* =========================================================
   treasury_payment_detail.html - JS movido desde HTML
   ========================================================= */
function addEvidence() {
    const list = document.getElementById("evidenceList");
    const row = document.createElement("div");
    row.className = "evidence-row";
    row.innerHTML =
      '<input type="file" name="comprobante_pago" accept=".pdf,.jpg,.jpeg,.png,.webp" required><button type="button" class="remove-evidence" onclick="removeEvidence(this)" title="Quitar">×</button>';
    list.appendChild(row);
  }
  function removeEvidence(btn) {
    const list = document.getElementById("evidenceList");
    if (list.children.length === 1) {
      const input = btn.parentElement.querySelector("input");
      input.value = "";
      return;
    }
    btn.parentElement.remove();
  }


/* =========================================================
   requests.html - JS movido desde HTML
   ========================================================= */
if (document.getElementById("requestSearchV2")) {
(function(){
 const tipo=document.getElementById('tramiteTipoV2'), campo=document.getElementById('buscarPor'), q=document.getElementById('requestSearchV2');
 const rows=Array.from(document.querySelectorAll('.tramite-row')), empty=document.getElementById('requestsEmptyRow');
 const tabs=Array.from(document.querySelectorAll('.tramite-status-tab'));
 const note=document.getElementById('tramiteTabNote');
 const n=v=>(v||'').toString().trim().toLowerCase();
 let flujoActivo='pendientes';

 const notas={
   pendientes:'Aquí aparecen solo los trámites que creaste y todavía no has enviado.',
   proceso:'Trámites que ya fueron enviados y están siendo revisados o atendidos.',
   finalizados:'Trámites que ya finalizaron, fueron atendidos o anulados.',
   todos:'Todos los trámites visibles para ti en la obra activa.'
 };

 function actualizarContadores(){
   const counts={pendientes:0,proceso:0,finalizados:0,todos:rows.length};
   rows.forEach(row=>{ const f=row.dataset.flujo||'proceso'; if(counts[f]!==undefined) counts[f]++; });
   const map={pendientes:'countPendientes',proceso:'countProceso',finalizados:'countFinalizados',todos:'countTodos'};
   Object.keys(map).forEach(k=>{ const el=document.getElementById(map[k]); if(el) el.textContent=counts[k]; });
   // Si no hay pendientes, abrir En proceso para no mostrar una pantalla vacía al entrar.
   if(counts.pendientes===0 && counts.proceso>0) flujoActivo='proceso';
 }

 function filtrar(){
  const tv=n(tipo.value);
  const text=n(q.value).replace(/\D/g,'');
  let visible=0;

  rows.forEach(row=>{
    const tipoFila=n(row.dataset.tipo);
    const okTipo=!tv || tipoFila===tv;
    const okFlujo=flujoActivo==='todos' || row.dataset.flujo===flujoActivo;

    const seg=n(row.dataset.seguimiento);
    const num=n(row.dataset.numero);
    const segCorto=(seg.split('-').pop()||'').replace(/\D/g,'');
    const partesNumero=num.split('-');
    let formatoCorto='';
    if(tipoFila==='req'){
      formatoCorto=(partesNumero[0]||'').replace(/\D/g,'');
    }else if(tipoFila==='sp'){
      formatoCorto=(partesNumero[partesNumero.length-1]||'').replace(/\D/g,'');
    }

    let ok=true;
    if(text){
      if(campo.value==='seguimiento'){
        ok=segCorto===text.padStart(3,'0');
      }else if(campo.value==='formato'){
        ok=formatoCorto===text;
      }else{
        ok=segCorto===text.padStart(3,'0') || formatoCorto===text;
      }
    }

    const show=okFlujo && okTipo && ok;
    row.style.display=show?'':'none';
    if(show) visible++;
  });

  empty.style.display=visible===0?'':'none';
  tabs.forEach(tab=>tab.classList.toggle('active',tab.dataset.flujo===flujoActivo));
  if(note) note.textContent=notas[flujoActivo]||'';
 }

 const btn=document.getElementById('btnBuscarTramite');
 const help=document.getElementById('searchHelp');

 function ayuda(){
   if(campo.value==='seguimiento'){
     help.textContent='Ingrese los 3 últimos dígitos. Ej.: 001';
   }else if(campo.value==='formato'){
     help.textContent='Ingrese el correlativo del formato. Ej.: 43';
   }else{
     help.textContent='Ingrese el correlativo que desea buscar.';
   }
 }

 function ejecutarBusqueda(){
   q.value=q.value.replace(/\D/g,'');
   if(campo.value==='seguimiento' && q.value && q.value.length!==3){
     help.textContent='Ingrese los 3 dígitos del código de seguimiento. Ej.: 001';
     return;
   }
   filtrar();
 }

 tabs.forEach(tab=>tab.addEventListener('click',function(){
   flujoActivo=this.dataset.flujo||'todos';
   filtrar();
 }));
 q.addEventListener('input',function(){ q.value=q.value.replace(/\D/g,''); });
 q.addEventListener('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();ejecutarBusqueda();} });
 btn.addEventListener('click',ejecutarBusqueda);
 tipo.addEventListener('change',function(){ q.value=''; filtrar(); ayuda(); });
 campo.addEventListener('change',function(){ q.value=''; filtrar(); ayuda(); });
 actualizarContadores();
 ayuda();
 filtrar();
})();
}


/* =========================================================
   edit_request.html - JS movido desde HTML
   ========================================================= */
(function(){
  const data = document.getElementById("editRequestUnitData");
  if (!data) return;
  try {
    window.LANR_REQ_UNITS = JSON.parse(data.dataset.reqUnits || "[]");
    window.LANR_SP_UNITS = JSON.parse(data.dataset.spUnits || "[]");
  } catch (error) {
    window.LANR_REQ_UNITS = [];
    window.LANR_SP_UNITS = [];
  }
})();

function editGrow(el) {
  if (!el) return;
  el.style.height = "32px";
  var max = 150;
  var h = Math.min(el.scrollHeight, max);
  el.style.height = h + "px";
  el.style.overflowY = el.scrollHeight > max ? "auto" : "hidden";
}

function editGrowAll() {
  document.querySelectorAll(".edit-auto").forEach(editGrow);
}

document.addEventListener("input", function (e) {
  if (e.target.matches(".edit-auto")) editGrow(e.target);
});

function editRenumberReq() {
  document.querySelectorAll("#editReqBody .nt-row").forEach(function (row, idx) {
    row.querySelector(".nt-num").textContent = String(idx + 1).padStart(2, "0");
  });
}

function editUnitOptions(units) {
  var html = '<option value="">Unidad</option>';
  (units || []).forEach(function (u) {
    html += '<option value="' + u + '">' + u + '</option>';
  });
  return html;
}

function editReqOrder(section) {
  if (section === "Ejecución de obra") return 1;
  if (section === "Seguridad en obra") return 2;
  return 3;
}

function editEnsureHeader(section) {
  var tbody = document.getElementById("editReqBody");
  if (!tbody || tbody.querySelector('[data-edit-header="' + section + '"]')) return;

  var tr = document.createElement("tr");
  var cls = section === "Ejecución de obra" ? "ejec" : (section === "Seguridad en obra" ? "seg" : "ofi");
  var icon = section === "Ejecución de obra" ? "fa-helmet-safety" : (section === "Seguridad en obra" ? "fa-shield-halved" : "fa-paperclip");
  tr.className = "nt-sec-row " + cls;
  tr.dataset.editHeader = section;
  tr.innerHTML = '<td colspan="9"><i class="fa-solid ' + icon + '"></i>&nbsp; ' + section + '</td>';

  var nextHeader = Array.from(tbody.querySelectorAll("[data-edit-header]")).find(function (h) {
    return editReqOrder(h.dataset.editHeader) > editReqOrder(section);
  });
  if (nextHeader) tbody.insertBefore(tr, nextHeader);
  else tbody.appendChild(tr);
}

function setupExistingReqDetailPair(mainRow, detailRow) {
  if (window.LANR_setupReqDetails) {
    window.LANR_setupReqDetails(mainRow, detailRow);
    return;
  }
  var toggle = mainRow && mainRow.querySelector('[data-req-details-toggle]');
  var close = detailRow && detailRow.querySelector('[data-req-details-close]');
  if (toggle && detailRow) toggle.addEventListener('click', function () { detailRow.classList.toggle('hidden'); });
  if (close && detailRow) close.addEventListener('click', function () { detailRow.classList.add('hidden'); });
}

function initEditReqDetails() {
  var tbody = document.getElementById('editReqBody');
  if (!tbody) return;
  tbody.querySelectorAll('.nt-row').forEach(function (mainRow) {
    var detailRow = mainRow.nextElementSibling;
    if (detailRow && detailRow.classList.contains('req-details-row')) setupExistingReqDetailPair(mainRow, detailRow);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  initEditReqDetails();
  editUpdateReqAddArea();
  document.querySelectorAll('#editReqBody .nt-row').forEach(editReqRecalcRow);
  var editSeq = document.getElementById('editReqSequence');
  if (editSeq) { editSeq.addEventListener('input', editSyncReqNumber); editSyncReqNumber(); }
  var editReqForm = document.getElementById('editReqForm');
  if (editReqForm) editReqForm.addEventListener('submit', editSyncReqNumber);
  // En un requerimiento nuevo, la fecha requerida por ítem siempre inicia vacía.
  if (window.location.pathname.indexOf('/requests/new') !== -1) {
    document.querySelectorAll('#reqForm input[name="fecha_requerida_item[]"]').forEach(function (input) { input.value = ''; });
  }
});

function editReqRecalcRow(row) {
  if (!row) return;
  var q = parseFloat((row.querySelector('[name="cantidad[]"]') || {}).value || 0) || 0;
  var s = parseFloat((row.querySelector('[name="stock[]"]') || {}).value || 0) || 0;
  var out = row.querySelector('.edit-req-buy');
  if (out) {
    var v = Math.max(q - s, 0);
    out.value = Number.isInteger(v) ? String(v) : v.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
  }
}

function editSyncReqNumber() {
  var seq = document.getElementById('editReqSequence');
  var full = document.getElementById('editReqFullNumber');
  var yearEl = document.querySelector('#editReqForm .lanr-year-fixed');
  if (!seq || !full || !yearEl) return;
  full.value = String(seq.value || '').trim() + '-' + String(yearEl.textContent || '').trim();
}

function editReqCheckboxForSection(section) {
  if (section === "Ejecución de obra") return document.getElementById("editChkEjec");
  if (section === "Seguridad en obra") return document.getElementById("editChkSeg");
  return document.getElementById("editChkOfi");
}

function editReqRowsForSection(section) {
  return Array.from(document.querySelectorAll('#editReqBody .nt-row[data-seccion="' + section + '"]'));
}

function editReqRowHasInformation(row) {
  if (!row) return false;
  var desc = (row.querySelector('[name="descripcion[]"]') || {}).value || '';
  var qty = (row.querySelector('[name="cantidad[]"]') || {}).value || '';
  var unit = (row.querySelector('[name="unidad[]"]') || {}).value || '';
  var stock = (row.querySelector('[name="stock[]"]') || {}).value || '';
  var just = (row.querySelector('[name="justificacion[]"]') || {}).value || '';
  var detail = row.nextElementSibling;
  var priority = detail && detail.querySelector('[name="prioridad_item[]"]');
  var date = detail && detail.querySelector('[name="fecha_requerida_item[]"]');
  var files = detail && detail.querySelector('input[type="file"]');
  var removeExisting = detail && detail.querySelector('input[name="remove_image_ids[]"]:checked');
  return desc.trim() !== '' || (qty !== '' && Number(qty) !== 1) || unit !== '' || (stock !== '' && Number(stock) !== 0) || just.trim() !== '' ||
         (priority && priority.value && priority.value !== 'Normal') || (date && date.value) || (files && files.files && files.files.length > 0) || !!removeExisting;
}

function editReqShowGuard(message, mode, onConfirm) {
  var modal = document.getElementById('editReqItemGuardModal');
  if (!modal) { if (mode === 'confirm') { if (window.confirm(message)) onConfirm && onConfirm(); } else window.alert(message); return; }
  var msg = document.getElementById('editReqItemGuardMessage');
  var cancel = modal.querySelector('[data-edit-req-guard-cancel]');
  var confirmBtn = modal.querySelector('[data-edit-req-guard-confirm]');
  var ok = modal.querySelector('[data-edit-req-guard-ok]');
  msg.textContent = message;
  cancel.classList.toggle('hidden', mode !== 'confirm');
  confirmBtn.classList.toggle('hidden', mode !== 'confirm');
  ok.classList.toggle('hidden', mode === 'confirm');
  modal.classList.remove('hidden');
  function close(){ modal.classList.add('hidden'); confirmBtn.onclick=null; cancel.onclick=null; ok.onclick=null; }
  cancel.onclick = close; ok.onclick = close;
  confirmBtn.onclick = function(){ close(); if (onConfirm) onConfirm(); };
}

function editUpdateReqAddArea() {
  var area = document.getElementById('edit-req-add-area');
  if (!area) return;
  var selected = [];
  [['editChkEjec','Ejecución de obra','Ejecución'],['editChkSeg','Seguridad en obra','Seguridad'],['editChkOfi','Útiles de oficina','Oficina']].forEach(function(x){
    var cb=document.getElementById(x[0]); if(cb && cb.checked) selected.push([x[1],x[2]]);
  });
  area.innerHTML='';
  selected.forEach(function(x){
    var b=document.createElement('button'); b.type='button'; b.className='btn small';
    b.innerHTML='<i class="fa-solid fa-plus"></i> Agregar a '+x[1];
    b.onclick=function(){ editAddReqRow(x[0]); };
    area.appendChild(b);
  });
}

function editToggleSection(section, checked) {
  var cb = editReqCheckboxForSection(section);
  var rows = editReqRowsForSection(section);
  if (checked) {
    if (!rows.length) editAddReqRow(section);
    editUpdateReqAddArea();
    return;
  }
  if (rows.some(editReqRowHasInformation)) {
    if (cb) cb.checked = true;
    editReqShowGuard('Esta sección contiene información registrada. Para evitar perder datos, elimine los ítems con la X de forma individual.', 'info');
    editUpdateReqAddArea();
    return;
  }
  rows.forEach(function(row){ var d=row.nextElementSibling; if(d && d.classList.contains('req-details-row')) d.remove(); row.remove(); });
  var header=document.querySelector('#editReqBody [data-edit-header="'+section+'"]'); if(header) header.remove();
  editRenumberReq(); editUpdateReqAddArea();
}

function editAddReqRow(section) {
  var tbody = document.getElementById("editReqBody");
  editEnsureHeader(section);

  var itemKey = "item_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 9);
  var tr = document.createElement("tr");
  var detailRow = document.createElement("tr");
  tr.className = "nt-row req-main-row";
  tr.dataset.seccion = section;
  detailRow.className = "req-details-row hidden";
  detailRow.dataset.reqDetailsRow = "1";

  tr.innerHTML = `
    <td class="nt-num"></td>
    <td class="nt-td-desc">
      <input type="hidden" name="seccion[]" value="${section}">
      <input type="hidden" name="item_key[]" value="${itemKey}">
      <textarea class="nt-auto-grow edit-auto" name="descripcion[]" rows="1"></textarea>
    </td>
    <td class="nt-td-cant"><input type="number" step="0.01" min="0" name="cantidad[]" value="1" oninput="editReqRecalcRow(this.closest('tr'))"></td>
    <td class="nt-td-und"><select name="unidad[]" required>${editUnitOptions(window.LANR_REQ_UNITS || [])}</select></td>
    <td class="nt-td-stk"><input type="number" step="0.01" min="0" name="stock[]" value="0" oninput="editReqRecalcRow(this.closest('tr'))"></td>
    <td class="nt-td-buy"><input class="readonly-field edit-req-buy" readonly value="1"></td>
    <td class="nt-td-just"><textarea class="nt-auto-grow edit-auto" name="justificacion[]" rows="1"></textarea></td>
    <td class="nt-td-details"><button type="button" class="req-details-btn" data-req-details-toggle aria-expanded="false" title="Opcional: aquí puede indicar prioridad, fecha requerida e imágenes de referencia."><i class="fa-solid fa-sliders"></i><span>Detalles</span></button><div class="req-details-summary"></div></td>
    <td class="nt-td-del"><div class="req-row-actions"><button type="button" class="nt-add-inline" onclick="editAddReqRowAfter(this)" title="Agregar ítem debajo" aria-label="Agregar ítem debajo"><i class="fa-solid fa-plus"></i></button><button type="button" class="nt-del" onclick="editRemoveReqRow(this)" title="Eliminar ítem" aria-label="Eliminar ítem"><i class="fa-solid fa-xmark"></i></button></div></td>`;

  detailRow.innerHTML = `
    <td colspan="9"><div class="req-details-panel">
      <div class="req-details-heading"><div><strong>Información interna y referencias</strong><span>Complete solo lo necesario. Prioridad y fecha no se imprimen en el PDF.</span></div><button type="button" class="req-details-close" data-req-details-close><i class="fa-solid fa-xmark"></i></button></div>
      <div class="req-details-grid">
        <div class="req-detail-field"><label title="Normal: sigue el proceso habitual. Prioritario: debe atenderse con preferencia. Urgente: requiere atención inmediata.">Prioridad</label><select name="prioridad_item[]" class="req-item-priority" title="Normal: sigue el proceso habitual. Prioritario: debe atenderse con preferencia. Urgente: requiere atención inmediata."><option value="Normal" selected>Normal</option><option value="Prioritario">Prioritario</option><option value="Urgente">Urgente</option></select><small>Normal por defecto. Cámbiala solo si corresponde.</small></div>
        <div class="req-detail-field"><label title="Opcional. Fecha máxima en la que se necesita el material.">Fecha requerida <span>(opcional)</span></label><input type="date" name="fecha_requerida_item[]" value="" autocomplete="off"><small>Si queda vacía, no se guarda ninguna fecha.</small></div>
        <div class="req-detail-field req-detail-images"><label title="Opcional. Adjunte imágenes que ayuden a identificar el material.">Imágenes de referencia <span>(opcional)</span></label><label class="req-image-btn"><i class="fa-regular fa-images"></i> Adjuntar imágenes<input type="file" name="imagenes_${itemKey}" accept="image/jpeg,image/png,image/webp" multiple hidden></label><span class="req-image-count">Sin imágenes</span><div class="req-image-preview" data-image-preview></div><small>JPG, PNG o WEBP. Máximo 5 imágenes por ítem.</small></div>
      </div>
    </div></td>`;

  var nextHeader = Array.from(tbody.querySelectorAll("[data-edit-header]")).find(function (h) {
    return editReqOrder(h.dataset.editHeader) > editReqOrder(section);
  });
  if (nextHeader) {
    tbody.insertBefore(tr, nextHeader);
    tbody.insertBefore(detailRow, nextHeader);
  } else {
    tbody.appendChild(tr);
    tbody.appendChild(detailRow);
  }

  editRenumberReq();
  var ta = tr.querySelector(".edit-auto");
  editGrow(ta);
  ta.focus();
  setupExistingReqDetailPair(tr, detailRow);
  var cb = editReqCheckboxForSection(section); if (cb) cb.checked = true;
  editUpdateReqAddArea();
}

function editAddReqRowAfter(btn) {
  var tbody = document.getElementById("editReqBody");
  var currentRow = btn && btn.closest ? btn.closest(".nt-row") : null;
  if (!tbody || !currentRow) return;
  var section = currentRow.dataset.seccion;
  if (!section) return;

  var itemKey = "item_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 9);
  var tr = document.createElement("tr");
  var detailRow = document.createElement("tr");
  tr.className = "nt-row req-main-row";
  tr.dataset.seccion = section;
  detailRow.className = "req-details-row hidden";
  detailRow.dataset.reqDetailsRow = "1";

  tr.innerHTML = `
    <td class="nt-num"></td>
    <td class="nt-td-desc"><input type="hidden" name="seccion[]" value="${section}"><input type="hidden" name="item_key[]" value="${itemKey}"><textarea class="nt-auto-grow edit-auto" name="descripcion[]" rows="1" placeholder="Descripción del material..."></textarea></td>
    <td class="nt-td-cant"><input type="number" step="0.01" min="0.01" name="cantidad[]" value="1" oninput="editReqRecalcRow(this.closest('tr'))"></td>
    <td class="nt-td-und"><select name="unidad[]" required>${editUnitOptions(window.LANR_REQ_UNITS || [])}</select></td>
    <td class="nt-td-stk"><input type="number" step="0.01" min="0" name="stock[]" value="" oninput="editReqRecalcRow(this.closest('tr'))"></td>
    <td class="nt-td-buy"><input class="readonly-field edit-req-buy" readonly value="1"></td>
    <td class="nt-td-just"><textarea class="nt-auto-grow edit-auto" name="justificacion[]" rows="1" placeholder="Justificación..."></textarea></td>
    <td class="nt-td-details"><button type="button" class="req-details-btn" data-req-details-toggle aria-expanded="false" title="Opcional: aquí puede indicar prioridad, fecha requerida e imágenes de referencia."><i class="fa-solid fa-sliders"></i><span>Detalles</span></button><div class="req-details-summary"></div></td>
    <td class="nt-td-del"><div class="req-row-actions"><button type="button" class="nt-add-inline" onclick="editAddReqRowAfter(this)" title="Agregar ítem debajo" aria-label="Agregar ítem debajo"><i class="fa-solid fa-plus"></i></button><button type="button" class="nt-del" onclick="editRemoveReqRow(this)" title="Eliminar ítem" aria-label="Eliminar ítem"><i class="fa-solid fa-xmark"></i></button></div></td>`;

  detailRow.innerHTML = `
    <td colspan="9"><div class="req-details-panel"><div class="req-details-heading"><div><strong>Información interna y referencias</strong><span>Complete solo lo necesario. Prioridad y fecha no se imprimen en el PDF.</span></div><button type="button" class="req-details-close" data-req-details-close><i class="fa-solid fa-xmark"></i></button></div><div class="req-details-grid"><div class="req-detail-field"><label title="Normal: sigue el proceso habitual. Prioritario: debe atenderse con preferencia. Urgente: requiere atención inmediata.">Prioridad</label><select name="prioridad_item[]" class="req-item-priority"><option value="Normal" selected>Normal</option><option value="Prioritario">Prioritario</option><option value="Urgente">Urgente</option></select><small>Normal por defecto. Cámbiela solo si corresponde.</small></div><div class="req-detail-field"><label>Fecha requerida <span>(opcional)</span></label><input type="date" name="fecha_requerida_item[]" value="" autocomplete="off"><small>Si queda vacía, no se guarda ninguna fecha.</small></div><div class="req-detail-field req-detail-images"><label>Imágenes de referencia <span>(opcional)</span></label><label class="req-image-btn"><i class="fa-regular fa-images"></i> Adjuntar imágenes<input type="file" name="imagenes_${itemKey}" accept="image/jpeg,image/png,image/webp" multiple hidden></label><span class="req-image-count">Sin imágenes</span><div class="req-image-preview" data-image-preview></div><small>JPG, PNG o WEBP. Máximo 5 imágenes por ítem.</small></div></div></div></td>`;

  var currentDetail = currentRow.nextElementSibling && currentRow.nextElementSibling.classList.contains("req-details-row") ? currentRow.nextElementSibling : null;
  var reference = currentDetail || currentRow;
  var nextNode = reference.nextSibling;
  tbody.insertBefore(tr, nextNode);
  tbody.insertBefore(detailRow, nextNode);
  editRenumberReq();
  setupExistingReqDetailPair(tr, detailRow);
  var ta = tr.querySelector(".edit-auto");
  editGrow(ta);
  if (ta) ta.focus();
}

function editRemoveReqRow(btn) {
  var tbody = document.getElementById("editReqBody");
  var row = btn.closest(".nt-row");
  if (!tbody || !row) return;
  var section = row.dataset.seccion;
  function removeNow(){
    var detailRow = row.nextElementSibling;
    if (detailRow && detailRow.classList.contains("req-details-row")) detailRow.remove();
    row.remove();
    if (!tbody.querySelector('.nt-row[data-seccion="' + section + '"]')) {
      var header = tbody.querySelector('[data-edit-header="' + section + '"]');
      if (header) header.remove();
      var cb = editReqCheckboxForSection(section); if (cb) cb.checked = false;
    }
    editRenumberReq(); editUpdateReqAddArea();
  }
  if (editReqRowHasInformation(row)) {
    editReqShowGuard('Este ítem contiene información registrada. ¿Desea eliminarlo? Esta acción quitará los datos de este ítem al guardar los cambios.', 'confirm', removeNow);
  } else removeNow();
}

function editRenumberSp() {
  document.querySelectorAll("#editSpBody .nt-row").forEach(function (row, idx) {
    row.querySelector(".nt-num").textContent = idx + 1;
  });
}

function editAddSpRow() {
  var tbody = document.getElementById("editSpBody");
  var tr = document.createElement("tr");
  tr.className = "nt-row";
  tr.innerHTML = `
    <td class="nt-num"></td>
    <td class="nt-td-conc"><textarea class="nt-auto-grow edit-auto" name="concepto[]" rows="1"></textarea></td>
    <td class="nt-td-unsp"><select name="unidad_sp[]" required>${editUnitOptions(window.LANR_SP_UNITS || [])}</select></td>
    <td class="nt-td-qsp"><input type="number" step="0.01" min="0.01" name="cantidad_sp[]" value="1"></td>
    <td class="nt-td-cost"><input type="number" step="0.01" min="0" name="costo[]" value=""></td>
    <td class="nt-td-del"><button type="button" class="nt-del" onclick="editRemoveSpRow(this)"><i class="fa-solid fa-xmark"></i></button></td>`;
  tbody.appendChild(tr);
  editRenumberSp();
  var ta = tr.querySelector(".edit-auto");
  editGrow(ta);
  ta.focus();
}

function editRemoveSpRow(btn) {
  var tbody = document.getElementById("editSpBody");
  if (tbody.querySelectorAll(".nt-row").length <= 1) {
    alert("La solicitud debe conservar al menos un ítem.");
    return;
  }
  btn.closest(".nt-row").remove();
  editRenumberSp();
}

function editToggleSP() {
  var select = document.getElementById("editSpSubtype");
  if (!select) return;

  var plan = select.value === "Planilla";
  document.querySelectorAll(".edit-plan-only").forEach(function(el) {
    el.classList.toggle("hidden", !plan);
  });
  document.querySelectorAll(".edit-third-only").forEach(function(el) {
    el.classList.toggle("hidden", plan);
  });
}

document.addEventListener("DOMContentLoaded", function () {
  editGrowAll();
  editToggleSP();
});


/* =========================================================
   EDITAR REQUERIMIENTO — VALIDACIÓN NATIVA E ÍTEMS VACÍOS
========================================================= */
(function () {
  var form = document.querySelector("form.edit-as-new");
  if (!form || !document.getElementById("editReqBody")) return;

  form.addEventListener("submit", function (e) {
    var rows = Array.from(form.querySelectorAll("#editReqBody .nt-row"));

    rows.forEach(function (row) {
      var desc = row.querySelector('[name="descripcion[]"]');
      var qty = row.querySelector('[name="cantidad[]"]');
      var unit = row.querySelector('[name="unidad[]"]');
      var description = desc ? desc.value.trim() : "";

      row.querySelectorAll("input, textarea, select").forEach(function (f) {
        if (f.dataset.reqIgnored === "1") {
          f.disabled = false;
          delete f.dataset.reqIgnored;
        }
      });

      if (!description) {
        row.querySelectorAll("input, textarea, select").forEach(function (f) {
          f.disabled = true;
          f.dataset.reqIgnored = "1";
        });
        return;
      }

      if (qty) {
        qty.required = true;
        qty.min = "0.01";
      }
      if (unit) unit.required = true;
    });

    if (!form.checkValidity()) {
      e.preventDefault();
      form.reportValidity();

      form.querySelectorAll('[data-req-ignored="1"]').forEach(function (f) {
        f.disabled = false;
        delete f.dataset.reqIgnored;
      });
    }
  });
  
})();

/* =========================================================
   EDITAR SOLICITUD — VALIDACIÓN NATIVA E ÍTEMS VACÍOS
========================================================= */
(function () {
  var form = document.querySelector("form.edit-as-new");
  if (!form || !document.getElementById("editSpBody")) return;

  form.addEventListener("submit", function (e) {
    var rows = Array.from(form.querySelectorAll("#editSpBody .nt-row"));
    var completedRows = 0;

    rows.forEach(function (row) {
      var concept = row.querySelector('[name="concepto[]"]');
      var unit = row.querySelector('[name="unidad_sp[]"]');
      var qty = row.querySelector('[name="cantidad_sp[]"]');
      var cost = row.querySelector('[name="costo[]"]');
      var description = concept ? concept.value.trim() : "";

      row.querySelectorAll("input, textarea, select").forEach(function (f) {
        if (f.dataset.spIgnored === "1") {
          f.disabled = false;
          delete f.dataset.spIgnored;
        }
      });

      if (!description) {
        row.querySelectorAll("input, textarea, select").forEach(function (f) {
          f.disabled = true;
          f.dataset.spIgnored = "1";
        });
        return;
      }

      completedRows++;

      if (concept) concept.required = true;
      if (unit) unit.required = true;

      if (qty) {
        qty.required = true;
        qty.min = "0.01";
      }

      if (cost) {
        cost.required = true;
        cost.min = "0";
      }
    });

    if (completedRows === 0) {
      e.preventDefault();

      var firstRow = rows[0];
      if (firstRow) {
        firstRow.querySelectorAll("input, textarea, select").forEach(function (f) {
          f.disabled = false;
          delete f.dataset.spIgnored;
        });

        var firstConcept = firstRow.querySelector('[name="concepto[]"]');
        if (firstConcept) {
          firstConcept.required = true;
          firstConcept.focus();
          firstConcept.reportValidity();
        }
      }
      return;
    }

    if (!form.checkValidity()) {
      e.preventDefault();
      form.reportValidity();

      form.querySelectorAll('[data-sp-ignored="1"]').forEach(function (f) {
        f.disabled = false;
        delete f.dataset.spIgnored;
      });
    }
  });
  
})();


/* =========================================================
   logistics_regularization_detail.html - JS movido desde HTML
   ========================================================= */
const regularizationForm = document.getElementById("regularizationForm");
  const REGULARIZATION_EXPECTED_TOTAL = Number(regularizationForm ? regularizationForm.dataset.expectedTotal : 0);

  function money(value) {
    return "S/ " + Number(value || 0).toFixed(2);
  }

  function renumberRegularizationRows() {
    document
      .querySelectorAll("#regularizationRows .reg-product-row")
      .forEach(function (row, index) {
        const number = row.querySelector(".reg-line-num");

        if (number) {
          number.textContent = String(index + 1).padStart(2, "0");
        }
      });
  }

  function calculateRegularization() {
    let total = 0;

    document
      .querySelectorAll("#regularizationRows .reg-product-row")
      .forEach(function (row) {
        const quantityInput = row.querySelector('[name="cantidad[]"]');
        const unitPriceInput = row.querySelector('[name="precio_unitario[]"]');
        const totalInput = row.querySelector(".reg-row-total");

        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const lineTotal = Math.round((quantity * unitPrice + Number.EPSILON) * 100) / 100;

        totalInput.value = lineTotal.toFixed(2);
        total += lineTotal;
      });

    total = Math.round((total + Number.EPSILON) * 100) / 100;

    document.getElementById("detailTotal").textContent = money(total);

    const status = document.getElementById("totalStatus");
    const difference =
      Math.round((REGULARIZATION_EXPECTED_TOTAL - total + Number.EPSILON) * 100) / 100;

    if (Math.abs(difference) <= 0.01 && total > 0) {
      status.className = "reg-sum-status ok";
      status.textContent = "Correcto: los importes coinciden.";
    } else {
      status.className = "reg-sum-status bad";

      if (difference > 0) {
        status.textContent =
          "Faltan " + money(difference) + " por detallar.";
      } else if (difference < 0) {
        status.textContent =
          "El detalle excede en " + money(Math.abs(difference)) + ".";
      } else {
        status.textContent = "Complete el detalle de productos.";
      }
    }
  }

  function addRegularizationRow() {
    const tbody = document.getElementById("regularizationRows");
    const row = document.createElement("tr");

    row.className = "reg-product-row";

    row.innerHTML = `
      <td>
        <span class="reg-line-num">01</span>
      </td>

      <td>
        <input
          type="text"
          name="descripcion[]"
          placeholder="Descripción del producto..."
          required
        />
      </td>

      <td>
        <input
          type="number"
          name="cantidad[]"
          min="0.01"
          step="0.01"
          placeholder="0"
          required
        />
      </td>

      <td>
        <input
          type="number"
          name="precio_unitario[]"
          min="0"
          step="0.01"
          placeholder="0.00"
          required
        />
      </td>

      <td>
        <input
          type="number"
          class="reg-row-total"
          value="0.00"
          readonly
        />
      </td>

      <td>
        <button
          type="button"
          class="reg-line-delete"
          title="Eliminar producto"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </td>
    `;

    row
      .querySelector('[name="cantidad[]"]')
      .addEventListener("input", calculateRegularization);

    row
      .querySelector('[name="precio_unitario[]"]')
      .addEventListener("input", calculateRegularization);

    row
      .querySelector(".reg-line-delete")
      .addEventListener("click", function () {
        row.remove();

        if (
          document.querySelectorAll("#regularizationRows .reg-product-row")
            .length === 0
        ) {
          addRegularizationRow();
          return;
        }

        renumberRegularizationRows();
        calculateRegularization();
      });

    tbody.appendChild(row);

    renumberRegularizationRows();
    calculateRegularization();

    row.querySelector('[name="descripcion[]"]').focus();
  }

  if (regularizationForm) regularizationForm.addEventListener("submit", function (event) {
      calculateRegularization();

      let total = 0;

      document
        .querySelectorAll("#regularizationRows .reg-row-total")
        .forEach(function (input) {
          total += parseFloat(input.value) || 0;
        });

      total = Math.round((total + Number.EPSILON) * 100) / 100;

      if (Math.abs(total - REGULARIZATION_EXPECTED_TOTAL) > 0.01) {
        event.preventDefault();

        const status = document.getElementById("totalStatus");
        status.scrollIntoView({
          behavior: "smooth",
          block: "center"
        });
      }
    });

  if (regularizationForm) addRegularizationRow();


/* =========================================================
   request_detail.html - JS movido desde HTML
   ========================================================= */
function toggleGuiaFields() {
            const opcion = document.getElementById('guiaOpcion');
            const campos = document.getElementById('guiaCampos');
            const aviso = document.getElementById('guiaPendienteAviso');
            const numero = document.getElementById('guiaNumero');
            const fecha = document.getElementById('guiaFecha');
            const archivo = document.getElementById('guiaArchivo');
            if (!opcion || !campos || !aviso) return;
            const adjuntar = opcion.value === 'adjuntar';
            const pendiente = opcion.value === 'pendiente';
            campos.style.display = adjuntar ? '' : 'none';
            aviso.style.display = pendiente ? 'flex' : 'none';
            numero.required = adjuntar;
            fecha.required = adjuntar;
            archivo.required = adjuntar;
            if (!adjuntar) {
              numero.value = '';
              archivo.value = '';
            }
          }
          document.addEventListener('DOMContentLoaded', toggleGuiaFields);
        

function openVbConfirm(){
  const modal=document.getElementById('vbConfirmModal');
  if(!modal) return;
  modal.classList.remove('hidden');
  document.body.style.overflow='hidden';
}
function closeVbConfirm(){
  const modal=document.getElementById('vbConfirmModal');
  if(!modal) return;
  modal.classList.add('hidden');
  document.body.style.overflow='';
}
function vbBackdropClose(event){
  if(event.target.id==='vbConfirmModal') closeVbConfirm();
}
document.addEventListener('keydown',function(event){
  if(event.key==='Escape') closeVbConfirm();
});


  (function () {
    var purchaseCounter = 0;

    window.addPurchaseReceipt = function () {
      var container = document.getElementById("purchaseReceipts");
      if (!container) return;

      purchaseCounter += 1;

      var row = document.createElement("div");
      row.className = "purchase-receipt-row";

      row.innerHTML =
        '<div class="purchase-receipt-num">' + purchaseCounter + '</div>' +
        '<div class="field">' +
          '<label>Fecha *</label>' +
          '<input type="date" name="fecha_compra[]" value="' + (container.dataset.defaultDate || '') + '" required>' +
        '</div>' +
        '<div class="field">' +
          '<label>Tipo *</label>' +
          '<select name="tipo_comprobante[]" required>' +
            '<option value="Factura">Factura</option>' +
            '<option value="Boleta">Boleta</option>' +
            '<option value="Otro">Otro</option>' +
          '</select>' +
        '</div>' +
        '<div class="field">' +
          '<label>N° comprobante *</label>' +
          '<input name="nro_comprobante[]" placeholder="Ej.: E001-169" required>' +
        '</div>' +
        '<div class="field">' +
          '<label>Monto *</label>' +
          '<input type="number" step="0.01" min="0.01" name="monto[]" placeholder="0.00" required>' +
        '</div>' +
        '<div class="field">' +
          '<label>Evidencia *</label>' +
          '<input type="file" name="comprobante[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.xml" required>' +
        '</div>' +
        '<button type="button" class="purchase-remove" title="Eliminar comprobante">' +
          '<i class="fa-solid fa-xmark"></i>' +
        '</button>';

      row.querySelector(".purchase-remove").addEventListener("click", function () {
        row.remove();
        renumberPurchaseReceipts();
        updatePurchaseTotal();
      });

      row.querySelector('[name="monto[]"]').addEventListener("input", updatePurchaseTotal);

      var fileInput = row.querySelector('[name="comprobante[]"]');
      if (fileInput) {
        fileInput.title = "Adjunte una factura, foto, PDF o XML como evidencia.";
      }

      container.appendChild(row);
      renumberPurchaseReceipts();
      updatePurchaseTotal();
    };

    function renumberPurchaseReceipts() {
      var rows = document.querySelectorAll("#purchaseReceipts .purchase-receipt-row");
      rows.forEach(function (row, index) {
        var number = row.querySelector(".purchase-receipt-num");
        if (number) number.textContent = index + 1;
      });
      purchaseCounter = rows.length;
    }

    function updatePurchaseTotal() {
      var total = 0;

      document.querySelectorAll('#purchaseReceipts [name="monto[]"]').forEach(function (input) {
        total += parseFloat(input.value) || 0;
      });

      var output = document.getElementById("purchaseGrandTotal");
      if (output) {
        output.textContent = "S/ " + total.toFixed(2);
      }
    }

    document.addEventListener("DOMContentLoaded", function () {
      var container = document.getElementById("purchaseReceipts");

      if (container && container.children.length === 0) {
        window.addPurchaseReceipt();
      }
    });
  })();


// BLOQUE 5: la pantalla inicia directamente en Requerimiento para mantener
// la composición compacta aprobada. Solicitud de pago sigue disponible desde
// el selector superior cuando el rol tiene permiso.
document.addEventListener('DOMContentLoaded', function () {
  var reqForm = document.getElementById('reqForm');
  if (reqForm) {
    showForm('req');
  }
});

// =========================================================
// BLOQUE A: NUMERACIÓN FORMAL REQ / SP
// =========================================================
(function () {
  function displayNumber(type, year, seq) {
    if (!seq) return "";
    return type === "REQ" ? `${seq}-${year}` : `${year}-${seq}`;
  }

  async function refreshNumberInfo(composer, forceSuggestion) {
    const dateInput = composer.closest("form")?.querySelector('input[name="fecha"]');
    const seqInput = composer.querySelector('input[name="numero_correlativo"]');
    const yearNode = composer.querySelector("[data-fixed-year]");
    const help = composer.parentElement?.querySelector("[data-number-help]");
    if (!dateInput || !seqInput || !yearNode) return;

    const year = (dateInput.value || lanrTodayLocal()).slice(0, 4);
    yearNode.textContent = year;
    try {
      const url = new URL(composer.dataset.infoUrl, window.location.origin);
      url.searchParams.set("year", year);
      const res = await fetch(url.toString(), { headers: { "X-Requested-With": "XMLHttpRequest" } });
      if (!res.ok) return;
      const info = await res.json();
      if (forceSuggestion) {
        seqInput.value = info.suggested == null ? "" : info.suggested;
      }
      if (help) {
        if (info.last == null) {
          help.textContent = `No hay numeración previa para ${year}. Ingrese el primer número.`;
        } else {
          help.textContent = `Último registrado: ${displayNumber(composer.dataset.type, year, info.last)} · Se propone el siguiente y puede modificarlo.`;
        }
      }
    } catch (_) {}
  }

  function showDuplicateModal(message, input) {
    const modal = document.getElementById("duplicateNumberModal");
    const text = document.getElementById("duplicateNumberMessage");
    if (!modal || !text) {
      input?.focus();
      return;
    }
    text.textContent = message;
    modal.classList.remove("hidden");
    const close = modal.querySelector("[data-close-number-modal]");
    if (close) {
      close.onclick = function () {
        modal.classList.add("hidden");
        input?.focus();
        input?.select();
      };
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-number-composer]").forEach(function (composer) {
      const form = composer.closest("form");
      const dateInput = form?.querySelector('input[name="fecha"]');
      const seqInput = composer.querySelector('input[name="numero_correlativo"]');
      if (!form || !dateInput || !seqInput) return;

      dateInput.addEventListener("change", function () {
        // Cambiar la fecha puede actualizar el año y la ayuda de numeración,
        // pero nunca debe borrar ni reemplazar el correlativo escrito por el usuario.
        refreshNumberInfo(composer, false);
      });

      form.addEventListener("submit", async function (event) {
        if (form.dataset.numberValidated === "1") {
          form.dataset.numberValidated = "";
          return;
        }
        event.preventDefault();
        const year = (dateInput.value || lanrTodayLocal()).slice(0, 4);
        const seq = seqInput.value.trim();
        if (!seq) {
          seqInput.focus();
          return;
        }
        try {
          const url = new URL(composer.dataset.infoUrl, window.location.origin);
          url.searchParams.set("year", year);
          url.searchParams.set("sequence", seq);
          const res = await fetch(url.toString(), { headers: { "X-Requested-With": "XMLHttpRequest" } });
          if (!res.ok) {
            form.dataset.numberValidated = "1";
            form.requestSubmit();
            return;
          }
          const info = await res.json();
          if (info.exists) {
            const last = info.last == null ? "sin registros previos" : displayNumber(composer.dataset.type, year, info.last);
            const full = displayNumber(composer.dataset.type, year, seq);
            showDuplicateModal(`El N.° ${full} ya existe. El último número registrado es ${last}. Presione Aceptar y modifique el correlativo.`, seqInput);
            return;
          }
          form.dataset.numberValidated = "1";
          form.requestSubmit();
        } catch (_) {
          form.dataset.numberValidated = "1";
          form.requestSubmit();
        }
      });
    });
  });
})();

function toggleQuotationFiles(){
  const type=document.getElementById('supportType');
  const field=document.getElementById('quotationFilesField');
  const input=document.getElementById('quotationFiles');
  if(!type||!field||!input) return;
  const needsFile=type.value==='Cotización';
  field.style.display=needsFile?'block':'none';
  input.required=needsFile;
}
document.addEventListener('DOMContentLoaded',toggleQuotationFiles);

/* =========================================================
   LANR V4 - SOLICITUD DE PAGO INTEGRAL
========================================================= */
(function(){
  function today(){ return new Date().toISOString().slice(0,10); }
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-today]').forEach(function(el){ if(!el.value) el.value=today(); });
    if(document.getElementById('spV4Items')){ if(!document.querySelector('#spV4Items tr')) spv4AddItem(); else { spv4Renumber(); spv4Recalc(); } }
    if(document.getElementById('spV4Accounts')){ if(!document.querySelector('#spV4Accounts .spv4-account-row')) spv4AddAccount(); }
    if(document.getElementById('orderV4Items')){ orderv4AddItem(); }
    var chk=document.getElementById('spV4OtherModeCheck');
    if(chk) chk.addEventListener('change',function(){ document.getElementById('spV4OtherModeWrap').classList.toggle('hidden',!chk.checked); });
  });
})();

window.spv4AddItem=function(){
  var body=document.getElementById('spV4Items'); if(!body) return;
  var units=[]; try{ units=JSON.parse(document.getElementById('requestUnitData').dataset.spUnits||'[]'); }catch(e){}
  var tr=document.createElement('tr');
  tr.innerHTML='<td class="spv4-index"></td>'+
    '<td><input name="concepto[]" required placeholder="Concepto"></td>'+
    '<td><select name="unidad_sp[]" required><option value="">UND.</option>'+units.map(u=>'<option value="'+u+'">'+u+'</option>').join('')+'</select></td>'+
    '<td><input type="number" step="0.01" name="cantidad_sp[]" required value="1" oninput="spv4Recalc()"></td>'+
    '<td><input type="number" step="0.01" min="0" name="costo[]" required value="0" oninput="spv4Recalc()"></td>'+
    '<td class="spv4-amount">0.00</td>'+
    '<td><button type="button" class="btn small" title="Opcional: N° de despacho para casos especiales" onclick="this.closest(\'tr\').querySelector(\'.spv4-dispatch\').classList.toggle(\'hidden\')">Detalles</button><div class="spv4-dispatch hidden"><input name="nro_despacho[]" inputmode="numeric" placeholder="N° despacho (opcional)"></div></td>'+
    '<td><button type="button" class="iconbtn danger" onclick="this.closest(\'tr\').remove();spv4Renumber();spv4Recalc()"><i class="fa-solid fa-trash"></i></button></td>';
  body.appendChild(tr); spv4Renumber(); spv4Recalc();
};
window.spv4Renumber=function(){ document.querySelectorAll('#spV4Items tr').forEach((tr,i)=>{var x=tr.querySelector('.spv4-index');if(x)x.textContent=i+1;}); };
window.spv4Recalc=function(){
  var total=0, cur=(document.getElementById('spV4Currency')||{}).value==='USD'?'US$':'S/';
  document.querySelectorAll('#spV4Items tr').forEach(function(tr){ var q=parseFloat((tr.querySelector('[name="cantidad_sp[]"]')||{}).value)||0; var p=parseFloat((tr.querySelector('[name="costo[]"]')||{}).value)||0; var m=q*p; total+=m; var c=tr.querySelector('.spv4-amount'); if(c){c.textContent=cur+' '+m.toFixed(2); c.classList.toggle('negative-money',q<0);} });
  var out=document.getElementById('spV4Total'); if(out) out.textContent=cur+' '+total.toFixed(2);
  var a=parseFloat((document.getElementById('spV4Amort')||{}).value)||0; var b=document.getElementById('spV4Balance'); if(b) b.value=cur+' '+(total-a).toFixed(2);
};
window.spv4ToggleAmortization=function(){ var on=document.getElementById('spV4AmortToggle').checked; document.getElementById('spV4AmortFlag').value=on?'1':'0'; document.getElementById('spV4AmortBox').classList.toggle('hidden',!on); if(!on) document.getElementById('spV4Amort').value=''; spv4Recalc(); };
window.spv4AddAccount=function(){
  var wrap=document.getElementById('spV4Accounts'); if(!wrap)return; var row=document.createElement('div'); row.className='spv4-account-row';
  row.innerHTML='<div class="field"><label>Banco</label><select name="banco[]" onchange="var x=this.closest(\'.spv4-account-row\').querySelector(\'.spv4-other-bank\');x.classList.toggle(\'hidden\',this.value!==\'Otro\')"><option value="">Seleccione...</option><option>Banco de la Nación</option><option>BBVA</option><option>BCP</option><option>Caja Trujillo</option><option>Otro</option></select><input class="spv4-other-bank hidden" name="banco_otro[]" placeholder="Otro banco"></div><div class="field"><label>Cuenta / CCI</label><input name="cuenta_cci[]" placeholder="Opcional"></div><button type="button" class="iconbtn danger spv4-row-delete" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>';
  wrap.appendChild(row);
};
window.spv4AddReceipt=function(){
  var wrap=document.getElementById('spV4Receipts'); if(!wrap)return; var row=document.createElement('div'); row.className='spv4-receipt-row';
  row.innerHTML='<div class="field"><label>Tipo</label><select name="comprobante_tipo[]"><option value="">Sin seleccionar</option><option>Factura</option><option>RH</option><option>SC</option><option>Boleta</option><option>Otro</option></select></div><div class="field"><label>N° comprobante</label><input name="comprobante_numero[]" placeholder="Número o PENDIENTE"></div><div class="field"><label>Archivo</label><input type="file" name="comprobante_archivo[]" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><button type="button" class="iconbtn danger spv4-row-delete" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>';
  wrap.appendChild(row);
};
var spCurrency=document.getElementById('spV4Currency'); if(spCurrency) spCurrency.addEventListener('change',spv4Recalc);

/* ÓRDENES */
window.orderv4AddItem=function(){
  var body=document.getElementById('orderV4Items'); if(!body)return; var tr=document.createElement('tr');
  tr.innerHTML='<td class="ordv4-index"></td><td><input name="descripcion_item[]" required></td><td><input name="unidad_item[]" placeholder="UND"></td><td><input type="number" step="0.01" name="cantidad_item[]" value="1" required oninput="orderv4Recalc()"></td><td><input type="number" min="0" step="0.01" name="precio_item[]" value="0" required oninput="orderv4Recalc()"></td><td class="ordv4-amount">0.00</td><td><button type="button" class="iconbtn danger" onclick="this.closest(\'tr\').remove();orderv4Renumber();orderv4Recalc()"><i class="fa-solid fa-trash"></i></button></td>';
  body.appendChild(tr); orderv4Renumber(); orderv4Recalc();
};
window.orderv4Renumber=function(){document.querySelectorAll('#orderV4Items tr').forEach((tr,i)=>tr.querySelector('.ordv4-index').textContent=i+1);};
window.orderv4Recalc=function(){document.querySelectorAll('#orderV4Items tr').forEach(function(tr){var q=parseFloat(tr.querySelector('[name="cantidad_item[]"]').value)||0,p=parseFloat(tr.querySelector('[name="precio_item[]"]').value)||0;tr.querySelector('.ordv4-amount').textContent=(q*p).toFixed(2);});};


/* =========================================================
   REQUERIMIENTO — CÓDIGO DE FORMATO EN TIEMPO REAL
========================================================= */
(function () {
  function syncReqFormatCode() {
    var sequence = document.getElementById("reqSequence");
    var format = document.getElementById("formatCode");
    if (!sequence || !format) return;
    var value = String(sequence.value || "").replace(/\D/g, "");
    format.value = "F01A-LANR-" + value;
  }
  document.addEventListener("DOMContentLoaded", function () {
    var sequence = document.getElementById("reqSequence");
    if (!sequence) return;
    sequence.addEventListener("input", syncReqFormatCode);
    sequence.addEventListener("change", syncReqFormatCode);
    syncReqFormatCode();
  });
})();

/* =========================================================
   REQUERIMIENTO — ARCHIVO EXTERNO ÚNICO (PDF / EXCEL)
========================================================= */
(function () {
  function bindExternalFile(inputId, boxId, nameId, viewId, clearId) {
    var input = document.getElementById(inputId);
    var box = document.getElementById(boxId);
    var name = document.getElementById(nameId);
    var view = document.getElementById(viewId);
    var clear = document.getElementById(clearId);
    if (!input || !box || !name || !view || !clear) return;
    var objectUrl = null;
    function reset() {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null; input.value = ""; box.hidden = true; view.removeAttribute("href"); name.textContent = "";
    }
    input.addEventListener("change", function () {
      var file = input.files && input.files[0];
      if (!file) { reset(); return; }
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      name.textContent = file.name;
      view.href = objectUrl;
      box.hidden = false;
    });
    clear.addEventListener("click", reset);
  }
  document.addEventListener("DOMContentLoaded", function () {
    bindExternalFile("reqDocumentos", "reqExternalFilePreview", "reqExternalFileName", "reqExternalFileView", "reqExternalFileClear");
    bindExternalFile("editReqDocumentos", "editReqExternalFilePreview", "editReqExternalFileName", "editReqExternalFileView", "editReqExternalFileClear");
    document.querySelectorAll('.lanr-delete-file-btn input[type="checkbox"]').forEach(function (check) {
      check.addEventListener("change", function () {
        var label = check.closest(".lanr-delete-file-btn");
        var card = check.closest(".lanr-current-external-file");
        if (label) label.classList.toggle("is-selected", check.checked);
        if (card) card.classList.toggle("is-marked-delete", check.checked);
      });
    });
  });
})();
