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
import { useStock } from '../../context/StockContext';
import { COLORS } from '../../theme';

const UNITS = ['kg', 'g', 'L', 'mL', 'pcs', 'bottles', 'packs', 'boxes'];

const StockScreen = () => {
  const {
    stockItems,
    getStatus,
    addStockItem,
    updateStockItem,
    deleteStockItem,
    deductIngredients,
  } = useStock();

  const [addModalVisible, setAddModalVisible] = useState(false);
  const [detailModalVisible, setDetailModalVisible] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);
  const [isEditing, setIsEditing] = useState(false);
  const [adjustValue, setAdjustValue] = useState('');
  const [adjustType, setAdjustType] = useState('add');
  const [search, setSearch] = useState('');

  // Form state
  const [name, setName] = useState('');
  const [quantity, setQuantity] = useState('');
  const [unit, setUnit] = useState('kg');
  const [threshold, setThreshold] = useState('');
  const [errors, setErrors] = useState({});

  const filteredItems = stockItems.filter(i =>
    i.name.toLowerCase().includes(search.toLowerCase())
  );

  const okCount = stockItems.filter(
    i => getStatus(i.quantity, i.threshold) === 'OK'
  ).length;
  const lowCount = stockItems.filter(
    i => getStatus(i.quantity, i.threshold) === 'Low'
  ).length;
  const outCount = stockItems.filter(
    i => getStatus(i.quantity, i.threshold) === 'Out'
  ).length;

  const getBadgeStyle = status => {
    switch (status) {
      case 'OK':
        return { badge: styles.badgeOk, text: styles.badgeOkText };
      case 'Low':
        return { badge: styles.badgeLow, text: styles.badgeLowText };
      case 'Out':
        return { badge: styles.badgeOut, text: styles.badgeOutText };
      default:
        return { badge: styles.badgeOk, text: styles.badgeOkText };
    }
  };

  const resetForm = () => {
    setName('');
    setQuantity('');
    setUnit('kg');
    setThreshold('');
    setErrors({});
  };

  const validate = () => {
    const newErrors = {};
    if (!name.trim()) newErrors.name = 'Please enter an item name.';
    if (!quantity || isNaN(parseFloat(quantity))) {
      newErrors.quantity = 'Please enter a valid quantity.';
    }
    if (!threshold || isNaN(parseFloat(threshold))) {
      newErrors.threshold = 'Please enter a low stock threshold.';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // ----------------------------------------------------------------
  // Add item
  // ----------------------------------------------------------------
  const handleAddItem = () => {
    if (!validate()) return;
    const newItem = {
      id: Date.now().toString(),
      name: name.trim(),
      quantity: parseFloat(quantity),
      unit,
      threshold: parseFloat(threshold),
    };
    addStockItem(newItem);
    setAddModalVisible(false);
    resetForm();
  };

  // ----------------------------------------------------------------
  // Open detail
  // ----------------------------------------------------------------
  const handleOpenDetail = item => {
    setSelectedItem(item);
    setIsEditing(false);
    setAdjustValue('');
    setAdjustType('add');
    setDetailModalVisible(true);
  };

  // ----------------------------------------------------------------
  // Adjust stock
  // ----------------------------------------------------------------
  const handleAdjust = () => {
    const val = parseFloat(adjustValue);
    if (isNaN(val) || val <= 0) return;

    const newQty = adjustType === 'add'
      ? selectedItem.quantity + val
      : Math.max(0, selectedItem.quantity - val);

    const updated = { ...selectedItem, quantity: newQty };
    updateStockItem(updated);
    setSelectedItem(updated);
    setAdjustValue('');
  };

  // ----------------------------------------------------------------
  // Start edit
  // ----------------------------------------------------------------
  const handleStartEdit = () => {
    setName(selectedItem.name);
    setQuantity(selectedItem.quantity.toString());
    setUnit(selectedItem.unit);
    setThreshold(selectedItem.threshold.toString());
    setErrors({});
    setIsEditing(true);
  };

  // ----------------------------------------------------------------
  // Save edit
  // ----------------------------------------------------------------
  const handleUpdate = () => {
    if (!validate()) return;
    const updated = {
      ...selectedItem,
      name: name.trim(),
      quantity: parseFloat(quantity),
      unit,
      threshold: parseFloat(threshold),
    };
    updateStockItem(updated);
    setSelectedItem(updated);
    setIsEditing(false);
    resetForm();
  };

  // ----------------------------------------------------------------
  // Delete item
  // ----------------------------------------------------------------
  const handleDelete = () => {
    deleteStockItem(selectedItem.id);
    setDetailModalVisible(false);
    setSelectedItem(null);
  };

  // ----------------------------------------------------------------
  // Render stock row
  // ----------------------------------------------------------------
  const renderItem = ({ item }) => {
    const status = getStatus(item.quantity, item.threshold);
    const { badge, text } = getBadgeStyle(status);
    return (
      <TouchableOpacity
        style={styles.stockRow}
        onPress={() => handleOpenDetail(item)}
        activeOpacity={0.7}>
        <View style={styles.stockLeft}>
          <View style={[styles.statusIndicator, {
            backgroundColor:
              status === 'OK' ? COLORS.income :
              status === 'Low' ? '#E65100' : COLORS.expense,
          }]} />
          <View>
            <Text style={styles.stockName}>{item.name}</Text>
            <Text style={styles.stockUnit}>
              {item.quantity} {item.unit} remaining
            </Text>
          </View>
        </View>
        <View style={[styles.badge, badge]}>
          <Text style={[styles.badgeText, text]}>{status}</Text>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Stock</Text>
        <Text style={styles.headerSub}>
          {stockItems.length} items • {lowCount + outCount} need attention
        </Text>
      </View>

      {/* Status Summary */}
      <View style={styles.summaryRow}>
        <View style={styles.summaryItem}>
          <Text style={styles.summaryCount}>{okCount}</Text>
          <Text style={[styles.summaryLabel, { color: COLORS.income }]}>OK</Text>
        </View>
        <View style={styles.summaryDivider} />
        <View style={styles.summaryItem}>
          <Text style={styles.summaryCount}>{lowCount}</Text>
          <Text style={[styles.summaryLabel, { color: '#E65100' }]}>Low</Text>
        </View>
        <View style={styles.summaryDivider} />
        <View style={styles.summaryItem}>
          <Text style={styles.summaryCount}>{outCount}</Text>
          <Text style={[styles.summaryLabel, { color: COLORS.expense }]}>Out</Text>
        </View>
      </View>

      {/* Search */}
      <View style={styles.searchWrap}>
        <TextInput
          style={styles.searchInput}
          placeholder="Search stock items..."
          value={search}
          onChangeText={setSearch}
        />
      </View>

      {/* Stock List */}
      {filteredItems.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyText}>No stock items found.</Text>
          <Text style={styles.emptySubText}>
            Tap + to add a new stock item.
          </Text>
        </View>
      ) : (
        <FlatList
          data={filteredItems}
          keyExtractor={item => item.id}
          renderItem={renderItem}
          contentContainerStyle={styles.list}
        />
      )}

      {/* FAB */}
      <TouchableOpacity
        style={styles.fab}
        onPress={() => setAddModalVisible(true)}>
        <Text style={styles.fabText}>+</Text>
      </TouchableOpacity>

      {/* ============================================================
          ADD ITEM MODAL
      ============================================================ */}
      <Modal
        visible={addModalVisible}
        animationType="slide"
        transparent
        onRequestClose={() => {
          setAddModalVisible(false);
          resetForm();
        }}>
        <View style={styles.slideOverlay}>
          <View style={styles.slideContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Add stock item</Text>
              <TouchableOpacity onPress={() => {
                setAddModalVisible(false);
                resetForm();
              }}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>
            <ScrollView showsVerticalScrollIndicator={false}>
              {renderForm()}
              <TouchableOpacity
                style={styles.saveButton}
                onPress={handleAddItem}>
                <Text style={styles.saveButtonText}>Add item</Text>
              </TouchableOpacity>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ============================================================
          DETAIL / EDIT MODAL
      ============================================================ */}
      <Modal
        visible={detailModalVisible}
        animationType="fade"
        transparent
        onRequestClose={() => {
          setDetailModalVisible(false);
          setIsEditing(false);
          resetForm();
        }}>
        <View style={styles.floatOverlay}>
          <View style={styles.floatCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>
                {isEditing ? 'Edit item' : selectedItem?.name}
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
                <>
                  {renderForm()}
                  <TouchableOpacity
                    style={styles.saveButton}
                    onPress={handleUpdate}>
                    <Text style={styles.saveButtonText}>Update item</Text>
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
                selectedItem && (() => {
                  const status = getStatus(
                    selectedItem.quantity,
                    selectedItem.threshold
                  );
                  const { badge, text } = getBadgeStyle(status);
                  return (
                    <>
                      {/* Current stock */}
                      <View style={styles.detailQtyRow}>
                        <View>
                          <Text style={styles.detailQty}>
                            {selectedItem.quantity} {selectedItem.unit}
                          </Text>
                          <Text style={styles.detailThreshold}>
                            Low stock at {selectedItem.threshold} {selectedItem.unit}
                          </Text>
                        </View>
                        <View style={[styles.badge, badge]}>
                          <Text style={[styles.badgeText, text]}>{status}</Text>
                        </View>
                      </View>

                      {/* Progress bar */}
                      <View style={styles.progressTrack}>
                        <View style={[styles.progressFill, {
                          width: `${Math.min(
                            (selectedItem.quantity /
                              Math.max(
                                selectedItem.quantity,
                                selectedItem.threshold * 3
                              )
                            ) * 100,
                            100
                          )}%`,
                          backgroundColor:
                            status === 'OK' ? COLORS.income :
                            status === 'Low' ? '#E65100' : COLORS.expense,
                        }]} />
                      </View>

                      {/* Alert banner */}
                      {status !== 'OK' && (
                        <View style={[
                          styles.alertBanner,
                          {
                            backgroundColor:
                              status === 'Out' ? '#FFEBEE' : '#FFF3E0',
                          },
                        ]}>
                          <Text style={[
                            styles.alertText,
                            {
                              color:
                                status === 'Out' ? COLORS.expense : '#E65100',
                            },
                          ]}>
                            {status === 'Out'
                              ? '⚠️ Out of stock — restock immediately.'
                              : '⚠️ Running low — consider restocking soon.'}
                          </Text>
                        </View>
                      )}

                      {/* Adjustment section */}
                      <Text style={styles.fieldLabel}>Adjust quantity</Text>
                      <View style={styles.adjustTypeRow}>
                        {['add', 'deduct'].map(t => (
                          <TouchableOpacity
                            key={t}
                            style={[
                              styles.toggleButton,
                              adjustType === t && (t === 'add'
                                ? styles.toggleActiveAdd
                                : styles.toggleActiveDeduct),
                            ]}
                            onPress={() => setAdjustType(t)}>
                            <Text style={[
                              styles.toggleText,
                              adjustType === t && styles.toggleTextActive,
                            ]}>
                              {t === 'add' ? '+ Add stock' : '- Deduct stock'}
                            </Text>
                          </TouchableOpacity>
                        ))}
                      </View>
                      <View style={styles.adjustRow}>
                        <TextInput
                          style={styles.adjustInput}
                          placeholder={`Amount in ${selectedItem.unit}`}
                          keyboardType="decimal-pad"
                          value={adjustValue}
                          onChangeText={setAdjustValue}
                        />
                        <TouchableOpacity
                          style={styles.adjustButton}
                          onPress={handleAdjust}>
                          <Text style={styles.adjustButtonText}>Apply</Text>
                        </TouchableOpacity>
                      </View>

                      {/* Actions */}
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
                  );
                })()
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>

    </View>
  );

  function renderForm() {
    return (
      <>
        <Text style={styles.fieldLabel}>Item name</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. Fresh milk"
          value={name}
          onChangeText={setName}
        />
        {errors.name && (
          <Text style={styles.errorText}>{errors.name}</Text>
        )}

        <Text style={styles.fieldLabel}>Quantity</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. 5000"
          keyboardType="decimal-pad"
          value={quantity}
          onChangeText={setQuantity}
        />
        {errors.quantity && (
          <Text style={styles.errorText}>{errors.quantity}</Text>
        )}

        <Text style={styles.fieldLabel}>Unit</Text>
        <View style={styles.chipRow}>
          {UNITS.map(u => (
            <TouchableOpacity
              key={u}
              style={[styles.chip, unit === u && styles.chipActive]}
              onPress={() => setUnit(u)}>
              <Text style={[
                styles.chipText,
                unit === u && styles.chipTextActive,
              ]}>
                {u}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text style={styles.fieldLabel}>Low stock threshold</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. 500 (shows Low badge below this)"
          keyboardType="decimal-pad"
          value={threshold}
          onChangeText={setThreshold}
        />
        {errors.threshold && (
          <Text style={styles.errorText}>{errors.threshold}</Text>
        )}
      </>
    );
  }
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F5F5F5' },
  header: {
    backgroundColor: COLORS.accent,
    padding: 24,
    paddingTop: 48,
  },
  headerTitle: { fontSize: 24, fontWeight: 'bold', color: '#0A2E2A' },
  headerSub: { fontSize: 13, color: 'rgba(0,0,0,0.5)', marginTop: 4 },
  summaryRow: {
    flexDirection: 'row',
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 0.5,
    borderBottomColor: '#E0E0E0',
  },
  summaryItem: { flex: 1, padding: 14, alignItems: 'center' },
  summaryCount: { fontSize: 20, fontWeight: 'bold', color: '#0A2E2A' },
  summaryLabel: { fontSize: 11, marginTop: 2, fontWeight: 'bold' },
  summaryDivider: { width: 0.5, backgroundColor: '#E0E0E0' },
  searchWrap: { padding: 12, paddingBottom: 8 },
  searchInput: {
    backgroundColor: '#FFFFFF',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 13,
    borderWidth: 0.5,
    borderColor: '#E0E0E0',
  },
  list: { padding: 12, paddingBottom: 100 },
  emptyState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
  },
  emptyText: { fontSize: 15, fontWeight: 'bold', color: '#AAAAAA' },
  emptySubText: { fontSize: 13, color: '#CCCCCC', marginTop: 6 },
  stockRow: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 0.5,
    borderColor: '#E0E0E0',
  },
  stockLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  statusIndicator: {
    width: 4,
    height: 40,
    borderRadius: 2,
  },
  stockName: { fontSize: 14, fontWeight: 'bold', color: '#0A2E2A' },
  stockUnit: { fontSize: 12, color: '#888888', marginTop: 2 },
  badge: { paddingHorizontal: 12, paddingVertical: 4, borderRadius: 20 },
  badgeText: { fontSize: 12, fontWeight: 'bold' },
  badgeOk: { backgroundColor: '#E8F5E9' },
  badgeOkText: { color: '#2E7D32' },
  badgeLow: { backgroundColor: '#FFF3E0' },
  badgeLowText: { color: '#E65100' },
  badgeOut: { backgroundColor: '#FFEBEE' },
  badgeOutText: { color: '#C62828' },
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
  fabText: { fontSize: 28, color: '#FFFFFF', lineHeight: 32 },
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
    maxHeight: '88%',
    elevation: 10,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: { fontSize: 18, fontWeight: 'bold', color: '#0A2E2A' },
  modalClose: { fontSize: 18, color: '#888888' },
  detailQtyRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 0.5,
    borderBottomColor: '#F0F0F0',
  },
  detailQty: { fontSize: 28, fontWeight: 'bold', color: '#0A2E2A' },
  detailThreshold: { fontSize: 12, color: '#888888', marginTop: 4 },
  progressTrack: {
    backgroundColor: '#F0F0F0',
    borderRadius: 4,
    height: 8,
    overflow: 'hidden',
    marginBottom: 12,
  },
  progressFill: { height: 8, borderRadius: 4 },
  alertBanner: {
    borderRadius: 10,
    padding: 12,
    marginBottom: 12,
  },
  alertText: { fontSize: 12, lineHeight: 18 },
  fieldLabel: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#555555',
    marginTop: 14,
    marginBottom: 6,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  adjustTypeRow: { flexDirection: 'row', gap: 10, marginBottom: 10 },
  adjustRow: { flexDirection: 'row', gap: 10, marginBottom: 16 },
  adjustInput: {
    flex: 1,
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 16,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  adjustButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 8,
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  adjustButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  toggleButton: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: '#F0F0F0',
    alignItems: 'center',
  },
  toggleActiveAdd: { backgroundColor: '#E8F5E9' },
  toggleActiveDeduct: { backgroundColor: '#FFEBEE' },
  toggleText: { fontSize: 13, color: '#888888', fontWeight: 'bold' },
  toggleTextActive: { color: '#0A2E2A' },
  actionRow: { flexDirection: 'row', gap: 10, marginTop: 8 },
  editButton: {
    flex: 1,
    backgroundColor: '#E8FBF5',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  editButtonText: { fontSize: 14, fontWeight: 'bold', color: COLORS.accent },
  deleteButton: {
    flex: 1,
    backgroundColor: '#FFEBEE',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  deleteButtonText: { fontSize: 14, fontWeight: 'bold', color: COLORS.expense },
  cancelButton: {
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 8,
    backgroundColor: '#F5F5F5',
  },
  cancelButtonText: { fontSize: 14, color: '#888888' },
  input: {
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#0A2E2A',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  chipActive: { backgroundColor: COLORS.accent, borderColor: COLORS.accent },
  chipText: { fontSize: 13, color: '#555555' },
  chipTextActive: { color: '#FFFFFF', fontWeight: 'bold' },
  errorText: { fontSize: 12, color: COLORS.expense, marginTop: 4 },
  saveButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 8,
  },
  saveButtonText: { fontSize: 15, fontWeight: 'bold', color: '#FFFFFF' },
});

export default StockScreen;