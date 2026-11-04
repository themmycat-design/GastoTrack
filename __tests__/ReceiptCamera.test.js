import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {Text, TouchableOpacity} from 'react-native';
import ReceiptCamera from '../src/components/staff/ReceiptCamera';
import {usePhotoOutput} from 'react-native-vision-camera';

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('react-native-safe-area-context', () => ({useSafeAreaInsets: () => ({top: 0, bottom: 0})}));
jest.mock('react-native-vision-camera', () => {
  const ReactForMock = require('react');
  return {
    Camera: ReactForMock.forwardRef((props, ref) => {
      ReactForMock.useImperativeHandle(ref, () => ({}));
      return ReactForMock.createElement('MockCamera', props);
    }),
    useCameraDevice: jest.fn(() => ({hasTorch: true})),
    usePhotoOutput: jest.fn(),
  };
});

it('shows a receipt guide and passes the captured file to OCR without showing a photo preview', async () => {
  const onCapture = jest.fn();
  const photoOutput = {capturePhotoToFile: jest.fn().mockResolvedValue({filePath: '/cache/receipt.jpg'})};
  usePhotoOutput.mockReturnValue(photoOutput);
  let screen;
  await act(async () => {screen = TestRenderer.create(<ReceiptCamera onClose={jest.fn()} onCapture={onCapture} />);});

  const textValues = screen.root.findAllByType(Text).map(node => node.props.children);
  expect(textValues).toContain('Fit the whole receipt inside the frame');
  expect(screen.root.findByType('MockCamera').props.implementationMode).toBe('compatible');
  await act(async () => screen.root.findByType('MockCamera').props.onStarted());

  const shutter = screen.root.findAllByType(TouchableOpacity)
    .find(node => node.props.accessibilityLabel === 'Capture receipt');
  await act(async () => shutter.props.onPress());
  expect(photoOutput.capturePhotoToFile).toHaveBeenCalledWith({flashMode: 'off'}, {});
  expect(onCapture).toHaveBeenCalledWith('file:///cache/receipt.jpg');
  act(() => screen.unmount());
});
