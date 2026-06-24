/**
 * Axer CMS Product Variants Manager (Shopify Style)
 */

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('variants-manager')) return;

    // We expect INITIAL_OPTIONS and INITIAL_VARIANTS to be defined globally by the PHP view
    let options = window.INITIAL_OPTIONS || [];
    let variants = window.INITIAL_VARIANTS || [];
    
    const container = document.getElementById('variants-manager');
    const inputPayload = document.getElementById('variants_payload');
    
    // UI Elements
    const optionsCard = document.getElementById('vm-options-card');
    const variantsCard = document.getElementById('vm-variants-card');
    const bulkToolbar = document.getElementById('vm-bulk-toolbar');
    
    // Drawer Elements
    const drawer = document.getElementById('vm-drawer');
    const drawerOverlay = document.getElementById('vm-drawer-overlay');
    const drawerClose = document.getElementById('vm-drawer-close');
    const drawerForm = document.getElementById('vm-drawer-form');
    let currentlyEditingVariantId = null;

    // Helper to generate a temp ID
    const generateId = () => 'temp_' + Math.random().toString(36).substr(2, 9);

    function updatePayload() {
        if (inputPayload) {
            inputPayload.value = JSON.stringify({ options, variants });
        }
    }

    function renderOptions() {
        let html = '';
        if (options.length === 0) {
            html = `
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    <p>This product doesn't have options like Size or Color.</p>
                    <button type="button" class="btn btn-secondary" onclick="window.vmAddOption()">+ Add options like Size or Color</button>
                </div>
            `;
        } else {
            options.forEach((opt, index) => {
                html += `
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="font-weight: 500;">Option name</label>
                            <button type="button" class="btn" style="color: var(--danger); background: transparent; padding: 0;" onclick="window.vmRemoveOption(${index})">Remove</button>
                        </div>
                        <input type="text" class="vm-input" value="${opt.name}" onchange="window.vmUpdateOptionName(${index}, this.value)" placeholder="e.g. Size" style="margin-bottom: 1rem; width: 100%;">
                        
                        <label style="font-weight: 500;">Option values</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                            ${opt.values.map((val, vIdx) => `
                                <span style="display: inline-flex; align-items: center; background: var(--bg-content); border: 1px solid var(--border-color); padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.875rem;">
                                    ${val}
                                    <button type="button" style="background: none; border: none; cursor: pointer; margin-left: 0.25rem; color: var(--text-muted);" onclick="window.vmRemoveOptionValue(${index}, ${vIdx})">&times;</button>
                                </span>
                            `).join('')}
                            <input type="text" class="vm-input" placeholder="Add value" style="min-width: 120px;" onkeydown="if(event.key === 'Enter') { event.preventDefault(); window.vmAddOptionValue(${index}, this.value); this.value=''; }">
                        </div>
                    </div>
                `;
            });
            if (options.length < 3) {
                html += `
                    <div style="padding: 1rem 1.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="window.vmAddOption()">+ Add another option</button>
                    </div>
                `;
            }
        }
        optionsCard.innerHTML = html;
        updatePayload();
    }

    function generateCombinations() {
        if (options.length === 0) return [];
        let combos = [[]];
        for (let i = 0; i < options.length; i++) {
            const currentOptionValues = options[i].values;
            if (currentOptionValues.length === 0) continue;
            let temp = [];
            for (let j = 0; j < combos.length; j++) {
                for (let k = 0; k < currentOptionValues.length; k++) {
                    temp.push([...combos[j], currentOptionValues[k]]);
                }
            }
            combos = temp;
        }
        // If a combo is empty array (e.g. options without values), return empty
        if (combos.length > 0 && combos[0].length === 0) return [];
        return combos;
    }

    function syncVariants() {
        const combos = generateCombinations();
        
        if (combos.length > 100) {
            alert('Cannot generate more than 100 variants.');
            return;
        }

        const newVariants = [];
        combos.forEach(combo => {
            // Check if this combo already exists in the variants array
            const existing = variants.find(v => {
                return v.option1_value === (combo[0] || null) &&
                       v.option2_value === (combo[1] || null) &&
                       v.option3_value === (combo[2] || null);
            });

            if (existing) {
                newVariants.push(existing);
            } else {
                newVariants.push({
                    id: generateId(),
                    option1_value: combo[0] || null,
                    option2_value: combo[1] || null,
                    option3_value: combo[2] || null,
                    price_override: null,
                    compare_price: null,
                    sku: '',
                    barcode: '',
                    stock: 0,
                    is_active: 1,
                    image: '',
                    color_hex: ''
                });
            }
        });

        // Retain existing variants if there are NO options defined (fallback for manual variants without options, though we shouldn't really support this in the new model)
        if (options.length === 0) {
            variants = variants.filter(v => !v.option1_value && !v.option2_value && !v.option3_value);
        } else {
            variants = newVariants;
        }
        
        renderVariants();
    }

    function renderVariants() {
        if (variants.length === 0) {
            variantsCard.style.display = 'none';
            return;
        }
        variantsCard.style.display = 'block';

        const tbody = document.getElementById('vm-variants-tbody');
        const countBadge = document.getElementById('vm-variants-count');
        countBadge.textContent = `${variants.length} total`;

        let html = '';
        variants.forEach(v => {
            const labels = [v.option1_value, v.option2_value, v.option3_value].filter(Boolean);
            const variantName = labels.join(' / ');
            
            const priceDisplay = v.price_override 
                ? `$${parseFloat(v.price_override).toFixed(2)}` 
                : `<span style="color:var(--text-muted)">Inherited</span>`;

            html += `
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;"><input type="checkbox" class="vm-row-checkbox" value="${v.id}"></td>
                    <td style="padding: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        ${v.image ? `<img src="${v.image}" style="width:32px; height:32px; border-radius:0.25rem; object-fit:cover;">` : `<div style="width:32px;height:32px;border-radius:0.25rem;background:var(--bg-content);border:1px dashed var(--border-color);display:flex;align-items:center;justify-content:center;"><i data-lucide="image" style="width:14px;color:var(--text-muted)"></i></div>`}
                        <strong>${variantName}</strong>
                    </td>
                    <td style="padding: 1rem;">${priceDisplay}</td>
                    <td style="padding: 1rem;">${v.stock} in stock</td>
                    <td style="padding: 1rem;">${v.sku || '-'}</td>
                    <td style="padding: 1rem; text-align: right;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="window.vmEditVariant('${v.id}')">Edit</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.lucide) lucide.createIcons();
        updatePayload();
    }

    // --- Global Actions exposed to window ---

    window.vmAddOption = function() {
        if (options.length >= 3) return;
        const defaultNames = ["Size", "Color", "Material"];
        let name = "Option " + (options.length + 1);
        for (let dn of defaultNames) {
            if (!options.find(o => o.name === dn)) {
                name = dn;
                break;
            }
        }
        options.push({ name: name, values: [] });
        renderOptions();
    };

    window.vmRemoveOption = function(index) {
        if (confirm("Deleting this option will remove all associated variant data. This cannot be undone.")) {
            options.splice(index, 1);
            renderOptions();
            syncVariants();
        }
    };

    window.vmUpdateOptionName = function(index, newName) {
        if (!newName.trim()) return;
        if (options.find((o, i) => o.name === newName && i !== index)) {
            alert('Option names must be unique.');
            renderOptions();
            return;
        }
        options[index].name = newName.trim();
        updatePayload();
    };

    window.vmAddOptionValue = function(optIndex, value) {
        val = value.trim();
        if (!val) return;
        if (options[optIndex].values.includes(val)) {
            alert('Value already exists in this option.');
            return;
        }
        options[optIndex].values.push(val);
        renderOptions();
        syncVariants();
    };

    window.vmRemoveOptionValue = function(optIndex, valIndex) {
        options[optIndex].values.splice(valIndex, 1);
        renderOptions();
        syncVariants();
    };

    // --- Drawer Logic ---

    window.vmEditVariant = function(id) {
        currentlyEditingVariantId = id;
        const v = variants.find(va => va.id == id);
        if (!v) return;

        const labels = [v.option1_value, v.option2_value, v.option3_value].filter(Boolean);
        document.getElementById('vm-drawer-title').textContent = labels.join(' / ');

        document.getElementById('vm-drawer-price').value = v.price_override || '';
        document.getElementById('vm-drawer-compare-price').value = v.compare_price || '';
        document.getElementById('vm-drawer-sku').value = v.sku || '';
        document.getElementById('vm-drawer-barcode').value = v.barcode || '';
        document.getElementById('vm-drawer-stock').value = v.stock || 0;
        document.getElementById('vm-drawer-weight').value = v.weight || '';
        document.getElementById('vm-drawer-image').value = v.image || '';
        
        const preview = document.getElementById('vm-drawer-image-preview');
        if (v.image) {
            preview.innerHTML = `<img src="${v.image}" style="width:100%; height:100%; object-fit:cover;">`;
        } else {
            preview.innerHTML = `<i data-lucide="image" style="color:var(--text-muted)"></i>`;
            if (window.lucide) lucide.createIcons();
        }

        drawer.classList.add('active');
        drawerOverlay.classList.add('active');
    };

    function closeDrawer() {
        drawer.classList.remove('active');
        drawerOverlay.classList.remove('active');
        currentlyEditingVariantId = null;
    }

    drawerClose.addEventListener('click', closeDrawer);
    drawerOverlay.addEventListener('click', closeDrawer);

    window.vmSaveDrawer = function() {
        if (!currentlyEditingVariantId) return;
        const v = variants.find(va => va.id == currentlyEditingVariantId);
        if (v) {
            v.price_override = document.getElementById('vm-drawer-price').value || null;
            v.compare_price = document.getElementById('vm-drawer-compare-price').value || null;
            v.sku = document.getElementById('vm-drawer-sku').value;
            v.barcode = document.getElementById('vm-drawer-barcode').value;
            v.stock = parseInt(document.getElementById('vm-drawer-stock').value) || 0;
            v.weight = document.getElementById('vm-drawer-weight').value || null;
            v.image = document.getElementById('vm-drawer-image').value;
            
            renderVariants();
        }
        closeDrawer();
    };

    // --- Bulk Actions ---
    const selectAllCb = document.getElementById('vm-select-all');
    if (selectAllCb) {
        selectAllCb.addEventListener('change', (e) => {
            document.querySelectorAll('.vm-row-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
            updateBulkToolbar();
        });
    }

    document.getElementById('vm-variants-tbody').addEventListener('change', (e) => {
        if (e.target.classList.contains('vm-row-checkbox')) {
            updateBulkToolbar();
        }
    });

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.vm-row-checkbox:checked')).map(cb => cb.value);
    }

    function updateBulkToolbar() {
        const selected = getSelectedIds();
        bulkToolbar.style.display = selected.length > 0 ? 'flex' : 'none';
        if (selectAllCb) {
            selectAllCb.checked = selected.length > 0 && selected.length === document.querySelectorAll('.vm-row-checkbox').length;
        }
    }

    window.vmBulkEditPrices = function() {
        const newPrice = prompt("Enter new price for selected variants:");
        if (newPrice !== null) {
            const ids = getSelectedIds();
            variants.forEach(v => {
                if (ids.includes(v.id.toString())) {
                    v.price_override = newPrice === "" ? null : newPrice;
                }
            });
            renderVariants();
            document.querySelectorAll('.vm-row-checkbox').forEach(cb => cb.checked = false);
            updateBulkToolbar();
        }
    };

    window.vmBulkUpdateStock = function() {
        const newStock = prompt("Enter new stock quantity for selected variants:");
        if (newStock !== null) {
            const ids = getSelectedIds();
            variants.forEach(v => {
                if (ids.includes(v.id.toString())) {
                    v.stock = parseInt(newStock) || 0;
                }
            });
            renderVariants();
            document.querySelectorAll('.vm-row-checkbox').forEach(cb => cb.checked = false);
            updateBulkToolbar();
        }
    };

    window.vmBulkDelete = function() {
        if (confirm("Delete selected variants?")) {
            const ids = getSelectedIds();
            variants = variants.filter(v => !ids.includes(v.id.toString()));
            renderVariants();
            updateBulkToolbar();
        }
    };

    // Initialize
    renderOptions();
    renderVariants();
});
