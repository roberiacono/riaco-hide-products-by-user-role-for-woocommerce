jQuery(document).ready(function ($) {
  function renderRow(index, rule) {
    const roles = riacoData.roles;
    const categories = riacoData.categories;

    let roleOptions = "";
    for (const key in roles) {
      const selected = rule.role === key ? "selected" : "";
      roleOptions += `<option value="${key}" ${selected}>${roles[key].name}</option>`;
    }

    let categoryOptions = '<option value="">Select category</option>';
    categories.forEach((cat) => {
      const selected = rule.category == cat.term_id ? "selected" : "";
      categoryOptions += `<option value="${cat.term_id}" ${selected}>${cat.name}</option>`;
    });

    const showCategory =
      rule.target === "category" ? "" : 'style="display:none;"';

    const moveDisabledUp = index === 0 ? "wc-move-disabled" : "";
    const moveDisabledDown =
      index === riacoData.rules.length - 1 ? "wc-move-disabled" : "";

    return `<tr>
            <td class="priority">
            <div class="riaco-hpburfw-item-reorder-nav">
                    <button type="button" class="move-up ${moveDisabledUp}" aria-label="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
                    <button type="button" class="move-down ${moveDisabledDown}" aria-label="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                    <input type="hidden" name="riaco_visibility_rules[${index}][order]" value="${index}">
                </div>
            </td>
            <td>
                <select name="riaco_visibility_rules[${index}][role]">
                    ${roleOptions}
                </select>
            </td>
            <td>
                <select class="target-select" name="riaco_visibility_rules[${index}][target]">
                    <option value="all" ${
                      rule.target === "all" ? "selected" : ""
                    }>All Products</option>
                    <option value="category" ${
                      rule.target === "category" ? "selected" : ""
                    }>Category</option>
                </select>
            </td>
            <td>
                <select class="category-select" name="riaco_visibility_rules[${index}][category]" ${showCategory}>
                    ${categoryOptions}
                </select>
            </td>
            <td>
                <button type="button" class="button button-link remove-row">Remove</button>
            </td>
        </tr>`;
  }

  function refreshTable() {
    const tbody = $("#riaco-hpburfw-rules tbody");
    tbody.empty();
    riacoData.rules.forEach((rule, index) => {
      tbody.append(renderRow(index, rule));
    });
  }

  function addRow() {
    riacoData.rules.push({ role: "", target: "all", category: "" });
    refreshTable();
  }

  function moveUp(index) {
    if (index === 0) return;
    const rules = riacoData.rules;
    [rules[index - 1], rules[index]] = [rules[index], rules[index - 1]];
    refreshTable();
  }

  function moveDown(index) {
    const rules = riacoData.rules;
    if (index === rules.length - 1) return;
    [rules[index], rules[index + 1]] = [rules[index + 1], rules[index]];
    refreshTable();
  }

  function removeRow(index) {
    riacoData.rules.splice(index, 1);
    refreshTable();
  }

  // Initial render
  refreshTable();

  // Add new row
  $("#add-rule").click(addRow);

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
    riacoData.rules[index].target = $(this).val();
    refreshTable();
  });

  $("#riaco-hpburfw-rules tbody").on(
    "change",
    'select[name*="[role]"]',
    function () {
      const row = $(this).closest("tr");
      const index = row.index();
      riacoData.rules[index].role = $(this).val();
    }
  );

  $("#riaco-hpburfw-rules tbody").on(
    "change",
    'select[name*="[category]"]',
    function () {
      const row = $(this).closest("tr");
      const index = row.index();
      riacoData.rules[index].category = $(this).val();
    }
  );
});
