import React, {useContext, useEffect, useRef} from 'react';
import {Animated, Easing, Image, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useIsFocused, useNavigation, useRoute} from '@react-navigation/native';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {COLORS} from '../../theme';
import useReduceMotion from '../../hooks/useReduceMotion';
import {AuthContext} from '../../context/AuthContext';

const AssistantAction = () => {
  const navigation = useNavigation();
  const route = useRoute();
  const isFocused = useIsFocused();
  const {user} = useContext(AuthContext);
  const wave = useRef(new Animated.Value(0)).current;
  const blink = useRef(new Animated.Value(0)).current;
  const greeting = useRef(new Animated.Value(0)).current;
  const reduceMotion = useReduceMotion();

  useEffect(() => {
    if (route.name === 'Assistant' || !isFocused || reduceMotion) {
      wave.stopAnimation();
      blink.stopAnimation();
      wave.setValue(0);
      blink.setValue(0);
      return undefined;
    }

    const pawAnimation = Animated.loop(Animated.sequence([
      Animated.timing(wave, {toValue: 1, duration: 180, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.timing(wave, {toValue: 0, duration: 180, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.timing(wave, {toValue: 1, duration: 180, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.timing(wave, {toValue: 0, duration: 180, easing: Easing.inOut(Easing.ease), useNativeDriver: true}),
      Animated.delay(1280),
    ]));
    const blinkAnimation = Animated.loop(Animated.sequence([
      Animated.delay(4700),
      Animated.timing(blink, {toValue: 1, duration: 80, useNativeDriver: true}),
      Animated.delay(140),
      Animated.timing(blink, {toValue: 0, duration: 80, useNativeDriver: true}),
    ]));
    pawAnimation.start();
    blinkAnimation.start();
    return () => {
      pawAnimation.stop();
      blinkAnimation.stop();
    };
  }, [route.name, isFocused, wave, blink, reduceMotion]);

  useEffect(() => {
    if (route.name === 'Assistant' || !isFocused || reduceMotion) {
      greeting.stopAnimation();
      greeting.setValue(0);
      return undefined;
    }

    // One quiet greeting every fifteen seconds; the bubble never takes touches from the screen.
    const greetingAnimation = Animated.loop(Animated.sequence([
      Animated.delay(13000),
      Animated.timing(greeting, {toValue: 1, duration: 220, useNativeDriver: true}),
      Animated.delay(1500),
      Animated.timing(greeting, {toValue: 0, duration: 280, useNativeDriver: true}),
    ]));
    greetingAnimation.start();
    return () => greetingAnimation.stop();
  }, [route.name, isFocused, greeting, reduceMotion]);

  const rotation = wave.interpolate({inputRange: [0, 1], outputRange: ['-5deg', '16deg']});
  const greetingLift = greeting.interpolate({inputRange: [0, 1], outputRange: [6, 0]});
  const staffName = user?.name?.trim() || 'there';

  if (route.name === 'Assistant') return null;

  return (
    <TouchableOpacity
      style={styles.assistantAction}
      onPress={() => navigation.navigate('Assistant')}
      accessibilityRole="button"
      accessibilityLabel={`Open Gasto AI Assistant. Hi, ${staffName}!`}
      hitSlop={4}>
      <View style={styles.mascotFrame} pointerEvents="none">
        <Image source={require('../../../assets/mascot/gasto-header-body.png')} style={styles.assistantMascot} resizeMode="contain" />
        <Animated.Image source={require('../../../assets/mascot/gasto-header-blink.png')} style={[styles.assistantMascot, styles.blinkFrame, {opacity: blink}]} resizeMode="contain" />
        <Animated.View style={[styles.pawPivot, {transform: [{rotate: rotation}]}]}>
          <Image source={require('../../../assets/mascot/gasto-header-paw.png')} style={styles.pawImage} resizeMode="contain" />
        </Animated.View>
      </View>
      <Animated.View pointerEvents="none" style={[styles.greeting, {opacity: greeting, transform: [{translateY: greetingLift}]}]}>
        <View style={styles.thoughtDotSmall} />
        <View style={styles.thoughtDotLarge} />
        <Text style={styles.greetingText} numberOfLines={2}>{`Hi, ${staffName}!`}</Text>
      </Animated.View>
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
    zIndex: 10,
    elevation: 4,
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
  assistantMascot: {width: 38, height: 38},
  greeting: {
    position: 'absolute',
    top: 54,
    right: 0,
    width: 172,
    minHeight: 52,
    paddingHorizontal: 13,
    paddingVertical: 10,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.surface,
    elevation: 5,
  },
  thoughtDotSmall: {position: 'absolute', top: -18, right: 13, width: 6, height: 6, borderRadius: 3, backgroundColor: COLORS.surface},
  thoughtDotLarge: {position: 'absolute', top: -10, right: 21, width: 10, height: 10, borderRadius: 5, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  greetingText: {fontSize: 13, lineHeight: 18, fontWeight: '700', color: COLORS.textDark, textAlign: 'center'},
  mascotFrame: {width: 38, height: 38},
  blinkFrame: {position: 'absolute', top: 0, left: 0},
  pawPivot: {position: 'absolute', left: 12, top: 23, width: 1, height: 1},
  pawImage: {position: 'absolute', left: -12, top: -23, width: 38, height: 38},
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
