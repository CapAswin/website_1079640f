const REQUIRED_SCHEMA = [
  { key: "lead_owner", label: "Lead Owner", required: true, type: "email" },
  { key: "company", label: "Company", required: true, type: "text" },
  { key: "first_name", label: "First Name", required: true, type: "text" },
  { key: "last_name", label: "Last Name", required: true, type: "text" },
  { key: "title", label: "Title", required: false, type: "text" },
  { key: "email", label: "Email", required: false, type: "email" },
  { key: "phone", label: "Phone", required: false, type: "text" },
  { key: "fax", label: "Fax", required: false, type: "text" },
  { key: "mobile", label: "Mobile", required: false, type: "text" },
  { key: "website", label: "Website", required: false, type: "text" },
  { key: "lead_source", label: "Lead Source", required: false, type: "text" },
  { key: "lead_status", label: "Lead Status", required: false, type: "text" },
  { key: "industry", label: "Industry", required: false, type: "text" },
  { key: "employees", label: "No. of Employees", required: false, type: "number" },
  { key: "annual_revenue", label: "Annual Revenue", required: false, type: "number" },
  { key: "rating", label: "Rating", required: false, type: "text" },
  { key: "street", label: "Street", required: false, type: "text" },
  { key: "city", label: "City", required: false, type: "text" },
  { key: "state", label: "State", required: false, type: "text" },
  { key: "zip", label: "Zip Code", required: false, type: "text" },
  { key: "country", label: "Country", required: false, type: "text" },
  { key: "description", label: "Description", required: false, type: "text" },
  { key: "skype", label: "Skype ID", required: false, type: "text" },
  { key: "email_opt_out", label: "Email Opt Out", required: false, type: "boolean" },
  { key: "salutation", label: "Salutation", required: false, type: "text" },
  { key: "secondary_email", label: "Secondary Email", required: false, type: "email" },
  { key: "twitter", label: "Twitter", required: false, type: "text" },
  { key: "tag", label: "Tag", required: false, type: "text" },
  { key: "remarks", label: "Remarks", required: false, type: "text" }
];

const state = {
  fileName: "",
  rawRows: [],
  sourceHeaders: [],
  mappings: {},
  processedRows: [],
  warnings: [],
  stats: {
    totalRows: 0,
    validRows: 0,
    invalidRows: 0,
    exportedRows: 0
  }
};

const dropZone = document.getElementById("dropZone");
const fileInput = document.getElementById("fileInput");
const fileNameEl = document.getElementById("fileName");
const mappingContainer = document.getElementById("mappingContainer");
const processBtn = document.getElementById("processBtn");
const downloadBtn = document.getElementById("downloadBtn");
const previewTable = document.getElementById("previewTable");
const previewMeta = document.getElementById("previewMeta");
const statsEl = document.getElementById("stats");
const warningsEl = document.getElementById("warnings");
const progressWrap = document.getElementById("progressWrap");
const progressBarFill = document.getElementById("progressBarFill");
const progressText = document.getElementById("progressText");
const saveMappingBtn = document.getElementById("saveMappingBtn");
const loadMappingBtn = document.getElementById("loadMappingBtn");
const resetBtn = document.getElementById("resetBtn");
const leadOwnerInput = document.getElementById("leadOwnerInput");
const autoMapBtn = document.getElementById("autoMapBtn");
const quickConvertBtn = document.getElementById("quickConvertBtn");

init();

function init() {
  bindUploadEvents();
  processBtn.addEventListener("click", processRowsInChunks);
  downloadBtn.addEventListener("click", downloadOutputCsv);
  saveMappingBtn.addEventListener("click", saveMapping);
  loadMappingBtn.addEventListener("click", loadSavedMapping);
  resetBtn.addEventListener("click", resetApp);
  autoMapBtn.addEventListener("click", () => {
    autoMapColumns();
    renderMappingSelectors();
  });
  quickConvertBtn.addEventListener("click", () => {
    autoMapColumns();
    renderMappingSelectors();
    processRowsInChunks();
  });
  leadOwnerInput.addEventListener("change", saveLeadOwnerEmail);
  loadLeadOwnerEmail();
  renderMappingSelectors();
}

function bindUploadEvents() {
  fileInput.addEventListener("change", (event) => {
    const file = event.target.files && event.target.files[0];
    if (file) {
      handleFile(file);
    }
  });

  ["dragenter", "dragover"].forEach((eventName) => {
    dropZone.addEventListener(eventName, (event) => {
      event.preventDefault();
      event.stopPropagation();
      dropZone.classList.add("dragover");
    });
  });

  ["dragleave", "drop"].forEach((eventName) => {
    dropZone.addEventListener(eventName, (event) => {
      event.preventDefault();
      event.stopPropagation();
      dropZone.classList.remove("dragover");
    });
  });

  dropZone.addEventListener("drop", (event) => {
    const files = event.dataTransfer && event.dataTransfer.files;
    if (!files || !files.length) return;
    handleFile(files[0]);
  });
}

