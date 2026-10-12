import React from 'react';
import ReactTestRenderer from 'react-test-renderer';
import {Image, Text, TextInput, TouchableOpacity} from 'react-native';
import {launchImageLibrary} from 'react-native-image-picker';
import ReceiptScannerScreen from '../src/screens/staff/ReceiptScannerScreen';
import {useTransactions} from '../src/context/TransactionContext';
import {buildReceiptCategoryDescription, parseReceiptData, scanReceipt} from '../src/services/OCRService';
import api from '../src/services/api';

jest.mock('react-native-image-picker', () => ({
  launchCamera: jest.fn(),
  launchImageLibrary: jest.fn(),
}));

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/context/TransactionContext', () => ({useTransactions: jest.fn()}));
jest.mock('../src/services/OCRService', () => ({
  scanReceipt: jest.fn(),
  parseReceiptData: jest.fn(),
  buildReceiptCategoryDescription: jest.fn(),
}));
jest.mock('../src/services/api', () => ({__esModule: true, default: {post: jest.fn()}}));
jest.mock('../src/services/apiErrors', () => ({
  getApiErrorMessage: jest.fn(() => 'Server unavailable.'),
}));

const currentDate = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const setupScanner = addTransaction => {
  useTransactions.mockReturnValue({
    addTransaction,
    INCOME_CATEGORIES: ['Sales', 'Tips', 'Other Income'],
    EXPENSE_CATEGORIES: ['Ingredients', 'Rent', 'Other Expense'],
    TRANSACTION_SOURCES: ['Cash', 'GCash', 'Maya'],
  });
  launchImageLibrary.mockResolvedValue({assets: [{uri: 'file:///receipt.jpg'}]});
  scanReceipt.mockResolvedValue({success: true, text: 'receipt text', method: 'mlkit'});
  parseReceiptData.mockReturnValue({
    amount: 85,
    date: currentDate(),
    dateDetected: true,
    merchant: 'Brew Haven',
    source: 'Cash',
    sourceDetected: true,
    type: 'Expense',
    confidence: 95,
  });
  buildReceiptCategoryDescription.mockReturnValue('Brew Haven | Coffee beans');
};

const chooseReceiptPhoto = async screen => {
  const chooseButton = screen.root.findAllByType(TouchableOpacity)
    .find(node => node.props.accessibilityLabel === 'Choose a receipt from photos');
  await ReactTestRenderer.act(async () => chooseButton.props.onPress());
};

