jQuery(document).ready(function ($) {
  console.log("riaco_hpburfw_data:", riaco_hpburfw_data);

  function renderRow(index, rule) {
    const roles = riaco_hpburfw_data.roles;
    const targets = riaco_hpburfw_data.targets;

    // Ensure rule.terms always exists (avoid undefined errors)
    rule.terms = rule.terms || [];

    // Build role <select>
    let roleOptions = "";
    for (const key in roles) {
      const selected = rule.role === key ? "selected" : "";
      roleOptions += `<option value="${key}" ${selected}>${roles[key].name}</option>`;
    }

    // Build target <select>
    let targetOptions = "";
    targets.forEach((target) => {
      const selected = rule.target === target.id ? "selected" : "";
      targetOptions += `<option value="${target.id}" ${selected}>${target.label}</option>`;
    });

    const selectedTarget = targets.find((t) => t.id === rule.target);
    const hasTerms = selectedTarget?.terms?.length > 0;

    // Recursive renderer for nested checkboxes (like WooCommerce product categories)
    function renderTerms(terms, level = 0) {
      return terms
        .map((term) => {
          const checked = rule.terms.includes(term.term_id.toString())
            ? "checked"
            : "";
          const hasChildren =
            Array.isArray(term.children) && term.children.length > 0;
          const margin = level * 20;

          return `
            <li class="term-item" style="margin-left:${margin}px;">
              <label>
                <input type="checkbox" 
                       name="riaco_hpburfw_rules[${index}][terms][]" 
                       value="${term.term_id}" 
                       ${checked}>
                ${term.name}
              </label>
              ${
                hasChildren
                  ? `<ul class="children">${renderTerms(
                      term.children,
                      level + 1
                    )}</ul>`
                  : ""
              }
            </li>
          `;
        })
        .join("");
    }

    // Render list of terms (if available)
    const termsList = hasTerms
      ? `<ul class="term-checklist">${renderTerms(selectedTarget.terms)}</ul>`
      : ``;

    return `
      <tr>
        <td class="priority">
          <div class="riaco-hpburfw-item-reorder-nav">
            <button type="button" class="move-up ${
              index === 0 ? "wc-move-disabled" : ""
            }" aria-label="Move up">
              <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
            <button type="button" class="move-down ${
              index === riaco_hpburfw_data.rules.length - 1
                ? "wc-move-disabled"
                : ""
            }" aria-label="Move down">
              <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <input type="hidden" name="riaco_hpburfw_rules[${index}][order]" value="${index}">
          </div>
        </td>
  
        <td>
          <select name="riaco_hpburfw_rules[${index}][role]">
            ${roleOptions}
          </select>
        </td>
  
        <td>
          <select class="target-select" name="riaco_hpburfw_rules[${index}][target]">
            ${targetOptions}
          </select>
        </td>
  
        <td class="terms-column">
          <div class="${
            termsList ? "term-checkbox-tree" : ""
          }">${termsList}</div>
        </td>
  
        <td>
          <button type="button" class="button button-link remove-row">Remove</button>
        </td>
      </tr>
    `;
  }

  function refreshTable() {
    const tbody = $("#riaco-hpburfw-rules tbody");
    tbody.empty();
    riaco_hpburfw_data.rules.forEach((rule, index) => {
      tbody.append(renderRow(index, rule));
    });
  }

  function addRow() {
    riaco_hpburfw_data.rules.push({
      role: "",
      target: "all_products",
      category: "",
    });
    refreshTable();
  }

  function moveUp(index) {
    if (index === 0) return;
    const rules = riaco_hpburfw_data.rules;
    [rules[index - 1], rules[index]] = [rules[index], rules[index - 1]];
    refreshTable();
  }

  function moveDown(index) {
    const rules = riaco_hpburfw_data.rules;
    if (index === rules.length - 1) return;
    [rules[index], rules[index + 1]] = [rules[index + 1], rules[index]];
    refreshTable();
  }

  function removeRow(index) {
    riaco_hpburfw_data.rules.splice(index, 1);
    refreshTable();
  }

  // Initial render
  refreshTable();

  // Add new row
  $("#add-rule").on("click", addRow);

  // Delegate actions
  $("#riaco-hpburfw-rules tbody").on("click", ".move-up", function () {
    const index = $(this).closest("tr").index();
    moveUp(index);
  });

  $("#riaco-hpburfw-rules tbody").on("click", ".move-down", function () {
    const index = $(this).closest("tr").index();
    moveDown(index);
  });

  $("#riaco-hpburfw-rules tbody").on("click", ".remove-row", function () {
    const index = $(this).closest("tr").index();
    removeRow(index);
  });

  $("#riaco-hpburfw-rules tbody").on("change", ".target-select", function () {
    const row = $(this).closest("tr");
    const index = row.index();
    riaco_hpburfw_data.rules[index].target = $(this).val();
    refreshTable();
  });

  $("#riaco-hpburfw-rules tbody").on(
    "change",
    'select[name*="[role]"]',
    function () {
      const row = $(this).closest("tr");
      const index = row.index();
      riaco_hpburfw_data.rules[index].role = $(this).val();
    }
  );

  $("#riaco-hpburfw-rules tbody").on(
    "change",
    'select[name*="[category]"]',
    function () {
      const row = $(this).closest("tr");
      const index = row.index();
      riaco_hpburfw_data.rules[index].category = $(this).val();
    }
  );
});
