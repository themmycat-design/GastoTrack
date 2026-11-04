import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {Text, TouchableOpacity} from 'react-native';
import YourReportsScreen from '../src/screens/staff/YourReportsScreen';
import api from '../src/services/api';

jest.mock('@react-navigation/native', () => ({useFocusEffect: callback => require('react').useEffect(callback, [callback])}));
jest.mock('../src/context/AuthContext', () => ({useAuth: () => ({userToken: 'test-token'})}));
jest.mock('../src/services/api', () => ({__esModule: true, default: {get: jest.fn()}, API_URL: 'https://example.test/api'}));
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/components/SkeletonPlaceholder', () => ({__esModule: true, default: () => null, SkeletonBlock: () => null}));

it('shows submitted reports and opens the separate submission form', async () => {
  api.get.mockResolvedValue({data: {bug_reports: [{id: 4, title: 'Checkout freezes', area: 'orders', priority: 'medium', status: 'open', description: 'The button stops responding.', public_update: 'We are investigating.'}], meta: {current_page: 1, last_page: 1}}});
  const navigation = {navigate: jest.fn(), goBack: jest.fn()};
  let screen;
  await act(async () => { screen = TestRenderer.create(<YourReportsScreen navigation={navigation} />); });

  expect(api.get).toHaveBeenCalledWith('/bug-reports', {params: {page: 1, per_page: 20}});
  const text = screen.root.findAllByType(Text).map(node => node.props.children).filter(value => typeof value === 'string');
  expect(text).toContain('Checkout freezes');
  expect(text).toContain('We are investigating.');
  const reportButton = screen.root.findAllByType(TouchableOpacity).find(node => node.props.accessibilityLabel === 'Report a problem');
  act(() => reportButton.props.onPress());
  expect(navigation.navigate).toHaveBeenCalledWith('BugReports');
  act(() => screen.unmount());
});
