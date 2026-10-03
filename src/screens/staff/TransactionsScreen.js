import React, {useEffect, useMemo, useState} from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {useTransactions} from '../../context/TransactionContext';
import {COLORS, RADIUS, SHADOWS} from '../../theme';

const localDate = () => {
  const now = new Date();
  const pad = value => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

const INCOME_SOURCES = ['Cash', 'Maya', 'GCash'];
const EXPENSE_SOURCES = ['Cash', 'Maya', 'GCash', 'Bank Transfer', 'Credit Card'];

const emptyForm = (type = 'Income') => ({
  amount: '',
  type,
  source: '',
  category: '',
  date: localDate(),
  notes: '',
});

const TransactionsScreen = ({route}) => {
  const {user} = useAuth();
  const {
    transactions,
    transactionTotal,
    isLoading,
    isLoadingMore,
    hasMoreTransactions,
    fetchTransactions,
    loadMoreTransactions,
    addTransaction,
    updateTransaction,
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
  } = useTransactions();

  const [refreshing, setRefreshing] = useState(false);
  const [activeType, setActiveType] = useState('Income');
  const [editorVisible, setEditorVisible] = useState(false);
  const [detailVisible, setDetailVisible] = useState(false);
  const [editingTransaction, setEditingTransaction] = useState(null);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);

  const categories = useMemo(
    () => form.type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES,
    [EXPENSE_CATEGORIES, INCOME_CATEGORIES, form.type],
  );
  const sources = form.type === 'Income' ? INCOME_SOURCES : EXPENSE_SOURCES;
  const filteredTransactions = useMemo(
    () => transactions.filter(transaction => transaction.type === activeType),
    [activeType, transactions],
  );

  useEffect(() => {
    const requestedType = route?.params?.initialType;
    if (requestedType === 'Income' || requestedType === 'Expense') {
      setActiveType(requestedType);
    }
  }, [route?.params?.initialType, route?.params?.requestedAt]);

  const setField = (field, value) => setForm(current => ({...current, [field]: value}));

  const openAdd = () => {
    setEditingTransaction(null);
    setForm(emptyForm(activeType));
    setEditorVisible(true);
  };

  const openDetail = transaction => {
    setSelectedTransaction(transaction);
    setDetailVisible(true);
  };

  const canEdit = transaction => transaction
    && transaction.entryMethod !== 'order_system'
    && Number(transaction.user_id) === Number(user?.id);

  const openEdit = transaction => {
    if (!canEdit(transaction)) return;
    setEditingTransaction(transaction);
    setForm({
      amount: String(transaction.amount ?? ''),
      type: transaction.type || 'Income',
      source: transaction.source || 'Cash',
      category: transaction.category || '',
      date: transaction.date || localDate(),
      notes: transaction.notes || '',
    });
    setDetailVisible(false);
    setEditorVisible(true);
  };

  const errorMessage = result => {
    const error = result?.error;
    if (typeof error === 'string') return error;
    const data = error?.response?.data;
    return Object.values(data?.errors || {}).flat().join('\n')
      || data?.message
      || 'Please check your connection and try again.';
  };

  const save = async () => {
    const amount = Number(form.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      Alert.alert('Invalid amount', 'Enter an amount greater than zero.');
      return;
    }
    if (!form.source) {
      Alert.alert('Source required', 'Choose a payment source before selecting a category.');
      return;
    }
    if (!form.category) {
      Alert.alert('Category required', 'Choose a transaction category.');
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date) || form.date > localDate()) {
      Alert.alert('Invalid date', 'Use YYYY-MM-DD and do not enter a future date.');
      return;
    }

    setSaving(true);
    const payload = {...form, amount, entryMethod: editingTransaction?.entryMethod || 'manual'};
    const result = editingTransaction
      ? await updateTransaction({...editingTransaction, ...payload})
      : await addTransaction(payload);
    setSaving(false);

    if (!result.success) {
      Alert.alert('Could not save transaction', errorMessage(result));
      return;
    }
    setActiveType(form.type);
    setEditorVisible(false);
    setEditingTransaction(null);
    setForm(emptyForm(form.type));
  };

  const refresh = async () => {
    setRefreshing(true);
    try {
      await fetchTransactions();
    } finally {
      setRefreshing(false);
    }
  };

  const renderTransaction = ({item}) => {
    const income = item.type?.toLowerCase() === 'income';
    return (
      <TouchableOpacity style={styles.card} onPress={() => openDetail(item)} activeOpacity={0.75}>
        <View style={[styles.iconBox, income ? styles.incomeIcon : styles.expenseIcon]}>
          <Icon name={income ? 'arrow-down-left' : 'arrow-up-right'} size={22} color={income ? COLORS.success : COLORS.danger} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle}>{item.category || item.type}</Text>
          <Text style={styles.cardSubtitle}>{item.source || 'Unknown source'} · {item.date}</Text>
          {item.notes ? <Text style={styles.cardNote} numberOfLines={1}>{item.notes}</Text> : null}
        </View>
        <Text style={[styles.amount, income ? styles.incomeAmount : styles.expenseAmount]}>
          {income ? '+' : '-'}₱{Number(item.amount || 0).toFixed(2)}
        </Text>
      </TouchableOpacity>
    );
  };

  const renderChips = (values, selected, onSelect) => (
    <View style={styles.chips}>
      {values.map(value => (
        <TouchableOpacity key={value} style={[styles.chip, selected === value && styles.chipActive]} onPress={() => onSelect(value)}>
          <Text style={[styles.chipText, selected === value && styles.chipTextActive]}>{value}</Text>
        </TouchableOpacity>
      ))}
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Transactions"
        subtitle={`${transactionTotal || transactions.length} total record${(transactionTotal || transactions.length) === 1 ? '' : 's'}`}
        actionIcon="plus"
        actionLabel="Add transaction"
        onActionPress={openAdd}
        centered
      />

      <View style={styles.tabs}>
        {['Income', 'Expense'].map(type => {
          const selected = activeType === type;
          return (
            <TouchableOpacity key={type} style={[styles.tab, selected && styles.tabActive]} onPress={() => setActiveType(type)}>
              <Icon name={type === 'Income' ? 'arrow-down-left' : 'arrow-up-right'} size={19} color={selected ? COLORS.accentDark : COLORS.textGray} />
              <Text style={[styles.tabText, selected && styles.tabTextActive]}>{type === 'Income' ? 'Income' : 'Expenses'}</Text>
            </TouchableOpacity>
          );
        })}
      </View>

      {isLoading && !transactions.length ? (
        <View style={styles.centerState}>
          <ActivityIndicator size="large" color={COLORS.accent} />
          <Text style={styles.stateText}>Loading transactions...</Text>
        </View>
      ) : (
        <FlatList
          data={filteredTransactions}
          keyExtractor={item => String(item.id)}
          renderItem={renderTransaction}
          contentContainerStyle={filteredTransactions.length ? styles.list : styles.emptyList}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} colors={[COLORS.accent]} />}
          ListEmptyComponent={<View style={styles.centerState}>
            <Icon name="receipt-text-outline" size={58} color={COLORS.textMuted} />
            <Text style={styles.emptyTitle}>No {activeType === 'Income' ? 'income' : 'expenses'} yet</Text>
            <Text style={styles.stateText}>Tap + to record the first {activeType.toLowerCase()} transaction.</Text>
          </View>}
          ListFooterComponent={isLoadingMore ? <ActivityIndicator style={styles.footerLoader} color={COLORS.accent} /> : null}
          onEndReached={hasMoreTransactions ? loadMoreTransactions : undefined}
          onEndReachedThreshold={0.35}
        />
      )}

      <Modal visible={editorVisible} transparent animationType="slide" onRequestClose={() => setEditorVisible(false)}>
        <View style={styles.overlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>{editingTransaction ? 'Edit transaction' : form.type === 'Income' ? 'Add income' : 'Add expense'}</Text>
                <Text style={styles.modalSubtitle}>{form.type === 'Income' ? 'Income' : 'Expense'} transaction</Text>
              </View>
              <TouchableOpacity onPress={() => setEditorVisible(false)} accessibilityLabel="Close transaction form">
                <Icon name="close" size={24} color={COLORS.textGray} />
              </TouchableOpacity>
            </View>
            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              <Text style={styles.label}>Source</Text>
              {renderChips(sources, form.source, value => setForm(current => ({...current, source: value, category: ''})))}

              {form.source ? <>
                <Text style={styles.label}>Category</Text>
                {renderChips(categories, form.category, value => setField('category', value))}
              </> : <View style={styles.sourceHint}>
                <Icon name="information-outline" size={17} color={COLORS.accentDark} />
                <Text style={styles.sourceHintText}>Select a source to show the available categories.</Text>
              </View>}

              <Text style={styles.label}>Amount</Text>
              <View style={styles.amountInputWrap}>
                <Text style={styles.peso}>₱</Text>
                <TextInput style={styles.amountInput} value={form.amount} onChangeText={value => setField('amount', value)} keyboardType="decimal-pad" placeholder="0.00" />
              </View>

              <Text style={styles.label}>Date</Text>
              <TextInput style={styles.input} value={form.date} onChangeText={value => setField('date', value)} placeholder="YYYY-MM-DD" />

              <Text style={styles.label}>Notes (optional)</Text>
              <TextInput style={[styles.input, styles.notesInput]} value={form.notes} onChangeText={value => setField('notes', value)} placeholder="Add a short note" multiline />

              <TouchableOpacity style={[styles.saveButton, saving && styles.disabled]} onPress={save} disabled={saving}>
                {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.saveButtonText}>{editingTransaction ? 'Save changes' : 'Save transaction'}</Text>}
              </TouchableOpacity>
            </ScrollView>
          </View>
        </View>
      </Modal>

      <Modal visible={detailVisible} transparent animationType="fade" onRequestClose={() => setDetailVisible(false)}>
        <View style={styles.centerOverlay}>
          <View style={styles.detailCard}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Transaction details</Text>
                <Text style={styles.modalSubtitle}>{selectedTransaction?.date}</Text>
              </View>
              <TouchableOpacity onPress={() => setDetailVisible(false)} accessibilityLabel="Close transaction details">
                <Icon name="close" size={24} color={COLORS.textGray} />
              </TouchableOpacity>
            </View>
            {selectedTransaction ? <>
              <Text style={[
                styles.detailAmount,
                selectedTransaction.type?.toLowerCase() === 'income' ? styles.incomeAmount : styles.expenseAmount,
              ]}>
                {selectedTransaction.type?.toLowerCase() === 'income' ? '+' : '-'}₱{Number(selectedTransaction.amount || 0).toFixed(2)}
              </Text>
              {[
                ['Type', selectedTransaction.type],
                ['Category', selectedTransaction.category],
                ['Source', selectedTransaction.source],
                ['Date', selectedTransaction.date],
                ['Notes', selectedTransaction.notes || 'None'],
              ].map(([label, value]) => <View key={label} style={styles.detailRow}>
                <Text style={styles.detailLabel}>{label}</Text>
                <Text style={styles.detailValue}>{value}</Text>
              </View>)}

              {canEdit(selectedTransaction) ? (
                <TouchableOpacity style={styles.editButton} onPress={() => openEdit(selectedTransaction)}>
                  <Icon name="pencil-outline" size={18} color={COLORS.accentDark} />
                  <Text style={styles.editButtonText}>Edit transaction</Text>
                </TouchableOpacity>
              ) : (
                <View style={styles.protectedNotice}>
                  <Icon name="lock-outline" size={18} color="#8A5A00" />
                  <Text style={styles.protectedText}>
                    {selectedTransaction.entryMethod === 'order_system'
                      ? 'Order-generated sales are protected accounting records.'
                      : 'You can only edit transactions that you recorded.'}
                  </Text>
                </View>
              )}
            </> : null}
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  tabs: {flexDirection: 'row', backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border},
  tab: {flex: 1, minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7, borderBottomWidth: 3, borderBottomColor: 'transparent'},
  tabActive: {borderBottomColor: COLORS.accent},
  tabText: {fontSize: 13, fontWeight: '600', color: COLORS.textGray},
  tabTextActive: {color: COLORS.accentDark},
  list: {paddingHorizontal: 20, paddingTop: 20, paddingBottom: 28},
  emptyList: {flexGrow: 1},
  card: {flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, padding: 14, marginBottom: 10, ...SHADOWS.card},
  iconBox: {width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center'},
  incomeIcon: {backgroundColor: '#E8F5E9'}, expenseIcon: {backgroundColor: '#FFEBEE'},
  cardBody: {flex: 1, marginHorizontal: 12}, cardTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark}, cardSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 3}, cardNote: {fontSize: 11, color: COLORS.textMuted, marginTop: 3},
  amount: {fontSize: 15, fontWeight: '700'}, incomeAmount: {color: COLORS.success}, expenseAmount: {color: COLORS.danger},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32}, emptyTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark, marginTop: 14}, stateText: {fontSize: 13, color: COLORS.textGray, textAlign: 'center', marginTop: 7}, footerLoader: {paddingVertical: 18},
  overlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'flex-end'},
  centerOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'center', padding: 20},
  modalCard: {maxHeight: '92%', backgroundColor: COLORS.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 20},
  detailCard: {backgroundColor: COLORS.surface, borderRadius: 20, padding: 20},
  modalHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16}, modalTitle: {fontSize: 20, fontWeight: '700', color: COLORS.textDark}, modalSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 3},
  label: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', marginTop: 16, marginBottom: 7},
  amountInputWrap: {flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 12}, peso: {fontSize: 22, fontWeight: '700', color: COLORS.textDark, marginRight: 6}, amountInput: {flex: 1, fontSize: 22, color: COLORS.textDark, paddingVertical: 10},
  input: {borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, color: COLORS.textDark, paddingHorizontal: 12, paddingVertical: 11}, notesInput: {height: 80, textAlignVertical: 'top'},
  chips: {flexDirection: 'row', flexWrap: 'wrap', gap: 8}, chip: {paddingHorizontal: 13, paddingVertical: 8, borderRadius: RADIUS.pill, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border}, chipActive: {backgroundColor: COLORS.accent, borderColor: COLORS.accent}, chipText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray}, chipTextActive: {color: '#FFFFFF'},
  sourceHint: {flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: COLORS.surfaceMuted, borderRadius: 10, padding: 11, marginTop: 12}, sourceHintText: {flex: 1, fontSize: 12, lineHeight: 17, color: COLORS.textGray},
  saveButton: {minHeight: 50, backgroundColor: COLORS.accent, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginTop: 22, marginBottom: 8}, saveButtonText: {fontSize: 15, fontWeight: '700', color: '#FFFFFF'}, disabled: {opacity: 0.6},
  detailAmount: {fontSize: 30, fontWeight: '700', marginBottom: 14}, detailRow: {flexDirection: 'row', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: COLORS.border, paddingVertical: 11}, detailLabel: {fontSize: 13, color: COLORS.textGray}, detailValue: {maxWidth: '65%', fontSize: 13, fontWeight: '600', color: COLORS.textDark, textAlign: 'right'},
  editButton: {minHeight: 48, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 20}, editButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.accentDark},
  protectedNotice: {flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 12, backgroundColor: '#FFF8E1', padding: 13, marginTop: 20}, protectedText: {flex: 1, fontSize: 12, lineHeight: 18, color: '#8A5A00'},
});

export default TransactionsScreen;
