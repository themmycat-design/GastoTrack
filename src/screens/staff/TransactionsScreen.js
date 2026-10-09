import React, {useCallback, useMemo, useRef, useState} from 'react';
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

const parseLocalDate = value => {
  const [year, month, day] = String(value || localDate()).split('-').map(Number);
  return new Date(year, month - 1, day);
};

const formatLocalDate = date => {
  const pad = value => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const emptyForm = (type = 'Income') => ({
  amount: '',
  type,
  source: '',
  category: '',
  date: localDate(),
  notes: '',
});

const getErrorMessage = result => {
  const error = result?.error;
  if (typeof error === 'string') return error;
  const data = error?.response?.data;
  return Object.values(data?.errors || {}).flat().join('\n')
    || data?.message
    || 'Please check your connection and try again.';
};

const TransactionFilters = React.memo(({sources, fetchCategories, onApply}) => {
  const [search, setSearch] = useState('');
  const [source, setSource] = useState('All');
  const [category, setCategory] = useState('All');
  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const [hasAppliedFilters, setHasAppliedFilters] = useState(false);
  const categoryRequestId = useRef(0);

  const renderChips = (values, selected, onSelect) => (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.chips}
      keyboardShouldPersistTaps="handled">
      {values.map(value => (
        <TouchableOpacity key={value} style={[styles.chip, selected === value && styles.chipActive]} onPress={() => onSelect(value)}>
          <Text style={[styles.chipText, selected === value && styles.chipTextActive]}>{value}</Text>
        </TouchableOpacity>
      ))}
    </ScrollView>
  );

  const selectSource = async nextSource => {
    setSource(nextSource);
    setCategory('All');
    setCategories([]);
    const requestId = ++categoryRequestId.current;

    if (nextSource === 'All') {
      setCategoriesLoading(false);
      return;
    }

    setCategoriesLoading(true);
    const result = await fetchCategories(nextSource);
    if (requestId !== categoryRequestId.current) return;
    setCategoriesLoading(false);
    if (result.success) {
      setCategories(result.categories);
    } else {
      Alert.alert('Could not load categories', getErrorMessage(result));
    }
  };

  const apply = () => {
    const filters = {search: search.trim(), source, category};
    setHasAppliedFilters(Boolean(filters.search || source !== 'All' || category !== 'All'));
    onApply(filters);
  };

  const clear = () => {
    categoryRequestId.current += 1;
    setSearch('');
    setSource('All');
    setCategory('All');
    setCategories([]);
    setCategoriesLoading(false);
    setHasAppliedFilters(false);
    onApply({search: '', source: 'All', category: 'All'});
  };

  const showClear = hasAppliedFilters || search.trim() || source !== 'All' || category !== 'All';

  return (
    <View style={styles.filters}>
      <View style={styles.searchBox}>
        <Icon name="magnify" size={21} color={COLORS.textGray} />
        <TextInput
          style={styles.searchInput}
          value={search}
          onChangeText={setSearch}
          placeholder="Search transactions..."
          placeholderTextColor={COLORS.textMuted}
          accessibilityLabel="Search transactions"
        />
        {search ? <TouchableOpacity onPress={() => setSearch('')} accessibilityLabel="Clear transaction search"><Icon name="close-circle" size={19} color={COLORS.textMuted} /></TouchableOpacity> : null}
      </View>
      <Text style={styles.filterLabel}>Source</Text>
      {renderChips(['All', ...sources], source, selectSource)}
      {source !== 'All' ? <>
        <Text style={styles.filterLabel}>Category</Text>
        {categoriesLoading
          ? <ActivityIndicator style={styles.categoryLoader} size="small" color={COLORS.accent} />
          : renderChips(['All', ...categories], category, setCategory)}
      </> : <Text style={styles.categoryHint}>Choose a source to view category filters.</Text>}
      <View style={styles.filterActions}>
        <TouchableOpacity style={styles.applyFilterButton} onPress={apply} accessibilityRole="button">
          <Icon name="filter-check-outline" size={18} color="#FFFFFF" />
          <Text style={styles.applyFilterText}>Apply filters</Text>
        </TouchableOpacity>
        {showClear ? (
          <TouchableOpacity style={styles.clearFilterButton} onPress={clear} accessibilityRole="button">
            <Text style={styles.clearFilterText}>Clear</Text>
          </TouchableOpacity>
        ) : null}
      </View>
    </View>
  );
});

