(() => {
    const boot = window.POS_BOOT;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const money = (value) => 'AED ' + Number(value || 0).toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;

    const state = {
        cart: [],
        customer: null,
        invoiceDiscount: { type: 'none', value: 0 },
        payments: [],
        shift: boot.shift,
        discountTarget: null,
        activeLine: null,
        holds: Number(boot.holds_count || 0),
        lastSale: null,
    };

    const toast = (message, type = 'success') => {
        const el = document.createElement('div');
        el.className = `toast-item ${type}`;
        el.textContent = message;
        $('#toast').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, 3800);
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

    // ---------------------------------------------------------------- search
    const initialResults = $('#search-results').innerHTML;
    let searchTimer = null;
    let activeCategory = '';

    const stockClass = (product) => !product.track_stock ? 'stock-in' : (Number(product.stock_qty) <= 0 ? 'stock-out' : (Number(product.stock_qty) <= Number(product.reorder_level) ? 'stock-low' : 'stock-in'));
    const stockText = (product) => !product.track_stock ? 'Service' : (Number(product.stock_qty) <= 0 ? 'Out' : (Number(product.stock_qty) <= Number(product.reorder_level) ? 'Low ' : '') + Number(product.stock_qty));

    const renderResults = (products) => {
        const grid = $('#search-results');
        const empty = $('#search-empty');
        $('#results-label').textContent = products.length ? 'Results' : 'No results';
        if (!products.length) {
            grid.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        grid.innerHTML = products.map(product => {
            const data = JSON.stringify(product).replaceAll("'", '&#39;');
            return `<button type="button" class="product-card" data-category="${product.category_id ?? ''}" data-product='${data}'>
                <div class="pc-top"><span class="pc-part">${esc(product.primary_part_number)}</span><span class="stock-dot ${stockClass(product)}">${esc(stockText(product))}</span></div>
                <div class="pc-name">${esc(product.name)}</div>
                <div class="pc-fit">${esc(product.fitment_hint || 'Universal / no fitment recorded')}</div>
                <div class="pc-bottom"><span class="pc-price">${money(product.selling_price)}</span><span class="pc-code">${esc(product.secret_cost_code)}</span></div>
            </button>`;
        }).join('');
        filterByCategory();
    };

    const filterByCategory = () => {
        $$('#search-results .product-card').forEach(card => {
            card.classList.toggle('hidden', !!activeCategory && String(card.dataset.category) !== String(activeCategory));
        });
    };

    const search = async (term) => {
        if (!term.trim()) {
            $('#search-results').innerHTML = initialResults;
            $('#results-label').textContent = 'Popular right now';
            $('#search-empty').classList.add('hidden');
            filterByCategory();
            return;
        }
        try {
            const data = await api('/api/pos/search?q=' + encodeURIComponent(term));
            renderResults(data.data || []);
        } catch (e) {
            toast(e.message, 'error');
        }
    };

    $('#search-input').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        const term = e.target.value;
        searchTimer = setTimeout(() => search(term), 220);
    });

    $('#search-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const first = $('#search-results .product-card:not(.hidden)');
            if (first) first.click();
        }
    });

    $('#category-chips').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-category]');
        if (!chip) return;
        $$('#category-chips .chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        activeCategory = chip.dataset.category || '';
        filterByCategory();
    });

    $('#search-results').addEventListener('click', (e) => {
        const card = e.target.closest('[data-product]');
        if (!card) return;
        addProduct(JSON.parse(card.dataset.product));
    });

    $('#empty-quick-add').addEventListener('click', () => openQuickAdd());

    // ---------------------------------------------------------------- cart
    const addProduct = (product) => {
        if (product.track_stock && Number(product.stock_qty) <= 0) {
            toast('This part is out of stock. Use Stock In before selling.', 'error');
            return;
        }
        const existing = state.cart.find(item => item.product_id === product.id && Number(item.unit_price) === Number(product.selling_price) && item.discount_type === 'none');
        if (existing) {
            if (product.track_stock && existing.qty + 1 > Number(product.stock_qty)) {
                toast('Only ' + product.stock_qty + ' in stock for ' + product.primary_part_number + '.', 'error');
                return;
            }
            existing.qty += 1;
        } else {
            state.cart.push({
                product_id: product.id,
                primary_part_number: product.primary_part_number,
                name: product.name,
                fitment_hint: product.fitment_hint,
                qty: 1,
                unit_price: Number(product.selling_price),
                original_unit_price: Number(product.selling_price),
                discount_type: 'none',
                discount_value: 0,
                secret_cost_code: product.secret_cost_code,
                stock_qty: Number(product.stock_qty),
                tax_rate: Number(product.tax_rate),
                cost_price: product.cost_price,
                track_stock: product.track_stock,
                price_override: false,
                override_reason: null,
            });
        }
        renderCart();
    };

    const lineDiscount = (item) => {
        const gross = Number(item.qty) * Number(item.unit_price);
        if (item.discount_type === 'percent') return num(gross * Number(item.discount_value || 0) / 100);
        if (item.discount_type === 'amount') return Math.min(gross, Number(item.discount_value || 0));
        return 0;
    };

    const totals = () => {
        const subtotal = state.cart.reduce((sum, item) => sum + Number(item.qty) * Number(item.unit_price), 0);
        const lineDiscounts = state.cart.reduce((sum, item) => sum + lineDiscount(item), 0);
        const afterLine = Math.max(0, subtotal - lineDiscounts);
        const invoiceDiscount = state.invoiceDiscount.type === 'percent'
            ? num(afterLine * Number(state.invoiceDiscount.value || 0) / 100)
            : Math.min(afterLine, Number(state.invoiceDiscount.value || 0));
        const totalGross = Math.max(0, afterLine - invoiceDiscount);
        const rate = Number(boot.tax_rate || 5);
        let net, vat, total;
        if (boot.prices_include_vat) {
            total = totalGross;
            net = total / (1 + rate / 100);
            vat = total - net;
        } else {
            net = totalGross;
            vat = net * rate / 100;
            total = net + vat;
        }
        return { subtotal: num(subtotal), lineDiscounts: num(lineDiscounts), invoiceDiscount: num(invoiceDiscount), discount: num(lineDiscounts + invoiceDiscount), net: num(net), vat: num(vat), total: num(total) };
    };

    const renderCart = () => {
        const body = $('#cart-body');
        const empty = $('#cart-empty');
        const table = $('.cart-table');
        if (!state.cart.length) {
            body.innerHTML = '';
            empty.classList.remove('hidden');
            table.classList.add('is-empty');
        } else {
            table.classList.remove('is-empty');
            empty.classList.add('hidden');
            body.innerHTML = state.cart.map((item, index) => {
                const lineTotal = num(Number(item.qty) * Number(item.unit_price) - lineDiscount(item));
                const original = item.price_override ? `<div class="line-original">was ${money(item.original_unit_price)}</div>` : '';
                const disc = item.discount_type !== 'none' ? `<span class="badge badge-red">-${money(lineDiscount(item))}</span>` : '';
                return `<tr>
                    <td>
                        <div class="cart-item-name">${esc(item.name)}</div>
                        <div class="cart-item-meta"><span class="mono">${esc(item.primary_part_number)}</span><span class="code-chip">${esc(item.secret_cost_code)}</span>${disc}</div>
                        <div class="line-actions">
                            <button class="mini-link" data-action="discount" data-index="${index}">Discount</button>
                            <button class="mini-link" data-action="override" data-index="${index}">Price</button>
                            <button class="mini-link danger" data-action="remove" data-index="${index}">Remove</button>
                        </div>
                    </td>
                    <td>
                        <div class="qty-control">
                            <button type="button" data-action="dec" data-index="${index}">−</button>
                            <input type="number" min="0.001" step="1" value="${item.qty}" data-action="qty" data-index="${index}">
                            <button type="button" data-action="inc" data-index="${index}">+</button>
                        </div>
                    </td>
                    <td class="text-right"><div class="line-price">${money(lineTotal)}</div>${original}</td>
                </tr>`;
            }).join('');
        }
        const t = totals();
        $('#subtotal').textContent = money(t.subtotal);
        $('#discount').textContent = '-' + money(t.discount);
        $('#vat').textContent = money(t.vat);
        $('#total').textContent = money(t.total);
        $('#item-count').textContent = state.cart.reduce((sum, item) => sum + Number(item.qty), 0) + ' items';
        $('#vat-note').textContent = boot.prices_include_vat ? 'VAT inclusive' : 'VAT exclusive';
        $('#pay-btn').disabled = !state.cart.length;
    };

    $('#cart-body').addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        const index = Number(target.dataset.index);
        const item = state.cart[index];
        if (!item) return;
        const action = target.dataset.action;
        if (action === 'inc') {
            if (item.track_stock && item.qty + 1 > item.stock_qty) { toast('No more stock available.', 'error'); return; }
            item.qty += 1;
        }
        if (action === 'dec') item.qty = Math.max(1, item.qty - 1);
        if (action === 'remove') state.cart.splice(index, 1);
        if (action === 'discount') return openDiscount('line', index);
        if (action === 'override') return openOverride(index);
        renderCart();
    });

    $('#cart-body').addEventListener('change', (e) => {
        if (e.target.dataset.action !== 'qty') return;
        const index = Number(e.target.dataset.index);
        const item = state.cart[index];
        if (!item) return;
        const value = Math.max(0.001, Number(e.target.value || 1));
        if (item.track_stock && value > item.stock_qty) {
            toast('Only ' + item.stock_qty + ' in stock.', 'error');
            e.target.value = item.stock_qty;
            item.qty = item.stock_qty;
        } else {
            item.qty = value;
        }
        renderCart();
    });

    $('#clear-btn').addEventListener('click', () => {
        if (!state.cart.length || confirm('Clear this cart?')) { state.cart = []; state.invoiceDiscount = { type: 'none', value: 0 }; renderCart(); }
    });

    // ---------------------------------------------------------------- customer
    const setCustomer = (customer) => {
        state.customer = customer;
        if (customer) {
            $('#customer-name').textContent = customer.company_name || customer.name;
            $('#customer-meta').textContent = (customer.trn ? 'TRN ' + customer.trn : 'No TRN') + ' · Credit limit ' + money(customer.credit_limit);
            $('#customer-avatar').textContent = (customer.company_name || customer.name).substring(0, 2).toUpperCase();
            $('#customer-clear').classList.remove('hidden');
        } else {
            $('#customer-name').textContent = 'Walk-in Customer';
            $('#customer-meta').textContent = 'Cash sale · no TRN';
            $('#customer-avatar').textContent = 'WI';
            $('#customer-clear').classList.add('hidden');
        }
    };

    const customerSearch = async (term) => {
        try {
            const data = await api('/api/pos/customers?q=' + encodeURIComponent(term));
            const results = $('#customer-results');
            if (!data.data.length) {
                results.innerHTML = '<div class="empty" style="padding:18px 0">No customer found. Use the Customers page to create one.</div>';
                return;
            }
            results.innerHTML = data.data.map(customer => `<div class="customer-result" data-customer='${JSON.stringify(customer).replaceAll("'", '&#39;')}'>
                <div><div class="who">${esc(customer.company_name || customer.name)}</div><div class="meta">${esc(customer.name)} · ${esc(customer.phone || 'No phone')} · ${customer.trn ? 'TRN ' + esc(customer.trn) : 'No TRN'}</div></div>
                <div class="credit">Limit ${money(customer.credit_limit)}<br>Outstanding ${money(customer.outstanding)}</div>
            </div>`).join('');
        } catch (e) { toast(e.message, 'error'); }
    };

    $('#customer-btn').addEventListener('click', () => { openModal('customer-modal'); customerSearch(''); setTimeout(() => $('#customer-search').focus(), 100); });
    $('#customer-search').addEventListener('input', (e) => { clearTimeout(searchTimer); searchTimer = setTimeout(() => customerSearch(e.target.value), 220); });
    $('#customer-results').addEventListener('click', (e) => {
        const row = e.target.closest('[data-customer]');
        if (!row) return;
        setCustomer(JSON.parse(row.dataset.customer));
        closeModal('customer-modal');
    });
    $('#walk-in-btn').addEventListener('click', () => { setCustomer(null); closeModal('customer-modal'); });
    $('#customer-clear').addEventListener('click', () => setCustomer(null));

    // ---------------------------------------------------------------- quick add
    const quickAddBrand = $('#qa-fit-brand');
    const quickAddModel = $('#qa-fit-model');
    const quickAddModelList = $('#qa-vehicle-model-list');

    const syncQuickAddModels = (clearModel = false) => {
        quickAddModelList.innerHTML = '';
        if (clearModel) quickAddModel.value = '';

        const name = quickAddBrand.value.trim().toLowerCase();
        const brand = boot.vehicles.find(v => v.name.toLowerCase() === name);
        if (!brand) return;

        brand.models.forEach(model => {
            const option = document.createElement('option');
            option.value = model.name;
            quickAddModelList.appendChild(option);
        });
    };
    quickAddBrand.addEventListener('input', () => syncQuickAddModels(true));
    quickAddBrand.addEventListener('change', () => syncQuickAddModels(true));


    const addDatalistOption = (selector, value) => {
        const list = $(selector);
        if (!list || !value) return;
        const exists = Array.from(list.querySelectorAll('option')).some(option => option.value.toLowerCase() === String(value).toLowerCase());
        if (!exists) {
            const option = document.createElement('option');
            option.value = value;
            list.appendChild(option);
        }
    };

    const addQuickAddSuggestions = (product) => {
        if (product.brand) {
            addDatalistOption('#qa-part-brand-list', product.brand);
        }
        (product.fitments || []).forEach(fitment => {
            if (fitment.brand) {
                addDatalistOption('#qa-vehicle-brand-list', fitment.brand);
                let brand = boot.vehicles.find(v => v.name.toLowerCase() === String(fitment.brand).toLowerCase());
                if (!brand) {
                    brand = { id: null, name: fitment.brand, models: [] };
                    boot.vehicles.push(brand);
                }
                if (fitment.model && !brand.models.some(model => String(model.name || model).toLowerCase() === String(fitment.model).toLowerCase())) {
                    brand.models.push({ id: null, name: fitment.model });
                }
            }
        });
    };

    const openQuickAdd = () => {
        $('#quick-add-form').reset();
        $('#qa-stock').value = 0;
        if ($('#qa-cost')) $('#qa-cost').value = 0;
        $('#qa-status').textContent = 'Duplicate checks run when you submit. The product is added to the cart immediately after creation.';
        syncQuickAddModels(true);
        openModal('quick-add-modal');
        setTimeout(() => $('#qa-name').focus(), 100);
    };
    $('#quick-add-btn').addEventListener('click', openQuickAdd);

    $('#quick-add-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const payload = {};
        form.forEach((value, key) => { if (value !== '') payload[key] = value; });
        ['selling_price', 'stock_qty', 'cost_price', 'year_from', 'year_to', 'category_id'].forEach(key => {
            if (payload[key] !== undefined) payload[key] = Number(payload[key]);
        });
        try {
            $('#qa-status').textContent = 'Creating product…';
            const data = await api('/api/pos/quick-add', { method: 'POST', body: JSON.stringify(payload) });
            addProduct(data.product);
            addQuickAddSuggestions(data.product);
            closeModal('quick-add-modal');
            toast('Product ' + data.product.primary_part_number + ' created and added.');
        } catch (err) {
            $('#qa-status').textContent = err.message;
            toast(err.message, 'error');
        }
    });

    // ---------------------------------------------------------------- discounts & override
    const openDiscount = (target, index = null) => {
        state.discountTarget = { target, index };
        const line = index !== null ? state.cart[index] : null;
        $('#discount-title').textContent = target === 'invoice' ? 'Order discount' : 'Line discount';
        $('#discount-sub').textContent = target === 'invoice' ? 'Apply a discount to the whole invoice.' : 'Apply a discount to ' + (line?.name || 'this item') + '.';
        const current = target === 'invoice' ? state.invoiceDiscount : { type: line?.discount_type === 'none' ? 'percent' : line?.discount_type, value: line?.discount_value || 0 };
        $('#discount-type').value = current.type === 'amount' ? 'amount' : 'percent';
        $('#discount-value').value = current.value || 0;
        openModal('discount-modal');
        setTimeout(() => $('#discount-value').focus(), 100);
    };
    $('#invoice-discount-btn').addEventListener('click', () => openDiscount('invoice'));
    $('#discount-apply').addEventListener('click', () => {
        const type = $('#discount-type').value;
        const value = Number($('#discount-value').value || 0);
        if (value < 0) { toast('Discount cannot be negative.', 'error'); return; }
        const discount = value > 0 ? { type, value } : { type: 'none', value: 0 };
        if (state.discountTarget?.target === 'invoice') {
            state.invoiceDiscount = discount;
        } else if (state.discountTarget?.index !== null) {
            const item = state.cart[state.discountTarget.index];
            if (item) { item.discount_type = discount.type; item.discount_value = discount.value; }
        }
        closeModal('discount-modal');
        renderCart();
    });

    const openOverride = (index) => {
        state.activeLine = index;
        const line = state.cart[index];
        $('#override-price').value = line.unit_price;
        $('#override-reason').value = line.override_reason || '';
        openModal('override-modal');
        setTimeout(() => $('#override-price').focus(), 100);
    };
    $('#override-apply').addEventListener('click', () => {
        const line = state.cart[state.activeLine];
        const price = Number($('#override-price').value || 0);
        const reason = $('#override-reason').value.trim();
        if (!line) return;
        if (price < 0) { toast('Price cannot be negative.', 'error'); return; }
        if (!reason) { toast('A reason is required for a price override.', 'error'); return; }
        line.unit_price = num(price);
        line.price_override = Math.abs(price - line.original_unit_price) >= 0.01;
        line.override_reason = reason;
        closeModal('override-modal');
        renderCart();
    });

    // ---------------------------------------------------------------- holds
    const openHolds = async () => {
        openModal('holds-modal');
        try {
            const data = await api('/api/pos/holds');
            const list = $('#holds-list');
            list.innerHTML = data.data.length ? data.data.map(hold => `<div class="hold-row">
                <div><div class="ref">${esc(hold.reference)}</div><div class="meta">${esc(hold.customer || 'Walk-in')} · ${hold.items} items · ${esc(hold.created_at)}</div></div>
                <div class="actions"><button class="btn btn-sm" data-resume="${hold.id}">Resume</button><button class="btn btn-secondary btn-sm" data-discard="${hold.id}">Discard</button></div>
            </div>`).join('') : '<div class="empty">No held carts.</div>';
        } catch (e) { toast(e.message, 'error'); }
    };
    $('#holds-btn').addEventListener('click', openHolds);

    const saveHold = async () => {
        if (!state.cart.length) { toast('Cart is empty.', 'error'); return; }
        const note = prompt('Note for this hold (optional):', '') ?? null;
        try {
            await api('/api/pos/holds', { method: 'POST', body: JSON.stringify({ cart: { items: state.cart, customer: state.customer, totals: totals(), invoice_discount: state.invoiceDiscount }, customer_id: state.customer?.id || null, note }) });
            state.cart = [];
            state.invoiceDiscount = { type: 'none', value: 0 };
            renderCart();
            state.holds += 1;
            $('#holds-count').textContent = state.holds;
            closeModal('holds-modal');
            toast('Cart held.');
        } catch (e) { toast(e.message, 'error'); }
    };
    $('#hold-btn').addEventListener('click', saveHold);
    $('#save-hold-btn').addEventListener('click', saveHold);

    $('#holds-list').addEventListener('click', async (e) => {
        const resume = e.target.closest('[data-resume]');
        const discard = e.target.closest('[data-discard]');
        if (resume) {
            try {
                const data = await api('/api/pos/holds/' + resume.dataset.resume + '/resume', { method: 'POST', body: '{}' });
                state.cart = data.cart.items || [];
                state.invoiceDiscount = data.cart.invoice_discount || { type: 'none', value: 0 };
                if (data.customer) setCustomer(data.customer);
                closeModal('holds-modal');
                renderCart();
                toast('Held cart resumed.');
            } catch (err) { toast(err.message, 'error'); }
        }
        if (discard) {
            if (!confirm('Discard this held cart?')) return;
            try {
                await api('/api/pos/holds/' + discard.dataset.discard, { method: 'DELETE' });
                openHolds();
                toast('Hold discarded.');
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    // ---------------------------------------------------------------- payment
    const paymentTotal = () => state.payments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const cashChange = () => state.payments.filter(p => p.method === 'cash').reduce((sum, p) => sum + Math.max(0, Number(p.tendered || p.amount || 0) - Number(p.amount || 0)), 0);

    const renderPayments = () => {
        const t = totals();
        $('#pay-total').textContent = money(t.total);
        $('#payment-list').innerHTML = state.payments.map((payment, index) => `<div class="payment-row" data-index="${index}">
            <span class="method">${payment.method === 'bank_transfer' ? 'Bank' : payment.method}</span>
            <input type="number" step="0.01" min="0" value="${payment.amount}" data-field="amount" data-index="${index}" aria-label="Amount">
            ${payment.method === 'cash'
                ? `<input type="number" step="0.01" min="0" value="${payment.tendered ?? payment.amount}" data-field="tendered" data-index="${index}" aria-label="Tendered" placeholder="Tendered">`
                : `<input type="text" value="${esc(payment.reference || '')}" data-field="reference" data-index="${index}" placeholder="Reference">`}
            <button type="button" class="remove" data-remove="${index}">×</button>
        </div>`).join('');

        const remaining = num(t.total - paymentTotal());
        const balance = $('#pay-balance');
        $('#pay-remaining').textContent = money(Math.max(0, remaining));
        balance.classList.toggle('done', remaining <= 0.009);
        $('#pay-change').textContent = money(cashChange());
        $('#complete-sale-btn').disabled = remaining > 0.009 || remaining < -0.009 || !state.payments.length || (state.payments.some(p => p.method === 'credit') && !state.customer);
    };

    $('#payment-list').addEventListener('input', (e) => {
        const index = Number(e.target.dataset.index);
        const field = e.target.dataset.field;
        if (Number.isNaN(index) || !field || !state.payments[index]) return;
        state.payments[index][field] = field === 'reference' ? e.target.value : Number(e.target.value || 0);
        if (field === 'amount' && state.payments[index].method === 'cash' && Number(state.payments[index].tendered) < Number(state.payments[index].amount)) {
            state.payments[index].tendered = Number(state.payments[index].amount);
        }
        renderPayments();
    });
    $('#payment-list').addEventListener('click', (e) => {
        const remove = e.target.closest('[data-remove]');
        if (!remove) return;
        state.payments.splice(Number(remove.dataset.remove), 1);
        renderPayments();
    });

    const addPayment = (method) => {
        const t = totals();
        const remaining = num(t.total - paymentTotal());
        if (remaining <= 0.009) { toast('The invoice is already fully paid.', 'error'); return; }
        state.payments.push({ method, amount: remaining, tendered: method === 'cash' ? remaining : null, reference: '' });
        $$('.method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === method));
        renderPayments();
    };
    $$('.method-btn').forEach(btn => btn.addEventListener('click', () => addPayment(btn.dataset.method)));

    const openPayment = () => {
        if (!state.shift) { openModal('shift-modal'); toast('Open a shift before taking payment.', 'error'); return; }
        if (!state.cart.length) { toast('Cart is empty.', 'error'); return; }
        const t = totals();
        state.payments = [{ method: 'cash', amount: t.total, tendered: t.total, reference: '' }];
        $('#approval-pin').value = '';
        $('#sale-note').value = '';
        renderPayments();
        openModal('payment-modal');
    };
    $('#pay-btn').addEventListener('click', openPayment);

    $('#complete-sale-btn').addEventListener('click', async () => {
        const t = totals();
        const payload = {
            customer_id: state.customer?.id || null,
            items: state.cart.map(item => ({
                product_id: item.product_id,
                qty: item.qty,
                unit_price: item.unit_price,
                discount_type: item.discount_type,
                discount_value: item.discount_value,
                override_reason: item.override_reason,
            })),
            invoice_discount_type: state.invoiceDiscount.type,
            invoice_discount_value: state.invoiceDiscount.value,
            payments: state.payments,
            approval_pin: $('#approval-pin').value || null,
            notes: $('#sale-note').value || null,
        };
        try {
            $('#complete-sale-btn').disabled = true;
            const data = await api('/api/pos/sales', { method: 'POST', body: JSON.stringify(payload) });
            state.lastSale = data.sale;
            $('#complete-total').textContent = money(data.sale.total);
            $('#complete-invoice').textContent = data.sale.invoice_number + ' · ' + data.sale.payment_status;
            $('#complete-receipt').href = data.sale.receipt_url;
            $('#complete-invoice-link').href = data.sale.invoice_url;
            $('#complete-change').textContent = Number(data.sale.change_total) > 0 ? 'Change due: ' + money(data.sale.change_total) : 'Paid in full. No balance due.';
            closeModal('payment-modal');
            openModal('complete-modal');
            state.cart = [];
            state.customer = null;
            state.invoiceDiscount = { type: 'none', value: 0 };
            state.payments = [];
            setCustomer(null);
            renderCart();
        } catch (err) {
            toast(err.message, 'error');
            $('#complete-sale-btn').disabled = false;
        }
    });

    $('#new-sale-btn').addEventListener('click', () => {
        closeModal('complete-modal');
        $('#search-input').value = '';
        search('');
        $('#search-input').focus();
    });

    // ---------------------------------------------------------------- shift
    $('#shift-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const data = await api('/api/pos/shift/open', { method: 'POST', body: JSON.stringify({ opening_float: Number($('#opening-float').value || 0) }) });
            state.shift = data.shift;
            $('#shift-status').textContent = 'Shift open';
            $('#shift-status').className = 'pos-status open';
            closeModal('shift-modal');
            toast('Shift opened. Opening float ' + money(data.shift.opening_float) + '.');
            $('#search-input').focus();
        } catch (err) { toast(err.message, 'error'); }
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
            $('#shift-status').textContent = 'Shift closed';
            $('#shift-status').className = 'pos-status closed';
            closeModal('close-shift-modal');
            toast('Shift closed. Variance ' + money(data.shift.variance) + '.', Math.abs(Number(data.shift.variance)) > 0.009 ? 'error' : 'success');
        } catch (err) { toast(err.message, 'error'); }
    });

    // ---------------------------------------------------------------- keyboard & modal close
    $$('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
    $$('.modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', (e) => { if (e.target === backdrop) backdrop.classList.remove('open'); }));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closeAll(); return; }
        if (e.key === 'F2') { e.preventDefault(); openQuickAdd(); }
        if (e.key === 'F3') { e.preventDefault(); $('#search-input').focus(); $('#search-input').select(); }
        if (e.key === 'F4') { e.preventDefault(); $('#customer-btn').click(); }
        if (e.key === 'F6') { e.preventDefault(); saveHold(); }
        if (e.key === 'F9') { e.preventDefault(); openPayment(); }
    });

    renderCart();
    if (!state.shift) setTimeout(() => $('#opening-float')?.focus(), 300);
})();