function handleFile(file) {
  if (!file.name.toLowerCase().endsWith(".csv")) {
    alert("Please upload a CSV file.");
    return;
  }

  state.fileName = file.name;
  fileNameEl.textContent = "Selected file: " + file.name;
  clearOutputUi();

  parseCsvWithFallback(file)
    .then((results) => {
      state.rawRows = results.data || [];
      state.sourceHeaders = (results.meta && results.meta.fields) || inferHeaders(state.rawRows);
      state.stats.totalRows = state.rawRows.length;

      autoMapColumns();
      renderMappingSelectors();
      processBtn.disabled = state.rawRows.length === 0;
      autoMapBtn.disabled = state.rawRows.length === 0;
      quickConvertBtn.disabled = state.rawRows.length === 0;
      statsEl.textContent = "Rows detected: " + state.stats.totalRows;
      if (state.rawRows.length === 0) {
        warningsEl.classList.remove("hidden");
        warningsEl.textContent = "No data rows found in this CSV.";
      }
    })
    .catch((error) => {
      alert("Failed to parse CSV: " + error.message);
    });
}

function parseCsvWithFallback(file) {
  return new Promise((resolve, reject) => {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: "greedy",
      dynamicTyping: false,
      complete: (results) => {
        const fields = (results.meta && results.meta.fields) || [];
        const firstField = fields[0] || "";
        const looksTabDelimited = fields.length === 1 && firstField.includes("\t");
        if (!looksTabDelimited) {
          resolve(results);
          return;
        }

        const reader = new FileReader();
        reader.onload = () => {
          const buffer = reader.result;
          const text = decodeCsvBytes(buffer);
          Papa.parse(text, {
            header: true,
            delimiter: "\t",
            skipEmptyLines: "greedy",
            dynamicTyping: false,
            complete: resolve,
            error: reject
          });
        };
        reader.onerror = () => reject(new Error("Could not read CSV bytes."));
        reader.readAsArrayBuffer(file);
      },
      error: reject
    });
  });
}

function decodeCsvBytes(arrayBuffer) {
  const bytes = new Uint8Array(arrayBuffer);
  if (bytes.length >= 2) {
    const bom0 = bytes[0];
    const bom1 = bytes[1];
    if (bom0 === 0xff && bom1 === 0xfe) {
      return new TextDecoder("utf-16le").decode(bytes);
    }
    if (bom0 === 0xfe && bom1 === 0xff) {
      return new TextDecoder("utf-16be").decode(bytes);
    }
  }
  return new TextDecoder("utf-8").decode(bytes);
}

function inferHeaders(rows) {
  if (!rows.length) return [];
  return Object.keys(rows[0]);
}

function normalize(str) {
  return String(str || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]/g, "");
}

function autoMapColumns() {
  const sourceByNormalized = new Map();
  state.sourceHeaders.forEach((header) => {
    sourceByNormalized.set(normalize(header), header);
  });

  const aliasMap = {
    leadowner: ["leadowner", "owner", "assignee"],
    company: ["company", "companyname", "organization"],
    firstname: ["firstname", "first", "givenname"],
    lastname: ["lastname", "last", "surname", "familyname"],
    title: ["title", "jobtitle", "designation"],
    email: ["email", "emailaddress", "mail"],
    phone: ["phone", "phonenumber", "contactnumber"],
    fax: ["fax"],
    mobile: ["mobile", "mobilephone", "cell", "contactnumber", "phone"],
    website: ["website", "site", "url"],
    leadsource: ["leadsource", "source", "platform", "campaignname"],
    leadstatus: ["leadstatus", "status"],
    industry: ["industry", "business_type", "whichbestdescribesyourbusiness"],
    noofemployees: ["noofemployees", "employees", "team_size"],
    annualrevenue: ["annualrevenue", "revenue"],
    rating: ["rating"],
    street: ["street", "address", "line1"],
    city: ["city"],
    state: ["state", "province"],
    zipcode: ["zipcode", "zip", "postalcode"],
    country: ["country", "nation", "countryname"],
    description: [
      "description",
      "notes",
      "remark",
      "whatisyourmaingoalfromsocialmediamarketing"
    ],
    skypeid: ["skypeid", "skype"],
    emailoptout: ["emailoptout", "do_not_email", "optout"],
    salutation: ["salutation"],
    secondaryemail: ["secondaryemail", "alt_email"],
    twitter: ["twitter", "xhandle"],
    tag: ["tag", "tags"],
    remarks: ["remarks", "remark", "notes"],
    fullname: ["fullname", "full_name", "name"],
    companyname: ["companyname"],
    jobtitle: ["jobtitle"],
    doyoucurrentlyrundigitalmarketingcampaigns: ["doyoucurrentlyrundigitalmarketingcampaigns"],
    whatisyourmaingoalfromsocialmediamarketing: ["whatisyourmaingoalfromsocialmediamarketing"]
  };

  state.mappings = {};
  REQUIRED_SCHEMA.forEach((field) => {
    const normTarget = normalize(field.label);
    let matchedHeader = sourceByNormalized.get(normTarget);

    if (!matchedHeader && aliasMap[normTarget]) {
      for (const alias of aliasMap[normTarget]) {
        matchedHeader = sourceByNormalized.get(normalize(alias));
        if (matchedHeader) break;
      }
    }

    state.mappings[field.key] = matchedHeader || "";
  });
}

