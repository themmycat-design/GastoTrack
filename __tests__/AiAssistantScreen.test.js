import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {Text, TouchableOpacity} from 'react-native';
import AiAssistantScreen from '../src/screens/staff/AiAssistantScreen';
import api from '../src/services/api';
import {AppAlert} from '../src/services/AppDialogService';

jest.mock('react-native-vector-icons/MaterialIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/services/api', () => ({__esModule: true, default: {post: jest.fn()}}));

it('removes the welcome panel when staff sends a question and restores it after clearing the chat', async () => {
  let screen;
  const textValues = () => screen.root.findAllByType(Text).map(node => node.props.children);
  const navigation = {canGoBack: () => false, navigate: jest.fn()};
  const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});
  let finishReply;
  api.post.mockReturnValue(new Promise(resolve => { finishReply = resolve; }));

  await act(async () => { screen = TestRenderer.create(<AiAssistantScreen navigation={navigation} />); });
  expect(textValues()).toContain("Hi, I'm Gasto!");

  const suggestion = screen.root.findAllByType(TouchableOpacity)
    .find(node => node.props.accessibilityLabel === 'How are sales today?');
  await act(async () => { suggestion.props.onPress(); });
  expect(textValues()).not.toContain("Hi, I'm Gasto!");
  expect(textValues()).not.toContain('TRY ASKING');
  expect(textValues()).toContain('How are sales today?');

  await act(async () => finishReply({data: {reply: 'Sales are recorded after confirmation.'}}));

  const header = screen.root.findByProps({title: 'AI Assistant'});
  await act(async () => header.props.onActionPress());
  const clear = alertSpy.mock.calls.at(-1)[2].find(button => button.text === 'Clear chat');
  await act(async () => clear.onPress());
  expect(textValues()).toContain("Hi, I'm Gasto!");
  expect(textValues()).toContain('TRY ASKING');

  act(() => screen.unmount());
  alertSpy.mockRestore();
});
