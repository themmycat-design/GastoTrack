import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import TransactionsScreen from '../screens/staff/TransactionsScreen';

const Stack = createNativeStackNavigator();

const TransactionsStack = () => {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="TransactionsMain" component={TransactionsScreen} />
    </Stack.Navigator>
  );
};

export default TransactionsStack;
