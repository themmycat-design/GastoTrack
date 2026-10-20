import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import StaffPinGate from '../src/components/StaffPinGate';
import StaffPinScreen from '../src/screens/auth/StaffPinScreen';
import {getStaffPin, saveStaffPin} from '../src/services/staffPinStorage';

jest.mock('../src/services/staffPinStorage', () => ({
  getStaffPin: jest.fn(),
  saveStaffPin: jest.fn(),
}));
jest.mock('../src/screens/auth/StaffPinScreen', () => 'StaffPinScreen');

const flush = () => new Promise(resolve => setTimeout(resolve, 0));

describe('staff MPIN gate', () => {
  let tree;
  const onSignOut = jest.fn(() => Promise.resolve());
  const enter = async digits => {
    for (const digit of digits) {
      await act(async () => {
        tree.root.findByType(StaffPinScreen).props.onDigit(digit);
        await flush();
      });
    }
  };

  beforeEach(() => {
    jest.clearAllMocks();
    getStaffPin.mockResolvedValue(null);
    saveStaffPin.mockResolvedValue(undefined);
  });
  afterEach(() => {
    if (tree) act(() => tree.unmount());
    tree = null;
  });

  const mount = async () => {
    await act(async () => {
      tree = TestRenderer.create(<StaffPinGate userId={17} onSignOut={onSignOut}><React.Fragment>workspace</React.Fragment></StaffPinGate>);
      await flush();
    });
  };

  it('requires setup and matching confirmation before showing workspace', async () => {
    await mount();
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('setup');
    await enter('1234');
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('confirm');
    await enter('1235');
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('setup');
    expect(saveStaffPin).not.toHaveBeenCalled();
    await enter('0042');
    await enter('0042');
    expect(saveStaffPin).toHaveBeenCalledWith(17, '0042');
    expect(tree.root.findAllByType(StaffPinScreen)).toHaveLength(0);
  });

  it('blocks a restored session until the saved PIN is entered', async () => {
    getStaffPin.mockResolvedValue('0042');
    await mount();
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('unlock');
    await enter('1234');
    expect(tree.root.findByType(StaffPinScreen).props.error).toContain('4 tries remaining');
    await enter('0042');
    expect(tree.root.findAllByType(StaffPinScreen)).toHaveLength(0);
  });

  it('signs out after five incorrect PIN attempts', async () => {
    getStaffPin.mockResolvedValue('0042');
    await mount();
    for (let attempt = 0; attempt < 5; attempt += 1) await enter('9999');
    expect(onSignOut).toHaveBeenCalledTimes(1);
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('unlock');
  });
});
