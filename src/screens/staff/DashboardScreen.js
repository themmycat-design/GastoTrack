import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { subscribeToNotifications } from '../../modules/NotificationModule';
import { useProducts } from '../../context/ProductContext';
import { useStock } from '../../context/StockContext';
import { COLORS } from '../../theme';

const PERIODS = ['Daily', 'Weekly', 'Monthly'];

const DashboardScreen = ({ navigation }) => {
  const [transactions, setTransactions] = useState([]);
  const { products } = useProducts();
  const { deductIngredients } = useStock();
  const [selectedCategory, setSelectedCategory] = useState('All');

  useEffect(() => {
    const unsubscribe = subscribeToNotifications(transaction => {
      setTransactions(prev => [transaction, ...prev]);
    });
    return () => unsubscribe();
  }, []);

  const totalIncome = transactions
    .filter(t => t.transactionType === 'received' || t.type === 'Income')
    .reduce((sum, t) => sum + parseAmount(t.amount), 0);

  const totalExpenses = transactions
    .filter(t => t.transactionType === 'sent' || t.type === 'Expense')
    .reduce((sum, t) => sum + parseAmount(t.amount), 0);

  const spendingGoal = 10000;
  const spendingPct = Math.min((totalExpenses / spendingGoal) * 100, 100);

  const recentThree = transactions.slice(0, 3);

  // Get unique categories from products
  const categories = ['All', ...new Set(products.map(p => p.category))];

  // Filter products by selected category
  const filteredProducts = selectedCategory === 'All' 
    ? products 
    : products.filter(p => p.category === selectedCategory);

  const getCategoryIcon = category => {
    const map = {
      Sales: '📦', Delivery: '🚗', Catering: '🍽️',
      Ingredients: '🥘', Packaging: '📦', Utilities: '💡',
      Rent: '🏠', Salaries: '💼', Equipment: '🔧',
      Supplies: '🛒', Others: '📝',
    };
    return map[category] || '💳';
  };

  const getTagStyle = type => {
    const isIncome = type === 'Income' || type === 'received';
    return {
      bg: isIncome ? COLORS.tagIncBg : COLORS.tagExpBg,
      text: isIncome ? COLORS.tagIncText : COLORS.tagExpText,
      label: isIncome ? 'Income' : 'Expense',
    };
  };

  return (
    <View style={[styles.container, { flex: 1 }]}>
      <ScrollView showsVerticalScrollIndicator={false}
      contentContainerStyle={styles.scrollContent}>
        {/* Dark teal top section */}
        <View style={styles.topSection}>

          {/* Header */}
          <View style={styles.headerRow}>
            <View>
              <Text style={styles.greeting}>Good morning</Text>
              <Text style={styles.title}>Hi, Welcome Back</Text>
            </View>
            <View style={styles.bellIcon}>
              <Text style={styles.bellText}>🔔</Text>
            </View>
          </View>

          {/* Stat Cards */}
          <View style={styles.cardsRow}>
            <View style={[styles.statCard, styles.accentCard]}>
              <Text style={styles.statLabelAccent}>Total income</Text>
              <Text style={styles.statValueAccent}>
                ₱{totalIncome.toFixed(2)}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.scanBtn}
              onPress={() => navigation.navigate('ReceiptScanner')}>
              <Text style={styles.scanBtnIcon}>📸</Text>
              <Text style={styles.scanBtnText}>{"Scan\nReceipt"}</Text>
            </TouchableOpacity>
            <View style={styles.statCard}>
              <Text style={styles.statLabel}>Total expenses</Text>
              <Text style={[styles.statValue, { color: COLORS.expense }]}>
                -₱{totalExpenses.toFixed(2)}
              </Text>
            </View>
          </View>
        </View>

        {/* White card section */}
        <View style={styles.whiteSection}>

          {/* Products Section */}
          <View style={styles.productsBox}>
            <View style={styles.productsHeader}>
              <Text style={styles.sectionTitle}>Products</Text>
              <TouchableOpacity>
                <Text style={styles.seeAll}>View all &gt;</Text>
              </TouchableOpacity>
            </View>

            {/* Category Filter */}
            <ScrollView 
              horizontal 
              showsHorizontalScrollIndicator={false}
              style={styles.categoryScroll}>
              {categories.map(cat => (
                <TouchableOpacity
                  key={cat}
                  style={[
                    styles.categoryChip,
                    selectedCategory === cat && styles.categoryChipActive,
                  ]}
                  onPress={() => setSelectedCategory(cat)}>
                  <Text style={[
                    styles.categoryChipText,
                    selectedCategory === cat && styles.categoryChipTextActive,
                  ]}>
                    {cat}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>

            {/* Product Cards - Horizontal Scroll */}
            <ScrollView 
              horizontal 
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.productsScroll}>
              {filteredProducts.map(product => (
                <View key={product.id} style={styles.productCard}>
                  <View style={styles.productImagePlaceholder}>
                    <Text style={styles.productEmoji}>{product.emoji}</Text>
                  </View>
                  <View style={styles.productFooter}>
                    <Text style={styles.productCategory}>{product.category}</Text>
                    <Text style={styles.productName}>{product.name}</Text>
                  </View>
                </View>
              ))}
            </ScrollView>
          </View>

          
          {/* Transactions box — separate scrollable card */}
          <View style={styles.txnBox}>
            <View style={styles.txnBoxHeader}>
              <Text style={styles.sectionTitle}>Recent transactions</Text>
              <Text style={styles.seeAll}>See all</Text>
            </View>

            {/* Removed Period toggle - Daily only */}
            <Text style={styles.periodLabel}>Daily</Text>

          {/* Scrollable transactions */}
          <View style={styles.txnScroll}>
            {transactions.length === 0 ? (
              <View style={styles.emptyState}>
                <Text style={styles.emptyText}>No transactions yet.</Text>
                <Text style={styles.emptySubText}>
                  Captured notifications will appear here.
                </Text>
              </View>
            ) : (
              transactions.map((t, i) => {
                const isIncome =
                  t.transactionType === 'received' || t.type === 'Income';
                const tag = getTagStyle(t.transactionType || t.type);
                return (
                  <View
                    key={i}
                    style={[
                      styles.txnRow,
                      i === transactions.length - 1 && { borderBottomWidth: 0 },
                    ]}>
                    <View style={styles.txnIcon}>
                      <Text style={styles.txnIconText}>
                        {getCategoryIcon(t.category || t.appSource)}
                      </Text>
                    </View>
                    <View style={styles.txnInfo}>
                      <Text style={styles.txnName}>
                        {t.category || t.appSource || 'Transaction'}
                      </Text>
                      <Text style={styles.txnMeta}>
                        {t.source || t.appSource} • {t.date || 'Today'}
                      </Text>
                      <View style={[
                        styles.txnTag,
                        { backgroundColor: tag.bg },
                      ]}>
                        <Text style={[styles.txnTagText, { color: tag.text }]}>
                          {tag.label}
                        </Text>
                      </View>
                    </View>
                    <Text style={[
                      styles.txnAmt,
                      { color: isIncome ? COLORS.income : COLORS.expense },
                    ]}>
                      {isIncome ? '+' : '-'}{t.amount}
                    </Text>
                  </View>
                );
              })
            )}
          </View>
        </View>
        </View>
      </ScrollView>
    </View>
  );
};

const parseAmount = str => {
  if (!str || str === 'unknown') return 0;
  return parseFloat(str.replace('₱', '').replace(/,/g, '')) || 0;
};

const styles = StyleSheet.create({
  container: {
  flex: 1,
  backgroundColor: '#F5F5F5',
},

  // Top dark section
  topSection: {
    backgroundColor: COLORS.bgDark,
    padding: 20,
    paddingTop: 48,
    paddingBottom: 16,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  greeting: { fontSize: 12, color: 'rgba(0,0,0,0.5)' },
  title: { fontSize: 22, fontWeight: 'bold', color: COLORS.textDark, marginTop: 2 },
  bellIcon: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(0,0,0,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bellText: { fontSize: 16 },
  cardsRow: { flexDirection: 'row', gap: 10, alignItems: 'center' },
  scanBtn: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 5,
  },
  scanBtnIcon: { fontSize: 22 },
  scanBtnText: {
    fontSize: 9,
    fontWeight: 'bold',
    color: COLORS.textWhite,
    textAlign: 'center',
    lineHeight: 12,
    marginTop: 2,
  },
  statCard: {
    flex: 1,
    backgroundColor: COLORS.bgCard,
    borderRadius: 16,
    padding: 14,
  },
  accentCard: { backgroundColor: COLORS.bgCard },
  statLabel: { fontSize: 11, color: COLORS.textGray },
  statLabelAccent: { fontSize: 11, color: COLORS.textGray },
  statValue: { fontSize: 20, fontWeight: 'bold', color: COLORS.expense, marginTop: 4 },
  statValueAccent: { fontSize: 20, fontWeight: 'bold', color: COLORS.income, marginTop: 4 },

  scrollContent: {
  flexGrow: 1,
  paddingBottom: 120,
},
  // White bottom section
  whiteSection: {
  backgroundColor: '#F5F5F5',
  borderTopLeftRadius: 24,
  borderTopRightRadius: 24,
  paddingTop: 16,
  minHeight: 800,
},
  productsBox: {
    backgroundColor: COLORS.bgCard,
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 12,
  },
  productsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  categoryScroll: {
    marginBottom: 16,
  },
  categoryChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    marginRight: 8,
  },
  categoryChipActive: {
    backgroundColor: '#D4A574',
  },
  categoryChipText: {
    fontSize: 13,
    color: COLORS.textGray,
    fontWeight: '500',
  },
  categoryChipTextActive: {
    color: COLORS.textWhite,
    fontWeight: 'bold',
  },
  productsScroll: {
    gap: 12,
  },
  productCard: {
    width: 160,
    borderRadius: 12,
    overflow: 'hidden',
    backgroundColor: '#F5F5F5',
  },
  productImagePlaceholder: {
    width: '100%',
    height: 140,
    backgroundColor: '#E8E8E8',
    alignItems: 'center',
    justifyContent: 'center',
  },
  productEmoji: {
    fontSize: 64,
  },
  productFooter: {
    backgroundColor: '#5A5A5A',
    padding: 12,
  },
  productCategory: {
    fontSize: 10,
    color: 'rgba(255,255,255,0.7)',
    marginBottom: 4,
  },
  productName: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.textWhite,
  },
  progressBox: {
  backgroundColor: COLORS.bgCard,
  borderRadius: 16,
  padding: 16,
  marginHorizontal: 16,
  marginBottom: 12,
  },
  progressLabel: { fontSize: 12, color: COLORS.textGray, marginBottom: 8 },
  progressTrack: {
    backgroundColor: COLORS.toggleBg,
    borderRadius: 4,
    height: 8,
    overflow: 'hidden',
    marginBottom: 6,
  },
  progressFill: {
    height: 8,
    borderRadius: 4,
    backgroundColor: COLORS.accent,
  },
  progressNote: {
    fontSize: 12,
    color: COLORS.accentDark,
    marginBottom: 16,
    fontWeight: '500',
  },
  miniCards: { flexDirection: 'row', gap: 10, marginBottom: 20 },
  miniCard: {
    flex: 1,
    backgroundColor: COLORS.miniCardBg,
    borderRadius: 12,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  miniIcon: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  miniLabel: { fontSize: 10, color: COLORS.textGray },
  miniValue: { fontSize: 13, fontWeight: 'bold', color: COLORS.textDark, marginTop: 2 },
  sectionTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.textDark,
    marginBottom: 10,
  },
  periodLabel: {
    fontSize: 12,
    fontWeight: 'bold',
    color: COLORS.textGray,
    textAlign: 'center',
    backgroundColor: COLORS.toggleBg,
    paddingVertical: 7,
    borderRadius: 17,
    marginBottom: 14,
  },
  toggleRow: {
    flexDirection: 'row',
    backgroundColor: COLORS.toggleBg,
    borderRadius: 20,
    padding: 3,
    marginBottom: 14,
  },
  toggleBtn: {
    flex: 1,
    paddingVertical: 7,
    borderRadius: 17,
    alignItems: 'center',
  },
  toggleBtnActive: { backgroundColor: COLORS.accent },
  toggleText: { fontSize: 12, color: COLORS.textGray },
  toggleTextActive: { fontSize: 12, color: COLORS.textWhite, fontWeight: 'bold' },
  emptyState: { alignItems: 'center', paddingVertical: 32 },
  emptyText: { fontSize: 14, fontWeight: 'bold', color: COLORS.textMuted },
  emptySubText: { fontSize: 12, color: COLORS.textMuted, marginTop: 4 },
  txnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: 0.5,
    borderBottomColor: COLORS.divider,
  },
    txnBox: {
    backgroundColor: COLORS.bgCard,
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginTop: 12,
  },
  txnBoxHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  seeAll: {
    fontSize: 12,
    color: COLORS.accent,
    fontWeight: '500',
  },
  txnScroll: {
    maxHeight: 280,
    overflow: 'scroll',
  },
  txnIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: COLORS.miniCardBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  txnIconText: { fontSize: 18 },
  txnInfo: { flex: 1 },
  txnName: { fontSize: 13, fontWeight: 'bold', color: COLORS.textDark },
  txnMeta: { fontSize: 11, color: COLORS.textGray, marginTop: 2 },
  txnTag: {
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    marginTop: 4,
  },
  txnTagText: { fontSize: 10, fontWeight: 'bold' },
  txnAmt: { fontSize: 13, fontWeight: 'bold' },
});

export default DashboardScreen;
