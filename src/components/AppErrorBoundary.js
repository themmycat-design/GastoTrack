import React from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {COLORS} from '../theme';

class AppErrorBoundary extends React.Component {
  state = {error: null, resetKey: 0};

  static getDerivedStateFromError(error) {
    return {error};
  }

  componentDidCatch(error, errorInfo) {
    console.error('[AppErrorBoundary] Screen rendering failed:', error, errorInfo);
  }

  retry = () => {
    this.setState(state => ({error: null, resetKey: state.resetKey + 1}));
  };

  render() {
    if (this.state.error) {
      return (
        <View style={styles.container}>
          <Text style={styles.title}>This screen ran into a problem</Text>
          <Text style={styles.message}>
            Your saved information is safe. Reload the screen to try again.
          </Text>
          <TouchableOpacity style={styles.button} onPress={this.retry}>
            <Text style={styles.buttonText}>Reload screen</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return <React.Fragment key={this.state.resetKey}>{this.props.children}</React.Fragment>;
  }
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 28,
    backgroundColor: COLORS.background,
  },
  title: {fontSize: 19, fontWeight: '700', textAlign: 'center', color: COLORS.textDark},
  message: {fontSize: 14, lineHeight: 21, textAlign: 'center', color: COLORS.textGray, marginTop: 8},
  button: {backgroundColor: COLORS.accent, borderRadius: 12, paddingHorizontal: 20, paddingVertical: 13, marginTop: 20},
  buttonText: {fontSize: 14, fontWeight: '700', color: COLORS.textWhite},
});

export default AppErrorBoundary;
