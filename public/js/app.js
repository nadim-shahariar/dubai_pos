(() => {
    const fmtTime = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dubai', hour: '2-digit', minute: '2-digit', hour12: false });
    const fmtDate = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dubai', weekday: 'short', day: '2-digit', month: 'short' });
    const tick = () => {
        const now = new Date();
        document.querySelectorAll('[data-clock]').forEach(el => el.textContent = fmtTime.format(now));
        document.querySelectorAll('[data-date]').forEach(el => el.textContent = fmtDate.format(now) + ' · Dubai');
    };
    tick();
    setInterval(tick, 15000);

    document.querySelectorAll('[data-confirm]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    document.querySelectorAll('.alert-success').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 450); }, 5000);
    });

    const variantCatalog = Array.isArray(window.VEHICLE_CATALOG)
        ? window.VEHICLE_CATALOG
        : (Array.isArray(window.POS_BOOT?.vehicles) ? window.POS_BOOT.vehicles : []);
    let variantIndex = 0;

    const ensureVariantDatalists = () => {
        if (!document.querySelector('#variant-brand-list')) {
            const brands = document.createElement('datalist');
            brands.id = 'variant-brand-list';
            variantCatalog.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand.name;
                brands.appendChild(option);
            });
            document.body.appendChild(brands);

            const models = document.createElement('datalist');
            models.id = 'variant-model-list';
            document.body.appendChild(models);
        }
    };

    const syncVariantModels = (brandInput, modelInput) => {
        const list = document.querySelector('#variant-model-list');
        if (!list) return;
        list.innerHTML = '';
        const brandName = brandInput.value.trim().toLowerCase();
        const brand = variantCatalog.find(b => String(b.name).toLowerCase() === brandName);
        (brand?.models || []).forEach(model => {
            const option = document.createElement('option');
            option.value = typeof model === 'string' ? model : model.name;
            list.appendChild(option);
        });
    };

    const escapeAttribute = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const addVariantRow = (tableId, values = {}) => {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (!tbody) return;
        ensureVariantDatalists();

        const index = variantIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input name="variants[${index}][vehicle_brand]" value="${escapeAttribute(values.vehicle_brand || '')}" list="variant-brand-list" placeholder="Toyota" autocomplete="off" data-variant-brand></td>
            <td><input name="variants[${index}][vehicle_model]" value="${escapeAttribute(values.vehicle_model || '')}" list="variant-model-list" placeholder="Camry" autocomplete="off" data-variant-model></td>
            <td><input name="variants[${index}][year_from]" type="number" min="1950" max="2100" value="${escapeAttribute(values.year_from || 2018)}" class="variant-year"></td>
            <td><input name="variants[${index}][year_to]" type="number" min="1950" max="2100" value="${escapeAttribute(values.year_to || 2024)}" class="variant-year"></td>
            <td><input name="variants[${index}][mapped_part_number]" value="${escapeAttribute(values.mapped_part_number || '')}" placeholder="Part number" maxlength="50"></td>
            <td><input name="variants[${index}][stock_qty]" type="number" step="0.001" min="0" value="${escapeAttribute(values.stock_qty ?? '')}" class="variant-stock" placeholder="Stock"></td>
            <td class="text-right"><button type="button" class="btn btn-ghost btn-sm text-danger" data-remove-variant>×</button></td>
        `;
        tbody.appendChild(tr);

        const brandInput = tr.querySelector('[data-variant-brand]');
        const modelInput = tr.querySelector('[data-variant-model]');
        brandInput.addEventListener('input', () => syncVariantModels(brandInput, modelInput));
        brandInput.addEventListener('change', () => { modelInput.value = ''; syncVariantModels(brandInput, modelInput); });
        modelInput.addEventListener('focus', () => syncVariantModels(brandInput, modelInput));
        tr.querySelector('[data-remove-variant]').addEventListener('click', () => {
            tr.remove();
            if (!tbody.rows.length) addVariantRow(tableId);
        });
    };

    const resetVariantTable = (tableId) => {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (!tbody) return;
        tbody.innerHTML = '';
        addVariantRow(tableId);
    };

    const collectVariantRows = (tableId) => {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (!tbody) return [];
        return Array.from(tbody.rows).map(row => ({
            vehicle_brand: row.querySelector('[data-variant-brand]')?.value.trim() || '',
            vehicle_model: row.querySelector('[data-variant-model]')?.value.trim() || '',
            year_from: Number(row.querySelector('input[name*="[year_from]"]')?.value || 0),
            year_to: Number(row.querySelector('input[name*="[year_to]"]')?.value || 0),
            mapped_part_number: row.querySelector('input[name*="[mapped_part_number]"]')?.value.trim() || '',
            stock_qty: row.querySelector('input[name*="[stock_qty]"]')?.value === '' ? null : Number(row.querySelector('input[name*="[stock_qty]"]')?.value),
        })).filter(row => row.vehicle_brand || row.vehicle_model);
    };

    window.addVariantRow = addVariantRow;
    window.resetVariantTable = resetVariantTable;
    window.collectVariantRows = collectVariantRows;

    document.querySelectorAll('[data-add-variant]').forEach(button => {
        button.addEventListener('click', () => addVariantRow(button.dataset.addVariant));
    });
    document.querySelectorAll('[data-variant-table]').forEach(table => {
        if (!table.querySelector('tbody')?.rows.length) addVariantRow(table.id);
    });

    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.add('hidden'));
            btn.classList.add('active');
            document.querySelector(`[data-tab-panel="${btn.dataset.tab}"]`)?.classList.remove('hidden');
        });
    });
})();
