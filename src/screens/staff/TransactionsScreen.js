import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import GastoModalMascot from '../../components/staff/GastoModalMascot';
import {
  ActivityIndicator,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {AppAlert as Alert} from '../../services/AppDialogService';
import SkeletonPlaceholder, {SkeletonBlock} from '../../components/SkeletonPlaceholder';
import {useIsFocused} from '@react-navigation/native';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {useTransactions} from '../../context/TransactionContext';
import {getApiErrorMessage} from '../../services/apiErrors';
import api from '../../services/api';
import {COLORS, RADIUS, SHADOWS} from '../../theme';
import {transactionDisplayName} from '../../utils/transactionDisplay';
import {transactionCategoryIcon, transactionTypeIcon} from '../../utils/transactionIcons';
import {useStaffDay} from '../../utils/useStaffDay';
import WalletCaptureIssues from '../../components/staff/WalletCaptureIssues';

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
  return getApiErrorMessage(error);
};

const TransactionFilters = React.memo(({sources, fetchCategories, onApply}) => {
  const [search, setSearch] = useState('');
  const [source, setSource] = useState('All');
  const [category, setCategory] = useState('All');
  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const categoryRequestId = useRef(0);

  useEffect(() => {
    const timer = setTimeout(() => onApply({search: search.trim(), source, category}), 350);
    return () => clearTimeout(timer);
  }, [search, source, category, onApply]);

  const renderChips = (values, selected, onSelect, iconForValue) => (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.chips}
      keyboardShouldPersistTaps="handled">
      {values.map(value => (
        <TouchableOpacity key={value} style={[styles.chip, selected === value && styles.chipActive]} onPress={() => onSelect(value)} accessibilityRole="button" accessibilityState={{selected: selected === value}}>
          {iconForValue ? <Icon name={iconForValue(value)} size={16} color={selected === value ? COLORS.textWhite : COLORS.accentDark} /> : null}
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

  const clear = () => {
    categoryRequestId.current += 1;
    setSearch('');
    setSource('All');
    setCategory('All');
    setCategories([]);
    setCategoriesLoading(false);
  };

  const showClear = Boolean(search.trim() || source !== 'All' || category !== 'All');

  return (
    <View style={styles.filters}>
      <View style={styles.searchBox}>
        <Icon name="magnify" size={21} color={COLORS.textGray} />
        <TextInput
          style={styles.searchInput}
          value={search}
          onChangeText={setSearch}
          placeholder="Search transactions..."
          placeholderTextColor={COLORS.placeholder}
          accessibilityLabel="Search transactions"
        />
        {search ? <TouchableOpacity onPress={() => setSearch('')} accessibilityLabel="Clear transaction search"><Icon name="close-circle" size={19} color={COLORS.textMuted} /></TouchableOpacity> : null}
      </View>
      <Text style={styles.filterLabel}>Source</Text>
      {renderChips(['All', ...sources], source, selectSource)}
      {source !== 'All' ? <>
        <Text style={styles.filterLabel}>Category</Text>
        {categoriesLoading
          ? <SkeletonPlaceholder style={styles.categoryLoaderSkeleton} accessibilityLabel="Loading transaction categories"><SkeletonBlock width={118} height={12} /></SkeletonPlaceholder>
          : renderChips(['All', ...categories], category, setCategory, value => value === 'All' ? 'view-grid-outline' : transactionCategoryIcon(value))}
      </> : <Text style={styles.categoryHint}>Choose a source to view category filters.</Text>}
      <View style={styles.filterActions}>
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
          <Icon name={transactionCategoryIcon(item.category)} size={22} color={income ? COLORS.success : COLORS.danger} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle} numberOfLines={2}>{transactionDisplayName(item)}</Text>
          <View style={styles.cardNoteRow}>
            <Icon name={transactionTypeIcon(item.type)} size={13} color={income ? COLORS.success : COLORS.danger} />
            <Text style={styles.cardNote}>{item.entryMethod === 'order_system' ? 'Order' : income ? 'Income' : 'Expense'}</Text>
          </View>
          <Text style={styles.cardSubtitle}>{item.source || 'Unknown source'} · {item.date}</Text>
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
      keyExtractor={item => String(item.client_id || item.id)}
      renderItem={renderTransactionProp || defaultRenderTransaction}
      ListHeaderComponent={<View>
        <View style={styles.activityHeader}>
          <View style={styles.activityHeading}>
            <Text style={styles.activityTitle}>Activity</Text>
            {loading ? <SkeletonPlaceholder style={styles.activityCountSkeleton} accessibilityLabel="Loading activity count"><SkeletonBlock width={52} height={12} /></SkeletonPlaceholder> : null}
          </View>
          {!loading ? <Text style={styles.activityCount}>{transactions.length} shown</Text> : null}
        </View>
      </View>}
      contentContainerStyle={transactions.length ? styles.list : styles.emptyList}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.accent]} />}
      ListEmptyComponent={loading ? <TransactionActivitySkeleton /> : <View style={styles.centerState}>
            <Image source={require('../../../assets/mascot/gasto-empty.png')} style={styles.emptyMascot} resizeMode="contain" accessibilityLabel="Gasto beside an empty tray" />
            <Text style={styles.emptyTitle}>{hasFilters ? 'No matching transactions' : 'No transactions today'}</Text>
            <Text style={styles.stateText}>{hasFilters ? 'Try changing your search or filters.' : 'Transactions recorded today will appear here. Tap + to add one.'}</Text>
            {!transactions.length && hasMore && !loadingMore ? (
              <TouchableOpacity style={styles.loadMoreButton} onPress={onLoadMore} accessibilityRole="button">
                <Text style={styles.loadMoreText}>Load more activity</Text>
              </TouchableOpacity>
            ) : null}
      </View>}
      ListFooterComponent={loadingMore ? <ActivityIndicator style={styles.footerLoader} color={COLORS.accent} /> : null}
      onEndReached={hasMore && transactions.length ? onLoadMore : undefined}
      onEndReachedThreshold={0.35}
    />
  );
});