const TransactionActivityList = React.memo(({
  transactions,
  loading,
  refreshing,
  onRefresh,
  loadingMore,
  hasMore,
  onLoadMore,
  onOpenDetail,
  renderTransaction: renderTransactionProp,
  hasFilters,
}) => {
  const defaultRenderTransaction = useCallback(({item}) => {
    const income = item.type?.toLowerCase() === 'income';
    return (
      <TouchableOpacity style={styles.card} onPress={() => onOpenDetail(item)} activeOpacity={0.75}>
        <View style={[styles.iconBox, income ? styles.incomeIcon : styles.expenseIcon]}>
          <Icon name={income ? 'arrow-down-left' : 'arrow-up-right'} size={22} color={income ? COLORS.success : COLORS.danger} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle}>{item.category || 'Uncategorized'}</Text>
          <Text style={styles.cardSubtitle}>{item.source || 'Unknown source'} · {item.date}</Text>
          {item.notes ? <Text style={styles.cardNote} numberOfLines={1}>{item.notes}</Text> : null}
        </View>
        <Text style={[styles.amount, income ? styles.incomeAmount : styles.expenseAmount]}>
          {income ? '+' : '-'}₱{Number(item.amount || 0).toFixed(2)}
        </Text>
      </TouchableOpacity>
    );
  }, [onOpenDetail]);

  return (
    <FlatList
      data={transactions}
      keyExtractor={item => String(item.id)}
      renderItem={renderTransactionProp || defaultRenderTransaction}
      ListHeaderComponent={<View style={styles.activityHeader}>
        <View style={styles.activityHeading}>
          <Text style={styles.activityTitle}>Activity</Text>
          {loading ? <ActivityIndicator size="small" color={COLORS.accent} /> : null}
        </View>
        <Text style={styles.activityCount}>{transactions.length} shown</Text>
      </View>}
      contentContainerStyle={transactions.length ? styles.list : styles.emptyList}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.accent]} />}
      ListEmptyComponent={<View style={styles.centerState}>
        {loading
          ? <><ActivityIndicator size="large" color={COLORS.accent} /><Text style={styles.stateText}>Loading activity...</Text></>
          : <>
            <Icon name="receipt-text-outline" size={58} color={COLORS.textMuted} />
            <Text style={styles.emptyTitle}>No transactions found</Text>
            <Text style={styles.stateText}>{hasFilters ? 'Try changing your search or filters.' : 'Tap + to record the first transaction.'}</Text>
            {!transactions.length && hasMore && !loadingMore ? (
              <TouchableOpacity style={styles.loadMoreButton} onPress={onLoadMore} accessibilityRole="button">
                <Text style={styles.loadMoreText}>Load more activity</Text>
              </TouchableOpacity>
            ) : null}
          </>}
      </View>}
      ListFooterComponent={loadingMore ? <ActivityIndicator style={styles.footerLoader} color={COLORS.accent} /> : null}
      onEndReached={hasMore && transactions.length ? onLoadMore : undefined}
      onEndReachedThreshold={0.35}
    />
  );
});