function renderMappingSelectors() {
  mappingContainer.innerHTML = "";
  REQUIRED_SCHEMA.forEach((field) => {
    const wrap = document.createElement("div");
    wrap.className = "mapping-item";

    const label = document.createElement("label");
    label.textContent = field.label + (field.required ? " *" : "");
    label.htmlFor = "map-" + field.key;

    const select = document.createElement("select");
    select.id = "map-" + field.key;
    select.dataset.fieldKey = field.key;

    const emptyOpt = document.createElement("option");
    emptyOpt.value = "";
    emptyOpt.textContent = "-- Not mapped --";
    select.appendChild(emptyOpt);

    state.sourceHeaders.forEach((header) => {
      const opt = document.createElement("option");
      opt.value = header;
      opt.textContent = header;
      select.appendChild(opt);
    });

    select.value = state.mappings[field.key] || "";
    select.addEventListener("change", (event) => {
      state.mappings[field.key] = event.target.value;
    });

    wrap.appendChild(label);
    wrap.appendChild(select);
    mappingContainer.appendChild(wrap);
  });
}

function getInvalidMode() {
  const modeRadio = document.querySelector("input[name='invalidMode']:checked");
  return modeRadio ? modeRadio.value : "skip";
}

function processRowsInChunks() {
  const missingMappings = REQUIRED_SCHEMA
    .filter((f) => f.required && !canResolveRequiredField(f))
    .map((f) => f.label);

  if (missingMappings.length) {
    alert("Map all required fields before processing: " + missingMappings.join(", "));
    return;
  }

  state.processedRows = [];
  state.warnings = [];
  state.stats.validRows = 0;
  state.stats.invalidRows = 0;
  state.stats.exportedRows = 0;
  warningsEl.classList.add("hidden");
  warningsEl.textContent = "";
  downloadBtn.disabled = true;
  previewTable.innerHTML = "";
  previewMeta.textContent = "";

  const mode = getInvalidMode();
  const total = state.rawRows.length;
  let index = 0;
  const chunkSize = 250;

  progressWrap.classList.remove("hidden");
  updateProgress(0);

  function processChunk() {
    const end = Math.min(index + chunkSize, total);

    for (; index < end; index += 1) {
      const inputRow = state.rawRows[index];
      const transformed = {};
      const rowIssues = [];

      REQUIRED_SCHEMA.forEach((field) => {
        const mappedHeader = state.mappings[field.key];
        const rawValue = mappedHeader ? inputRow[mappedHeader] : "";
        const value = buildFieldValue(field, inputRow, rawValue);
        transformed[field.label] = value;

        if (field.required && !value) {
          rowIssues.push(field.label + " is required");
        } else if (value && field.type === "email" && !isEmailValid(value)) {
          rowIssues.push("Invalid email format");
        } else if (value && field.type === "number" && !isNumeric(value)) {
          rowIssues.push(field.label + " must be numeric");
        }
      });

      if (rowIssues.length) {
        state.stats.invalidRows += 1;
        const warning = "Row " + (index + 2) + ": " + rowIssues.join("; ");
        state.warnings.push(warning);

        if (mode === "include") {
          transformed.Warning = rowIssues.join(" | ");
          state.processedRows.push(transformed);
          state.stats.exportedRows += 1;
        }
      } else {
        state.stats.validRows += 1;
        if (mode === "include") {
          transformed.Warning = "";
        }
        state.processedRows.push(transformed);
        state.stats.exportedRows += 1;
      }
    }

    updateProgress(Math.round((index / total) * 100));

    if (index < total) {
      window.requestAnimationFrame(processChunk);
    } else {
      finishProcessing(mode);
    }
  }

  if (total === 0) {
    finishProcessing(mode);
    return;
  }

  window.requestAnimationFrame(processChunk);
}

