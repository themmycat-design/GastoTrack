import React, {useEffect, useState} from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {AppAlert as Alert} from '../../services/AppDialogService';
import {launchImageLibrary} from 'react-native-image-picker';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {COLORS, SHADOWS} from '../../theme';
import {getProfilePhotoSource, profilePhotoUpload} from '../../utils/profilePhoto';

const PersonalInformationScreen = ({navigation}) => {
  const {user, userRole, business, userToken, updateProfile} = useAuth();
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState(user?.name || '');
  const [phone, setPhone] = useState(user?.phone || '');
  const [photoAsset, setPhotoAsset] = useState(null);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    setName(user?.name || '');
    setPhone(user?.phone || '');
    setPhotoAsset(null);
  }, [user]);

  const initial = name.trim().charAt(0).toUpperCase() || 'S';
  const photoSource = photoAsset?.uri
    ? {uri: photoAsset.uri}
    : getProfilePhotoSource(user, userToken);

  const choosePhoto = async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        selectionLimit: 1,
        quality: 0.85,
        maxWidth: 1200,
        maxHeight: 1200,
        assetRepresentationMode: 'compatible',
      });
      if (result.didCancel) return;
      if (result.errorCode) {
        Alert.alert('Could not open photos', result.errorMessage || 'Check photo access and try again.');
        return;
      }

      const selectedPhoto = result.assets?.[0];
      if (!selectedPhoto?.uri) {
        Alert.alert('Could not use this photo', 'Choose another image and try again.');
        return;
      }
      profilePhotoUpload(selectedPhoto);
      setPhotoAsset(selectedPhoto);
    } catch (error) {
      Alert.alert('Could not add photo', error.code === 'PROFILE_PHOTO_INVALID'
        ? error.message : 'Please choose another photo and try again.');
    }
  };

  const cancelEditing = () => {
    setName(user?.name || '');
    setPhone(user?.phone || '');
    setPhotoAsset(null);
    setIsEditing(false);
  };

  const saveChanges = async () => {
    const trimmedName = name.trim();
    if (!trimmedName) {
      Alert.alert('Name required', 'Enter your name before saving.');
      return;
    }

    let profileData = {name: trimmedName, phone: phone.trim()};
    try {
      if (photoAsset) {
        const form = new FormData();
        form.append('name', trimmedName);
        form.append('phone', phone.trim());
        form.append('profile_photo', profilePhotoUpload(photoAsset));
        profileData = form;
      }
    } catch (error) {
      Alert.alert('Could not add photo', error.message);
      return;
    }

    setIsSaving(true);
    const result = await updateProfile(profileData, Boolean(photoAsset));
    setIsSaving(false);
    if (!result.success) {
      Alert.alert('Could not save changes', result.message || 'Please try again.');
      return;
    }

    setPhotoAsset(null);
    setIsEditing(false);
    Alert.alert('Profile updated');
  };

  const fields = [
    ['Email', user?.email || 'Not available'],
    ['Role', userRole === 'staff' ? 'Staff / Cashier' : userRole || 'Staff'],
    ['Business', business?.name || 'Not available'],
  ];

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Personal information"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
        centered
      />
      <KeyboardAvoidingView style={styles.body} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.card}>
            <View style={styles.profileTop}>
              <View style={styles.avatar}>
                {photoSource
                  ? <Image source={photoSource} style={styles.avatarImage} />
                  : <Text style={styles.avatarText}>{initial}</Text>}
              </View>
              <View style={styles.profileCopy}>
                <Text style={styles.profileName}>{name || 'Staff User'}</Text>
                <Text style={styles.profileHint}>{isEditing ? 'Update your name or profile picture' : 'Your staff account details'}</Text>
              </View>
              {!isEditing ? (
                <TouchableOpacity
                  style={styles.editButton}
                  onPress={() => setIsEditing(true)}
                  accessibilityRole="button"
                  accessibilityLabel="Edit personal information">
                  <Icon name="pencil-outline" size={17} color={COLORS.accentDark} />
                  <Text style={styles.editButtonText}>Edit</Text>
                </TouchableOpacity>
              ) : null}
            </View>

            {isEditing ? (
              <>
                <TouchableOpacity style={styles.photoButton} onPress={choosePhoto} accessibilityRole="button" accessibilityLabel="Choose profile photo">
                  <Icon name="camera-outline" size={19} color={COLORS.accentDark} />
                  <Text style={styles.photoButtonText}>{photoAsset || user?.has_profile_photo ? 'Change profile photo' : 'Add profile photo'}</Text>
                </TouchableOpacity>

                <Text style={styles.label}>Name</Text>
                <TextInput
                  style={styles.input}
                  value={name}
                  onChangeText={setName}
                  placeholder="Enter your name"
                  placeholderTextColor={COLORS.placeholder}
                  autoCapitalize="words"
                  maxLength={255}
                />
                <Text style={styles.label}>Phone number</Text>
                <TextInput
                  style={styles.input}
                  value={phone}
                  onChangeText={setPhone}
                  placeholder="Add a phone number (optional)"
                  placeholderTextColor={COLORS.placeholder}
                  keyboardType="phone-pad"
                  maxLength={30}
                />
              </>
            ) : (
              <>
                <View style={styles.row}>
                  <Text style={styles.label}>Name</Text>
                  <Text style={styles.value}>{user?.name || 'Not available'}</Text>
                </View>
                <View style={styles.row}>
                  <Text style={styles.label}>Phone number</Text>
                  <Text style={styles.value}>{user?.phone || 'Not provided'}</Text>
                </View>
              </>
            )}

            {fields.map(([label, value], index) => (
              <View key={label} style={[styles.row, !isEditing && index === fields.length - 1 && styles.lastRow]}>
                <Text style={styles.label}>{label}</Text>
                <Text style={styles.value}>{value}</Text>
              </View>
            ))}

            {isEditing ? (
              <View style={styles.editActions}>
                <TouchableOpacity style={styles.cancelButton} onPress={cancelEditing} disabled={isSaving}>
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.saveButton, isSaving && styles.disabled]} onPress={saveChanges} disabled={isSaving} accessibilityRole="button" accessibilityLabel="Save personal information changes">
                  {isSaving
                    ? <ActivityIndicator size="small" color={COLORS.textWhite} />
                    : <Text style={styles.saveButtonText}>Save changes</Text>}
                </TouchableOpacity>
              </View>
            ) : null}
          </View>
          {isEditing ? <Text style={styles.emailNote}>Email, role, and business assignment can’t be changed here.</Text> : null}
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  body: {flex: 1},
  content: {padding: 16, paddingBottom: 28},
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 14,
    ...SHADOWS.card,
  },
  profileTop: {flexDirection: 'row', alignItems: 'center', marginBottom: 14},
  avatar: {
    width: 58,
    height: 58,
    borderRadius: 29,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarImage: {width: 58, height: 58, borderRadius: 29},
  avatarText: {fontSize: 24, fontWeight: '700', color: COLORS.accentDark},
  profileCopy: {flex: 1, marginLeft: 11},
  profileName: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
  profileHint: {fontSize: 12, color: COLORS.textGray, marginTop: 3},
  editButton: {flexDirection: 'row', alignItems: 'center', gap: 5, borderRadius: 10, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 10, paddingVertical: 8},
  editButtonText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  photoButton: {minHeight: 44, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderWidth: 1, borderColor: COLORS.border, borderRadius: 12, backgroundColor: COLORS.surfaceMuted, marginBottom: 8},
  photoButtonText: {fontSize: 13, fontWeight: '700', color: COLORS.accentDark},
  row: {paddingVertical: 12, borderTopWidth: 1, borderTopColor: COLORS.border},
  lastRow: {borderBottomWidth: 0},
  label: {fontSize: 12, color: COLORS.textGray, marginBottom: 5},
  value: {fontSize: 15, fontWeight: '600', color: COLORS.textDark},
  input: {minHeight: 46, borderWidth: 1, borderColor: COLORS.border, borderRadius: 11, backgroundColor: COLORS.surfaceMuted, paddingHorizontal: 12, paddingVertical: 10, fontSize: 15, color: COLORS.textDark, marginBottom: 10},
  editActions: {flexDirection: 'row', gap: 9, marginTop: 8},
  cancelButton: {flex: 1, minHeight: 46, borderWidth: 1, borderColor: COLORS.border, borderRadius: 12, alignItems: 'center', justifyContent: 'center'},
  cancelButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.textGray},
  saveButton: {flex: 1.4, minHeight: 48, borderRadius: 12, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  saveButtonText: {fontSize: 14, fontWeight: '700', color: COLORS.textWhite},
  disabled: {opacity: 0.6},
  emailNote: {fontSize: 12, lineHeight: 18, color: COLORS.textGray, marginTop: 10, textAlign: 'center'},
});

export default PersonalInformationScreen;
