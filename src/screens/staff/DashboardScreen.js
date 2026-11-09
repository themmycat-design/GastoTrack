import React, { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  Animated,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  Image,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useFocusEffect, useIsFocused} from '@react-navigation/native';
import { useTransactions } from '../../context/TransactionContext';
import { useProducts } from '../../context/ProductContext';
import { useAuth } from '../../context/AuthContext';
import { COLORS } from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import ApiErrorBanner from '../../components/ApiErrorBanner';
import SkeletonPlaceholder, {SkeletonBlock} from '../../components/SkeletonPlaceholder';
import {transactionDisplayName} from '../../utils/transactionDisplay';
import {transactionCategoryIcon} from '../../utils/transactionIcons';
import {useStaffDay} from '../../utils/useStaffDay';
import api from '../../services/api';
import {getApiErrorMessage} from '../../services/apiErrors';
import {productCategoryTabs, productsInCategory} from '../../constants/productCategories';
import {getProfilePhotoSource} from '../../utils/profilePhoto';
import {getStaffOfflineActions, getStaffSnapshot, saveStaffSnapshot, subscribeStaffOffline, syncStaffOfflineActions} from '../../services/staffOffline';
import {pendingIncomeForDay} from '../../utils/pendingIncome';
import useReduceMotion from '../../hooks/useReduceMotion';

const IncomePeekCat = () => {
  const isFocused = useIsFocused();
  const reduceMotion = useReduceMotion();
  const blink = React.useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (!isFocused || reduceMotion) {
      blink.stopAnimation();
      blink.setValue(0);
      return undefined;
    }

    const animation = Animated.loop(Animated.sequence([
      Animated.delay(4700),
      Animated.timing(blink, {toValue: 1, duration: 80, useNativeDriver: true}),
      Animated.delay(140),
      Animated.timing(blink, {toValue: 0, duration: 80, useNativeDriver: true}),
    ]));
    animation.start();
    return () => animation.stop();
  }, [blink, isFocused, reduceMotion]);

  return (
    <View pointerEvents="none" accessibilityElementsHidden importantForAccessibility="no-hide-descendants" style={styles.incomeMascotPeek}>
      <Image source={require('../../../assets/mascot/gasto-header-body.png')} style={styles.incomeMascotImage} resizeMode="contain" />
      <Animated.Image source={require('../../../assets/mascot/gasto-header-blink.png')} style={[styles.incomeMascotImage, styles.incomeMascotBlink, {opacity: blink}]} resizeMode="contain" />
    </View>
  );
};

