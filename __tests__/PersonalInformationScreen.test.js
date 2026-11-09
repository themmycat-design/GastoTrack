import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {TextInput, TouchableOpacity} from 'react-native';
import {launchImageLibrary} from 'react-native-image-picker';
import PersonalInformationScreen from '../src/screens/staff/PersonalInformationScreen';
import {useAuth} from '../src/context/AuthContext';
import {AppAlert} from '../src/services/AppDialogService';

jest.mock('react-native-image-picker', () => ({launchImageLibrary: jest.fn()}));
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/context/AuthContext', () => ({useAuth: jest.fn()}));
jest.mock('../src/services/AppDialogService', () => ({AppAlert: {alert: jest.fn()}}));
jest.mock('../src/utils/profilePhoto', () => ({
  getProfilePhotoSource: () => null,
  profilePhotoUpload: asset => ({uri: asset.uri, type: 'image/jpeg', name: 'profile-photo.jpg'}),
}));

describe('staff personal information editing', () => {
  let screen;
  let platformFormData;
  const updateProfile = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    platformFormData = global.FormData;
    global.FormData = class MockFormData {
      constructor() { this._parts = []; }
      append(name, value) { this._parts.push([name, value]); }
    };
    updateProfile.mockResolvedValue({success: true});
    useAuth.mockReturnValue({
      user: {id: 17, name: 'Original Name', email: 'staff@example.test', phone: '09123456789'},
      userRole: 'staff',
      business: {name: 'Sample Shop'},
      userToken: 'test-token',
      updateProfile,
    });
  });

  afterEach(() => {
    if (screen) act(() => screen.unmount());
    screen = null;
    global.FormData = platformFormData;
  });

  const press = async label => {
    const button = screen.root.findAllByType(TouchableOpacity)
      .find(item => item.props.accessibilityLabel === label);
    expect(button).toBeDefined();
    await act(async () => button.props.onPress());
  };

  const beginEditing = async () => {
    act(() => {
      screen = TestRenderer.create(<PersonalInformationScreen navigation={{goBack: jest.fn()}} />);
    });
    await press('Edit personal information');
    const [name, phone] = screen.root.findAllByType(TextInput);
    act(() => {
      name.props.onChangeText('Updated Staff');
      phone.props.onChangeText('09998887777');
    });
  };

  it('sends name and phone edits as JSON without a photo upload', async () => {
    await beginEditing();
    await press('Save personal information changes');

    expect(updateProfile).toHaveBeenCalledWith({name: 'Updated Staff', phone: '09998887777'}, false);
    expect(AppAlert.alert).toHaveBeenCalledWith('Profile updated');
  });

  it('sends a selected photo and edits in one multipart request', async () => {
    launchImageLibrary.mockResolvedValue({assets: [{uri: 'file:///avatar.jpg', type: 'image/jpeg', fileSize: 40000}]});
    await beginEditing();
    await press('Choose profile photo');
    await press('Save personal information changes');

    const [payload, hasPhoto] = updateProfile.mock.calls[0];
    expect(hasPhoto).toBe(true);
    expect(payload).toBeInstanceOf(FormData);
    expect(payload._parts).toEqual(expect.arrayContaining([
      ['name', 'Updated Staff'],
      ['phone', '09998887777'],
      ['profile_photo', {uri: 'file:///avatar.jpg', type: 'image/jpeg', name: 'profile-photo.jpg'}],
    ]));
  });
});
