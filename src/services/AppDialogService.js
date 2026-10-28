import {DeviceEventEmitter} from 'react-native';

export const APP_DIALOG_EVENT = 'gastotrack:app-dialog';

let nextDialogId = 0;

export const AppAlert = {
  alert(title, message = '', buttons = [], options = {}) {
    if (Array.isArray(message)) {
      options = buttons || {};
      buttons = message;
      message = '';
    }

    DeviceEventEmitter.emit(APP_DIALOG_EVENT, {
      id: ++nextDialogId,
      title: title == null ? '' : String(title),
      message: message == null ? '' : String(message),
      buttons: Array.isArray(buttons) && buttons.length ? buttons : [{text: 'OK'}],
      options: options && typeof options === 'object' ? options : {},
    });
  },
};
