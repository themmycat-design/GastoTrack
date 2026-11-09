import React, {useEffect, useRef, useState} from 'react';
import {
  Animated,
  DeviceEventEmitter,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import {APP_DIALOG_EVENT} from '../services/AppDialogService';
import {COLORS, SHADOWS} from '../theme';
import useReduceMotion from '../hooks/useReduceMotion';

const getMascot = (title, message) => {
  const text = `${title || ''} ${message || ''}`.toLowerCase();
  if (/notification access/.test(text)) {
    return {source: require('../../assets/mascot/gasto-permission.png'), label: 'Gasto holding a notification bell'};
  }
  if (/low stock|out of stock|not enough stock/.test(text)) {
    return {source: require('../../assets/mascot/gasto-low-stock.png'), label: 'Gasto checking an empty stock box'};
  }
  if (/saved on this device|offline|no internet connection|no connection|cannot reach|could not reach|network error|server.*unavailable/.test(text)) {
    return {source: require('../../assets/mascot/gasto-offline.png'), label: 'Gasto resting while the connection is unavailable'};
  }
  if (/order placed|order completed|payment confirmed/.test(text) && !/could not|failed|not confirmed/.test(text)) {
    return {source: require('../../assets/mascot/gasto-order-success.png'), label: 'Gasto celebrating a completed order'};
  }
  if (/could not|failed|error|invalid|unavailable|not submitted|not saved/.test(text)) {
    return {source: require('../../assets/mascot/gasto-error.png'), label: 'Gasto showing an error'};
  }
  if (/saved|updated|added|confirmed|complete|success/.test(text)) {
    return {source: require('../../assets/mascot/gasto-order-success.png'), label: 'Gasto celebrating'};
  }
  if (/delete|remove|cancel|sign out|clear|retry|pending|missing|choose/.test(text)) {
    return {source: require('../../assets/mascot/gasto-thinking.png'), label: 'Gasto thinking'};
  }
  return {source: require('../../assets/mascot/gasto-waving.png'), label: 'Gasto waving'};
};

const AppDialogHost = () => {
  const reduceMotion = useReduceMotion();
  const [dialog, setDialog] = useState(null);
  const [modalVisible, setModalVisible] = useState(false);
  const animation = useRef(new Animated.Value(0)).current;
  const currentDialogId = useRef(null);
  const closing = useRef(false);

  useEffect(() => {
    const subscription = DeviceEventEmitter.addListener(APP_DIALOG_EVENT, nextDialog => {
      currentDialogId.current = nextDialog.id;
      closing.current = false;
      animation.stopAnimation();
      animation.setValue(0);
      setDialog(nextDialog);
      setModalVisible(true);

      if (reduceMotion) {
        animation.setValue(1);
      } else {
        requestAnimationFrame(() => {
          if (currentDialogId.current !== nextDialog.id) return;
          Animated.timing(animation, {
            toValue: 1,
            duration: 210,
            useNativeDriver: true,
          }).start();
        });
      }
    });

    return () => subscription.remove();
  }, [animation, reduceMotion]);

  const closeWithButton = button => {
    if (!dialog || closing.current) return;
    closing.current = true;
    const closingId = dialog.id;
    const onPress = button?.onPress;

    Animated.timing(animation, {
      toValue: 0,
      duration: reduceMotion ? 0 : 140,
      useNativeDriver: true,
    }).start(({finished}) => {
      if (!finished || currentDialogId.current !== closingId) return;

      setDialog(null);
      setModalVisible(false);
      currentDialogId.current = null;
      closing.current = false;
    });
    // The confirmed action must not depend on an animation completing.
    onPress?.();
  };

  const handleRequestClose = () => {
    const cancelButton = dialog?.buttons?.find(button => button.style === 'cancel');
    if (dialog?.options?.cancelable || cancelButton) closeWithButton(cancelButton);
  };

  if (!dialog && !modalVisible) return null;

  const mascot = getMascot(dialog?.title, dialog?.message);
  const buttons = dialog?.buttons || [];
  const horizontalButtons = buttons.length <= 2;
  const backdropOpacity = animation.interpolate({inputRange: [0, 1], outputRange: [0, 0.48]});
  const cardOpacity = animation;
  const cardTransform = [
    {scale: animation.interpolate({inputRange: [0, 1], outputRange: [0.94, 1]})},
    {translateY: animation.interpolate({inputRange: [0, 1], outputRange: [18, 0]})},
  ];

  return (
    <Modal
      visible={modalVisible}
      transparent
      animationType="none"
      statusBarTranslucent
      onRequestClose={handleRequestClose}>
      <View style={styles.overlay}>
        <Animated.View pointerEvents="none" style={[StyleSheet.absoluteFill, styles.backdrop, {opacity: backdropOpacity}]} />
        <Animated.View
          accessibilityViewIsModal
          accessibilityRole="alert"
          style={[styles.card, {opacity: cardOpacity, transform: cardTransform}]}>
          <Image source={mascot.source} style={styles.mascotImage} resizeMode="contain" accessibilityLabel={mascot.label} />

          {dialog?.title ? <Text accessibilityRole="header" style={styles.title}>{dialog.title}</Text> : null}
          {dialog?.message ? (
            <ScrollView style={styles.messageScroll} contentContainerStyle={styles.messageContent}>
              <Text style={styles.message}>{dialog.message}</Text>
            </ScrollView>
          ) : null}

          <View style={[styles.actions, horizontalButtons && styles.actionsHorizontal]}>
            {buttons.map((button, index) => {
              const isCancel = button.style === 'cancel';
              const isDestructive = button.style === 'destructive';
              const isPrimary = !isCancel && (isDestructive || index === buttons.length - 1 || buttons.length === 1);

              return (
                <Pressable
                  key={`${button.text || 'action'}-${index}`}
                  accessibilityRole="button"
                  accessibilityLabel={button.text || 'Continue'}
                  onPress={() => closeWithButton(button)}
                  style={({pressed}) => [
                    styles.actionButton,
                    horizontalButtons && styles.actionButtonHorizontal,
                    isPrimary && (isDestructive ? styles.destructiveButton : styles.primaryButton),
                    isCancel && styles.cancelButton,
                    pressed && styles.actionPressed,
                  ]}>
                  <Text style={[
                    styles.actionLabel,
                    isPrimary && styles.primaryLabel,
                    isDestructive && styles.destructiveLabel,
                    isCancel && styles.cancelLabel,
                  ]}>
                    {button.text || 'Continue'}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </Animated.View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 22, paddingVertical: 28},
  backdrop: {backgroundColor: '#061F1B'},
  card: {
    width: '100%',
    maxWidth: 420,
    maxHeight: '88%',
    alignItems: 'center',
    paddingHorizontal: 22,
    paddingTop: 23,
    paddingBottom: 18,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#E4F0EC',
    backgroundColor: COLORS.surface,
    ...SHADOWS.card,
    elevation: 18,
  },
  mascotImage: {width: 88, height: 88, marginBottom: 12},
  title: {color: COLORS.textDark, fontSize: 18, lineHeight: 24, fontWeight: '800', textAlign: 'center'},
  messageScroll: {flexGrow: 0, width: '100%', maxHeight: 240, marginTop: 7},
  messageContent: {paddingVertical: 2},
  message: {color: COLORS.textGray, fontSize: 14, lineHeight: 21, textAlign: 'center'},
  actions: {width: '100%', gap: 9, marginTop: 21},
  actionsHorizontal: {flexDirection: 'row'},
  actionButton: {minHeight: 48, paddingHorizontal: 16, justifyContent: 'center', alignItems: 'center', borderRadius: 14, backgroundColor: '#EAF6F2'},
  actionButtonHorizontal: {flex: 1},
  primaryButton: {backgroundColor: COLORS.actionPrimary},
  destructiveButton: {backgroundColor: COLORS.danger},
  cancelButton: {borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface},
  actionPressed: {opacity: 0.78, transform: [{scale: 0.985}]},
  actionLabel: {color: COLORS.accentDark, fontSize: 14, fontWeight: '800', textAlign: 'center'},
  primaryLabel: {color: COLORS.actionText},
  destructiveLabel: {color: COLORS.textWhite},
  cancelLabel: {color: COLORS.textGray},
});

export default AppDialogHost;
