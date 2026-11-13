(() => {
    const parseClipboard = (text) => {
        const rows = []; let row = [], value = '', quoted = false;
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (ch === '"' && (quoted || value === '')) {
                if (quoted && text[i + 1] === '"') { value += '"'; i++; }
                else quoted = !quoted;
            } else if (!quoted && (ch === '\t' || ch === '\n')) {
                row.push(value); value = '';
                if (ch === '\n') { rows.push(row); row = []; }
            } else value += ch;
        }
        row.push(value);
        if (row.some(v => v !== '') || !rows.length) rows.push(row);
        return rows;
    };
    const cleanValue = value => String(value ?? '').trim() === '' ? null : String(value).trim();
    globalThis.ProductSheetUtils = { parseClipboard, cleanValue };
    if (typeof document === 'undefined') return;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let sequence = 0;
    const productFields = [
        { key: 'name', label: 'Product name *', required: true, max: 150 },
        { key: 'primary_part_number', label: 'Part no. (optional)', max: 50 },
        { key: 'category_name', label: 'Category / new name', list: 'categories', max: 100 },
        { key: 'brand', label: 'Part brand', list: 'partBrands', max: 80 },
        { key: 'selling_price', label: 'Selling price *', type: 'number', required: true, step: '.01' },
        { key: 'cost_price', label: 'Buying price *', type: 'number', required: true, step: '.01' },
        { key: 'secret_cost_code', label: 'Buying code *', required: true, max: 20 },
        { key: 'stock_qty', label: 'Opening stock *', type: 'number', required: true, step: '.001' },
        { key: 'barcode', label: 'Barcode', max: 64, more: true },
        { key: 'unit', label: 'Unit', max: 20, value: 'piece', more: true },
        { key: 'tax_rate', label: 'VAT %', type: 'number', step: '.01', maxNumber: 100, more: true },
        { key: 'reorder_level', label: 'Reorder level', type: 'number', step: '.001', value: '0', more: true },
        { key: 'bin_location', label: 'Bin / shelf', max: 50, more: true },
        { key: 'description', label: 'Description', type: 'textarea', max: 1000, more: true },
        { key: 'name_ar', label: 'Arabic name', max: 150, more: true },
    ];
    const variantFields = [
        { key: 'vehicle_brand', label: 'Vehicle brand *', list: 'vehicleBrands', required: true, max: 60 },
        { key: 'vehicle_model', label: 'Model *', list: 'vehicleModels', required: true, max: 80 },
        { key: 'year_from', label: 'Year from', type: 'number', value: '2018', min: 1950, maxNumber: 2100, step: '1' },
        { key: 'year_to', label: 'Year to', type: 'number', value: '2024', min: 1950, maxNumber: 2100, step: '1' },
        { key: 'category_name', label: 'Category / new name', list: 'categories', max: 100 },
        { key: 'mapped_part_number', label: 'Part no. (optional)', max: 50 },
        { key: 'cost_price', label: 'Buying price *', type: 'number', required: true, step: '.01' },
        { key: 'stock_qty', label: 'Stock *', type: 'number', required: true, step: '.001' },
    ];

    document.querySelectorAll('[data-product-sheet]').forEach(form => {
        const config = JSON.parse(form.dataset.sheetConfig);
        const prefix = 'product-sheet-' + (++sequence);
        const body = form.querySelector('[data-sheet-body]');
        const message = form.querySelector('[data-sheet-message]');
        const saveButton = form.querySelector('[data-sheet-save]');
        const entries = new Map(); let rowSequence = 0, busy = false;
        const field = (row, key) => row.querySelector(`[data-field="${key}"]`);
        const mainRows = () => Array.from(body.querySelectorAll(':scope > [data-sheet-main]'));
        const notify = (items, success = false) => {
            message.classList.remove('hidden'); message.classList.toggle('sheet-success', success);
            message.innerHTML = '<strong>' + (success ? 'Saved successfully' : 'Please check the sheet') + '</strong><ul>' + items.map(item => '<li>' + esc(item) + '</li>').join('') + '</ul>';
        };
        Object.entries(config).forEach(([name, values]) => {
            if (!Array.isArray(values)) return;
            const list = document.createElement('datalist'); list.id = prefix + '-' + name;
            list.innerHTML = values.map(v => `<option value="${esc(v)}"></option>`).join(''); form.appendChild(list);
        });
        const inputHTML = (definition, value) => {
            const valueToUse = value ?? (definition.key === 'tax_rate' ? config.taxRate : definition.value) ?? '';
            const attrs = `data-field="${definition.key}" aria-label="${esc(definition.label)}" ${definition.required ? 'required' : ''} ${definition.max ? 'maxlength="' + definition.max + '"' : ''}`;
            if (definition.type === 'textarea') return `<textarea ${attrs}>${esc(valueToUse)}</textarea>`;
            return `<input ${attrs} type="${definition.type || 'text'}" value="${esc(valueToUse)}" ${definition.list ? 'list="' + prefix + '-' + definition.list + '" autocomplete="off"' : ''} ${definition.type === 'number' ? `min="${definition.min ?? 0}" step="${definition.step || 'any'}" ${definition.maxNumber !== undefined ? 'max="' + definition.maxNumber + '"' : ''}` : ''}>`;
        };
        const headers = fields => '<tr><th>#</th>' + fields.map(d => `<th class="${d.more ? 'sheet-more-cell' : ''}">${esc(d.label)}</th>`).join('') + '<th>Actions</th></tr>';
        const cells = (fields, values = {}) => '<td class="sheet-row-number"></td>' + fields.map(d => `<td class="${d.more ? 'sheet-more-cell' : ''}">${inputHTML(d, values[d.key])}</td>`).join('');
        form.querySelector('[data-sheet-head]').innerHTML = headers(productFields);
        const renumber = () => {
            mainRows().forEach((row, index) => {
                row.querySelector('.sheet-row-number').textContent = index + 1;
                const entry = entries.get(row.dataset.sheetMain);
                entry.panel.querySelector('[data-variant-title]').textContent = `Variants for row ${index + 1}`;
                Array.from(entry.variants.children).forEach((v, i) => v.querySelector('.sheet-row-number').textContent = i + 1);
            });
            form.querySelector('[data-sheet-count]').textContent = mainRows().length + ' product rows';
        };
        const syncVariants = entry => {
            const hasVariants = entry.variants.children.length > 0;
            ['cost_price', 'stock_qty'].forEach(key => {
                const input = field(entry.row, key);
                input.disabled = hasVariants;
                input.placeholder = hasVariants ? 'Per variant' : '';
            });
            entry.row.querySelector('[data-sheet-variants]').textContent = hasVariants ? `Variants (${entry.variants.children.length})` : '+ Variants';
            renumber();
        };
        const addVariant = (entry, values = {}) => {
            if (entry.variants.children.length >= 50) { notify(['Maximum 50 variants per product.']); return null; }
            const row = document.createElement('tr'); row.dataset.sheetVariant = 'true';
            row.innerHTML = cells(variantFields, values) + '<td class="sheet-row-actions"><button type="button" class="btn btn-ghost btn-sm text-danger" data-remove-variant-row>Remove</button></td>';
            entry.variants.appendChild(row); entry.row.dataset.dirty = 'true'; syncVariants(entry); return row;
        };
        const addProduct = (values = {}) => {
            if (mainRows().length >= 100) { notify(['Maximum 100 products per batch.']); return null; }
            const id = String(++rowSequence);
            const row = document.createElement('tr'); row.dataset.sheetMain = id;
            row.innerHTML = cells(productFields, values) + '<td class="sheet-row-actions"><button type="button" class="btn btn-secondary btn-sm" data-sheet-variants>+ Variants</button><button type="button" class="btn btn-ghost btn-sm text-danger" data-remove-product-row>×</button></td>';
            const panel = document.createElement('tr'); panel.dataset.sheetChildren = id; panel.hidden = true;
            panel.innerHTML = `<td colspan="${productFields.length + 2}" class="sheet-variants-cell"><div class="sheet-variants-panel"><div class="sheet-variants-title"><div><strong data-variant-title></strong><p>One variant per row. Each has independent stock; buying price and stock are required.</p></div><button type="button" class="btn btn-secondary btn-sm" data-add-variant-row>+ Add variant row</button></div><div class="sheet-scroll"><table class="entry-sheet-table sheet-variant-table"><thead>${headers(variantFields)}</thead><tbody></tbody></table></div></div></td>`;
            body.append(row, panel);
            entries.set(id, { row, panel, variants: panel.querySelector('tbody') }); renumber(); return row;
        };
        const entryFor = target => {
            const main = target.closest('[data-sheet-main]');
            return entries.get(main?.dataset.sheetMain || target.closest('[data-sheet-children]')?.dataset.sheetChildren);
        };
        const dataFrom = (row, fields) => Object.fromEntries(fields.map(d => [d.key, cleanValue(field(row, d.key).value)]));
        const usedRows = () => mainRows().filter(row => row.dataset.dirty === 'true' || field(row, 'name').value.trim() || field(row, 'secret_cost_code').value.trim());
        const markField = (entry, path) => {
            const match = path.match(/^variants\.(\d+)\.(.+)$/);
            const row = match ? entry.variants.children[Number(match[1])] : entry.row;
            if (match) entry.panel.hidden = false;
            const input = row && field(row, match ? match[2] : path);
            input?.classList.add('sheet-invalid');
            if (input?.closest('.sheet-more-cell')) form.classList.add('sheet-show-more');
        };
        const lock = value => {
            form.querySelectorAll('input,textarea,select,button').forEach(el => el.disabled = value);
            if (!value) entries.forEach(syncVariants);
        };
        form.querySelector('[data-sheet-add]').addEventListener('click', () => { if (!busy) field(addProduct() || mainRows().at(-1), 'name').focus(); });
        form.querySelector('[data-sheet-more]').addEventListener('click', event => {
            form.classList.toggle('sheet-show-more'); event.target.textContent = form.classList.contains('sheet-show-more') ? 'Fewer columns' : 'More columns';
        });
        form.addEventListener('input', event => {
            if (!event.target.dataset.field) return;
            event.target.classList.remove('sheet-invalid'); const entry = entryFor(event.target); if (entry) entry.row.dataset.dirty = 'true';
        });
        form.addEventListener('click', event => {
            if (busy || form.dataset.saved === 'true') return;
            const entry = entryFor(event.target); if (!entry) return;
            if (event.target.closest('[data-sheet-variants]')) {
                entry.panel.hidden = !entry.panel.hidden;
                if (!entry.variants.children.length) addVariant(entry);
            }
            if (event.target.closest('[data-add-variant-row]')) addVariant(entry);
            if (event.target.closest('[data-remove-variant-row]')) { event.target.closest('[data-sheet-variant]').remove(); syncVariants(entry); }
            if (event.target.closest('[data-remove-product-row]')) {
                entries.delete(entry.row.dataset.sheetMain); entry.row.remove(); entry.panel.remove();
                if (!mainRows().length) addProduct(); renumber();
            }
        });
        const reset = () => {
            if (busy) return;
            if (usedRows().length && form.dataset.saved !== 'true' && !confirm('Clear these unsaved product rows?')) return;
            body.innerHTML = ''; entries.clear(); form.dataset.saved = 'false'; lock(false);
            message.classList.add('hidden'); saveButton.textContent = 'Save all products';
            addProduct(); field(mainRows()[0], 'name').focus();
        };
        form.querySelector('[data-sheet-reset]').addEventListener('click', reset);
        form.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); form.requestSubmit(); return; }
            const input = event.target.closest('[data-field]'); if (!input || busy || form.dataset.saved === 'true') return;
            if (event.key === 'Enter' && !(input.tagName === 'TEXTAREA' && event.shiftKey)) {
                event.preventDefault();
                const row = input.closest('tr');
                const controls = Array.from(row.querySelectorAll('[data-field]')).filter(el => !el.disabled && el.offsetParent !== null);
                const index = controls.indexOf(input), next = controls[index + (event.shiftKey ? -1 : 1)];
                if (next) next.focus();
                else if (!event.shiftKey) {
                    const entry = entryFor(input);
                    const nextRow = row.dataset.sheetVariant ? (row.nextElementSibling || addVariant(entry)) : (mainRows()[mainRows().indexOf(row) + 1] || addProduct());
                    nextRow?.querySelector('[data-field]')?.focus();
                }
            }
            if (['ArrowUp', 'ArrowDown'].includes(event.key) && !input.list && input.tagName !== 'TEXTAREA') {
                const row = input.closest('tr'), rows = row.dataset.sheetVariant ? Array.from(row.parentElement.children) : mainRows();
                const next = rows[rows.indexOf(row) + (event.key === 'ArrowDown' ? 1 : -1)];
                if (next) { event.preventDefault(); field(next, input.dataset.field)?.focus(); }
            }
        });
        form.addEventListener('paste', event => {
            const input = event.target.closest('[data-field]'); if (!input || busy || form.dataset.saved === 'true') return;
            const text = event.clipboardData.getData('text/plain');
            if (!text.includes('\t') && (!text.includes('\n') || input.tagName === 'TEXTAREA')) return;
            event.preventDefault();
            const grid = parseClipboard(text), row = input.closest('tr'), entry = entryFor(input), variant = !!row.dataset.sheetVariant;
            const fields = variant ? variantFields : productFields.filter(d => !d.more || form.classList.contains('sheet-show-more'));
            const column = fields.findIndex(d => d.key === input.dataset.field);
            const rows = variant ? Array.from(entry.variants.children) : mainRows(), start = rows.indexOf(row);
            if (start + grid.length > (variant ? 50 : 100) || grid.some(cells => cells.length + column > fields.length)) {
                notify(['Clipboard exceeds the available rows or columns. Paste starting in the first cell, or enable More columns.']); return;
            }
            grid.forEach((cells, i) => {
                const target = rows[start + i] || (variant ? addVariant(entry) : addProduct());
                cells.forEach((value, j) => {
                    const control = field(target, fields[column + j].key);
                    if (!control.disabled) { control.value = value; control.classList.remove('sheet-invalid'); }
                });
                entryFor(target).row.dataset.dirty = 'true';
            });
        });
        form.addEventListener('submit', async event => {
            event.preventDefault(); if (busy || form.dataset.saved === 'true') return;
            const submitted = usedRows(); if (!submitted.length) { notify(['Enter at least one product.']); return; }
            const errors = [];
            form.querySelectorAll('.sheet-invalid').forEach(el => el.classList.remove('sheet-invalid'));
            submitted.forEach((row, index) => {
                const entry = entryFor(row);
                const groups = [[row, productFields, ''], ...Array.from(entry.variants.children).map((v, i) => [v, variantFields, `, variant ${i + 1}`])];
                groups.forEach(([target, fields, label]) => fields.forEach(d => {
                    const control = field(target, d.key);
                    if (!control.disabled && !control.checkValidity()) {
                        control.classList.add('sheet-invalid'); errors.push(`Row ${index + 1}${label}: ${d.label.replace(' *', '')} — ${control.validationMessage}`);
                        if (label) entry.panel.hidden = false;
                        if (control.closest('.sheet-more-cell')) form.classList.add('sheet-show-more');
                    }
                }));
            });
            if (errors.length) { notify(errors); form.querySelector('.sheet-invalid')?.focus(); return; }
            const rows = submitted.map(row => {
                const entry = entryFor(row), value = dataFrom(row, productFields);
                value.variants = Array.from(entry.variants.children).map(v => dataFrom(v, variantFields));
                if (value.variants.length) { delete value.cost_price; delete value.stock_qty; }
                return value;
            });
            busy = true; lock(true); saveButton.textContent = 'Saving products…'; message.classList.add('hidden');
            try {
                const response = await fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify({ rows }) });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const problems = [];
                    Object.entries(result.errors || {}).forEach(([path, messages]) => {
                        const match = path.match(/^rows\.(\d+)\.(.+)$/);
                        if (match && submitted[Number(match[1])]) markField(entryFor(submitted[Number(match[1])]), match[2]);
                        problems.push((match ? `Row ${Number(match[1]) + 1}: ` : '') + messages.join(' '));
                    });
                    notify(problems.length ? problems : [result.message || 'Could not save. Your rows are still here.']);
                    lock(false); saveButton.textContent = 'Save all products';
                } else {
                    form.dataset.saved = 'true'; notify([`${result.count} products saved. Use Clear / new batch to enter more.`], true);
                    const links = document.createElement('div');
                    links.innerHTML = result.products.map(p => `<a href="${esc(p.edit_url)}">${esc(p.name)}</a>`).join(' · '); message.appendChild(links);
                    saveButton.textContent = 'Products saved'; form.querySelector('[data-sheet-reset]').disabled = false;
                }
            } catch (error) { notify(['Connection failed. Your entries are preserved. If the request reached the server, check the catalogue before retrying.']); lock(false); saveButton.textContent = 'Save all products'; }
            finally { busy = false; }
        });
        addProduct();
    });
})();
