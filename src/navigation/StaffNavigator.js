import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { COLORS } from '../theme';

import DashboardScreen from '../screens/staff/DashboardScreen';
import TransactionsStack from './TransactionsStack';
import OrderQueueScreen from '../screens/staff/OrderQueueScreen';
import StockScreen from '../screens/staff/StockScreen';
import ProfileScreen from '../screens/staff/ProfileScreen';

const Tab = createBottomTabNavigator();

const CustomTabBar = ({ state, descriptors, navigation }) => {
  return (
    <View style={styles.navContainer}>
      {state.routes.map((route, index) => {
        const isFocused = state.index === index;

        const iconMap = {
          Dashboard: 'dashboard',
          Transactions: 'receipt-long',
          Orders: 'shopping-cart',
          Stock: 'inventory',
          Profile: 'person',
        };

        const onPress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: route.key,
            canPreventDefault: true,
          });
          if (!isFocused && !event.defaultPrevented) {
            navigation.navigate(route.name);
          }
        };

        return (
          <TouchableOpacity
            key={route.key}
            style={styles.navItem}
            onPress={onPress}
            activeOpacity={0.7}>
            <View style={isFocused ? styles.activePill : styles.inactivePill}>
              <Icon
                name={iconMap[route.name]}
                size={22}
                color={isFocused ? '#00C897' : '#AAAAAA'}
              />
            </View>
            <Text style={isFocused ? styles.activeLabel : styles.inactiveLabel}>
              {route.name}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
};

const StaffNavigator = () => {
  return (
    <Tab.Navigator
      tabBar={props => <CustomTabBar {...props} />}
      screenOptions={{ headerShown: false,tabBarStyle: { display: 'none' } }}>
      <Tab.Screen name="Dashboard" component={DashboardScreen} />
      <Tab.Screen name="Transactions" component={TransactionsStack} />
      <Tab.Screen name="Orders" component={OrderQueueScreen} />
      <Tab.Screen name="Stock" component={StockScreen} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
};

const styles = StyleSheet.create({
  navContainer: {
    flexDirection: 'row',
    backgroundColor: '#DFF7E2',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingVertical: 10,
    paddingHorizontal: 8,
    paddingBottom: 20,
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    zIndex: 999,
    elevation: 10,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  navItemInner: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  activePill: {
    width: 44,
    height: 34,
    borderRadius: 17,
    backgroundColor: '#E8FBF5',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 3,
  },
  activeLabel: {
    fontSize: 11,
    fontWeight: 'bold',
    color: '#00C897',
    textAlign: 'center',
  },
  inactivePill: {
    width: 44,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 3,
  },
  inactiveLabel: {
    fontSize: 11,
    color: '#AAAAAA',
    textAlign: 'center',
  },
});

export default StaffNavigator;