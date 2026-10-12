import React, { useRef, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  Platform,
  PermissionsAndroid,
  Alert,
} from 'react-native';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import { scanReceipt, parseReceiptData, buildReceiptCategoryDescription } from '../../services/OCRService';
import { useTransactions } from '../../context/TransactionContext';
import api from '../../services/api';
import {getApiErrorMessage} from '../../services/apiErrors';
import {createIdempotencyKey} from '../../config/orderIdempotency';
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
  const [parsedData, setParsedData] = useState(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isSelectingImage, setIsSelectingImage] = useState(false);
  const [categorySuggestion, setCategorySuggestion] = useState(null);
  const [scanError, setScanError] = useState('');
  const [scanSaved, setScanSaved] = useState(false);
  const [savedManually, setSavedManually] = useState(false);
  const [processingMessage, setProcessingMessage] = useState('');
  const [aiFailed, setAiFailed] = useState(false);
  const [manualEditing, setManualEditing] = useState(false);
  const [manualFormError, setManualFormError] = useState('');
  const [manualDraft, setManualDraft] = useState({
    name: '',
    date: '',
    amount: '',
    type: 'Expense',
    source: '',
    category: '',
    ocrProvider: 'device_ocr',
  });
  const savingRef = useRef(false);
  const scanAttemptIdRef = useRef(null);

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

  const processImage = async (uri, retryCurrentAttempt = false) => {
    if (savingRef.current) return;
    savingRef.current = true;
    if (!retryCurrentAttempt || !scanAttemptIdRef.current) {
      scanAttemptIdRef.current = createIdempotencyKey();
    }

    setImageUri(uri);
    setScanning(true);
    setIsSaving(false);
    setProcessingMessage('Reading receipt text…');
    setScanError('');
    setScanSaved(false);
    setSavedManually(false);
    setParsedData(null);
    setCategorySuggestion(null);
    setAiFailed(false);
    setManualEditing(false);
    setManualFormError('');

    let processingStage = 'scan';
    try {
      const ocrResponse = await scanReceipt(uri);
      if (!ocrResponse.success) {
        setScanError('The receipt could not be read. The photo may be blurry, too dark, or cropped. Please retake a clear, well-lit photo showing the full receipt, then try again. Nothing was saved.');
        return;
      }

      if (!ocrResponse.text?.trim()) {
        setScanError('No readable text was found. The photo may be blurry, too dark, or out of focus. Please retake the full receipt in good light and try again. Nothing was saved.');
        return;
      }

      const parsed = parseReceiptData(ocrResponse.text);
      setParsedData(parsed);
      const numericAmount = Number(parsed.amount);
      if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
        setScanError('The receipt was scanned, but its total amount could not be read. Make sure the printed total is clear and fully in frame, then retake the photo and try again. Nothing was saved.');
        return;
      }

      const transactionDate = parsed.date;
      const datePattern = /^\d{4}-\d{2}-\d{2}$/;
      const [year, month, day] = String(transactionDate || '').split('-').map(Number);
      const parsedDate = new Date(`${transactionDate}T00:00:00`);
      const today = new Date();
      today.setHours(23, 59, 59, 999);
      const isRealDate = parsedDate.getFullYear() === year
        && parsedDate.getMonth() === month - 1
        && parsedDate.getDate() === day;
      if (!datePattern.test(transactionDate || '') || Number.isNaN(parsedDate.getTime()) || !isRealDate || parsedDate > today) {
        setScanError('The receipt date could not be read as a valid date. Make sure the date is clear and visible, then retake the photo and try again. Nothing was saved.');
        return;
      }

      const transactionType = parsed.type === 'Income' ? 'Income' : 'Expense';
      const categories = transactionType === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;
      if (!categories.length) {
        setScanError('No categories are configured for this transaction type. Nothing was saved. Ask the owner to configure categories.');
        return;
      }

      const source = TRANSACTION_SOURCES.includes(parsed.source)
        ? parsed.source
        : TRANSACTION_SOURCES.includes('Cash') ? 'Cash' : '';
      if (!source) {
        setScanError('No valid payment source is configured. Nothing was saved. Ask the owner to configure transaction sources.');
        return;
      }

      const extractedName = parsed.merchant && parsed.merchant !== 'Unknown Merchant'
        ? parsed.merchant
        : '';
      const description = buildReceiptCategoryDescription(parsed, extractedName);
      if (description.trim().length < 4) {
        setScanError('There is not enough readable item or merchant detail for Gasto AI to choose a category. Make sure those details are clear and in frame, then try again. Nothing was saved.');
        return;
      }
      const transactionName = extractedName || description.split(' | ').find(Boolean) || 'Scanned receipt';
      setManualDraft({
        name: transactionName,
        date: transactionDate,
        amount: String(numericAmount),
        type: transactionType,
        source,
        category: '',
        ocrProvider: ocrResponse.provider || ocrResponse.method || 'device_ocr',
      });

      setProcessingMessage('Gasto AI is assigning a category...');
      processingStage = 'category';
      let response;
      try {
        response = await api.post('/ai/transaction-category', {
          type: transactionType.toLowerCase(),
          source,
          description,
        }, {timeout: 35000});
      } catch (error) {
        setAiFailed(true);
        setScanError(`Gasto AI could not categorize this receipt. ${getApiErrorMessage(error, 'Check the connection and try again.')} Nothing was saved.`);
        return;
      }
      const category = categories.find(item => item.toLowerCase() === String(response.data?.category || '').toLowerCase());
      if (!category) {
        setAiFailed(true);
        setScanError('Gasto AI read the receipt but could not match it to a category configured for this business. Make sure the receipt shows the merchant or items clearly, then retry. Nothing was saved.');
        return;
      }

      setCategorySuggestion(category);
      setProcessingMessage('Saving the transaction...');
      setIsSaving(true);
      processingStage = 'save';
      const result = await addTransaction({
        amount: numericAmount,
        type: transactionType,
        source,
        category,
        date: transactionDate,
        notes: transactionName,
        clientId: scanAttemptIdRef.current,
        entryMethod: 'ocr',
        metadata: {
          ocr_confidence: parsed.confidence ?? null,
          ocr_provider: ocrResponse.provider || ocrResponse.method || 'device_ocr',
        },
      });

      if (!result?.success) {
        const responseData = result?.error?.response?.data;
        const validationMessage = Object.values(responseData?.errors || {}).flat().join('\n');
        setScanError(`Gasto AI assigned a category, but the transaction could not be saved. ${validationMessage || responseData?.message || getApiErrorMessage(result?.error)} Nothing was saved.`);
        return;
      }

      scanAttemptIdRef.current = null;
      setScanSaved(true);
      setSavedManually(false);
      setProcessingMessage('Transaction saved to Transactions.');
    } catch (error) {
      const detail = getApiErrorMessage(error, 'Check the connection and try again.');
      if (processingStage === 'category') {
        setScanError(`Gasto AI could not categorize this receipt. ${detail} Nothing was saved.`);
      } else if (processingStage === 'save') {
        setScanError(`The category was assigned, but the transaction could not be saved. ${detail} Nothing was saved.`);
      } else {
        setScanError('The receipt scan could not be completed. The photo may be blurry, too dark, or cropped. Please retake a clear photo of the full receipt and try again. Nothing was saved.');
      }
      if (processingStage === 'category') setAiFailed(true);
    } finally {
      setScanning(false);
      setIsSaving(false);
      savingRef.current = false;
    }
  };

  const handleManualSave = async () => {
    if (savingRef.current || !parsedData) return;

    const amount = Number(String(manualDraft.amount).replace(/,/g, '').trim());
    if (!Number.isFinite(amount) || amount <= 0) {
      setManualFormError('Enter a valid amount greater than zero.');
      return;
    }

    const datePattern = /^\d{4}-\d{2}-\d{2}$/;
    const [year, month, day] = String(manualDraft.date || '').split('-').map(Number);
    const parsedDate = new Date(`${manualDraft.date}T00:00:00`);
    const today = new Date();
    today.setHours(23, 59, 59, 999);
    const isRealDate = parsedDate.getFullYear() === year
      && parsedDate.getMonth() === month - 1
      && parsedDate.getDate() === day;
    if (!datePattern.test(manualDraft.date || '') || Number.isNaN(parsedDate.getTime()) || !isRealDate || parsedDate > today) {
      setManualFormError('Enter a valid receipt date in YYYY-MM-DD format that is not in the future.');
      return;
    }

    const allowedCategories = manualDraft.type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;
    if (!allowedCategories.includes(manualDraft.category)) {
      setManualFormError('Choose a category before saving.');
      return;
    }
    if (!TRANSACTION_SOURCES.includes(manualDraft.source)) {
      setManualFormError('Choose a valid payment source before saving.');
      return;
    }

    savingRef.current = true;
    setIsSaving(true);
    setManualFormError('');
    if (!scanAttemptIdRef.current) scanAttemptIdRef.current = createIdempotencyKey();
    try {
      const result = await addTransaction({
        amount,
        type: manualDraft.type,
        source: manualDraft.source,
        category: manualDraft.category,
        date: manualDraft.date,
        notes: manualDraft.name.trim() || 'Scanned receipt',
        clientId: scanAttemptIdRef.current,
        entryMethod: 'ocr',
        metadata: {
          ocr_confidence: parsedData.confidence ?? null,
          ocr_provider: manualDraft.ocrProvider || 'device_ocr',
        },
      });

      if (!result?.success) {
        const responseData = result?.error?.response?.data;
        const validationMessage = Object.values(responseData?.errors || {}).flat().join('\n');
        setManualFormError(validationMessage || responseData?.message || getApiErrorMessage(result?.error, 'The transaction could not be saved. Try again.'));
        return;
      }

      scanAttemptIdRef.current = null;
      setParsedData(current => ({
        ...current,
        merchant: manualDraft.name.trim() || 'Scanned receipt',
        date: manualDraft.date,
        dateDetected: true,
        amount,
        type: manualDraft.type,
        source: manualDraft.source,
        sourceDetected: true,
      }));
      setCategorySuggestion(manualDraft.category);
      setAiFailed(false);
      setManualEditing(false);
      setScanError('');
      setScanSaved(true);
      setSavedManually(true);
      setProcessingMessage('Transaction saved to Transactions.');
    } catch (error) {
      setManualFormError(getApiErrorMessage(error, 'The transaction could not be saved. Try again.'));
    } finally {
      setIsSaving(false);
      savingRef.current = false;
    }
  };

  const manualCategories = manualDraft.type === 'Income' ? INCOME_CATEGORIES : EXPENSE_CATEGORIES;

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
                <Text style={styles.stepDescription}>On-device OCR extracts the receipt name, date, and total.</Text>
              </View>
            </View>
            <View style={styles.stepRow}>
              <View style={styles.stepNumber}><Text style={styles.stepNumberText}>3</Text></View>
              <View style={styles.stepCopy}>
                <Text style={styles.stepTitle}>AI categorizes and saves</Text>
                <Text style={styles.stepDescription}>Gasto AI assigns a category, then the transaction is saved automatically.</Text>
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

        {scanning ? (
          <View style={styles.processingStatus} accessibilityLiveRegion="polite">
            <ActivityIndicator size="small" color={COLORS.accentDark} />
            <Text style={styles.processingStatusText}>{processingMessage || 'Processing receipt...'}</Text>
          </View>
        ) : null}

        {parsedData && (
          <View style={styles.resultSection}>
            <Text style={styles.sectionTitle}>Receipt details</Text>
            <View style={styles.dataCard}>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Receipt name:</Text>
                <Text style={styles.dataValue} numberOfLines={2}>{parsedData.merchant || 'Not detected'}</Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Date:</Text>
                <Text style={styles.dataValue}>
                  {parsedData.dateDetected ? parsedData.date : `${parsedData.date} (today default)`}
                </Text>
              </View>
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
                <Text style={styles.dataValue} numberOfLines={2}>
                  {categorySuggestion || (scanning ? 'Assigning…' : 'Not assigned')}
                </Text>
              </View>
              <View style={styles.dataRow}>
                <Text style={styles.dataLabel}>Source:</Text>
                <Text style={styles.dataValue}>
                  {parsedData.sourceDetected ? parsedData.source : `${parsedData.source} (default)`}
                </Text>
              </View>
              {aiFailed && !manualEditing && !scanSaved ? (
                <TouchableOpacity
                  style={styles.manualFallbackButton}
                  onPress={() => {
                    setManualFormError('');
                    setManualEditing(true);
                  }}
                  accessibilityRole="button"
                  accessibilityLabel="Edit receipt and add transaction manually">
                  <Icon name="pencil-plus-outline" size={18} color="#FFFFFF" />
                  <Text style={styles.manualFallbackButtonText}>Edit &amp; add transaction</Text>
                </TouchableOpacity>
              ) : null}

              {aiFailed && manualEditing && !scanSaved ? (
                <View style={styles.manualEditor}>
                  <Text style={styles.manualEditorTitle}>Edit receipt details</Text>
                  <Text style={styles.manualEditorHint}>AI could not assign a category. Review the details, choose a category, then save.</Text>

                  <Text style={styles.manualFieldLabel}>Receipt name</Text>
                  <TextInput
                    style={styles.manualInput}
                    value={manualDraft.name}
                    onChangeText={name => setManualDraft(current => ({...current, name}))}
                    placeholder="Receipt or merchant name"
                    placeholderTextColor={COLORS.textGray}
                    maxLength={255}
                    accessibilityLabel="Receipt name"
                  />

                  <Text style={styles.manualFieldLabel}>Amount</Text>
                  <TextInput
                    style={styles.manualInput}
                    value={manualDraft.amount}
                    onChangeText={amount => setManualDraft(current => ({...current, amount}))}
                    placeholder="0.00"
                    placeholderTextColor={COLORS.textGray}
                    keyboardType="decimal-pad"
                    accessibilityLabel="Transaction amount"
                  />

                  <Text style={styles.manualFieldLabel}>Date (YYYY-MM-DD)</Text>
                  <TextInput
                    style={styles.manualInput}
                    value={manualDraft.date}
                    onChangeText={date => setManualDraft(current => ({...current, date}))}
                    placeholder="YYYY-MM-DD"
                    placeholderTextColor={COLORS.textGray}
                    keyboardType="numbers-and-punctuation"
                    accessibilityLabel="Transaction date"
                  />

                  <Text style={styles.manualFieldLabel}>Transaction type</Text>
                  <View style={styles.manualChoiceRow}>
                    {['Expense', 'Income'].map(type => (
                      <TouchableOpacity
                        key={type}
                        style={[styles.manualChoice, manualDraft.type === type && styles.manualChoiceSelected]}
                        onPress={() => setManualDraft(current => ({...current, type, category: ''}))}
                        accessibilityRole="button"
                        accessibilityLabel={`Manual transaction type ${type}`}>
                        <Text style={[styles.manualChoiceText, manualDraft.type === type && styles.manualChoiceTextSelected]}>{type}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>

                  <Text style={styles.manualFieldLabel}>Category</Text>
                  <View style={styles.manualChoiceWrap}>
                    {manualCategories.map(category => (
                      <TouchableOpacity
                        key={category}
                        style={[styles.manualChoice, manualDraft.category === category && styles.manualChoiceSelected]}
                        onPress={() => setManualDraft(current => ({...current, category}))}
                        accessibilityRole="button"
                        accessibilityLabel={`Manual category ${category}`}>
                        <Text style={[styles.manualChoiceText, manualDraft.category === category && styles.manualChoiceTextSelected]}>{category}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>

                  <Text style={styles.manualFieldLabel}>Payment source</Text>
                  <View style={styles.manualChoiceWrap}>
                    {TRANSACTION_SOURCES.map(source => (
                      <TouchableOpacity
                        key={source}
                        style={[styles.manualChoice, manualDraft.source === source && styles.manualChoiceSelected]}
                        onPress={() => setManualDraft(current => ({...current, source}))}
                        accessibilityRole="button"
                        accessibilityLabel={`Manual source ${source}`}>
                        <Text style={[styles.manualChoiceText, manualDraft.source === source && styles.manualChoiceTextSelected]}>{source}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>

                  {manualFormError ? (
                    <Text style={styles.manualFormError} accessibilityLiveRegion="polite">{manualFormError}</Text>
                  ) : null}

                  <TouchableOpacity
                    style={[styles.manualSaveButton, (!manualDraft.category || isSaving) && styles.manualSaveButtonDisabled]}
                    onPress={handleManualSave}
                    disabled={!manualDraft.category || isSaving || scanning}
                    accessibilityRole="button"
                    accessibilityLabel="Save receipt transaction manually">
                    {isSaving
                      ? <ActivityIndicator color="#FFFFFF" />
                      : <Text style={styles.manualSaveButtonText}>Save transaction</Text>}
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={styles.manualCancelButton}
                    onPress={() => {
                      setManualEditing(false);
                      setManualFormError('');
                    }}
                    accessibilityRole="button"
                    accessibilityLabel="Cancel manual receipt editing">
                    <Text style={styles.manualCancelButtonText}>Cancel</Text>
                  </TouchableOpacity>
                </View>
              ) : null}
            </View>
          </View>
        )}

        {scanSaved ? (
          <View style={styles.scanStatusSuccess} accessibilityLiveRegion="polite">
            <Icon name="check-circle-outline" size={20} color={COLORS.accentDark} />
            <Text style={styles.scanStatusSuccessText}>
              {savedManually ? 'Transaction added to Transactions.' : 'Saved automatically to Transactions.'}
            </Text>
          </View>
        ) : null}

        {scanError ? (
          <View style={styles.scanStatusError} accessibilityLiveRegion="polite">
            <Icon name="alert-circle-outline" size={20} color="#A33A2B" />
            <Text style={styles.scanStatusErrorText}>{scanError}</Text>
            {imageUri ? (
              <TouchableOpacity
                style={styles.retryScanButton}
                disabled={scanning}
                onPress={() => processImage(imageUri, true)}
                accessibilityRole="button"
                accessibilityLabel="Retry automatic receipt scan">
                <Text style={styles.retryScanText}>Retry automatic scan</Text>
              </TouchableOpacity>
            ) : null}
          </View>
        ) : null}

      </ScrollView>

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
  sectionTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: COLORS.textDark,
    marginBottom: 12,
  },
  processingStatus: {flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: 12, padding: 14, marginBottom: 16},
  processingStatusText: {flex: 1, fontSize: 13, lineHeight: 19, color: COLORS.textDark},
  resultSection: {
    marginBottom: 16,
  },
  scanStatusSuccess: {flexDirection: 'row', alignItems: 'center', gap: 9, backgroundColor: '#EAF8F2', borderWidth: 1, borderColor: '#B8E6D5', borderRadius: 12, padding: 14, marginBottom: 16},
  scanStatusSuccessText: {flex: 1, fontSize: 13, lineHeight: 19, fontWeight: '700', color: COLORS.accentDark},
  scanStatusError: {backgroundColor: '#FFF6E8', borderWidth: 1, borderColor: '#F2D39E', borderRadius: 12, padding: 14, marginBottom: 16},
  scanStatusErrorText: {fontSize: 13, lineHeight: 19, color: '#6B4B16', marginTop: 6},
  retryScanButton: {alignSelf: 'flex-start', paddingVertical: 8, marginTop: 4},
  retryScanText: {fontSize: 13, fontWeight: '800', color: COLORS.accentDark},
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
  manualFallbackButton: {
    minHeight: 46,
    marginTop: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    backgroundColor: COLORS.accentDark,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  manualFallbackButtonText: {fontSize: 14, fontWeight: '800', color: '#FFFFFF'},
  manualEditor: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  manualEditorTitle: {fontSize: 15, fontWeight: '800', color: COLORS.textDark},
  manualEditorHint: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginTop: 4, marginBottom: 8},
  manualFieldLabel: {fontSize: 12, fontWeight: '700', color: COLORS.textDark, marginTop: 12, marginBottom: 6},
  manualInput: {
    minHeight: 44,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.background,
    fontSize: 14,
    color: COLORS.textDark,
  },
  manualChoiceRow: {flexDirection: 'row', gap: 8},
  manualChoiceWrap: {flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  manualChoice: {
    minHeight: 36,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  manualChoiceSelected: {backgroundColor: COLORS.accentDark, borderColor: COLORS.accentDark},
  manualChoiceText: {fontSize: 12, fontWeight: '700', color: COLORS.textDark},
  manualChoiceTextSelected: {color: '#FFFFFF'},
  manualFormError: {fontSize: 12, lineHeight: 18, color: '#A33A2B', marginTop: 12},
  manualSaveButton: {
    minHeight: 48,
    marginTop: 16,
    borderRadius: 12,
    backgroundColor: COLORS.accentDark,
    alignItems: 'center',
    justifyContent: 'center',
  },
  manualSaveButtonDisabled: {opacity: 0.55},
  manualSaveButtonText: {fontSize: 14, fontWeight: '800', color: '#FFFFFF'},
  manualCancelButton: {minHeight: 40, marginTop: 6, alignItems: 'center', justifyContent: 'center'},
  manualCancelButtonText: {fontSize: 13, fontWeight: '700', color: COLORS.textGray},
});

export default ReceiptScannerScreen;
