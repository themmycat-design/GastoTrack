import React, {useRef, useState} from 'react';
import {
  ActivityIndicator,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import api from '../../services/api';
import {COLORS, RADIUS, SHADOWS} from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const welcomeMessage = {
  id: 'welcome',
  role: 'assistant',
  localOnly: true,
  text: 'Hi! I’m Gasto, your staff assistant. Ask me about today’s sales, completed orders, or low-stock items.',
};

const suggestions = [
  'How are sales today?',
  'Suggest alternatives for out-of-stock ingredients.',
  'How many orders were completed today?',
];

const AiAssistantScreen = ({navigation}) => {
  const [messages, setMessages] = useState([welcomeMessage]);
  const [input, setInput] = useState('');
  const [isSending, setIsSending] = useState(false);
  const listRef = useRef(null);

  const sendMessage = async suggestedText => {
    const text = (suggestedText || input).trim();
    if (!text || isSending) return;

    const userMessage = {id: `user-${Date.now()}`, role: 'user', text};
    const nextMessages = [...messages, userMessage];
    setMessages(nextMessages);
    setInput('');
    setIsSending(true);

    try {
      const history = nextMessages
        .filter(message => !message.localOnly)
        .slice(-20)
        .map(({role, text: messageText}) => ({role, text: messageText}));
      const response = await api.post('/ai/chat', {messages: history});
      setMessages(current => [...current, {
        id: `assistant-${Date.now()}`,
        role: 'assistant',
        text: response.data.reply,
      }]);
    } catch (error) {
      setMessages(current => [...current, {
        id: `error-${Date.now()}`,
        role: 'assistant',
        isError: true,
        localOnly: true,
        text: error.response?.data?.message || 'I could not connect right now. Please try again.',
      }]);
    } finally {
      setIsSending(false);
    }
  };

  const clearChat = () => {
    if (!isSending) setMessages([welcomeMessage]);
  };

  const goBack = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
    } else {
      navigation.navigate('Profile');
    }
  };

  const renderMessage = ({item}) => (
    <View style={[
      styles.messageBubble,
      item.role === 'user' ? styles.userBubble : styles.assistantBubble,
      item.isError && styles.errorBubble,
    ]}>
      {item.role === 'assistant' && (
        <View style={styles.assistantLabel}>
          <Icon name="auto-awesome" size={14} color={COLORS.accentDark} />
          <Text style={styles.assistantLabelText}>Gasto AI</Text>
        </View>
      )}
      <Text style={item.role === 'user' ? styles.userText : styles.assistantText}>
        {item.text}
      </Text>
    </View>
  );

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <StaffScreenHeader
        title="AI Assistant"
        subtitle="Live, read-only business guidance"
        icon="robot-outline"
        leftIcon="arrow-left"
        leftLabel="Go back"
        onLeftPress={goBack}
        actionIcon="delete-outline"
        actionLabel="Clear chat"
        onActionPress={clearChat}
        centered
      />

      <FlatList
        ref={listRef}
        data={messages}
        renderItem={renderMessage}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.messageList}
        onContentSizeChange={() => listRef.current?.scrollToEnd({animated: true})}
        ListFooterComponent={isSending ? (
          <View style={[styles.messageBubble, styles.assistantBubble, styles.typingBubble]}>
            <ActivityIndicator size="small" color={COLORS.accent} />
            <Text style={styles.typingText}>Gasto is thinking…</Text>
          </View>
        ) : null}
      />

      {messages.length === 1 && (
        <View style={styles.suggestions}>
          {suggestions.map(suggestion => (
            <TouchableOpacity key={suggestion} style={styles.suggestionChip} onPress={() => sendMessage(suggestion)}>
              <Text style={styles.suggestionText}>{suggestion}</Text>
            </TouchableOpacity>
          ))}
        </View>
      )}

      <View style={styles.composer}>
        <TextInput
          style={styles.input}
          value={input}
          onChangeText={setInput}
          placeholder="Ask Gasto something…"
          placeholderTextColor={COLORS.textMuted}
          multiline
          maxLength={2000}
          editable={!isSending}
        />
        <TouchableOpacity
          style={[styles.sendButton, (!input.trim() || isSending) && styles.sendButtonDisabled]}
          onPress={() => sendMessage()}
          disabled={!input.trim() || isSending}
          accessibilityLabel="Send message">
          <Icon name="send" size={20} color="#FFFFFF" />
        </TouchableOpacity>
      </View>
      <Text style={styles.disclaimer}>AI can make mistakes. Verify important business information.</Text>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  messageList: {paddingHorizontal: 20, paddingTop: 20, paddingBottom: 12},
  messageBubble: {maxWidth: '86%', padding: 14, borderRadius: RADIUS.lg, marginBottom: 12},
  assistantBubble: {alignSelf: 'flex-start', backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderTopLeftRadius: 5, ...SHADOWS.card},
  userBubble: {alignSelf: 'flex-end', backgroundColor: COLORS.accent, borderTopRightRadius: 5},
  errorBubble: {borderColor: COLORS.danger, backgroundColor: '#FFF2F2'},
  assistantLabel: {flexDirection: 'row', alignItems: 'center', gap: 5, marginBottom: 7},
  assistantLabelText: {fontSize: 11, fontWeight: '700', color: COLORS.accentDark},
  assistantText: {fontSize: 14, lineHeight: 21, color: COLORS.textDark},
  userText: {fontSize: 14, lineHeight: 21, color: '#FFFFFF'},
  typingBubble: {flexDirection: 'row', alignItems: 'center', gap: 9},
  typingText: {fontSize: 13, color: COLORS.textGray},
  suggestions: {paddingHorizontal: 20, paddingBottom: 8, gap: 8},
  suggestionChip: {alignSelf: 'flex-start', borderWidth: 1, borderColor: COLORS.accent, backgroundColor: COLORS.surface, paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.pill},
  suggestionText: {fontSize: 13, fontWeight: '600', color: COLORS.accentDark},
  composer: {flexDirection: 'row', alignItems: 'flex-end', gap: 10, backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border, paddingHorizontal: 14, paddingTop: 12, paddingBottom: 8},
  input: {flex: 1, minHeight: 44, maxHeight: 110, borderWidth: 1, borderColor: COLORS.border, borderRadius: 22, paddingHorizontal: 16, paddingVertical: 11, color: COLORS.textDark, backgroundColor: COLORS.background},
  sendButton: {width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.accent, alignItems: 'center', justifyContent: 'center'},
  sendButtonDisabled: {opacity: 0.45},
  disclaimer: {backgroundColor: COLORS.surface, color: COLORS.textGray, fontSize: 10, textAlign: 'center', paddingBottom: 7},
});

export default AiAssistantScreen;
