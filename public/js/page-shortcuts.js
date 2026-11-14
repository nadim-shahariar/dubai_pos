(() => {
    const catalogue = document.getElementById('catalogue-search');
    const search = catalogue || document.getElementById('sales-search');
    const sales = Array.from(document.querySelectorAll('[data-sale-row]'));
    let selected = 0;
    const select = index => {
        if (!sales.length) return;
        selected = Math.max(0, Math.min(index, sales.length - 1));
        sales.forEach((row, i) => { row.classList.toggle('sale-selected', i === selected); row.setAttribute('aria-selected', String(i === selected)); });
    };
    sales.forEach((row, index) => row.addEventListener('click', () => select(index)));
    select(0);
    document.addEventListener('keydown', event => {
        if (event.ctrlKey || event.metaKey || event.defaultPrevented) return;
        const modal = document.querySelector('.modal-backdrop.open');
        const typing = event.target.matches('input, textarea, select, [contenteditable="true"]');
        if (event.key === 'F2' && catalogue && !modal) { event.preventDefault(); document.getElementById('quick-add-product')?.click(); return; }
        if (event.key === 'F3' && search && !modal) { event.preventDefault(); search.focus(); search.select(); return; }
        if (modal || typing) return;
        if (catalogue && event.altKey && event.key.toLowerCase() === 'n') { event.preventDefault(); document.querySelector('[data-create-product]')?.click(); }
        if (catalogue && event.altKey && event.key.toLowerCase() === 'l') { event.preventDefault(); document.querySelector('[data-low-stock]')?.click(); }
        if (!sales.length || event.target.matches('a,button')) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); select(selected + (event.key === 'ArrowDown' ? 1 : -1)); sales[selected].scrollIntoView({ block: 'nearest' }); }
        if (event.key === 'Enter') { event.preventDefault(); sales[selected].querySelector('[data-sale-open]')?.click(); }
        if (event.altKey && event.key.toLowerCase() === 'p') { event.preventDefault(); sales[selected].querySelector('[data-invoice-print]')?.click(); }
        if (event.altKey && event.key.toLowerCase() === 'r') { event.preventDefault(); sales[selected].querySelector('[data-receipt-print]')?.click(); }
    });
})();
