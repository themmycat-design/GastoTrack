import React, {useEffect, useState} from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Keyboard, View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, RADIUS, SHADOWS } from '../theme';

import DashboardScreen from '../screens/staff/DashboardScreen';
import TransactionsStack from './TransactionsStack';
import OrdersStack from './OrdersStack';
import StockScreen from '../screens/staff/StockScreen';
import ProfileStack from './ProfileStack';
import AiAssistantScreen from '../screens/staff/AiAssistantScreen';

const Tab = createBottomTabNavigator();
const renderTabBar = props => <CustomTabBar {...props} />;

const CustomTabBar = ({ state, descriptors, navigation }) => {
  const insets = useSafeAreaInsets();
  const [keyboardVisible, setKeyboardVisible] = useState(Keyboard.isVisible?.() || false);
  useEffect(() => {
    const show = Keyboard.addListener('keyboardDidShow', () => setKeyboardVisible(true));
    const hide = Keyboard.addListener('keyboardDidHide', () => setKeyboardVisible(false));
    return () => { show.remove(); hide.remove(); };
  }, []);
  const tabMeta = {
    Dashboard: { icon: 'space-dashboard', label: 'Home' },
    Transactions: { icon: 'receipt-long', label: 'Transactions' },
    Orders: { icon: 'point-of-sale', label: 'Orders' },
    Stock: { icon: 'inventory-2', label: 'Inventory' },
    Profile: { icon: 'person-outline', label: 'Profile' },
  };

  if (keyboardVisible) return null;

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
            accessibilityRole="tab"
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
      <Tab.Screen name="Orders" component={OrdersStack} />
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
    minHeight: 56,
    paddingHorizontal: 2,
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
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.accentDark,
    textAlign: 'center',
  },
  inactiveLabel: {
    fontSize: 11,
    color: COLORS.textGray,
    textAlign: 'center',
  },
});

export default StaffNavigator;
