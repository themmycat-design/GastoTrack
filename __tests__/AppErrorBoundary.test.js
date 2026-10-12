import React from 'react';
import ReactTestRenderer from 'react-test-renderer';
import {Text} from 'react-native';
import AppErrorBoundary from '../src/components/AppErrorBoundary';

const BrokenStartupChild = () => {
  throw new Error('internal startup detail');
};

describe('AppErrorBoundary', () => {
  it('shows a recovery screen when a descendant fails during startup render', async () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    let screen;

    try {
      await ReactTestRenderer.act(async () => {
        screen = ReactTestRenderer.create(
          <AppErrorBoundary>
            <BrokenStartupChild />
          </AppErrorBoundary>,
        );
      });

      const visibleText = screen.root.findAllByType(Text).map(node => node.props.children);
      expect(visibleText).toContain('This screen ran into a problem');
      expect(visibleText).toContain('Reload screen');
      expect(visibleText).not.toContain('internal startup detail');
    } finally {
      consoleError.mockRestore();
    }
  });
});
