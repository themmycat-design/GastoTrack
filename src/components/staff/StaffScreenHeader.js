import React from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {COLORS} from '../../theme';

const StaffScreenHeader = ({
  title,
  subtitle,
  icon,
  leftIcon,
  leftLabel,
  onLeftPress,
  actionIcon,
  actionLabel,
  onActionPress,
  centered = false,
}) => (
  <SafeAreaView style={styles.safeArea} edges={['top']}>
    <View style={[styles.header, centered && styles.centeredHeader]}>
      {leftIcon ? (
        <TouchableOpacity
          style={[styles.action, styles.leftAction, centered && styles.centeredLeftAction]}
          onPress={onLeftPress}
          accessibilityRole="button"
          accessibilityLabel={leftLabel}>
          <Icon name={leftIcon} size={23} color={COLORS.textDark} />
        </TouchableOpacity>
      ) : null}
      <View style={[styles.identity, centered && styles.centeredIdentity]}>
        {!centered && (
          <View style={styles.iconContainer}>
            <Icon name={icon} size={27} color={COLORS.textWhite} />
          </View>
        )}
        <View style={[styles.copy, centered && styles.centeredCopy]}>
          <Text style={styles.subtitle} numberOfLines={1}>{subtitle}</Text>
          <Text style={[styles.title, centered && styles.centeredTitle]} numberOfLines={1}>{title}</Text>
        </View>
      </View>

      {actionIcon ? (
        <TouchableOpacity
          style={[styles.action, centered && styles.centeredAction]}
          onPress={onActionPress}
          disabled={!onActionPress}
          accessibilityRole={onActionPress ? 'button' : undefined}
          accessibilityLabel={actionLabel}>
          <Icon name={actionIcon} size={23} color={COLORS.textDark} />
        </TouchableOpacity>
      ) : null}
    </View>
  </SafeAreaView>
);

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: COLORS.surface,
  },
  header: {
    minHeight: 74,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 10,
    paddingBottom: 14,
    backgroundColor: COLORS.surface,
  },
  identity: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  centeredHeader: {
    justifyContent: 'center',
  },
  centeredIdentity: {
    flex: 0,
    justifyContent: 'center',
  },
  iconContainer: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  copy: {
    flex: 1,
  },
  centeredCopy: {
    flex: 0,
    alignItems: 'center',
    paddingHorizontal: 52,
  },
  subtitle: {
    fontSize: 12,
    color: COLORS.textGray,
    marginBottom: 2,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.textDark,
  },
  centeredTitle: {
    fontSize: 20,
    textAlign: 'center',
  },
  action: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: COLORS.miniCardBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 12,
  },
  centeredAction: {
    position: 'absolute',
    right: 20,
  },
  leftAction: {
    marginLeft: 0,
    marginRight: 12,
  },
  centeredLeftAction: {
    position: 'absolute',
    left: 20,
    marginRight: 0,
    zIndex: 1,
  },
});

export default StaffScreenHeader;
