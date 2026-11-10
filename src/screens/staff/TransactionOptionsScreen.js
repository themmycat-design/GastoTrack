import React, {useCallback, useMemo, useState} from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {AppAlert as Alert} from '../../services/AppDialogService';
import {useFocusEffect} from '@react-navigation/native';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useTransactions} from '../../context/TransactionContext';
import {useProducts} from '../../context/ProductContext';
import {getApiErrorMessage} from '../../services/apiErrors';
import SkeletonPlaceholder, {SkeletonBlock} from '../../components/SkeletonPlaceholder';
import {COLORS, RADIUS, SHADOWS} from '../../theme';

const TransactionOptionsScreen = ({navigation, route}) => {
  const kind = route?.params?.kind === 'source' ? 'source' : 'category';
  const isCategory = kind === 'category';
  const {
    INCOME_CATEGORIES,
    EXPENSE_CATEGORIES,
    PRODUCT_CATEGORIES,
    TRANSACTION_SOURCES,
    transactionOptions,
    optionsLoading,
    fetchTransactionOptions,
    addTransactionOption,
    updateTransactionOption,
    deleteTransactionOption,
  } = useTransactions();
  const {fetchProducts} = useProducts();
  const [name, setName] = useState('');
  const [transactionType, setTransactionType] = useState('expense');
  const [saving, setSaving] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [editingOption, setEditingOption] = useState(null);

  useFocusEffect(useCallback(() => {
    fetchTransactionOptions();
  }, [fetchTransactionOptions]));

  const refresh = async () => {
    setRefreshing(true);
    try { await fetchTransactionOptions(); }
    finally { setRefreshing(false); }
  };

  const groups = useMemo(() => isCategory ? [
    {key: 'income', title: 'Income categories', values: INCOME_CATEGORIES},
    {key: 'expense', title: 'Expense categories', values: EXPENSE_CATEGORIES},
    {key: 'product', title: 'Product categories', values: PRODUCT_CATEGORIES},
  ] : [
    {key: 'all', title: 'Payment sources', values: TRANSACTION_SOURCES},
  ], [EXPENSE_CATEGORIES, INCOME_CATEGORIES, PRODUCT_CATEGORIES, TRANSACTION_SOURCES, isCategory]);

  const findOption = (value, type) => transactionOptions.find(option =>
    option.kind === kind
    && option.transaction_type === type
    && option.name.toLowerCase() === value.toLowerCase(),
  );

  const errorMessage = error => {
    return getApiErrorMessage(error);
  };

  const saveOption = async () => {
    const cleanName = name.trim();
    if (!cleanName) {
      Alert.alert(`${isCategory ? 'Category' : 'Source'} required`, `Enter a ${kind} name.`);
      return;
    }
    const currentValues = isCategory
      ? transactionType === 'income' ? INCOME_CATEGORIES : transactionType === 'product' ? PRODUCT_CATEGORIES : EXPENSE_CATEGORIES
      : TRANSACTION_SOURCES;
    if (currentValues.some(value => value.toLowerCase() === cleanName.toLowerCase() && value.toLowerCase() !== editingOption?.name?.toLowerCase())) {
      Alert.alert('Already exists', `This ${kind} is already in the list.`);
      return;
    }

    setSaving(true);
    const payload = {transaction_type: isCategory ? transactionType : 'all', name: cleanName};
    const result = editingOption
      ? await updateTransactionOption(editingOption.id, payload)
      : await addTransactionOption({kind, ...payload});
    setSaving(false);
    if (!result.success) {
      Alert.alert(`Could not ${editingOption ? 'update' : 'add'} ${kind}`, errorMessage(result.error));
      return;
    }
    setName('');
    setEditingOption(null);
    if (editingOption?.transaction_type === 'product') {
      const refreshed = await fetchProducts();
      if (!refreshed?.success) Alert.alert('Category saved', 'Refresh the Products list to see the updated category.');
    }
  };

  const beginEdit = option => {
    setEditingOption(option);
    setName(option.name);
    setTransactionType(option.transaction_type === 'all' ? 'expense' : option.transaction_type);
  };

  const cancelEdit = () => {
    setEditingOption(null);
    setName('');
  };

  const removeOption = option => {
    Alert.alert(
      `Remove ${kind}?`,
      option.transaction_type === 'product'
        ? `"${option.name}" can only be removed when no products use it.`
        : `"${option.name}" will no longer appear as a choice. Existing transactions will not be changed.`,
      [
        {text: 'Cancel', style: 'cancel'},
        {text: 'Remove', style: 'destructive', onPress: async () => {
          setDeletingId(option.id);
          const result = await deleteTransactionOption(option.id);
          setDeletingId(null);
          if (result.success && editingOption?.id === option.id) cancelEdit();
          if (!result.success) Alert.alert(`Could not remove ${kind}`, errorMessage(result.error));
        }},
      ],
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title={isCategory ? 'Categories' : 'Sources'}
        subtitle=""
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
        centered
      />
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} colors={[COLORS.accent]} />}>
        <View style={styles.addCard}>
          <View style={styles.cardHeading}>
            <View style={styles.headingIcon}><Icon name={isCategory ? 'shape-outline' : 'wallet-outline'} size={22} color={COLORS.accentDark} /></View>
            <View style={styles.headingCopy}>
              <Text style={styles.cardTitle}>Add {isCategory ? 'category' : 'source'}</Text>
            </View>
          </View>

          {isCategory ? <View style={styles.typeSelector}>
            {['income', 'expense', 'product'].map(type => (
              <TouchableOpacity key={type} style={[styles.typeButton, transactionType === type && styles.typeButtonActive]} onPress={() => setTransactionType(type)} disabled={!!editingOption && (editingOption.transaction_type === 'product') !== (type === 'product')}>
                <Text style={[styles.typeText, transactionType === type && styles.typeTextActive]}>{type === 'income' ? 'Income' : type === 'product' ? 'Product' : 'Expense'}</Text>
              </TouchableOpacity>
            ))}
          </View> : null}

          <View style={styles.inputRow}>
            <TextInput
              style={styles.input}
              value={name}
              onChangeText={setName}
              placeholder={isCategory ? transactionType === 'product' ? 'Example: Bakery' : 'Example: Delivery fees' : 'Example: GrabPay'}
              placeholderTextColor={COLORS.placeholder}
              maxLength={100}
              onSubmitEditing={saveOption}
              returnKeyType="done"
            />
            {editingOption ? <TouchableOpacity style={styles.cancelButton} onPress={cancelEdit} disabled={saving} accessibilityLabel="Cancel editing">
              <Icon name="close" size={22} color={COLORS.textGray} />
            </TouchableOpacity> : null}
            <TouchableOpacity style={[styles.addButton, saving && styles.disabled]} onPress={saveOption} disabled={saving}>
              {saving ? <ActivityIndicator color="#FFFFFF" size="small" /> : <Icon name={editingOption ? 'check' : 'plus'} size={24} color="#FFFFFF" />}
            </TouchableOpacity>
          </View>
        </View>

        {optionsLoading && !groups.some(group => group.values.length) ? <OptionsSkeleton groupCount={groups.length} /> : groups.map(group => (
          <View key={group.key} style={styles.listCard}>
            <Text style={styles.sectionTitle}>{group.title}</Text>
            {group.values.map((value, index) => {
              const option = findOption(value, group.key);
              return <View key={`${group.key}-${value}`} style={[styles.optionRow, index === group.values.length - 1 && styles.lastRow]}>
                <View style={styles.optionIcon}><Icon name={isCategory ? 'tag-outline' : 'credit-card-outline'} size={19} color={COLORS.accentDark} /></View>
                <Text style={styles.optionName}>{value}</Text>
                {option ? <>
                  <TouchableOpacity style={styles.editButton} onPress={() => beginEdit(option)} disabled={deletingId === option.id} accessibilityLabel={`Edit ${value}`}>
                    <Icon name="pencil-outline" size={20} color={COLORS.accentDark} />
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.deleteButton} onPress={() => removeOption(option)} disabled={deletingId === option.id} accessibilityLabel={`Remove ${value}`}>
                    {deletingId === option.id ? <ActivityIndicator size="small" color={COLORS.danger} /> : <Icon name="trash-can-outline" size={20} color={COLORS.danger} />}
                  </TouchableOpacity>
                </> : null}
              </View>;
            })}
          </View>
        ))}
      </ScrollView>
    </View>
  );
};

