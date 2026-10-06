import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, RADIUS, SHADOWS } from '../theme';

import DashboardScreen from '../screens/staff/DashboardScreen';
import TransactionsStack from './TransactionsStack';
import OrderQueueScreen from '../screens/staff/OrderQueueScreen';
import StockScreen from '../screens/staff/StockScreen';
import ProfileStack from './ProfileStack';
import AiAssistantScreen from '../screens/staff/AiAssistantScreen';

const Tab = createBottomTabNavigator();
const renderTabBar = props => <CustomTabBar {...props} />;

const CustomTabBar = ({ state, descriptors, navigation }) => {
  const insets = useSafeAreaInsets();
  const tabMeta = {
    Dashboard: { icon: 'space-dashboard', label: 'Home' },
    Transactions: { icon: 'receipt-long', label: 'Transaction' },
    Orders: { icon: 'point-of-sale', label: 'Orders' },
    Stock: { icon: 'inventory-2', label: 'Inventory' },
    Assistant: { icon: 'auto-awesome', label: 'AI' },
    Profile: { icon: 'person-outline', label: 'Profile' },
  };

  return (
    <View style={[styles.navContainer, { paddingBottom: Math.max(insets.bottom, 8) }]}>
      {state.routes.filter(route => route.name !== 'Assistant').map(route => {
        const routeIndex = state.routes.findIndex(item => item.key === route.key);
        const isFocused = state.index === routeIndex;
        const meta = tabMeta[route.name];

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
            onLongPress={() => navigation.emit({
              type: 'tabLongPress',
              target: route.key,
            })}
            accessibilityRole="button"
            accessibilityState={isFocused ? { selected: true } : {}}
            accessibilityLabel={descriptors[route.key].options.tabBarAccessibilityLabel || meta.label}
            activeOpacity={0.7}>
            <View style={[styles.iconWrap, isFocused && styles.activePill]}>
              <Icon
                name={meta.icon}
                size={23}
                color={isFocused ? COLORS.accentDark : COLORS.navInactive}
              />
            </View>
            <Text style={isFocused ? styles.activeLabel : styles.inactiveLabel}>
              {meta.label}
            </Text>
          </TouchableOpacity>
        );
      })}
      {state.routes[state.index]?.name !== 'Assistant' ? (
        <TouchableOpacity
          style={styles.aiFloatingButton}
          onPress={() => navigation.navigate('Assistant')}
          accessibilityRole="button"
          accessibilityLabel="Open AI Assistant"
          activeOpacity={0.85}>
          <Icon name="auto-awesome" size={25} color={COLORS.textWhite} />
        </TouchableOpacity>
      ) : null}
    </View>
  );
};

const StaffNavigator = () => {
  return (
    <Tab.Navigator
      tabBar={renderTabBar}
      screenOptions={{
        headerShown: false,
        lazy: true,
        sceneStyle: { backgroundColor: COLORS.background },
      }}>
      <Tab.Screen name="Dashboard" component={DashboardScreen} />
      <Tab.Screen name="Transactions" component={TransactionsStack} />
      <Tab.Screen name="Orders" component={OrderQueueScreen} />
      <Tab.Screen name="Stock" component={StockScreen} />
      <Tab.Screen name="Assistant" component={AiAssistantScreen} />
      <Tab.Screen name="Profile" component={ProfileStack} />
    </Tab.Navigator>
  );
};

const styles = StyleSheet.create({
  navContainer: {
    flexDirection: 'row',
    backgroundColor: COLORS.surface,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    paddingTop: 8,
    paddingHorizontal: 2,
    ...SHADOWS.card,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconWrap: {
    width: 42,
    height: 32,
    borderRadius: RADIUS.pill,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 2,
  },
  activePill: {
    backgroundColor: COLORS.surfaceMuted,
  },
  activeLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: COLORS.accentDark,
    textAlign: 'center',
  },
  inactiveLabel: {
    fontSize: 10,
    color: COLORS.textGray,
    textAlign: 'center',
  },
  aiFloatingButton: {
    position: 'absolute',
    right: 25,
    top: -58,
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 3,
    borderColor: COLORS.surface,
    shadowColor: COLORS.textDark,
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.22,
    shadowRadius: 7,
    elevation: 7,
  },
});

export default StaffNavigator;
