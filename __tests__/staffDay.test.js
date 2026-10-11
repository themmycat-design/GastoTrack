import {staffDayKey} from '../src/utils/useStaffDay';

describe('staff day boundary', () => {
  it('changes at local midnight, including a month change', () => {
    expect(staffDayKey(new Date(2026, 9, 31, 23, 59, 59))).toBe('2026-10-31');
    expect(staffDayKey(new Date(2026, 10, 1, 0, 0, 0))).toBe('2026-11-01');
  });
});