const OptionsSkeleton = ({groupCount}) => (
  <SkeletonPlaceholder style={styles.skeletonOptions} accessibilityLabel="Loading categories and payment sources">
    {Array.from({length: groupCount}, (_, group) => (
      <View key={group} style={styles.skeletonOptionsCard}>
        <SkeletonBlock width={group === 0 ? 136 : 112} height={12} style={styles.skeletonOptionsGroupTitle} />
        {[0, 1, 2].map(row => (
          <View key={row} style={styles.skeletonOptionRow}>
            <SkeletonBlock width={34} height={34} radius={10} />
            <SkeletonBlock width={row === 1 ? '48%' : '65%'} height={13} style={styles.skeletonOptionLabel} />
            <SkeletonBlock width={22} height={22} radius={7} />
          </View>
        ))}
      </View>
    ))}
  </SkeletonPlaceholder>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {flexGrow: 1, padding: 16, paddingBottom: 28},
  addCard: {backgroundColor: COLORS.surface, borderRadius: 18, borderWidth: 1, borderColor: COLORS.border, padding: 16, ...SHADOWS.card},
  cardHeading: {flexDirection: 'row', alignItems: 'center'}, headingIcon: {width: 44, height: 44, borderRadius: 13, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center'}, headingCopy: {flex: 1, marginLeft: 11}, cardTitle: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
  typeSelector: {flexDirection: 'row', backgroundColor: COLORS.surfaceMuted, borderRadius: RADIUS.pill, padding: 4, marginTop: 12}, typeButton: {flex: 1, minHeight: 48, alignItems: 'center', justifyContent: 'center', borderRadius: RADIUS.pill}, typeButtonActive: {backgroundColor: COLORS.accentDark}, typeText: {fontSize: 13, fontWeight: '700', color: COLORS.textGray}, typeTextActive: {color: '#FFFFFF'},
  inputRow: {flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 12}, input: {flex: 1, minHeight: 48, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 13, fontSize: 14, color: COLORS.textDark}, addButton: {width: 48, height: 48, borderRadius: 14, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'}, cancelButton: {width: 48, height: 48, borderRadius: 14, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, alignItems: 'center', justifyContent: 'center'}, disabled: {opacity: 0.6},
  skeletonOptions: {marginTop: 12}, skeletonOptionsCard: {backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 13, paddingTop: 13, paddingBottom: 5, marginTop: 10}, skeletonOptionRow: {minHeight: 50, flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: COLORS.border}, skeletonOptionsGroupTitle: {marginBottom: 12}, skeletonOptionLabel: {flex: 1, marginLeft: 10},
  listCard: {backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 13, marginTop: 12}, sectionTitle: {fontSize: 12, fontWeight: '700', color: COLORS.textGray, textTransform: 'uppercase', letterSpacing: 0.7, paddingTop: 12, paddingBottom: 6}, optionRow: {minHeight: 50, flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: COLORS.border}, lastRow: {borderBottomWidth: 0}, optionIcon: {width: 34, height: 34, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center'}, optionName: {flex: 1, fontSize: 14, fontWeight: '600', color: COLORS.textDark, marginLeft: 9}, editButton: {width: 48, height: 48, alignItems: 'center', justifyContent: 'center'}, deleteButton: {width: 48, height: 48, alignItems: 'center', justifyContent: 'center'},
});

export default TransactionOptionsScreen;
