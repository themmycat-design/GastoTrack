import React, { useContext, useState } from 'react';
import { View, Text, TouchableOpacity, Alert, ActivityIndicator } from 'react-native';
import api from '../../services/api';
import { TransactionContext } from '../../context/TransactionContext';
import { StockContext } from '../../context/StockContext';
// ... iba pang imports mo (ProductContext, etc.)

const DashboardScreen = () => {
  const { fetchTransactions } = useContext(TransactionContext);
  const { fetchStock } = useContext(StockContext);
  const [isProcessing, setIsProcessing] = useState(false);

  // Bagong function para sa "Customer Ordered"
  const handleCustomerOrder = async (product) => {
    // Optional: Confirmation dialog para iwas accidental press
    Alert.alert(
      "Confirm Order",
      `Record order for ${product.name}?`,
      [
        { text: "Cancel", style: "cancel" },
        { 
          text: "Confirm", 
          onPress: async () => {
            setIsProcessing(true);
            try {
              // I-send ang order sa Laravel
              const response = await api.post('/orders', {
                product_id: product.id,
                quantity: 1 // Default to 1, or gawing dynamic kung may quantity selector ka
              });

              if (response.data.success) {
                // I-refresh ang data sa contexts para mag-update ang Dashboard at Stock list
                await fetchTransactions();
                await fetchStock();
                Alert.alert("Success", "Transaction recorded and stock updated!");
              }
            } catch (error) {
              console.log("Order error:", error);
              Alert.alert("Error", "Could not process the order. Please try again.");
            } finally {
              setIsProcessing(false);
            }
          }
        }
      ]
    );
  };

  // ... sa loob ng render method / return ...
  // Hanapin mo yung button mo para sa order at ilagay ang handler
  return (
    <View>
      {/* Halimbawa ng pagtawag sa Product Card */}
      <TouchableOpacity 
         onPress={() => handleCustomerOrder(product)}
         disabled={isProcessing}
      >
        <Text>Customer Ordered</Text>
      </TouchableOpacity>
    </View>
  );
};

export default DashboardScreen;