import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Modal,
  TextInput,
  Image,
} from 'react-native';
import { useProducts } from '../../context/ProductContext';
import { useOrders } from '../../context/OrderContext';
import { COLORS } from '../../theme';

const PAYMENT_METHODS = ['Cash', 'GCash', 'Maya', 'Card'];

const OrderScreen = () => {
  const { products, PRODUCT_CATEGORIES } = useProducts();
  const { createOrder } = useOrders();

  const [selectedCategory, setSelectedCategory] = useState('All');
  const [cart, setCart] = useState([]); // [{ product, quantity }]
  const [checkoutModalVisible, setCheckoutModalVisible] = useState(false);

  // Customer info
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [notes, setNotes] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('Cash');

  // Filter products by category
  const filteredProducts =
    selectedCategory === 'All'
      ? products
      : products.filter(p => p.category === selectedCategory);

  // Add to cart
  const addToCart = (product) => {
    const existing = cart.find(item => item.product.id === product.id);
    if (existing) {
      setCart(prev =>
        prev.map(item =>
          item.product.id === product.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        )
      );
    } else {
      setCart(prev => [...prev, { product, quantity: 1 }]);
    }
  };

  // Update cart item quantity
  const updateCartQuantity = (productId, change) => {
    setCart(prev =>
      prev.map(item => {
        if (item.product.id === productId) {
          const newQuantity = item.quantity + change;
          return { ...item, quantity: Math.max(0, newQuantity) };
        }
        return item;
      }).filter(item => item.quantity > 0)
    );
  };

  // Remove from cart
  const removeFromCart = (productId) => {
    setCart(prev => prev.filter(item => item.product.id !== productId));
  };

  // Calculate totals
  const subtotal = cart.reduce(
    (sum, item) => sum + item.product.price * item.quantity,
    0
  );
  const discount = 0; // Can add discount logic later
  const total = subtotal - discount;

  // Place order
  const handlePlaceOrder = () => {
    if (cart.length === 0) {
      alert('Your cart is empty!');
      return;
    }

    if (!customerName.trim()) {
      alert('Please enter your name');
      return;
    }

    const order = createOrder({
      items: cart,
      customerName: customerName.trim(),
      customerPhone: customerPhone.trim(),
      notes: notes.trim(),
      subtotal,
      discount,
      total,
      paymentMethod,
    });

    // Show success
    alert(`✅ Order placed successfully!\n\nOrder #${order.orderNumber}\n\nPlease wait for staff to prepare your order.`);

    // Reset
    setCart([]);
    setCustomerName('');
    setCustomerPhone('');
    setNotes('');
    setPaymentMethod('Cash');
    setCheckoutModalVisible(false);
  };

  const cartItemCount = cart.reduce((sum, item) => sum + item.quantity, 0);

  return (
    <View style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.headerTitle}>Order Now</Text>
          <Text style={styles.headerSub}>Browse our menu and place your order</Text>
        </View>
        {cartItemCount > 0 && (
          <View style={styles.cartBadge}>
            <Text style={styles.cartBadgeText}>{cartItemCount}</Text>
          </View>
        )}
      </View>

      {/* Category Filter */}
      <View style={styles.categorySection}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          <View style={styles.categoryRow}>
            <TouchableOpacity
              style={[
                styles.categoryChip,
                selectedCategory === 'All' && styles.categoryChipActive,
              ]}
              onPress={() => setSelectedCategory('All')}>
              <Text
                style={[
                  styles.categoryChipText,
                  selectedCategory === 'All' && styles.categoryChipTextActive,
                ]}>
                All
              </Text>
            </TouchableOpacity>
            {PRODUCT_CATEGORIES.map(cat => (
              <TouchableOpacity
                key={cat}
                style={[
                  styles.categoryChip,
                  selectedCategory === cat && styles.categoryChipActive,
                ]}
                onPress={() => setSelectedCategory(cat)}>
                <Text
                  style={[
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
      <ScrollView contentContainerStyle={styles.productsGrid}>
        {filteredProducts.map(product => {
          const inCart = cart.find(item => item.product.id === product.id);
          return (
            <View key={product.id} style={styles.productCard}>
              <View style={styles.productEmoji}>
                <Text style={styles.productEmojiText}>{product.emoji}</Text>
              </View>
              <Text style={styles.productName}>{product.name}</Text>
              <Text style={styles.productCategory}>{product.category}</Text>
              <Text style={styles.productPrice}>₱{product.price}</Text>
              <Text style={styles.productDesc} numberOfLines={2}>
                {product.description}
              </Text>
              
              {inCart ? (
                <View style={styles.quantityControl}>
                  <TouchableOpacity
                    style={styles.quantityButton}
                    onPress={() => updateCartQuantity(product.id, -1)}>
                    <Text style={styles.quantityButtonText}>-</Text>
                  </TouchableOpacity>
                  <Text style={styles.quantityText}>{inCart.quantity}</Text>
                  <TouchableOpacity
                    style={styles.quantityButton}
                    onPress={() => updateCartQuantity(product.id, 1)}>
                    <Text style={styles.quantityButtonText}>+</Text>
                  </TouchableOpacity>
                </View>
              ) : (
                <TouchableOpacity
                  style={styles.addButton}
                  onPress={() => addToCart(product)}>
                  <Text style={styles.addButtonText}>+ Add to Cart</Text>
                </TouchableOpacity>
              )}
            </View>
          );
        })}
      </ScrollView>

      {/* Cart Summary Footer */}
      {cart.length > 0 && (
        <View style={styles.cartFooter}>
          <View style={styles.cartSummary}>
            <Text style={styles.cartSummaryLabel}>{cartItemCount} items</Text>
            <Text style={styles.cartSummaryTotal}>₱{total.toFixed(2)}</Text>
          </View>
          <TouchableOpacity
            style={styles.checkoutButton}
            onPress={() => setCheckoutModalVisible(true)}>
            <Text style={styles.checkoutButtonText}>Checkout →</Text>
          </TouchableOpacity>
        </View>
      )}

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

              {/* Order Items */}
              <Text style={styles.sectionTitle}>Your Order</Text>
              {cart.map(item => (
                <View key={item.product.id} style={styles.cartItem}>
                  <Text style={styles.cartItemEmoji}>{item.product.emoji}</Text>
                  <View style={styles.cartItemInfo}>
                    <Text style={styles.cartItemName}>{item.product.name}</Text>
                    <Text style={styles.cartItemQty}>Qty: {item.quantity}</Text>
                  </View>
                  <Text style={styles.cartItemPrice}>
                    ₱{(item.product.price * item.quantity).toFixed(2)}
                  </Text>
                </View>
              ))}

              {/* Totals */}
              <View style={styles.totalsBox}>
                <View style={styles.totalRow}>
                  <Text style={styles.totalLabel}>Subtotal:</Text>
                  <Text style={styles.totalValue}>₱{subtotal.toFixed(2)}</Text>
                </View>
                {discount > 0 && (
                  <View style={styles.totalRow}>
                    <Text style={styles.totalLabel}>Discount:</Text>
                    <Text style={[styles.totalValue, styles.discountValue]}>
                      -₱{discount.toFixed(2)}
                    </Text>
                  </View>
                )}
                <View style={[styles.totalRow, styles.grandTotalRow]}>
                  <Text style={styles.grandTotalLabel}>Total:</Text>
                  <Text style={styles.grandTotalValue}>₱{total.toFixed(2)}</Text>
                </View>
              </View>

              {/* Customer Info */}
              <Text style={styles.sectionTitle}>Your Information</Text>
              
              <Text style={styles.fieldLabel}>Name *</Text>
              <TextInput
                style={styles.input}
                placeholder="Enter your name"
                value={customerName}
                onChangeText={setCustomerName}
              />

              <Text style={styles.fieldLabel}>Phone (optional)</Text>
              <TextInput
                style={styles.input}
                placeholder="Enter phone number"
                keyboardType="phone-pad"
                value={customerPhone}
                onChangeText={setCustomerPhone}
              />

              <Text style={styles.fieldLabel}>Special Instructions (optional)</Text>
              <TextInput
                style={[styles.input, styles.notesInput]}
                placeholder="Any special requests?"
                multiline
                value={notes}
                onChangeText={setNotes}
              />

              {/* Payment Method */}
              <Text style={styles.sectionTitle}>Payment Method</Text>
              <View style={styles.paymentRow}>
                {PAYMENT_METHODS.map(method => (
                  <TouchableOpacity
                    key={method}
                    style={[
                      styles.paymentChip,
                      paymentMethod === method && styles.paymentChipActive,
                    ]}
                    onPress={() => setPaymentMethod(method)}>
                    <Text
                      style={[
                        styles.paymentChipText,
                        paymentMethod === method && styles.paymentChipTextActive,
                      ]}>
                      {method}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>

              <TouchableOpacity
                style={styles.placeOrderButton}
                onPress={handlePlaceOrder}>
                <Text style={styles.placeOrderButtonText}>
                  🛒 Place Order (₱{total.toFixed(2)})
                </Text>
              </TouchableOpacity>

            </ScrollView>

          </View>
        </View>
      </Modal>

    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  header: {
    backgroundColor: COLORS.bgDark,
    padding: 20,
    paddingTop: 48,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: COLORS.textDark,
  },
  headerSub: {
    fontSize: 13,
    color: 'rgba(0,0,0,0.5)',
    marginTop: 2,
  },
  cartBadge: {
    backgroundColor: '#FF5722',
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cartBadgeText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  categorySection: {
    backgroundColor: '#FFFFFF',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  categoryRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    gap: 8,
  },
  categoryChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
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
  productsGrid: {
    padding: 16,
    paddingBottom: 200,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  productCard: {
    width: '48%',
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 12,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  productEmoji: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: COLORS.miniCardBg,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'center',
    marginBottom: 8,
  },
  productEmojiText: {
    fontSize: 32,
  },
  productName: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
    marginBottom: 2,
  },
  productCategory: {
    fontSize: 11,
    color: COLORS.accent,
    marginBottom: 4,
  },
  productPrice: {
    fontSize: 16,
    fontWeight: 'bold',
    color: COLORS.income,
    marginBottom: 6,
  },
  productDesc: {
    fontSize: 11,
    color: '#888888',
    marginBottom: 10,
    height: 32,
  },
  addButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 8,
    paddingVertical: 8,
    alignItems: 'center',
  },
  addButtonText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  quantityControl: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: COLORS.miniCardBg,
    borderRadius: 8,
    padding: 4,
  },
  quantityButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  quantityButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  quantityText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  cartFooter: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#FFFFFF',
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  cartSummary: {
    flex: 1,
  },
  cartSummaryLabel: {
    fontSize: 12,
    color: '#888888',
  },
  cartSummaryTotal: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  checkoutButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 24,
  },
  checkoutButtonText: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContainer: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    maxHeight: '92%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  modalClose: {
    fontSize: 24,
    color: '#888888',
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333333',
    marginTop: 16,
    marginBottom: 10,
  },
  cartItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#F9F9F9',
    borderRadius: 10,
    marginBottom: 8,
  },
  cartItemEmoji: {
    fontSize: 28,
    marginRight: 12,
  },
  cartItemInfo: {
    flex: 1,
  },
  cartItemName: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  cartItemQty: {
    fontSize: 12,
    color: '#888888',
    marginTop: 2,
  },
  cartItemPrice: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.income,
  },
  totalsBox: {
    backgroundColor: '#F5FBF9',
    borderRadius: 12,
    padding: 14,
    marginTop: 12,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  totalLabel: {
    fontSize: 13,
    color: '#666666',
  },
  totalValue: {
    fontSize: 13,
    fontWeight: '500',
    color: '#1A1A1A',
  },
  discountValue: {
    color: '#E65100',
  },
  grandTotalRow: {
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
    marginBottom: 0,
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
  fieldLabel: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#555555',
    marginTop: 12,
    marginBottom: 6,
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
  paymentRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  paymentChip: {
    paddingHorizontal: 16,
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
  placeOrderButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    paddingVertical: 16,
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 20,
  },
  placeOrderButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
});

export default OrderScreen;
