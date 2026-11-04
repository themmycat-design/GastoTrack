import React from 'react';
import {Image, StyleSheet} from 'react-native';

const mascots = {
  welcome: {source: require('../../../assets/mascot/gasto-waving.png'), label: 'Gasto waving'},
  thinking: {source: require('../../../assets/mascot/gasto-thinking.png'), label: 'Gasto thinking'},
  success: {source: require('../../../assets/mascot/gasto-order-success.png'), label: 'Gasto celebrating'},
  stock: {source: require('../../../assets/mascot/gasto-low-stock.png'), label: 'Gasto checking stock'},
  receipt: {source: require('../../../assets/mascot/gasto-receipt.png'), label: 'Gasto checking a receipt'},
};

const GastoModalMascot = ({variant = 'welcome'}) => {
  const mascot = mascots[variant] || mascots.welcome;
  return <Image source={mascot.source} style={styles.image} resizeMode="contain" accessibilityLabel={mascot.label} />;
};

const styles = StyleSheet.create({image: {width: 42, height: 42, marginRight: 8}});

export default GastoModalMascot;
