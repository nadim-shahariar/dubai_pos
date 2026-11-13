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

    const brandSelect = document.querySelector('[data-brand-select]');
    const modelSelect = document.querySelector('[data-model-select]');
    if (brandSelect && modelSelect) {
        const options = Array.from(modelSelect.querySelectorAll('option[data-brand]'));
        const filterModels = () => {
            const brand = brandSelect.value;
            modelSelect.innerHTML = '<option value="">Select model</option>';
            options.filter(o => !brand || o.dataset.brand === brand).forEach(o => modelSelect.appendChild(o.cloneNode(true)));
        };
        brandSelect.addEventListener('change', filterModels);
        filterModels();
    }


    const fitBrand = document.querySelector('[data-vehicle-brand]');
    const fitModel = document.querySelector('[data-vehicle-model]');
    if (fitBrand && fitModel) {
        const catalog = Array.isArray(window.VEHICLE_CATALOG) ? window.VEHICLE_CATALOG : [];
        const list = document.querySelector('#fitment-model-list');
        const syncFitmentModels = () => {
            if (!list) return;
            list.innerHTML = '';
            const name = fitBrand.value.trim().toLowerCase();
            const brand = catalog.find(b => String(b.name).toLowerCase() === name);
            if (!brand) return;
            (brand.models || []).forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                list.appendChild(option);
            });
        };
        fitBrand.addEventListener('input', syncFitmentModels);
        fitBrand.addEventListener('change', syncFitmentModels);
        syncFitmentModels();
    }

    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.add('hidden'));
            btn.classList.add('active');
            document.querySelector(`[data-tab-panel="${btn.dataset.tab}"]`)?.classList.remove('hidden');
        });
    });
})();
