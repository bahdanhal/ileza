const assert = require('node:assert/strict');
const { compare, progressiveAnnualTax } = require('../../public/income-math.js');

assert.equal(progressiveAnnualTax(30000), 0);
assert.equal(progressiveAnnualTax(120000), 10800);
assert.equal(progressiveAnnualTax(130000), 14000);

const regular = compare({ budget: 12000, studentUnder26: false, costs: 500, taxation: 'linear', zus: 'standard', lumpRate: 12, llcCosts: 600 });
assert.equal(regular.employment.cost, 12000);
assert.ok(regular.employment.net > 0 && regular.employment.net < regular.employment.gross);
assert.ok(regular.work.net > regular.employment.net);
assert.equal(regular.b2b.businessCosts, 500);
assert.equal(regular.spolka.cost, 12000);
assert.equal(regular.spolka.social, 0);
assert.equal(regular.spolka.businessCosts, 600);
assert.equal(regular.spolka.gross, 11400);
assert.ok(regular.spolka.net > 0 && regular.spolka.net < regular.spolka.gross);

const student = compare({ budget: 6000, studentUnder26: true, costs: 0, taxation: 'scale', zus: 'start', lumpRate: 12 });
assert.equal(student.mandate.net, 6000);
assert.equal(student.mandate.social, 0);
assert.equal(student.mandate.health, 0);

const uopMode = compare({ inputMode: 'uop_gross', grossUop: 10000 });
assert.equal(uopMode.employment.cost, 12048);
assert.equal(uopMode.employment.gross, 10000);

console.log('Income comparison tests passed');