function finishProcessing(mode) {
  updateProgress(100);

  const maxWarningsToShow = 8;
  if (state.warnings.length) {
    warningsEl.classList.remove("hidden");
    const truncated = state.warnings.slice(0, maxWarningsToShow);
    const remaining = Math.max(0, state.warnings.length - maxWarningsToShow);
    warningsEl.innerHTML =
      "<strong>Warnings:</strong><br>" +
      truncated.join("<br>") +
      (remaining ? "<br>... and " + remaining + " more warnings." : "");
  } else {
    warningsEl.classList.add("hidden");
  }

  statsEl.textContent =
    "Total rows: " + state.stats.totalRows +
    " | Valid: " + state.stats.validRows +
    " | Invalid: " + state.stats.invalidRows +
    " | Exported: " + state.stats.exportedRows;

  renderPreview(state.processedRows, mode);
  downloadBtn.disabled = state.processedRows.length === 0;
}

function transformValue(value, type) {
  const text = String(value == null ? "" : value).trim();
  if (!text) return "";

  if (type === "number") {
    const normalized = text.replace(/,/g, "");
    return normalized;
  }
  if (type === "boolean") {
    const lowered = text.toLowerCase();
    if (["true", "yes", "1"].includes(lowered)) return "true";
    if (["false", "no", "0"].includes(lowered)) return "false";
    return "";
  }

  return text;
}

function buildFieldValue(field, row, directRawValue) {
  if (state.mappings[field.key]) {
    if (field.key === "phone" || field.key === "mobile") {
      return normalizePhoneNumber(directRawValue);
    }
    return transformValue(directRawValue, field.type);
  }

  if (field.key === "lead_owner") {
    return String(leadOwnerInput.value || "").trim();
  }

  if (field.key === "first_name" || field.key === "last_name") {
    const fullNameHeader = getMappedHeaderByNormalizedName("fullname");
    const fullName = fullNameHeader ? String(row[fullNameHeader] || "").trim() : "";
    if (!fullName) return "";
    const parts = fullName.split(/\s+/);
    const lastName = parts.slice(1).join(" ");
    if (field.key === "first_name") {
      if (!lastName) return fullName;
      return parts[0] || "";
    }
    return lastName;
  }

  if (field.key === "company") {
    const companyHeader = getMappedHeaderByNormalizedName("companyname");
    if (companyHeader) return transformValue(row[companyHeader], "text");
  }

  if (field.key === "title") {
    const titleHeader = getMappedHeaderByNormalizedName("jobtitle");
    if (titleHeader) return transformValue(row[titleHeader], "text");
  }

  if (field.key === "lead_status") {
    return "Contacted";
  }

  if (field.key === "lead_source") {
    const sourceHeader = getMappedHeaderByNormalizedName("platform");
    const campaignHeader = getMappedHeaderByNormalizedName("campaignname");
    const source = sourceHeader ? String(row[sourceHeader] || "").trim().toUpperCase() : "";
    const campaign = campaignHeader ? String(row[campaignHeader] || "").trim() : "";
    if (source && campaign) return source + " - " + campaign;
    if (source) return source;
    if (campaign) return campaign;
  }

  if (field.key === "country") {
    const countryHeader = getMappedHeaderByNormalizedName("nation");
    if (countryHeader) return transformValue(row[countryHeader], "text");
  }

  if (field.key === "description") {
    const goalHeader = getMappedHeaderByNormalizedName("whatisyourmaingoalfromsocialmediamarketing");
    const runningHeader = getMappedHeaderByNormalizedName("doyoucurrentlyrundigitalmarketingcampaigns");
    const businessHeader = getMappedHeaderByNormalizedName("whichbestdescribesyourbusiness");
    const goal = goalHeader ? String(row[goalHeader] || "").trim() : "";
    const running = runningHeader ? String(row[runningHeader] || "").trim() : "";
    const business = businessHeader ? String(row[businessHeader] || "").trim() : "";
    const parts = [];
    if (goal) parts.push("Goal: " + goal);
    if (running) parts.push("Current Campaigns: " + running);
    if (business) parts.push("Business Type: " + business);
    return parts.join(" | ");
  }

  return "";
}

