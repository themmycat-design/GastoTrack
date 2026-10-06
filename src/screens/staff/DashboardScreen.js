import React, { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  Image,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useTransactions } from '../../context/TransactionContext';
import { useProducts } from '../../context/ProductContext';
import { useAuth } from '../../context/AuthContext';
import { COLORS } from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const DashboardScreen = ({ navigation }) => {
  const { user } = useAuth();
  const { transactions, isLoading: transactionsLoading, fetchTransactions } = useTransactions();
  const { products, isLoading: productsLoading, fetchProducts } = useProducts();
  const [refreshing, setRefreshing] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState('All');

  useEffect(() => {
    fetchTransactions();
    fetchProducts();
  }, [fetchProducts, fetchTransactions]);

  const onRefresh = async () => {
    setRefreshing(true);
    await Promise.all([fetchTransactions(), fetchProducts()]);
    setRefreshing(false);
  };

  const availableProducts = useMemo(
    () => (products || []).filter(product => product.is_available !== false),
    [products],
  );
  const categories = useMemo(() => [
    'All',
    ...Array.from(new Set(availableProducts
      .map(product => product.category?.trim())
      .filter(Boolean))),
  ], [availableProducts]);
  useEffect(() => {
    if (!categories.some(category => category.toLowerCase() === selectedCategory.toLowerCase())) {
      setSelectedCategory('All');
    }
  }, [categories, selectedCategory]);
  const isToday = value => {
    if (!value) return false;
    return new Date(value).toDateString() === new Date().toDateString();
  };

  // Calculate today's sales (income)
  const todayIncome = (transactions || [])
    .filter(t => {
      return t.type?.toLowerCase() === 'income' && isToday(t.date || t.created_at);
    })
    .reduce((sum, t) => sum + parseFloat(t.amount), 0);

  // Filter products by category
  const filteredProducts = selectedCategory === 'All'
    ? availableProducts
    : availableProducts.filter(product => (
      product.category?.trim().toLowerCase() === selectedCategory.toLowerCase()
    ));

  // Get recent transactions (last 5)
  const recentTransactions = (transactions || []).slice(0, 5);

  if (transactionsLoading || productsLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.accent} />
        <Text style={styles.loadingText}>Loading Dashboard...</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container} edges={[]}>
      <StaffScreenHeader
        title={user?.name || 'Staff'}
        subtitle="Welcome back"
        icon="account"
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
        {/* Daily Income Card */}
        <View style={styles.incomeCard}>
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
            <Text style={styles.currencySymbol}>₱</Text>
            <Text style={styles.incomeAmount}>{todayIncome.toFixed(2)}</Text>
          </View>
          <Text style={styles.incomeDescription}>Total income logged today</Text>
        </View>

        <View style={styles.quickActions}>
          {[
            { label: 'New order', icon: 'point-of-sale', route: 'Orders' },
            { label: 'Scan receipt', icon: 'line-scan', route: 'ReceiptScanner' },
            { label: 'Check stock', icon: 'package-variant-closed', route: 'Stock' },
          ].map(action => (
            <TouchableOpacity
              key={action.label}
              style={styles.quickAction}
              onPress={() => navigation.navigate(action.route)}>
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
              navigation.navigate(
                'Orders',
                selectedCategory === 'All'
                  ? undefined
                  : {category: selectedCategory},
              )
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
                <View style={styles.emptyProductsIcon}>
                  <Icon name="food-off" size={34} color={COLORS.textMuted} />
                </View>
                <Text style={styles.emptyProductsTitle}>No products available</Text>
                <Text style={styles.emptyProductsText}>
                  {selectedCategory === 'All'
                    ? 'Add or enable products from Inventory.'
                    : `No available products in ${selectedCategory}.`}
                </Text>
              </View>
            ) : (
              filteredProducts.slice(0, 5).map((product) => (
                <TouchableOpacity
                  key={product.id}
                  style={styles.productCard}
                  onPress={() => navigation.navigate('Orders', {category: product.category})}>
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
            <Text style={styles.sectionTitle}>Recent Transactions</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Transactions')}>
              <Text style={styles.viewAllText}>View all &gt;</Text>
            </TouchableOpacity>
          </View>

          {recentTransactions.length === 0 ? (
            <View style={styles.emptyTransactions}>
              <Icon name="receipt-text-outline" size={64} color="#DDD" />
              <Text style={styles.emptyText}>No transactions yet</Text>
            </View>
          ) : (
            recentTransactions.map((transaction) => (
              <TouchableOpacity
                key={transaction.id}
                style={styles.transactionItem}
                onPress={() => navigation.navigate('Transactions')}>
                <View style={styles.transactionIconContainer}>
                  <Icon
                    name={
                      transaction.type?.toLowerCase() === 'income'
                        ? 'flash'
                        : transaction.type?.toLowerCase() === 'expense'
                        ? 'cart'
                        : 'refresh'
                    }
                    size={24}
                    color="#333"
                  />
                </View>
                <View style={styles.transactionInfo}>
                  <Text style={styles.transactionCategory}>{transaction.category}</Text>
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
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.miniCardBg,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: COLORS.textGray,
  },
  scrollView: {
    flex: 1,
  },
  incomeCard: {
    backgroundColor: COLORS.textDark,
    marginHorizontal: 20,
    marginTop: 20,
    marginBottom: 16,
    padding: 24,
    borderRadius: 16,
    elevation: 4,
  },
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
  incomeDescription: {
    fontSize: 13,
    color: COLORS.textWhite,
    opacity: 0.72,
  },
  quickActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginHorizontal: 20,
    marginBottom: 24,
  },
  quickAction: {
    width: '30%',
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
    marginTop: 8,
    marginBottom: 16,
  },
  transactionsSection: {
    marginHorizontal: 20,
    marginBottom: 16,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    marginBottom: 12,
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
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  categoryTab: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 20,
    backgroundColor: COLORS.bgCard,
    marginRight: 8,
    elevation: 1,
  },
  categoryTabActive: {
    backgroundColor: COLORS.accent,
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
    paddingHorizontal: 20,
    paddingBottom: 8,
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
    padding: 12,
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
    minHeight: 178,
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  emptyProductsIcon: {
    width: 58,
    height: 58,
    borderRadius: 29,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
  },
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
    padding: 16,
    borderRadius: 8,
    marginBottom: 8,
  },
  transactionIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 8,
    backgroundColor: COLORS.bgCard,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
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
    padding: 40,
    borderRadius: 12,
    alignItems: 'center',
  },
  emptyText: {
    marginTop: 16,
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.textMuted,
  },
  bottomSpacing: {
    height: 24,
  },
});

export default DashboardScreen;
