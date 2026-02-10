const STORAGE_KEY = "peluqueria-manager-data";

const defaultData = {
  catalog: ["Corte de pelo", "Tinturado", "Ondulado", "Planchado"],
  collaborators: [],
  works: [],
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
  }).format(Number(value) || 0);
}

function now() {
  return new Date().toLocaleString("es-ES");
}

function byId(id) {
  return document.getElementById(id);
}

function findCollaborator(id) {
  return state.collaborators.find((item) => item.id === id);
}

function renderCollaborators() {
  byId("collaborator-table").innerHTML = state.collaborators
    .map((item) => {
      const ownerPercent = 100 - item.percent;
      return `<tr>
        <td>${item.name}</td>
        <td>${item.percent.toFixed(2)}%</td>
        <td>${ownerPercent.toFixed(2)}%</td>
        <td><button data-collaborator-delete="${item.id}">Eliminar</button></td>
      </tr>`;
    })
    .join("");

  const collaboratorSelect = document.querySelector("#work-form select[name='collaboratorId']");
  collaboratorSelect.innerHTML = `<option value="">Selecciona colaborador</option>${state.collaborators
    .map((item) => `<option value="${item.id}">${item.name} (${item.percent.toFixed(2)}%)</option>`)
    .join("")}`;
}

function renderCatalog() {
  const select = document.querySelector("#work-form select[name='service']");
  select.innerHTML = `<option value="">Tipo de trabajo</option>${state.catalog
    .map((item) => `<option value="${item}">${item}</option>`)
    .join("")}`;

  byId("catalog-list").innerHTML = state.catalog
    .map(
      (item, index) =>
        `<li>${item}<button class="small-btn" data-catalog-delete="${index}">×</button></li>`
    )
    .join("");
}

function renderWorks() {
  byId("work-table").innerHTML = state.works
    .map((row) => {
      const collaborator = findCollaborator(row.collaboratorId);
      return `<tr>
        <td>${row.date}</td>
        <td>${collaborator?.name ?? "Colaborador eliminado"}</td>
        <td>${row.service}</td>
        <td>${formatMoney(row.amount)}</td>
        <td>${formatMoney(row.collaboratorShare)}</td>
        <td>${formatMoney(row.ownerShare)}</td>
      </tr>`;
    })
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

function bindTabs() {
  document.querySelectorAll(".tab-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const tab = button.dataset.tab;
      document.querySelectorAll(".tab-btn").forEach((x) => x.classList.remove("active"));
      document.querySelectorAll(".tab-panel").forEach((x) => x.classList.remove("active"));
      button.classList.add("active");
      byId(`tab-${tab}`).classList.add("active");
    });
  });
}

function bindForms() {
  byId("collaborator-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    const name = form.name.value.trim();
    const percent = Number(form.percent.value);

    if (!name || Number.isNaN(percent) || percent <= 0 || percent >= 100) return;

    state.collaborators.push({
      id: crypto.randomUUID(),
      name,
      percent,
    });

    form.reset();
    saveState();
    renderCollaborators();
  });

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

  byId("work-form").addEventListener("submit", (event) => {
    event.preventDefault();
    const form = event.target;
    const collaboratorId = form.collaboratorId.value;
    const collaborator = findCollaborator(collaboratorId);
    if (!collaborator) return;

    const amount = Number(form.amount.value);
    const collaboratorShare = (amount * collaborator.percent) / 100;
    const ownerShare = amount - collaboratorShare;

    state.works.unshift({
      date: now(),
      collaboratorId,
      service: form.service.value,
      client: form.client.value.trim(),
      amount,
      collaboratorShare,
      ownerShare,
    });

    state.ledger.unshift({
      date: now(),
      description: `Ingreso por ${form.service.value} (${collaborator.name})`,
      type: "haber",
      amount: ownerShare,
    });

    form.reset();
    saveState();
    renderWorks();
    renderLedger();
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

    if (target.dataset.collaboratorDelete !== undefined) {
      state.collaborators = state.collaborators.filter((x) => x.id !== target.dataset.collaboratorDelete);
      saveState();
      renderCollaborators();
      renderWorks();
      return;
    }

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
  bindTabs();
  bindForms();
  bindDelegatedActions();
  renderCollaborators();
  renderCatalog();
  renderWorks();
  renderLedger();
  renderProducts();
  renderAdmins();
}

init();
