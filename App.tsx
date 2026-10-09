import React, { useContext } from 'react';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { SafeAreaProvider } from 'react-native-safe-area-context';

// Providers
import { AuthProvider, AuthContext } from './src/context/AuthContext';
import { StockProvider } from './src/context/StockContext';
import { ProductProvider } from './src/context/ProductContext';
import { TransactionProvider } from './src/context/TransactionContext';
import { OrderProvider } from './src/context/OrderContext';

// Navigators & Screens
import StaffNavigator from './src/navigation/StaffNavigator';
import ReceiptScannerScreen from './src/screens/staff/ReceiptScannerScreen';
import LoginScreen from './src/screens/LoginScreens';
import OwnerBlockedScreen from './src/screens/auth/OwnerBlockedScreen';
import { COLORS } from './src/theme';
import AppErrorBoundary from './src/components/AppErrorBoundary';

const RootStack = createNativeStackNavigator();

// Ginawa natin itong hiwalay na component para maka-access sa AuthContext
const AppNav = () => {
  const { isLoading, userToken, userRole, logout } = useContext(AuthContext);

  console.log('[AppNav] isLoading:', isLoading, 'userToken:', userToken ? 'exists' : 'null');

  // Loading screen habang tsine-check kung may nakasave na token sa AsyncStorage
  if (isLoading) {
    console.log('[AppNav] Showing loading screen');
    return (
      <View style={styles.loadingScreen}>
        <ActivityIndicator size="large" color="#FFFFFF" />
      </View>
    );
  }

  // Kung walang token, LoginScreen lang ang pwede ma-access
  if (!userToken) {
    console.log('[AppNav] No token, showing LoginScreen');
    return <LoginScreen />;
  }

  if (userRole === 'owner') {
    return <OwnerBlockedScreen onLogout={logout} />;
  }

  // Kapag nakapag-login na, ilalabas na yung original RootStack mo
  console.log('[AppNav] User logged in, showing StaffNavigator');
  return (
    <RootStack.Navigator screenOptions={{ headerShown: false }}>
      <RootStack.Screen name="Main" component={StaffNavigator} />
      <RootStack.Screen name="ReceiptScanner" component={ReceiptScannerScreen} />
    </RootStack.Navigator>
  );
};

const App = () => (
  <SafeAreaProvider>
    <AuthProvider>
      <StockProvider>
        <ProductProvider>
          <TransactionProvider>
            <OrderProvider>
              <NavigationContainer>
                <AppErrorBoundary>
                  <AppNav />
                </AppErrorBoundary>
              </NavigationContainer>
            </OrderProvider>
          </TransactionProvider>
        </ProductProvider>
      </StockProvider>
    </AuthProvider>
  </SafeAreaProvider>
);

export default App;

const styles = StyleSheet.create({
  loadingScreen: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.accent,
  },
});
