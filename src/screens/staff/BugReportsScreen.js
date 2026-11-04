import React, {useRef, useState} from 'react';
import {
  ActivityIndicator,
  Image,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import {launchImageLibrary} from 'react-native-image-picker';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import api from '../../services/api';
import {getApiErrorMessage} from '../../services/apiErrors';
import {COLORS, SHADOWS} from '../../theme';

const AREAS = ['login', 'dashboard', 'transactions', 'orders', 'payments', 'inventory', 'analytics', 'other'];
const MAX_SCREENSHOT_BYTES = 5 * 1024 * 1024;

const areaLabel = value => value.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const BugReportsScreen = ({navigation}) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [title, setTitle] = useState('');
  const [area, setArea] = useState('');
  const [description, setDescription] = useState('');
  const [steps, setSteps] = useState('');
  const [screenshot, setScreenshot] = useState(null);
  const descriptionRef = useRef(null);
  const scrollRef = useRef(null);

  const pickScreenshot = async () => {
    setFormError('');
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        selectionLimit: 1,
        quality: 0.8,
        maxWidth: 1600,
        maxHeight: 1600,
      });
      if (result.didCancel) return;
      if (result.errorCode) {
        setFormError(result.errorMessage || 'Could not open your photos. Check permission and try again.');
        return;
      }

      const image = result.assets?.[0];
      if (!image?.uri) return;
      if (image.fileSize && image.fileSize > MAX_SCREENSHOT_BYTES) {
        setFormError('Choose an image smaller than 5 MB.');
        return;
      }
      if (image.type && !['image/jpeg', 'image/png', 'image/webp'].includes(image.type.toLowerCase())) {
        setFormError('Choose a JPEG, PNG, or WebP image.');
        return;
      }
      setScreenshot(image);
    } catch {
      setFormError('Could not open your photos. Please try again.');
    }
  };

  const submitReport = async () => {
    if (isSubmitting) return;
    if (!title.trim() || !area || !description.trim()) {
      setFormError('Add a title, choose an area, and describe what happened.');
      return;
    }

    setIsSubmitting(true);
    Keyboard.dismiss();
    setFormError('');
    setSuccessMessage('');
    const form = new FormData();
    form.append('subject', title.trim());
    form.append('area', area);
    form.append('description', description.trim());
    if (steps.trim()) form.append('steps_to_reproduce', steps.trim());
    if (screenshot?.uri) {
      form.append('screenshot', {
        uri: screenshot.uri,
        name: screenshot.fileName || `bug-report-${Date.now()}.jpg`,
        type: screenshot.type || 'image/jpeg',
      });
    }

    try {
      await api.post('/bug-reports', form, {headers: {'Content-Type': 'multipart/form-data'}});
      setTitle('');
      setArea('');
      setDescription('');
      setSteps('');
      setScreenshot(null);
      setSuccessMessage('Bug report submitted. You can follow its status in Your reports.');
      scrollRef.current?.scrollTo({y: 0, animated: true});
    } catch (error) {
      setFormError(getApiErrorMessage(error) || 'Could not submit the report. Check your connection and retry. Your form is still here.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <StaffScreenHeader
        title="Bug reports"
        subtitle="Submit an app issue"
        icon="bug-outline"
        leftIcon="arrow-left"
        leftLabel="Go back"
        onLeftPress={() => navigation.goBack()}
      />
      <ScrollView
        ref={scrollRef}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag">
        <View style={styles.notice}>
          <Icon name="wifi" size={19} color={COLORS.accentDark} />
          <Text style={styles.noticeText}>Bug reports need an internet connection. If a submission fails, your form stays here so you can retry.</Text>
        </View>
        {successMessage ? <View style={styles.successBox}>
          <Text accessibilityLiveRegion="polite" style={styles.successMessage}>{successMessage}</Text>
          <TouchableOpacity accessibilityRole="button" accessibilityLabel="View your reports" onPress={() => navigation.navigate('YourReports')} style={styles.reportsLink}><Text style={styles.reportsLinkText}>View your reports</Text></TouchableOpacity>
        </View> : null}

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Report a bug</Text>
          <Text style={styles.label}>Title</Text>
          <TextInput accessibilityLabel="Report title" editable={!isSubmitting} value={title} onChangeText={setTitle} maxLength={255} placeholderTextColor={COLORS.placeholder} placeholder="A short description of the issue" style={styles.input} returnKeyType="next" onSubmitEditing={() => descriptionRef.current?.focus()} />

          <Text style={styles.label}>Area</Text>
          <View style={styles.areaList}>
            {AREAS.map(item => (
              <TouchableOpacity key={item} disabled={isSubmitting} onPress={() => setArea(item)} style={[styles.areaChip, area === item && styles.areaChipSelected]} accessibilityRole="button" accessibilityState={{selected: area === item, disabled: isSubmitting}}>
                <Text style={[styles.areaText, area === item && styles.areaTextSelected]}>{areaLabel(item)}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={styles.label}>What happened?</Text>
          <TextInput ref={descriptionRef} accessibilityLabel="What happened?" editable={!isSubmitting} value={description} onChangeText={setDescription} maxLength={5000} placeholderTextColor={COLORS.placeholder} placeholder="Describe what you expected and what happened instead" style={[styles.input, styles.multiline]} multiline textAlignVertical="top" />

          <Text style={styles.label}>Steps to reproduce (optional)</Text>
          <TextInput accessibilityLabel="Steps to reproduce, optional" editable={!isSubmitting} placeholderTextColor={COLORS.placeholder} value={steps} onChangeText={setSteps} maxLength={5000} placeholder="1. Open…  2. Tap…  3. The issue appears…" style={[styles.input, styles.multiline]} multiline textAlignVertical="top" />

          <TouchableOpacity accessibilityRole="button" style={styles.attachButton} onPress={pickScreenshot} disabled={isSubmitting}>
            <Icon name="image-plus" size={20} color={COLORS.accentDark} />
            <Text style={styles.attachText}>{screenshot ? 'Change screenshot' : 'Add screenshot (optional)'}</Text>
          </TouchableOpacity>
          {screenshot?.uri ? (
            <View style={styles.previewRow}>
              <Image source={{uri: screenshot.uri}} style={styles.preview} />
              <Text style={styles.previewName} numberOfLines={2}>{screenshot.fileName || 'Screenshot selected'}</Text>
              <TouchableOpacity style={styles.removeButton} disabled={isSubmitting} accessibilityRole="button" onPress={() => setScreenshot(null)} accessibilityLabel="Remove screenshot"><Icon name="close-circle" size={23} color={COLORS.danger} /></TouchableOpacity>
            </View>
          ) : <Text style={styles.helperText}>JPEG, PNG, or WebP up to 5 MB.</Text>}

          {formError ? <Text accessibilityRole="alert" style={styles.errorText}>{formError}</Text> : null}
          <TouchableOpacity accessibilityRole="button" accessibilityLabel={isSubmitting ? 'Submitting bug report' : 'Submit bug report'} accessibilityState={{disabled: isSubmitting, busy: isSubmitting}} onPress={submitReport} disabled={isSubmitting} style={[styles.submitButton, isSubmitting && styles.disabledButton]}>
            {isSubmitting ? <View style={styles.submittingRow}><ActivityIndicator color={COLORS.textWhite} /><Text style={styles.submitText}>Submitting…</Text></View> : <Text style={styles.submitText}>Submit bug report</Text>}
          </TouchableOpacity>
        </View>

      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  removeButton: {width: 48, height: 48, alignItems: 'center', justifyContent: 'center'},
  submittingRow: {flexDirection: 'row', alignItems: 'center', gap: 10},
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 16, paddingBottom: 32},
  notice: {flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, padding: 11, marginBottom: 10},
  noticeText: {flex: 1, color: COLORS.textDark, fontSize: 12, lineHeight: 18},
  successBox: {marginBottom: 12, borderRadius: 11, backgroundColor: COLORS.surfaceMuted, padding: 12},
  successMessage: {color: COLORS.success, fontSize: 12, fontWeight: '700'},
  reportsLink: {alignSelf: 'flex-start', minHeight: 44, justifyContent: 'center', marginTop: 4},
  reportsLinkText: {color: COLORS.accentDark, fontWeight: '700', fontSize: 13},
  card: {backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, padding: 13, ...SHADOWS.card},
  sectionTitle: {fontSize: 18, fontWeight: '700', color: COLORS.textDark},
  label: {fontSize: 13, fontWeight: '700', color: COLORS.textDark, marginTop: 12, marginBottom: 5},
  input: {minHeight: 46, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 12, paddingVertical: 10, color: COLORS.textDark, backgroundColor: '#FFFFFF', fontSize: 14},
  multiline: {minHeight: 92, paddingTop: 11},
  areaList: {flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  areaChip: {minHeight: 48, justifyContent: 'center', borderRadius: 999, borderWidth: 1, borderColor: COLORS.border, backgroundColor: '#FFFFFF', paddingHorizontal: 11, paddingVertical: 8},
  areaChipSelected: {borderColor: COLORS.accentDark, backgroundColor: COLORS.surfaceMuted},
  areaText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray},
  areaTextSelected: {color: COLORS.accentDark},
  attachButton: {marginTop: 11, minHeight: 48, flexDirection: 'row', alignItems: 'center', gap: 8, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 12},
  attachText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  helperText: {marginTop: 6, color: COLORS.textGray, fontSize: 11},
  previewRow: {marginTop: 9, flexDirection: 'row', alignItems: 'center', gap: 10},
  preview: {width: 54, height: 54, borderRadius: 9, backgroundColor: COLORS.surfaceMuted},
  previewName: {flex: 1, color: COLORS.textDark, fontSize: 12},
  errorText: {color: COLORS.danger, fontSize: 12, lineHeight: 18, marginTop: 11},
  submitButton: {minHeight: 48, borderRadius: 12, marginTop: 11, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  disabledButton: {opacity: 0.65},
  submitText: {color: COLORS.textWhite, fontWeight: '700', fontSize: 14},
});

export default BugReportsScreen;
