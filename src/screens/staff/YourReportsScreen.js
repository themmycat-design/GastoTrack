import React, {useCallback, useRef, useState} from 'react';
import {ActivityIndicator, Image, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import SkeletonPlaceholder, {SkeletonBlock} from '../../components/SkeletonPlaceholder';
import {useAuth} from '../../context/AuthContext';
import api, {API_URL} from '../../services/api';
import {getApiErrorMessage} from '../../services/apiErrors';
import {COLORS, SHADOWS} from '../../theme';

const label = value => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());

const YourReportsScreen = ({navigation}) => {
  const {userToken} = useAuth();
  const [reports, setReports] = useState([]);
  const [page, setPage] = useState(1);
  const pageRef = useRef(1);
  const [lastPage, setLastPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState('');

  const loadReports = useCallback(async ({refresh = false, more = false} = {}) => {
    if (!userToken) return;
    if (refresh) setIsRefreshing(true);
    else if (more) setIsLoadingMore(true);
    else setIsLoading(true);
    setLoadError('');
    const requestedPage = more ? pageRef.current + 1 : 1;

    try {
      const response = await api.get('/bug-reports', {params: {page: requestedPage, per_page: 20}});
      const nextReports = response.data?.bug_reports || [];
      setReports(current => more ? [...current, ...nextReports] : nextReports);
      const loadedPage = response.data?.meta?.current_page || requestedPage;
      pageRef.current = loadedPage;
      setPage(loadedPage);
      setLastPage(response.data?.meta?.last_page || 1);
    } catch (error) {
      setLoadError(getApiErrorMessage(error) || 'Could not load your reports. Check your connection and try again.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
      setIsLoadingMore(false);
    }
  }, [userToken]);

  useFocusEffect(useCallback(() => {
    loadReports();
  }, [loadReports]));

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Your reports"
        subtitle="Follow the issues you reported"
        icon="bug-outline"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
      />
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={() => loadReports({refresh: true})} colors={[COLORS.accent]} />}>
        <TouchableOpacity accessibilityRole="button" accessibilityLabel="Report a problem" onPress={() => navigation.navigate('BugReports')} style={styles.newReportButton}>
          <Text style={styles.newReportText}>Report a problem</Text>
        </TouchableOpacity>

        {isLoading && reports.length === 0 ? <SkeletonPlaceholder accessibilityLabel="Loading your reports" style={styles.skeleton}>
          {[0, 1].map(item => <View key={item} style={styles.skeletonCard}><SkeletonBlock width="72%" height={16} /><SkeletonBlock width="100%" height={12} style={styles.skeletonLine} /></View>)}
        </SkeletonPlaceholder> : null}
        {!isLoading && loadError ? <View style={styles.errorCard}>
          <Text accessibilityRole="alert" style={styles.errorText}>{loadError}</Text>
          <TouchableOpacity accessibilityRole="button" accessibilityLabel="Retry loading reports" onPress={() => loadReports()} style={styles.retryButton}><Text style={styles.retryText}>Retry</Text></TouchableOpacity>
        </View> : null}
        {!isLoading && !loadError && reports.length === 0 ? <View style={styles.emptyState}>
          <Image source={require('../../../assets/mascot/gasto-empty.png')} style={styles.emptyMascot} resizeMode="contain" accessibilityLabel="Gasto beside an empty tray" />
          <Text style={styles.emptyText}>You have not submitted any reports yet.</Text>
        </View> : null}

        {reports.map(report => (
          <View key={report.id} style={styles.reportCard}>
            <View style={styles.reportHeader}>
              <View style={styles.titleWrap}>
                <Text style={styles.reportTitle}>{report.title}</Text>
                <Text style={styles.reportMeta}>{label(report.area || 'other')} · {label(report.priority || 'medium')} priority · {report.created_at ? new Date(report.created_at).toLocaleDateString() : ''}</Text>
              </View>
              <View style={[styles.statusBadge, report.status === 'resolved' && styles.resolvedBadge]}><Text style={styles.statusText}>{label(report.status || 'open')}</Text></View>
            </View>
            <Text style={styles.reportDescription}>{report.description}</Text>
            {report.steps_to_reproduce ? <Text style={styles.reportSteps}><Text style={styles.bold}>Steps: </Text>{report.steps_to_reproduce}</Text> : null}
            {report.public_update ? <View style={styles.publicUpdate}><Text style={styles.publicUpdateLabel}>Update from GastoTrack</Text><Text style={styles.publicUpdateText}>{report.public_update}</Text></View> : null}
            {report.has_screenshot && userToken ? <Image accessibilityLabel="Attached report screenshot" source={{uri: `${API_URL}/bug-reports/${report.id}/screenshot`, headers: {Authorization: `Bearer ${userToken}`}}} style={styles.screenshot} resizeMode="contain" /> : null}
          </View>
        ))}

        {page < lastPage ? <TouchableOpacity accessibilityRole="button" accessibilityLabel="Load older reports" accessibilityState={{disabled: isLoadingMore}} onPress={() => loadReports({more: true})} disabled={isLoadingMore} style={styles.loadMoreButton}>
          {isLoadingMore ? <ActivityIndicator color={COLORS.accentDark} /> : <Text style={styles.loadMoreText}>Load older reports</Text>}
        </TouchableOpacity> : null}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 16, paddingBottom: 32},
  newReportButton: {minHeight: 48, borderRadius: 12, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center', marginBottom: 14},
  newReportText: {color: COLORS.textWhite, fontWeight: '700', fontSize: 14},
  skeleton: {marginBottom: 12},
  skeletonCard: {padding: 14, marginBottom: 10, borderRadius: 15, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border},
  skeletonLine: {marginTop: 14},
  errorCard: {borderRadius: 12, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, padding: 14},
  errorText: {color: COLORS.danger, fontSize: 12, lineHeight: 18},
  retryButton: {alignSelf: 'flex-start', minHeight: 48, justifyContent: 'center', paddingHorizontal: 12, marginTop: 7},
  retryText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  emptyState: {alignItems: 'center', justifyContent: 'center', paddingVertical: 24},
  emptyMascot: {width: 120, height: 120},
  emptyText: {marginTop: 10, textAlign: 'center', color: COLORS.textGray, fontSize: 13},
  reportCard: {marginBottom: 10, borderWidth: 1, borderColor: COLORS.border, borderRadius: 15, backgroundColor: COLORS.surface, padding: 12, ...SHADOWS.card},
  reportHeader: {flexDirection: 'row', alignItems: 'flex-start', gap: 10},
  titleWrap: {flex: 1},
  reportTitle: {fontSize: 15, fontWeight: '700', color: COLORS.textDark},
  reportMeta: {marginTop: 4, fontSize: 11, color: COLORS.textGray},
  statusBadge: {borderRadius: 999, paddingHorizontal: 9, paddingVertical: 5, backgroundColor: COLORS.surfaceMuted},
  resolvedBadge: {backgroundColor: COLORS.tagIncBg},
  statusText: {fontSize: 10, fontWeight: '700', color: COLORS.textDark},
  reportDescription: {marginTop: 9, color: COLORS.textDark, fontSize: 13, lineHeight: 19},
  reportSteps: {marginTop: 7, color: COLORS.textGray, fontSize: 12, lineHeight: 18},
  bold: {fontWeight: '700', color: COLORS.textDark},
  publicUpdate: {marginTop: 12, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, padding: 11},
  publicUpdateLabel: {fontSize: 11, fontWeight: '700', color: COLORS.accentDark},
  publicUpdateText: {marginTop: 4, color: COLORS.textDark, fontSize: 12, lineHeight: 18},
  screenshot: {width: '100%', height: 180, borderRadius: 10, marginTop: 12, backgroundColor: COLORS.surfaceMuted},
  loadMoreButton: {minHeight: 48, marginTop: 14, borderRadius: 11, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, alignItems: 'center', justifyContent: 'center'},
  loadMoreText: {color: COLORS.accentDark, fontWeight: '700', fontSize: 13},
});

export default YourReportsScreen;
