import React, {useCallback, useState} from 'react';
import {ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useOrders} from '../../context/OrderContext';
import {COLORS, SHADOWS} from '../../theme';

const formatOrderDate = value => {
  if (!value) return 'Date unavailable';
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? 'Date unavailable' : parsed.toLocaleString();
};

const OrderHistoryScreen = ({navigation}) => {
  const {
    orders,
    isLoading,
    isLoadingMore,
    hasMoreOrders,
    fetchOrders,
    loadMoreOrders,
  } = useOrders();
  const [refreshing, setRefreshing] = useState(false);

  useFocusEffect(useCallback(() => {
    fetchOrders({page: 1});
  }, [fetchOrders]));

  const refresh = async () => {
    setRefreshing(true);
    try {
      await fetchOrders({page: 1});
    } finally {
      setRefreshing(false);
    }
  };

  const renderOrder = ({item}) => {
    const items = item.items || [];
    const itemDescription = items.length
      ? items.map(orderItem => `${orderItem.quantity}× ${orderItem.product_name || orderItem.product?.name || 'Product'}`).join(', ')
      : 'Order items unavailable';
    const number = item.orderNumber || item.order_number || `#${item.id}`;
    const paymentMethod = item.paymentMethod || item.payment_method || 'Payment unavailable';
    const status = String(item.status || 'completed').replace(/_/g, ' ');
    const statusColor = status === 'cancelled' ? COLORS.danger : status === 'completed' ? COLORS.success : COLORS.warning;

    return (
      <View style={styles.orderCard}>
        <View style={styles.cardTop}>
          <View style={styles.orderIcon}>
            <Icon name="receipt-text-outline" size={21} color={COLORS.accentDark} />
          </View>
          <View style={styles.orderInfo}>
            <Text style={styles.orderNumber}>Order {number}</Text>
            <Text style={styles.orderDate}>{formatOrderDate(item.createdAt || item.created_at)}</Text>
          </View>
          <Text style={styles.orderTotal}>₱{Number(item.total || 0).toFixed(2)}</Text>
        </View>
        <Text style={styles.orderItems} numberOfLines={2}>{itemDescription}</Text>
        <View style={styles.cardBottom}>
          <Text style={styles.paymentMethod}>{paymentMethod}</Text>
          <Text style={[styles.status, {color: statusColor}]}>{status}</Text>
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Order history"
        leftIcon="arrow-left"
        leftLabel="Back to new order"
        onLeftPress={() => navigation.goBack()}
      />
      {isLoading && !orders.length ? (
        <View style={styles.centerState}>
          <ActivityIndicator size="large" color={COLORS.accent} />
          <Text style={styles.stateText}>Loading order history…</Text>
        </View>
      ) : (
        <FlatList
          data={orders}
          keyExtractor={item => String(item.id)}
          renderItem={renderOrder}
          contentContainerStyle={orders.length ? styles.list : styles.emptyList}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} colors={[COLORS.accent]} />}
          onEndReached={hasMoreOrders ? loadMoreOrders : undefined}
          onEndReachedThreshold={0.35}
          ListEmptyComponent={(
            <View style={styles.centerState}>
              <Icon name="receipt-text-clock-outline" size={52} color={COLORS.textMuted} />
              <Text style={styles.emptyTitle}>No previous orders</Text>
              <Text style={styles.stateText}>Completed orders will appear here.</Text>
            </View>
          )}
          ListFooterComponent={isLoadingMore ? <ActivityIndicator style={styles.footer} color={COLORS.accent} /> : null}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  list: {padding: 18, paddingBottom: 30},
  emptyList: {flexGrow: 1},
  orderCard: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 14,
    marginBottom: 11,
    ...SHADOWS.card,
  },
  cardTop: {flexDirection: 'row', alignItems: 'center'},
  orderIcon: {width: 42, height: 42, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.surfaceMuted},
  orderInfo: {flex: 1, marginHorizontal: 10},
  orderNumber: {fontSize: 14, fontWeight: '700', color: COLORS.textDark},
  orderDate: {fontSize: 11, color: COLORS.textGray, marginTop: 3},
  orderTotal: {fontSize: 15, fontWeight: '700', color: COLORS.accentDark},
  orderItems: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginTop: 12},
  cardBottom: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 11, paddingTop: 10, borderTopWidth: 1, borderTopColor: COLORS.border},
  paymentMethod: {fontSize: 11, fontWeight: '600', color: COLORS.textGray, textTransform: 'capitalize'},
  status: {fontSize: 11, fontWeight: '700', color: COLORS.success, textTransform: 'capitalize'},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28},
  emptyTitle: {fontSize: 16, fontWeight: '700', color: COLORS.textDark, marginTop: 13},
  stateText: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, textAlign: 'center', marginTop: 6},
  footer: {paddingVertical: 16},
});

export default OrderHistoryScreen;
