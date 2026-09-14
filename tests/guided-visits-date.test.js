import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

// Load the browser entry point with no form present to test calendar arithmetic.
const context = vm.createContext({ Intl, Date, document: { querySelector: () => null } });
vm.runInContext(fs.readFileSync(new URL('../src/assets/js/components/guided-visits.js', import.meta.url), 'utf8').replace('export function', 'function'), context);

for (const [name, now, expected] of [
    ['J+21', '2026-09-11T12:00:00Z', '2026-10-02'],
    ['Brussels midnight and spring DST', '2026-03-20T23:30:00Z', '2026-04-11'],
    ['autumn DST', '2026-10-15T12:00:00Z', '2026-11-05'],
    ['year boundary', '2026-12-20T12:00:00Z', '2027-01-10'],
]) {
    test(name, () => assert.equal(context.minimumVisitDate(new Date(now)), expected));
}
