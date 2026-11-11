import React, {useMemo, useRef, useState, useEffect} from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Modal,
  TextInput,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  Image,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {AppAlert as Alert} from '../../services/AppDialogService';
import SkeletonPlaceholder, {SkeletonBlock} from '../../components/SkeletonPlaceholder';
import { useProducts } from '../../context/ProductContext';
import { useTransactions } from '../../context/TransactionContext';
import { useOrders } from '../../context/OrderContext';
import {isNotificationAccessGranted, openNotificationSettings, subscribeToNotifications} from '../../modules/NotificationModule';
import {getApiErrorMessage} from '../../services/apiErrors';
import api from '../../services/api';
import { COLORS } from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import GastoModalMascot from '../../components/staff/GastoModalMascot';
import {productCategoryTabs, productSizesInCategory, productsInCategory, productsInSize} from '../../constants/productCategories';
import {ingredientUsageForCartLine} from '../../utils/orderIngredientUsage';

const OrderQueueScreen = ({navigation, route}) => {
  const {products, isLoading: productsLoading, loadError: productError, fetchProducts} = useProducts();
  const {PRODUCT_CATEGORIES, fetchTransactionOptions} = useTransactions();
  const {createOrder, confirmPaymentFromNotification, markPaymentFailed, cancelOrder, cart, setCart, cartHydrated} = useOrders();
  const submittingOrder = useRef(false);
  const autoConfirmAttemptRef = useRef(null);
  const cashlessCartSnapshotRef = useRef(null);

  const [selectedProduct, setSelectedProduct] = useState(null);
  const [editingCartIndex, setEditingCartIndex] = useState(null);
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
  const [ingredientAmounts, setIngredientAmounts] = useState({});
  const [ingredientError, setIngredientError] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [selectedSize, setSelectedSize] = useState('All sizes');
  const [refreshing, setRefreshing] = useState(false);

  const refreshProducts = async () => {
    setRefreshing(true);
    try { await Promise.all([fetchProducts(), fetchTransactionOptions()]); }
    finally { setRefreshing(false); }
  };

  const availableProducts = useMemo(
    () => products.filter(product => product.is_available !== false),
    [products],
  );
  const categories = useMemo(() => productCategoryTabs(PRODUCT_CATEGORIES), [PRODUCT_CATEGORIES]);
  const sizes = useMemo(() => productSizesInCategory(availableProducts, selectedCategory), [availableProducts, selectedCategory]);
  const filteredProducts = productsInSize(productsInCategory(availableProducts, selectedCategory), selectedSize);

  useEffect(() => {
    const requestedCategory = route?.params?.category;
    const matchingCategory = requestedCategory && categories.find(
      category => category.toLowerCase() === requestedCategory.toLowerCase(),
    );
    if (matchingCategory) {
      setSelectedCategory(matchingCategory);
      const requestedSize = route?.params?.size;
      setSelectedSize(productSizesInCategory(availableProducts, matchingCategory).includes(requestedSize) ? requestedSize : 'All sizes');
      navigation.setParams({category: undefined, size: undefined});
    } else if (!categories.some(category => category.toLowerCase() === selectedCategory.toLowerCase())) {
      setSelectedCategory('All');
      setSelectedSize('All sizes');
    }
  }, [availableProducts, categories, navigation, route?.params?.category, route?.params?.size, selectedCategory]);

  useEffect(() => {
    if (!route?.params?.category && selectedSize !== 'All sizes' && !sizes.includes(selectedSize)) setSelectedSize('All sizes');
  }, [route?.params?.category, selectedSize, sizes]);

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

      const reference = String(transaction.reference || '').trim();
      if (!reference) {
        setPaymentSignalWarning(`${selectedEwallet} notification arrived, but its official reference number could not be read. Still waiting for a matching notification.`);
        return;
      }

      let capture;
      try {
        capture = JSON.parse(transaction.capturePayload || 'null');
      } catch {
        capture = null;
      }
      if (!capture?.client_id || capture.reference !== reference || capture.source !== selectedEwallet.toLowerCase()) {
        setPaymentSignalWarning('The wallet alert could not be saved on this device. Still waiting for a captured payment.');
        return;
      }

      const signal = {
        amount,
        source: selectedEwallet.toLowerCase(),
        reference,
        merchant: transaction.merchant || '',
        capturedAt: new Date().toISOString(),
        notificationId: capture.client_id,
        capture,
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

    api.post('/wallet-notifications/batch', {captures: [detectedWalletSignal.capture]}).then(() => confirmPaymentFromNotification(paymentOrder.id, {
      amount: detectedWalletSignal.amount,
      currency: paymentOrder.paymentCurrency || 'PHP',
      source: detectedWalletSignal.source,
      reference: detectedWalletSignal.reference,
      notification_id: detectedWalletSignal.notificationId,
    })).then(result => {
      if (!isCurrent) return;
      setPaymentOrder(result.order);
      setQrPaymentModalVisible(false);
      setWalletPickerVisible(false);
      setPaymentType(null);
      setSelectedEwallet(null);
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
    setEditingCartIndex(null);
    setSelectedProduct(product);
    setQuantity('1');
    setIngredientAmounts(Object.fromEntries((product.ingredients || []).map(ingredient => [
      String(ingredient.stockId), String(ingredient.quantity),
    ])));
    setIngredientError('');
    setDetailModalVisible(true);
  };

  const handleEditCartItem = index => {
    const item = cart[index];
    if (!item || isPlacingOrder) return;
    setEditingCartIndex(index);
    setSelectedProduct(item.product);
    setQuantity(String(item.quantity));
    const amounts = Object.fromEntries((item.product.ingredients || []).map(ingredient => [
      String(ingredient.stockId), String(ingredient.quantity),
    ]));
    (item.ingredientOverrides || []).forEach(override => {
      amounts[String(override.stock_item_id)] = String(override.quantity);
    });
    setIngredientAmounts(amounts);
    setIngredientError('');
    setCheckoutModalVisible(false);
    setTimeout(() => setDetailModalVisible(true), 350);
  };

  const closeProductDetails = () => {
    setDetailModalVisible(false);
    if (editingCartIndex !== null) {
      setTimeout(() => setCheckoutModalVisible(true), 350);
      setEditingCartIndex(null);
    }
  };

  const addProductToCart = (qty, overrides) => {
    if (!cartHydrated || !selectedProduct) return;
    setCart(previousCart => {
      const variantKey = JSON.stringify(overrides);
      const existingIndex = previousCart.findIndex(item => item.product?.id === selectedProduct.id
        && JSON.stringify(item.ingredientOverrides || []) === variantKey);
      if (existingIndex < 0) return [...previousCart, {
        product: selectedProduct, quantity: qty, ...(overrides.length ? {ingredientOverrides: overrides} : {}),
      }];
      return previousCart.map((item, index) => index === existingIndex
        ? {...item, quantity: Number(item.quantity) + qty}
        : item);
    });
  };

  // Add to cart from product detail, then return to the product list.
  const handleAddToCart = () => {
    const qty = Number.parseInt(quantity, 10);
    if (!Number.isInteger(qty) || qty < 1 || qty > 1000) {
      Alert.alert('Invalid quantity', 'Enter 1 to 1000 products.');
      return;
    }

    const overrides = [];
    for (const ingredient of selectedProduct.ingredients || []) {
      const stockId = Number(ingredient.stockId);
      const amountText = String(ingredientAmounts[String(stockId)] ?? ingredient.quantity).trim();
      const amount = Number(amountText);
      const regular = Number(ingredient.quantity);
      if (!Number.isInteger(stockId) || stockId < 1 || !/^\d+(?:\.\d{1,4})?$/.test(amountText)
        || !Number.isFinite(amount) || amount < 0 || amount > regular + 0.000001) {
        setIngredientError(`Enter a valid amount for ${ingredient.name} between 0 and ${regular} ${ingredient.unit || ''}.`);
        return;
      }
      if (Math.abs(amount - regular) > 0.000001) {
        overrides.push({stock_item_id: stockId, quantity: amount});
      }
    }

    if (editingCartIndex !== null) {
      setCart(previousCart => previousCart.map((item, index) => index === editingCartIndex
        ? {product: selectedProduct, quantity: qty, ...(overrides.length ? {ingredientOverrides: overrides} : {})}
        : item));
      setEditingCartIndex(null);
      setTimeout(() => setCheckoutModalVisible(true), 350);
    } else {
      addProductToCart(qty, overrides);
    }

    setDetailModalVisible(false);
    setQuantity('1');
    setIngredientError('');
  };

  // Remove item from cart
  const handleRemoveFromCart = (cartIndex) => {
    setCart(prev => prev.filter((_, index) => index !== cartIndex));
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
        ingredientOverrides: item.ingredientOverrides || [],
      })),
      customerName: 'Walk-in Customer',
      customerPhone: null,
      paymentMethod,
    };

    try {
      const order = await createOrder(orderData);
      setPaymentOrder(order.offlinePending ? null : order);
      setCheckoutModalVisible(false);
      setWalletPickerVisible(false);

      if (order.offlinePending) {
        setQrPaymentModalVisible(false);
        setPaymentType(null);
        setSelectedEwallet(null);
        setPaymentNotice('Cash order completed on this device. It will sync automatically when online.');
        Alert.alert('Order completed on this device', 'Cash payment was recorded on this phone. The sale will sync automatically when online; shared stock and owner reports update after the server accepts it.', [
          {text: 'Continue ordering'},
          {text: 'View order', onPress: () => navigation.navigate('OrderHistory')},
        ]);
      } else if (orderPaymentType === 'cashless' && order.paymentStatus !== 'confirmed') {
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
        setDetectedWalletSignal(null);
        setPaymentSignalDetected(false);
        setDetectedAmount(0);
        setPaymentNotice('This order is completed; payment and sales were recorded by the server.');
      }
    } catch (error) {
      const message = getApiErrorMessage(error, 'The server did not confirm this order. Your cart remains available to retry.');
      const stockShortage = /insufficient stock|order ingredients are unavailable|is inactive and cannot be used for this order/i.test(message);
      Alert.alert(stockShortage ? 'Not enough stock' : 'Order not submitted',
        stockShortage ? message.replace(/\s*No payment or inventory changes were made\.\s*$/i, '').trim() : message);
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
      accessibilityLabel={`View ${product.name}${product.size ? ` ${product.size}` : ''} details`}
      activeOpacity={0.8}>
      <View style={styles.productEmoji}>
        {product.image
          ? <Image source={{uri: product.image}} style={styles.productImage} />
          : <Icon name="image-outline" size={42} color={COLORS.textMuted} />}
      </View>
      <View style={styles.productInfo}>
        <Text style={styles.productName}>{product.name}</Text>
        <Text style={styles.productCategory}>{product.category}{product.size ? ` · ${product.size}` : ''}</Text>
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
        <SkeletonPlaceholder style={styles.cartLoadingNotice} accessibilityLabel="Restoring saved cart">
          <SkeletonBlock width="66%" height={12} />
        </SkeletonPlaceholder>
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
          {/completed|order was saved/.test(paymentNotice) ? (
            <Image source={require('../../../assets/mascot/gasto-order-success.png')} style={styles.paymentNoticeMascot} resizeMode="contain" accessibilityLabel="Gasto celebrating the completed order" />
          ) : (
            <Icon name="check-circle-outline" size={19} color={COLORS.success} />
          )}
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
                accessibilityRole="button"
                accessibilityLabel={`Filter products by ${cat}`}
                onPress={() => {setSelectedCategory(cat); setSelectedSize('All sizes');}}>
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
        {sizes.length > 0 ? <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.categoryRow} style={styles.sizeRow}>
          {['All sizes', ...sizes].map(size => <TouchableOpacity key={size} accessibilityRole="button" accessibilityLabel={`Filter ${selectedCategory} by ${size}`} style={[styles.categoryChip, styles.sizeChip, selectedSize === size && styles.categoryChipActive]} onPress={() => setSelectedSize(size)}>
            <Text style={[styles.categoryChipText, selectedSize === size && styles.categoryChipTextActive]}>{size}</Text>
          </TouchableOpacity>)}
        </ScrollView> : null}
      </View>

      {/* Products Grid */}
      <FlatList
        data={filteredProducts}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refreshProducts} colors={[COLORS.accent]} />}
        keyExtractor={item => item.id}
        renderItem={renderProduct}
        numColumns={2}
        contentContainerStyle={styles.productsList}
        columnWrapperStyle={styles.productRow}
        ListEmptyComponent={productsLoading ? <ProductGridSkeleton /> : (
          <View style={styles.emptyProductsState}>
            <Image source={require('../../../assets/mascot/gasto-empty.png')} style={styles.emptyProductsMascot} resizeMode="contain" accessibilityLabel="Gasto beside an empty tray" />
            <Text style={styles.emptyProductsTitle}>
              {productError && !availableProducts.length ? 'Products unavailable' : availableProducts.length ? 'No products in this category' : 'No products available yet'}
            </Text>
            <Text style={styles.emptyProductsText}>
              {productError && !availableProducts.length
                ? 'Pull down to refresh.'
                : availableProducts.length
                ? 'Choose another category to see available products.'
                : 'Add products in Inventory before creating an order.'}
            </Text>
            {!availableProducts.length && !productError ? (
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
        onRequestClose={closeProductDetails}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            
            {selectedProduct && (
              <>
                <View style={styles.modalHeader}>
                  <GastoModalMascot variant="thinking" />
                  <Text style={styles.modalTitle}>{selectedProduct.name}</Text>
                  <TouchableOpacity onPress={closeProductDetails} accessibilityRole="button" accessibilityLabel="Close product details">
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
                    <Text style={styles.productDetailCategory}>{selectedProduct.category}{selectedProduct.size ? ` · ${selectedProduct.size}` : ''}</Text>
                    <Text style={styles.productDetailPrice}>₱{selectedProduct.price.toFixed(2)}</Text>
                  </View>

                  {/* Description */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailSectionTitle}>Description</Text>
                    <Text style={styles.descriptionText}>{selectedProduct.description}</Text>
                  </View>

                  {/* Ingredients */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailSectionTitle}>Ingredients per product</Text>
                    <Text style={styles.ingredientHint}>Choose regular, half, none, or enter a smaller amount. Changes apply to every item in this cart line.</Text>
                    {(selectedProduct.ingredients || []).map(ing => {
                      const stockId = String(ing.stockId);
                      const regular = Number(ing.quantity);
                      const current = ingredientAmounts[stockId] ?? String(ing.quantity);
                      return (
                        <View key={stockId} style={styles.ingredientEditor}>
                          <View style={styles.ingredientEditorHeading}>
                            <Text style={styles.ingredientName}>{ing.name}</Text>
                            <Text style={styles.ingredientRegular}>Regular: {ing.quantity} {ing.unit || ''}</Text>
                          </View>
                          <View style={styles.ingredientControls}>
                            {[['Regular', regular], ['Half', Number((regular / 2).toFixed(4))], ['None', 0]].map(([label, amount]) => (
                              <TouchableOpacity
                                key={label}
                                style={[styles.ingredientChoice, current !== '' && Number(current) === amount && styles.ingredientChoiceActive]}
                                onPress={() => {
                                  setIngredientAmounts(previous => ({...previous, [stockId]: String(amount)}));
                                  setIngredientError('');
                                }}
                                accessibilityRole="button"
                                accessibilityLabel={`${label} ${ing.name}`}>
                                <Text style={[styles.ingredientChoiceText, current !== '' && Number(current) === amount && styles.ingredientChoiceTextActive]}>{label}</Text>
                              </TouchableOpacity>
                            ))}
                            <TextInput
                              style={styles.ingredientAmountInput}
                              keyboardType="decimal-pad"
                              value={current}
                              onChangeText={value => {
                                setIngredientAmounts(previous => ({...previous, [stockId]: value}));
                                setIngredientError('');
                              }}
                              accessibilityLabel={`${ing.name} amount per product`}
                              selectTextOnFocus
                            />
                            <Text style={styles.ingredientUnit}>{ing.unit || ''}</Text>
                          </View>
                        </View>
                      );
                    })}
                    {!(selectedProduct.ingredients || []).length ? <Text style={styles.ingredientHint}>No recipe ingredients are configured for this product.</Text> : null}
                    {ingredientError ? <Text style={styles.ingredientError} accessibilityLiveRegion="polite">{ingredientError}</Text> : null}
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
                    accessibilityLabel={editingCartIndex !== null ? `Save ${selectedProduct.name} cart changes` : `Add ${selectedProduct.name} to cart`}>
                    <Text style={styles.addToCartButtonText}>
                      {editingCartIndex !== null ? 'Save changes' : 'Add to Cart'} • ₱{(selectedProduct.price * (Number.parseInt(quantity, 10) || 1)).toFixed(2)}
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
              <GastoModalMascot variant="welcome" />
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
                <Text style={styles.ingredientHint}>Estimated ingredient use: amount per product × quantity. Stock is deducted once the sale is completed.</Text>
                {cart.map((item, idx) => (
                  <View key={idx} style={styles.cartItemRow}>
                    {item.product.image
                      ? <Image source={{uri: item.product.image}} style={styles.cartItemImage} />
                      : <Icon name="image-outline" size={28} color={COLORS.textMuted} style={styles.cartItemEmoji} />}
                    <View style={styles.cartItemInfo}>
                      <Text style={styles.cartItemName}>{item.product.name}{item.product.size ? ` · ${item.product.size}` : ''}</Text>
                      <Text style={styles.cartItemQty}>Qty: {item.quantity}</Text>
                      {ingredientUsageForCartLine(item).map(ingredient => (
                        <Text key={ingredient.stockId} style={styles.cartItemIngredient}>
                          {ingredient.name}: {item.quantity} × {ingredient.perProduct} {ingredient.unit} = {ingredient.total} {ingredient.unit}
                        </Text>
                      ))}
                      {!(item.product.ingredients || []).length ? <Text style={styles.cartItemIngredient}>No recipe: no ingredient stock will be deducted.</Text> : null}
                      <TouchableOpacity onPress={() => handleEditCartItem(idx)} disabled={isPlacingOrder} accessibilityRole="button" accessibilityLabel={`Edit ${item.product.name} cart item`}>
                        <Text style={styles.cartItemEdit}>Edit ingredients</Text>
                      </TouchableOpacity>
                    </View>
                    <View style={styles.cartItemRight}>
                      <Text style={styles.cartItemPrice}>
                        ₱{(item.product.price * item.quantity).toFixed(2)}
                      </Text>
                      <TouchableOpacity onPress={() => handleRemoveFromCart(idx)} disabled={isPlacingOrder} accessibilityRole="button" accessibilityLabel={`Remove ${item.product.name} from cart`}>
                        <Text style={styles.removeItemText}>✕</Text>
                      </TouchableOpacity>
                    </View>
                  </View>
                ))}
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
              <GastoModalMascot variant="thinking" />
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
          <ScrollView style={styles.qrModalCard} contentContainerStyle={styles.qrModalContent} keyboardShouldPersistTaps="handled">
            <GastoModalMascot variant={paymentSignalDetected ? 'success' : 'thinking'} />
            
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
                <Text style={styles.qrWaitingText}>Checking for a payment notification...</Text>
                {paymentSignalWarning ? <Text style={styles.paymentSignalWarning}>{paymentSignalWarning}</Text> : null}

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

          </ScrollView>
        </View>
      </Modal>

    </View>
  );
};

const ProductGridSkeleton = () => (
  <SkeletonPlaceholder style={styles.productGridSkeleton} accessibilityLabel="Loading order products">
    {[0, 1, 2, 3].map(item => (
      <View key={item} style={styles.skeletonMenuCard}>
        <SkeletonBlock height={96} radius={13} />
        <SkeletonBlock width="62%" height={10} style={styles.skeletonProductCategorySpacer} />
        <SkeletonBlock width="84%" height={13} style={styles.skeletonProductNameSpacer} />
        <SkeletonBlock width="48%" height={12} style={styles.skeletonProductPriceSpacer} />
      </View>
    ))}
  </SkeletonPlaceholder>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  cartBadge: {
    backgroundColor: COLORS.accentDark,
    marginHorizontal: 16,
    marginTop: 12,
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
    paddingHorizontal: 16,
    paddingVertical: 5,
  },
  historyLinkText: {fontSize: 12, fontWeight: '700', color: COLORS.accentDark},
  paymentNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    marginHorizontal: 16,
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
  paymentNoticeMascot: {width: 48, height: 48},
  cartLoadingNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginTop: 8,
    padding: 10,
    borderRadius: 10,
    backgroundColor: COLORS.surface,
  },
  cartLoadingText: {fontSize: 12, color: COLORS.textGray},
  categorySection: {
    backgroundColor: COLORS.background,
    paddingVertical: 8,
  },
  categoryRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    gap: 8,
  },
  sizeRow: {marginTop: 8},
  sizeChip: {minHeight: 40},
  categoryChip: {
    minHeight: 48,
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  categoryChipActive: {
    backgroundColor: COLORS.accentDark,
    borderColor: COLORS.accentDark,
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
    flexGrow: 1,
    paddingHorizontal: 16,
    paddingTop: 6,
    paddingBottom: 22,
  },
  productGridSkeleton: {flexDirection: 'row', flexWrap: 'wrap', gap: 10, paddingTop: 6, paddingBottom: 22},
  skeletonMenuCard: {width: '48%', minHeight: 170, padding: 10, borderRadius: 16, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonProductCategorySpacer: {marginTop: 10},
  skeletonProductNameSpacer: {marginTop: 8},
  skeletonProductPriceSpacer: {marginTop: 9},
  emptyProductsState: {
    minHeight: 230,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 28,
  },
  emptyProductsMascot: {width: 112, height: 112},
  emptyProductsTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: COLORS.textDark,
    textAlign: 'center',
    marginTop: 8,
  },
  emptyProductsText: {
    fontSize: 13,
    lineHeight: 19,
    color: COLORS.textGray,
    textAlign: 'center',
    marginTop: 6,
  },
  inventoryButton: {
    minHeight: 48,
    justifyContent: 'center',
    marginTop: 11,
    paddingVertical: 10,
    paddingHorizontal: 17,
    borderRadius: 11,
    backgroundColor: COLORS.accentDark,
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
    padding: 18,
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
    padding: 16,
    width: '100%',
    maxHeight: '92%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  modalTitle: {
    flex: 1,
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
    backgroundColor: COLORS.accentDark,
    minHeight: 48,
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
    marginBottom: 14,
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
    marginBottom: 14,
  },
  detailSectionTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333333',
    marginBottom: 8,
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
  ingredientHint: {fontSize: 12, lineHeight: 18, color: COLORS.textMuted, marginBottom: 8},
  ingredientEditor: {paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: COLORS.border},
  ingredientEditorHeading: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8},
  ingredientName: {fontSize: 14, fontWeight: '700', color: COLORS.textDark, flex: 1},
  ingredientRegular: {fontSize: 12, color: COLORS.textMuted},
  ingredientControls: {flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 6, marginTop: 8},
  ingredientChoice: {borderWidth: 1, borderColor: COLORS.border, borderRadius: 9, paddingHorizontal: 10, paddingVertical: 8, backgroundColor: COLORS.surface},
  ingredientChoiceActive: {borderColor: COLORS.accent, backgroundColor: COLORS.miniCardBg},
  ingredientChoiceText: {fontSize: 12, color: COLORS.textDark, fontWeight: '600'},
  ingredientChoiceTextActive: {color: COLORS.accentDark},
  ingredientAmountInput: {minWidth: 56, maxWidth: 82, borderWidth: 1, borderColor: COLORS.border, borderRadius: 9, paddingHorizontal: 8, paddingVertical: 6, color: COLORS.textDark, fontSize: 14, textAlign: 'center', backgroundColor: COLORS.surface},
  ingredientUnit: {fontSize: 12, color: COLORS.textMuted},
  ingredientError: {fontSize: 12, color: COLORS.danger, marginTop: 8},
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
    backgroundColor: COLORS.accentDark,
    minHeight: 48,
    borderRadius: 14,
    padding: 14,
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
  cartItemIngredient: {fontSize: 11, color: COLORS.accentDark, marginTop: 2},
  cartItemEdit: {fontSize: 12, color: COLORS.accentDark, fontWeight: '700', marginTop: 6},
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
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  paymentChip: {
    minHeight: 48,
    justifyContent: 'center',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  paymentChipActive: {
    backgroundColor: COLORS.accentDark,
    borderColor: COLORS.accentDark,
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
    marginBottom: 12,
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
    paddingTop: 9,
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
    padding: 14,
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
    width: '100%',
    maxWidth: 400,
    maxHeight: '90%',
    flexGrow: 0,
    flexShrink: 1,
  },
  qrModalContent: {
    padding: 18,
    alignItems: 'center',
  },
  qrTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
    marginBottom: 16,
  },
  qrAmountBox: {
    backgroundColor: '#F9F9F9',
    borderRadius: 12,
    padding: 16,
    width: '100%',
    alignItems: 'center',
    marginBottom: 14,
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
    color: COLORS.textGray,
    marginTop: 12,
    textAlign: 'center',
    lineHeight: 19,
  },
  qrSuccessTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: COLORS.income,
    marginBottom: 16,
    textAlign: 'center',
  },
  qrSuccessIcon: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#E8F5E9',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
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
    marginTop: 16,
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
