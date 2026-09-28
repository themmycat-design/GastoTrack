import React, { useState, useContext } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator } from 'react-native';
import { AuthContext } from '../context/AuthContext';
import axios from 'axios';

const LoginScreen = () => {
  const [email, setEmail] = useState('staff1@gastotrack.com');
  const [password, setPassword] = useState('password');
  const [loading, setLoading] = useState(false);
  const [testResult, setTestResult] = useState('');
  const { login, userToken } = useContext(AuthContext);

  // Direct API test (bypass context)
  const testDirectAPI = async () => {
    setLoading(true);
    setTestResult('Testing...');
    
    try {
      const response = await axios.post('http://192.168.0.11:8000/api/login', {
        email,
        password
      }, {
        headers: { 'Content-Type': 'application/json' },
        timeout: 10000
      });
      
      setTestResult(`✓ API works! Token: ${response.data.token.substring(0,20)}...`);
      Alert.alert('API Test Success', 'Direct API call works!');
    } catch (error) {
      const errorMsg = error.message || 'Unknown error';
      setTestResult(`✗ API failed: ${errorMsg}`);
      Alert.alert('API Test Failed', errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleLogin = async () => {
    if (!email || !password) {
      Alert.alert('Error', 'Please fill in all fields.');
      return;
    }

    setLoading(true);
    setTestResult('Logging in via context...');
    
    const result = await login(email, password);
    
    setLoading(false);
    setTestResult(result.success ? '✓ Login success' : `✗ ${result.message}`);

    if (!result.success) {
      Alert.alert('Login Failed', result.message);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>GastoTrack</Text>
      
      {userToken && (
        <Text style={styles.tokenText}>Logged in! Token: {userToken.substring(0,30)}...</Text>
      )}
      
      <View style={styles.card}>
        <Text style={styles.label}>Email</Text>
        <TextInput 
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />

        <Text style={styles.label}>Password</Text>
        <TextInput 
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
        />

        {testResult ? (
          <Text style={styles.testResult}>{testResult}</Text>
        ) : null}

        <TouchableOpacity 
          style={[styles.button, styles.buttonPrimary]} 
          onPress={handleLogin} 
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#FFFFFF" />
          ) : (
            <Text style={styles.buttonText}>Log In</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity 
          style={[styles.button, styles.buttonSecondary]} 
          onPress={testDirectAPI} 
          disabled={loading}
        >
          <Text style={[styles.buttonText, {color: '#00C897'}]}>Test API Direct</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#00C897', justifyContent: 'center', padding: 20 },
  title: { fontSize: 32, fontWeight: 'bold', color: '#FFF', textAlign: 'center', marginBottom: 40 },
  tokenText: { fontSize: 12, color: '#FFF', textAlign: 'center', marginBottom: 10 },
  card: { backgroundColor: '#FFF', borderRadius: 16, padding: 20, elevation: 5 },
  label: { fontSize: 14, color: '#888', marginBottom: 5 },
  input: { backgroundColor: '#F5F5F5', borderRadius: 8, padding: 12, marginBottom: 15, color: '#0A2E2A' },
  button: { padding: 15, borderRadius: 8, alignItems: 'center', marginTop: 10 },
  buttonPrimary: { backgroundColor: '#00C897' },
  buttonSecondary: { backgroundColor: '#FFF', borderWidth: 2, borderColor: '#00C897' },
  buttonText: { color: '#FFF', fontWeight: 'bold', fontSize: 16 },
  testResult: { fontSize: 12, color: '#666', marginBottom: 10, padding: 10, backgroundColor: '#F5F5F5', borderRadius: 5 }
});

export default LoginScreen;