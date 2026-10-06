import React, {useMemo, useState} from 'react';
import {
  ActivityIndicator, Alert, FlatList, Image, Modal, RefreshControl, ScrollView,
  StyleSheet, Text, TextInput, TouchableOpacity, View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {launchImageLibrary} from 'react-native-image-picker';
import {useStock} from '../../context/StockContext';
import {useProducts} from '../../context/ProductContext';
import {COLORS} from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const emptyProductForm = {
  name: '', category: 'Coffee', price: '', emoji: '☕', description: '',
  isAvailable: true, ingredients: [], image: null, imageAsset: null,
};

const emptyStockForm = {name: '', unit: 'pcs', quantity: '', threshold: '', unitCost: ''};

const productToForm = product => ({
  name: product.name || '',
  category: product.category || 'Coffee',
  price: String(product.price ?? ''),
  emoji: product.emoji || '☕',
  description: product.description || '',
  isAvailable: product.is_available !== false,
  image: product.image || null,
  imageAsset: null,
  ingredients: (product.ingredients || []).map(ingredient => ({
    stockId: Number(ingredient.stockId),
    quantity: String(ingredient.quantity ?? ''),
  })),
});

const StockScreen = () => {
  const {
    stockItems, isLoading, isLoadingMore, hasMoreStock,
    fetchStock, loadMoreStock, getStatus, adjustStock, createStock,
  } = useStock();
  const {
    products, fetchProducts, createProduct, updateProduct, deleteProduct,
    PRODUCT_CATEGORIES,
  } = useProducts();
  const [activeTab, setActiveTab] = useState('stock');
  const [search, setSearch] = useState('');
  const [selectedItem, setSelectedItem] = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [adjustType, setAdjustType] = useState('add');
  const [adjustValue, setAdjustValue] = useState('');
  const [adjustReason, setAdjustReason] = useState('');
  const [isAdjusting, setIsAdjusting] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [productEditorVisible, setProductEditorVisible] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);
  const [productForm, setProductForm] = useState(emptyProductForm);
  const [isSavingProduct, setIsSavingProduct] = useState(false);
  const [stockEditorVisible, setStockEditorVisible] = useState(false);
  const [stockForm, setStockForm] = useState(emptyStockForm);
  const [isSavingStock, setIsSavingStock] = useState(false);

  const filteredItems = useMemo(() => {
    const source = activeTab === 'stock' ? stockItems : products;
    const term = search.trim().toLowerCase();
    return term ? source.filter(item => item.name?.toLowerCase().includes(term)) : source;
  }, [activeTab, products, search, stockItems]);

  const statusCounts = useMemo(() => stockItems.reduce((counts, item) => {
    counts[getStatus(item.quantity, item.threshold)] += 1;
    return counts;
  }, {OK: 0, Low: 0, Out: 0}), [getStatus, stockItems]);

  const refresh = async () => {
    setIsRefreshing(true);
    try { await Promise.all([fetchStock(), fetchProducts()]); }
    finally { setIsRefreshing(false); }
  };

  const openStock = item => {
    setSelectedItem(item);
    setAdjustType('add');
    setAdjustValue('');
    setAdjustReason('');
  };

  const openCreateStock = () => {
    setStockForm(emptyStockForm);
    setStockEditorVisible(true);
  };

  const setStockField = (field, value) => setStockForm(current => ({...current, [field]: value}));

  const saveStock = async () => {
    const quantity = Number(stockForm.quantity);
    const threshold = Number(stockForm.threshold);
    const unitCost = Number(stockForm.unitCost);
    if (!stockForm.name.trim() || !stockForm.unit.trim()) {
      Alert.alert('Missing details', 'Enter the stock name and unit.');
      return;
    }
    if (![quantity, threshold, unitCost].every(value => Number.isFinite(value) && value >= 0)) {
      Alert.alert('Invalid values', 'Quantity, low-stock level, and unit cost must be zero or greater.');
      return;
    }
    setIsSavingStock(true);
    try {
      await createStock({
        name: stockForm.name.trim(),
        unit: stockForm.unit.trim(),
        current_quantity: quantity,
        minimum_quantity: threshold,
        unit_cost: unitCost,
      });
      setStockEditorVisible(false);
      setStockForm(emptyStockForm);
      Alert.alert('Stock item added', 'The new stock item is ready to use.');
    } catch (error) {
      Alert.alert('Could not add stock item', apiErrorMessage(error));
    } finally {
      setIsSavingStock(false);
    }
  };

  const handleAdjust = async () => {
    const quantity = Number(adjustValue);
    if (!Number.isFinite(quantity) || quantity <= 0) {
      Alert.alert('Invalid quantity', 'Enter an amount greater than zero.');
      return;
    }
    if (adjustType === 'deduct' && quantity > selectedItem.quantity) {
      Alert.alert('Not enough stock', `Only ${selectedItem.quantity} ${selectedItem.unit} is available.`);
      return;
    }
    setIsAdjusting(true);
    try {
      const updated = await adjustStock(
        selectedItem.id, adjustType, quantity,
        adjustReason.trim() || 'Manual stock adjustment',
      );
      setSelectedItem(updated);
      setAdjustValue('');
      setAdjustReason('');
      Alert.alert('Stock updated', `${updated.name} now has ${updated.quantity} ${updated.unit}.`);
    } catch (error) {
      const data = error.response?.data;
      const details = Object.values(data?.errors || {}).flat().join('\n');
      Alert.alert('Could not adjust stock', details || data?.message || 'Please try again.');
    } finally { setIsAdjusting(false); }
  };

  const openCreateProduct = () => {
    setEditingProduct(null);
    setProductForm({...emptyProductForm, ingredients: []});
    setProductEditorVisible(true);
  };

  const openEditProduct = product => {
    setEditingProduct(product);
    setProductForm(productToForm(product));
    setSelectedProduct(null);
    setProductEditorVisible(true);
  };

  const setProductField = (field, value) => {
    setProductForm(current => ({...current, [field]: value}));
  };

  const pickProductImage = async () => {
    const result = await launchImageLibrary({
      mediaType: 'photo',
      selectionLimit: 1,
      quality: 0.85,
      maxWidth: 1600,
      maxHeight: 1600,
    });
    if (result.didCancel) return;
    if (result.errorCode) {
      Alert.alert('Could not open photos', result.errorMessage || 'Please try again.');
      return;
    }
    const asset = result.assets?.[0];
    if (asset?.uri) {
      setProductForm(current => ({...current, image: asset.uri, imageAsset: asset}));
    }
  };

  const toggleIngredient = stockId => {
    setProductForm(current => {
      const selected = current.ingredients.some(item => item.stockId === stockId);
      return {
        ...current,
        ingredients: selected
          ? current.ingredients.filter(item => item.stockId !== stockId)
          : [...current.ingredients, {stockId, quantity: '1'}],
      };
    });
  };

  const setIngredientQuantity = (stockId, quantity) => {
    setProductForm(current => ({
      ...current,
      ingredients: current.ingredients.map(item => item.stockId === stockId ? {...item, quantity} : item),
    }));
  };

  const apiErrorMessage = error => {
    const data = error.response?.data;
    return Object.values(data?.errors || {}).flat().join('\n') || data?.message || 'Please try again.';
  };

  const saveProduct = async () => {
    const price = Number(productForm.price);
    if (!productForm.name.trim() || !productForm.category || !Number.isFinite(price) || price < 0) {
      Alert.alert('Check product details', 'Enter a product name, category, and valid price.');
      return;
    }
    if (productForm.ingredients.some(item => !Number.isFinite(Number(item.quantity)) || Number(item.quantity) <= 0)) {
      Alert.alert('Check recipe', 'Every selected ingredient needs a quantity greater than zero.');
      return;
    }

    setIsSavingProduct(true);
    try {
      const saved = editingProduct
        ? await updateProduct(editingProduct.id, productForm)
        : await createProduct(productForm);
      setProductEditorVisible(false);
      setEditingProduct(null);
      setSelectedProduct(saved);
      Alert.alert('Product saved', `${saved.name} is ready in the product list.`);
    } catch (error) {
      Alert.alert('Could not save product', apiErrorMessage(error));
    } finally {
      setIsSavingProduct(false);
    }
  };

  const toggleProductAvailability = async product => {
    try {
      const updated = await updateProduct(product.id, {
        ...productToForm(product),
        isAvailable: product.is_available === false,
      });
      setSelectedProduct(updated);
    } catch (error) {
      Alert.alert('Could not update product', apiErrorMessage(error));
    }
  };

  const confirmDeleteProduct = product => {
    Alert.alert(
      'Delete product?',
      `${product.name} will be removed from the ordering menu.`,
      [
        {text: 'Cancel', style: 'cancel'},
        {text: 'Delete', style: 'destructive', onPress: async () => {
          try {
            await deleteProduct(product.id);
            setSelectedProduct(null);
          } catch (error) {
            Alert.alert('Could not delete product', apiErrorMessage(error));
          }
        }},
      ],
    );
  };

  const badgeStyles = status => ({
    OK: [styles.badgeOk, styles.badgeOkText],
    Low: [styles.badgeLow, styles.badgeLowText],
    Out: [styles.badgeOut, styles.badgeOutText],
  }[status]);

  const renderStockItem = ({item}) => {
    const status = getStatus(item.quantity, item.threshold);
    const [badge, badgeText] = badgeStyles(status);
    return (
      <TouchableOpacity style={styles.card} onPress={() => openStock(item)} activeOpacity={0.75}>
        <View style={[styles.iconBox, status === 'OK' ? styles.iconOk : styles.iconWarning]}>
          <Icon name="package-variant" size={24} color={status === 'OK' ? COLORS.accent : COLORS.expense} />
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle}>{item.name}</Text>
          <Text style={styles.cardSubtitle}>{item.quantity} {item.unit} remaining</Text>
        </View>
        <View style={[styles.badge, badge]}><Text style={[styles.badgeText, badgeText]}>{status}</Text></View>
      </TouchableOpacity>
    );
  };

  const renderProductItem = ({item}) => {
    const available = item.is_available !== false;
    return (
      <TouchableOpacity style={styles.card} onPress={() => setSelectedProduct(item)} activeOpacity={0.75}>
        <View style={styles.iconBox}>
          {item.image
            ? <Image source={{uri: item.image}} style={styles.productThumbnail} />
            : <Icon name="image-outline" size={24} color={COLORS.textGray} />}
        </View>
        <View style={styles.cardBody}>
          <Text style={styles.cardTitle}>{item.name}</Text>
          <Text style={styles.cardSubtitle}>{item.category || 'Uncategorized'} · ₱{Number(item.price || 0).toFixed(2)}</Text>
        </View>
        <View style={[styles.badge, available ? styles.badgeOk : styles.badgeOut]}>
          <Text style={[styles.badgeText, available ? styles.badgeOkText : styles.badgeOutText]}>{available ? 'Available' : 'Unavailable'}</Text>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Inventory"
        actionIcon="plus"
        actionLabel={activeTab === 'products' ? 'Add product' : 'Add stock item'}
        onActionPress={activeTab === 'products' ? openCreateProduct : openCreateStock}
      />

      <View style={styles.tabs}>
        {[
          ['stock', 'package-variant', 'Stock Items'], ['products', 'food', 'Products'],
        ].map(([key, icon, label]) => (
          <TouchableOpacity key={key} style={[styles.tab, activeTab === key && styles.tabActive]} onPress={() => setActiveTab(key)}>
            <Icon name={icon} size={20} color={activeTab === key ? COLORS.accent : COLORS.textGray} />
            <Text style={[styles.tabText, activeTab === key && styles.tabTextActive]}>{label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {activeTab === 'stock' && (
        <View style={styles.summary}>
          {[
            ['OK', statusCounts.OK, COLORS.income], ['Low', statusCounts.Low, '#E65100'], ['Out', statusCounts.Out, COLORS.expense],
          ].map(([label, count, color]) => (
            <View key={label} style={styles.summaryItem}>
              <Text style={styles.summaryCount}>{count}</Text><Text style={[styles.summaryLabel, {color}]}>{label}</Text>
            </View>
          ))}
        </View>
      )}

      <View style={styles.searchBox}>
        <Icon name="magnify" size={21} color={COLORS.textGray} />
        <TextInput style={styles.searchInput} placeholder={`Search ${activeTab === 'stock' ? 'stock items' : 'products'}...`} value={search} onChangeText={setSearch} />
      </View>

      {isLoading && !isRefreshing && filteredItems.length === 0 ? (
        <View style={styles.centerState}><ActivityIndicator color={COLORS.accent} size="large" /><Text style={styles.stateText}>Loading inventory...</Text></View>
      ) : (
        <FlatList
          data={filteredItems}
          keyExtractor={item => String(item.id)}
          renderItem={activeTab === 'stock' ? renderStockItem : renderProductItem}
          contentContainerStyle={filteredItems.length ? styles.list : styles.emptyList}
          refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={refresh} colors={[COLORS.accent]} />}
          ListEmptyComponent={<View style={styles.centerState}>
            <Icon name={activeTab === 'stock' ? 'package-variant-closed' : 'food-off'} size={58} color="#C9CEC9" />
            <Text style={styles.emptyTitle}>No {activeTab === 'stock' ? 'stock items' : 'products'} found</Text>
            <Text style={styles.stateText}>{search ? 'Try a different search.' : activeTab === 'products' ? 'Tap + to add the first product.' : 'Tap + to add the first stock item.'}</Text>
          </View>}
          ListFooterComponent={isLoadingMore ? <ActivityIndicator style={styles.footerLoader} color={COLORS.accent} /> : null}
          onEndReached={activeTab === 'stock' && hasMoreStock ? loadMoreStock : undefined}
          onEndReachedThreshold={0.35}
        />
      )}

      <Modal visible={stockEditorVisible} transparent animationType="slide" onRequestClose={() => setStockEditorVisible(false)}>
        <View style={styles.modalOverlay}><View style={styles.modalCard}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Add stock item</Text>
            <TouchableOpacity onPress={() => setStockEditorVisible(false)} accessibilityLabel="Close stock item form"><Icon name="close" size={24} color={COLORS.textGray} /></TouchableOpacity>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled">
            <Text style={styles.fieldLabel}>Stock name</Text>
            <TextInput style={styles.input} value={stockForm.name} onChangeText={value => setStockField('name', value)} placeholder="e.g. Fresh milk" />
            <Text style={styles.fieldLabel}>Unit</Text>
            <TextInput style={styles.input} value={stockForm.unit} onChangeText={value => setStockField('unit', value)} placeholder="pcs, ml, g, kg" />
            <Text style={styles.fieldLabel}>Starting quantity</Text>
            <TextInput style={styles.input} value={stockForm.quantity} onChangeText={value => setStockField('quantity', value)} keyboardType="decimal-pad" placeholder="0" />
            <Text style={styles.fieldLabel}>Low-stock level</Text>
            <TextInput style={styles.input} value={stockForm.threshold} onChangeText={value => setStockField('threshold', value)} keyboardType="decimal-pad" placeholder="0" />
            <Text style={styles.fieldLabel}>Unit cost</Text>
            <TextInput style={styles.input} value={stockForm.unitCost} onChangeText={value => setStockField('unitCost', value)} keyboardType="decimal-pad" placeholder="0.00" />
            <TouchableOpacity style={[styles.primaryButton, isSavingStock && styles.disabledButton]} onPress={saveStock} disabled={isSavingStock}>
              {isSavingStock ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.primaryButtonText}>Add stock item</Text>}
            </TouchableOpacity>
          </ScrollView>
        </View></View>
      </Modal>

      <Modal visible={Boolean(selectedItem)} transparent animationType="fade" onRequestClose={() => setSelectedItem(null)}>
        <View style={styles.modalOverlay}><View style={styles.modalCard}>
          <View style={styles.modalHeader}>
            <View><Text style={styles.modalTitle}>{selectedItem?.name}</Text><Text style={styles.modalSubtitle}>Adjust the physical stock count</Text></View>
            <TouchableOpacity onPress={() => setSelectedItem(null)}><Icon name="close" size={24} color={COLORS.textGray} /></TouchableOpacity>
          </View>
          {selectedItem && <ScrollView keyboardShouldPersistTaps="handled">
            <View style={styles.quantityPanel}>
              <Text style={styles.quantityValue}>{selectedItem.quantity} {selectedItem.unit}</Text>
              <Text style={styles.quantityHint}>Low-stock level: {selectedItem.threshold} {selectedItem.unit}</Text>
            </View>
            <Text style={styles.fieldLabel}>Adjustment</Text>
            <View style={styles.adjustToggle}>{['add', 'deduct'].map(value => (
              <TouchableOpacity key={value} style={[styles.toggleButton, adjustType === value && styles.toggleButtonActive]} onPress={() => setAdjustType(value)}>
                <Text style={[styles.toggleText, adjustType === value && styles.toggleTextActive]}>{value === 'add' ? 'Add stock' : 'Deduct stock'}</Text>
              </TouchableOpacity>
            ))}</View>
            <Text style={styles.fieldLabel}>Quantity ({selectedItem.unit})</Text>
            <TextInput style={styles.input} keyboardType="decimal-pad" value={adjustValue} onChangeText={setAdjustValue} placeholder="0.00" />
            <Text style={styles.fieldLabel}>Reason (optional)</Text>
            <TextInput style={styles.input} value={adjustReason} onChangeText={setAdjustReason} placeholder="Delivery, wastage, stock count..." />
            <TouchableOpacity style={[styles.primaryButton, isAdjusting && styles.disabledButton]} onPress={handleAdjust} disabled={isAdjusting}>
              {isAdjusting ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.primaryButtonText}>Apply adjustment</Text>}
            </TouchableOpacity>
            <Text style={styles.ownerNote}>Item details and deletions are managed by the business owner.</Text>
          </ScrollView>}
        </View></View>
      </Modal>

      <Modal visible={Boolean(selectedProduct)} transparent animationType="fade" onRequestClose={() => setSelectedProduct(null)}>
        <View style={styles.modalOverlay}><View style={styles.modalCard}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>{selectedProduct?.name}</Text>
            <TouchableOpacity onPress={() => setSelectedProduct(null)}><Icon name="close" size={24} color={COLORS.textGray} /></TouchableOpacity>
          </View>
          {selectedProduct && <ScrollView>
            <View style={styles.productHero}>
              {selectedProduct.image
                ? <Image source={{uri: selectedProduct.image}} style={styles.productHeroImage} />
                : <View style={styles.noImage}><Icon name="image-outline" size={38} color={COLORS.textMuted} /><Text style={styles.noImageText}>No product photo</Text></View>}
            </View>
            <View style={styles.detailRow}><Text style={styles.detailLabel}>Price</Text><Text style={styles.detailValue}>₱{Number(selectedProduct.price || 0).toFixed(2)}</Text></View>
            <View style={styles.detailRow}><Text style={styles.detailLabel}>Category</Text><Text style={styles.detailValue}>{selectedProduct.category || 'Uncategorized'}</Text></View>
            <View style={styles.detailRow}><Text style={styles.detailLabel}>Status</Text><Text style={styles.detailValue}>{selectedProduct.is_available === false ? 'Unavailable' : 'Available'}</Text></View>
            {selectedProduct.description ? <Text style={styles.description}>{selectedProduct.description}</Text> : null}
            <Text style={styles.fieldLabel}>Recipe ingredients</Text>
            {(selectedProduct.ingredients || []).length ? selectedProduct.ingredients.map((ingredient, index) => (
              <View key={`${ingredient.stockId}-${index}`} style={styles.ingredientRow}>
                <Text style={styles.ingredientName}>{ingredient.name || 'Stock item'}</Text><Text style={styles.ingredientQuantity}>{ingredient.quantity}</Text>
              </View>
            )) : <Text style={styles.ownerNote}>No recipe ingredients configured.</Text>}
            <View style={styles.productActions}>
              <TouchableOpacity style={styles.secondaryButton} onPress={() => openEditProduct(selectedProduct)}>
                <Icon name="pencil-outline" size={18} color={COLORS.accentDark} />
                <Text style={styles.secondaryButtonText}>Edit product</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.secondaryButton} onPress={() => toggleProductAvailability(selectedProduct)}>
                <Icon name={selectedProduct.is_available === false ? 'eye-outline' : 'eye-off-outline'} size={18} color={COLORS.accentDark} />
                <Text style={styles.secondaryButtonText}>{selectedProduct.is_available === false ? 'Make available' : 'Make unavailable'}</Text>
              </TouchableOpacity>
            </View>
            <TouchableOpacity style={styles.deleteButton} onPress={() => confirmDeleteProduct(selectedProduct)}>
              <Icon name="trash-can-outline" size={18} color={COLORS.danger} />
              <Text style={styles.deleteButtonText}>Delete product</Text>
            </TouchableOpacity>
          </ScrollView>}
        </View></View>
      </Modal>

      <Modal visible={productEditorVisible} transparent animationType="slide" onRequestClose={() => setProductEditorVisible(false)}>
        <View style={styles.modalOverlay}><View style={styles.modalCard}>
          <View style={styles.modalHeader}>
            <View>
              <Text style={styles.modalTitle}>{editingProduct ? 'Edit product' : 'Add product'}</Text>
              <Text style={styles.modalSubtitle}>Set menu details and stock recipe</Text>
            </View>
            <TouchableOpacity onPress={() => setProductEditorVisible(false)} accessibilityLabel="Close product form">
              <Icon name="close" size={24} color={COLORS.textGray} />
            </TouchableOpacity>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <Text style={styles.fieldLabel}>Product name</Text>
            <TextInput style={styles.input} value={productForm.name} onChangeText={value => setProductField('name', value)} placeholder="e.g. Caramel Latte" />

            <Text style={styles.fieldLabel}>Product photo</Text>
            <TouchableOpacity style={styles.imagePicker} onPress={pickProductImage} activeOpacity={0.8}>
              {productForm.image
                ? <Image source={{uri: productForm.image}} style={styles.imagePreview} />
                : <View style={styles.imagePlaceholder}><Icon name="image-plus" size={34} color={COLORS.accentDark} /><Text style={styles.imagePlaceholderTitle}>Upload product poster</Text><Text style={styles.imagePlaceholderHint}>JPG, PNG or WebP · Maximum 5 MB</Text></View>}
              <View style={styles.imagePickerBadge}><Icon name="camera-outline" size={16} color="#FFFFFF" /><Text style={styles.imagePickerBadgeText}>{productForm.image ? 'Change photo' : 'Choose photo'}</Text></View>
            </TouchableOpacity>

            <Text style={styles.fieldLabel}>Price</Text>
            <View style={styles.priceInputWrap}>
              <Text style={styles.peso}>₱</Text>
              <TextInput style={styles.priceInput} value={productForm.price} onChangeText={value => setProductField('price', value)} keyboardType="decimal-pad" placeholder="0.00" />
            </View>

            <Text style={styles.fieldLabel}>Category</Text>
            <View style={styles.categoryOptions}>
              {PRODUCT_CATEGORIES.map(category => (
                <TouchableOpacity key={category} style={[styles.categoryOption, productForm.category === category && styles.categoryOptionActive]} onPress={() => setProductField('category', category)}>
                  <Text style={[styles.categoryOptionText, productForm.category === category && styles.categoryOptionTextActive]}>{category}</Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={styles.fieldLabel}>Description (optional)</Text>
            <TextInput style={[styles.input, styles.descriptionInput]} value={productForm.description} onChangeText={value => setProductField('description', value)} placeholder="Short product description" multiline />

            <TouchableOpacity style={styles.availabilityRow} onPress={() => setProductField('isAvailable', !productForm.isAvailable)}>
              <View><Text style={styles.availabilityTitle}>Available for ordering</Text><Text style={styles.availabilityHint}>Unavailable products stay saved but cannot be ordered.</Text></View>
              <Icon name={productForm.isAvailable ? 'toggle-switch' : 'toggle-switch-off-outline'} size={38} color={productForm.isAvailable ? COLORS.accent : COLORS.textMuted} />
            </TouchableOpacity>

            <Text style={styles.fieldLabel}>Recipe ingredients</Text>
            <Text style={styles.recipeHint}>Choose the stock used for one order and enter its quantity.</Text>
            {stockItems.length ? stockItems.map(stock => {
              const ingredient = productForm.ingredients.find(item => item.stockId === Number(stock.id));
              return (
                <View key={stock.id} style={[styles.recipeRow, ingredient && styles.recipeRowSelected]}>
                  <TouchableOpacity style={styles.recipeSelector} onPress={() => toggleIngredient(Number(stock.id))}>
                    <Icon name={ingredient ? 'checkbox-marked' : 'checkbox-blank-outline'} size={22} color={ingredient ? COLORS.accent : COLORS.textGray} />
                    <View style={styles.recipeNameWrap}><Text style={styles.recipeName}>{stock.name}</Text><Text style={styles.recipeUnit}>per order ({stock.unit})</Text></View>
                  </TouchableOpacity>
                  {ingredient ? <TextInput style={styles.recipeQuantity} value={ingredient.quantity} onChangeText={value => setIngredientQuantity(Number(stock.id), value)} keyboardType="decimal-pad" placeholder="0" /> : null}
                </View>
              );
            }) : <Text style={styles.ownerNote}>No stock items are available for a recipe yet.</Text>}

            <TouchableOpacity style={[styles.primaryButton, isSavingProduct && styles.disabledButton]} onPress={saveProduct} disabled={isSavingProduct}>
              {isSavingProduct ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.primaryButtonText}>{editingProduct ? 'Save changes' : 'Add product'}</Text>}
            </TouchableOpacity>
          </ScrollView>
        </View></View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  tabs: {flexDirection: 'row', backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border},
  tab: {flex: 1, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center', paddingVertical: 13, borderBottomWidth: 3, borderBottomColor: 'transparent'},
  tabActive: {borderBottomColor: COLORS.accent}, tabText: {fontSize: 14, fontWeight: '600', color: COLORS.textGray}, tabTextActive: {color: COLORS.accent},
  summary: {flexDirection: 'row', marginHorizontal: 20, marginTop: 20, marginBottom: 8, borderRadius: 16, backgroundColor: COLORS.surface, paddingVertical: 14, borderWidth: 1, borderColor: COLORS.border},
  summaryItem: {flex: 1, alignItems: 'center'}, summaryCount: {fontSize: 20, fontWeight: '700', color: COLORS.textDark}, summaryLabel: {fontSize: 12, fontWeight: '600', marginTop: 2},
  searchBox: {flexDirection: 'row', alignItems: 'center', marginHorizontal: 20, marginVertical: 10, paddingHorizontal: 12, borderRadius: 12, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  searchInput: {flex: 1, paddingVertical: 11, paddingHorizontal: 8, color: COLORS.textDark}, list: {paddingHorizontal: 20, paddingBottom: 24}, emptyList: {flexGrow: 1},
  card: {flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface, borderRadius: 16, padding: 14, marginBottom: 10, borderWidth: 1, borderColor: COLORS.border},
  iconBox: {width: 44, height: 44, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center', overflow: 'hidden'}, iconOk: {backgroundColor: '#E8F5E9'}, iconWarning: {backgroundColor: '#FFF3E0'}, productThumbnail: {width: '100%', height: '100%', resizeMode: 'cover'},
  cardBody: {flex: 1, marginHorizontal: 12}, cardTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark}, cardSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 3},
  badge: {paddingHorizontal: 9, paddingVertical: 5, borderRadius: 12}, badgeText: {fontSize: 11, fontWeight: '700'}, badgeOk: {backgroundColor: '#E8F5E9'}, badgeOkText: {color: '#2E7D32'}, badgeLow: {backgroundColor: '#FFF3E0'}, badgeLowText: {color: '#E65100'}, badgeOut: {backgroundColor: '#FFEBEE'}, badgeOutText: {color: '#C62828'},
  centerState: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32}, emptyTitle: {fontSize: 17, fontWeight: '700', color: COLORS.textDark, marginTop: 14}, stateText: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, textAlign: 'center', marginTop: 7}, footerLoader: {paddingVertical: 18},
  modalOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.48)', justifyContent: 'center', padding: 20}, modalCard: {backgroundColor: COLORS.surface, borderRadius: 20, padding: 20, maxHeight: '88%'},
  modalHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16}, modalTitle: {fontSize: 20, fontWeight: '700', color: COLORS.textDark}, modalSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 2},
  quantityPanel: {backgroundColor: COLORS.surfaceMuted, borderRadius: 14, padding: 18, marginBottom: 8}, quantityValue: {fontSize: 28, fontWeight: '700', color: COLORS.textDark}, quantityHint: {fontSize: 12, color: COLORS.textGray, marginTop: 4},
  fieldLabel: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', marginTop: 16, marginBottom: 7}, adjustToggle: {flexDirection: 'row', gap: 10},
  toggleButton: {flex: 1, alignItems: 'center', paddingVertical: 11, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border}, toggleButtonActive: {backgroundColor: COLORS.accent, borderColor: COLORS.accent}, toggleText: {fontSize: 13, fontWeight: '600', color: COLORS.textGray}, toggleTextActive: {color: '#FFFFFF'},
  input: {backgroundColor: COLORS.surfaceMuted, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 12, paddingVertical: 11, color: COLORS.textDark},
  primaryButton: {backgroundColor: COLORS.accent, borderRadius: 12, alignItems: 'center', paddingVertical: 14, marginTop: 20}, primaryButtonText: {color: '#FFFFFF', fontSize: 15, fontWeight: '700'}, disabledButton: {opacity: 0.6}, ownerNote: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginTop: 14, textAlign: 'center'},
  productHero: {height: 190, borderRadius: 14, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center', marginBottom: 12, overflow: 'hidden'}, productHeroImage: {width: '100%', height: '100%', resizeMode: 'cover'}, noImage: {alignItems: 'center'}, noImageText: {fontSize: 12, color: COLORS.textGray, marginTop: 6},
  detailRow: {flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: COLORS.border}, detailLabel: {fontSize: 14, color: COLORS.textGray}, detailValue: {fontSize: 14, fontWeight: '600', color: COLORS.textDark}, description: {fontSize: 14, lineHeight: 20, color: COLORS.textDark, marginTop: 14},
  ingredientRow: {flexDirection: 'row', justifyContent: 'space-between', backgroundColor: COLORS.surfaceMuted, borderRadius: 9, padding: 11, marginBottom: 7}, ingredientName: {fontSize: 13, color: COLORS.textDark}, ingredientQuantity: {fontSize: 13, fontWeight: '700', color: COLORS.accent},
  productActions: {gap: 9, marginTop: 20},
  secondaryButton: {minHeight: 46, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surfaceMuted, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8},
  secondaryButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.accentDark},
  deleteButton: {minHeight: 46, borderRadius: 12, borderWidth: 1, borderColor: '#F1C6C6', backgroundColor: '#FFF7F7', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 9},
  deleteButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.danger},
  imagePicker: {height: 190, borderRadius: 14, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surfaceMuted, overflow: 'hidden', alignItems: 'center', justifyContent: 'center'}, imagePreview: {width: '100%', height: '100%', resizeMode: 'cover'}, imagePlaceholder: {alignItems: 'center', paddingHorizontal: 20}, imagePlaceholderTitle: {fontSize: 14, fontWeight: '700', color: COLORS.textDark, marginTop: 8}, imagePlaceholderHint: {fontSize: 11, color: COLORS.textGray, marginTop: 4}, imagePickerBadge: {position: 'absolute', right: 10, bottom: 10, minHeight: 34, borderRadius: 17, paddingHorizontal: 12, backgroundColor: COLORS.accent, flexDirection: 'row', alignItems: 'center', gap: 6}, imagePickerBadgeText: {fontSize: 12, fontWeight: '700', color: '#FFFFFF'},
  priceInputWrap: {minHeight: 46, flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surfaceMuted, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 12},
  peso: {fontSize: 16, fontWeight: '700', color: COLORS.textDark, marginRight: 5}, priceInput: {flex: 1, color: COLORS.textDark, paddingVertical: 10},
  categoryOptions: {flexDirection: 'row', flexWrap: 'wrap', gap: 8}, categoryOption: {paddingHorizontal: 13, paddingVertical: 8, borderRadius: 20, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border}, categoryOptionActive: {backgroundColor: COLORS.accent, borderColor: COLORS.accent}, categoryOptionText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray}, categoryOptionTextActive: {color: '#FFFFFF'},
  descriptionInput: {minHeight: 76, textAlignVertical: 'top'},
  availabilityRow: {marginTop: 16, padding: 13, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'}, availabilityTitle: {fontSize: 14, fontWeight: '700', color: COLORS.textDark}, availabilityHint: {fontSize: 11, color: COLORS.textGray, marginTop: 3, maxWidth: 245},
  recipeHint: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginBottom: 9}, recipeRow: {minHeight: 52, borderRadius: 11, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, paddingHorizontal: 11, paddingVertical: 7, marginBottom: 8, flexDirection: 'row', alignItems: 'center'}, recipeRowSelected: {backgroundColor: COLORS.surfaceMuted, borderColor: COLORS.accent}, recipeSelector: {flex: 1, flexDirection: 'row', alignItems: 'center'}, recipeNameWrap: {marginLeft: 9}, recipeName: {fontSize: 13, fontWeight: '600', color: COLORS.textDark}, recipeUnit: {fontSize: 10, color: COLORS.textGray, marginTop: 2}, recipeQuantity: {width: 72, borderRadius: 8, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, color: COLORS.textDark, textAlign: 'center', paddingVertical: 7, paddingHorizontal: 6},
});

export default StockScreen;