describe('ReceiptScannerScreen automatic transaction flow', () => {
  beforeEach(() => jest.clearAllMocks());

  it('classifies and saves a scanned receipt automatically without a manual save form', async () => {
    const addTransaction = jest.fn().mockResolvedValue({success: true, transaction: {id: 12}});
    setupScanner(addTransaction);
    api.post.mockResolvedValue({data: {category: 'Ingredients'}});

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    expect(api.post).toHaveBeenCalledWith('/ai/transaction-category', expect.objectContaining({
      type: 'expense',
      source: 'Cash',
      description: 'Brew Haven | Coffee beans',
    }), {timeout: 35000});
    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({
      amount: 85,
      type: 'Expense',
      source: 'Cash',
      category: 'Ingredients',
      date: currentDate(),
      notes: 'Brew Haven',
      entryMethod: 'ocr',
      clientId: expect.stringMatching(/^[0-9a-f-]{36}$/i),
    }));
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Saved automatically to Transactions.')).toBe(true);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Save transaction')).toBe(false);
    expect(screen.root.findAllByType(Image)).toHaveLength(0);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Extracted text' || node.props.children === 'receipt text')).toBe(false);
  });

  it('explains when the image could not be read and keeps the image and OCR text hidden', async () => {
    const addTransaction = jest.fn();
    setupScanner(addTransaction);
    scanReceipt.mockResolvedValue({success: false, error: 'No text found'});

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    expect(addTransaction).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(Text).some(node => String(node.props.children).includes('The photo may be blurry, too dark, or cropped'))).toBe(true);
    expect(screen.root.findAllByType(Image)).toHaveLength(0);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Extracted text')).toBe(false);
  });

  it('explains that a missing total prevents saving', async () => {
    const addTransaction = jest.fn();
    setupScanner(addTransaction);
    parseReceiptData.mockReturnValue({amount: null});

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    expect(addTransaction).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(Text).some(node => String(node.props.children).includes('its total amount could not be read'))).toBe(true);
  });

  it('offers a manual fallback without saving automatically if AI cannot return a configured category', async () => {
    const addTransaction = jest.fn();
    setupScanner(addTransaction);
    api.post.mockResolvedValue({data: {category: null}});

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    expect(addTransaction).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(Text).some(node => String(node.props.children).includes('could not match it to a category configured for this business'))).toBe(true);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Save transaction')).toBe(false);
    expect(screen.root.findAllByType(TouchableOpacity).some(node => node.props.accessibilityLabel === 'Edit receipt and add transaction manually')).toBe(true);
  });

  it('lets staff edit the receipt and save with a manually selected category after an AI error', async () => {
    const addTransaction = jest.fn().mockResolvedValue({success: true, transaction: {id: 24}});
    setupScanner(addTransaction);
    api.post.mockRejectedValue({
      isAxiosError: true,
      response: {status: 503, data: {message: 'Gemini is temporarily unavailable.'}},
    });

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    const editButton = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === 'Edit receipt and add transaction manually');
    await ReactTestRenderer.act(async () => editButton.props.onPress());

    const amountInput = screen.root.findAllByType(TextInput)
      .find(node => node.props.accessibilityLabel === 'Transaction amount');
    const nameInput = screen.root.findAllByType(TextInput)
      .find(node => node.props.accessibilityLabel === 'Receipt name');
    await ReactTestRenderer.act(async () => {
      amountInput.props.onChangeText('95');
      nameInput.props.onChangeText('Brew Haven supply receipt');
    });

    let saveButton = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === 'Save receipt transaction manually');
    expect(saveButton.props.disabled).toBe(true);
    expect(addTransaction).not.toHaveBeenCalled();

    const categoryButton = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === 'Manual category Ingredients');
    await ReactTestRenderer.act(async () => categoryButton.props.onPress());

    saveButton = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === 'Save receipt transaction manually');
    expect(saveButton.props.disabled).toBe(false);
    await ReactTestRenderer.act(async () => saveButton.props.onPress());

    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({
      amount: 95,
      type: 'Expense',
      source: 'Cash',
      category: 'Ingredients',
      date: currentDate(),
      notes: 'Brew Haven supply receipt',
      entryMethod: 'ocr',
      clientId: expect.stringMatching(/^[0-9a-f-]{36}$/i),
    }));
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Transaction added to Transactions.')).toBe(true);
  });

  it('reuses the same idempotency key when retrying an automatic save', async () => {
    const addTransaction = jest.fn()
      .mockResolvedValueOnce({success: false, error: {response: {data: {message: 'Temporary timeout.'}}}})
      .mockResolvedValueOnce({success: true, transaction: {id: 12}});
    setupScanner(addTransaction);
    api.post.mockResolvedValue({data: {category: 'Ingredients'}});

    let screen;
    await ReactTestRenderer.act(async () => {
      screen = ReactTestRenderer.create(<ReceiptScannerScreen navigation={{goBack: jest.fn()}} />);
    });
    await chooseReceiptPhoto(screen);

    const retryButton = screen.root.findAllByType(TouchableOpacity)
      .find(node => node.props.accessibilityLabel === 'Retry automatic receipt scan');
    await ReactTestRenderer.act(async () => retryButton.props.onPress());

    const firstClientId = addTransaction.mock.calls[0][0].clientId;
    const retryClientId = addTransaction.mock.calls[1][0].clientId;
    expect(retryClientId).toBe(firstClientId);
    expect(addTransaction).toHaveBeenCalledTimes(2);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Saved automatically to Transactions.')).toBe(true);
  });
});
