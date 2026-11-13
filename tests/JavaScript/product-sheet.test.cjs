const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { readFileSync } = require('node:fs');
const context = vm.createContext({});
vm.runInContext(readFileSync('public/js/product-sheet.js', 'utf8'), context);
const parse = input => JSON.parse(JSON.stringify(context.ProductSheetUtils.parseClipboard(input)));
test('Excel clipboard preserves columns, zero and blank optional cells', () => {
    assert.deepEqual(parse('Oil filter\t\tFilters\tDenso\t45\t25\tCODE\t0\r\nBrake pad\tBP1\tBrakes\tToyota\t125\t80\tPAD\t5\r\n'), [
        ['Oil filter', '', 'Filters', 'Denso', '45', '25', 'CODE', '0'],
        ['Brake pad', 'BP1', 'Brakes', 'Toyota', '125', '80', 'PAD', '5'],
    ]);
});
test('quoted Excel cells support tabs, newlines and escaped quotation marks', () => {
    assert.deepEqual(parse('"Oil\tfilter"\t"A ""quoted"" part"\n"Multi\nline"\t0'), [['Oil\tfilter', 'A "quoted" part'], ['Multi\nline', '0']]);
});
test('blank numeric cells stay null and explicit zero stays present', () => {
    const clean = context.ProductSheetUtils.cleanValue;
    assert.equal(clean(''), null);
    assert.equal(clean('  '), null);
    assert.equal(clean(0), '0');
    assert.equal(clean(' 0 '), '0');
});
