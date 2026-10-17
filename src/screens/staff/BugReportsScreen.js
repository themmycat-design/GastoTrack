import React, {useCallback, useRef, useState} from 'react';
import {
  ActivityIndicator,
  Image,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import {launchImageLibrary} from 'react-native-image-picker';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import api, {API_URL} from '../../services/api';
import {getApiErrorMessage} from '../../services/apiErrors';
import {COLORS, SHADOWS} from '../../theme';

const AREAS = ['login', 'dashboard', 'transactions', 'orders', 'payments', 'inventory', 'analytics', 'other'];
const MAX_SCREENSHOT_BYTES = 5 * 1024 * 1024;

const areaLabel = value => value.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
const statusLabel = value => String(value || 'open').replace(/_/g, ' ');

const BugReportsScreen = ({navigation}) => {
  const {userToken} = useAuth();
  const [reports, setReports] = useState([]);
  const [page, setPage] = useState(1);
  const pageRef = useRef(1);
  const [lastPage, setLastPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [title, setTitle] = useState('');
  const [area, setArea] = useState('');
  const [description, setDescription] = useState('');
  const [steps, setSteps] = useState('');
  const [screenshot, setScreenshot] = useState(null);

  const loadReports = useCallback(async ({refresh = false, more = false} = {}) => {
    if (!userToken) return;
    if (refresh) setIsRefreshing(true);
    else if (more) setIsLoadingMore(true);
    else setIsLoading(true);
    setLoadError('');
    const requestedPage = refresh ? 1 : more ? pageRef.current + 1 : 1;

    try {
      const response = await api.get('/bug-reports', {params: {page: requestedPage, per_page: 20}});
      const nextReports = response.data?.bug_reports || [];
      setReports(current => refresh || !more ? nextReports : [...current, ...nextReports]);
      const loadedPage = response.data?.meta?.current_page || requestedPage;
      pageRef.current = loadedPage;
      setPage(loadedPage);
      setLastPage(response.data?.meta?.last_page || 1);
    } catch (error) {
      setLoadError(getApiErrorMessage(error) || 'Could not load bug reports. Check your connection and try again.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
      setIsLoadingMore(false);
    }
  }, [userToken]);

  useFocusEffect(useCallback(() => {
    loadReports();
  }, [loadReports]));

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
      setSuccessMessage('Bug report submitted. You can follow its status below.');
      await loadReports({refresh: true});
    } catch (error) {
      setFormError(getApiErrorMessage(error) || 'Could not submit the report. Check your connection and retry. Your form is still here.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const screenshotSource = report => ({
    uri: `${API_URL}/bug-reports/${report.id}/screenshot`,
    headers: {Authorization: `Bearer ${userToken}`},
  });

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Bugs reports"
        subtitle="Submit and follow up on app issues"
        icon="bug-outline"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
      />
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={() => loadReports({refresh: true})} colors={[COLORS.accent]} />}>
        <View style={styles.notice}>
          <Icon name="wifi" size={19} color={COLORS.accentDark} />
          <Text style={styles.noticeText}>Bug reports need an internet connection. If a submission fails, your form stays here so you can retry.</Text>
        </View>
        {successMessage ? <Text accessibilityRole="status" style={styles.successMessage}>{successMessage}</Text> : null}

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Report a bug</Text>
          <Text style={styles.label}>Title</Text>
          <TextInput value={title} onChangeText={setTitle} maxLength={255} placeholder="A short description of the issue" style={styles.input} returnKeyType="next" />

          <Text style={styles.label}>Area</Text>
          <View style={styles.areaList}>
            {AREAS.map(item => (
              <TouchableOpacity key={item} onPress={() => setArea(item)} style={[styles.areaChip, area === item && styles.areaChipSelected]} accessibilityRole="button" accessibilityState={{selected: area === item}}>
                <Text style={[styles.areaText, area === item && styles.areaTextSelected]}>{areaLabel(item)}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={styles.label}>What happened?</Text>
          <TextInput value={description} onChangeText={setDescription} maxLength={5000} placeholder="Describe what you expected and what happened instead" style={[styles.input, styles.multiline]} multiline textAlignVertical="top" />

          <Text style={styles.label}>Steps to reproduce (optional)</Text>
          <TextInput value={steps} onChangeText={setSteps} maxLength={5000} placeholder="1. Open…  2. Tap…  3. The issue appears…" style={[styles.input, styles.multiline]} multiline textAlignVertical="top" />

          <TouchableOpacity style={styles.attachButton} onPress={pickScreenshot} disabled={isSubmitting}>
            <Icon name="image-plus" size={20} color={COLORS.accentDark} />
            <Text style={styles.attachText}>{screenshot ? 'Change screenshot' : 'Add screenshot (optional)'}</Text>
          </TouchableOpacity>
          {screenshot?.uri ? (
            <View style={styles.previewRow}>
              <Image source={{uri: screenshot.uri}} style={styles.preview} />
              <Text style={styles.previewName} numberOfLines={2}>{screenshot.fileName || 'Screenshot selected'}</Text>
              <TouchableOpacity onPress={() => setScreenshot(null)} accessibilityLabel="Remove screenshot"><Icon name="close-circle" size={23} color={COLORS.danger} /></TouchableOpacity>
            </View>
          ) : <Text style={styles.helperText}>JPEG, PNG, or WebP up to 5 MB.</Text>}

          {formError ? <Text accessibilityRole="alert" style={styles.errorText}>{formError}</Text> : null}
          <TouchableOpacity onPress={submitReport} disabled={isSubmitting} style={[styles.submitButton, isSubmitting && styles.disabledButton]}>
            {isSubmitting ? <ActivityIndicator color={COLORS.textWhite} /> : <Text style={styles.submitText}>Submit bug report</Text>}
          </TouchableOpacity>
        </View>

        <View style={styles.listHeader}>
          <Text style={styles.sectionTitle}>Your reports</Text>
          {reports.length > 0 ? <Text style={styles.reportCount}>{reports.length} shown</Text> : null}
        </View>

        {isLoading ? <ActivityIndicator style={styles.loader} size="large" color={COLORS.accentDark} /> : null}
        {!isLoading && loadError ? (
          <View style={styles.errorCard}>
            <Text style={styles.errorText}>{loadError}</Text>
            <TouchableOpacity onPress={() => loadReports()} style={styles.retryButton}><Text style={styles.retryText}>Retry</Text></TouchableOpacity>
          </View>
        ) : null}
        {!isLoading && !loadError && reports.length === 0 ? <Text style={styles.emptyText}>You have not submitted any bug reports yet.</Text> : null}

        {reports.map(report => (
          <View key={report.id} style={styles.reportCard}>
            <View style={styles.reportTopRow}>
              <View style={styles.reportTitleWrap}>
                <Text style={styles.reportTitle}>{report.title}</Text>
                <Text style={styles.reportMeta}>{areaLabel(report.area || 'other')}  ·  {areaLabel(report.priority || 'medium')} priority  ·  {report.created_at ? new Date(report.created_at).toLocaleDateString() : ''}</Text>
              </View>
              <View style={[styles.statusBadge, report.status === 'resolved' && styles.resolvedBadge]}><Text style={styles.statusText}>{statusLabel(report.status)}</Text></View>
            </View>
            <Text style={styles.reportDescription}>{report.description}</Text>
            {report.steps_to_reproduce ? <Text style={styles.reportSteps}><Text style={styles.reportBold}>Steps: </Text>{report.steps_to_reproduce}</Text> : null}
            {report.public_update ? <View style={styles.publicUpdate}><Text style={styles.publicUpdateLabel}>Update from GastoTrack</Text><Text style={styles.publicUpdateText}>{report.public_update}</Text></View> : null}
            {report.has_screenshot ? <Image source={screenshotSource(report)} style={styles.reportScreenshot} resizeMode="cover" /> : null}
          </View>
        ))}

        {page < lastPage ? (
          <TouchableOpacity onPress={() => loadReports({more: true})} disabled={isLoadingMore} style={styles.loadMoreButton}>
            {isLoadingMore ? <ActivityIndicator color={COLORS.accentDark} /> : <Text style={styles.loadMoreText}>Load older reports</Text>}
          </TouchableOpacity>
        ) : null}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 16, paddingBottom: 32},
  notice: {flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, padding: 13, marginBottom: 14},
  noticeText: {flex: 1, color: COLORS.textDark, fontSize: 12, lineHeight: 18},
  successMessage: {marginBottom: 12, borderRadius: 11, backgroundColor: '#E6F6EF', color: COLORS.success, padding: 12, fontSize: 12, fontWeight: '700'},
  card: {backgroundColor: COLORS.surface, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, padding: 16, ...SHADOWS.card},
  sectionTitle: {fontSize: 18, fontWeight: '700', color: COLORS.textDark},
  label: {fontSize: 13, fontWeight: '700', color: COLORS.textDark, marginTop: 16, marginBottom: 7},
  input: {minHeight: 46, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 12, paddingVertical: 10, color: COLORS.textDark, backgroundColor: '#FFFFFF', fontSize: 14},
  multiline: {minHeight: 92, paddingTop: 11},
  areaList: {flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  areaChip: {borderRadius: 999, borderWidth: 1, borderColor: COLORS.border, backgroundColor: '#FFFFFF', paddingHorizontal: 11, paddingVertical: 8},
  areaChipSelected: {borderColor: COLORS.accentDark, backgroundColor: COLORS.surfaceMuted},
  areaText: {fontSize: 12, fontWeight: '600', color: COLORS.textGray},
  areaTextSelected: {color: COLORS.accentDark},
  attachButton: {marginTop: 15, minHeight: 46, flexDirection: 'row', alignItems: 'center', gap: 9, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, paddingHorizontal: 12},
  attachText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  helperText: {marginTop: 6, color: COLORS.textGray, fontSize: 11},
  previewRow: {marginTop: 9, flexDirection: 'row', alignItems: 'center', gap: 10},
  preview: {width: 54, height: 54, borderRadius: 9, backgroundColor: COLORS.surfaceMuted},
  previewName: {flex: 1, color: COLORS.textDark, fontSize: 12},
  errorText: {color: COLORS.danger, fontSize: 12, lineHeight: 18, marginTop: 11},
  submitButton: {minHeight: 48, borderRadius: 12, marginTop: 15, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  disabledButton: {opacity: 0.65},
  submitText: {color: COLORS.textWhite, fontWeight: '700', fontSize: 14},
  listHeader: {marginTop: 24, marginBottom: 10, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'},
  reportCount: {fontSize: 12, color: COLORS.textGray},
  loader: {paddingVertical: 22},
  errorCard: {borderRadius: 12, borderWidth: 1, borderColor: '#F2C7A0', backgroundColor: '#FFF8EE', padding: 14},
  retryButton: {alignSelf: 'flex-start', marginTop: 7, paddingVertical: 4},
  retryText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  emptyText: {paddingVertical: 22, textAlign: 'center', color: COLORS.textGray, fontSize: 13},
  reportCard: {marginTop: 10, borderWidth: 1, borderColor: COLORS.border, borderRadius: 15, backgroundColor: COLORS.surface, padding: 15, ...SHADOWS.card},
  reportTopRow: {flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10},
  reportTitleWrap: {flex: 1},
  reportTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark},
  reportMeta: {marginTop: 4, fontSize: 11, color: COLORS.textGray, textTransform: 'capitalize'},
  statusBadge: {borderRadius: 999, paddingHorizontal: 9, paddingVertical: 5, backgroundColor: '#FFF2D9'},
  resolvedBadge: {backgroundColor: '#E6F6EF'},
  statusText: {fontSize: 10, fontWeight: '700', color: COLORS.textDark, textTransform: 'capitalize'},
  reportDescription: {marginTop: 12, color: COLORS.textDark, fontSize: 13, lineHeight: 19},
  reportSteps: {marginTop: 9, color: COLORS.textGray, fontSize: 12, lineHeight: 18},
  reportBold: {fontWeight: '700', color: COLORS.textDark},
  publicUpdate: {marginTop: 12, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, padding: 11},
  publicUpdateLabel: {fontSize: 11, fontWeight: '700', color: COLORS.accentDark},
  publicUpdateText: {marginTop: 4, color: COLORS.textDark, fontSize: 12, lineHeight: 18},
  reportScreenshot: {width: '100%', height: 180, borderRadius: 10, marginTop: 12, backgroundColor: COLORS.surfaceMuted},
  loadMoreButton: {minHeight: 45, marginTop: 14, borderRadius: 11, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, alignItems: 'center', justifyContent: 'center'},
  loadMoreText: {color: COLORS.accentDark, fontWeight: '700', fontSize: 13},
});

export default BugReportsScreen;
