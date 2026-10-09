import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import OrderQueueScreen from '../screens/staff/OrderQueueScreen';
import OrderHistoryScreen from '../screens/staff/OrderHistoryScreen';

const Stack = createNativeStackNavigator();

const OrdersStack = () => (
  <Stack.Navigator screenOptions={{headerShown: false}}>
    <Stack.Screen name="OrderQueue" component={OrderQueueScreen} />
    <Stack.Screen name="OrderHistory" component={OrderHistoryScreen} />
  </Stack.Navigator>
);

export default OrdersStack;
