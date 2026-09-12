function showForm(t) {
  const reqForm = document.getElementById("reqForm");

  const spForm = document.getElementById("spForm");

  const optionReq = document.getElementById("optionReq");

  const optionSp = document.getElementById("optionSp");

  reqForm.classList.toggle("hidden", t !== "req");

  spForm.classList.toggle("hidden", t !== "sp");

  if (optionReq) {
    optionReq.classList.toggle("active", t === "req");
  }

  if (optionSp) {
    optionSp.classList.toggle("active", t === "sp");
  }

  document.querySelectorAll("input[type=date]").forEach((i) => {
    if (!i.value) {
      i.value = new Date().toISOString().slice(0, 10);
    }
  });

  setTimeout(() => {
    const form = t === "req" ? reqForm : spForm;

    form.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }, 100);
}
function toggleReq(k, on) {
  let sec = document.getElementById("sec-" + k);
  sec.classList.toggle("hidden", !on);
  let body = document.getElementById("body-" + k);
  if (on && body.children.length === 0)
    addReqRow(
      "body-" + k,
      k === "ejec" ? "Ejecución de obra" : "Ing. de seguridad",
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
    if (!i.value) i.value = new Date().toISOString().slice(0, 10);
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
// MAYÚSCULAS AUTOMÁTICAS EN TODO CAMPO DE TEXTO
// =========================================================

document.addEventListener("input", function (e) {
  const el = e.target;

  if (!(el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement)) {
    return;
  }

  // NO modificar estos campos
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

  if (el.id === "username" || el.id === "password") {
    return;
  }

  if (tiposExcluidos.includes(el.type)) {
    return;
  }

  // Los campos monetarios tienen su propio formato
  if (el.classList.contains("money-input")) {
    return;
  }

  const home = el.selectionStart;
  const fin = el.selectionEnd;

  const valorMayuscula = el.value.toUpperCase();

  // Solo modificar si realmente cambió
  if (el.value !== valorMayuscula) {
    el.value = valorMayuscula;

    if (home !== null && fin !== null) {
      el.setSelectionRange(home, fin);
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
  const buscador = document.getElementById("requestsearch");
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
