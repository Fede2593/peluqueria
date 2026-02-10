const STORAGE_KEY = "peluqueria-manager-data";

const defaultData = {
  catalog: ["Corte de pelo", "Tinturado", "Ondulado", "Planchado"],
  services: [],
  ledger: [],
  products: [],
  admins: [],
};

const state = loadState();

function loadState() {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (!saved) return structuredClone(defaultData);

  try {
    return { ...structuredClone(defaultData), ...JSON.parse(saved) };
  } catch {
    return structuredClone(defaultData);
  }
}

function saveState() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function formatMoney(value) {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "USD",
  }).format(value);
}

function now() {
  return new Date().toLocaleString("es-ES");
}

function byId(id) {
  return document.getElementById(id);
}

function renderCatalog() {
  const select = document.querySelector("#service-form select[name='service']");
  select.innerHTML = `<option value="">Tipo de servicio</option>${state.catalog
    .map((item) => `<option value="${item}">${item}</option>`)
    .join("")}`;

  byId("catalog-list").innerHTML = state.catalog
    .map(
      (item, index) =>
        `<li>${item}<button class="small-btn" data-catalog-delete="${index}">×</button></li>`
    )
    .join("");
}

function renderServices() {
  byId("service-table").innerHTML = state.services
    .map(
      (row) => `<tr>
      <td>${row.date}</td>
      <td>${row.collaborator}</td>
      <td>${row.service}</td>
      <td>${row.client}</td>
      <td>${formatMoney(row.price)}</td>
    </tr>`
    )
    .join("");
}

function renderLedger() {
  byId("ledger-table").innerHTML = state.ledger
    .map(
      (row) => `<tr>
      <td>${row.date}</td>
      <td>${row.description}</td>
      <td>${row.type}</td>
      <td>${formatMoney(row.amount)}</td>
    </tr>`
    )
    .join("");

  const totalDebe = state.ledger
    .filter((x) => x.type === "debe")
    .reduce((sum, x) => sum + x.amount, 0);
  const totalHaber = state.ledger
    .filter((x) => x.type === "haber")
    .reduce((sum, x) => sum + x.amount, 0);

  byId("total-debe").textContent = formatMoney(totalDebe);
  byId("total-haber").textContent = formatMoney(totalHaber);
  byId("balance").textContent = formatMoney(totalHaber - totalDebe);
}

function renderProducts() {
  byId("product-table").innerHTML = state.products
    .map(
      (product, index) => `<tr>
      <td>${product.name}</td>
      <td>${product.stock}</td>
      <td>${formatMoney(product.price)}</td>
      <td><button data-product-delete="${index}">Eliminar</button></td>
    </tr>`
    )
    .join("");
}

function renderAdmins() {
  byId("admin-table").innerHTML = state.admins
    .map(
      (admin, index) => `<tr>
      <td>${admin.name}</td>
      <td>${admin.present ? "Sí" : "No"}</td>
      <td>${admin.lastCheck ?? "Sin registro"}</td>
      <td>
        <button data-admin-toggle="${index}">Marcar asistencia</button>
        <button data-admin-delete="${index}">Quitar</button>
      </td>
    </tr>`
    )
    .join("");
}

function bindForms() {
  byId("catalog-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    const name = form.name.value.trim();
    if (!name || state.catalog.includes(name)) return;
    state.catalog.push(name);
    form.reset();
    saveState();
    renderCatalog();
  });

  byId("service-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    state.services.unshift({
      date: now(),
      collaborator: form.collaborator.value.trim(),
      service: form.service.value,
      client: form.client.value.trim(),
      price: Number(form.price.value),
    });
    form.reset();
    saveState();
    renderServices();
  });

  byId("ledger-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    state.ledger.unshift({
      date: now(),
      description: form.description.value.trim(),
      type: form.type.value,
      amount: Number(form.amount.value),
    });
    form.reset();
    saveState();
    renderLedger();
  });

  byId("product-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    state.products.push({
      name: form.name.value.trim(),
      stock: Number(form.stock.value),
      price: Number(form.price.value),
    });
    form.reset();
    saveState();
    renderProducts();
  });

  byId("admin-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    state.admins.push({
      name: form.name.value.trim(),
      present: false,
      lastCheck: null,
    });
    form.reset();
    saveState();
    renderAdmins();
  });
}

function bindDelegatedActions() {
  document.body.addEventListener("click", (event) => {
    const target = event.target;

    if (target.dataset.catalogDelete !== undefined) {
      state.catalog.splice(Number(target.dataset.catalogDelete), 1);
      saveState();
      renderCatalog();
      return;
    }

    if (target.dataset.productDelete !== undefined) {
      state.products.splice(Number(target.dataset.productDelete), 1);
      saveState();
      renderProducts();
      return;
    }

    if (target.dataset.adminDelete !== undefined) {
      state.admins.splice(Number(target.dataset.adminDelete), 1);
      saveState();
      renderAdmins();
      return;
    }

    if (target.dataset.adminToggle !== undefined) {
      const idx = Number(target.dataset.adminToggle);
      const admin = state.admins[idx];
      admin.present = !admin.present;
      admin.lastCheck = now();
      saveState();
      renderAdmins();
    }
  });
}

function init() {
  renderCatalog();
  renderServices();
  renderLedger();
  renderProducts();
  renderAdmins();
  bindForms();
  bindDelegatedActions();
}

init();
