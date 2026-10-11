import React, { useRef, useState } from 'react';
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
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import { scanReceipt, parseReceiptData, formatForTransaction, suggestReceiptCategory } from '../../services/OCRService';
import { useTransactions } from '../../context/TransactionContext';
import {getApiErrorMessage} from '../../services/apiErrors';
import { COLORS, SHADOWS } from '../../theme';

const ReceiptScannerScreen = ({ navigation }) => {
  const {
    addTransaction,
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
    TRANSACTION_SOURCES,
  } = useTransactions();
  const [imageUri, setImageUri] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [ocrResult, setOcrResult] = useState(null);
  const [parsedData, setParsedData] = useState(null);
  const [reviewModalVisible, setReviewModalVisible] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isSelectingImage, setIsSelectingImage] = useState(false);
  const [showExtractedText, setShowExtractedText] = useState(false);
  const savingRef = useRef(false);

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
    if (isSelectingImage || scanning || isSaving) return;
    setIsSelectingImage(true);
    try {
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

      let result;
      try {
        result = await launchCamera({
          mediaType: 'photo',
          quality: 0.8,
          saveToPhotos: false,
          cameraType: 'back',
        });
      } catch {
        Alert.alert('Camera unavailable', 'GastoTrack could not open the camera. Check camera permission in your device settings and try again.');
        return;
      }

      if (result.didCancel) return;

      if (result.errorCode) {
        const permissionError = /permission|access/i.test(`${result.errorCode} ${result.errorMessage || ''}`);
        Alert.alert(
          permissionError ? 'Camera Permission Required' : 'Camera Error',
          permissionError
            ? 'Allow camera access in your device settings, then try again.'
            : result.errorMessage || 'Failed to open camera. Please try again.',
          [{ text: 'OK' }]
        );
        return;
      }

      if (result.assets && result.assets[0]) {
        const uri = result.assets[0].uri;
        setImageUri(uri);
        await processImage(uri);
      }
    } finally {
      setIsSelectingImage(false);
    }
  };

  const handlePickImage = async () => {
    if (isSelectingImage || scanning || isSaving) return;
    setIsSelectingImage(true);
    try {
      let result;
      try {
        result = await launchImageLibrary({
          mediaType: 'photo',
          quality: 0.8,
        });
      } catch {
        Alert.alert('Photo access unavailable', 'GastoTrack could not open your photos. Allow photo access in your device settings and try again.');
        return;
      }

      if (result.didCancel) return;

      if (result.errorCode) {
        const permissionError = /permission|access/i.test(`${result.errorCode} ${result.errorMessage || ''}`);
        Alert.alert(
          permissionError ? 'Photo Permission Required' : 'Photo Picker Error',
          permissionError
            ? 'Allow GastoTrack to access photos in your device settings, then try again.'
            : result.errorMessage || 'Failed to pick an image. Please try again.',
          [{ text: 'OK' }]
        );
        return;
      }

      if (result.assets && result.assets[0]) {
        const uri = result.assets[0].uri;
        setImageUri(uri);
        await processImage(uri);
      }
    } finally {
      setIsSelectingImage(false);
    }
  };

  const processImage = async (uri) => {
    setScanning(true);
    setShowExtractedText(false);
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

      if (!ocrResponse.text?.trim()) {
        Alert.alert('No text detected', 'Try a clearer photo with the full receipt visible.');
        setScanning(false);
        return;
      }

      // Step 2: Parse the extracted text
      const parsed = parseReceiptData(ocrResponse.text);

      // Step 3: Format for transaction form
      const formatted = formatForTransaction(parsed, parsed.type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES);
      setParsedData({
        ...parsed,
        category: formatted.category || null,
        confidence: parsed.confidence - (parsed.category ? 15 : 0) + (formatted.category ? 15 : 0),
      });
      
      // Pre-fill form
      setAmount(formatted.amount);
      setType(formatted.type);
      setSource(TRANSACTION_SOURCES.includes(formatted.source) ? formatted.source : '');
      setCategory(formatted.category);
      setDate(formatted.date);
      setNotes(formatted.notes);

      setScanning(false);
      setReviewModalVisible(true);

    } catch {
      Alert.alert(
        'Processing Error',
        'Error processing receipt. Please try again.',
        [{ text: 'OK' }]
      );
      setScanning(false);
    }
  };

  const handleSaveTransaction = async () => {
    if (savingRef.current) return;
    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      Alert.alert('Validation Error', 'Please enter a valid amount.', [{ text: 'OK' }]);
      return;
    }
    if (!category) {
      Alert.alert('Validation Error', 'Please select a category.', [{ text: 'OK' }]);
      return;
    }
    if (!categories.includes(category)) {
      Alert.alert('Category unavailable', 'Choose a category from the current list.');
      return;
    }
    if (!TRANSACTION_SOURCES.includes(source)) {
      Alert.alert('Source required', 'Choose a payment source before saving.');
      return;
    }
    if (!notes.trim()) {
      Alert.alert('Name required', 'Enter a name for this transaction before saving.');
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

    savingRef.current = true;
    setIsSaving(true);
    let result;
    try {
      result = await addTransaction({
        amount: numericAmount,
        type,
        source,
        category,
        date,
        notes: notes.trim(),
        entryMethod: 'ocr',
        metadata: {
          ocr_confidence: parsedData?.confidence ?? null,
          ocr_provider: ocrResult?.provider || ocrResult?.method || 'device_ocr',
        },
      });
    } finally {
      savingRef.current = false;
      setIsSaving(false);
    }

    if (!result.success) {
      const responseData = result.error?.response?.data;
      const validationMessage = Object.values(responseData?.errors || {}).flat().join('\n');
      Alert.alert(
        'Could not save transaction',
        validationMessage || responseData?.message || getApiErrorMessage(result.error),
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
    setShowExtractedText(false);
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

      <StaffScreenHeader
        title="Scan receipt"
        leftIcon="arrow-left"
        leftLabel="Back"
        onLeftPress={() => navigation.goBack()}
        centered
      />

      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.heroCard}>
          <View style={styles.heroIcon}><Icon name="line-scan" size={30} color={COLORS.textWhite} /></View>
          <Text style={styles.heroTitle}>Scan receipt</Text>
          <View style={styles.stepList}>
            <View style={styles.stepRow}>
              <View style={styles.stepNumber}><Text style={styles.stepNumberText}>1</Text></View>
              <View style={styles.stepCopy}>
                <Text style={styles.stepTitle}>Take or choose a photo</Text>
                <Text style={styles.stepDescription}>Keep the full receipt visible and the text clear.</Text>
              </View>
            </View>
            <View style={styles.stepRow}>
              <View style={styles.stepNumber}><Text style={styles.stepNumberText}>2</Text></View>
              <View style={styles.stepCopy}>
                <Text style={styles.stepTitle}>Scan the receipt</Text>
                <Text style={styles.stepDescription}>The app reads the text and fills in transaction details.</Text>
              </View>
            </View>
            <View style={styles.stepRow}>
              <View style={styles.stepNumber}><Text style={styles.stepNumberText}>3</Text></View>
              <View style={styles.stepCopy}>
                <Text style={styles.stepTitle}>Review and save</Text>
                <Text style={styles.stepDescription}>Check the amount, source, category, and date before saving.</Text>
              </View>
            </View>
          </View>
        </View>

        <View style={styles.buttonColumn}>
          <TouchableOpacity
            style={[styles.primaryAction, (isSelectingImage || scanning || isSaving) && styles.actionButtonDisabled]}
            disabled={isSelectingImage || scanning || isSaving}
            onPress={handleTakePhoto}
            activeOpacity={0.78}
            accessibilityRole="button"
            accessibilityLabel="Take a receipt photo">
            <View style={styles.primaryActionIcon}><Icon name="camera-outline" size={34} color={COLORS.textWhite} /></View>
            <Text style={styles.primaryActionTitle}>Take photo</Text>
            <Text style={styles.primaryActionHint}>Open camera</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.secondaryAction, (isSelectingImage || scanning || isSaving) && styles.actionButtonDisabled]}
            disabled={isSelectingImage || scanning || isSaving}
            onPress={handlePickImage}
            activeOpacity={0.78}
            accessibilityRole="button"
            accessibilityLabel="Choose a receipt from photos">
            <View style={styles.secondaryActionIcon}><Icon name="image-outline" size={34} color={COLORS.accentDark} /></View>
            <Text style={styles.secondaryActionTitle}>Choose photo</Text>
            <Text style={styles.secondaryActionHint}>Open gallery</Text>
          </TouchableOpacity>
        </View>

        {/* Image Preview */}
        {imageUri && (
          <View style={styles.previewSection}>
            <View style={styles.sectionHeading}>
              <Text style={styles.sectionTitle}>Receipt preview</Text>
              {!scanning ? <Text style={styles.sectionHint}>Choose another photo to rescan</Text> : null}
            </View>
            <Image 
              source={{ uri: imageUri }} 
              style={styles.previewImage}
              resizeMode="contain"
            />
            {scanning && (
              <View style={styles.scanningOverlay}>
                <ActivityIndicator size="large" color={COLORS.accent} />
                <Text style={styles.scanningText}>Reading receipt text...</Text>
              </View>
            )}
          </View>
        )}

        {parsedData && !scanning && (
          <View style={styles.resultSection}>
            <Text style={styles.sectionTitle}>Detected details</Text>
            <Text style={styles.resultHint}>These values may be wrong. Review and correct them before saving.</Text>
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
              <Text style={styles.reviewButtonText}>Review transaction</Text>
              <Icon name="arrow-right" size={20} color={COLORS.textWhite} />
            </TouchableOpacity>
          </View>
        )}

        {ocrResult && !scanning ? (
          <View style={styles.extractedCard}>
            <TouchableOpacity style={styles.extractedToggle} onPress={() => setShowExtractedText(value => !value)} accessibilityRole="button" accessibilityLabel={showExtractedText ? 'Hide extracted text' : 'Show extracted text'}>
              <Text style={styles.extractedToggleText}>Extracted text</Text>
              <Icon name={showExtractedText ? 'chevron-up' : 'chevron-down'} size={21} color={COLORS.textGray} />
            </TouchableOpacity>
            {showExtractedText ? <Text style={styles.extractedText}>{ocrResult.text || 'No text found.'}</Text> : null}
          </View>
        ) : null}

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
              <View style={styles.modalHeadingCopy}>
                <Text style={styles.modalTitle}>Review receipt</Text>
                <Text style={styles.modalSubtitle}>Nothing is saved until you confirm below.</Text>
              </View>
              <TouchableOpacity style={styles.modalCloseButton} onPress={() => setReviewModalVisible(false)} accessibilityLabel="Close receipt review" accessibilityRole="button">
                <Icon name="close" size={22} color={COLORS.textDark} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>

              {/* Confidence Badge */}
              {parsedData && (
                <View style={[
                  styles.confidenceBox,
                  { backgroundColor: getConfidenceColor(parsedData.confidence) + '20' },
                ]}>
                  <Text style={styles.confidenceBoxLabel}>Text detection: {getConfidenceLabel(parsedData.confidence)} ({parsedData.confidence}%)</Text>
                  <Text style={styles.confidenceBoxHint}>
                    Check each detail against the receipt. Text detection can make mistakes.
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
                    onPress={() => {
                      setType(t);
                      setCategory(suggestReceiptCategory(parsedData || {}, t === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES, t));
                    }}>
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
                {TRANSACTION_SOURCES.map(s => (
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
              {!category ? <Text style={styles.categoryReviewHint}>No matching category detected. Choose one before saving.</Text> : null}
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

              <Text style={styles.fieldLabel}>{type === 'Expense' ? 'Expense name' : 'Income name'}</Text>
              <TextInput
                style={[styles.input, styles.notesInput]}
                placeholder="What was this transaction for?"
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
                  : <Text style={styles.saveButtonText}>Save transaction</Text>}
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
    backgroundColor: COLORS.background,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 40,
  },
  heroCard: {backgroundColor: COLORS.textDark, borderRadius: 20, padding: 20, marginBottom: 24, ...SHADOWS.card},
  heroIcon: {width: 52, height: 52, borderRadius: 15, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center', alignSelf: 'center', marginBottom: 16},
  heroTitle: {fontSize: 20, fontWeight: '800', color: COLORS.textWhite, lineHeight: 25, textAlign: 'center'},
  stepList: {gap: 14, marginTop: 22},
  stepRow: {flexDirection: 'row', alignItems: 'flex-start', gap: 12},
  stepNumber: {width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  stepNumberText: {fontSize: 12, fontWeight: '800', color: COLORS.textWhite},
  stepCopy: {flex: 1},
  stepTitle: {fontSize: 13, fontWeight: '800', color: COLORS.textWhite},
  stepDescription: {fontSize: 11, lineHeight: 16, color: '#C7E2D9', marginTop: 3},
  buttonColumn: {flexDirection: 'row', gap: 12, marginBottom: 24},
  primaryAction: {flex: 1, minHeight: 154, alignItems: 'center', justifyContent: 'center', padding: 12, backgroundColor: COLORS.accentDark, borderRadius: 17, ...SHADOWS.card},
  secondaryAction: {flex: 1, minHeight: 154, alignItems: 'center', justifyContent: 'center', padding: 12, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: 17},
  primaryActionIcon: {width: 58, height: 58, borderRadius: 17, backgroundColor: 'rgba(255,255,255,0.17)', alignItems: 'center', justifyContent: 'center', marginBottom: 12},
  secondaryActionIcon: {width: 58, height: 58, borderRadius: 17, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center', marginBottom: 12},
  primaryActionTitle: {fontSize: 14, fontWeight: '800', color: COLORS.textWhite, textAlign: 'center'},
  primaryActionHint: {fontSize: 11, color: '#E5F6F0', marginTop: 4, textAlign: 'center'},
  secondaryActionTitle: {fontSize: 14, fontWeight: '800', color: COLORS.textDark, textAlign: 'center'},
  secondaryActionHint: {fontSize: 11, color: COLORS.textGray, marginTop: 4, textAlign: 'center'},
  actionButtonDisabled: {opacity: 0.55},
  previewSection: {
    marginBottom: 24,
  },
  sectionHeading: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 10},
  sectionHint: {flex: 1, textAlign: 'right', fontSize: 11, color: COLORS.textGray},
  sectionTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: COLORS.textDark,
    marginBottom: 12,
  },
  previewImage: {
    width: '100%',
    height: 260,
    borderRadius: 16,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  scanningOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.7)',
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanningText: {
    fontSize: 14,
    color: '#FFFFFF',
    marginTop: 12,
  },
  resultSection: {
    marginBottom: 16,
  },
  resultHint: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginBottom: 12},
  extractedCard: {backgroundColor: COLORS.surface, borderRadius: 14, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 14, marginBottom: 20},
  extractedToggle: {minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  extractedToggleText: {fontSize: 13, fontWeight: '700', color: COLORS.textDark},
  extractedText: {
    fontSize: 12,
    color: COLORS.textGray,
    lineHeight: 18,
    paddingBottom: 14,
  },
  dataCard: {
    backgroundColor: COLORS.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: 14,
  },
  dataRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 0.5,
    borderBottomColor: COLORS.border,
  },
  dataLabel: {
    fontSize: 13,
    color: COLORS.textGray,
    fontWeight: '500',
  },
  dataValue: {
    fontSize: 14,
    fontWeight: 'bold',
    color: COLORS.textDark,
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
    backgroundColor: COLORS.accentDark,
    borderRadius: 12,
    minHeight: 50,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 9,
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
    backgroundColor: COLORS.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    maxHeight: '92%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  modalHeadingCopy: {flex: 1, paddingRight: 12},
  modalTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: COLORS.textDark,
  },
  modalSubtitle: {fontSize: 12, color: COLORS.textGray, marginTop: 4},
  modalCloseButton: {width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.surfaceMuted},
  confidenceBox: {
    borderRadius: 12,
    padding: 12,
    marginBottom: 16,
  },
  confidenceBoxLabel: {
    fontSize: 13,
    fontWeight: 'bold',
    color: COLORS.textDark,
  },
  confidenceBoxHint: {
    fontSize: 12,
    color: COLORS.textGray,
    marginTop: 4,
  },
  fieldLabel: {
    fontSize: 12,
    fontWeight: 'bold',
    color: COLORS.textGray,
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
    borderRadius: 10,
    backgroundColor: COLORS.surfaceMuted,
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
    color: COLORS.textGray,
    fontWeight: 'bold',
  },
  toggleTextActive: {
    color: COLORS.textDark,
  },
  amountInputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surfaceMuted,
    borderRadius: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  pesoSign: {
    fontSize: 18,
    color: COLORS.textDark,
    marginRight: 6,
  },
  amountInput: {
    flex: 1,
    fontSize: 24,
    paddingVertical: 10,
    color: COLORS.textDark,
  },
  input: {
    backgroundColor: COLORS.surfaceMuted,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: COLORS.textDark,
    borderWidth: 1,
    borderColor: COLORS.border,
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
    backgroundColor: COLORS.surface,
    borderWidth: 1.5,
    borderColor: COLORS.border,
  },
  chipActive: {
    backgroundColor: COLORS.accent,
    borderColor: COLORS.accent,
  },
  chipText: {
    fontSize: 13,
    color: COLORS.textDark,
    fontWeight: '500',
  },
  chipTextActive: {
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  categoryReviewHint: {fontSize: 12, color: COLORS.textGray, marginBottom: 8},
  saveButton: {
    backgroundColor: COLORS.accentDark,
    borderRadius: 12,
    minHeight: 50,
    justifyContent: 'center',
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
