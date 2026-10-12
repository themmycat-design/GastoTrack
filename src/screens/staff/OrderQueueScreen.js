import React, {useMemo, useRef, useState, useEffect} from 'react';
import {
  View,
  Text,
  Alert,
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
import { useTransactions } from '../../context/TransactionContext';
import { useOrders } from '../../context/OrderContext';
import {isNotificationAccessGranted, openNotificationSettings, subscribeToNotifications} from '../../modules/NotificationModule';
import {getApiErrorMessage} from '../../services/apiErrors';
import { COLORS } from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {productCategoryTabs, productsInCategory} from '../../constants/productCategories';

const OrderQueueScreen = ({navigation, route}) => {
  const {products, isLoading: productsLoading} = useProducts();
  const {PRODUCT_CATEGORIES} = useTransactions();
  const {createOrder, confirmPaymentFromNotification, markPaymentFailed, cancelOrder, cart, setCart, cartHydrated} = useOrders();
  const submittingOrder = useRef(false);
  const autoConfirmAttemptRef = useRef(null);
  const cashlessCartSnapshotRef = useRef(null);

  const [selectedProduct, setSelectedProduct] = useState(null);
  const [detailModalVisible, setDetailModalVisible] = useState(false);
  const [checkoutModalVisible, setCheckoutModalVisible] = useState(false);
  const [walletPickerVisible, setWalletPickerVisible] = useState(false);
  const [qrPaymentModalVisible, setQrPaymentModalVisible] = useState(false);
  const [paymentSignalDetected, setPaymentSignalDetected] = useState(false);
  const [detectedAmount, setDetectedAmount] = useState(0);
  const [paymentOrder, setPaymentOrder] = useState(null);
  const [paymentAutoConfirmError, setPaymentAutoConfirmError] = useState('');
  const [paymentSignalWarning, setPaymentSignalWarning] = useState('');
  const [paymentCheckAttempt, setPaymentCheckAttempt] = useState(0);
  const [isCheckingWalletPayment, setIsCheckingWalletPayment] = useState(false);
  const [paymentNotice, setPaymentNotice] = useState('');
  const [detectedWalletSignal, setDetectedWalletSignal] = useState(null);
  
  // Cart and payment state
  const [isPlacingOrder, setIsPlacingOrder] = useState(false);
  const [paymentType, setPaymentType] = useState(null); // 'cash' or 'cashless'
  const [selectedEwallet, setSelectedEwallet] = useState(null);
  
  // Order form state
  const [quantity, setQuantity] = useState('1');
  const [notes, setNotes] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');

  const availableProducts = useMemo(
    () => products.filter(product => product.is_available !== false),
    [products],
  );
  const categories = useMemo(() => productCategoryTabs(PRODUCT_CATEGORIES), [PRODUCT_CATEGORIES]);
  const filteredProducts = productsInCategory(availableProducts, selectedCategory);

  useEffect(() => {
    const requestedCategory = route?.params?.category;
    const matchingCategory = requestedCategory && categories.find(
      category => category.toLowerCase() === requestedCategory.toLowerCase(),
    );
    if (matchingCategory) {
      setSelectedCategory(matchingCategory);
      navigation.setParams({category: undefined});
    } else if (!categories.some(category => category.toLowerCase() === selectedCategory.toLowerCase())) {
      setSelectedCategory('All');
    }
  }, [categories, navigation, route?.params?.category, selectedCategory]);

  useEffect(() => {
    if (!paymentNotice) return undefined;
    const timeout = setTimeout(() => setPaymentNotice(''), 6000);
    return () => clearTimeout(timeout);
  }, [paymentNotice]);

  // Listen only after the pending cashless order exists. A payment can be
  // matched only when the OS-reported wallet and received amount match it.
  useEffect(() => {
    if (!qrPaymentModalVisible || !paymentOrder?.id || !selectedEwallet || paymentSignalDetected) return undefined;

    const unsubscribe = subscribeToNotifications((transaction) => {
      const source = String(transaction.appSource || transaction.source || '').toLowerCase();
      if (transaction.transactionType !== 'received' || !source.includes(selectedEwallet.toLowerCase())) return;

      const amountText = String(transaction.amount || '').replace(/,/g, '').match(/[0-9]+(?:\.[0-9]{1,2})?/);
      const amount = amountText ? Number(amountText[0]) : 0;
      if (!Number.isFinite(amount) || amount <= 0) {
        setPaymentSignalWarning(`${selectedEwallet} notification arrived, but the amount could not be read. Still waiting for a matching payment.`);
        return;
      }

      const expectedAmount = Number(paymentOrder.total || 0);
      if (Math.round(amount * 100) !== Math.round(expectedAmount * 100)) {
        setPaymentSignalWarning(`${selectedEwallet} notification amount ₱${amount.toFixed(2)} does not match the order total ₱${expectedAmount.toFixed(2)}. Still waiting for the correct amount.`);
        return;
      }

      const signal = {
        amount,
        source: selectedEwallet.toLowerCase(),
        merchant: transaction.merchant || '',
        capturedAt: new Date().toISOString(),
        notificationId: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`,
      };
      setDetectedWalletSignal(signal);
      setPaymentSignalDetected(true);
      setDetectedAmount(amount);
      setPaymentAutoConfirmError('');
      setPaymentSignalWarning('');
      setPaymentNotice('The wallet and amount match. Saving the order…');
    });

    return unsubscribe;
  }, [qrPaymentModalVisible, paymentOrder?.id, paymentOrder?.total, selectedEwallet, paymentSignalDetected]);

  useEffect(() => {
    if (!qrPaymentModalVisible || !paymentOrder?.id || !paymentSignalDetected || !detectedWalletSignal) return undefined;
    const requestKey = `${paymentOrder.id}:${detectedWalletSignal.notificationId}:${paymentCheckAttempt}`;
    if (autoConfirmAttemptRef.current === requestKey) return undefined;
    autoConfirmAttemptRef.current = requestKey;
    let isCurrent = true;
    setIsCheckingWalletPayment(true);

    confirmPaymentFromNotification(paymentOrder.id, {
      amount: detectedWalletSignal.amount,
      currency: paymentOrder.paymentCurrency || 'PHP',
      source: detectedWalletSignal.source,
      notification_id: detectedWalletSignal.notificationId,
    }).then(result => {
      if (!isCurrent) return;
      setPaymentOrder(result.order);
      setQrPaymentModalVisible(false);
      setWalletPickerVisible(false);
      setPaymentType(null);
      setSelectedEwallet(null);
      setNotes('');
      setDetectedWalletSignal(null);
      setPaymentSignalDetected(false);
      setDetectedAmount(0);
      setPaymentAutoConfirmError('');
      setPaymentSignalWarning('');
      setPaymentNotice('Payment notification matched the order and the order was saved. GastoTrack did not verify it with the wallet provider.');
    }).catch(error => {
      if (!isCurrent) return;
      const message = getApiErrorMessage(error, 'The server could not save the matched payment. Retry when the connection is available.');
      setPaymentAutoConfirmError(message);
      setPaymentNotice(`Payment notification matched, but the server could not save it: ${message}`);
    }).finally(() => {
      if (isCurrent) setIsCheckingWalletPayment(false);
    });

    return () => { isCurrent = false; };
  }, [qrPaymentModalVisible, paymentOrder?.id, paymentOrder?.paymentCurrency, paymentSignalDetected, detectedWalletSignal, paymentCheckAttempt, confirmPaymentFromNotification]);

  // Every product opens its details first; payment is chosen later in checkout.
  const handleOpenProduct = (product) => {
    if (!cartHydrated) return;
    setSelectedProduct(product);
    setQuantity('1');
    setDetailModalVisible(true);
  };

  const addProductToCart = qty => {
    if (!cartHydrated || !selectedProduct) return;
    setCart(previousCart => {
      const existingIndex = previousCart.findIndex(item => item.product?.id === selectedProduct.id);
      if (existingIndex < 0) return [...previousCart, {product: selectedProduct, quantity: qty}];
      return previousCart.map((item, index) => index === existingIndex
        ? {...item, quantity: Number(item.quantity) + qty}
        : item);
    });
  };

  // Add to cart from product detail, then return to the product list.
  const handleAddToCart = () => {
    const qty = Number.parseInt(quantity, 10);
    if (!Number.isInteger(qty) || qty < 1) {
      Alert.alert('Invalid quantity', 'Please enter a quantity greater than zero.');
      return;
    }

    addProductToCart(qty);

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
    if (!cartHydrated) {
      Alert.alert('Cart is loading', 'Please wait while your saved cart is restored.');
      return;
    }
    if (cart.length === 0) {
      Alert.alert('Cart is empty', 'Add products before checking out.');
      return;
    }
    setPaymentType(null);
    setSelectedEwallet(null);
    setPaymentOrder(null);
    setDetectedWalletSignal(null);
    setPaymentSignalDetected(false);
    setDetectedAmount(0);
    setPaymentAutoConfirmError('');
    setPaymentSignalWarning('');
    setIsCheckingWalletPayment(false);
    autoConfirmAttemptRef.current = null;
    setPaymentNotice('');
    setCheckoutModalVisible(true);
  };

  const handleSelectPaymentMethod = method => {
    if (method === 'cash') {
      setPaymentType('cash');
      setSelectedEwallet(null);
      setWalletPickerVisible(false);
    } else if (method === 'cashless') {
      setPaymentType('cashless');
      setSelectedEwallet(null);
      setWalletPickerVisible(true);
    }
  };

  // Proceed to payment (from checkout)
  const handleProceedToPayment = () => {
    if (isPlacingOrder) return;
    if (paymentType === 'cashless' && !selectedEwallet) {
      setWalletPickerVisible(true);
      return;
    }
    handlePlaceOrder();
  };

  const handlePlaceOrder = async (orderPaymentType = paymentType, wallet = selectedEwallet) => {
    if (submittingOrder.current || isPlacingOrder) return;
    if (!cartHydrated) {
      Alert.alert('Cart is loading', 'Please wait while your saved cart is restored.');
      return;
    }
    if (cart.length === 0) {
      Alert.alert('Cart is empty', 'Add products before placing the order.');
      return;
    }
    if (orderPaymentType !== 'cash' && orderPaymentType !== 'cashless') {
      Alert.alert('Choose a payment method', 'Select Cash or Cashless before placing this order.');
      return;
    }
    if (orderPaymentType === 'cashless' && !wallet) {
      setWalletPickerVisible(true);
      return;
    }
    submittingOrder.current = true;
    setIsPlacingOrder(true);
    cashlessCartSnapshotRef.current = orderPaymentType === 'cashless'
      ? cart.map(item => ({...item, product: {...item.product}}))
      : null;

    const paymentMethod = orderPaymentType === 'cash' ? 'cash' : wallet.toLowerCase();
    
    const orderData = {
      items: cart.map(item => ({
        product: item.product,
        quantity: item.quantity,
      })),
      customerName: 'Walk-in Customer',
      customerPhone: null,
      notes: notes.trim() || null,
      paymentMethod,
    };

    try {
      const order = await createOrder(orderData);
      setPaymentOrder(order);
      setCheckoutModalVisible(false);
      setWalletPickerVisible(false);

      if (orderPaymentType === 'cashless' && order.paymentStatus !== 'confirmed') {
        setQrPaymentModalVisible(true);
        setPaymentSignalDetected(false);
        setDetectedWalletSignal(null);
        setDetectedAmount(0);
        setPaymentAutoConfirmError('');
        setPaymentSignalWarning('');
        setIsCheckingWalletPayment(false);
        autoConfirmAttemptRef.current = null;
        setPaymentNotice(`Waiting for the customer’s ${wallet} payment notification.`);
      } else {
        setQrPaymentModalVisible(false);
        cashlessCartSnapshotRef.current = null;
        setPaymentType(null);
        setSelectedEwallet(null);
        setNotes('');
        setDetectedWalletSignal(null);
        setPaymentSignalDetected(false);
        setDetectedAmount(0);
        setPaymentNotice('This order is completed; payment and sales were recorded by the server.');
      }
    } catch (error) {
      Alert.alert('Order not submitted', getApiErrorMessage(error, 'The server did not confirm this order. Your cart remains available to retry.'));
    } finally {
      submittingOrder.current = false;
      setIsPlacingOrder(false);
      if (paymentType === 'cashless' && !paymentOrder?.id) setWalletPickerVisible(false);
    }
  };

  const cancelCashlessOrder = () => {
    if (!paymentOrder?.id) return;
    Alert.alert(
      'Cancel pending order?',
      paymentSignalDetected
        ? 'Check the official wallet account. Continue only if no payment was received; a notification is not proof. If payment arrived, confirm it and use the refund workflow.'
        : 'Check the official wallet account and continue only if no payment was received. No income or stock change will be recorded.',
      [
        {text: 'Keep pending', style: 'cancel'},
        {text: 'Checked wallet — cancel', style: 'destructive', onPress: async () => {
          setIsPlacingOrder(true);
          try {
            await cancelOrder(paymentOrder.id, {
              reason: 'Staff checked the wallet account and cancelled before payment confirmation.',
              payment_not_received: true,
            });
            setQrPaymentModalVisible(false);
            if (cashlessCartSnapshotRef.current?.length) {
              setCart(cashlessCartSnapshotRef.current);
            }
            cashlessCartSnapshotRef.current = null;
            setPaymentOrder(null);
            setPaymentSignalDetected(false);
            setDetectedWalletSignal(null);
            setDetectedAmount(0);
            setPaymentAutoConfirmError('');
            setPaymentSignalWarning('');
            setPaymentType(null);
            setSelectedEwallet(null);
            setWalletPickerVisible(false);
            setPaymentNotice('Pending cashless order cancelled. Your cart is restored; choose a payment method to continue.');
            setCheckoutModalVisible(true);
          } catch (error) {
            Alert.alert('Order not cancelled', getApiErrorMessage(error, 'The order is still pending. Check Order history and try again.'));
          } finally {
            setIsPlacingOrder(false);
          }
        }},
      ],
    );
  };

  const markCashlessPaymentFailed = () => {
    if (!paymentOrder?.id) return;
    Alert.alert(
      'Mark payment attempt failed?',
      'Check the actual wallet account first. If payment was received but the notification did not match, leave the order pending and retry the payment check instead of marking it failed.',
      [
        {text: 'Keep pending', style: 'cancel'},
        {text: 'Mark failed', style: 'destructive', onPress: async () => {
          setIsPlacingOrder(true);
          try {
            const result = await markPaymentFailed(paymentOrder.id, {
              reason: 'Staff checked the wallet and confirmed this payment attempt was not completed.',
              payment_not_received: true,
            });
            setPaymentOrder(result.order);
            setQrPaymentModalVisible(false);
            setPaymentType(null);
            setPaymentNotice('Payment attempt for this order was marked failed. No income or stock was recorded; retry or cancel it from Order history.');
          } catch (error) {
            Alert.alert('Could not update payment', getApiErrorMessage(error, 'The server did not confirm this payment status change.'));
          } finally {
            setIsPlacingOrder(false);
          }
        }},
      ],
    );
  };

  const handleChooseWallet = async wallet => {
    const notificationAccess = await isNotificationAccessGranted();
    if (!notificationAccess) {
      setWalletPickerVisible(false);
      Alert.alert(
        'Enable notification access',
        'GastoTrack needs Android notification access to detect the selected wallet payment.',
        [
          {text: 'Not now', style: 'cancel'},
          {text: 'Open settings', onPress: openNotificationSettings},
        ],
      );
      return;
    }
    setPaymentType('cashless');
    setSelectedEwallet(wallet);
    handlePlaceOrder('cashless', wallet);
  };

  // Render product card
  const renderProduct = ({ item: product }) => (
    <TouchableOpacity
      style={[styles.productCard, !cartHydrated && styles.productCardDisabled]}
      onPress={() => handleOpenProduct(product)}
      disabled={!cartHydrated}
      accessibilityRole="button"
      accessibilityLabel={`View ${product.name} details`}
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
        actionIcon="cart-outline"
        actionLabel="View your cart"
        onActionPress={cartHydrated && cart.length > 0 ? handleOpenCheckout : undefined}
      />

      {!cartHydrated ? (
        <View style={styles.cartLoadingNotice} accessibilityLiveRegion="polite">
          <ActivityIndicator size="small" color={COLORS.accent} />
          <Text style={styles.cartLoadingText}>Restoring your saved cart…</Text>
        </View>
      ) : null}

      <TouchableOpacity
        style={styles.historyLink}
        onPress={() => navigation.navigate('OrderHistory')}
        accessibilityRole="button">
        <Icon name="history" size={18} color={COLORS.accentDark} />
        <Text style={styles.historyLinkText}>Order history</Text>
        <Icon name="chevron-right" size={19} color={COLORS.textMuted} />
      </TouchableOpacity>

      {paymentNotice ? (
        <View style={styles.paymentNotice} accessibilityLiveRegion="polite">
          <Icon name="check-circle-outline" size={19} color={COLORS.success} />
          <Text style={styles.paymentNoticeText}>{paymentNotice}</Text>
          <TouchableOpacity onPress={() => setPaymentNotice('')} accessibilityLabel="Dismiss payment notice">
            <Icon name="close" size={18} color={COLORS.textGray} />
          </TouchableOpacity>
        </View>
      ) : null}

      {/* Cart Badge */}
      {cartHydrated && cart.length > 0 && (
        <TouchableOpacity style={styles.cartBadge} onPress={handleOpenCheckout} accessibilityRole="button" accessibilityLabel="View your cart">
          <Text style={styles.cartBadgeText}>
            View your cart · {cart.length} item{cart.length !== 1 ? 's' : ''} · ₱{getCartSubtotal().toFixed(2)}
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
        ListEmptyComponent={productsLoading ? (
          <View style={styles.emptyProductsState}>
            <ActivityIndicator size="large" color={COLORS.accent} />
            <Text style={styles.emptyProductsText}>Loading products…</Text>
          </View>
        ) : (
          <View style={styles.emptyProductsState}>
            <Icon name="store-alert-outline" size={48} color={COLORS.textMuted} />
            <Text style={styles.emptyProductsTitle}>
              {availableProducts.length ? 'No products in this category' : 'No products available yet'}
            </Text>
            <Text style={styles.emptyProductsText}>
              {availableProducts.length
                ? 'Choose another category to see available products.'
                : 'Add products in Inventory before creating an order.'}
            </Text>
            {!availableProducts.length ? (
              <TouchableOpacity style={styles.inventoryButton} onPress={() => navigation.navigate('Stock')}>
                <Text style={styles.inventoryButtonText}>Open Inventory</Text>
              </TouchableOpacity>
            ) : null}
          </View>
        )}
      />

      {/* Product details appear before adding the item to the cart. */}
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
                          const q = Number.parseInt(quantity, 10) || 1;
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
                          const q = Number.parseInt(quantity, 10) || 1;
                          setQuantity(String(q + 1));
                        }}>
                        <Text style={styles.quantityButtonText}>+</Text>
                      </TouchableOpacity>
                    </View>
                  </View>

                  {/* Add to Cart Button */}
                  <TouchableOpacity
                    style={styles.addToCartButton}
                    onPress={handleAddToCart}
                    accessibilityRole="button"
                    accessibilityLabel={`Add ${selectedProduct.name} to cart`}>
                    <Text style={styles.addToCartButtonText}>
                      Add to Cart • ₱{(selectedProduct.price * (Number.parseInt(quantity, 10) || 1)).toFixed(2)}
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
        onRequestClose={() => {
          if (!isPlacingOrder) setCheckoutModalVisible(false);
        }}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Checkout</Text>
              <TouchableOpacity
                onPress={() => setCheckoutModalVisible(false)}
                disabled={isPlacingOrder}
                accessibilityLabel="Close checkout">
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>

              {/* Payment Type Badge */}
              <View style={[
                styles.paymentTypeBadge,
                paymentType === 'cash' ? styles.cashBadge : paymentType === 'cashless' ? styles.cashlessBadge : styles.paymentTypeUnselectedBadge,
              ]}>
                <Text style={styles.paymentTypeBadgeText}>
                  {paymentType === 'cash' ? 'Cash payment' : paymentType === 'cashless' ? 'Cashless payment' : 'Choose a payment method'}
                </Text>
              </View>

              <View style={styles.detailSection}>
                <Text style={styles.detailSectionTitle}>Choose payment method</Text>
                <View style={styles.chipRow}>
                  {[
                    {value: 'cash', label: 'Cash'},
                    {value: 'cashless', label: 'Cashless'},
                  ].map(method => {
                    const selected = paymentType === method.value;
                    return (
                      <TouchableOpacity
                        key={method.value}
                        style={[styles.paymentChip, selected && styles.paymentChipActive]}
                        onPress={() => handleSelectPaymentMethod(method.value)}
                        disabled={isPlacingOrder}
                        accessibilityRole="radio"
                        accessibilityLabel={`Select ${method.label} payment method`}
                        accessibilityState={{selected}}>
                        <Text style={[
                          styles.paymentChipText,
                          selected && styles.paymentChipTextActive,
                        ]}>
                          {method.label}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
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
                      <TouchableOpacity onPress={() => handleRemoveFromCart(item.product.id)} disabled={isPlacingOrder}>
                        <Text style={styles.removeItemText}>✕</Text>
                      </TouchableOpacity>
                    </View>
                  </View>
                ))}
              </View>

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
                style={styles.paymentTypeCancelButton}
                onPress={() => setCheckoutModalVisible(false)}
                disabled={isPlacingOrder}>
                <Text style={styles.paymentTypeCancelText}>Add more products</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.placeOrderButton, (!cartHydrated || !paymentType || !cart.length || isPlacingOrder) && styles.placeOrderDisabled]}
                onPress={handleProceedToPayment}
                disabled={!cartHydrated || !paymentType || !cart.length || isPlacingOrder}
                accessibilityRole="button"
                accessibilityLabel={paymentType === 'cash'
                  ? 'Save cash order'
                  : paymentType === 'cashless' ? 'Choose cashless wallet' : 'Choose a payment method'}>
                {isPlacingOrder ? (
                  <ActivityIndicator color={COLORS.textWhite} />
                ) : (
                  <Text style={styles.placeOrderButtonText}>
                    {paymentType === 'cash'
                      ? 'Save cash order'
                      : paymentType === 'cashless' ? (selectedEwallet ? `Continue with ${selectedEwallet}` : 'Choose GCash or Maya') : 'Choose a payment method'}
                  </Text>
                )}
              </TouchableOpacity>

            </ScrollView>

          </View>
        </View>
      </Modal>

      {/* Choose a wallet after Cashless is selected. */}
      <Modal
        visible={walletPickerVisible}
        animationType="fade"
        transparent
        onRequestClose={() => {
          if (!isPlacingOrder) setWalletPickerVisible(false);
        }}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Choose a wallet</Text>
              <TouchableOpacity
                onPress={() => setWalletPickerVisible(false)}
                disabled={isPlacingOrder}
                accessibilityLabel="Close wallet selection">
                <Text style={styles.modalClose}>×</Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.qrInstructionText}>Select the wallet the customer will use. GastoTrack will wait for a received notification matching the order total.</Text>
            <View style={styles.walletChoices}>
              {['GCash', 'Maya'].map(wallet => (
                <TouchableOpacity
                  key={wallet}
                  style={[styles.walletChoiceButton, isPlacingOrder && styles.placeOrderDisabled]}
                  onPress={() => handleChooseWallet(wallet)}
                  disabled={isPlacingOrder}
                  accessibilityRole="button"
                  accessibilityLabel={`Choose ${wallet}`}>
                  {isPlacingOrder && selectedEwallet === wallet
                    ? <ActivityIndicator color={COLORS.textWhite} />
                    : <Text style={styles.placeOrderButtonText}>{wallet}</Text>}
                </TouchableOpacity>
              ))}
            </View>
            <TouchableOpacity
              style={styles.paymentTypeCancelButton}
              onPress={() => setWalletPickerVisible(false)}
              disabled={isPlacingOrder}>
              <Text style={styles.paymentTypeCancelText}>Back to checkout</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Cashless payment waiting and automatic notification matching. */}
      <Modal
        visible={qrPaymentModalVisible}
        animationType="fade"
        transparent
        onRequestClose={() => {}}>
        <View style={styles.modalOverlay}>
          <View style={styles.qrModalCard}>
            
            {paymentSignalDetected ? (
              <>
                <Text style={styles.qrSuccessTitle}>{paymentAutoConfirmError ? 'Payment match needs retry' : 'Payment detected — checking'}</Text>
                <View style={styles.qrSuccessIcon}>
                  {isCheckingWalletPayment
                    ? <ActivityIndicator size="large" color={COLORS.accentDark} />
                    : <Icon name={paymentAutoConfirmError ? 'alert-circle-outline' : 'check-circle-outline'} size={58} color={paymentAutoConfirmError ? COLORS.warning : COLORS.success} />}
                </View>

                <View style={styles.qrAmountBox}>
                  <Text style={styles.qrAmountLabel}>{selectedEwallet} received notification</Text>
                  <Text style={styles.qrAmountValue}>₱{detectedAmount.toFixed(2)}</Text>
                  <Text style={styles.qrAmountLabel}>Order total: ₱{Number(paymentOrder?.total || 0).toFixed(2)}</Text>
                </View>

                <Text style={styles.qrSuccessText}>
                  {paymentAutoConfirmError
                    ? `The wallet and amount matched, but the order could not be saved: ${paymentAutoConfirmError}`
                    : 'The wallet and exact order amount matched. GastoTrack is saving the payment now.'}
                </Text>
                {paymentAutoConfirmError ? (
                  <TouchableOpacity
                    style={styles.placeOrderButton}
                    onPress={() => {
                      autoConfirmAttemptRef.current = null;
                      setPaymentAutoConfirmError('');
                      setPaymentCheckAttempt(attempt => attempt + 1);
                    }}>
                    <Text style={styles.placeOrderButtonText}>Retry saving payment</Text>
                  </TouchableOpacity>
                ) : null}
                <Text style={styles.walletVerificationNote}>This automatic match uses the phone notification and is not verification by GCash or Maya.</Text>
                {paymentAutoConfirmError ? (
                  <TouchableOpacity style={styles.qrCancelButton} onPress={cancelCashlessOrder} disabled={isPlacingOrder || isCheckingWalletPayment} accessibilityLabel="Cancel pending order">
                    <Text style={styles.qrCancelButtonText}>Cancel pending order</Text>
                  </TouchableOpacity>
                ) : null}
              </>
            ) : (
              <>
                <Text style={styles.qrTitle}>Waiting for {selectedEwallet} payment</Text>

                <View style={styles.qrAmountBox}>
                  <Text style={styles.qrAmountLabel}>Order total</Text>
                  <Text style={styles.qrAmountValue}>₱{Number(paymentOrder?.total || 0).toFixed(2)}</Text>
                </View>

                <ActivityIndicator size="large" color={COLORS.accentDark} />
                <Text style={styles.qrWaitingText}>Keep this screen open. GastoTrack is waiting for a received {selectedEwallet} notification with the exact order total.</Text>
                {paymentSignalWarning ? <Text style={styles.paymentSignalWarning}>{paymentSignalWarning}</Text> : null}
                <Text style={styles.walletVerificationNote}>If no notification arrives, check that GastoTrack notification access is enabled in Android Settings.</Text>
                <Text style={styles.walletVerificationNote}>Automatic matching uses the phone notification; it is not provider verification.</Text>

                <View style={styles.waitingActions}>
                  <TouchableOpacity
                    style={[styles.qrCancelButton, styles.waitingActionButton]}
                    onPress={cancelCashlessOrder}
                    disabled={isPlacingOrder || isCheckingWalletPayment}
                    accessibilityLabel="Cancel pending order">
                    <Text style={[styles.qrCancelButtonText, styles.waitingActionText]}>Cancel pending order</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.qrCancelButton, styles.waitingActionButton]}
                    onPress={markCashlessPaymentFailed}
                    disabled={isPlacingOrder || isCheckingWalletPayment}
                    accessibilityLabel="Mark payment attempt failed">
                    <Text style={[styles.qrCancelButtonText, styles.waitingActionText]}>Mark attempt failed</Text>
                  </TouchableOpacity>
                </View>
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
  historyLink: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-end',
    gap: 6,
    paddingHorizontal: 20,
    paddingVertical: 7,
  },
  historyLinkText: {fontSize: 12, fontWeight: '700', color: COLORS.accentDark},
  paymentNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    marginHorizontal: 20,
    marginTop: 4,
    marginBottom: 8,
    paddingHorizontal: 12,
    paddingVertical: 11,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#B9E3C2',
    backgroundColor: '#EEF8F0',
  },
  paymentNoticeText: {flex: 1, fontSize: 12, lineHeight: 17, color: COLORS.textDark},
  cartLoadingNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginHorizontal: 20,
    marginTop: 10,
    padding: 10,
    borderRadius: 10,
    backgroundColor: COLORS.surface,
  },
  cartLoadingText: {fontSize: 12, color: COLORS.textGray},
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
  emptyProductsState: {
    minHeight: 280,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 28,
  },
  emptyProductsTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: COLORS.textDark,
    textAlign: 'center',
    marginTop: 12,
  },
  emptyProductsText: {
    fontSize: 13,
    lineHeight: 19,
    color: COLORS.textGray,
    textAlign: 'center',
    marginTop: 6,
  },
  inventoryButton: {
    marginTop: 15,
    paddingVertical: 10,
    paddingHorizontal: 17,
    borderRadius: 11,
    backgroundColor: COLORS.accent,
  },
  inventoryButtonText: {
    color: COLORS.textWhite,
    fontSize: 13,
    fontWeight: '700',
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
  productCardDisabled: {opacity: 0.55},
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
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
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
  paymentTypeUnselectedBadge: {backgroundColor: '#F2F4F3'},
  paymentTypeBadgeText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  walletChoiceButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    width: '100%',
    alignSelf: 'stretch',
    flexGrow: 0,
    flexShrink: 0,
    marginBottom: 10,
  },
  walletChoices: {flexDirection: 'column', alignItems: 'stretch', width: '100%'},
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
  placeOrderDisabled: {opacity: 0.65},
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
  qrSuccessText: {
    fontSize: 14,
    color: '#666666',
    marginTop: 16,
    textAlign: 'center',
  },
  walletVerificationNote: {
    fontSize: 11,
    lineHeight: 16,
    color: COLORS.textGray,
    textAlign: 'center',
    marginTop: 12,
  },
  paymentSignalWarning: {
    fontSize: 12,
    lineHeight: 17,
    color: COLORS.warning,
    textAlign: 'center',
    marginTop: 10,
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
  waitingActions: {flexDirection: 'row', gap: 10, width: '100%', marginTop: 18},
  waitingActionButton: {flex: 1, marginTop: 0, paddingHorizontal: 8, minHeight: 48, justifyContent: 'center'},
  waitingActionText: {fontSize: 12},
  qrCancelButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#C62828',
    textAlign: 'center',
  },
});

export default OrderQueueScreen;
