import React, {useCallback, useEffect, useRef, useState} from 'react';
import {ActivityIndicator, Alert, FlatList, Modal, RefreshControl, StyleSheet, Text, TextInput, TouchableOpacity, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import ApiErrorBanner from '../../components/ApiErrorBanner';
import {useOrders} from '../../context/OrderContext';
import {getApiErrorMessage} from '../../services/apiErrors';
import {canManuallyConfirmOrderPayment, getEffectiveOrderPaymentStatus, getOrderHistoryStatus} from '../../utils/orderHistoryStatus';
import {staffDayKey, useStaffDay} from '../../utils/useStaffDay';
import {COLORS, SHADOWS} from '../../theme';

const formatOrderDate = value => {
  if (!value) return 'Date unavailable';
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? 'Date unavailable' : parsed.toLocaleString();
};

const OrderHistoryScreen = ({navigation}) => {
  const day = useStaffDay();
  const lastDay = useRef(day);
  const [showHistory, setShowHistory] = useState(false);
  const {
    orders,
    isLoading,
    loadError,
    isLoadingMore,
    hasMoreOrders,
    fetchOrders,
    loadMoreOrders,
    cancelOrder,
    confirmPayment,
    retryPayment,
    requestRefund,
    confirmRefund,
  } = useOrders();
  const [refreshing, setRefreshing] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [action, setAction] = useState(null);
  const [reason, setReason] = useState('');
  const [amount, setAmount] = useState('');
  const [reference, setReference] = useState('');
  const [restock, setRestock] = useState(false);
  const [paymentNotReceived, setPaymentNotReceived] = useState(false);
  const [actionBusy, setActionBusy] = useState(false);

  const openAction = (order, nextAction) => {
    setSelectedOrder(order);
    setAction(nextAction);
    setReason('');
    setReference('');
    setAmount(Number(order.total || 0).toFixed(2));
    setRestock(false);
    setPaymentNotReceived(false);
  };

  const closeAction = () => {
    if (actionBusy) return;
    setAction(null);
    setSelectedOrder(null);
  };

  const submitAction = async () => {
    if (!selectedOrder || !action || actionBusy) return;
    const numericAmount = Number(amount);
    if (['confirm-payment', 'confirm-refund'].includes(action) && (!Number.isFinite(numericAmount) || numericAmount <= 0 || !reference.trim())) {
      Alert.alert('Details required', 'Enter the exact amount and the reference from the wallet transaction.');
      return;
    }
    if (['cancel', 'request-refund'].includes(action) && !reason.trim()) {
      Alert.alert('Reason required', 'Add a short reason so the order correction is recorded clearly.');
      return;
    }
    if (action === 'cancel' && !paymentNotReceived) {
      Alert.alert('Payment check required', 'Check the official wallet account and confirm that no payment was received. If the customer paid, confirm the payment and use the refund workflow.');
      return;
    }

    setActionBusy(true);
    try {
      if (action === 'cancel') {
        await cancelOrder(selectedOrder.id, {
          reason: reason.trim(),
          payment_not_received: true,
          ...(selectedOrder.financials_committed_at ? {restock} : {}),
        });
      } else if (action === 'confirm-payment') {
        await confirmPayment(selectedOrder.id, {
          amount: numericAmount,
          currency: selectedOrder.paymentCurrency || 'PHP',
          reference: reference.trim(),
        });
      } else if (action === 'request-refund') {
        await requestRefund(selectedOrder.id, {reason: reason.trim()});
      } else {
        await confirmRefund(selectedOrder.id, {
          amount: numericAmount,
          currency: selectedOrder.paymentCurrency || 'PHP',
          reference: reference.trim(),
          restock,
        });
      }
      setAction(null);
      setSelectedOrder(null);
      Alert.alert('Order updated', action === 'request-refund'
        ? 'Refund is pending manual processing outside GastoTrack. No funds have been moved.'
        : action === 'confirm-refund'
          ? 'Manual refund details were recorded. GastoTrack did not transfer funds.'
          : action === 'confirm-payment'
            ? 'Staff manually confirmed the payment. No provider API verification was performed.'
            : 'Cancellation and its audit record were saved.');
    } catch (error) {
      Alert.alert('Could not update order', getApiErrorMessage(error, 'The server did not confirm this correction. Refresh order history before retrying.'));
    } finally {
      setActionBusy(false);
    }
  };

  useFocusEffect(useCallback(() => {
    fetchOrders({page: 1});
  }, [fetchOrders]));

  useEffect(() => {
    if (lastDay.current === day) return;
    lastDay.current = day;
    fetchOrders({page: 1});
  }, [day, fetchOrders]);

  const visibleOrders = showHistory ? orders : orders.filter(order => {
    const created = new Date(order.createdAt || order.created_at);
    return !Number.isNaN(created.getTime())
      && staffDayKey(created) === day
      && String(order.status).toLowerCase() !== 'cancelled';
  });

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
    const orderStatus = String(item.status || 'pending').trim().toLowerCase();
    const rawPaymentStatus = getEffectiveOrderPaymentStatus(item);
    const status = getOrderHistoryStatus(item);
    const paymentStatus = rawPaymentStatus.replace(/_/g, ' ');
    const statusColor = status === 'cancelled' ? COLORS.danger : status === 'completed' ? COLORS.success : COLORS.warning;
    const canCancel = ['pending', 'detected', 'failed'].includes(rawPaymentStatus)
      && orderStatus !== 'cancelled'
      && (!item.financials_committed_at || rawPaymentStatus === 'detected');
    const needsPaymentReview = canManuallyConfirmOrderPayment(item);
    const canRequestRefund = orderStatus === 'completed' && rawPaymentStatus === 'confirmed';
    const canConfirmRefund = rawPaymentStatus === 'refund_pending';
    const canRetryPayment = rawPaymentStatus === 'failed' && orderStatus === 'pending';

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
        <Text style={styles.paymentStatus}>Payment: {paymentStatus}</Text>
        {canCancel || needsPaymentReview || canRequestRefund || canConfirmRefund || canRetryPayment ? (
          <View style={styles.actionRow}>
            {canCancel ? (
              <TouchableOpacity style={styles.secondaryAction} onPress={() => openAction(item, 'cancel')}>
                <Text style={styles.secondaryActionText}>Cancel order</Text>
              </TouchableOpacity>
            ) : null}
            {needsPaymentReview ? (
              <TouchableOpacity style={styles.primaryAction} onPress={() => openAction(item, 'confirm-payment')}>
                <Text style={styles.primaryActionText}>Verify payment manually</Text>
              </TouchableOpacity>
            ) : null}
            {canRequestRefund ? (
              <TouchableOpacity style={styles.secondaryAction} onPress={() => openAction(item, 'request-refund')}>
                <Text style={styles.secondaryActionText}>Request manual refund</Text>
              </TouchableOpacity>
            ) : null}
            {canConfirmRefund ? (
              <TouchableOpacity style={styles.primaryAction} onPress={() => openAction(item, 'confirm-refund')}>
                <Text style={styles.primaryActionText}>Record completed refund</Text>
              </TouchableOpacity>
            ) : null}
            {canRetryPayment ? (
              <TouchableOpacity
                style={styles.primaryAction}
                onPress={() => Alert.alert('Retry payment?', 'This reopens manual payment verification. No payment, stock deduction, or income is created until staff confirms it.', [
                  {text: 'Not now', style: 'cancel'},
                  {text: 'Retry', onPress: async () => {
                    try {
                      await retryPayment(item.id);
                    } catch (error) {
                      Alert.alert('Could not retry payment', getApiErrorMessage(error));
                    }
                  }},
                ])}>
                <Text style={styles.primaryActionText}>Retry payment</Text>
              </TouchableOpacity>
            ) : null}
          </View>
        ) : null}
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
      <View style={styles.dayScopeRow}>
        <TouchableOpacity style={[styles.dayScopeButton, !showHistory && styles.dayScopeActive]} onPress={() => setShowHistory(false)} accessibilityRole="button" accessibilityState={{selected: !showHistory}}>
          <Text style={[styles.dayScopeText, !showHistory && styles.dayScopeTextActive]}>Today</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.dayScopeButton, showHistory && styles.dayScopeActive]} onPress={() => setShowHistory(true)} accessibilityRole="button" accessibilityState={{selected: showHistory}}>
          <Text style={[styles.dayScopeText, showHistory && styles.dayScopeTextActive]}>History</Text>
        </TouchableOpacity>
      </View>
      {!showHistory ? <Text style={styles.historyHint}>Cancelled and older orders remain in History for review.</Text> : null}
      <ApiErrorBanner message={loadError} onRetry={() => fetchOrders({page: 1})} />
      {isLoading && !orders.length ? (
        <View style={styles.centerState}>
          <ActivityIndicator size="large" color={COLORS.accent} />
          <Text style={styles.stateText}>Loading order history…</Text>
        </View>
      ) : (
        <FlatList
          data={visibleOrders}
          keyExtractor={item => String(item.id)}
          renderItem={renderOrder}
          contentContainerStyle={visibleOrders.length ? styles.list : styles.emptyList}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} colors={[COLORS.accent]} />}
          onEndReached={hasMoreOrders && (showHistory || visibleOrders.length) ? loadMoreOrders : undefined}
          onEndReachedThreshold={0.35}
          ListEmptyComponent={(
            <View style={styles.centerState}>
              <Icon name="receipt-text-clock-outline" size={52} color={COLORS.textMuted} />
              <Text style={styles.emptyTitle}>{showHistory ? 'No previous orders' : 'No orders today'}</Text>
              <Text style={styles.stateText}>{showHistory ? 'Completed orders will appear here.' : 'Older and cancelled orders remain available in History.'}</Text>
            </View>
          )}
          ListFooterComponent={isLoadingMore ? <ActivityIndicator style={styles.footer} color={COLORS.accent} /> : null}
        />
      )}

      <Modal visible={!!action} transparent animationType="slide" onRequestClose={closeAction}>
        <View style={styles.modalBackdrop}>
          <View style={styles.actionModal}>
            <Text style={styles.modalTitle}>
              {action === 'cancel' ? 'Cancel order'
                : action === 'confirm-payment' ? 'Manually confirm payment'
                  : action === 'request-refund' ? 'Request a manual refund'
                    : 'Record manual refund'}
            </Text>
            <Text style={styles.modalDescription}>
              {action === 'confirm-payment'
                ? 'Check the official wallet account first. A device notification is not proof; this records staff attestation only.'
                : action === 'request-refund'
                  ? 'This creates a pending manual refund record. GastoTrack cannot send money to the customer.'
                  : action === 'confirm-refund'
                    ? 'Only continue after processing the refund outside GastoTrack. This app does not transfer or verify funds.'
                    : selectedOrder?.financials_committed_at
                      ? 'This legacy sale already affected accounting and stock. Only void it after checking the wallet and confirming no payment was received. A received payment must be confirmed, then refunded. Choose whether items were physically returned.'
                      : 'Check the official wallet account and confirm no payment was received. If the customer paid, confirm the payment and use the refund workflow.'}
            </Text>
            {['cancel', 'request-refund'].includes(action) ? (
              <TextInput style={styles.actionInput} value={reason} onChangeText={setReason} placeholder="Reason" multiline />
            ) : null}
            {action === 'cancel' ? (
              <TouchableOpacity style={styles.restockToggle} onPress={() => setPaymentNotReceived(value => !value)}>
                <Icon name={paymentNotReceived ? 'checkbox-marked' : 'checkbox-blank-outline'} size={21} color={COLORS.accentDark} />
                <Text style={styles.restockLabel}>I checked the official wallet; no payment was received</Text>
              </TouchableOpacity>
            ) : null}
            {['confirm-payment', 'confirm-refund'].includes(action) ? (
              <>
                <Text style={styles.inputLabel}>Amount ({selectedOrder?.paymentCurrency || 'PHP'})</Text>
                <TextInput style={styles.actionInput} value={amount} onChangeText={setAmount} keyboardType="decimal-pad" />
                <Text style={styles.inputLabel}>Wallet reference</Text>
                <TextInput style={styles.actionInput} value={reference} onChangeText={setReference} autoCapitalize="characters" />
              </>
            ) : null}
            {action === 'confirm-refund' || (action === 'cancel' && !!selectedOrder?.financials_committed_at) ? (
              <TouchableOpacity style={styles.restockToggle} onPress={() => setRestock(value => !value)}>
                <Icon name={restock ? 'checkbox-marked' : 'checkbox-blank-outline'} size={21} color={COLORS.accentDark} />
                <Text style={styles.restockLabel}>Items were physically returned; restore stock</Text>
              </TouchableOpacity>
            ) : null}
            <View style={styles.modalButtons}>
              <TouchableOpacity style={styles.modalCancel} onPress={closeAction} disabled={actionBusy}>
                <Text style={styles.modalCancelText}>Back</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.modalSubmit, actionBusy && styles.disabled]} onPress={submitAction} disabled={actionBusy}>
                {actionBusy ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.modalSubmitText}>Confirm</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  dayScopeRow: {flexDirection: 'row', gap: 8, paddingHorizontal: 18, paddingVertical: 10, backgroundColor: COLORS.surface},
  dayScopeButton: {flex: 1, alignItems: 'center', paddingVertical: 9, borderRadius: 999, borderWidth: 1, borderColor: COLORS.border},
  dayScopeActive: {backgroundColor: COLORS.accent, borderColor: COLORS.accent},
  dayScopeText: {fontSize: 12, fontWeight: '700', color: COLORS.textGray},
  dayScopeTextActive: {color: '#FFFFFF'},
  historyHint: {paddingHorizontal: 18, paddingVertical: 8, fontSize: 11, color: COLORS.textGray},
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
  paymentStatus: {fontSize: 11, color: COLORS.textGray, marginTop: 8, textTransform: 'capitalize'},
  actionRow: {flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12},
  primaryAction: {backgroundColor: COLORS.accent, borderRadius: 9, paddingHorizontal: 11, paddingVertical: 9},
  primaryActionText: {fontSize: 11, fontWeight: '700', color: '#FFFFFF'},
  secondaryAction: {borderWidth: 1, borderColor: COLORS.border, borderRadius: 9, paddingHorizontal: 11, paddingVertical: 9},
  secondaryActionText: {fontSize: 11, fontWeight: '700', color: COLORS.textDark},
  modalBackdrop: {flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.45)'},
  actionModal: {backgroundColor: COLORS.surface, borderTopLeftRadius: 22, borderTopRightRadius: 22, padding: 20, paddingBottom: 30},
  modalTitle: {fontSize: 19, fontWeight: '700', color: COLORS.textDark},
  modalDescription: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, marginTop: 8, marginBottom: 12},
  inputLabel: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, marginTop: 9, marginBottom: 5},
  actionInput: {minHeight: 46, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 9, color: COLORS.textDark, backgroundColor: COLORS.background, marginTop: 7},
  restockToggle: {flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 13},
  restockLabel: {flex: 1, fontSize: 12, color: COLORS.textDark},
  modalButtons: {flexDirection: 'row', gap: 10, marginTop: 20},
  modalCancel: {flex: 1, alignItems: 'center', justifyContent: 'center', minHeight: 48, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11},
  modalCancelText: {fontSize: 14, fontWeight: '700', color: COLORS.textDark},
  modalSubmit: {flex: 1, alignItems: 'center', justifyContent: 'center', minHeight: 48, borderRadius: 11, backgroundColor: COLORS.accent},
  modalSubmitText: {fontSize: 14, fontWeight: '700', color: '#FFFFFF'},
  disabled: {opacity: 0.65},
  status: {fontSize: 11, fontWeight: '700', color: COLORS.success, textTransform: 'capitalize'},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28},
  emptyTitle: {fontSize: 16, fontWeight: '700', color: COLORS.textDark, marginTop: 13},
  stateText: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, textAlign: 'center', marginTop: 6},
  footer: {paddingVertical: 16},
});

export default OrderHistoryScreen;
