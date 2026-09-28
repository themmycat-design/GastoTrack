import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { StockProvider } from './src/context/StockContext';
import { ProductProvider } from './src/context/ProductContext';
import { TransactionProvider } from './src/context/TransactionContext';
import { OrderProvider } from './src/context/OrderContext';
import StaffNavigator from './src/navigation/StaffNavigator';
import ReceiptScannerScreen from './src/screens/staff/ReceiptScannerScreen';

const RootStack = createNativeStackNavigator();

const App = () => (
  <StockProvider>
    <ProductProvider>
      <TransactionProvider>
        <OrderProvider>
          <NavigationContainer>
            <RootStack.Navigator screenOptions={{ headerShown: false }}>
              <RootStack.Screen name="Main" component={StaffNavigator} />
              <RootStack.Screen name="ReceiptScanner" component={ReceiptScannerScreen} />
            </RootStack.Navigator>
          </NavigationContainer>
        </OrderProvider>
      </TransactionProvider>
    </ProductProvider>
  </StockProvider>
);

export default App;
