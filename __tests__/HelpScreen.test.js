import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {Text, TouchableOpacity} from 'react-native';
import HelpScreen from '../src/screens/staff/HelpScreen';

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);

describe('staff Help FAQ', () => {
  it('shows the questions and expands or collapses an answer when tapped', () => {
    let screen;
    act(() => {
      screen = TestRenderer.create(<HelpScreen navigation={{goBack: jest.fn()}} />);
    });

    const question = 'How do I place and complete an order?';
    const answer = 'Open Orders, tap a product, add it to the cart, then tap View your cart.';
    const textValues = () => screen.root.findAllByType(Text).map(node => String(node.props.children));
    const button = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === question);

    expect(button).toBeDefined();
    expect(screen.root.findAllByType(TouchableOpacity)).toHaveLength(13);
    expect(textValues()).toContain(question);
    expect(textValues().some(text => text.includes(answer))).toBe(false);

    act(() => button.props.onPress());
    expect(textValues().some(text => text.includes(answer))).toBe(true);
    expect(button.props.accessibilityState.expanded).toBe(true);

    act(() => button.props.onPress());
    expect(textValues().some(text => text.includes(answer))).toBe(false);

    act(() => screen.unmount());
  });
});