function normalizePhoneNumber(value) {
  const text = String(value == null ? "" : value).trim();
  if (!text) return "";
  return text.replace(/\D/g, "");
}

function getMappedHeaderByNormalizedName(normalizedName) {
  const candidates = state.sourceHeaders.filter(
    (h) => normalize(h) === normalizedName
  );
  return candidates.length ? candidates[0] : "";
}

function canResolveRequiredField(field) {
  if (state.mappings[field.key]) return true;

  if (field.key === "lead_owner") {
    return String(leadOwnerInput.value || "").trim().length > 0;
  }

  if (field.key === "company") {
    return Boolean(getMappedHeaderByNormalizedName("companyname"));
  }

  if (field.key === "first_name" || field.key === "last_name") {
    return Boolean(getMappedHeaderByNormalizedName("fullname"));
  }

  return false;
}

function isEmailValid(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function isNumeric(value) {
  return /^-?\d+(\.\d+)?$/.test(value);
}

function updateProgress(percent) {
  progressBarFill.style.width = percent + "%";
  progressText.textContent = percent + "%";
}

function renderPreview(rows, mode) {
  previewTable.innerHTML = "";
  const previewRows = rows.slice(0, 20);
  if (!previewRows.length) {
    previewMeta.textContent = "No rows available for preview.";
    return;
  }

  const headers = REQUIRED_SCHEMA.map((f) => f.label);
  if (mode === "include") headers.push("Warning");

  const thead = document.createElement("thead");
  const headRow = document.createElement("tr");
  headers.forEach((header) => {
    const th = document.createElement("th");
    th.textContent = header;
    headRow.appendChild(th);
  });
  thead.appendChild(headRow);
  previewTable.appendChild(thead);

  const tbody = document.createElement("tbody");
  previewRows.forEach((row) => {
    const tr = document.createElement("tr");
    headers.forEach((header) => {
      const td = document.createElement("td");
      td.textContent = row[header] || "";
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  previewTable.appendChild(tbody);

  previewMeta.textContent = "Previewing " + previewRows.length + " of " + rows.length + " rows.";
}

function downloadOutputCsv() {
  if (!state.processedRows.length) return;
  const csv = Papa.unparse(state.processedRows);
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = buildOutputFileName(state.fileName);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function buildOutputFileName(inputName) {
  const base = (inputName || "converted")
    .replace(/\.csv$/i, "")
    .replace(/[^a-zA-Z0-9_-]/g, "_");
  return base + "_cleaned.csv";
}

function saveMapping() {
  if (!state.sourceHeaders.length) {
    alert("Upload a CSV first.");
    return;
  }
  const payload = {
    sourceHeaders: state.sourceHeaders,
    mappings: state.mappings
  };
  sessionStorage.setItem("csvMapping", JSON.stringify(payload));
  alert("Mapping saved for this browser session.");
}

function saveLeadOwnerEmail() {
  sessionStorage.setItem("leadOwnerEmail", String(leadOwnerInput.value || "").trim());
}

function loadLeadOwnerEmail() {
  const email = sessionStorage.getItem("leadOwnerEmail");
  if (email) {
    leadOwnerInput.value = email;
  }
}

function loadSavedMapping() {
  const raw = sessionStorage.getItem("csvMapping");
  if (!raw) {
    alert("No saved mapping found in this session.");
    return;
  }
  try {
    const parsed = JSON.parse(raw);
    if (!parsed.mappings || typeof parsed.mappings !== "object") {
      throw new Error("Invalid mapping payload");
    }
    state.mappings = { ...state.mappings, ...parsed.mappings };
    renderMappingSelectors();
    alert("Saved mapping loaded.");
  } catch (error) {
    alert("Could not load mapping: " + error.message);
  }
}

function resetApp() {
  state.fileName = "";
  state.rawRows = [];
  state.sourceHeaders = [];
  state.mappings = {};
  state.processedRows = [];
  state.warnings = [];
  state.stats = { totalRows: 0, validRows: 0, invalidRows: 0, exportedRows: 0 };

  fileInput.value = "";
  fileNameEl.textContent = "";
  processBtn.disabled = true;
  downloadBtn.disabled = true;
  autoMapBtn.disabled = true;
  quickConvertBtn.disabled = true;
  renderMappingSelectors();
  clearOutputUi();
}

function clearOutputUi() {
  previewTable.innerHTML = "";
  previewMeta.textContent = "";
  statsEl.textContent = "";
  warningsEl.classList.add("hidden");
  warningsEl.textContent = "";
  progressWrap.classList.add("hidden");
  updateProgress(0);
}
