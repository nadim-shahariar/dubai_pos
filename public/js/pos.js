(() => {
    const boot = window.POS_BOOT;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const money = (value) => 'AED ' + Number(value || 0).toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
    const rate = Number(boot.tax_rate || 5);
    const inclusive = !!boot.prices_include_vat;

    const state = { shift: boot.shift, payments: [], rowSeq: 0, lastSale: null, activeRow: null, activeResults: [] };

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
    customerSelect.addEventListener('change', syncCustomerMeta);
    syncCustomerMeta();

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
                    <div class="right">${money(product.selling_price)}<div class="meta">stock ${Number(product.stock_qty)}</div></div>
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
            selectProduct(state.activeRow, product);
            hideSuggest();
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
        sku.readOnly = true;
        code.value = product.secret_cost_code;
        code.readOnly = true;
        stock.value = Number(product.stock_qty);
        stock.readOnly = true;
        price.value = Number(product.selling_price);
        tr.querySelector('.entry-qty').value = 1;
        tr.querySelector('.entry-discount').value = 0;
        tr.querySelector('.duplicate-variant').style.display = product.fitments?.length ? '' : 'none';

        if (product.fitments?.length) {
            const options = product.fitments.map((fitment, index) => {
                const label = [fitment.brand, fitment.model].filter(Boolean).join(' ') + (fitment.year_from ? ` ${fitment.year_from}–${fitment.year_to}` : '');
                const variantSku = fitment.mapped_part_number || product.primary_part_number;
                return `<option value="${index}" data-label="${esc(label)}" data-sku="${esc(variantSku)}" data-brand="${esc(fitment.brand)}" data-model="${esc(fitment.model)}" data-year-from="${esc(fitment.year_from)}" data-year-to="${esc(fitment.year_to)}">${esc(label)}</option>`;
            }).join('');
            setVariantInput(tr, `<select class="entry-variant-select"><option value="">Choose variant</option>${options}</select>`);
        } else {
            setVariantInput(tr, '<input class="entry-input entry-variant" placeholder="Variant" autocomplete="off">');
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
        price.value = 0;
        setVariantInput(tr, '<input class="entry-input entry-variant" placeholder="Variant" autocomplete="off">');
        tr.querySelector('.duplicate-variant').style.display = 'none';
        recalc();
        if (!silent) {
            sku.focus();
            toast('New product row. Complete SKU, buying code and selling price.', 'success');
        }
    };

    const getVariant = (tr) => {
        const select = tr.querySelector('.entry-variant-select');
        if (select) {
            const option = select.options[select.selectedIndex];
            return { label: option?.dataset.label || '', sku: option?.dataset.sku || '' };
        }
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
        recalc();
    });

    $('#entry-body').addEventListener('change', (e) => {
        if (e.target.classList.contains('entry-product')) {
            const tr = e.target.closest('.entry-row');
            if (!tr.dataset.productId && e.target.value.trim() && !tr.querySelector('.entry-sku').value) {
                // No exact selection yet; let the suggestion dropdown handle it.
            }
        }
        recalc();
    });

    $('#entry-body').addEventListener('click', (e) => {
        const tr = e.target.closest('.entry-row');
        if (!tr) return;
        if (e.target.closest('.remove-row')) {
            tr.remove();
            if (!$$('#entry-body .entry-row').length) addRow();
            recalc();
        }
        if (e.target.closest('.duplicate-variant')) {
            duplicateVariant(tr);
        }
    });

    const duplicateVariant = (tr) => {
        if (!tr.dataset.productId) return;
        const product = {
            product_id: Number(tr.dataset.productId),
            id: Number(tr.dataset.productId),
            name: tr.querySelector('.entry-product').value,
            primary_part_number: tr.querySelector('.entry-sku').value,
            secret_cost_code: tr.querySelector('.entry-code').value,
            stock_qty: Number(tr.querySelector('.entry-stock').value),
            selling_price: Number(tr.querySelector('.entry-price').value),
            fitments: [],
        };
        // Rebuild fitment options from the current select so the duplicate can pick another variant.
        const select = tr.querySelector('.entry-variant-select');
        if (select) {
            product.fitments = Array.from(select.options).filter(o => o.value !== '').map(o => ({
                brand: o.dataset.brand || '',
                model: o.dataset.model || '',
                year_from: Number(o.dataset.yearFrom || 0),
                year_to: Number(o.dataset.yearTo || 0),
                mapped_part_number: o.dataset.sku || '',
            }));
        }
        const newRow = addRow(product);
        const newSelect = newRow.querySelector('.entry-variant-select');
        if (newSelect) {
            newSelect.selectedIndex = 0;
            newSelect.focus();
        } else {
            newRow.querySelector('.entry-variant')?.focus();
        }
    };

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

    const openPayment = () => {
        if (!state.shift) { openModal('shift-modal'); toast('Open a shift before taking payment.', 'error'); return; }
        const total = recalc().total;
        if (total <= 0) { toast('Add a product and selling price first.', 'error'); return; }
        const invalid = validRows().find(row => row.is_new && (!row.sku || !row.code || row.stock < row.qty));
        if (invalid) { toast('New product needs SKU, buying code and enough opening stock.', 'error'); return; }
        state.payments = [{ method: 'cash', amount: total, tendered: total, reference: '' }];
        $('#approval-pin').value = '';
        $('#sale-note').value = '';
        renderPayments(total);
        openModal('payment-modal');
    };
    $('#pay-btn').addEventListener('click', openPayment);

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
            variant_label: variant.label,
            variant_sku: variant.sku,
        };
    }).filter(row => row.product_id || (row.is_new && row.name));

    const createNewProducts = async () => {
        for (const row of validRows()) {
            if (!row.is_new) continue;
            if (!row.sku || !row.code) throw new Error('New product ' + row.name + ' needs an SKU and buying code.');
            if (row.stock < row.qty) throw new Error('Opening stock for ' + row.name + ' must cover the sold quantity.');
            const data = await api('/api/pos/quick-add', {
                method: 'POST',
                body: JSON.stringify({
                    name: row.name,
                    primary_part_number: row.sku,
                    selling_price: row.price,
                    secret_cost_code: row.code,
                    stock_qty: row.stock,
                    variants: [],
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
            $('#complete-change').textContent = Number(sale.sale.change_total) > 0 ? 'Change due: ' + money(sale.sale.change_total) : 'Paid in full. No balance due.';
            closeModal('payment-modal');
            openModal('complete-modal');
            resetSale();
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
        if (e.key === 'F9') { e.preventDefault(); openPayment(); }
    });

    addRow();
    if (!state.shift) setTimeout(() => $('#opening-float')?.focus(), 300);
})();