const TransactionsScreen = ({route}) => {
  const {user} = useAuth();
  const {
    transactions,
    isLoading,
    isLoadingMore,
    hasMoreTransactions,
    fetchTransactions,
    queryTransactions,
    fetchTransactionCategories,
    loadMoreTransactions,
    addTransaction,
    updateTransaction,
    deleteTransaction,
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
    TRANSACTION_SOURCES,
  } = useTransactions();

  const [refreshing, setRefreshing] = useState(false);
  const [activityFilter, setActivityFilter] = useState({search: '', source: 'All', category: 'All'});
  const [activityTransactions, setActivityTransactions] = useState(null);
  const [activityPagination, setActivityPagination] = useState(null);
  const [activityLoading, setActivityLoading] = useState(false);
  const [activityLoadingMore, setActivityLoadingMore] = useState(false);
  const [editorVisible, setEditorVisible] = useState(false);
  const [detailVisible, setDetailVisible] = useState(false);
  const [editingTransaction, setEditingTransaction] = useState(null);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [datePickerVisible, setDatePickerVisible] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => {
    const today = new Date();
    return new Date(today.getFullYear(), today.getMonth(), 1);
  });
  const activityRequestId = useRef(0);

  const calendarDays = useMemo(() => {
    const startOffset = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1).getDay();
    const dayCount = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0).getDate();
    return Array.from({length: 42}, (_, index) => {
      const day = index - startOffset + 1;
      return day > 0 && day <= dayCount ? day : null;
    });
  }, [calendarMonth]);

  const categories = useMemo(
    () => form.type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES,
    [EXPENSE_CATEGORIES, INCOME_CATEGORIES, form.type],
  );
  const incomeSources = useMemo(
    () => TRANSACTION_SOURCES.filter(source => !['Bank Transfer', 'Credit Card'].includes(source)),
    [TRANSACTION_SOURCES],
  );
  const sources = form.type === 'Income' ? incomeSources : TRANSACTION_SOURCES;
  const displayedTransactions = activityTransactions ?? transactions;
  const hasAppliedFilters = Boolean(activityFilter.search || activityFilter.source !== 'All' || activityFilter.category !== 'All');
  const hasMoreActivity = activityTransactions === null
    ? hasMoreTransactions
    : Number(activityPagination?.currentPage || 0) < Number(activityPagination?.lastPage || 1);

  const setField = (field, value) => setForm(current => ({...current, [field]: value}));

  const openDatePicker = () => {
    const selectedDate = parseLocalDate(form.date);
    setCalendarMonth(new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1));
    setDatePickerVisible(true);
  };

  const chooseCalendarDay = day => {
    const selectedDate = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day);
    const value = formatLocalDate(selectedDate);
    if (value <= localDate()) {
      setField('date', value);
      setDatePickerVisible(false);
    }
  };

  const openAdd = useCallback(() => {
    setEditingTransaction(null);
    setForm(emptyForm(route?.params?.initialType === 'Expense' ? 'Expense' : 'Income'));
    setEditorVisible(true);
  }, [route?.params?.initialType]);

  const openDetail = useCallback(transaction => {
    setSelectedTransaction(transaction);
    setDetailVisible(true);
  }, []);

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

  const confirmDelete = transaction => {
    if (!canEdit(transaction) || deleting) return;
    Alert.alert(
      'Delete transaction?',
      'This manual transaction will be removed from the business records. This cannot be undone in the app.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            setDeleting(true);
            const result = await deleteTransaction(transaction.id);
            setDeleting(false);
            if (!result.success) {
              Alert.alert('Could not delete transaction', getErrorMessage(result));
              return;
            }
            if (activityTransactions !== null) await refreshFilteredActivity();
            setDetailVisible(false);
            setSelectedTransaction(null);
          },
        },
      ],
    );
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
      Alert.alert('Could not save transaction', getErrorMessage(result));
      return;
    }
    setEditorVisible(false);
    setEditingTransaction(null);
    setForm(emptyForm(form.type));
    if (activityTransactions !== null) await refreshFilteredActivity();
  };

  const refreshFilteredActivity = useCallback(async () => {
    const result = await queryTransactions({page: 1, ...activityFilter});
    if (result.success) {
      setActivityTransactions(result.transactions);
      setActivityPagination(result.pagination);
    }
  }, [activityFilter, queryTransactions]);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    try {
      if (activityTransactions !== null) {
        await refreshFilteredActivity();
      } else {
        await fetchTransactions();
      }
    } finally {
      setRefreshing(false);
    }
  }, [activityTransactions, fetchTransactions, refreshFilteredActivity]);

  const applyActivityFilters = useCallback(async nextFilter => {
    const normalizedFilter = {
      search: nextFilter.search?.trim() || '',
      source: nextFilter.source || 'All',
      category: nextFilter.category || 'All',
    };
    if (!normalizedFilter.search && normalizedFilter.source === 'All' && normalizedFilter.category === 'All') {
      activityRequestId.current += 1;
      setActivityFilter(normalizedFilter);
      setActivityTransactions(null);
      setActivityPagination(null);
      setActivityLoading(false);
      return;
    }

    const requestId = ++activityRequestId.current;
    setActivityLoading(true);
    const result = await queryTransactions({page: 1, ...normalizedFilter});
    if (requestId !== activityRequestId.current) return;
    setActivityLoading(false);
    if (!result.success) {
      Alert.alert('Could not filter transactions', getErrorMessage(result));
      return;
    }

    setActivityFilter(normalizedFilter);
    setActivityTransactions(result.transactions);
    setActivityPagination(result.pagination);
  }, [queryTransactions]);

  const loadMoreActivity = useCallback(async () => {
    if (activityTransactions === null) {
      return loadMoreTransactions();
    }
    if (activityLoadingMore || !hasMoreActivity) return;

    const nextPage = Number(activityPagination?.currentPage || 1) + 1;
    setActivityLoadingMore(true);
    const result = await queryTransactions({page: nextPage, ...activityFilter});
    setActivityLoadingMore(false);
    if (result.success) {
      setActivityTransactions(current => [
        ...(current || []),
        ...result.transactions.filter(transaction => !(current || []).some(item => item.id === transaction.id)),
      ]);
      setActivityPagination(result.pagination);
    } else {
      Alert.alert('Could not load more activity', getErrorMessage(result));
    }
  }, [activityFilter, activityLoadingMore, activityPagination, activityTransactions, hasMoreActivity, loadMoreTransactions, queryTransactions]);

  const renderTransaction = useCallback(({item}) => {
    const income = item.type?.toLowerCase() === 'income';
    return (
      <TouchableOpacity style={styles.card} onPress={() => openDetail(item)} activeOpacity={0.75}>
        <View style={[styles.iconBox, income ? styles.incomeIcon : styles.expenseIcon]}>
          <Icon name={income ? 'arrow-down-left' : 'arrow-up-right'} size={22} color={income ? COLORS.success : COLORS.danger} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle}>{item.category || 'Uncategorized'}</Text>
          <Text style={styles.cardSubtitle}>{item.source || 'Unknown source'} · {item.date}</Text>
          {item.notes ? <Text style={styles.cardNote} numberOfLines={1}>{item.notes}</Text> : null}
        </View>
        <Text style={[styles.amount, income ? styles.incomeAmount : styles.expenseAmount]}>
          {income ? '+' : '-'}₱{Number(item.amount || 0).toFixed(2)}
        </Text>
      </TouchableOpacity>
    );
  }, [openDetail]);

  const renderChips = (values, selected, onSelect) => (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.chips}
      keyboardShouldPersistTaps="handled">
      {values.map(value => (
        <TouchableOpacity key={value} style={[styles.chip, selected === value && styles.chipActive]} onPress={() => onSelect(value)}>
          <Text style={[styles.chipText, selected === value && styles.chipTextActive]}>{value}</Text>
        </TouchableOpacity>
      ))}
    </ScrollView>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Transactions"
        actionIcon="plus"
        actionLabel="Add transaction"
        onActionPress={openAdd}
      />

      <TransactionFilters
        sources={TRANSACTION_SOURCES}
        fetchCategories={fetchTransactionCategories}
        onApply={applyActivityFilters}
      />

      {isLoading && !transactions.length ? (
        <View style={styles.centerState}>
          <ActivityIndicator size="large" color={COLORS.accent} />
          <Text style={styles.stateText}>Loading transactions...</Text>
        </View>
      ) : (
        <TransactionActivityList
          transactions={displayedTransactions}
          loading={activityLoading}
          refreshing={refreshing}
          onRefresh={refresh}
          loadingMore={activityTransactions === null ? isLoadingMore : activityLoadingMore}
          hasMore={hasMoreActivity}
          onLoadMore={loadMoreActivity}
          onOpenDetail={openDetail}
          renderTransaction={renderTransaction}
          hasFilters={hasAppliedFilters}
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
              <Text style={styles.label}>Transaction type</Text>
              {renderChips(['Income', 'Expense'], form.type, value => setForm(current => ({...current, type: value, source: '', category: ''})))}

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
              <TouchableOpacity style={[styles.input, styles.dateButton]} onPress={openDatePicker}>
                <Text style={styles.dateValue}>{form.date}</Text>
                <Icon name="calendar-month-outline" size={20} color={COLORS.accentDark} />
              </TouchableOpacity>

              <Text style={styles.label}>Description (optional)</Text>
              <TextInput style={[styles.input, styles.notesInput]} value={form.notes} onChangeText={value => setField('notes', value)} placeholder="Add a short description" multiline />

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
              <View style={[styles.detailHero, selectedTransaction.type?.toLowerCase() === 'income' ? styles.detailHeroIncome : styles.detailHeroExpense]}>
                <View style={styles.detailHeroIcon}><Icon name={selectedTransaction.type?.toLowerCase() === 'income' ? 'arrow-down-left' : 'arrow-up-right'} size={23} color="#FFFFFF" /></View>
                <View style={styles.detailHeroText}>
                  <Text style={styles.detailCategory}>{selectedTransaction.category || 'Uncategorized'}</Text>
                  <Text style={styles.detailType}>{selectedTransaction.source || 'Unknown source'} · {selectedTransaction.date}</Text>
                </View>
              </View>
              <Text style={[
                styles.detailAmount,
                selectedTransaction.type?.toLowerCase() === 'income' ? styles.incomeAmount : styles.expenseAmount,
              ]}>
                {selectedTransaction.type?.toLowerCase() === 'income' ? '+' : '-'}₱{Number(selectedTransaction.amount || 0).toFixed(2)}
              </Text>
              {[
                ['Category', selectedTransaction.category],
                ['Source', selectedTransaction.source],
                ['Date', selectedTransaction.date],
                [selectedTransaction.entryMethod === 'order_system' ? 'Products ordered' : 'Description', selectedTransaction.notes || 'None'],
              ].map(([label, value]) => <View key={label} style={styles.detailRow}>
                <Text style={styles.detailLabel}>{label}</Text>
                <Text style={styles.detailValue}>{value}</Text>
              </View>)}

              {canEdit(selectedTransaction) ? (
                <View style={styles.transactionActions}>
                  <TouchableOpacity
                    style={[styles.editButton, styles.transactionActionButton]}
                    onPress={() => openEdit(selectedTransaction)}>
                    <Icon name="pencil-outline" size={18} color={COLORS.accentDark} />
                    <Text style={styles.editButtonText}>Edit</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.deleteButton, styles.transactionActionButton, deleting && styles.disabled]}
                    onPress={() => confirmDelete(selectedTransaction)}
                    disabled={deleting}>
                    {deleting
                      ? <ActivityIndicator size="small" color={COLORS.danger} />
                      : <Icon name="trash-can-outline" size={18} color={COLORS.danger} />}
                    <Text style={styles.deleteButtonText}>{deleting ? 'Deleting…' : 'Delete'}</Text>
                  </TouchableOpacity>
                </View>
              ) : selectedTransaction.entryMethod !== 'order_system' ? (
                <View style={styles.protectedNotice}>
                  <Icon name="lock-outline" size={18} color="#8A5A00" />
                  <Text style={styles.protectedText}>You can only edit or delete transactions that you recorded.</Text>
                </View>
              ) : null}
            </> : null}
          </View>
        </View>
      </Modal>

      <Modal
        visible={datePickerVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setDatePickerVisible(false)}>
        <View style={styles.centerOverlay}>
          <View style={styles.calendarCard}>
            <View style={styles.calendarHeader}>
              <TouchableOpacity
                style={styles.calendarNavButton}
                onPress={() => setCalendarMonth(current => new Date(current.getFullYear(), current.getMonth() - 1, 1))}
                accessibilityLabel="Previous month">
                <Icon name="chevron-left" size={23} color={COLORS.textDark} />
              </TouchableOpacity>
              <Text style={styles.calendarMonth}>
                {calendarMonth.toLocaleDateString(undefined, {month: 'long', year: 'numeric'})}
              </Text>
              <TouchableOpacity
                style={styles.calendarNavButton}
                onPress={() => setCalendarMonth(current => new Date(current.getFullYear(), current.getMonth() + 1, 1))}
                disabled={calendarMonth.getFullYear() === new Date().getFullYear() && calendarMonth.getMonth() >= new Date().getMonth()}
                accessibilityLabel="Next month">
                <Icon name="chevron-right" size={23} color={COLORS.textDark} />
              </TouchableOpacity>
            </View>
            <View style={styles.calendarWeek}>
              {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((day, index) => (
                <Text key={`${day}-${index}`} style={styles.calendarWeekday}>{day}</Text>
              ))}
            </View>
            {Array.from({length: 6}, (_, week) => (
              <View key={week} style={styles.calendarWeek}>
                {calendarDays.slice(week * 7, week * 7 + 7).map((day, index) => {
                  const dateValue = day
                    ? formatLocalDate(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day))
                    : null;
                  const isFuture = dateValue ? dateValue > localDate() : false;
                  const isSelected = dateValue === form.date;
                  return (
                    <TouchableOpacity
                      key={`${week}-${index}`}
                      style={[styles.calendarDay, isSelected && styles.calendarDaySelected]}
                      disabled={!day || isFuture}
                      onPress={() => chooseCalendarDay(day)}>
                      <Text style={[
                        styles.calendarDayText,
                        isSelected && styles.calendarDayTextSelected,
                        isFuture && styles.calendarDayTextDisabled,
                      ]}>{day || ''}</Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            ))}
            <TouchableOpacity style={styles.calendarCancel} onPress={() => setDatePickerVisible(false)}>
              <Text style={styles.calendarCancelText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  filters: {backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border, borderBottomWidth: 1, borderBottomColor: COLORS.border, paddingHorizontal: 20, paddingTop: 14, paddingBottom: 15},
  searchBox: {minHeight: 46, flexDirection: 'row', alignItems: 'center', gap: 9, borderWidth: 1, borderColor: COLORS.border, borderRadius: 13, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 13},
  searchInput: {flex: 1, fontSize: 14, color: COLORS.textDark, paddingVertical: 10},
  filterLabel: {fontSize: 11, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', letterSpacing: 0.5, marginTop: 13, marginBottom: 7},
  categoryHint: {fontSize: 12, color: COLORS.textMuted, marginTop: 11},
  categoryLoader: {alignSelf: 'flex-start', marginVertical: 10},
  filterActions: {flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 14},
  applyFilterButton: {minHeight: 42, flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7, backgroundColor: COLORS.accent, borderRadius: 11, paddingHorizontal: 14},
  applyFilterText: {fontSize: 13, fontWeight: '700', color: '#FFFFFF'},
  clearFilterButton: {minHeight: 42, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 16, backgroundColor: COLORS.surface},
  clearFilterText: {fontSize: 13, fontWeight: '700', color: COLORS.textGray},
  list: {paddingHorizontal: 20, paddingTop: 8, paddingBottom: 28},
  emptyList: {flexGrow: 1, paddingHorizontal: 20, paddingTop: 8, paddingBottom: 28},
  activityHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 10, marginBottom: 4},
  activityHeading: {flexDirection: 'row', alignItems: 'center', gap: 9},
  activityTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark},
  activityCount: {fontSize: 12, color: COLORS.textGray},
  card: {flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, padding: 14, marginBottom: 10, ...SHADOWS.card},
  iconBox: {width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center'},
  incomeIcon: {backgroundColor: '#E8F5E9'}, expenseIcon: {backgroundColor: '#FFEBEE'},
  cardBody: {flex: 1, marginHorizontal: 12}, cardTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark}, cardSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 3}, cardNote: {fontSize: 11, color: COLORS.textMuted, marginTop: 3},
  amount: {fontSize: 15, fontWeight: '700'}, incomeAmount: {color: COLORS.success}, expenseAmount: {color: COLORS.danger},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32}, emptyTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark, marginTop: 14}, stateText: {fontSize: 13, color: COLORS.textGray, textAlign: 'center', marginTop: 7}, footerLoader: {paddingVertical: 18},
  loadMoreButton: {marginTop: 16, paddingHorizontal: 16, paddingVertical: 10, borderRadius: 10, borderWidth: 1, borderColor: COLORS.accent, backgroundColor: COLORS.surface},
  loadMoreText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  overlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'flex-end'},
  centerOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'center', padding: 20},
  modalCard: {maxHeight: '92%', backgroundColor: COLORS.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 20},
  detailCard: {backgroundColor: COLORS.surface, borderRadius: 20, padding: 20},
  modalHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16}, modalTitle: {fontSize: 20, fontWeight: '700', color: COLORS.textDark}, modalSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 3},
  label: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', marginTop: 16, marginBottom: 7},
  amountInputWrap: {flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 12}, peso: {fontSize: 22, fontWeight: '700', color: COLORS.textDark, marginRight: 6}, amountInput: {flex: 1, fontSize: 22, color: COLORS.textDark, paddingVertical: 10},
  input: {borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, color: COLORS.textDark, paddingHorizontal: 12, paddingVertical: 11}, notesInput: {height: 80, textAlignVertical: 'top'},
  dateButton: {minHeight: 46, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  dateValue: {fontSize: 14, color: COLORS.textDark},
  calendarCard: {backgroundColor: COLORS.surface, width: '100%', maxWidth: 380, borderRadius: 20, padding: 18},
  calendarHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12},
  calendarNavButton: {width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: COLORS.surfaceMuted},
  calendarMonth: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
  calendarWeek: {flexDirection: 'row', justifyContent: 'space-around'},
  calendarWeekday: {width: '13.5%', textAlign: 'center', paddingVertical: 8, color: COLORS.textGray, fontSize: 12, fontWeight: '700'},
  calendarDay: {width: '13.5%', aspectRatio: 1, alignItems: 'center', justifyContent: 'center', borderRadius: 20, marginVertical: 2},
  calendarDaySelected: {backgroundColor: COLORS.accent},
  calendarDayText: {fontSize: 14, color: COLORS.textDark},
  calendarDayTextSelected: {fontWeight: '700', color: COLORS.textWhite},
  calendarDayTextDisabled: {color: COLORS.textMuted},
  calendarCancel: {alignSelf: 'flex-end', paddingVertical: 10, paddingHorizontal: 14, marginTop: 8},
  calendarCancelText: {color: COLORS.accentDark, fontSize: 14, fontWeight: '700'},
  chips: {flexDirection: 'row', gap: 8, paddingRight: 20}, chip: {paddingHorizontal: 13, paddingVertical: 8, borderRadius: RADIUS.pill, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border}, chipActive: {backgroundColor: COLORS.accent, borderColor: COLORS.accent}, chipText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray}, chipTextActive: {color: '#FFFFFF'},
  sourceHint: {flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: COLORS.surfaceMuted, borderRadius: 10, padding: 11, marginTop: 12}, sourceHintText: {flex: 1, fontSize: 12, lineHeight: 17, color: COLORS.textGray},
  saveButton: {minHeight: 50, backgroundColor: COLORS.accent, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginTop: 22, marginBottom: 8}, saveButtonText: {fontSize: 15, fontWeight: '700', color: '#FFFFFF'}, disabled: {opacity: 0.6},
  detailHero: {flexDirection: 'row', alignItems: 'center', borderRadius: 16, padding: 14, marginBottom: 14}, detailHeroIncome: {backgroundColor: '#E8F5E9'}, detailHeroExpense: {backgroundColor: '#FFEBEE'}, detailHeroIcon: {width: 42, height: 42, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.accent}, detailHeroText: {flex: 1, marginLeft: 11}, detailCategory: {fontSize: 16, fontWeight: '700', color: COLORS.textDark}, detailType: {fontSize: 12, color: COLORS.textGray, marginTop: 3}, detailAmount: {fontSize: 30, fontWeight: '700', marginBottom: 14}, detailRow: {flexDirection: 'row', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: COLORS.border, paddingVertical: 11}, detailLabel: {fontSize: 13, color: COLORS.textGray}, detailValue: {maxWidth: '65%', fontSize: 13, fontWeight: '600', color: COLORS.textDark, textAlign: 'right'},
  editButton: {minHeight: 48, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 20}, editButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.accentDark},
  transactionActions: {flexDirection: 'row', gap: 10, marginTop: 20},
  transactionActionButton: {flex: 1, marginTop: 0},
  deleteButton: {minHeight: 48, borderRadius: 12, backgroundColor: '#FFF1F0', borderWidth: 1, borderColor: '#F2C5C2', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8},
  deleteButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.danger},
  protectedNotice: {flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 12, backgroundColor: '#FFF8E1', padding: 13, marginTop: 20}, protectedText: {flex: 1, fontSize: 12, lineHeight: 18, color: '#8A5A00'},
});

export default TransactionsScreen;
