import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {Modal, Text, TextInput, TouchableOpacity} from 'react-native';
import TransactionsScreen from '../src/screens/staff/TransactionsScreen';
import {useTransactions} from '../src/context/TransactionContext';
import api from '../src/services/api';

jest.mock('@react-navigation/native', () => ({useIsFocused: () => false}));
jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/context/AuthContext', () => ({useAuth: () => ({user: {id: 7}})}));
jest.mock('../src/context/TransactionContext', () => ({useTransactions: jest.fn()}));
jest.mock('../src/utils/useStaffDay', () => ({useStaffDay: () => '2026-11-03'}));
jest.mock('../src/components/staff/WalletCaptureIssues', () => () => null);
jest.mock('../src/components/SkeletonPlaceholder', () => ({__esModule: true, default: () => null, SkeletonBlock: () => null}));
jest.mock('../src/components/staff/StaffScreenHeader', () => ({onActionPress}) => {
  const Button = require('react-native').TouchableOpacity;
  return <Button accessibilityLabel="Open transaction form" onPress={onActionPress} />;
});
jest.mock('../src/services/api', () => ({__esModule: true, default: {post: jest.fn()}}));
jest.mock('../src/services/apiErrors', () => ({getApiErrorMessage: () => 'AI is unavailable.'}));
jest.mock('../src/services/AppDialogService', () => ({AppAlert: {alert: jest.fn()}}));

const findForm = screen => screen.root.findAllByType(Modal).find(node => node.props.visible);
const findButton = (form, label) => form.findAllByType(TouchableOpacity).find(node =>
  node.findAllByType(Text).some(text => text.props.children === label));
const findInput = (form, placeholder) => form.findAllByType(TextInput).find(node => node.props.placeholder === placeholder);

const openExpenseForm = async (screen, {selectSource = true} = {}) => {
  await act(async () => screen.root.findByProps({accessibilityLabel: 'Open transaction form'}).props.onPress());
  const form = findForm(screen);
  await act(async () => findButton(form, 'Expense').props.onPress());
  expect(findButton(form, 'Ingredients')).toBeDefined();
  if (selectSource) await act(async () => findButton(form, 'Cash').props.onPress());
  await act(async () => findInput(form, '0.00').props.onChangeText('85'));
  return form;
};

describe('staff expense category selection', () => {
  let screen;
  let addTransaction;

  beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
    addTransaction = jest.fn().mockResolvedValue({success: true});
    useTransactions.mockReturnValue({
      queryTransactions: jest.fn().mockResolvedValue({success: true, transactions: [], pagination: {currentPage: 1, lastPage: 1}}),
      fetchTransactionCategories: jest.fn().mockResolvedValue({success: true, categories: []}),
      addTransaction,
      updateTransaction: jest.fn(),
      deleteTransaction: jest.fn(),
      INCOME_CATEGORIES: ['Sales'],
      EXPENSE_CATEGORIES: ['Ingredients', 'Rent'],
      TRANSACTION_SOURCES: ['Cash', 'GCash'],
    });
  });

  afterEach(() => {
    if (screen) act(() => screen.unmount());
    screen = null;
    jest.useRealTimers();
  });

  it('hides categories for a named expense and saves the configured AI category', async () => {
    api.post.mockResolvedValue({data: {category: 'Ingredients'}});
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    const form = await openExpenseForm(screen);
    await act(async () => findInput(form, 'Enter a name for AI, or leave blank to choose a category').props.onChangeText('Coffee beans'));

    expect(findButton(form, 'Ingredients')).toBeUndefined();
    await act(async () => findButton(form, 'Save transaction').props.onPress());
    expect(api.post).toHaveBeenCalledWith('/ai/transaction-category', {type: 'expense', source: 'Cash', description: 'Coffee beans'});
    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({category: 'Ingredients', notes: 'Coffee beans', amount: 85}));
  });

  it('shows a device-completed cash order in activity with a sync label', async () => {
    useTransactions().queryTransactions.mockResolvedValue({
      success: true,
      transactions: [{id: 'local:order-1', client_id: 'order-1', type: 'Income',
        category: 'Sales', source: 'Cash', amount: 170, date: '2026-11-03',
        notes: '2× Latte', entryMethod: 'order_system', offlinePending: true, localCompleted: true, offlineAction: 'Order'}],
      pagination: {currentPage: 1, lastPage: 1},
    });
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    expect(screen.root.findAllByType(Text).some(node => node.props.children === '2× Latte')).toBe(true);
    expect(screen.root.findAllByType(Text).some(node => String(node.props.children).includes('Completed on device · syncing when online'))).toBe(true);
    expect(screen.root.findAllByType(Text).some(node => JSON.stringify(node.props.children).includes('170.00'))).toBe(true);
  });

  it('shows categories for a blank expense name and saves the chosen category without AI', async () => {
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    const form = await openExpenseForm(screen, {selectSource: false});
    await act(async () => findButton(form, 'Rent').props.onPress());
    await act(async () => findButton(form, 'Cash').props.onPress());
    await act(async () => findButton(form, 'Save transaction').props.onPress());

    expect(api.post).not.toHaveBeenCalled();
    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({category: 'Rent', notes: 'Rent', amount: 85}));
  });

  it('shows manual choices after an AI error and does not save until a category is chosen', async () => {
    api.post.mockRejectedValue(new Error('offline'));
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    const form = await openExpenseForm(screen);
    await act(async () => findInput(form, 'Enter a name for AI, or leave blank to choose a category').props.onChangeText('Coffee beans'));
    await act(async () => findButton(form, 'Save transaction').props.onPress());

    expect(addTransaction).not.toHaveBeenCalled();
    expect(findButton(form, 'Ingredients')).toBeDefined();
    await act(async () => findButton(form, 'Ingredients').props.onPress());
    await act(async () => findButton(form, 'Save transaction').props.onPress());
    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({category: 'Ingredients', notes: 'Coffee beans'}));
  });

  it('does not record a category that is not configured for the business', async () => {
    api.post.mockResolvedValue({data: {category: 'Unknown category'}});
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    const form = await openExpenseForm(screen);
    await act(async () => findInput(form, 'Enter a name for AI, or leave blank to choose a category').props.onChangeText('Coffee beans'));
    await act(async () => findButton(form, 'Save transaction').props.onPress());

    expect(addTransaction).not.toHaveBeenCalled();
    expect(findButton(form, 'Rent')).toBeDefined();
  });

  it('can categorize a short product name without changing the saved name', async () => {
    api.post.mockResolvedValue({data: {category: 'Ingredients'}});
    await act(async () => { screen = TestRenderer.create(<TransactionsScreen route={{params: {}}} />); });
    const form = await openExpenseForm(screen);
    await act(async () => findInput(form, 'Enter a name for AI, or leave blank to choose a category').props.onChangeText('Tea'));
    await act(async () => findButton(form, 'Save transaction').props.onPress());

    expect(api.post).toHaveBeenCalledWith('/ai/transaction-category', {type: 'expense', source: 'Cash', description: 'Expense item: Tea'});
    expect(addTransaction).toHaveBeenCalledWith(expect.objectContaining({category: 'Ingredients', notes: 'Tea'}));
  });
});
