import React, { createContext, useContext, useState } from 'react';
import { useStock } from './StockContext';
import { useTransactions } from './TransactionContext';

const OrderContext = createContext();

// Order statuses
export const ORDER_STATUS = {
  PENDING: 'Pending',      // Just placed, waiting for staff
  PREPARING: 'Preparing',  // Staff is making the order
  READY: 'Ready',          // Order is ready for pickup
  COMPLETED: 'Completed',  // Order picked up and paid
  CANCELLED: 'Cancelled',  // Order cancelled
};

export const OrderProvider = ({ children }) => {
  const [orders, setOrders] = useState([]);
  const { deductIngredients } = useStock();
  const { addTransaction } = useTransactions();

  // Create new order
  const createOrder = (orderData) => {
    const newOrder = {
      id: `ORD-${Date.now()}`,
      orderNumber: generateOrderNumber(),
      items: orderData.items, // [{ product, quantity }]
      customerName: orderData.customerName || 'Guest',
      customerPhone: orderData.customerPhone || '',
      notes: orderData.notes || '',
      subtotal: orderData.subtotal,
      discount: orderData.discount || 0,
      total: orderData.total,
      paymentMethod: orderData.paymentMethod || 'Cash',
      status: ORDER_STATUS.PENDING,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      preparedAt: null,
      readyAt: null,
      completedAt: null,
    };

    setOrders(prev => [newOrder, ...prev]);
    return newOrder;
  };

  // Update order status
  const updateOrderStatus = (orderId, newStatus) => {
    const now = new Date().toISOString();
    
    setOrders(prev =>
      prev.map(order => {
        if (order.id !== orderId) return order;

        const updated = {
          ...order,
          status: newStatus,
          updatedAt: now,
        };

        // Set timestamps based on status
        if (newStatus === ORDER_STATUS.PREPARING) {
          updated.preparedAt = now;
        } else if (newStatus === ORDER_STATUS.READY) {
          updated.readyAt = now;
        } else if (newStatus === ORDER_STATUS.COMPLETED) {
          updated.completedAt = now;
          // Deduct stock and create transaction
          handleOrderCompletion(order);
        }

        return updated;
      })
    );
  };

  // Handle order completion (deduct stock, create transaction)
  const handleOrderCompletion = (order) => {
    // Deduct ingredients from stock
    order.items.forEach(orderItem => {
      const { product, quantity } = orderItem;
      
      // Multiply ingredient quantities by order quantity
      const ingredientsToDeduct = product.ingredients.map(ing => ({
        ...ing,
        quantity: ing.quantity * quantity,
      }));

      deductIngredients(ingredientsToDeduct);
    });

    // Create income transaction
    addTransaction({
      amount: order.total.toFixed(2),
      type: 'Income',
      source: order.paymentMethod,
      category: 'Sales',
      date: new Date().toISOString().split('T')[0],
      notes: `Order #${order.orderNumber} - ${order.customerName}`,
      entryMethod: 'Order System',
    });
  };

  // Update order details
  const updateOrder = (orderId, updates) => {
    setOrders(prev =>
      prev.map(order =>
        order.id === orderId
          ? { ...order, ...updates, updatedAt: new Date().toISOString() }
          : order
      )
    );
  };

  // Cancel order
  const cancelOrder = (orderId, reason = '') => {
    setOrders(prev =>
      prev.map(order =>
        order.id === orderId
          ? {
              ...order,
              status: ORDER_STATUS.CANCELLED,
              cancelReason: reason,
              updatedAt: new Date().toISOString(),
            }
          : order
      )
    );
  };

  // Delete order (for testing/cleanup)
  const deleteOrder = (orderId) => {
    setOrders(prev => prev.filter(order => order.id !== orderId));
  };

  // Get orders by status
  const getOrdersByStatus = (status) => {
    return orders.filter(order => order.status === status);
  };

  // Get active orders (not completed or cancelled)
  const getActiveOrders = () => {
    return orders.filter(
      order =>
        order.status !== ORDER_STATUS.COMPLETED &&
        order.status !== ORDER_STATUS.CANCELLED
    );
  };

  // Get order counts by status
  const getOrderCounts = () => {
    return {
      pending: orders.filter(o => o.status === ORDER_STATUS.PENDING).length,
      preparing: orders.filter(o => o.status === ORDER_STATUS.PREPARING).length,
      ready: orders.filter(o => o.status === ORDER_STATUS.READY).length,
      completed: orders.filter(o => o.status === ORDER_STATUS.COMPLETED).length,
      cancelled: orders.filter(o => o.status === ORDER_STATUS.CANCELLED).length,
      active: getActiveOrders().length,
    };
  };

  // Calculate order statistics
  const getOrderStats = () => {
    const completedOrders = orders.filter(
      o => o.status === ORDER_STATUS.COMPLETED
    );

    const totalRevenue = completedOrders.reduce(
      (sum, order) => sum + order.total,
      0
    );

    const avgOrderValue = completedOrders.length > 0
      ? totalRevenue / completedOrders.length
      : 0;

    return {
      totalOrders: orders.length,
      completedOrders: completedOrders.length,
      totalRevenue,
      avgOrderValue,
    };
  };

  // Generate order number (format: YYYYMMDD-XXX)
  const generateOrderNumber = () => {
    const date = new Date();
    const dateStr = date.toISOString().split('T')[0].replace(/-/g, '');
    const todayOrders = orders.filter(order =>
      order.createdAt.startsWith(date.toISOString().split('T')[0])
    );
    const sequenceNum = (todayOrders.length + 1).toString().padStart(3, '0');
    return `${dateStr}-${sequenceNum}`;
  };

  return (
    <OrderContext.Provider
      value={{
        orders,
        createOrder,
        updateOrderStatus,
        updateOrder,
        cancelOrder,
        deleteOrder,
        getOrdersByStatus,
        getActiveOrders,
        getOrderCounts,
        getOrderStats,
        ORDER_STATUS,
      }}>
      {children}
    </OrderContext.Provider>
  );
};

export const useOrders = () => useContext(OrderContext);
