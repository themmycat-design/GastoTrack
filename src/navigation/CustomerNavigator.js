import React from 'react';
import { View } from 'react-native';
import OrderScreen from '../screens/customer/OrderScreen';

const CustomerNavigator = () => {
  return (
    <View style={{ flex: 1 }}>
      <OrderScreen />
    </View>
  );
};

export default CustomerNavigator;
