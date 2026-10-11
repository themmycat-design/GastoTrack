import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, {setApiAuthToken} from '../src/services/api';
import {AuthContext, AuthProvider} from '../src/context/AuthContext';
import {clearAuthToken, getAuthToken, saveAuthToken} from '../src/services/authTokenStorage';

jest.mock('../src/services/api', () => ({
  __esModule: true,
  default: {
    get: jest.fn(),
    post: jest.fn(),
  },
  setUnauthorizedHandler: jest.fn(() => jest.fn()),
  setApiAuthToken: jest.fn(),
}));

jest.mock('../src/services/authTokenStorage', () => ({
  clearAuthToken: jest.fn(() => Promise.resolve()),
  getAuthToken: jest.fn(() => Promise.resolve(null)),
  saveAuthToken: jest.fn(() => Promise.resolve()),
}));

const flushEffects = () => new Promise(resolve => setTimeout(resolve, 0));

describe('AuthProvider session storage', () => {
  let contextValue;
  let tree;

  beforeEach(() => {
    jest.clearAllMocks();
    contextValue = null;
    getAuthToken.mockResolvedValue(null);
    saveAuthToken.mockResolvedValue(undefined);
    api.get.mockResolvedValue({data: {user: {}, business: null}});
    api.post.mockResolvedValue({
      data: {
        token: 'staff-session-token',
        user: {id: 17, role: 'staff'},
        business: {id: 4, name: 'Harbor Coffee'},
      },
    });
    AsyncStorage.getItem.mockResolvedValue(null);
    AsyncStorage.setMany.mockResolvedValue(undefined);
    AsyncStorage.removeMany.mockResolvedValue(undefined);
  });

  afterEach(() => {
    if (tree) act(() => tree.unmount());
    tree = null;
  });

  const mountProvider = async () => {
    await act(async () => {
      tree = TestRenderer.create(
        <AuthProvider>
          <AuthContext.Consumer>
            {value => {
              contextValue = value;
              return null;
            }}
          </AuthContext.Consumer>
        </AuthProvider>,
      );
      await flushEffects();
    });
  };

  it('stores staff profile fields through the AsyncStorage v3 setMany API after login', async () => {
    await mountProvider();

    let result;
    await act(async () => {
      result = await contextValue.login('staff1@example.test', 'private-password');
    });

    expect(result.success).toBe(true);
    expect(saveAuthToken).toHaveBeenCalledWith('staff-session-token');
    expect(AsyncStorage.setMany).toHaveBeenCalledWith({
      userRole: 'staff',
      user: JSON.stringify({id: 17, role: 'staff'}),
      business: JSON.stringify({id: 4, name: 'Harbor Coffee'}),
    });
    expect(contextValue.userToken).toBe('staff-session-token');
    expect(setApiAuthToken).toHaveBeenCalledWith('staff-session-token');
    expect(AsyncStorage.multiSet).toBeUndefined();
  });

  it('clears local session through AsyncStorage v3 removeMany even if the API is offline', async () => {
    await mountProvider();
    await act(async () => {
      await contextValue.login('staff1@example.test', 'private-password');
    });
    api.post.mockRejectedValueOnce(new Error('offline'));

    await act(async () => {
      await contextValue.logout();
    });

    expect(clearAuthToken).toHaveBeenCalled();
    expect(AsyncStorage.removeMany).toHaveBeenCalledWith(['userToken', 'userRole', 'user', 'business']);
    expect(contextValue.userToken).toBeNull();
    expect(setApiAuthToken).toHaveBeenCalledWith(null);
    expect(contextValue.user).toBeNull();
    expect(AsyncStorage.multiRemove).toBeUndefined();
  });

  it('opens a saved staff session while the server profile check is pending', async () => {
    getAuthToken.mockResolvedValue('saved-staff-token');
    AsyncStorage.getItem.mockImplementation(key => Promise.resolve({
      userRole: 'staff',
      user: JSON.stringify({id: 17, role: 'staff'}),
      business: JSON.stringify({id: 4, name: 'Harbor Coffee'}),
    }[key] ?? null));
    api.get.mockImplementation(() => new Promise(() => {}));

    await mountProvider();

    expect(contextValue.isLoading).toBe(false);
    expect(contextValue.userToken).toBe('saved-staff-token');
    expect(setApiAuthToken).toHaveBeenCalledWith('saved-staff-token');
  });

  it('opens the staff workspace without waiting for secure token persistence', async () => {
    let finishSave;
    saveAuthToken.mockImplementation(() => new Promise(resolve => { finishSave = resolve; }));
    await mountProvider();

    let result;
    await act(async () => {
      result = await contextValue.login('staff1@example.test', 'private-password');
    });

    expect(result.success).toBe(true);
    expect(contextValue.userToken).toBe('staff-session-token');
    await act(async () => { finishSave(); });
  });
});
