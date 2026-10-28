import React, {useEffect, useRef} from 'react';
import {Animated, Easing, Image, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {COLORS} from '../../theme';
import useReduceMotion from '../../hooks/useReduceMotion';

const AssistantAction = () => {
  const navigation = useNavigation();
  const route = useRoute();
  const wave = useRef(new Animated.Value(0)).current;
  const reduceMotion = useReduceMotion();

  useEffect(() => {
    if (route.name === 'Assistant' || reduceMotion) {
      wave.stopAnimation();
      wave.setValue(0);
      return undefined;
    }

    const animation = Animated.loop(Animated.sequence([
      Animated.timing(wave, {toValue: 1, duration: 190, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.timing(wave, {toValue: 0, duration: 190, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.delay(900),
    ]));
    animation.start();
    return () => animation.stop();
  }, [route.name, wave, reduceMotion]);

  const rotation = wave.interpolate({inputRange: [0, 1], outputRange: ['-18deg', '18deg']});

  if (route.name === 'Assistant') return null;

  return (
    <TouchableOpacity
      style={styles.assistantAction}
      onPress={() => navigation.navigate('Assistant')}
      accessibilityRole="button"
      accessibilityLabel="Open Gasto AI Assistant"
      hitSlop={4}>
      <Animated.Text style={[styles.assistantHand, {transform: [{rotate: rotation}]}]}>👋</Animated.Text>
    </TouchableOpacity>
  );
};

const StaffScreenHeader = ({
  title,
  subtitle,
  icon,
  leftIcon,
  leftLabel,
  onLeftPress,
  actionIcon,
  actionLabel,
  onActionPress,
  avatarSource,
  centered = false,
}) => (
  <SafeAreaView style={styles.safeArea} edges={['top']}>
    <View style={[styles.header, centered && styles.centeredHeader]}>
      {leftIcon ? (
        <TouchableOpacity
          style={[styles.action, styles.leftAction, centered && styles.centeredLeftAction]}
          onPress={onLeftPress}
          accessibilityRole="button"
          accessibilityLabel={leftLabel || 'Go back'}
          hitSlop={4}>
          <Icon name={leftIcon} size={23} color={COLORS.textDark} />
        </TouchableOpacity>
      ) : null}
      <View style={[styles.identity, centered && styles.centeredIdentity]}>
        {!centered && icon ? (
          <View style={styles.iconContainer}>
            {avatarSource
              ? <Image source={avatarSource} style={styles.profileAvatarImage} />
              : <Icon name={icon} size={27} color={COLORS.textWhite} />}
          </View>
        ) : null}
        <View style={[styles.copy, centered && styles.centeredCopy]}>
          {subtitle ? <Text style={styles.subtitle} numberOfLines={1}>{subtitle}</Text> : null}
          <Text accessibilityRole="header" style={[styles.title, centered && styles.centeredTitle]} numberOfLines={2}>{title}</Text>
        </View>
      </View>

      <View style={[styles.rightActions, centered && styles.centeredRightActions]}>
        <AssistantAction />
        {actionIcon ? (
          <TouchableOpacity
            style={[styles.action, !onActionPress && styles.actionDisabled]}
            onPress={onActionPress}
            disabled={!onActionPress}
            accessibilityRole="button"
            accessibilityLabel={actionLabel || title}
            accessibilityState={{disabled: !onActionPress}}
            hitSlop={4}>
            <Icon name={actionIcon} size={23} color={COLORS.textDark} />
          </TouchableOpacity>
        ) : null}
      </View>
    </View>
  </SafeAreaView>
);

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: COLORS.surface,
  },
  header: {
    minHeight: 68,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 6,
    paddingBottom: 10,
    backgroundColor: COLORS.surface,
  },
  identity: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  centeredHeader: {
    justifyContent: 'center',
  },
  centeredIdentity: {
    flex: 1,
    minWidth: 0,
    justifyContent: 'center',
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.accentDark,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  profileAvatarImage: {width: 44, height: 44, borderRadius: 22},
  copy: {
    flex: 1,
  },
  centeredCopy: {
    flex: 1,
    alignItems: 'center',
    paddingHorizontal: 52,
  },
  subtitle: {
    fontSize: 12,
    color: COLORS.textGray,
    marginBottom: 2,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.textDark,
  },
  centeredTitle: {
    fontSize: 20,
    textAlign: 'center',
  },
  action: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: COLORS.miniCardBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 8,
  },
  rightActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  centeredRightActions: {
    zIndex: 1,
    position: 'absolute',
    right: 16,
  },
  assistantAction: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  assistantHand: {
    fontSize: 23,
    transform: [{rotate: '-12deg'}],
  },
  actionDisabled: {
    opacity: 0.4,
  },
  leftAction: {
    marginLeft: 0,
    marginRight: 10,
  },
  centeredLeftAction: {
    position: 'absolute',
    left: 16,
    marginRight: 0,
    zIndex: 1,
  },
});

export default StaffScreenHeader;
