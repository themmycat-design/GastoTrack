import React from 'react';
import {AppState} from 'react-native';
import TestRenderer, {act} from 'react-test-renderer';
import StaffPinGate from '../src/components/StaffPinGate';
import StaffPinScreen from '../src/screens/auth/StaffPinScreen';
import {
  clearStaffPin, getStaffPin, getStaffPinAttempts, recordStaffPinFailure,
  resetStaffPinAttempts, saveStaffPin,
} from '../src/services/staffPinStorage';

jest.mock('../src/services/staffPinStorage', () => ({
  getStaffPin: jest.fn(),
  saveStaffPin: jest.fn(),
  getStaffPinAttempts: jest.fn(),
  recordStaffPinFailure: jest.fn(),
  resetStaffPinAttempts: jest.fn(),
  clearStaffPin: jest.fn(),
}));
jest.mock('../src/screens/auth/StaffPinScreen', () => 'StaffPinScreen');

const flush = () => new Promise(resolve => setTimeout(resolve, 0));

describe('staff MPIN gate', () => {
  let tree;
  let persistedAttempts = 0;
  let appStateChanged;
  let now;
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
    now = 1_000_000;
    jest.spyOn(Date, 'now').mockImplementation(() => now);
    jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, listener) => {
      appStateChanged = listener;
      return {remove: jest.fn()};
    });
    getStaffPin.mockResolvedValue(null);
    saveStaffPin.mockResolvedValue(undefined);
    persistedAttempts = 0;
    getStaffPinAttempts.mockImplementation(async () => persistedAttempts);
    recordStaffPinFailure.mockImplementation(async () => ++persistedAttempts);
    resetStaffPinAttempts.mockImplementation(async () => {persistedAttempts = 0;});
    clearStaffPin.mockImplementation(async () => {persistedAttempts = 0;});
  });
  afterEach(() => {
    if (tree) act(() => tree.unmount());
    tree = null;
    jest.restoreAllMocks();
  });

  const mount = async (passwordAuthenticated = false, content = <React.Fragment>workspace</React.Fragment>) => {
    await act(async () => {
      tree = TestRenderer.create(<StaffPinGate userId={17} onSignOut={onSignOut}
        passwordAuthenticated={passwordAuthenticated}>{content}</StaffPinGate>);
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

  it('keeps a brief app interruption open and preserves work after a long background lock', async () => {
    getStaffPin.mockResolvedValue('0042');
    const onWorkspaceUnmount = jest.fn();
    const Workspace = () => {
      React.useEffect(() => () => onWorkspaceUnmount(), []);
      return <React.Fragment>unfinished order</React.Fragment>;
    };
    await mount(false, <Workspace />);
    await enter('0042');

    act(() => appStateChanged('inactive'));
    now += 20 * 60 * 1000;
    act(() => appStateChanged('active'));
    expect(tree.root.findAllByType(StaffPinScreen)).toHaveLength(0);

    act(() => appStateChanged('background'));
    now += 60 * 1000;
    act(() => appStateChanged('active'));
    expect(tree.root.findAllByType(StaffPinScreen)).toHaveLength(0);

    act(() => appStateChanged('background'));
    now += 15 * 60 * 1000;
    act(() => appStateChanged('active'));
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('unlock');
    expect(tree.root.findByType(Workspace)).toBeTruthy();
    expect(onWorkspaceUnmount).not.toHaveBeenCalled();

    await enter('0042');
    expect(tree.root.findAllByType(StaffPinScreen)).toHaveLength(0);
    expect(onWorkspaceUnmount).not.toHaveBeenCalled();
  });

  it('signs out after five incorrect PIN attempts', async () => {
    getStaffPin.mockResolvedValue('0042');
    await mount();
    for (let attempt = 0; attempt < 5; attempt += 1) await enter('9999');
    expect(onSignOut).toHaveBeenCalledTimes(1);
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('locked');
    act(() => tree.unmount());
    tree = null;
    await mount();
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('locked');
  });

  it('allows a new PIN only after a fresh password sign-in', async () => {
    getStaffPin.mockResolvedValue('0042');
    await mount(true);
    await act(async () => tree.root.findByType(StaffPinScreen).props.onRecover());
    expect(clearStaffPin).toHaveBeenCalledWith(17);
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('setup');
    await enter('2222');
    await enter('2222');
    expect(saveStaffPin).toHaveBeenCalledWith(17, '2222');
  });

  it('offers recovery on a restored session and requires password sign-in before clearing the PIN', async () => {
    getStaffPin.mockResolvedValue('0042');
    await mount();
    const pinScreen = tree.root.findByType(StaffPinScreen);
    expect(pinScreen.props.mode).toBe('unlock');
    expect(pinScreen.props.recoveryRequiresSignIn).toBe(true);
    await act(async () => pinScreen.props.onRecover());
    expect(onSignOut).toHaveBeenCalledTimes(1);
    expect(clearStaffPin).not.toHaveBeenCalled();

    act(() => tree.unmount());
    tree = null;
    await mount(true);
    await act(async () => tree.root.findByType(StaffPinScreen).props.onRecover());
    expect(clearStaffPin).toHaveBeenCalledWith(17);
    expect(tree.root.findByType(StaffPinScreen).props.mode).toBe('setup');
  });
});
