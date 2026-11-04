import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {TouchableOpacity} from 'react-native';
import ProfileScreen from '../src/screens/staff/ProfileScreen';
import AboutScreen from '../src/screens/staff/AboutScreen';
import {useAuth} from '../src/context/AuthContext';

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/context/AuthContext', () => ({useAuth: jest.fn()}));
jest.mock('../src/utils/profilePhoto', () => ({getProfilePhotoSource: () => null}));

describe('staff Profile navigation', () => {
  beforeEach(() => {
    useAuth.mockReturnValue({user: {name: 'Maria Santos'}, userRole: 'staff', business: {name: 'Sample Shop'}, logout: jest.fn()});
  });

  it('shows the requested Profile items in order and opens their routes', () => {
    const navigation = {navigate: jest.fn()};
    let screen;
    act(() => { screen = TestRenderer.create(<ProfileScreen navigation={navigation} />); });

    const labels = screen.root.findAllByType(TouchableOpacity)
      .map(node => node.props.accessibilityLabel).filter(Boolean);
    expect(labels).toEqual([
      'Personal information', 'Permissions', 'Your reports', 'Help', 'About',
    ]);

    for (const [label, route] of [['Help', 'Help'], ['Your reports', 'YourReports'], ['About', 'About']]) {
      const button = screen.root.findAllByType(TouchableOpacity)
        .find(node => node.props.accessibilityLabel === label);
      act(() => button.props.onPress());
      expect(navigation.navigate).toHaveBeenLastCalledWith(route);
    }
    act(() => screen.unmount());
  });

  it('opens the Terms and Privacy screens from About', () => {
    const navigation = {navigate: jest.fn(), goBack: jest.fn()};
    let screen;
    act(() => { screen = TestRenderer.create(<AboutScreen navigation={navigation} />); });

    for (const [label, route] of [['Terms and Conditions', 'Terms'], ['Privacy Policy', 'PrivacyPolicy']]) {
      const link = screen.root.findAllByType(TouchableOpacity)
        .find(node => node.props.accessibilityLabel === label);
      expect(link).toBeDefined();
      act(() => link.props.onPress());
      expect(navigation.navigate).toHaveBeenLastCalledWith(route);
    }
    act(() => screen.unmount());
  });
});
