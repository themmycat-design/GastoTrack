import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Modal,
  TextInput,
  ScrollView,
} from 'react-native';
import { useTransactions } from '../../context/TransactionContext';
import { COLORS } from '../../theme';

const TransactionsScreen = ({ navigation }) => {
  const {
    transactions,
    addTransaction,
    updateTransaction,
    deleteTransaction,
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
    TRANSACTION_SOURCES,
  } = useTransactions();

  const SOURCES = TRANSACTION_SOURCES;

  // Add modal state
  const [addModalVisible, setAddModalVisible] = useState(false);

  // Detail/Edit modal state
  const [detailModalVisible, setDetailModalVisible] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [isEditing, setIsEditing] = useState(false);

  // Form state (used for both add and edit)
  const [amount, setAmount] = useState('');
  const [type, setType] = useState('Income');
  const [source, setSource] = useState('Cash');
  const [category, setCategory] = useState('');
  const [date, setDate] = useState(getTodayDate());
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState({});

  const categories = type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;

  function getTodayDate() {
    return new Date().toISOString().split('T')[0];
  }

  const resetForm = () => {
    setAmount('');
    setType('Income');
    setSource('Cash');
    setCategory('');
    setDate(getTodayDate());
    setNotes('');
    setErrors({});
  };

  const validate = () => {
    const newErrors = {};
    if (!amount || isNaN(parseFloat(amount))) {
      newErrors.amount = 'Please enter a valid amount.';
    }
    if (!category) {
      newErrors.category = 'Please select a category.';
    }
    if (!date) {
      newErrors.date = 'Please enter a date.';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // ----------------------------------------------------------------
  // Add transaction
  // ----------------------------------------------------------------
  const handleSave = () => {
    if (!validate()) return;

    const newTransaction = {
      type,
      amount: parseFloat(amount),
      source,
      category,
      date,
      notes,
      entryMethod: 'Manual',
      recordedBy: 'Staff User',
    };

    addTransaction(newTransaction);
    setAddModalVisible(false);
    resetForm();
  };

  // ----------------------------------------------------------------
  // Open detail card
  // ----------------------------------------------------------------
  const handleOpenDetail = (transaction) => {
    setSelectedTransaction(transaction);
    setIsEditing(false);
    setDetailModalVisible(true);
  };

  // ----------------------------------------------------------------
  // Start editing — pre-fill form with selected transaction's data
  // ----------------------------------------------------------------
  const handleStartEdit = () => {
    if (!selectedTransaction) return;
    // Extract numeric amount from transaction
    const numericAmount = typeof selectedTransaction.amount === 'number' 
      ? selectedTransaction.amount 
      : parseFloat(selectedTransaction.amount?.toString().replace(/[^0-9.]/g, '')) || 0;
    
    setAmount(numericAmount.toString());
    setType(selectedTransaction.type);
    setSource(selectedTransaction.source);
    setCategory(selectedTransaction.category);
    setDate(selectedTransaction.date);
    setNotes(selectedTransaction.notes || '');
    setErrors({});
    setIsEditing(true);
  };

  // ----------------------------------------------------------------
  // Save edited transaction
  // ----------------------------------------------------------------
  const handleUpdate = () => {
    if (!validate()) return;

    const updated = {
      ...selectedTransaction,
      type,
      amount: parseFloat(amount),
      source,
      category,
      date,
      notes,
    };

    updateTransaction(updated);
    setSelectedTransaction(updated);
    setIsEditing(false);
    resetForm();
  };

  // ----------------------------------------------------------------
  // Delete transaction
  // ----------------------------------------------------------------
  // Delete transaction
  // ----------------------------------------------------------------
  const handleDelete = () => {
    deleteTransaction(selectedTransaction.id);
    setDetailModalVisible(false);
    setSelectedTransaction(null);
  };

  // ----------------------------------------------------------------
  // Render transaction list card
  // ----------------------------------------------------------------
  const renderTransaction = ({ item }) => (
    <TouchableOpacity
      style={styles.transactionCard}
      onPress={() => handleOpenDetail(item)}
      activeOpacity={0.7}>
      <View style={styles.transactionRow}>
        <View style={styles.transactionLeft}>
          <View style={[
            styles.typeDot,
            item.type === 'Income' ? styles.dotIncome : styles.dotExpense,
          ]} />
          <View>
            <Text style={styles.transactionCategory}>{item.category}</Text>
            <Text style={styles.transactionSource}>
              {item.source} • {item.date}
            </Text>
            {item.notes ? (
              <Text style={styles.transactionNotes} numberOfLines={1}>
                {item.notes}
              </Text>
            ) : null}
          </View>
        </View>
        <Text style={[
          styles.transactionAmount,
          item.type === 'Income' ? styles.amountIncome : styles.amountExpense,
        ]}>
          {item.type === 'Income' ? '+' : '-'}₱{typeof item.amount === 'number' ? item.amount.toFixed(2) : item.amount}
        </Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Transactions</Text>
        <Text style={styles.headerSub}>
          {transactions.length} record{transactions.length !== 1 ? 's' : ''}
        </Text>
      </View>

      {/* Transaction List */}
      {transactions.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyText}>No transactions yet.</Text>
          <Text style={styles.emptySubText}>
            Tap the + button to add one manually.
          </Text>
        </View>
      ) : (
        <FlatList
          data={transactions}
          keyExtractor={item => item.id}
          renderItem={renderTransaction}
          contentContainerStyle={styles.list}
        />
      )}

      {/* Floating + Button */}
      <TouchableOpacity
        style={styles.fab}
        onPress={() => setAddModalVisible(true)}>
        <Text style={styles.fabText}>+</Text>
      </TouchableOpacity>

      {/* ============================================================
          ADD TRANSACTION MODAL
      ============================================================ */}
      <Modal
        visible={addModalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={() => {
          setAddModalVisible(false);
          resetForm();
        }}>
        <View style={styles.slideOverlay}>
          <View style={styles.slideContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Add Transaction</Text>
              <TouchableOpacity onPress={() => {
                setAddModalVisible(false);
                resetForm();
              }}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>
            <ScrollView showsVerticalScrollIndicator={false}>
              {renderForm()}
              <TouchableOpacity style={styles.saveButton} onPress={handleSave}>
                <Text style={styles.saveButtonText}>Save Transaction</Text>
              </TouchableOpacity>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ============================================================
          DETAIL / EDIT MODAL — floating centered card
      ============================================================ */}
      <Modal
        visible={detailModalVisible}
        animationType="fade"
        transparent={true}
        onRequestClose={() => {
          setDetailModalVisible(false);
          setIsEditing(false);
          resetForm();
        }}>
        <View style={styles.floatOverlay}>
          <View style={styles.floatCard}>

            {/* Card Header */}
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>
                {isEditing ? 'Edit Transaction' : 'Transaction Detail'}
              </Text>
              <TouchableOpacity onPress={() => {
                setDetailModalVisible(false);
                setIsEditing(false);
                resetForm();
              }}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>
              {isEditing ? (
                // ---- EDIT MODE ----
                <>
                  {renderForm()}
                  <TouchableOpacity
                    style={styles.saveButton}
                    onPress={handleUpdate}>
                    <Text style={styles.saveButtonText}>Update Transaction</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.cancelButton}
                    onPress={() => {
                      setIsEditing(false);
                      resetForm();
                    }}>
                    <Text style={styles.cancelButtonText}>Cancel</Text>
                  </TouchableOpacity>
                </>
              ) : (
                // ---- VIEW MODE ----
                <>
                  {selectedTransaction && (
                    <>
                      {/* Amount */}
                      <View style={styles.detailAmountRow}>
                        <Text style={[
                          styles.detailAmount,
                          selectedTransaction.type === 'Income'
                            ? styles.amountIncome
                            : styles.amountExpense,
                        ]}>
                          {selectedTransaction.type === 'Income' ? '+' : '-'}
                          ₱{typeof selectedTransaction.amount === 'number' ? selectedTransaction.amount.toFixed(2) : selectedTransaction.amount}
                        </Text>
                        <View style={[
                          styles.typeBadge,
                          selectedTransaction.type === 'Income'
                            ? styles.badgeIncome
                            : styles.badgeExpense,
                        ]}>
                          <Text style={styles.typeBadgeText}>
                            {selectedTransaction.type}
                          </Text>
                        </View>
                      </View>

                      {/* Info Rows */}
                      {renderDetailRow('Category', selectedTransaction.category)}
                      {renderDetailRow('Source', selectedTransaction.source)}
                      {renderDetailRow('Date', selectedTransaction.date)}
                      {renderDetailRow('Entry', selectedTransaction.entryMethod)}
                      {selectedTransaction.notes
                        ? renderDetailRow('Notes', selectedTransaction.notes)
                        : null}
                    </>
                  )}

                  {/* Action Buttons */}
                  <View style={styles.actionRow}>
                    <TouchableOpacity
                      style={styles.editButton}
                      onPress={handleStartEdit}>
                      <Text style={styles.editButtonText}>✏️ Edit</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={styles.deleteButton}
                      onPress={handleDelete}>
                      <Text style={styles.deleteButtonText}>🗑️ Delete</Text>
                    </TouchableOpacity>
                  </View>
                </>
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>

    </View>
  );

  // ----------------------------------------------------------------
  // Shared form fields — used in both Add and Edit mode
  // ----------------------------------------------------------------
  function renderForm() {
    return (
      <>
        {/* Type Toggle */}
        <Text style={styles.fieldLabel}>Type</Text>
        <View style={styles.toggleRow}>
          {['Income', 'Expense'].map(t => (
            <TouchableOpacity
              key={t}
              style={[
                styles.toggleButton,
                type === t && (t === 'Income'
                  ? styles.toggleActiveIncome
                  : styles.toggleActiveExpense),
              ]}
              onPress={() => { setType(t); setCategory(''); }}>
              <Text style={[
                styles.toggleText,
                type === t && styles.toggleTextActive,
              ]}>
                {t}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Amount */}
        <Text style={styles.fieldLabel}>Amount</Text>
        <View style={styles.amountInputRow}>
          <Text style={styles.pesoSign}>₱</Text>
          <TextInput
            style={styles.amountInput}
            placeholder="0.00"
            keyboardType="decimal-pad"
            value={amount}
            onChangeText={setAmount}
          />
        </View>
        {errors.amount && <Text style={styles.errorText}>{errors.amount}</Text>}

        {/* Source */}
        <Text style={styles.fieldLabel}>Source</Text>
        <View style={styles.chipRow}>
          {SOURCES.map(s => (
            <TouchableOpacity
              key={s}
              style={[styles.chip, source === s && styles.chipActive]}
              onPress={() => setSource(s)}>
              <Text style={[
                styles.chipText,
                source === s && styles.chipTextActive,
              ]}>
                {s}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Category */}
        <Text style={styles.fieldLabel}>Category</Text>
        <View style={styles.chipRow}>
          {categories.map(c => (
            <TouchableOpacity
              key={c}
              style={[styles.chip, category === c && styles.chipActive]}
              onPress={() => setCategory(c)}>
              <Text style={[
                styles.chipText,
                category === c && styles.chipTextActive,
              ]}>
                {c}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
        {errors.category && (
          <Text style={styles.errorText}>{errors.category}</Text>
        )}

        {/* Date */}
        <Text style={styles.fieldLabel}>Date</Text>
        <TextInput
          style={styles.input}
          placeholder="YYYY-MM-DD"
          value={date}
          onChangeText={setDate}
        />
        {errors.date && <Text style={styles.errorText}>{errors.date}</Text>}

        {/* Notes */}
        <Text style={styles.fieldLabel}>Notes (optional)</Text>
        <TextInput
          style={[styles.input, styles.notesInput]}
          placeholder="Add a note..."
          value={notes}
          onChangeText={setNotes}
          multiline
        />
      </>
    );
  }

  // Detail info row helper
  function renderDetailRow(label, value) {
    return (
      <View style={styles.detailRow} key={label}>
        <Text style={styles.detailLabel}>{label}</Text>
        <Text style={styles.detailValue}>{value}</Text>
      </View>
    );
  }
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  header: {
    backgroundColor: COLORS.bgDark,
    padding: 24,
    paddingTop: 40,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: COLORS.textDark,
    textAlign: 'center',
  },
  headerSub: {
    fontSize: 13,
    color: COLORS.textDark,
    marginTop: 4,
    textAlign: 'center',
    opacity: 0.7,
  },
  list: {
    padding: 16,
    paddingBottom: 120,
  },
  emptyState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#AAAAAA',
  },
  emptySubText: {
    fontSize: 13,
    color: '#CCCCCC',
    marginTop: 6,
  },
  transactionCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginBottom: 10,
    elevation: 1,
  },
  transactionRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  transactionLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  typeDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  dotIncome: {
    backgroundColor: '#2E7D32',
  },
  dotExpense: {
    backgroundColor: '#C62828',
  },
  transactionCategory: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  transactionSource: {
    fontSize: 12,
    color: '#888888',
    marginTop: 2,
  },
  transactionNotes: {
    fontSize: 12,
    color: '#AAAAAA',
    marginTop: 2,
  },
  transactionAmount: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  amountIncome: {
    color: '#2E7D32',
  },
  amountExpense: {
    color: '#C62828',
  },
  fab: {
    position: 'absolute',
    bottom: 100,
    right: 24,
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
  },
  fabText: {
    fontSize: 28,
    color: '#FFFFFF',
    lineHeight: 32,
  },

  // ---- Slide-up modal (Add) ----
  slideOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  slideContainer: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    maxHeight: '90%',
  },

  // ---- Floating card modal (Detail/Edit) ----
  floatOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  floatCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 20,
    width: '100%',
    maxHeight: '85%',
    elevation: 10,
  },

  // ---- Shared modal elements ----
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  modalClose: {
    fontSize: 18,
    color: '#888888',
  },

  // ---- Detail view ----
  detailAmountRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  detailAmount: {
    fontSize: 32,
    fontWeight: 'bold',
  },
  typeBadge: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
  },
  badgeIncome: {
    backgroundColor: '#E8F5E9',
  },
  badgeExpense: {
    backgroundColor: '#FFEBEE',
  },
  typeBadgeText: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F0F0',
  },
  detailLabel: {
    fontSize: 14,
    color: '#888888',
  },
  detailValue: {
    fontSize: 14,
    fontWeight: '500',
    color: '#1A1A1A',
    flexShrink: 1,
    textAlign: 'right',
    marginLeft: 12,
  },

  // ---- Action buttons ----
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 20,
  },
  editButton: {
    flex: 1,
    backgroundColor: '#E8F5E9',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  editButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.accent,
  },
  deleteButton: {
    flex: 1,
    backgroundColor: '#FFEBEE',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  deleteButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#C62828',
  },
  cancelButton: {
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 8,
    backgroundColor: '#F5F5F5',
  },
  cancelButtonText: {
    fontSize: 14,
    color: '#888888',
  },

  // ---- Form fields ----
  fieldLabel: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#555555',
    marginTop: 14,
    marginBottom: 6,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  toggleRow: {
    flexDirection: 'row',
    gap: 10,
  },
  toggleButton: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: '#F0F0F0',
    alignItems: 'center',
  },
  toggleActiveIncome: {
    backgroundColor: '#E8F5E9',
  },
  toggleActiveExpense: {
    backgroundColor: '#FFEBEE',
  },
  toggleText: {
    fontSize: 14,
    color: '#888888',
    fontWeight: 'bold',
  },
  toggleTextActive: {
    color: '#1A1A1A',
  },
  amountInputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  pesoSign: {
    fontSize: 18,
    color: '#1A1A1A',
    marginRight: 6,
  },
  amountInput: {
    flex: 1,
    fontSize: 24,
    paddingVertical: 10,
    color: '#1A1A1A',
  },
  input: {
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#1A1A1A',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  notesInput: {
    height: 80,
    textAlignVertical: 'top',
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    borderWidth: 1.5,
    borderColor: '#BBBBBB',
  },
  chipActive: {
    backgroundColor: COLORS.accent,
    borderColor: COLORS.accent,
  },
  chipText: {
    fontSize: 13,
    color: '#555555',
    fontWeight: '500',
  },
  chipTextActive: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  errorText: {
    fontSize: 12,
    color: '#C62828',
    marginTop: 4,
  },
  saveButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 8,
  },
  saveButtonText: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
});

export default TransactionsScreen;