const DashboardScreen = ({ navigation }) => {
  const {user, userToken, business, sessionStorageWarning} = useAuth();
  const offlineAccount = useMemo(() => user?.id && (user?.business_id || business?.id)
    ? {userId: user.id, businessId: user.business_id || business.id} : null,
  [user?.id, user?.business_id, business?.id]);
  const { transactions, isLoading: transactionsLoading, loadError: transactionError, fetchTransactions, PRODUCT_CATEGORIES } = useTransactions();
  const { products, isLoading: productsLoading, loadError: productError, fetchProducts } = useProducts();
  const [refreshing, setRefreshing] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [todayIncome, setTodayIncome] = useState(null);
  const [todayIncomeCount, setTodayIncomeCount] = useState(null);
  const [pendingIncomeCount, setPendingIncomeCount] = useState(0);
  const [incomeLoading, setIncomeLoading] = useState(true);
  const [incomeError, setIncomeError] = useState(null);
  const incomeRequestId = React.useRef(0);
  const day = useStaffDay();

  const fetchDailyIncome = React.useCallback(async () => {
    const requestId = ++incomeRequestId.current;
    setIncomeLoading(true);
    const pending = async () => {
      try {return pendingIncomeForDay(offlineAccount ? await getStaffOfflineActions(offlineAccount) : [], day);}
      catch {return {amount: 0, count: 0};}
    };
    try {
      const response = await api.get('/transactions/summary', {params: {from: day, to: day}});
      if (requestId !== incomeRequestId.current) return;
      const summary = {day, income: Number(response.data?.income || 0), count: Number(response.data?.income_count || 0)};
      const queued = await pending();
      if (requestId !== incomeRequestId.current) return;
      setTodayIncome(summary.income + queued.amount);
      setTodayIncomeCount(summary.count + queued.count);
      setPendingIncomeCount(queued.count);
      if (offlineAccount) saveStaffSnapshot(offlineAccount, 'daily_income', summary).catch(() => {});
      setIncomeError(null);
    } catch (error) {
      if (requestId !== incomeRequestId.current) return;
      let cached = null;
      if (offlineAccount) {
        try {cached = await getStaffSnapshot(offlineAccount, 'daily_income');} catch {}
      }
      if (cached?.day === day) {
        const queued = await pending();
        if (requestId !== incomeRequestId.current) return;
        setTodayIncome(Number(cached.income) + queued.amount);
        setTodayIncomeCount(Number(cached.count) + queued.count);
        setPendingIncomeCount(queued.count);
      } else {
        setTodayIncome(null);
        setTodayIncomeCount(null);
        setPendingIncomeCount(0);
      }
      setIncomeError(getApiErrorMessage(error));
    } finally {
      if (requestId === incomeRequestId.current) setIncomeLoading(false);
    }
  }, [day, offlineAccount]);

  useEffect(() => subscribeStaffOffline(event => {
    if (event === 'queued' || event === 'synced') fetchDailyIncome();
  }), [fetchDailyIncome]);

  useFocusEffect(React.useCallback(() => {
    fetchDailyIncome();
    fetchTransactions();
    fetchProducts();
  }, [fetchDailyIncome, fetchProducts, fetchTransactions]));

  const onRefresh = async () => {
    setRefreshing(true);
    try {
      await Promise.all([fetchTransactions(), fetchProducts(), fetchDailyIncome(),
        offlineAccount ? syncStaffOfflineActions(offlineAccount).catch(() => {}) : Promise.resolve()]);
    } finally {
      setRefreshing(false);
    }
  };

  const availableProducts = useMemo(
    () => (products || []).filter(product => product.is_available !== false),
    [products],
  );
  const categories = useMemo(() => productCategoryTabs(PRODUCT_CATEGORIES), [PRODUCT_CATEGORIES]);
  useEffect(() => {
    if (!categories.some(category => category.toLowerCase() === selectedCategory.toLowerCase())) {
      setSelectedCategory('All');
    }
  }, [categories, selectedCategory]);
  const isToday = value => {
    if (!value) return false;
    return String(value).slice(0, 10) === day;
  };

  // Filter products by category
  const filteredProducts = productsInCategory(availableProducts, selectedCategory);

  // Get recent transactions (last 5)
  const recentTransactions = (transactions || []).filter(t => isToday(t.date || t.created_at)).slice(0, 5);
  const dashboardError = incomeError || transactionError || productError;
  const dataUnavailable = Boolean(dashboardError);
  const isLoadingDashboard = transactionsLoading || productsLoading || incomeLoading;
  const hasDashboardData = availableProducts.length > 0 || transactions.length > 0 || todayIncome !== null;
  const showGettingStarted = !dataUnavailable && !incomeLoading && !transactionsLoading && !productsLoading
    && (transactions.length === 0 || availableProducts.length === 0);

  const retryDashboard = () => Promise.allSettled([
    fetchDailyIncome(), fetchTransactions(), fetchProducts(),
  ]);

  return (
    <SafeAreaView style={styles.container} edges={[]}>
      <StaffScreenHeader
        title={user?.name || 'Staff'}
        subtitle="Welcome back"
        icon="account"
        avatarSource={getProfilePhotoSource(user, userToken)}
        actionIcon="account-outline"
        actionLabel="Open profile"
        onActionPress={() => navigation.navigate('Profile')}
      />
      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.accent]} />
        }>
        <ApiErrorBanner message={sessionStorageWarning} />
        {isLoadingDashboard && !hasDashboardData ? <DashboardSkeleton /> : <>
        {/* Daily Income Card */}
        <View style={styles.incomeCard}>
          <IncomePeekCat />
          <View style={styles.incomeHeader}>
            <Text style={styles.incomeLabel}>Daily income</Text>
            <TouchableOpacity
              style={styles.incomeLinkButton}
              onPress={() => navigation.navigate('Transactions')}
              accessibilityRole="button"
              accessibilityLabel="View transactions">
              <Icon name="arrow-right" size={22} color={COLORS.textWhite} />
            </TouchableOpacity>
          </View>
          <View style={styles.incomeAmountContainer}>
            {todayIncome !== null ? <Text style={styles.currencySymbol}>₱</Text> : null}
            <Text style={[styles.incomeAmount, todayIncome === null && styles.incomeStatus]}>{incomeLoading ? 'Loading…' : todayIncome === null ? 'Unavailable' : todayIncome.toFixed(2)}</Text>
          </View>
          <Text style={styles.incomeDescription}>{incomeLoading ? 'Loading today’s income records…'
            : todayIncome !== null ? `Income entries today: ${todayIncomeCount ?? 0}${pendingIncomeCount ? ` · ${pendingIncomeCount} pending sync` : ''}`
              : 'Could not load today’s income'}</Text>
        </View>

        <ApiErrorBanner message={dashboardError} onRetry={retryDashboard} />

        {showGettingStarted ? (
          <View style={styles.gettingStartedCard}>
            <View style={styles.gettingStartedIcon}>
              <Icon name="lightbulb-on-outline" size={22} color={COLORS.accentDark} />
            </View>
            <View style={styles.gettingStartedCopy}>
              <Text style={styles.gettingStartedTitle}>Ready to get started?</Text>
              <Text style={styles.gettingStartedText}>
                Browse products and place customer orders from Orders. Record income or expenses in Transactions. If the product list is empty, ask your owner to add products and stock in Inventory.
              </Text>
              <TouchableOpacity style={styles.gettingStartedAction} onPress={() => navigation.navigate('Stock')}>
                <Text style={styles.gettingStartedActionText}>Open Inventory</Text>
                <Icon name="arrow-right" size={17} color={COLORS.accentDark} />
              </TouchableOpacity>
            </View>
          </View>
        ) : null}

        <View style={styles.quickActions}>
          {[
            { label: 'New order', icon: 'point-of-sale', route: 'Orders' },
            { label: 'Scan receipt', icon: 'line-scan', route: 'ReceiptScanner' },
            { label: 'Check stock', icon: 'package-variant-closed', route: 'Stock' },
            { label: 'Bug reports', icon: 'bug-outline', route: 'Profile', params: {screen: 'BugReports'} },
          ].map(action => (
            <TouchableOpacity
              key={action.label}
              style={styles.quickAction}
              onPress={() => navigation.navigate(action.route, action.params)}>
              <View style={styles.quickActionIcon}>
                <Icon name={action.icon} size={22} color={COLORS.accentDark} />
              </View>
              <Text style={styles.quickActionLabel}>{action.label}</Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Products Section */}
        <View style={styles.productsSection}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Products</Text>
          <TouchableOpacity
            onPress={() =>
              navigation.navigate('Orders', {
                screen: 'OrderQueue',
                params: selectedCategory === 'All' ? undefined : {category: selectedCategory},
              })
            }>
              <Text style={styles.viewAllText}>View all &gt;</Text>
            </TouchableOpacity>
          </View>

          {/* Category Tabs */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.categoryTabs}>
            {categories.map((category) => (
              <TouchableOpacity
                key={category}
                style={[
                  styles.categoryTab,
                  selectedCategory === category && styles.categoryTabActive,
                ]}
                onPress={() => setSelectedCategory(category)}>
                <Text
                  style={[
                    styles.categoryTabText,
                    selectedCategory === category && styles.categoryTabTextActive,
                  ]}>
                  {category}
                </Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          {/* Product Cards */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.productsScroll}
            contentContainerStyle={[
              styles.productsScrollContent,
              filteredProducts.length === 0 && styles.emptyProductsContent,
            ]}>
            {filteredProducts.length === 0 ? (
              <View style={styles.emptyProducts}>
                <Image
                  source={productError ? require('../../../assets/mascot/gasto-error.png') : require('../../../assets/mascot/gasto-empty.png')}
                  style={styles.emptyProductsMascot}
                  resizeMode="contain"
                  accessibilityLabel={productError ? 'Gasto could not load products' : 'Gasto beside an empty tray'}
                />
                <Text style={styles.emptyProductsTitle}>{productError ? 'Products unavailable' : 'No products available'}</Text>
                <Text style={styles.emptyProductsText}>
                  {productError ? 'Check your internet connection, then retry.' : selectedCategory === 'All'
                    ? 'Add or enable products from Inventory.'
                    : `No available products in ${selectedCategory}.`}
                </Text>
              </View>
            ) : (
              filteredProducts.slice(0, 5).map((product) => (
                <TouchableOpacity
                  key={product.id}
                  style={styles.productCard}
                  onPress={() => navigation.navigate('Orders', {
                    screen: 'OrderQueue',
                    params: {category: product.category},
                  })}>
                  <View style={styles.productImageContainer}>
                    {product.image ? (
                      <Image source={{ uri: product.image }} style={styles.productImage} />
                    ) : (
                      <Icon name="image-outline" size={42} color={COLORS.textMuted} />
                    )}
                  </View>
                  <View style={styles.productInfo}>
                    <View style={styles.productBadge}>
                      <Text style={styles.productBadgeText}>{product.category || 'Uncategorized'}</Text>
                    </View>
                    <Text style={styles.productName}>{product.name}</Text>
                    <Text style={styles.productPrice}>₱{parseFloat(product.price || 0).toFixed(2)}</Text>
                  </View>
                </TouchableOpacity>
              ))
            )}
          </ScrollView>
        </View>

        {/* Recent Transactions */}
        <View style={styles.transactionsSection}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Today's Transactions</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Transactions')}>
              <Text style={styles.viewAllText}>View all &gt;</Text>
            </TouchableOpacity>
          </View>

          {recentTransactions.length === 0 ? (
            <View style={styles.emptyTransactions}>
              <Image source={transactionError ? require('../../../assets/mascot/gasto-error.png') : require('../../../assets/mascot/gasto-empty.png')} style={styles.emptyMascot} resizeMode="contain" accessibilityLabel={transactionError ? 'Gasto could not load transactions' : 'Gasto beside an empty tray'} />
              <Text style={styles.emptyText}>{transactionError ? 'Transactions unavailable' : 'No transactions today'}</Text>
            </View>
          ) : (
            recentTransactions.map((transaction) => (
              <TouchableOpacity
                key={transaction.id}
                style={styles.transactionItem}
                onPress={() => navigation.navigate('Transactions')}>
                <View style={[styles.transactionIconContainer, transaction.type?.toLowerCase() === 'income' ? styles.incomeTransactionIcon : styles.expenseTransactionIcon]}>
                  <Icon
                    name={transactionCategoryIcon(transaction.category)}
                    size={22}
                    color={transaction.type?.toLowerCase() === 'income' ? COLORS.success : COLORS.danger}
                  />
                </View>
                <View style={styles.transactionInfo}>
                  <Text style={styles.transactionCategory} numberOfLines={2}>{transactionDisplayName(transaction)}</Text>
                  <Text style={styles.transactionDate}>
                    {new Date(transaction.date || transaction.created_at).toLocaleDateString('en-PH', {
                      month: 'short',
                      day: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </Text>
                </View>
                <Text
                  style={[
                    styles.transactionAmount,
                    {
                      color: transaction.type?.toLowerCase() === 'income' ? COLORS.success : COLORS.danger,
                    },
                  ]}>
                  {transaction.type?.toLowerCase() === 'income' ? '+' : '-'}₱{parseFloat(transaction.amount).toFixed(2)}
                </Text>
              </TouchableOpacity>
            ))
          )}
        </View>

        {/* Bottom spacing */}
        <View style={styles.bottomSpacing} />
        </>}
      </ScrollView>
    </SafeAreaView>
  );
};

const DashboardSkeleton = () => (
  <SkeletonPlaceholder style={styles.dashboardSkeleton} accessibilityLabel="Loading home screen">
    <View style={styles.skeletonIncomeCard}>
      <SkeletonBlock width={112} height={14} />
      <SkeletonBlock width={174} height={38} radius={12} style={styles.skeletonIncomeAmountSpacer} />
      <SkeletonBlock width="72%" height={12} style={styles.skeletonIncomeCaptionSpacer} />
    </View>
    <View style={styles.skeletonQuickActions}>
      {[0, 1, 2, 3].map(item => (
        <View key={item} style={styles.skeletonQuickAction}>
          <SkeletonBlock width={38} height={38} radius={14} />
          <SkeletonBlock width="68%" height={12} style={styles.skeletonQuickActionLabelSpacer} />
        </View>
      ))}
    </View>
    <View style={styles.skeletonSection}>
      <View style={styles.skeletonSectionHeader}>
        <SkeletonBlock width={116} height={17} />
        <SkeletonBlock width={55} height={12} />
      </View>
      <View style={styles.skeletonProductRow}>
        {[0, 1, 2].map(item => (
          <View key={item} style={styles.skeletonProductCard}>
            <SkeletonBlock height={86} radius={13} />
            <SkeletonBlock width="58%" height={10} style={styles.skeletonProductCategorySpacer} />
            <SkeletonBlock width="78%" height={13} style={styles.skeletonProductNameSpacer} />
            <SkeletonBlock width="42%" height={12} style={styles.skeletonProductPriceSpacer} />
          </View>
        ))}
      </View>
    </View>
    <View style={styles.skeletonSection}>
      <View style={styles.skeletonSectionHeader}>
        <SkeletonBlock width={150} height={17} />
        <SkeletonBlock width={55} height={12} />
      </View>
      {[0, 1, 2].map(item => (
        <View key={item} style={styles.skeletonTransactionRow}>
          <SkeletonBlock width={40} height={40} radius={14} />
          <View style={styles.skeletonTransactionCopy}>
            <SkeletonBlock width="76%" height={13} />
            <SkeletonBlock width="48%" height={11} style={styles.skeletonTransactionSubtitleSpacer} />
          </View>
          <SkeletonBlock width={62} height={14} />
        </View>
      ))}
    </View>
  </SkeletonPlaceholder>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  dashboardSkeleton: {paddingHorizontal: 16, paddingTop: 12, paddingBottom: 28},
  skeletonIncomeCard: {padding: 18, minHeight: 140, borderRadius: 18, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonQuickActions: {flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 16},
  skeletonQuickAction: {width: '48%', minHeight: 86, alignItems: 'center', justifyContent: 'center', borderRadius: 15, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonSection: {marginTop: 22},
  skeletonSectionHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12},
  skeletonProductRow: {flexDirection: 'row', gap: 10},
  skeletonProductCard: {width: 142, padding: 10, borderRadius: 15, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonTransactionRow: {flexDirection: 'row', alignItems: 'center', gap: 11, paddingVertical: 12, paddingHorizontal: 11, marginBottom: 8, borderRadius: 14, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonTransactionCopy: {flex: 1},
  skeletonIncomeAmountSpacer: {marginTop: 15},
  skeletonIncomeCaptionSpacer: {marginTop: 12},
  skeletonQuickActionLabelSpacer: {marginTop: 9},
  skeletonProductCategorySpacer: {marginTop: 10},
  skeletonProductNameSpacer: {marginTop: 7},
  skeletonProductPriceSpacer: {marginTop: 8},
  skeletonTransactionSubtitleSpacer: {marginTop: 8},
  scrollView: {
    flex: 1,
  },
  incomeCard: {
    backgroundColor: COLORS.textDark,
    marginHorizontal: 16,
    marginTop: 12,
    marginBottom: 12,
    padding: 18,
    borderRadius: 16,
    elevation: 4,
  },
  incomeMascotPeek: {
    position: 'absolute',
    right: 16,
    bottom: 0,
    width: 108,
    height: 76,
    overflow: 'hidden',
  },
  incomeMascotImage: {width: 108, height: 108},
  incomeMascotBlink: {position: 'absolute', top: 0, left: 0},
  incomeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  incomeLabel: {
    fontSize: 14,
    color: COLORS.textWhite,
    opacity: 0.8,
  },
  incomeLinkButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'transparent',
    justifyContent: 'center',
    alignItems: 'center',
  },
  incomeAmountContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 2,
  },
  currencySymbol: {
    fontSize: 32,
    fontWeight: 'bold',
    color: COLORS.textWhite,
    marginTop: 8,
    marginRight: 8,
  },
  incomeAmount: {
    fontSize: 44,
    fontWeight: 'bold',
    color: COLORS.textWhite,
  },
  incomeStatus: {fontSize: 27, paddingVertical: 10},
  incomeDescription: {
    fontSize: 13,
    color: COLORS.textWhite,
    opacity: 0.72,
  },
  incomeEmptyHint: {
    fontSize: 11,
    color: COLORS.textWhite,
    opacity: 0.62,
    marginTop: 5,
  },
  gettingStartedCard: {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginBottom: 14,
    padding: 13,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.surface,
  },
  gettingStartedIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.surfaceMuted,
    marginRight: 10,
  },
  gettingStartedCopy: {flex: 1},
  gettingStartedTitle: {fontSize: 14, fontWeight: '700', color: COLORS.textDark},
  gettingStartedText: {fontSize: 12, lineHeight: 17, color: COLORS.textGray, marginTop: 4},
  gettingStartedAction: {flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', marginTop: 7},
  gettingStartedActionText: {fontSize: 12, fontWeight: '700', color: COLORS.accentDark},
  quickActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginHorizontal: 16,
    marginBottom: 16,
  },
  quickAction: {
    width: '23%',
    alignItems: 'center',
  },
  quickActionIcon: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 7,
  },
  quickActionLabel: {
    fontSize: 11,
    lineHeight: 15,
    color: COLORS.textDark,
    textAlign: 'center',
  },
  productsSection: {
    marginTop: 4,
    marginBottom: 12,
  },
  transactionsSection: {
    marginHorizontal: 16,
    marginBottom: 12,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.textDark,
  },
  viewAllText: {
    fontSize: 14,
    color: COLORS.textGray,
  },
  categoryTabs: {
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  categoryTab: {
    minHeight: 48,
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: COLORS.bgCard,
    marginRight: 8,
    elevation: 1,
  },
  categoryTabActive: {
    backgroundColor: COLORS.accentDark,
  },
  categoryTabText: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.textGray,
  },
  categoryTabTextActive: {
    color: COLORS.textWhite,
  },
  productsScroll: {
    flexGrow: 0,
  },
  productsScrollContent: {
    paddingHorizontal: 16,
    paddingBottom: 4,
  },
  emptyProductsContent: {
    flexGrow: 1,
  },
  productCard: {
    width: 156,
    marginRight: 12,
    marginBottom: 8,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    overflow: 'hidden',
    elevation: 1,
  },
  productImageContainer: {
    width: '100%',
    height: 118,
    backgroundColor: COLORS.miniCardBg,
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  productImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  productInfo: {
    padding: 10,
  },
  productBadge: {
    alignSelf: 'flex-start',
    backgroundColor: COLORS.surfaceMuted,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    marginBottom: 6,
  },
  productBadgeText: {
    fontSize: 10,
    color: COLORS.accentDark,
    fontWeight: '600',
  },
  productName: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.textDark,
    marginBottom: 4,
  },
  productPrice: {
    fontSize: 13,
    color: COLORS.accentDark,
    fontWeight: '600',
  },
  emptyProducts: {
    flex: 1,
    minHeight: 148,
    padding: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  emptyProductsMascot: {width: 96, height: 96},
  emptyProductsTitle: {
    marginTop: 12,
    fontSize: 15,
    fontWeight: '700',
    color: COLORS.textDark,
  },
  emptyProductsText: {
    marginTop: 5,
    fontSize: 12,
    lineHeight: 18,
    color: COLORS.textGray,
    textAlign: 'center',
  },
  transactionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.miniCardBg,
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
  },
  transactionIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  incomeTransactionIcon: {backgroundColor: '#E8F5E9'},
  expenseTransactionIcon: {backgroundColor: '#FFEBEE'},
  transactionInfo: {
    flex: 1,
  },
  transactionCategory: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.textDark,
    marginBottom: 4,
  },
  transactionDate: {
    fontSize: 12,
    color: COLORS.textGray,
  },
  transactionAmount: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  emptyTransactions: {
    backgroundColor: COLORS.miniCardBg,
    padding: 24,
    borderRadius: 12,
    alignItems: 'center',
  },
  emptyMascot: {width: 104, height: 104},
  emptyText: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.textMuted,
  },
  bottomSpacing: {
    height: 80,
  },
});

export default DashboardScreen;