const TransactionActivitySkeleton = () => (
  <SkeletonPlaceholder style={styles.activitySkeletonList} accessibilityLabel="Loading transactions">
    {[0, 1, 2, 3].map(item => (
      <View key={item} style={styles.skeletonTransactionCard}>
        <SkeletonBlock width={42} height={42} radius={13} />
        <View style={styles.skeletonTransactionText}>
          <SkeletonBlock width="78%" height={13} />
          <SkeletonBlock width="47%" height={11} style={styles.skeletonTransactionMetaSpacer} />
          <SkeletonBlock width="60%" height={10} style={styles.skeletonTransactionDateSpacer} />
        </View>
        <SkeletonBlock width={62} height={14} />
      </View>
    ))}
  </SkeletonPlaceholder>
);

const TransactionsScreen = ({route}) => {
  const isFocused = useIsFocused();
  const {user} = useAuth();
  const staffDay = useStaffDay();
  const {
    queryTransactions,
    fetchTransactionCategories,
    addTransaction,
    updateTransaction,
    deleteTransaction,
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
    TRANSACTION_SOURCES,
  } = useTransactions();

  const [refreshing, setRefreshing] = useState(false);
  const [activityFilter, setActivityFilter] = useState({search: '', source: 'All', category: 'All'});
  const [activityTransactions, setActivityTransactions] = useState([]);
  const [activityPagination, setActivityPagination] = useState(null);
  const [activityLoading, setActivityLoading] = useState(true);
  const [activityLoadingMore, setActivityLoadingMore] = useState(false);
  const [editorVisible, setEditorVisible] = useState(false);
  const [detailVisible, setDetailVisible] = useState(false);
  const [editingTransaction, setEditingTransaction] = useState(null);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [suggestionError, setSuggestionError] = useState(null);
  const [manualCategoryFallback, setManualCategoryFallback] = useState(false);
  const [saving, setSaving] = useState(false);
  const savingRef = useRef(false);
  const [categorizing, setCategorizing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [datePickerVisible, setDatePickerVisible] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => {
    const today = new Date();
    return new Date(today.getFullYear(), today.getMonth(), 1);
  });
  const activityRequestId = useRef(0);
  const wasFocused = useRef(isFocused);
  const refreshOnFocus = useRef(null);

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

  const useAutomaticCategory = !editingTransaction && form.type === 'Expense'
    && Boolean(form.notes.trim()) && !manualCategoryFallback;
  const hasAppliedFilters = Boolean(activityFilter.search || activityFilter.source !== 'All' || activityFilter.category !== 'All');
  const hasMoreActivity = Boolean(activityPagination)
    && Number(activityPagination.currentPage || 0) < Number(activityPagination.lastPage || 1);

  const setField = (field, value) => setForm(current => ({...current, [field]: value}));
  const setExpenseName = value => {
    setManualCategoryFallback(false);
    setSuggestionError(null);
    setForm(current => ({...current, notes: value, category: ''}));
  };
  const selectCategory = value => setField('category', value);

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
    setManualCategoryFallback(false);
    setSuggestionError(null);
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
    setManualCategoryFallback(false);
    setSuggestionError(null);
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
            await refreshFilteredActivity();
            setDetailVisible(false);
            setSelectedTransaction(null);
          },
        },
      ],
    );
  };

  const save = async () => {
    if (savingRef.current) return;
    if (!form.notes.trim() && (form.type !== 'Expense' || editingTransaction)) {
      Alert.alert('Name required', `Enter a name for this ${form.type.toLowerCase()} transaction.`);
      return;
    }
    const amount = Number(form.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      Alert.alert('Invalid amount', 'Enter an amount greater than zero.');
      return;
    }
    if (!form.source) {
      Alert.alert('Source required', 'Choose a payment source before saving.');
      return;
    }
    if (!useAutomaticCategory && !form.category) {
      Alert.alert('Category required', 'Choose a transaction category.');
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date) || form.date > localDate()) {
      Alert.alert('Invalid date', 'Use YYYY-MM-DD and do not enter a future date.');
      return;
    }

    savingRef.current = true;
    setSaving(true);
    try {
      let category = form.category;
      if (useAutomaticCategory && !categories.includes(category)) {
        setCategorizing(true);
        setSuggestionError(null);
        try {
          const response = await api.post('/ai/transaction-category', {
            type: 'expense', source: form.source,
            description: form.notes.trim().length < 4 ? `Expense item: ${form.notes.trim()}` : form.notes.trim(),
          });
          category = categories.find(item => item.toLowerCase() === String(response.data?.category || '').toLowerCase());
          if (!category) {
            setManualCategoryFallback(true);
            setSuggestionError('Gasto AI could not match this name to a business category. Choose a category below, then save again.');
            return;
          }
          setForm(current => ({...current, category}));
        } catch (error) {
          setManualCategoryFallback(true);
          setSuggestionError(getApiErrorMessage(error, 'Gasto AI is unavailable. Choose a category below, then save again.'));
          return;
        } finally {
          setCategorizing(false);
        }
      }

      const payload = {
        ...form,
        category,
        notes: form.notes.trim() || category,
        amount,
        entryMethod: editingTransaction?.entryMethod || 'manual',
      };
      const result = editingTransaction
        ? await updateTransaction({...editingTransaction, ...payload})
        : await addTransaction(payload);
      if (!result.success) {
        Alert.alert('Could not save transaction', getErrorMessage(result));
        return;
      }
      if (result.queued) Alert.alert('Saved on this device', 'This transaction is waiting to sync. It will appear in Transactions after the server accepts it.');
      setEditorVisible(false);
      setEditingTransaction(null);
      setForm(emptyForm(form.type));
      await refreshFilteredActivity();
    } finally {
      savingRef.current = false;
      setSaving(false);
    }
  };

  const refreshFilteredActivity = useCallback(async () => {
    const requestId = ++activityRequestId.current;
    const result = await queryTransactions({page: 1, ...activityFilter, from: staffDay, to: staffDay});
    if (requestId !== activityRequestId.current) return;
    setActivityLoading(false);
    if (result.success) {
      setActivityTransactions(result.transactions);
      setActivityPagination(result.pagination);
    } else {
      Alert.alert('Could not refresh transactions', getErrorMessage(result));
    }
  }, [activityFilter, queryTransactions, staffDay]);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await refreshFilteredActivity();
    } finally {
      setRefreshing(false);
    }
  }, [refreshFilteredActivity]);

  const applyActivityFilters = useCallback(async nextFilter => {
    const normalizedFilter = {
      search: nextFilter.search?.trim() || '',
      source: nextFilter.source || 'All',
      category: nextFilter.category || 'All',
    };
    const requestId = ++activityRequestId.current;
    setActivityLoading(true);
    const result = await queryTransactions({page: 1, ...normalizedFilter, from: staffDay, to: staffDay});
    if (requestId !== activityRequestId.current) return;
    setActivityLoading(false);
    if (!result.success) {
      Alert.alert('Could not filter transactions', getErrorMessage(result));
      return;
    }

    setActivityFilter(normalizedFilter);
    setActivityTransactions(result.transactions);
    setActivityPagination(result.pagination);
  }, [queryTransactions, staffDay]);

  const lastScope = useRef(null);
  useEffect(() => {
    const scope = staffDay;
    if (lastScope.current === scope) return;
    lastScope.current = scope;
    setActivityTransactions([]);
    applyActivityFilters(activityFilter);
  }, [activityFilter, applyActivityFilters, staffDay]);

  const loadMoreActivity = useCallback(async () => {
    if (activityLoadingMore || !hasMoreActivity) return;

    const nextPage = Number(activityPagination?.currentPage || 1) + 1;
    const requestId = activityRequestId.current;
    setActivityLoadingMore(true);
    const result = await queryTransactions({page: nextPage, ...activityFilter, from: staffDay, to: staffDay});
    setActivityLoadingMore(false);
    if (requestId !== activityRequestId.current) return;
    if (result.success) {
      setActivityTransactions(current => [
        ...current,
        ...result.transactions.filter(transaction => !current.some(item => item.id === transaction.id)),
      ]);
      setActivityPagination(result.pagination);
    } else {
      Alert.alert('Could not load more activity', getErrorMessage(result));
    }
  }, [activityFilter, activityLoadingMore, activityPagination, hasMoreActivity, queryTransactions, staffDay]);

  refreshOnFocus.current = refreshFilteredActivity;

  useEffect(() => {
    if (!isFocused) {
      wasFocused.current = false;
      return;
    }
    if (wasFocused.current) return;

    wasFocused.current = true;
    refreshOnFocus.current?.();
  }, [isFocused]);

  const renderTransaction = useCallback(({item}) => {
    const income = item.type?.toLowerCase() === 'income';
    return (
      <TouchableOpacity style={styles.card} onPress={() => openDetail(item)} activeOpacity={0.75}>
        <View style={[styles.iconBox, income ? styles.incomeIcon : styles.expenseIcon]}>
          <Icon name={transactionCategoryIcon(item.category)} size={22} color={income ? COLORS.success : COLORS.danger} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle} numberOfLines={2}>{transactionDisplayName(item)}</Text>
          <View style={styles.cardNoteRow}>
            <Icon name={transactionTypeIcon(item.type)} size={13} color={income ? COLORS.success : COLORS.danger} />
            <Text style={styles.cardNote}>{item.entryMethod === 'order_system' ? 'Order' : income ? 'Income' : 'Expense'}</Text>
          </View>
          <Text style={styles.cardSubtitle}>{item.source || 'Unknown source'} · {item.date}</Text>
        </View>
        <Text style={[styles.amount, income ? styles.incomeAmount : styles.expenseAmount]}>
          {income ? '+' : '-'}₱{Number(item.amount || 0).toFixed(2)}
        </Text>
      </TouchableOpacity>
    );
  }, [openDetail]);

  const renderChips = (values, selected, onSelect, iconForValue) => (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.chips}
      keyboardShouldPersistTaps="handled">
      {values.map(value => (
        <TouchableOpacity key={value} style={[styles.chip, selected === value && styles.chipActive]} onPress={() => onSelect(value)} accessibilityRole="button" accessibilityState={{selected: selected === value}}>
          {iconForValue ? <Icon name={iconForValue(value)} size={16} color={selected === value ? COLORS.textWhite : COLORS.accentDark} /> : null}
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

      <WalletCaptureIssues />

      <TransactionFilters
        sources={TRANSACTION_SOURCES}
        fetchCategories={fetchTransactionCategories}
        onApply={applyActivityFilters}
      />

      <TransactionActivityList
        transactions={activityTransactions}
        loading={activityLoading}
        refreshing={refreshing}
        onRefresh={refresh}
        loadingMore={activityLoadingMore}
        hasMore={hasMoreActivity}
        onLoadMore={loadMoreActivity}
        onOpenDetail={openDetail}
        renderTransaction={renderTransaction}
        hasFilters={hasAppliedFilters}
      />

      <Modal visible={editorVisible} transparent animationType="slide" onRequestClose={() => { if (!savingRef.current) setEditorVisible(false); }}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.overlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <GastoModalMascot variant="receipt" />
              <View style={styles.formHeaderCopy}>
                <Text style={styles.modalTitle}>{editingTransaction ? 'Edit transaction' : form.type === 'Income' ? 'Add income' : 'Add expense'}</Text>
                <Text style={styles.modalSubtitle}>{form.type === 'Income' ? 'Income' : 'Expense'} transaction</Text>
              </View>
              <TouchableOpacity style={styles.formCloseButton} accessibilityRole="button" onPress={() => setEditorVisible(false)} disabled={saving} accessibilityLabel="Close transaction form">
                <Icon name="close" size={24} color={COLORS.textGray} />
              </TouchableOpacity>
            </View>
            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              <View pointerEvents={saving ? 'none' : 'auto'}>
              <Text style={styles.label}>Transaction type</Text>
              {renderChips(['Income', 'Expense'], form.type, value => {
                setManualCategoryFallback(false);
                setSuggestionError(null);
                setForm(current => ({...current, type: value, source: '', category: ''}));
              }, transactionTypeIcon)}

              <Text style={styles.label}>Source</Text>
              {renderChips(sources, form.source, value => {
                setSuggestionError(null);
                setForm(current => ({...current, source: value, category: useAutomaticCategory ? '' : current.category}));
              })}

              <Text style={styles.label}>{form.type === 'Expense' ? 'Expense or product name (optional)' : 'Income name'}</Text>
              <TextInput style={[styles.input, styles.notesInput]} value={form.notes} onChangeText={form.type === 'Expense' ? setExpenseName : value => setField('notes', value)} placeholder={form.type === 'Expense' ? 'Enter a name for AI, or leave blank to choose a category' : 'e.g. catering payment'} placeholderTextColor={COLORS.placeholder} maxLength={500} multiline />

              <Text style={styles.label}>Category</Text>
              {useAutomaticCategory ? (
                <View style={styles.suggestionButton}>
                  <Icon name="creation" size={17} color={COLORS.accentDark} />
                  <Text style={styles.suggestionText}>{categorizing ? 'Choosing a category...' : form.category ? `Category: ${form.category}` : 'Gasto AI will choose a category when you save.'}</Text>
                </View>
              ) : renderChips(categories, form.category, selectCategory, transactionCategoryIcon)}
              {!form.source ? <View style={styles.sourceHint}>
                <Icon name="information-outline" size={17} color={COLORS.accentDark} />
                <Text style={styles.sourceHintText}>Choose a payment source before saving.</Text>
              </View> : null}
              {suggestionError ? <Text accessibilityRole="alert" style={styles.suggestionError}>{suggestionError}</Text> : null}

              <Text style={styles.label}>Amount</Text>
              <View style={styles.amountInputWrap}>
                <Text style={styles.peso}>₱</Text>
                <TextInput style={styles.amountInput} value={form.amount} onChangeText={value => setField('amount', value)} keyboardType="decimal-pad" placeholder="0.00" placeholderTextColor={COLORS.placeholder} />
              </View>

              <Text style={styles.label}>Date</Text>
              <TouchableOpacity style={[styles.input, styles.dateButton]} onPress={openDatePicker}>
                <Text style={styles.dateValue}>{form.date}</Text>
                <Icon name="calendar-month-outline" size={20} color={COLORS.accentDark} />
              </TouchableOpacity>
              </View>

              <TouchableOpacity style={[styles.saveButton, saving && styles.disabled]} onPress={save} disabled={saving}>
                {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.saveButtonText}>{editingTransaction ? 'Save changes' : 'Save transaction'}</Text>}
              </TouchableOpacity>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>

      <Modal visible={detailVisible} transparent animationType="slide" onRequestClose={() => setDetailVisible(false)}>
        <View style={styles.centerOverlay}>
          <View style={styles.detailCard}>
            <View style={styles.modalHeader}>
              <GastoModalMascot variant="receipt" />
              <View style={styles.formHeaderCopy}>
                <Text style={styles.modalTitle}>Transaction details</Text>
              </View>
              <TouchableOpacity style={styles.formCloseButton} onPress={() => setDetailVisible(false)} accessibilityRole="button" accessibilityLabel="Close transaction details">
                <Icon name="close" size={24} color={COLORS.textGray} />
              </TouchableOpacity>
            </View>
            {selectedTransaction ? <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.detailScrollContent}>
              <View style={[styles.detailTypeBadge, selectedTransaction.type?.toLowerCase() === 'income' ? styles.detailBadgeIncome : styles.detailBadgeExpense]}>
                <Icon name={transactionTypeIcon(selectedTransaction.type)} size={18} color={selectedTransaction.type?.toLowerCase() === 'income' ? COLORS.success : COLORS.danger} />
                <Text style={[styles.detailBadgeText, selectedTransaction.type?.toLowerCase() === 'income' ? styles.incomeAmount : styles.expenseAmount]}>
                  {selectedTransaction.type?.toLowerCase() === 'income' ? 'Income' : 'Expense'} transaction
                </Text>
              </View>

              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Transaction</Text>
                <View style={styles.detailNameRow}>
                  <Icon name={transactionCategoryIcon(selectedTransaction.category)} size={22} color={selectedTransaction.type?.toLowerCase() === 'income' ? COLORS.success : COLORS.danger} />
                  <Text style={styles.detailName}>{transactionDisplayName(selectedTransaction)}</Text>
                </View>
              </View>

              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Details</Text>
                {[
                  ['Category', selectedTransaction.category],
                  ['Source', selectedTransaction.source],
                  ['Date', selectedTransaction.date],
                ].map(([label, value]) => <View key={label} style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{label}</Text>
                  <Text style={styles.detailValue}>{value || '—'}</Text>
                </View>)}
              </View>

              <View style={styles.detailTotalBox}>
                <Text style={styles.detailTotalLabel}>Amount</Text>
                <Text style={[styles.detailAmount, selectedTransaction.type?.toLowerCase() === 'income' ? styles.incomeAmount : styles.expenseAmount]}>
                  {selectedTransaction.type?.toLowerCase() === 'income' ? '+' : '-'}₱{Number(selectedTransaction.amount || 0).toFixed(2)}
                </Text>
              </View>

              {canEdit(selectedTransaction) ? (
                <View style={styles.transactionActions}>
                  <TouchableOpacity
                    style={[styles.editButton, styles.transactionActionButton]}
                    accessibilityRole="button"
                    accessibilityLabel="Edit transaction"
                    onPress={() => openEdit(selectedTransaction)}>
                    <Icon name="pencil-outline" size={18} color={COLORS.accentDark} />
                    <Text style={styles.editButtonText}>Edit</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.deleteButton, styles.transactionActionButton, deleting && styles.disabled]}
                    onPress={() => confirmDelete(selectedTransaction)}
                    accessibilityRole="button"
                    accessibilityLabel="Delete transaction"
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
            </ScrollView> : null}
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
  formHeaderCopy: {flex: 1, minWidth: 0, marginRight: 8},
  formCloseButton: {width: 48, height: 48, borderRadius: 24, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.surfaceMuted},
  container: {flex: 1, backgroundColor: COLORS.background},
  filters: {backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border, borderBottomWidth: 1, borderBottomColor: COLORS.border, paddingHorizontal: 16, paddingTop: 10, paddingBottom: 11},
  searchBox: {minHeight: 46, flexDirection: 'row', alignItems: 'center', gap: 9, borderWidth: 1, borderColor: COLORS.border, borderRadius: 13, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 13},
  searchInput: {flex: 1, fontSize: 14, color: COLORS.textDark, paddingVertical: 10},
  filterLabel: {fontSize: 11, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', letterSpacing: 0.5, marginTop: 10, marginBottom: 5},
  categoryHint: {fontSize: 12, color: COLORS.textMuted, marginTop: 8},
  categoryLoader: {alignSelf: 'flex-start', marginVertical: 7},
  categoryLoaderSkeleton: {alignSelf: 'flex-start', width: 118, height: 12, borderRadius: 6, marginVertical: 10},
  filterActions: {flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10},
  clearFilterButton: {minHeight: 42, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 16, backgroundColor: COLORS.surface},
  clearFilterText: {fontSize: 13, fontWeight: '700', color: COLORS.textGray},
  list: {paddingHorizontal: 16, paddingTop: 6, paddingBottom: 24},
  emptyList: {flexGrow: 1, paddingHorizontal: 16, paddingTop: 6, paddingBottom: 24},
  activityCountSkeleton: {width: 52, height: 12, borderRadius: 6},
  activitySkeletonList: {width: '100%', gap: 9},
  skeletonTransactionCard: {minHeight: 70, flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12, borderRadius: 15, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonTransactionText: {flex: 1},
  skeletonTransactionMetaSpacer: {marginTop: 8},
  skeletonTransactionDateSpacer: {marginTop: 6},
  activityHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 7, marginBottom: 3},
  activityHeading: {flexDirection: 'row', alignItems: 'center', gap: 9},
  activityTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark},
  activityCount: {fontSize: 12, color: COLORS.textGray},
  card: {flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, padding: 12, marginBottom: 8, ...SHADOWS.card},
  iconBox: {width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center'},
  incomeIcon: {backgroundColor: '#E8F5E9'}, expenseIcon: {backgroundColor: '#FFEBEE'},
  cardBody: {flex: 1, marginHorizontal: 10}, cardTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark}, cardSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 2}, cardNoteRow: {flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 2}, cardNote: {fontSize: 11, color: COLORS.textMuted},
  amount: {fontSize: 15, fontWeight: '700'}, incomeAmount: {color: COLORS.success}, expenseAmount: {color: COLORS.danger},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24}, emptyTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark, marginTop: 10}, stateText: {fontSize: 13, color: COLORS.textGray, textAlign: 'center', marginTop: 5}, footerLoader: {paddingVertical: 14},
  emptyMascot: {width: 120, height: 120},
  loadMoreButton: {marginTop: 12, paddingHorizontal: 16, paddingVertical: 9, borderRadius: 10, borderWidth: 1, borderColor: COLORS.accent, backgroundColor: COLORS.surface},
  loadMoreText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  overlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'flex-end'},
  centerOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'center', padding: 20},
  modalCard: {maxHeight: '92%', backgroundColor: COLORS.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 16},
  detailCard: {width: '100%', maxHeight: '92%', flexShrink: 1, backgroundColor: COLORS.surface, borderRadius: 24, padding: 16},
  detailScrollContent: {paddingBottom: 4},
  modalHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12}, modalTitle: {fontSize: 20, fontWeight: '700', color: COLORS.textDark}, modalSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 2},
  label: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', marginTop: 12, marginBottom: 5},
  amountInputWrap: {flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 12}, peso: {fontSize: 22, fontWeight: '700', color: COLORS.textDark, marginRight: 6}, amountInput: {flex: 1, fontSize: 22, color: COLORS.textDark, paddingVertical: 10},
  input: {borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, color: COLORS.textDark, paddingHorizontal: 12, paddingVertical: 11}, notesInput: {height: 80, textAlignVertical: 'top'},
  dateButton: {minHeight: 46, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  dateValue: {fontSize: 14, color: COLORS.textDark},
  calendarCard: {backgroundColor: COLORS.surface, width: '100%', maxWidth: 380, borderRadius: 20, padding: 16},
  calendarHeader: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8},
  calendarNavButton: {width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: COLORS.surfaceMuted},
  calendarMonth: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
  calendarWeek: {flexDirection: 'row', justifyContent: 'space-around'},
  calendarWeekday: {width: '13.5%', textAlign: 'center', paddingVertical: 8, color: COLORS.textGray, fontSize: 12, fontWeight: '700'},
  calendarDay: {width: '13.5%', aspectRatio: 1, alignItems: 'center', justifyContent: 'center', borderRadius: 20, marginVertical: 2},
  calendarDaySelected: {backgroundColor: COLORS.accentDark},
  calendarDayText: {fontSize: 14, color: COLORS.textDark},
  calendarDayTextSelected: {fontWeight: '700', color: COLORS.textWhite},
  calendarDayTextDisabled: {color: COLORS.textMuted},
  calendarCancel: {alignSelf: 'flex-end', paddingVertical: 10, paddingHorizontal: 14, marginTop: 8},
  calendarCancelText: {color: COLORS.accentDark, fontSize: 14, fontWeight: '700'},
  chips: {flexDirection: 'row', gap: 8, paddingRight: 20}, chip: {minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, paddingHorizontal: 13, paddingVertical: 8, borderRadius: RADIUS.pill, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border}, chipActive: {backgroundColor: COLORS.accentDark, borderColor: COLORS.accentDark}, chipText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray}, chipTextActive: {color: '#FFFFFF'},
  sourceHint: {flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: COLORS.surfaceMuted, borderRadius: 10, padding: 10, marginTop: 8}, sourceHintText: {flex: 1, fontSize: 12, lineHeight: 17, color: COLORS.textGray},
  suggestionButton: {flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: '#E8F7F1', borderRadius: 10, padding: 10, marginBottom: 9},
  suggestionText: {flex: 1, fontSize: 12, lineHeight: 18, color: COLORS.accentDark},
  suggestionError: {fontSize: 12, lineHeight: 18, color: COLORS.danger, marginBottom: 9},
  saveButton: {minHeight: 50, backgroundColor: COLORS.accentDark, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginTop: 14, marginBottom: 6}, saveButtonText: {fontSize: 15, fontWeight: '700', color: '#FFFFFF'}, disabled: {opacity: 0.6},
  detailTypeBadge: {minHeight: 42, borderRadius: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 16},
  detailBadgeIncome: {backgroundColor: COLORS.tagIncBg},
  detailBadgeExpense: {backgroundColor: COLORS.dangerSurface},
  detailBadgeText: {fontSize: 14, fontWeight: '700'},
  detailSection: {marginBottom: 16},
  detailSectionTitle: {fontSize: 14, fontWeight: '700', color: COLORS.textDark, marginBottom: 9},
  detailNameRow: {flexDirection: 'row', alignItems: 'center', gap: 10},
  detailName: {fontSize: 16, fontWeight: '600', color: COLORS.textDark, paddingVertical: 4},
  detailTotalBox: {backgroundColor: COLORS.miniCardBg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border, padding: 16, marginTop: 2},
  detailTotalLabel: {fontSize: 13, color: COLORS.textGray, marginBottom: 4},
  detailAmount: {fontSize: 28, fontWeight: '700'},
  detailRow: {flexDirection: 'row', justifyContent: 'space-between', gap: 12, borderBottomWidth: 1, borderBottomColor: COLORS.border, paddingVertical: 11},
  detailLabel: {fontSize: 13, color: COLORS.textGray},
  detailValue: {maxWidth: '65%', fontSize: 13, fontWeight: '600', color: COLORS.textDark, textAlign: 'right'},
  editButton: {minHeight: 48, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 14}, editButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.accentDark},
  transactionActions: {flexDirection: 'row', gap: 10, marginTop: 14},
  transactionActionButton: {flex: 1, marginTop: 0},
  deleteButton: {minHeight: 48, borderRadius: 12, backgroundColor: '#FFF1F0', borderWidth: 1, borderColor: '#F2C5C2', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8},
  deleteButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.danger},
  protectedNotice: {flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 12, backgroundColor: '#FFF8E1', padding: 11, marginTop: 14}, protectedText: {flex: 1, fontSize: 12, lineHeight: 18, color: '#8A5A00'},
});

export default TransactionsScreen;
