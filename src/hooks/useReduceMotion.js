import {useEffect, useState} from 'react';
import {AccessibilityInfo} from 'react-native';

// Keep motion preferences in sync when the device setting changes while the app is open.
export default function useReduceMotion() {
  const [reduced, setReduced] = useState(false);

  useEffect(() => {
    let active = true;
    AccessibilityInfo.isReduceMotionEnabled?.()
      ?.then(value => { if (active) setReduced(Boolean(value)); })
      ?.catch(() => {});
    const subscription = AccessibilityInfo.addEventListener?.('reduceMotionChanged', value => {
      if (active) setReduced(Boolean(value));
    });
    return () => {
      active = false;
      subscription?.remove?.();
    };
  }, []);

  return reduced;
}
