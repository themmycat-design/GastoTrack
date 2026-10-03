import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Image,
  ActivityIndicator,
  ScrollView,
  Modal,
  TextInput,
  Platform,
  PermissionsAndroid,
  Alert,
} from 'react-native';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import { scanReceipt, parseReceiptData, formatForTransaction } from '../../services/OCRService';
import { useTransactions } from '../../context/TransactionContext';
import { COLORS } from '../../theme';

const INCOME_CATEGORIES = ['Sales', 'Delivery', 'Catering', 'Others'];
const EXPENSE_CATEGORIES = [
  'Ingredients', 'Packaging', 'Utilities',
  'Rent', 'Salaries', 'Equipment', 'Supplies', 'Others',
];
const SOURCES = ['Cash', 'GCash', 'Maya', 'GrabPay', 'ShopeePay'];

const ReceiptScannerScreen = ({ navigation }) => {
  const { addTransaction } = useTransactions();
  const [imageUri, setImageUri] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [ocrResult, setOcrResult] = useState(null);
  const [parsedData, setParsedData] = useState(null);
  const [reviewModalVisible, setReviewModalVisible] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // Form state for review/edit
  const [amount, setAmount] = useState('');
  const [type, setType] = useState('Expense');
  const [source, setSource] = useState('Cash');
  const [category, setCategory] = useState('');
  const [date, setDate] = useState('');
  const [notes, setNotes] = useState('');

  const categories = type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;

  // Request camera permission on Android
  const requestCameraPermission = async () => {
    if (Platform.OS === 'android') {
      try {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.CAMERA,
          {
            title: 'Camera Permission',
            message: 'GastoTrack needs access to your camera to scan receipts.',
            buttonNeutral: 'Ask Me Later',
            buttonNegative: 'Cancel',
            buttonPositive: 'OK',
          },
        );
        return granted === PermissionsAndroid.RESULTS.GRANTED;
      } catch (err) {
        console.warn(err);
        return false;
      }
    }
    return true; // iOS handles permissions automatically
  };

  const handleTakePhoto = async () => {
    // Request permission first
    const hasPermission = await requestCameraPermission();

    if (!hasPermission) {
      Alert.alert(
        'Camera Permission Required',
        'Please enable camera permission in your device settings to scan receipts.',
        [{ text: 'OK' }]
      );
      return;
    }

    const result = await launchCamera({
      mediaType: 'photo',
      quality: 0.8,
      saveToPhotos: false,
      cameraType: 'back',
    });

    if (result.didCancel) {
      console.log('User cancelled camera');
      return;
    }

    if (result.errorCode) {
      console.error('Camera Error:', result.errorCode, result.errorMessage);
      Alert.alert(
        'Camera Error',
        result.errorMessage || 'Failed to open camera. Please try again.',
        [{ text: 'OK' }]
      );
      return;
    }

    if (result.assets && result.assets[0]) {
      const uri = result.assets[0].uri;
      setImageUri(uri);
      await processImage(uri);
    }
  };

  const handlePickImage = async () => {
    const result = await launchImageLibrary({
      mediaType: 'photo',
      quality: 0.8,
    });

    if (result.didCancel) {
      console.log('User cancelled image picker');
      return;
    }

    if (result.errorCode) {
      console.error('Image Picker Error:', result.errorCode, result.errorMessage);
      Alert.alert(
        'Error',
        result.errorMessage || 'Failed to pick image. Please try again.',
        [{ text: 'OK' }]
      );
      return;
    }

    if (result.assets && result.assets[0]) {
      const uri = result.assets[0].uri;
      setImageUri(uri);
      await processImage(uri);
    }
  };

  const processImage = async (uri) => {
    setScanning(true);
    setOcrResult(null);
    setParsedData(null);

    try {
      // Step 1: Extract text from image
      const ocrResponse = await scanReceipt(uri);
      
      if (!ocrResponse.success) {
        Alert.alert(
          'Scan Failed',
          'Failed to scan receipt. Please ensure the image is clear and try again.',
          [{ text: 'OK' }]
        );
        setScanning(false);
        return;
      }

      setOcrResult(ocrResponse);

      // Step 2: Parse the extracted text
      const parsed = parseReceiptData(ocrResponse.text);
      setParsedData(parsed);

      // Step 3: Format for transaction form
      const formatted = formatForTransaction(parsed);
      
      // Pre-fill form
      setAmount(formatted.amount);
      setType(formatted.type);
      setSource(formatted.source);
      setCategory(formatted.category);
      setDate(formatted.date);
      setNotes(formatted.notes);

      setScanning(false);
      setReviewModalVisible(true);

    } catch (error) {
      console.error('Processing error:', error);
      Alert.alert(
        'Processing Error',
        'Error processing receipt. Please try again.',
        [{ text: 'OK' }]
      );
      setScanning(false);
    }
  };

  const handleSaveTransaction = async () => {
    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      Alert.alert('Validation Error', 'Please enter a valid amount.', [{ text: 'OK' }]);
      return;
    }
    if (!category) {
      Alert.alert('Validation Error', 'Please select a category.', [{ text: 'OK' }]);
      return;
    }

    const datePattern = /^\d{4}-\d{2}-\d{2}$/;
    const [year, month, day] = date.split('-').map(Number);
    const parsedDate = new Date(`${date}T00:00:00`);
    const today = new Date();
    today.setHours(23, 59, 59, 999);
    const isRealDate = parsedDate.getFullYear() === year
      && parsedDate.getMonth() === month - 1
      && parsedDate.getDate() === day;
    if (!datePattern.test(date) || Number.isNaN(parsedDate.getTime()) || !isRealDate || parsedDate > today) {
      Alert.alert('Validation Error', 'Enter a valid date in YYYY-MM-DD format that is not in the future.');
      return;
    }

    setIsSaving(true);
    const result = await addTransaction({
      amount: numericAmount,
      type,
      source,
      category,
      date,
      notes,
      entryMethod: 'ocr',
      metadata: {
        ocr_confidence: parsedData?.confidence ?? null,
        ocr_provider: ocrResult?.provider || ocrResult?.method || 'device_ocr',
      },
    });
    setIsSaving(false);

    if (!result.success) {
      const responseData = result.error?.response?.data;
      const validationMessage = Object.values(responseData?.errors || {}).flat().join('\n');
      Alert.alert(
        'Could not save transaction',
        validationMessage || responseData?.message || 'Please check your connection and try again.',
      );
      return;
    }

    Alert.alert('Transaction saved', 'The receipt was added to Transactions.', [{
      text: 'OK',
      onPress: () => {
        setReviewModalVisible(false);
        resetScanner();
        navigation.goBack();
      },
    }]);
  };

  const resetScanner = () => {
    setImageUri(null);
    setOcrResult(null);
    setParsedData(null);
    setAmount('');
    setType('Expense');
    setSource('Cash');
    setCategory('');
    setDate('');
    setNotes('');
  };

  const getConfidenceColor = (confidence) => {
    if (confidence >= 80) return '#4CAF50';
    if (confidence >= 60) return '#FFC107';
    return '#FF5722';
  };

  const getConfidenceLabel = (confidence) => {
    if (confidence >= 80) return 'High';
    if (confidence >= 60) return 'Medium';
    return 'Low';
  };

  return (
    <View style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity 
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Text style={styles.backIcon}>←</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Scan Receipt</Text>
        <View style={styles.placeholder} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent}>

        {/* Instructions */}
        <View style={styles.instructionBox}>
          <Text style={styles.instructionIcon}>📸</Text>
          <Text style={styles.instructionTitle}>How to scan a receipt</Text>
          <Text style={styles.instructionText}>
            1. Take a clear photo of the receipt{'\n'}
            2. Ensure all text is visible and not blurry{'\n'}
            3. Review and edit the extracted data{'\n'}
            4. Save the transaction
          </Text>
        </View>

        {/* Action Buttons */}
        <View style={styles.buttonRow}>
          <TouchableOpacity 
            style={styles.actionButton}
            onPress={handleTakePhoto}>
            <Text style={styles.actionButtonIcon}>📷</Text>
            <Text style={styles.actionButtonText}>Take Photo</Text>
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.actionButton}
            onPress={handlePickImage}>
            <Text style={styles.actionButtonIcon}>🖼️</Text>
            <Text style={styles.actionButtonText}>Choose from Gallery</Text>
          </TouchableOpacity>
        </View>

        {/* Image Preview */}
        {imageUri && (
          <View style={styles.previewSection}>
            <Text style={styles.sectionTitle}>Receipt Image</Text>
            <Image 
              source={{ uri: imageUri }} 
              style={styles.previewImage}
              resizeMode="contain"
            />
            {scanning && (
              <View style={styles.scanningOverlay}>
                <ActivityIndicator size="large" color={COLORS.accent} />
                <Text style={styles.scanningText}>Scanning receipt...</Text>
              </View>
            )}
          </View>
        )}

        {/* OCR Result Preview */}
        {ocrResult && !scanning && (
          <View style={styles.resultSection}>
            <Text style={styles.sectionTitle}>Extracted Text</Text>
            <View style={styles.textBox}>
              <Text style={styles.extractedText}>{ocrResult.text}</Text>
            </View>
          </View>
        )}

        {/* Parsed Data Preview */}
        {parsedData && !scanning && (
          <View style={styles.resultSection}>
            <Text style={styles.sectionTitle}>Detected Information</Text>
            <View style={styles.dataCard}>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Amount:</Text>
                <Text style={styles.dataValue}>
                  ₱{parsedData.amount?.toFixed(2) || 'Not detected'}
                </Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Type:</Text>
                <Text style={styles.dataValue}>{parsedData.type}</Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Category:</Text>
                <Text style={styles.dataValue}>
                  {parsedData.category || 'Not detected'}
                </Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Source:</Text>
                <Text style={styles.dataValue}>{parsedData.source}</Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Confidence:</Text>
                <View style={[
                  styles.confidenceBadge,
                  { backgroundColor: getConfidenceColor(parsedData.confidence) },
                ]}>
                  <Text style={styles.confidenceText}>
                    {getConfidenceLabel(parsedData.confidence)} ({parsedData.confidence}%)
                  </Text>
                </View>
              </View>
            </View>
            <TouchableOpacity 
              style={styles.reviewButton}
              onPress={() => setReviewModalVisible(true)}>
              <Text style={styles.reviewButtonText}>Review & Save Transaction</Text>
            </TouchableOpacity>
          </View>
        )}

      </ScrollView>

      {/* Review & Edit Modal */}
      <Modal
        visible={reviewModalVisible}
        animationType="slide"
        transparent
        onRequestClose={() => setReviewModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Review Transaction</Text>
              <TouchableOpacity onPress={() => setReviewModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>

              {/* Confidence Badge */}
              {parsedData && (
                <View style={[
                  styles.confidenceBox,
                  { backgroundColor: getConfidenceColor(parsedData.confidence) + '20' },
                ]}>
                  <Text style={styles.confidenceBoxLabel}>
                    OCR Confidence: {getConfidenceLabel(parsedData.confidence)} ({parsedData.confidence}%)
                  </Text>
                  <Text style={styles.confidenceBoxHint}>
                    {parsedData.confidence >= 80 
                      ? '✅ Data looks accurate' 
                      : '⚠️ Please verify the details below'}
                  </Text>
                </View>
              )}

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

              {/* Date */}
              <Text style={styles.fieldLabel}>Date</Text>
              <TextInput
                style={styles.input}
                placeholder="YYYY-MM-DD"
                value={date}
                onChangeText={setDate}
              />

              {/* Notes */}
              <Text style={styles.fieldLabel}>Notes</Text>
              <TextInput
                style={[styles.input, styles.notesInput]}
                placeholder="Add notes..."
                value={notes}
                onChangeText={setNotes}
                multiline
              />

              <TouchableOpacity
                style={[styles.saveButton, isSaving && styles.saveButtonDisabled]}
                onPress={handleSaveTransaction}
                disabled={isSaving}>
                {isSaving
                  ? <ActivityIndicator color="#FFFFFF" />
                  : <Text style={styles.saveButtonText}>Save Transaction</Text>}
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
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    width: 40,
  },
  backIcon: {
    fontSize: 24,
    color: COLORS.textDark,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: COLORS.textDark,
  },
  placeholder: {
    width: 40,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 120,
  },
  instructionBox: {
    backgroundColor: '#E8FBF5',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
    marginBottom: 20,
  },
  instructionIcon: {
    fontSize: 40,
    marginBottom: 8,
  },
  instructionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: COLORS.textDark,
    marginBottom: 8,
  },
  instructionText: {
    fontSize: 13,
    color: '#666666',
    textAlign: 'center',
    lineHeight: 20,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 20,
  },
  actionButton: {
    flex: 1,
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
  },
  actionButtonIcon: {
    fontSize: 32,
    marginBottom: 8,
  },
  actionButtonText: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  previewSection: {
    marginBottom: 20,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333333',
    marginBottom: 10,
  },
  previewImage: {
    width: '100%',
    height: 300,
    borderRadius: 12,
    backgroundColor: '#E0E0E0',
  },
  scanningOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.7)',
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanningText: {
    fontSize: 14,
    color: '#FFFFFF',
    marginTop: 12,
  },
  resultSection: {
    marginBottom: 20,
  },
  textBox: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  extractedText: {
    fontSize: 12,
    color: '#333333',
    lineHeight: 18,
  },
  dataCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#E0E0E0',
    marginBottom: 12,
  },
  dataRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 0.5,
    borderBottomColor: '#F0F0F0',
  },
  dataLabel: {
    fontSize: 13,
    color: '#888888',
    fontWeight: '500',
  },
  dataValue: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  confidenceBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  confidenceText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  reviewButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
  },
  reviewButtonText: {
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
    fontSize: 20,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  modalClose: {
    fontSize: 24,
    color: '#888888',
  },
  confidenceBox: {
    borderRadius: 12,
    padding: 12,
    marginBottom: 16,
  },
  confidenceBoxLabel: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#333333',
  },
  confidenceBoxHint: {
    fontSize: 12,
    color: '#666666',
    marginTop: 4,
  },
  fieldLabel: {
    fontSize: 12,
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
    backgroundColor: '#FFFFFF',
    borderWidth: 1.5,
    borderColor: '#CCCCCC',
  },
  chipActive: {
    backgroundColor: COLORS.accent,
    borderColor: COLORS.accent,
  },
  chipText: {
    fontSize: 13,
    color: '#333333',
    fontWeight: '500',
  },
  chipTextActive: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  saveButton: {
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
    marginTop: 20,
  },
  saveButtonText: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  saveButtonDisabled: {
    opacity: 0.6,
  },
});

export default ReceiptScannerScreen;
