import {DeviceEventEmitter} from 'react-native';
import {AppAlert, APP_DIALOG_EVENT} from '../src/services/AppDialogService';

describe('AppAlert', () => {
  afterEach(() => jest.restoreAllMocks());

  test('publishes a branded dialog request while preserving button callbacks', () => {
    const emit = jest.spyOn(DeviceEventEmitter, 'emit');
    const onPress = jest.fn();
    const buttons = [{text: 'Cancel', style: 'cancel'}, {text: 'Delete', style: 'destructive', onPress}];

    AppAlert.alert('Delete product?', 'This cannot be undone.', buttons, {cancelable: true});

    expect(emit).toHaveBeenCalledWith(APP_DIALOG_EVENT, expect.objectContaining({
      title: 'Delete product?',
      message: 'This cannot be undone.',
      buttons,
      options: {cancelable: true},
    }));
  });

  test('provides a default acknowledgement button', () => {
    const emit = jest.spyOn(DeviceEventEmitter, 'emit');

    AppAlert.alert('Saved', 'Your changes are ready.');

    expect(emit).toHaveBeenCalledWith(APP_DIALOG_EVENT, expect.objectContaining({
      title: 'Saved',
      message: 'Your changes are ready.',
      buttons: [{text: 'OK'}],
    }));
  });
});
