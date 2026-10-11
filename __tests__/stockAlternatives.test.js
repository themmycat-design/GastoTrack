import {needsStockAlternativeCheck} from '../src/utils/stockAlternatives';

describe('automatic stock alternative checks', () => {
  it('skips AI when every loaded item is in stock and there are no hidden pages', () => {
    expect(needsStockAlternativeCheck([{quantity: 12}, {quantity: 3}], false)).toBe(false);
  });

  it('checks shortages and additional stock pages', () => {
    expect(needsStockAlternativeCheck([{quantity: 0}], false)).toBe(true);
    expect(needsStockAlternativeCheck([{quantity: 12}], true)).toBe(true);
  });
});
