const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync('public/js/pos.js', 'utf8');
function setup() {
    const context = vm.createContext({
        toast: () => {}, setVariantInput: (row, html) => { row.variantHTML = html; },
        variantLabel: f => f.model || 'Variant', esc: String, recalc: () => {},
    });
    for (const name of ['applyVariant', 'fillProduct', 'validateVariantNumbers']) {
        const start = source.indexOf(`    const ${name} = `);
        const end = source.indexOf('\n    };', start) + '\n    };'.length;
        vm.runInContext(source.slice(start, end) + `\nglobalThis.${name} = ${name};`, context);
    }
    const nodes = new Map();
    const row = { dataset: {}, classList: { remove() {} }, querySelector(selector) {
        if (!nodes.has(selector)) nodes.set(selector, { value: '', style: {}, classList: { add() {} } });
        return nodes.get(selector);
    }};
    return { context, row };
}
test('selecting a product with variants never displays the combined stock', () => {
    const { context, row } = setup();
    context.fillProduct(row, { id: 1, stock_qty: 999, selling_price: 125, fitments: [{ mapped_part_number: 'A', stock_qty: 5 }, { mapped_part_number: 'B', stock_qty: 8 }] });
    assert.equal(row.querySelector('.entry-stock').value, '');
    context.applyVariant(row, { id: 101, mapped_part_number: 'SAME', model: 'A', stock_qty: 5 });
    assert.equal(row.querySelector('.entry-stock').value, 5);
    assert.equal(row._variant.id, 101);
    context.applyVariant(row, { id: 102, mapped_part_number: 'SAME', model: 'B', stock_qty: 8 });
    assert.equal(row.querySelector('.entry-stock').value, 8);
    assert.equal(row._variant.id, 102);
    context.applyVariant(row, { mapped_part_number: 'C', model: 'C', stock_qty: null });
    assert.equal(row.querySelector('.entry-stock').value, 0);
});
test('a product without variants shows its single product quantity', () => {
    const { context, row } = setup();
    context.fillProduct(row, { id: 2, stock_qty: 17, selling_price: 10, fitments: [] });
    assert.equal(row.querySelector('.entry-stock').value, 17);
    assert.match(row.variantHTML, /Single product/);
});

test('variant buying price and stock require an explicit nonnegative number, including zero', () => {
    const { context } = setup();
    assert.equal(context.validateVariantNumbers([{ cost_price: '0', stock_qty: 0 }]), true);
    assert.equal(context.validateVariantNumbers([{ cost_price: null, stock_qty: 1 }]), false);
    assert.equal(context.validateVariantNumbers([{ cost_price: '10', stock_qty: null }]), false);
    assert.equal(context.validateVariantNumbers([{ cost_price: '-1', stock_qty: 1 }]), false);
    assert.equal(context.validateVariantNumbers([{ cost_price: 'abc', stock_qty: 1 }]), false);
});
