import React from 'react';
import {StyleSheet, useWindowDimensions, View} from 'react-native';
import {COLORS} from '../../theme';

// Fixed positions keep the pattern steady while a screen scrolls.
const paws = [
  {top: '4%', left: '2%', size: 22, angle: '-20deg'},
  {top: '10%', right: '2%', size: 26, angle: '22deg'},
  {top: '17%', left: '30%', size: 20, angle: '12deg'},
  {top: '23%', right: '3%', size: 23, angle: '-15deg'},
  {top: '30%', left: '2%', size: 25, angle: '27deg'},
  {top: '37%', right: '28%', size: 20, angle: '-30deg'},
  {top: '43%', left: '45%', size: 24, angle: '14deg'},
  {top: '50%', right: '2%', size: 21, angle: '32deg'},
  {top: '56%', left: '3%', size: 22, angle: '-10deg'},
  {top: '62%', right: '35%', size: 25, angle: '18deg'},
  {top: '68%', left: '2%', size: 20, angle: '30deg'},
  {top: '74%', right: '3%', size: 24, angle: '-22deg'},
  {top: '80%', left: '36%', size: 21, angle: '15deg'},
  {top: '86%', right: '30%', size: 25, angle: '-16deg'},
  {top: '92%', left: '2%', size: 22, angle: '24deg'},
  {top: '96%', right: '2%', size: 20, angle: '-12deg'},
];

const StaffPawBackground = ({opacity = 0.18}) => {
  const {width, height} = useWindowDimensions();

  return <View pointerEvents="none" accessible={false} importantForAccessibility="no-hide-descendants" style={[styles.background, {width, height}]}>
    {paws.map((paw, index) => (
      <View key={index} style={[styles.paw, {
        top: paw.top,
        left: paw.left,
        right: paw.right,
        width: paw.size,
        height: paw.size,
        opacity,
        transform: [{rotate: paw.angle}],
      }]}>
        <View style={[styles.toe, styles.firstToe]} />
        <View style={[styles.toe, styles.secondToe]} />
        <View style={[styles.toe, styles.thirdToe]} />
        <View style={[styles.toe, styles.fourthToe]} />
        <View style={styles.pad} />
      </View>
    ))}
  </View>;
};

const styles = StyleSheet.create({
  background: {position: 'absolute', top: 0, left: 0, overflow: 'hidden'},
  paw: {position: 'absolute'},
  toe: {position: 'absolute', width: '19%', height: '23%', borderRadius: 10, backgroundColor: COLORS.accentDark},
  firstToe: {top: '22%', left: '3%', transform: [{rotate: '-28deg'}]},
  secondToe: {top: '3%', left: '25%', transform: [{rotate: '-10deg'}]},
  thirdToe: {top: '2%', right: '25%', transform: [{rotate: '10deg'}]},
  fourthToe: {top: '22%', right: '3%', transform: [{rotate: '28deg'}]},
  pad: {position: 'absolute', width: '54%', height: '45%', left: '23%', bottom: '5%', borderRadius: 12, backgroundColor: COLORS.accentDark},
});

export default StaffPawBackground;
