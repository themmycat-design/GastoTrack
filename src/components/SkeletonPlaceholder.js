import React, {createContext, useContext, useEffect, useRef} from 'react';
import {Animated, StyleSheet, View} from 'react-native';
import useReduceMotion from '../hooks/useReduceMotion';

const SkeletonOpacityContext = createContext(null);

const SkeletonPlaceholder = ({children, style, accessibilityLabel = 'Loading content'}) => {
  const reduceMotion = useReduceMotion();
  const opacity = useRef(new Animated.Value(0.42)).current;

  useEffect(() => {
    if (reduceMotion) {
      opacity.setValue(0.58);
      return undefined;
    }
    const pulse = Animated.loop(Animated.sequence([
      Animated.timing(opacity, {toValue: 0.82, duration: 760, useNativeDriver: true}),
      Animated.timing(opacity, {toValue: 0.42, duration: 760, useNativeDriver: true}),
    ]));
    pulse.start();
    return () => pulse.stop();
  }, [opacity, reduceMotion]);

  return (
    <SkeletonOpacityContext.Provider value={opacity}>
      <View accessible accessibilityRole="progressbar" accessibilityLabel={accessibilityLabel} style={style}>
        {children}
      </View>
    </SkeletonOpacityContext.Provider>
  );
};

export const SkeletonBlock = ({width = '100%', height = 14, radius = 8, style}) => {
  const opacity = useContext(SkeletonOpacityContext);

  return (
    <Animated.View
      accessible={false}
      style={[
        styles.block,
        {width, height, borderRadius: radius, opacity: opacity || 0.58},
        style,
      ]}
    />
  );
};

const styles = StyleSheet.create({
  block: {backgroundColor: '#DDEAE5'},
});

export default SkeletonPlaceholder;
