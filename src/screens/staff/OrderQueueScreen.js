import React, { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Modal,
  TextInput,
  FlatList,
  ActivityIndicator,
  Image,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useProducts } from '../../context/ProductContext';
import { useOrders } from '../../context/OrderContext';
import { subscribeToNotifications } from '../../modules/NotificationModule';
import { COLORS } from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const OrderQueueScreen = ({route}) => {
  const { products } = useProducts();
  const { createOrder } = useOrders();

  const [selectedProduct, setSelectedProduct] = useState(null);
  const [paymentTypeModalVisible, setPaymentTypeModalVisible] = useState(false);
  const [detailModalVisible, setDetailModalVisible] = useState(false);
  const [checkoutModalVisible, setCheckoutModalVisible] = useState(false);
  const [qrPaymentModalVisible, setQrPaymentModalVisible] = useState(false);
  const [paymentReceived, setPaymentReceived] = useState(false);
  const [receivedAmount, setReceivedAmount] = useState(0);
  
  // Cart and payment state
  const [cart, setCart] = useState([]);
  const [paymentType, setPaymentType] = useState(null); // 'cash' or 'cashless'
  const [selectedEwallet, setSelectedEwallet] = useState('GCash'); // For QR payment
  
  // Order form state
  const [quantity, setQuantity] = useState('1');
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [notes, setNotes] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');

  const availableProducts = useMemo(
    () => products.filter(product => product.is_available !== false),
    [products],
  );
  const categories = useMemo(
    () => ['All', ...new Set(availableProducts.map(product => product.category).filter(Boolean))],
    [availableProducts],
  );
  const EWALLETS = ['GCash', 'Maya'];

  const filteredProducts = selectedCategory === 'All'
    ? availableProducts
    : availableProducts.filter(product => product.category === selectedCategory);

  useEffect(() => {
    const requestedCategory = route?.params?.category;
    if (requestedCategory && categories.includes(requestedCategory)) {
      setSelectedCategory(requestedCategory);
    } else if (!categories.includes(selectedCategory)) {
      setSelectedCategory('All');
    }
  }, [categories, route?.params?.category, selectedCategory]);

  // Listen for e-wallet notifications when QR payment modal is open
  useEffect(() => {
    if (!qrPaymentModalVisible) return;

    const unsubscribe = subscribeToNotifications((transaction) => {
      // Check if this is a received payment matching our e-wallet
      if (
        transaction.transactionType === 'received' &&
        (transaction.appSource?.toLowerCase().includes(selectedEwallet.toLowerCase()) ||
         transaction.source?.toLowerCase().includes(selectedEwallet.toLowerCase()))
      ) {
        const amount = parseFloat(transaction.amount?.toString().replace(/[^0-9.]/g, '')) || 0;
        
        // Check if amount matches (with small tolerance for rounding)
        const expectedAmount = getCartSubtotal();
        const tolerance = 1; // ±1 peso tolerance
        
        if (Math.abs(amount - expectedAmount) <= tolerance) {
          // Payment received!
          setPaymentReceived(true);
          setReceivedAmount(amount);
          
          // Auto-complete order after 1.5 seconds
          setTimeout(() => {
            handlePlaceOrder();
          }, 1500);
        }
      }
    });

    return () => unsubscribe();
    // The subscription is intentionally recreated only when the payment listener changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [qrPaymentModalVisible, selectedEwallet]);

  // Step 1: Open payment type selection
  const handleOpenProduct = (product) => {
    setSelectedProduct(product);
    setQuantity('1');
    setPaymentType(null);
    setPaymentTypeModalVisible(true);
  };

  // Step 2: User selects Cash or Cashless
  const handleSelectPaymentType = (type) => {
    setPaymentType(type);
    setPaymentTypeModalVisible(false);
    
    if (type === 'cash') {
      // Cash: show product details first
      setDetailModalVisible(true);
    } else {
      // Cashless: skip details, add to cart directly
      handleQuickAddToCart();
    }
  };

  // Quick add to cart (for cashless, no detail view)
  const handleQuickAddToCart = () => {
    const qty = parseInt(quantity) || 1;
    const existingIndex = cart.findIndex(item => item.product.id === selectedProduct.id);
    
    if (existingIndex >= 0) {
      const newCart = [...cart];
      newCart[existingIndex].quantity += qty;
      setCart(newCart);
    } else {
      setCart(prev => [...prev, { product: selectedProduct, quantity: qty }]);
    }
    
    setQuantity('1');
  };

  // Add to cart from product detail (cash flow)
  const handleAddToCart = () => {
    const qty = parseInt(quantity) || 1;
    if (qty < 1) {
      alert('Please enter a valid quantity');
      return;
    }

    const existingIndex = cart.findIndex(item => item.product.id === selectedProduct.id);
    
    if (existingIndex >= 0) {
      const newCart = [...cart];
      newCart[existingIndex].quantity += qty;
      setCart(newCart);
    } else {
      setCart(prev => [...prev, { product: selectedProduct, quantity: qty }]);
    }

    setDetailModalVisible(false);
    setQuantity('1');
  };

  // Remove item from cart
  const handleRemoveFromCart = (productId) => {
    setCart(prev => prev.filter(item => item.product.id !== productId));
  };

  // Calculate cart totals
  const getCartSubtotal = () => {
    return cart.reduce((sum, item) => sum + (item.product.price * item.quantity), 0);
  };

  // Open checkout modal
  const handleOpenCheckout = () => {
    if (cart.length === 0) {
      alert('Cart is empty. Add products first.');
      return;
    }
    setCheckoutModalVisible(true);
  };

  // Proceed to payment (from checkout)
  const handleProceedToPayment = () => {
    setCheckoutModalVisible(false);

    if (paymentType === 'cashless') {
      // Reset payment status
      setPaymentReceived(false);
      setReceivedAmount(0);
      // Show QR payment waiting screen
      setQrPaymentModalVisible(true);
    } else {
      // Cash: place order immediately
      handlePlaceOrder();
    }
  };

  // Place an order. The backend creates income only after completion.
  const handlePlaceOrder = async () => {
    const subtotal = getCartSubtotal();
    const paymentMethod = paymentType === 'cash' ? 'Cash' : selectedEwallet;
    
    const orderData = {
      items: cart.map(item => ({
        product: item.product,
        quantity: item.quantity,
      })),
      customerName: 'Walk-in Customer',
      customerPhone: null,
      notes: notes.trim() || null,
      subtotal,
      discount: 0,
      total: subtotal,
      paymentMethod,
    };

    try {
      const order = await createOrder(orderData);

    // Reset form
      setCart([]);
      setCustomerName('');
      setCustomerPhone('');
      setPaymentType(null);
      setNotes('');
      setPaymentReceived(false);
      setReceivedAmount(0);
      setQrPaymentModalVisible(false);

      alert(`Order #${order.orderNumber} completed and added to Transactions.`);
    } catch (error) {
      alert(error.response?.data?.message || 'Unable to place the order.');
    }
  };

  // Render product card
  const renderProduct = ({ item: product }) => (
    <TouchableOpacity
      style={styles.productCard}
      onPress={() => handleOpenProduct(product)}
      activeOpacity={0.8}>
      <View style={styles.productEmoji}>
        {product.image
          ? <Image source={{uri: product.image}} style={styles.productImage} />
          : <Icon name="image-outline" size={42} color={COLORS.textMuted} />}
      </View>
      <View style={styles.productInfo}>
        <Text style={styles.productName}>{product.name}</Text>
        <Text style={styles.productCategory}>{product.category}</Text>
        <Text style={styles.productPrice}>₱{product.price.toFixed(2)}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>

      <StaffScreenHeader
        title="New order"
        subtitle="Choose items, then review the cart"
        icon="point-of-sale"
        actionIcon="cart-outline"
        actionLabel="Open cart"
        onActionPress={cart.length > 0 ? handleOpenCheckout : undefined}
        centered
      />

      {/* Cart Badge */}
      {cart.length > 0 && (
        <TouchableOpacity style={styles.cartBadge} onPress={handleOpenCheckout}>
          <Text style={styles.cartBadgeText}>
            🛒 {cart.length} item{cart.length !== 1 ? 's' : ''} • ₱{getCartSubtotal().toFixed(2)}
          </Text>
        </TouchableOpacity>
      )}

      {/* Category Filter */}
      <View style={styles.categorySection}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          <View style={styles.categoryRow}>
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
          </View>
        </ScrollView>
      </View>

      {/* Products Grid */}
      <FlatList
        data={filteredProducts}
        keyExtractor={item => item.id}
        renderItem={renderProduct}
        numColumns={2}
        contentContainerStyle={styles.productsList}
        columnWrapperStyle={styles.productRow}
      />

      {/* Floating Checkout Button */}
      {cart.length > 0 && (
        <TouchableOpacity
          style={styles.checkoutButton}
          onPress={handleOpenCheckout}>
          <Text style={styles.checkoutButtonText}>
            Checkout ({cart.length})
          </Text>
        </TouchableOpacity>
      )}

      {/* Payment Type Modal (Cash or Cashless) */}
      <Modal
        visible={paymentTypeModalVisible}
        animationType="fade"
        transparent
        onRequestClose={() => setPaymentTypeModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.paymentTypeCard}>
            
            <Text style={styles.paymentTypeTitle}>Select Payment Type</Text>
            
            {selectedProduct && (
              <View style={styles.paymentTypeProductInfo}>
                {selectedProduct.image
                  ? <Image source={{uri: selectedProduct.image}} style={styles.paymentProductImage} />
                  : <Icon name="image-outline" size={42} color={COLORS.textMuted} />}
                <Text style={styles.paymentTypeProductName}>{selectedProduct.name}</Text>
                <Text style={styles.paymentTypeProductPrice}>₱{selectedProduct.price.toFixed(2)}</Text>
              </View>
            )}

            <View style={styles.paymentTypeButtons}>
              <TouchableOpacity
                style={styles.paymentTypeButton}
                onPress={() => handleSelectPaymentType('cash')}>
                <Text style={styles.paymentTypeButtonIcon}>💵</Text>
                <Text style={styles.paymentTypeButtonText}>Cash</Text>
                <Text style={styles.paymentTypeButtonDesc}>View details first</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.paymentTypeButton}
                onPress={() => handleSelectPaymentType('cashless')}>
                <Text style={styles.paymentTypeButtonIcon}>📱</Text>
                <Text style={styles.paymentTypeButtonText}>Cashless</Text>
                <Text style={styles.paymentTypeButtonDesc}>Quick add to cart</Text>
              </TouchableOpacity>
            </View>

            <TouchableOpacity
              style={styles.paymentTypeCancelButton}
              onPress={() => setPaymentTypeModalVisible(false)}>
              <Text style={styles.paymentTypeCancelText}>Cancel</Text>
            </TouchableOpacity>

          </View>
        </View>
      </Modal>

      {/* Product Detail Modal (Cash flow only) */}
      <Modal
        visible={detailModalVisible}
        animationType="slide"
        transparent
        onRequestClose={() => setDetailModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            
            {selectedProduct && (
              <>
                <View style={styles.modalHeader}>
                  <Text style={styles.modalTitle}>{selectedProduct.name}</Text>
                  <TouchableOpacity onPress={() => setDetailModalVisible(false)}>
                    <Text style={styles.modalClose}>✕</Text>
                  </TouchableOpacity>
                </View>

                <ScrollView showsVerticalScrollIndicator={false}>

                  {/* Product Emoji */}
                  <View style={styles.productDetailEmoji}>
                    {selectedProduct.image
                      ? <Image source={{uri: selectedProduct.image}} style={styles.productDetailImage} />
                      : <Icon name="image-outline" size={56} color={COLORS.textMuted} />}
                  </View>

                  {/* Category & Price */}
                  <View style={styles.productDetailHeader}>
                    <Text style={styles.productDetailCategory}>{selectedProduct.category}</Text>
                    <Text style={styles.productDetailPrice}>₱{selectedProduct.price.toFixed(2)}</Text>
                  </View>

                  {/* Description */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailSectionTitle}>Description</Text>
                    <Text style={styles.descriptionText}>{selectedProduct.description}</Text>
                  </View>

                  {/* Ingredients */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailSectionTitle}>Ingredients</Text>
                    {selectedProduct.ingredients.map((ing, idx) => (
                      <View key={idx} style={styles.ingredientRow}>
                        <Text style={styles.ingredientDot}>•</Text>
                        <Text style={styles.ingredientText}>
                          {ing.name} ({ing.quantity} {ing.unit})
                        </Text>
                      </View>
                    ))}
                  </View>

                  {/* Quantity Input */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailSectionTitle}>Quantity</Text>
                    <View style={styles.quantityRow}>
                      <TouchableOpacity
                        style={styles.quantityButton}
                        onPress={() => {
                          const q = parseInt(quantity) || 1;
                          if (q > 1) setQuantity(String(q - 1));
                        }}>
                        <Text style={styles.quantityButtonText}>−</Text>
                      </TouchableOpacity>
                      <TextInput
                        style={styles.quantityInput}
                        keyboardType="number-pad"
                        value={quantity}
                        onChangeText={setQuantity}
                      />
                      <TouchableOpacity
                        style={styles.quantityButton}
                        onPress={() => {
                          const q = parseInt(quantity) || 1;
                          setQuantity(String(q + 1));
                        }}>
                        <Text style={styles.quantityButtonText}>+</Text>
                      </TouchableOpacity>
                    </View>
                  </View>

                  {/* Add to Cart Button */}
                  <TouchableOpacity
                    style={styles.addToCartButton}
                    onPress={handleAddToCart}>
                    <Text style={styles.addToCartButtonText}>
                      Add to Cart • ₱{(selectedProduct.price * (parseInt(quantity) || 1)).toFixed(2)}
                    </Text>
                  </TouchableOpacity>

                </ScrollView>
              </>
            )}

          </View>
        </View>
      </Modal>

      {/* Checkout Modal */}
      <Modal
        visible={checkoutModalVisible}
        animationType="slide"
        transparent
        onRequestClose={() => setCheckoutModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Checkout</Text>
              <TouchableOpacity onPress={() => setCheckoutModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>

              {/* Payment Type Badge */}
              <View style={[
                styles.paymentTypeBadge,
                paymentType === 'cash' ? styles.cashBadge : styles.cashlessBadge,
              ]}>
                <Text style={styles.paymentTypeBadgeText}>
                  {paymentType === 'cash' ? '💵 Cash Payment' : '📱 Cashless Payment'}
                </Text>
              </View>

              {/* Cart Items */}
              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Order Items</Text>
                {cart.map((item, idx) => (
                  <View key={idx} style={styles.cartItemRow}>
                    {item.product.image
                      ? <Image source={{uri: item.product.image}} style={styles.cartItemImage} />
                      : <Icon name="image-outline" size={28} color={COLORS.textMuted} style={styles.cartItemEmoji} />}
                    <View style={styles.cartItemInfo}>
                      <Text style={styles.cartItemName}>{item.product.name}</Text>
                      <Text style={styles.cartItemQty}>Qty: {item.quantity}</Text>
                    </View>
                    <View style={styles.cartItemRight}>
                      <Text style={styles.cartItemPrice}>
                        ₱{(item.product.price * item.quantity).toFixed(2)}
                      </Text>
                      <TouchableOpacity onPress={() => handleRemoveFromCart(item.product.id)}>
                        <Text style={styles.removeItemText}>✕</Text>
                      </TouchableOpacity>
                    </View>
                  </View>
                ))}
              </View>

              {/* E-wallet selection (only for cashless) */}
              {paymentType === 'cashless' && (
                <View style={styles.detailSection}>
                  <Text style={styles.detailSectionTitle}>Select E-wallet</Text>
                  <View style={styles.chipRow}>
                    {EWALLETS.map(wallet => (
                      <TouchableOpacity
                        key={wallet}
                        style={[
                          styles.paymentChip,
                          selectedEwallet === wallet && styles.paymentChipActive,
                        ]}
                        onPress={() => setSelectedEwallet(wallet)}>
                        <Text style={[
                          styles.paymentChipText,
                          selectedEwallet === wallet && styles.paymentChipTextActive,
                        ]}>
                          {wallet}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
              )}

              {/* Notes */}
              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Special Instructions (optional)</Text>
                <TextInput
                  style={[styles.input, styles.notesInput]}
                  placeholder="E.g., Less ice, no sugar, etc."
                  value={notes}
                  onChangeText={setNotes}
                  multiline
                />
              </View>

              {/* Total */}
              <View style={styles.totalBox}>
                <View style={styles.totalRow}>
                  <Text style={styles.totalLabel}>Subtotal:</Text>
                  <Text style={styles.totalValue}>₱{getCartSubtotal().toFixed(2)}</Text>
                </View>
                <View style={[styles.totalRow, styles.grandTotalRow]}>
                  <Text style={styles.grandTotalLabel}>Total:</Text>
                  <Text style={styles.grandTotalValue}>₱{getCartSubtotal().toFixed(2)}</Text>
                </View>
              </View>

              {/* Proceed to Payment Button */}
              <TouchableOpacity
                style={styles.placeOrderButton}
                onPress={handleProceedToPayment}>
                <Text style={styles.placeOrderButtonText}>
                  {paymentType === 'cash' ? 'Place Order' : 'Proceed to Payment'}
                </Text>
              </TouchableOpacity>

            </ScrollView>

          </View>
        </View>
      </Modal>

      {/* QR Payment Waiting Modal (Cashless only) */}
      <Modal
        visible={qrPaymentModalVisible}
        animationType="fade"
        transparent
        onRequestClose={() => {}}>
        <View style={styles.modalOverlay}>
          <View style={styles.qrModalCard}>
            
            {paymentReceived ? (
              <>
                {/* Payment Received Success State */}
                <Text style={styles.qrSuccessTitle}>Payment Received! ✅</Text>
                
                <View style={styles.qrSuccessIcon}>
                  <Text style={styles.qrSuccessEmoji}>✅</Text>
                </View>

                <View style={styles.qrAmountBox}>
                  <Text style={styles.qrAmountLabel}>Amount Received:</Text>
                  <Text style={styles.qrAmountValue}>₱{receivedAmount.toFixed(2)}</Text>
                </View>

                <Text style={styles.qrSuccessText}>
                  Processing order...
                </Text>
              </>
            ) : (
              <>
                {/* Waiting for Payment State */}
                <Text style={styles.qrTitle}>Waiting for Payment</Text>
                
                {/* QR Code Placeholder */}
                <View style={styles.qrCodeBox}>
                  <Text style={styles.qrCodeEmoji}>📱</Text>
                  <Text style={styles.qrCodeText}>QR Code</Text>
                  <Text style={styles.qrCodeSubtext}>{selectedEwallet}</Text>
                </View>

                <View style={styles.qrAmountBox}>
                  <Text style={styles.qrAmountLabel}>Amount to Pay:</Text>
                  <Text style={styles.qrAmountValue}>₱{getCartSubtotal().toFixed(2)}</Text>
                </View>

                <View style={styles.qrInstructions}>
                  <Text style={styles.qrInstructionText}>
                    1. Open {selectedEwallet} app on customer's phone
                  </Text>
                  <Text style={styles.qrInstructionText}>
                    2. Scan the QR code above
                  </Text>
                  <Text style={styles.qrInstructionText}>
                    3. Confirm payment
                  </Text>
                  <Text style={styles.qrInstructionText}>
                    4. Payment will be detected automatically
                  </Text>
                </View>

                <ActivityIndicator size="large" color={COLORS.accent} />
                <Text style={styles.qrWaitingText}>Listening for {selectedEwallet} notification...</Text>

                <TouchableOpacity
                  style={styles.qrCancelButton}
                  onPress={() => {
                    setQrPaymentModalVisible(false);
                    setPaymentReceived(false);
                    setReceivedAmount(0);
                  }}>
                  <Text style={styles.qrCancelButtonText}>Cancel Payment</Text>
                </TouchableOpacity>
              </>
            )}

          </View>
        </View>
      </Modal>

    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  cartBadge: {
    backgroundColor: COLORS.accent,
    marginHorizontal: 20,
    marginTop: 20,
    marginBottom: 8,
    padding: 12,
    borderRadius: 12,
    alignItems: 'center',
    elevation: 3,
  },
  cartBadgeText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  categorySection: {
    backgroundColor: COLORS.background,
    paddingVertical: 12,
  },
  categoryRow: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    gap: 8,
  },
  categoryChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  categoryChipActive: {
    backgroundColor: COLORS.accent,
    borderColor: COLORS.accent,
  },
  categoryChipText: {
    fontSize: 13,
    color: '#666666',
    fontWeight: '500',
  },
  categoryChipTextActive: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  productsList: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 28,
  },
  productRow: {
    justifyContent: 'space-between',
    gap: 12,
  },
  productCard: {
    flex: 1,
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    elevation: 1,
  },
  productEmoji: {
    width: '100%',
    aspectRatio: 1,
    backgroundColor: COLORS.miniCardBg,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
    overflow: 'hidden',
  },
  productImage: {width: '100%', height: '100%', resizeMode: 'cover'},
  productInfo: {
    alignItems: 'center',
  },
  productName: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#1A1A1A',
    textAlign: 'center',
    marginBottom: 2,
  },
  productCategory: {
    fontSize: 10,
    color: '#888888',
    marginBottom: 4,
  },
  productPrice: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  checkoutButton: {
    position: 'absolute',
    bottom: 100,
    left: 24,
    right: 24,
    backgroundColor: COLORS.accent,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    elevation: 6,
  },
  checkoutButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  paymentTypeCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 24,
    width: '100%',
    maxWidth: 400,
  },
  paymentTypeTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
    textAlign: 'center',
    marginBottom: 20,
  },
  paymentTypeProductInfo: {
    alignItems: 'center',
    marginBottom: 24,
    padding: 16,
    backgroundColor: '#F9F9F9',
    borderRadius: 12,
  },
  paymentProductImage: {width: 96, height: 96, borderRadius: 12, resizeMode: 'cover', marginBottom: 10},
  paymentTypeProductName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1A1A1A',
    marginBottom: 4,
  },
  paymentTypeProductPrice: {
    fontSize: 18,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  paymentTypeButtons: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 16,
  },
  paymentTypeButton: {
    flex: 1,
    backgroundColor: '#F9F9F9',
    borderRadius: 14,
    padding: 20,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#E0E0E0',
  },
  paymentTypeButtonIcon: {
    fontSize: 40,
    marginBottom: 10,
  },
  paymentTypeButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1A1A1A',
    marginBottom: 4,
  },
  paymentTypeButtonDesc: {
    fontSize: 11,
    color: '#888888',
    textAlign: 'center',
  },
  paymentTypeCancelButton: {
    padding: 12,
    alignItems: 'center',
  },
  paymentTypeCancelText: {
    fontSize: 14,
    color: '#888888',
  },
  modalContainer: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    width: '100%',
    maxHeight: '92%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  modalClose: {
    fontSize: 24,
    color: '#888888',
  },
  paymentTypeBadge: {
    borderRadius: 12,
    padding: 12,
    alignItems: 'center',
    marginBottom: 16,
  },
  cashBadge: {
    backgroundColor: '#E8F5E9',
  },
  cashlessBadge: {
    backgroundColor: '#E3F2FD',
  },
  paymentTypeBadgeText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  productDetailEmoji: {
    height: 220,
    borderRadius: 14,
    backgroundColor: COLORS.miniCardBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
    overflow: 'hidden',
  },
  productDetailImage: {width: '100%', height: '100%', resizeMode: 'cover'},
  productDetailHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  productDetailCategory: {
    fontSize: 14,
    color: '#888888',
    fontWeight: '500',
  },
  productDetailPrice: {
    fontSize: 24,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  detailSection: {
    marginBottom: 20,
  },
  detailSectionTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333333',
    marginBottom: 10,
  },
  descriptionText: {
    fontSize: 13,
    color: '#666666',
    lineHeight: 20,
  },
  ingredientRow: {
    flexDirection: 'row',
    marginBottom: 6,
  },
  ingredientDot: {
    fontSize: 13,
    color: '#888888',
    marginRight: 8,
  },
  ingredientText: {
    fontSize: 13,
    color: '#666666',
  },
  quantityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
  },
  quantityButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#F0F0F0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  quantityButtonText: {
    fontSize: 24,
    color: '#333333',
    fontWeight: 'bold',
  },
  quantityInput: {
    width: 80,
    fontSize: 20,
    fontWeight: 'bold',
    textAlign: 'center',
    backgroundColor: '#F9F9F9',
    borderRadius: 10,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  addToCartButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    marginTop: 10,
  },
  addToCartButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  cartItemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#F9F9F9',
    borderRadius: 10,
    marginBottom: 10,
  },
  cartItemEmoji: {
    marginRight: 10,
  },
  cartItemImage: {width: 42, height: 42, borderRadius: 9, resizeMode: 'cover', marginRight: 10},
  cartItemInfo: {
    flex: 1,
  },
  cartItemName: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  cartItemQty: {
    fontSize: 11,
    color: '#888888',
    marginTop: 2,
  },
  cartItemRight: {
    alignItems: 'flex-end',
  },
  cartItemPrice: {
    fontSize: 13,
    fontWeight: 'bold',
    color: COLORS.income,
    marginBottom: 4,
  },
  removeItemText: {
    fontSize: 16,
    color: '#C62828',
  },
  fieldLabel: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#666666',
    marginBottom: 6,
    marginTop: 8,
  },
  input: {
    backgroundColor: '#F5F5F5',
    borderRadius: 10,
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
  paymentChip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  paymentChipActive: {
    backgroundColor: COLORS.accent,
    borderColor: COLORS.accent,
  },
  paymentChipText: {
    fontSize: 13,
    color: '#666666',
    fontWeight: '500',
  },
  paymentChipTextActive: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  totalBox: {
    backgroundColor: '#F9F9F9',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  totalLabel: {
    fontSize: 14,
    color: '#666666',
  },
  totalValue: {
    fontSize: 14,
    fontWeight: '500',
    color: '#1A1A1A',
  },
  grandTotalRow: {
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
    marginTop: 4,
  },
  grandTotalLabel: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  grandTotalValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  placeOrderButton: {
    backgroundColor: COLORS.income,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    marginBottom: 10,
  },
  placeOrderButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  qrModalCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 24,
    width: '100%',
    maxWidth: 400,
    alignItems: 'center',
  },
  qrTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
    marginBottom: 24,
  },
  qrCodeBox: {
    width: 220,
    height: 220,
    backgroundColor: '#F9F9F9',
    borderRadius: 16,
    borderWidth: 2,
    borderColor: '#E0E0E0',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  qrCodeEmoji: {
    fontSize: 80,
    marginBottom: 10,
  },
  qrCodeText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  qrCodeSubtext: {
    fontSize: 13,
    color: '#888888',
    marginTop: 4,
  },
  qrAmountBox: {
    backgroundColor: '#F9F9F9',
    borderRadius: 12,
    padding: 16,
    width: '100%',
    alignItems: 'center',
    marginBottom: 20,
  },
  qrAmountLabel: {
    fontSize: 13,
    color: '#666666',
    marginBottom: 4,
  },
  qrAmountValue: {
    fontSize: 28,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  qrInstructions: {
    width: '100%',
    marginBottom: 20,
  },
  qrInstructionText: {
    fontSize: 13,
    color: '#666666',
    marginBottom: 8,
    lineHeight: 20,
  },
  qrWaitingText: {
    fontSize: 13,
    color: '#888888',
    marginTop: 12,
  },
  qrSuccessTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: COLORS.income,
    marginBottom: 24,
    textAlign: 'center',
  },
  qrSuccessIcon: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#E8F5E9',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  qrSuccessEmoji: {
    fontSize: 64,
  },
  qrSuccessText: {
    fontSize: 14,
    color: '#666666',
    marginTop: 16,
    textAlign: 'center',
  },
  qrCancelButton: {
    marginTop: 24,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 12,
    backgroundColor: '#FFEBEE',
    borderWidth: 1,
    borderColor: '#C62828',
  },
  qrCancelButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#C62828',
    textAlign: 'center',
  },
});

export default OrderQueueScreen;
