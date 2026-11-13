(() => {
    const boot = window.POS_BOOT;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const money = (value) => 'AED ' + Number(value || 0).toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
    const rate = Number(boot.tax_rate || 5);
    const inclusive = !!boot.prices_include_vat;

    const state = { shift: boot.shift, payments: [], rowSeq: 0, lastSale: null, activeRow: null, activeResults: [], recent: {} };

    const toast = (message, type = 'success') => {
        const el = document.createElement('div');
        el.className = `toast-item ${type}`;
        el.textContent = message;
        $('#toast').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, 4200);
    };

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': boot.csrf,
                ...(options.headers || {}),
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(validation || data.message || 'Something went wrong.');
        }
        return data;
    };

    const openModal = (id) => $('#' + id)?.classList.add('open');
    const closeModal = (id) => $('#' + id)?.classList.remove('open');
    const closeAll = () => $$('.modal-backdrop.open').forEach(el => el.classList.remove('open'));

    // ---------------------------------------------------------------- customer
    const customerSelect = $('#customer-select');
    const customer = () => {
        const option = customerSelect.options[customerSelect.selectedIndex];
        if (!option || !option.value) return null;
        return {
            id: Number(option.value),
            name: option.dataset.name || option.textContent.trim(),
            trn: option.dataset.trn || null,
            car_plate_number: option.dataset.plate || null,
            credit_limit: Number(option.dataset.credit || 0),
            outstanding: Number(option.dataset.outstanding || 0),
        };
    };
    const syncCustomerMeta = () => {
        const c = customer();
        $('#customer-meta').textContent = c
            ? `${c.trn ? 'TRN ' + c.trn : 'No TRN'} · credit limit ${money(c.credit_limit)} · outstanding ${money(c.outstanding)}`
            : 'Cash sale · no TRN';
    };
    customerSelect.addEventListener('change', () => {
        syncCustomerMeta();
        $('#invoice-car-plate').value = customer()?.car_plate_number || '';
        $('#invoice-person-name').value = '';
    });
    syncCustomerMeta();


    // ---------------------------------------------------------------- new customer
    const openNewCustomer = () => {
        $('#customer-form').reset();
        openModal('customer-modal');
        setTimeout(() => $('#new-customer-name').focus(), 100);
    };
    $('#new-customer-btn').addEventListener('click', openNewCustomer);
    $('#customer-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const payload = {};
        form.forEach((value, key) => { if (value !== '') payload[key] = value; });
        try {
            const data = await api('/api/pos/customers', { method: 'POST', body: JSON.stringify(payload) });
            const c = data.customer;
            const option = new Option(c.name + (c.trn ? ' — ' + c.trn : ''), c.id);
            option.dataset.name = c.name;
            option.dataset.trn = c.trn || '';
            option.dataset.plate = c.car_plate_number || '';
            option.dataset.credit = c.credit_limit;
            option.dataset.outstanding = c.outstanding;
            customerSelect.appendChild(option);
            customerSelect.value = String(c.id);
            $('#invoice-car-plate').value = c.car_plate_number || '';
            syncCustomerMeta();
            closeModal('customer-modal');
            toast('Customer ' + c.name + ' created and selected.');
        } catch (err) { toast(err.message, 'error'); }
    });


    // ---------------------------------------------------------------- recent transactions
    const loadRecentSales = async () => {
        try {
            const data = await api('/api/pos/recent-sales');
            state.recent = {};
            (data.data || []).forEach(sale => { state.recent[sale.id] = sale; });
            renderRecentSales(data.data || []);
        } catch (e) {
            $('#recent-list').innerHTML = '<div class="recent-empty">Could not load recent invoices.</div>';
        }
    };

    const renderRecentSales = (sales) => {
        if (!sales.length) {
            $('#recent-list').innerHTML = '<div class="recent-empty">No recent transactions yet.</div>';
            return;
        }
        $('#recent-list').innerHTML = sales.map(sale => {
            const status = sale.status === 'voided'
                ? '<span class="badge badge-red">Voided</span>'
                : (sale.balance_due > 0 ? '<span class="badge badge-gold">Credit</span>' : '<span class="badge badge-green">Paid</span>');
            return `<div class="recent-item">
                <div class="recent-invoice">${esc(sale.invoice_number)}</div>
                <div class="recent-customer" title="${esc(sale.customer_name)}">${esc(sale.customer_name)}</div>
                <div class="recent-total">${money(sale.total)}</div>
                <div>${status}</div>
                <div class="recent-actions">
                    <a href="${sale.invoice_url}" target="_blank">Print</a>
                    ${sale.can_edit ? `<button type="button" data-edit-sale="${sale.id}">Edit</button>` : ''}
                    ${sale.can_void ? `<button type="button" class="delete" data-void-sale="${sale.id}">Delete</button>` : ''}
                </div>
            </div>`;
        }).join('');
    };

    $('#recent-list').addEventListener('click', (event) => {
        const edit = event.target.closest('[data-edit-sale]');
        const remove = event.target.closest('[data-void-sale]');
        if (edit) openEditSale(state.recent[Number(edit.dataset.editSale)]);
        if (remove) openVoidSale(state.recent[Number(remove.dataset.voidSale)]);
    });

    let editingSale = null;
    const openEditSale = (sale) => {
        if (!sale) return;
        editingSale = sale;
        $('#edit-sale-id').value = sale.id;
        $('#edit-sale-title').textContent = sale.invoice_number + ' · ' + money(sale.total);
        $('#edit-sale-customer').value = sale.customer_id ? String(sale.customer_id) : '';
        $('#edit-sale-customer').disabled = Number(sale.balance_due) > 0;
        $('#edit-sale-customer-hint').textContent = Number(sale.balance_due) > 0
            ? 'Customer cannot be changed while this invoice has a credit balance.'
            : 'Customer can be changed before any credit collection exists.';
        $('#edit-sale-vehicle').value = sale.vehicle || '';
        $('#edit-sale-notes').value = sale.notes || '';
        $('#edit-sale-error').classList.add('hidden');
        openModal('edit-sale-modal');
    };

    $('#edit-sale-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!editingSale) return;
        try {
            await api('/api/pos/sales/' + editingSale.id, {
                method: 'PATCH',
                body: JSON.stringify({
                    customer_id: $('#edit-sale-customer').value || null,
                    vehicle: $('#edit-sale-vehicle').value || null,
                    notes: $('#edit-sale-notes').value || null,
                }),
            });
            closeModal('edit-sale-modal');
            toast('Transaction updated.');
            loadRecentSales();
        } catch (e) {
            const box = $('#edit-sale-error');
            box.textContent = e.message;
            box.classList.remove('hidden');
        }
    });

    let voidingSale = null;
    const openVoidSale = (sale) => {
        if (!sale) return;
        voidingSale = sale;
        $('#void-sale-id').value = sale.id;
        $('#void-sale-title').textContent = 'Void ' + sale.invoice_number + ' · ' + money(sale.total) + '?';
        $('#void-sale-reason').value = '';
        const pin = $('#void-sale-pin');
        if (pin) pin.value = '';
        $('#void-sale-error').classList.add('hidden');
        openModal('void-sale-modal');
    };

    $('#void-sale-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!voidingSale) return;
        try {
            await api('/api/pos/sales/' + voidingSale.id + '/void', {
                method: 'POST',
                body: JSON.stringify({
                    reason: $('#void-sale-reason').value,
                    approval_pin: $('#void-sale-pin')?.value || null,
                }),
            });
            closeModal('void-sale-modal');
            toast('Invoice voided and stock restored.');
            loadRecentSales();
        } catch (e) {
            const box = $('#void-sale-error');
            box.textContent = e.message;
            box.classList.remove('hidden');
        }
    });

    document.querySelectorAll('[data-close-edit-sale]').forEach(button => button.addEventListener('click', () => closeModal('edit-sale-modal')));
    document.querySelectorAll('[data-close-void-sale]').forEach(button => button.addEventListener('click', () => closeModal('void-sale-modal')));

    // ---------------------------------------------------------------- hidden buying price
    let costRow = null;
    const openBuyingPrice = (tr) => {
        if (!boot.user?.can_view_cost) return;
        costRow = tr;
        $('#buying-price-product').textContent = tr.querySelector('.entry-product').value + ' — the value is masked and never shown in the table.';
        $('#buying-price-input').value = tr._cost ?? '';
        openModal('buying-price-modal');
        setTimeout(() => $('#buying-price-input').focus(), 100);
    };
    $('#buying-price-save').addEventListener('click', () => {
        if (!costRow) return;
        const raw = $('#buying-price-input').value.trim();
        const value = raw === '' ? null : Number(raw);
        if (value !== null && (!Number.isFinite(value) || value < 0)) {
            toast('Buying price must be zero or more.', 'error');
            return;
        }
        costRow._cost = value;
        const btn = costRow.querySelector('.buying-price');
        btn.classList.toggle('cost-set', value !== null);
        btn.title = value === null ? 'Enter hidden buying price' : 'Buying price saved (hidden)';
        closeModal('buying-price-modal');
        toast(value === null ? 'Buying price cleared.' : 'Buying price saved hidden.');
        costRow = null;
    });

    // ---------------------------------------------------------------- shortcuts
    const openShortcuts = () => openModal('shortcuts-modal');
    $('#shortcuts-btn').addEventListener('click', openShortcuts);

    // ---------------------------------------------------------------- product search
    const suggest = $('#product-suggest');
    const hideSuggest = () => { suggest.classList.remove('open'); state.activeRow = null; };

    const searchProducts = async (row, term) => {
        if (!term.trim()) { hideSuggest(); return; }
        clearTimeout(row._searchTimer);
        row._searchTimer = setTimeout(async () => {
            try {
                const data = await api('/api/pos/search?q=' + encodeURIComponent(term));
                state.activeRow = row;
                state.activeResults = data.data || [];
                renderSuggest(term);
            } catch (e) {
                toast(e.message, 'error');
            }
        }, 200);
    };

    const renderSuggest = (term) => {
        const rect = state.activeRow?.querySelector('.entry-product')?.getBoundingClientRect();
        if (!rect) return;
        suggest.style.left = Math.max(8, rect.left) + 'px';
        suggest.style.top = (rect.bottom + 4) + 'px';
        suggest.style.width = Math.max(rect.width, 390) + 'px';
        suggest.innerHTML = state.activeResults.length
            ? state.activeResults.map((product, index) => `
                <div class="suggest-item" data-product-index="${index}">
                    <div class="right">${money(product.selling_price)}<div class="meta">${product.fitments?.length ? product.fitments.length + ' variants · select for stock' : 'Stock ' + Number(product.stock_qty)}</div></div>
                    <div class="name">${esc(product.name)}</div>
                    <div class="meta"><span class="mono">${esc(product.primary_part_number)}</span> · code ${esc(product.secret_cost_code)}${product.fitment_hint ? ' · ' + esc(product.fitment_hint) : ''}</div>
                </div>`).join('') + `
                <div class="suggest-item create" data-create-product="1">+ Create new product “${esc(term)}”</div>`
            : `<div class="suggest-empty">No product found.</div><div class="suggest-item create" data-create-product="1">+ Create new product “${esc(term)}”</div>`;
        suggest.classList.add('open');
    };

    suggest.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const item = e.target.closest('[data-product-index]');
        const create = e.target.closest('[data-create-product]');
        if (item && state.activeRow) {
            const product = state.activeResults[Number(item.dataset.productIndex)];
            const row = state.activeRow;
            selectProduct(row, product);
            hideSuggest();
            focusVariantControl(row);
        }
        if (create && state.activeRow) {
            const term = state.activeRow.querySelector('.entry-product').value.trim();
            markNewRow(state.activeRow, term);
            hideSuggest();
        }
    });

    document.addEventListener('mousedown', (e) => {
        if (!e.target.closest('#product-suggest') && !e.target.closest('.entry-product')) hideSuggest();
    });

    // ---------------------------------------------------------------- rows
    const rowHtml = () => `
        <td>
            <input class="entry-input entry-product" placeholder="Type product name or SKU…" autocomplete="off">
            <span class="entry-new-badge hidden">NEW — created on payment</span>
        </td>
        <td class="variant-cell"><input class="entry-input entry-variant" placeholder="Variant" autocomplete="off"></td>
        <td><input class="entry-input entry-sku mono" placeholder="SKU"></td>
        <td><input class="entry-input entry-code mono" placeholder="Code"></td>
        <td><input class="entry-input entry-stock" type="number" min="0" step="1" value="1"></td>
        <td><input class="entry-input entry-qty" type="number" min="0.001" step="1" value="1"></td>
        <td><input class="entry-input entry-price" type="number" min="0" step="0.01" value="0"></td>
        <td>
            <div class="discount-cell">
                <input class="entry-input entry-discount" type="number" min="0" step="0.01" value="0" title="Discount">
                <select class="entry-discount-type" title="Discount type"><option value="percent">%</option><option value="amount">AED</option></select>
            </div>
        </td>
        <td><div class="entry-total">AED 0.00</div></td>
        <td class="nowrap">
            <button type="button" class="entry-remove buying-price" title="Enter hidden buying price" style="display:none">🔒</button>
            <button type="button" class="entry-remove duplicate-variant" title="Add another variant of this product" style="display:none">＋</button>
            <button type="button" class="entry-remove remove-row" title="Remove row">×</button>
        </td>`;

    const addRow = (data = {}) => {
        const tr = document.createElement('tr');
        tr.className = 'entry-row';
        tr.dataset.key = ++state.rowSeq;
        tr.dataset.productId = '';
        tr.dataset.isNew = 'false';
        tr.dataset.originalPrice = '';
        tr.innerHTML = rowHtml();
        $('#entry-body').appendChild(tr);

        if (data.product_id) {
            fillProduct(tr, data);
        } else if (data.is_new) {
            markNewRow(tr, data.name || '', true);
            if (data.sku) tr.querySelector('.entry-sku').value = data.sku;
        } else {
            tr.querySelector('.entry-product').focus();
        }
        recalc();
        return tr;
    };

    const setVariantInput = (tr, html) => { tr.querySelector('.variant-cell').innerHTML = html; };

    const variantLabel = (fitment) => {
        const brand = fitment.brand || fitment.vehicle_brand || '';
        const model = fitment.model || fitment.vehicle_model || '';
        const label = [brand, model].filter(Boolean).join(' ');
        const years = fitment.year_from ? ` ${fitment.year_from}–${fitment.year_to}` : '';
        return (label + years).trim() || 'Vehicle variant';
    };

    const applyVariant = (tr, fitment) => {
        if (!fitment) return;
        tr._variant = {
            label: variantLabel(fitment),
            sku: fitment.mapped_part_number || tr._baseSku || tr.querySelector('.entry-sku').value,
        };
        const skuInput = tr.querySelector('.entry-sku');
        skuInput.value = tr._variant.sku;
        skuInput.readOnly = true;
        if (fitment.stock_qty !== null && fitment.stock_qty !== undefined) {
            tr.querySelector('.entry-stock').value = Number(fitment.stock_qty);
        } else {
            tr.querySelector('.entry-stock').value = 0;
        }
        setVariantInput(tr, `<button type="button" class="variant-picker selected" data-open-variants>
            ${esc(tr._variant.label)}<span class="change">${esc(tr._variant.sku)} · change / add</span>
        </button>`);
    };


    const renderNewVariantSelect = (tr) => {
        if (!tr._newVariants?.length) {
            setVariantInput(tr, `<div class="new-variant-cell">
                <input class="entry-input entry-variant" placeholder="Variant" autocomplete="off">
                <button type="button" class="mini-link" data-open-new-variants>+ Add product variants</button>
            </div>`);
            return;
        }
        const currentSku = tr._variant?.sku;
        const options = tr._newVariants.map((variant, index) => {
            const label = variantLabel(variant);
            return `<option value="${index}" ${currentSku === variant.mapped_part_number ? 'selected' : ''}>${esc(label)} · ${esc(variant.mapped_part_number)}</option>`;
        }).join('');
        setVariantInput(tr, `<select class="new-variant-select npv-select">
            <option value="">Select variant to sell</option>${options}
        </select><button type="button" class="mini-link" data-open-new-variants>Edit variants (${tr._newVariants.length})</button>`);
    };

    const fillProduct = (tr, product) => {
        tr.dataset.productId = product.id;
        tr.dataset.isNew = 'false';
        tr.dataset.originalPrice = product.selling_price;
        tr.classList.remove('is-new');
        tr.querySelector('.entry-new-badge').classList.add('hidden');
        tr.querySelector('.entry-product').value = product.name;
        const sku = tr.querySelector('.entry-sku');
        const code = tr.querySelector('.entry-code');
        const stock = tr.querySelector('.entry-stock');
        const price = tr.querySelector('.entry-price');
        sku.value = product.primary_part_number;
        tr._baseSku = product.primary_part_number;
        sku.readOnly = true;
        code.value = product.secret_cost_code;
        code.readOnly = true;
        stock.value = Number(product.stock_qty);
        tr._baseStock = Number(product.stock_qty);
        stock.readOnly = true;
        price.value = Number(product.selling_price);
        tr.querySelector('.entry-qty').value = 1;
        tr.querySelector('.entry-discount').value = 0;
        tr.querySelector('.duplicate-variant').style.display = '';
        tr.querySelector('.buying-price').style.display = 'none';
        tr._fitments = product.fitments || [];
        tr._newVariants = [];
        tr._variant = null;
        if (tr._fitments.length) {
            stock.value = '';
            setVariantInput(tr, `<button type="button" class="variant-picker" data-open-variants>
                Choose variant<span class="change">Select to see its own stock</span>
            </button>`);
        } else {
            tr.querySelector('.duplicate-variant').style.display = 'none';
            setVariantInput(tr, '<span class="small muted">Single product</span><button type="button" class="mini-link" data-open-variants>+ Add variants</button>');
        }
        if (product._selectedVariant) {
            applyVariant(tr, product._selectedVariant);
        }
        recalc();
    };

    const selectProduct = (tr, product) => fillProduct(tr, product);

    const markNewRow = (tr, name, silent = false) => {
        tr.dataset.productId = '';
        tr.dataset.isNew = 'true';
        tr.dataset.originalPrice = '';
        tr.classList.add('is-new');
        tr.querySelector('.entry-new-badge').classList.remove('hidden');
        tr.querySelector('.entry-product').value = name;
        const sku = tr.querySelector('.entry-sku');
        const code = tr.querySelector('.entry-code');
        const stock = tr.querySelector('.entry-stock');
        const price = tr.querySelector('.entry-price');
        sku.readOnly = false;
        code.readOnly = false;
        stock.readOnly = false;
        if (!sku.value || !silent) sku.value = /^[A-Za-z0-9][A-Za-z0-9._/-]{1,}$/.test(name) ? name.toUpperCase() : '';
        code.value = '';
        stock.value = 1;
        tr._baseStock = 1;
        price.value = 0;
        tr._fitments = [];
        tr._newVariants = [];
        tr._variant = null;
        tr._baseSku = null;
        tr._cost = null;
        setVariantInput(tr, `<div class="new-variant-cell">
            <input class="entry-input entry-variant" placeholder="Variant" autocomplete="off">
            <button type="button" class="mini-link" data-open-new-variants>+ Add product variants</button>
        </div>`);
        tr.querySelector('.duplicate-variant').style.display = 'none';
        const costBtn = tr.querySelector('.buying-price');
        costBtn.style.display = boot.user?.can_view_cost ? '' : 'none';
        costBtn.classList.remove('cost-set');
        costBtn.title = 'Enter hidden buying price';
        recalc();
        if (!silent) {
            sku.focus();
            toast('New product row. Complete SKU, buying code and selling price.', 'success');
        }
    };

    const getVariant = (tr) => {
        if (tr._variant) return tr._variant;
        const input = tr.querySelector('.entry-variant');
        return { label: input?.value.trim() || '', sku: '' };
    };

    const recalc = () => {
        let subtotal = 0, discount = 0, vat = 0, total = 0, qtyCount = 0, validRows = 0;
        $$('#entry-body .entry-row').forEach(tr => {
            const qty = Number(tr.querySelector('.entry-qty').value || 0);
            const price = Number(tr.querySelector('.entry-price').value || 0);
            const discountValue = Number(tr.querySelector('.entry-discount').value || 0);
            const discountType = tr.querySelector('.entry-discount-type').value;
            const gross = num(qty * price);
            const disc = discountType === 'percent' ? num(gross * discountValue / 100) : Math.min(gross, discountValue);
            const after = Math.max(0, gross - disc);
            let lineNet, lineVat, lineTotal;
            if (inclusive) {
                lineTotal = after;
                lineNet = after / (1 + rate / 100);
                lineVat = lineTotal - lineNet;
            } else {
                lineNet = after;
                lineVat = lineNet * rate / 100;
                lineTotal = lineNet + lineVat;
            }
            tr.querySelector('.entry-total').textContent = money(lineTotal);
            subtotal += gross;
            discount += disc;
            vat += lineVat;
            total += lineTotal;
            qtyCount += qty;
            if (tr.dataset.productId || (tr.dataset.isNew === 'true' && tr.querySelector('.entry-product').value.trim())) validRows++;
        });
        $('#summary-subtotal').textContent = money(subtotal);
        $('#summary-discount').textContent = money(discount);
        $('#summary-vat').textContent = money(vat);
        $('#summary-total').textContent = money(total);
        $('#entry-count').textContent = qtyCount + ' item' + (qtyCount === 1 ? '' : 's');
        $('#pay-btn').disabled = total <= 0 || validRows === 0;
        $('#credit-sale-btn').disabled = total <= 0 || validRows === 0;
        return { subtotal: num(subtotal), discount: num(discount), vat: num(vat), total: num(total) };
    };

    $('#entry-body').addEventListener('input', (e) => {
        const tr = e.target.closest('.entry-row');
        if (!tr) return;
        if (e.target.classList.contains('entry-product')) {
            if (tr.dataset.productId) {
                markNewRow(tr, e.target.value, true);
            }
            searchProducts(tr, e.target.value);
        }
        if (e.target.classList.contains('entry-sku') && tr.dataset.isNew === 'true' && !tr._variant) {
            tr._baseSku = e.target.value.trim();
        }
        recalc();
    });

    $('#entry-body').addEventListener('change', (e) => {
        if (e.target.classList.contains('npv-select')) {
            const tr = e.target.closest('.entry-row');
            const index = e.target.value;
            if (index === '') {
                tr._variant = null;
                const sku = tr.querySelector('.entry-sku');
                sku.value = tr._baseSku || '';
                sku.readOnly = false;
                tr.querySelector('.entry-stock').value = '';
            } else {
                applyVariant(tr, tr._newVariants[Number(index)]);
            }
            recalc();
            return;
        }
        if (e.target.classList.contains('entry-product')) {
            const tr = e.target.closest('.entry-row');
            if (!tr.dataset.productId && e.target.value.trim() && !tr.querySelector('.entry-sku').value) {
                // No exact selection yet; let the suggestion dropdown handle it.
            }
        }
        recalc();
    });

    const focusVariantControl = (tr) => {
        const target = tr?.querySelector('.variant-picker, [data-open-new-variants], .npv-select, .entry-variant');
        target?.focus();
    };

    const focusNextEntryField = (current) => {
        const fields = $$('#entry-body input:not([type=hidden]):not([readonly]):not([disabled]), #entry-body select:not([disabled]), #entry-body textarea:not([disabled])')
            .filter(el => el.offsetParent !== null);
        const index = fields.indexOf(current);
        if (index >= 0 && fields[index + 1]) fields[index + 1].focus();
    };

    $('#entry-body').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        if (e.target.classList.contains('entry-product')) {
            const tr = e.target.closest('.entry-row');
            if (tr && state.activeRow === tr && state.activeResults.length) {
                e.preventDefault();
                selectProduct(tr, state.activeResults[0]);
                hideSuggest();
                focusVariantControl(tr);
                return;
            }
        }
        if (e.target.matches('input, select, textarea')) {
            e.preventDefault();
            focusNextEntryField(e.target);
        }
    });

    $('#entry-body').addEventListener('click', (e) => {
        const tr = e.target.closest('.entry-row');
        if (!tr) return;
        if (e.target.closest('.remove-row')) {
            tr.remove();
            if (!$$('#entry-body .entry-row').length) addRow();
            recalc();
        }
        if (e.target.closest('.buying-price')) {
            openBuyingPrice(tr);
        }
        if (e.target.closest('[data-open-new-variants]')) {
            openNewProductVariants(tr);
        }
        if (e.target.closest('.duplicate-variant') || e.target.closest('[data-open-variants]')) {
            openVariantPicker(tr);
        }
    });

    let variantRow = null;
    let npvRow = null;

    const createVariantInputRow = (values = {}) => {
        const canViewCost = !!boot.user?.can_view_cost;
        const row = document.createElement('div');
        row.className = 'new-variant-row' + (canViewCost ? ' with-cost' : '');
        row.innerHTML = `
            <input data-new-brand list="variant-brand-list" placeholder="Vehicle brand" autocomplete="off">
            <input data-new-model list="variant-model-list" placeholder="Model" autocomplete="off">
            <input data-new-year-from type="number" min="1950" max="2100" value="2018" placeholder="From">
            <input data-new-year-to type="number" min="1950" max="2100" value="2024" placeholder="To">
            <input data-new-mapped placeholder="Part number *">
            <input data-new-stock type="number" min="0" step="0.001" placeholder="Variant stock (blank = not set)">
            ${canViewCost ? '<input data-new-cost type="password" inputmode="decimal" autocomplete="off" placeholder="Buying price">' : ''}
            <button type="button" class="remove" data-remove-new-variant>×</button>
        `;

        row.querySelector('[data-new-brand]').value = values.vehicle_brand || values.brand || '';
        row.querySelector('[data-new-model]').value = values.vehicle_model || values.model || '';
        row.querySelector('[data-new-year-from]').value = values.year_from || 2018;
        row.querySelector('[data-new-year-to]').value = values.year_to || 2024;
        row.querySelector('[data-new-mapped]').value = values.mapped_part_number || '';
        row.querySelector('[data-new-stock]').value = values.stock_qty ?? '';
        const cost = row.querySelector('[data-new-cost]');
        if (cost) cost.value = values.cost_price ?? '';

        const brand = row.querySelector('[data-new-brand]');
        const model = row.querySelector('[data-new-model]');
        const syncModelList = () => {
            const list = $('#variant-model-list');
            if (!list) return;
            list.innerHTML = '';
            const name = brand.value.trim().toLowerCase();
            const vehicle = (boot.vehicles || []).find(v => String(v.name).toLowerCase() === name);
            (vehicle?.models || []).forEach(item => {
                const option = document.createElement('option');
                option.value = typeof item === 'string' ? item : item.name;
                list.appendChild(option);
            });
        };
        brand.addEventListener('input', syncModelList);
        model.addEventListener('focus', syncModelList);
        row.querySelector('[data-remove-new-variant]').addEventListener('click', () => row.remove());
        return row;
    };

    const addNewVariantRow = () => {
        const row = createVariantInputRow();
        $('#new-variant-rows').appendChild(row);
        row.querySelector('[data-new-brand]').focus();
    };

    const collectVariantRows = (selector) => {
        return $$(selector + ' .new-variant-row').map(row => ({
            vehicle_brand: row.querySelector('[data-new-brand]').value.trim(),
            vehicle_model: row.querySelector('[data-new-model]').value.trim(),
            year_from: Number(row.querySelector('[data-new-year-from]').value || 0),
            year_to: Number(row.querySelector('[data-new-year-to]').value || 0),
            mapped_part_number: row.querySelector('[data-new-mapped]').value.trim(),
            stock_qty: row.querySelector('[data-new-stock]')?.value === '' ? null : Number(row.querySelector('[data-new-stock]')?.value),
            cost_price: row.querySelector('[data-new-cost]')?.value.trim() || null,
        })).filter(row => row.vehicle_brand || row.vehicle_model || row.mapped_part_number);
    };

    const openNewProductVariants = (tr) => {
        if (!tr || tr.dataset.isNew !== 'true') return;
        npvRow = tr;
        $('#npv-product').textContent = tr.querySelector('.entry-product').value + ' — add the variants for this new product.';
        $('#npv-rows').innerHTML = '';
        const variants = tr._newVariants || [];
        if (variants.length) {
            variants.forEach(variant => $('#npv-rows').appendChild(createVariantInputRow(variant)));
        } else {
            $('#npv-rows').appendChild(createVariantInputRow());
        }
        openModal('new-product-variant-modal');
        setTimeout(() => $('#npv-rows [data-new-brand]')?.focus(), 100);
    };

    const saveNewProductVariants = () => {
        if (!npvRow) return;
        const variants = collectVariantRows('#npv-rows');
        if (!variants.length) { toast('Add at least one variant.', 'error'); return; }
        const invalid = variants.find(v => !v.vehicle_brand || !v.vehicle_model);
        if (invalid) { toast('Each variant needs a vehicle brand and model.', 'error'); return; }
        const missingPart = variants.find(v => !v.mapped_part_number);
        if (missingPart) { toast('Each variant needs its own part number.', 'error'); return; }

        const row = npvRow;
        row._newVariants = variants.map(v => ({ ...v, brand: v.vehicle_brand, model: v.vehicle_model }));
        if (!row._baseSku) {
            row._baseSku = row.querySelector('.entry-sku').value.trim() || variants[0].mapped_part_number;
        }
        if (row._variant && !variants.some(v => v.mapped_part_number === row._variant.sku)) {
            row._variant = null;
        }
        row.querySelector('.entry-sku').value = row._baseSku;
        renderNewVariantSelect(row);
        closeModal('new-product-variant-modal');
        toast(variants.length + ' variant' + (variants.length === 1 ? '' : 's') + ' saved for this new product.');
        npvRow = null;
    };

    $('#npv-add').addEventListener('click', () => {
        const row = createVariantInputRow();
        $('#npv-rows').appendChild(row);
        row.querySelector('[data-new-brand]').focus();
    });
    $('#npv-save').addEventListener('click', saveNewProductVariants);

    const openVariantPicker = (tr) => {
        variantRow = tr;
        $('#variant-picker-product').textContent = tr.querySelector('.entry-product').value + ' — select one or more vehicle variants.';
        const selectedLabel = tr._variant?.label || '';
        $('#variant-options').innerHTML = tr._fitments?.length
            ? tr._fitments.map((fitment, index) => {
                const label = variantLabel(fitment);
                const sku = fitment.mapped_part_number || tr._baseSku || tr.querySelector('.entry-sku').value;
                return `<label class="variant-option">
                    <input type="checkbox" value="${index}" ${selectedLabel === label ? 'checked' : ''}>
                    <span><span class="v-name">${esc(label)}</span><span class="v-meta">Part no. ${esc(sku)} · Stock: ${fitment.stock_qty === null || fitment.stock_qty === undefined ? 'Stock not set (0 available)' : Number(fitment.stock_qty)}</span></span>
                </label>`;
            }).join('')
            : '<div class="empty" style="padding:12px 0">No saved variants yet. Create the first one below.</div>';
        $('#new-variant-rows').innerHTML = '';
        openModal('variant-picker-modal');
    };

    const collectNewVariants = () => collectVariantRows('#new-variant-rows');

    const applySelectedVariants = async () => {
        if (!variantRow) return;
        const row = variantRow;
        const selected = Array.from($('#variant-options').querySelectorAll('input[type=checkbox]:checked'))
            .map(input => Number(input.value))
            .filter(index => Number.isInteger(index) && row._fitments[index]);
        const newVariants = collectNewVariants();
        const invalid = newVariants.find(v => !v.vehicle_brand || !v.vehicle_model);
        if (invalid) {
            toast('Each new variant needs a vehicle brand and model.', 'error');
            return;
        }
        const missingPart = newVariants.find(v => !v.mapped_part_number);
        if (missingPart) {
            toast('Each new variant needs its own part number.', 'error');
            return;
        }
        if (!selected.length && !newVariants.length) {
            toast('Select an existing variant or add a new one.', 'error');
            return;
        }

        try {
            $('#variant-apply').disabled = true;
            let chosen = selected.map(index => row._fitments[index]);

            if (newVariants.length) {
                const data = await api(`/api/pos/products/${row.dataset.productId}/variants`, {
                    method: 'POST',
                    body: JSON.stringify({ variants: newVariants }),
                });
                row._fitments = data.data;
                newVariants.forEach(variant => {
                    const found = data.data.find(fitment =>
                        String(fitment.brand).toLowerCase() === variant.vehicle_brand.toLowerCase()
                        && String(fitment.model).toLowerCase() === variant.vehicle_model.toLowerCase()
                        && Number(fitment.year_from) === Number(variant.year_from)
                        && Number(fitment.year_to) === Number(variant.year_to)
                    );
                    if (found && !chosen.some(item => item.label === found.label)) chosen.push(found);
                });
            }

            closeModal('variant-picker-modal');
            if (!chosen.length) { variantRow = null; toast('No variant was added.', 'error'); return; }

            const base = {
                product_id: Number(row.dataset.productId),
                id: Number(row.dataset.productId),
                name: row.querySelector('.entry-product').value,
                primary_part_number: row.querySelector('.entry-sku').value,
                secret_cost_code: row.querySelector('.entry-code').value,
                stock_qty: Number(row.querySelector('.entry-stock').value),
                selling_price: Number(row.querySelector('.entry-price').value),
                fitments: row._fitments,
            };
            applyVariant(row, chosen[0]);
            chosen.slice(1).forEach(fitment => {
                const newRow = addRow({ ...base, _selectedVariant: fitment });
                newRow.querySelector('.entry-price').value = row.querySelector('.entry-price').value;
                newRow.querySelector('.entry-qty').value = 1;
            });
            recalc();
            variantRow = null;
            toast(chosen.length + ' variant' + (chosen.length === 1 ? '' : 's') + ' added.');
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            $('#variant-apply').disabled = false;
        }
    };

    $('#variant-apply').addEventListener('click', applySelectedVariants);
    $('#add-new-variant').addEventListener('click', addNewVariantRow);
    $('#variant-select-all').addEventListener('click', () => {
        const boxes = $$('#variant-options input[type=checkbox]');
        const allChecked = boxes.length > 0 && boxes.every(box => box.checked);
        boxes.forEach(box => { box.checked = !allChecked; });
    });

    $('#add-row-btn').addEventListener('click', () => addRow());
    $('#clear-rows-btn').addEventListener('click', () => {
        if (!confirm('Clear every row?')) return;
        $('#entry-body').innerHTML = '';
        addRow();
    });

    // ---------------------------------------------------------------- payments
    const paymentTotal = () => state.payments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const cashChange = () => state.payments.filter(p => p.method === 'cash').reduce((sum, p) => sum + Math.max(0, Number(p.tendered || p.amount || 0) - Number(p.amount || 0)), 0);

    const renderPayments = (total) => {
        $('#pay-total').textContent = money(total);
        $('#payment-list').innerHTML = state.payments.map((payment, index) => `<div class="payment-row" data-index="${index}">
            <span class="method">${payment.method === 'bank_transfer' ? 'Bank' : payment.method}</span>
            <input type="number" step="0.01" min="0" value="${payment.amount}" data-field="amount" data-index="${index}" aria-label="Amount">
            ${payment.method === 'cash'
                ? `<input type="number" step="0.01" min="0" value="${payment.tendered ?? payment.amount}" data-field="tendered" data-index="${index}" aria-label="Tendered" placeholder="Tendered">`
                : `<input type="text" value="${esc(payment.reference || '')}" data-field="reference" data-index="${index}" placeholder="Reference">`}
            <button type="button" class="remove" data-remove="${index}">×</button>
        </div>`).join('');
        const remaining = num(total - paymentTotal());
        const balance = $('#pay-balance');
        $('#pay-remaining').textContent = money(Math.max(0, remaining));
        balance.classList.toggle('done', remaining <= 0.009);
        $('#pay-change').textContent = money(cashChange());
        const creditWithoutCustomer = state.payments.some(p => p.method === 'credit') && !customer();
        $('#complete-sale-btn').disabled = remaining > 0.009 || remaining < -0.009 || !state.payments.length || creditWithoutCustomer;
    };

    $('#payment-list').addEventListener('input', (e) => {
        const index = Number(e.target.dataset.index);
        const field = e.target.dataset.field;
        if (Number.isNaN(index) || !field || !state.payments[index]) return;
        state.payments[index][field] = field === 'reference' ? e.target.value : Number(e.target.value || 0);
        if (field === 'amount' && state.payments[index].method === 'cash' && Number(state.payments[index].tendered) < Number(state.payments[index].amount)) {
            state.payments[index].tendered = Number(state.payments[index].amount);
        }
        renderPayments(recalc().total);
    });
    $('#payment-list').addEventListener('click', (e) => {
        const remove = e.target.closest('[data-remove]');
        if (!remove) return;
        state.payments.splice(Number(remove.dataset.remove), 1);
        renderPayments(recalc().total);
    });

    const addPayment = (method) => {
        if (method === 'credit' && !customer()) { toast('Select a customer before taking a credit payment.', 'error'); return; }
        const total = recalc().total;
        const remaining = num(total - paymentTotal());
        if (remaining <= 0.009) { toast('The invoice is already fully paid.', 'error'); return; }
        state.payments.push({ method, amount: remaining, tendered: method === 'cash' ? remaining : null, reference: '' });
        $$('.method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === method));
        renderPayments(total);
    };
    $$('.method-btn').forEach(btn => btn.addEventListener('click', () => addPayment(btn.dataset.method)));

    const openPayment = (method = 'cash') => {
        if (!state.shift) { openModal('shift-modal'); toast('Open a shift before taking payment.', 'error'); return; }
        const total = recalc().total;
        if (total <= 0) { toast('Add a product and selling price first.', 'error'); return; }
        const invalid = validRows().find(row => row.is_new && (!row.base_sku || !row.code || row.stock < row.qty));
        if (invalid) { toast('New product needs a part number, buying code and enough opening stock.', 'error'); return; }

        if (method === 'credit') {
            const c = customer();
            if (!c) {
                toast('Select a customer before making a credit sale.', 'error');
                customerSelect.focus();
                return;
            }
            if (c.credit_limit <= 0) {
                toast('This customer has no credit limit. Ask a manager to set one.', 'error');
                return;
            }
            const available = c.credit_limit - c.outstanding;
            if (total > available + 0.01) {
                toast('Credit limit exceeded. Available: ' + money(available) + '.', 'error');
                return;
            }
        }

        state.payments = [{ method, amount: total, tendered: method === 'cash' ? total : null, reference: '' }];
        $$('.method-btn').forEach(button => button.classList.toggle('active', button.dataset.method === method));
        $('#approval-pin').value = '';
        $('#sale-note').value = '';
        renderPayments(total);
        openModal('payment-modal');
    };
    $('#pay-btn').addEventListener('click', () => openPayment('cash'));
    $('#credit-sale-btn').addEventListener('click', () => openPayment('credit'));

    const validRows = () => $$('#entry-body .entry-row').map(tr => {
        const variant = getVariant(tr);
        return {
            tr,
            product_id: Number(tr.dataset.productId || 0),
            is_new: tr.dataset.isNew === 'true',
            name: tr.querySelector('.entry-product').value.trim(),
            sku: tr.querySelector('.entry-sku').value.trim(),
            code: tr.querySelector('.entry-code').value.trim(),
            stock: Number(tr.querySelector('.entry-stock').value || 0),
            qty: Number(tr.querySelector('.entry-qty').value || 0),
            price: Number(tr.querySelector('.entry-price').value || 0),
            discount_type: tr.querySelector('.entry-discount-type').value,
            discount_value: Number(tr.querySelector('.entry-discount').value || 0),
            original_price: Number(tr.dataset.originalPrice || 0),
            cost: tr._cost ?? null,
            base_sku: tr._baseSku || tr.querySelector('.entry-sku').value.trim(),
            variants: tr._newVariants || [],
            variant_label: variant.label,
            variant_sku: variant.sku,
        };
    }).filter(row => row.product_id || (row.is_new && row.name));

    const createNewProducts = async () => {
        for (const row of validRows()) {
            if (!row.is_new) continue;
            if (!row.sku || !row.code) throw new Error('New product ' + row.name + ' needs an SKU and buying code.');
            if (row.stock < row.qty) throw new Error('Opening stock for ' + row.name + ' must cover the sold quantity.');
            const hasVariantStock = (row.variants || []).some(variant => variant.stock_qty !== null && variant.stock_qty !== undefined && variant.stock_qty !== '');
            const variantStock = (row.variants || []).reduce((sum, variant) => sum + Number(variant.stock_qty || 0), 0);
            const openingStock = hasVariantStock ? variantStock : row.stock;
            const data = await api('/api/pos/quick-add', {
                method: 'POST',
                body: JSON.stringify({
                    name: row.name,
                    primary_part_number: row.base_sku || row.sku,
                    selling_price: row.price,
                    secret_cost_code: row.code,
                    stock_qty: openingStock,
                    cost_price: row.cost ?? undefined,
                    variants: row.variants,
                }),
            });
            row.tr.dataset.productId = data.product.id;
            row.tr.dataset.isNew = 'false';
            row.tr.dataset.originalPrice = data.product.selling_price;
            row.tr.classList.remove('is-new');
            row.tr.querySelector('.entry-new-badge').classList.add('hidden');
        }
    };

    const completeSale = async () => {
        const total = recalc().total;
        if (num(total - paymentTotal()) !== 0) { toast('Payments must equal the total.', 'error'); return; }
        try {
            $('#complete-sale-btn').disabled = true;
            await createNewProducts();
            const rows = validRows();
            const items = rows.map(row => ({
                product_id: row.product_id,
                qty: row.qty,
                unit_price: row.price,
                discount_type: row.discount_type,
                discount_value: row.discount_value,
                override_reason: row.original_price && Math.abs(row.price - row.original_price) >= 0.01 ? 'Counter price adjustment' : null,
                variant_label: row.variant_label || null,
                variant_sku: row.variant_sku || null,
            }));
            const sale = await api('/api/pos/sales', {
                method: 'POST',
                body: JSON.stringify({
                    customer_id: customer()?.id || null,
                    person_name: $('#invoice-person-name').value.trim() || null,
                    car_plate_number: $('#invoice-car-plate').value.trim() || null,
                    items,
                    payments: state.payments,
                    invoice_discount_type: 'none',
                    invoice_discount_value: 0,
                    approval_pin: $('#approval-pin').value || null,
                    notes: $('#sale-note').value || null,
                }),
            });
            state.lastSale = sale.sale;
            $('#complete-total').textContent = money(sale.sale.total);
            $('#complete-invoice').textContent = sale.sale.invoice_number + ' · ' + sale.sale.payment_status;
            $('#complete-receipt').href = sale.sale.receipt_url;
            $('#complete-invoice-link').href = sale.sale.invoice_url;
            const balanceDue = Number(sale.sale.balance_due || 0);
            const change = Number(sale.sale.change_total || 0);
            $('#complete-change').textContent = balanceDue > 0
                ? 'Credit balance due: ' + money(balanceDue)
                : (change > 0 ? 'Change due: ' + money(change) : 'Paid in full. No balance due.');
            closeModal('payment-modal');
            openModal('complete-modal');
            resetSale();
            loadRecentSales();
        } catch (e) {
            toast(e.message, 'error');
            $('#complete-sale-btn').disabled = false;
        }
    };
    $('#complete-sale-btn').addEventListener('click', completeSale);

    const resetSale = () => {
        $('#entry-body').innerHTML = '';
        addRow();
        customerSelect.value = '';
        $('#invoice-car-plate').value = '';
        $('#invoice-person-name').value = '';
        syncCustomerMeta();
        state.payments = [];
        recalc();
    };

    $('#new-sale-btn').addEventListener('click', () => {
        closeModal('complete-modal');
        $('#entry-body .entry-product')?.focus();
    });

    // ---------------------------------------------------------------- shift
    $('#shift-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const data = await api('/api/pos/shift/open', { method: 'POST', body: JSON.stringify({ opening_float: Number($('#opening-float').value || 0) }) });
            state.shift = data.shift;
            $('#entry-shift-badge').textContent = 'Shift open';
            $('#entry-shift-badge').className = 'badge badge-green';
            closeModal('shift-modal');
            toast('Shift opened. Opening float ' + money(data.shift.opening_float) + '.');
            $('#entry-body .entry-product')?.focus();
        } catch (e) { toast(e.message, 'error'); }
    });

    $('#close-shift-btn').addEventListener('click', () => {
        if (!state.shift) { toast('No open shift on this terminal.', 'error'); return; }
        openModal('close-shift-modal');
        setTimeout(() => $('#counted-cash').focus(), 100);
    });
    $('#close-shift-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const data = await api('/api/pos/shift/close', { method: 'POST', body: JSON.stringify({ counted_cash: Number($('#counted-cash').value || 0), notes: $('#close-note').value || null }) });
            state.shift = null;
            $('#entry-shift-badge').textContent = 'Shift closed';
            $('#entry-shift-badge').className = 'badge badge-red';
            closeModal('close-shift-modal');
            toast('Shift closed. Variance ' + money(data.shift.variance) + '.', Math.abs(Number(data.shift.variance)) > 0.009 ? 'error' : 'success');
        } catch (e) { toast(e.message, 'error'); }
    });

    // ---------------------------------------------------------------- keyboard & modals
    $$('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
    $$('.modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', (e) => { if (e.target === backdrop) backdrop.classList.remove('open'); }));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeAll(); hideSuggest(); return; }
        if (e.key === 'F2') { e.preventDefault(); addRow(); }
        if (e.key === 'F3') { e.preventDefault(); $('#entry-body .entry-product')?.focus(); }
        if (e.key === 'F4') { e.preventDefault(); openNewCustomer(); }
        if (e.key === 'F9') { e.preventDefault(); openPayment('cash'); }
        if (e.key === 'F10') { e.preventDefault(); openPayment('credit'); }
        if (e.key === '?' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            e.preventDefault();
            openShortcuts();
        }
    });

    addRow();
    loadRecentSales();
    if (!state.shift) setTimeout(() => $('#opening-float')?.focus(), 300);
})();
