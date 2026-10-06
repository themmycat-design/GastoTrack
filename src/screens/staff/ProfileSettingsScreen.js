import React from 'react';
import {ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {COLORS, SHADOWS} from '../../theme';

const ProfileSettingsScreen = ({navigation}) => (
  <View style={styles.container}>
    <StaffScreenHeader
      title="Settings"
      subtitle=""
      leftIcon="arrow-left"
      leftLabel="Back to profile"
      onLeftPress={() => navigation.goBack()}
      centered
    />
    <ScrollView contentContainerStyle={styles.content}>
      <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('CategorySettings')} activeOpacity={0.75}>
        <View style={styles.copy}>
          <Text style={styles.title}>Categories</Text>
        </View>
        <Icon name="chevron-right" size={26} color={COLORS.textMuted} />
      </TouchableOpacity>

      <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('SourceSettings')} activeOpacity={0.75}>
        <View style={styles.copy}>
          <Text style={styles.title}>Sources</Text>
        </View>
        <Icon name="chevron-right" size={26} color={COLORS.textMuted} />
      </TouchableOpacity>
    </ScrollView>
  </View>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 20, paddingBottom: 36},
  card: {minHeight: 68, flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface, borderRadius: 18, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 18, marginBottom: 14, ...SHADOWS.card},
  copy: {flex: 1},
  title: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
});

export default ProfileSettingsScreen;
