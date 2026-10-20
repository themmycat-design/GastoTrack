import React, {useRef, useState} from 'react';
import {
  ActivityIndicator,
  Alert,
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
import {getAiErrorMessage} from '../../services/aiErrors';
import {COLORS, RADIUS, SHADOWS} from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const welcomeMessage = {
  id: 'welcome',
  role: 'assistant',
  localOnly: true,
  text: "Hi! I'm Gasto. Ask about your shop's sales, recorded income and expenses, orders, stock, or how GastoTrack works.",
};

const suggestions = [
  'How are sales today?',
  'What were our income and expenses this week?',
  'Which ingredients are low or out of stock?',
];

const AiAssistantScreen = ({navigation}) => {
  const [messages, setMessages] = useState([welcomeMessage]);
  const [input, setInput] = useState('');
  const [isSending, setIsSending] = useState(false);
  const sendingRef = useRef(false);
  const listRef = useRef(null);
  const nearBottomRef = useRef(true);

  const sendMessage = async suggestedText => {
    const text = (suggestedText || input).trim();
    if (!text || sendingRef.current) return;
    sendingRef.current = true;
    nearBottomRef.current = true;

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
      setInput(text);
      setMessages(current => [...current.map(message => message.id === userMessage.id
        ? {...message, localOnly: true}
        : message), {
        id: `error-${Date.now()}`,
        role: 'assistant',
        isError: true,
        localOnly: true,
        text: getAiErrorMessage(error),
      }]);
    } finally {
      sendingRef.current = false;
      setIsSending(false);
    }
  };

  const clearChat = () => {
    if (isSending || messages.length === 1) return;
    Alert.alert('Clear conversation?', 'This removes the messages from this chat.', [
      {text: 'Cancel', style: 'cancel'},
      {text: 'Clear chat', style: 'destructive', onPress: () => {
        setMessages([welcomeMessage]);
        setInput('');
        nearBottomRef.current = true;
        listRef.current?.scrollToOffset({offset: 0, animated: false});
      }},
    ]);
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
      <Text selectable style={item.role === 'user' ? styles.userText : styles.assistantText}>
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
        onActionPress={isSending || messages.length === 1 ? undefined : clearChat}
        centered
      />

      <FlatList
        ref={listRef}
        data={messages}
        renderItem={renderMessage}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.messageList}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
        onScroll={({nativeEvent: {contentOffset, contentSize, layoutMeasurement}}) => {
          nearBottomRef.current = contentSize.height - contentOffset.y - layoutMeasurement.height < 80;
        }}
        scrollEventThrottle={100}
        onContentSizeChange={() => {
          if (messages.length > 1 && nearBottomRef.current) listRef.current?.scrollToEnd({animated: true});
        }}
        ListFooterComponent={<>
        {isSending ? (
          <View style={[styles.messageBubble, styles.assistantBubble, styles.typingBubble]}>
            <ActivityIndicator size="small" color={COLORS.accent} />
            <Text style={styles.typingText}>Gasto is checking your business data...</Text>
          </View>
        ) : null}
        {messages.length === 1 && (
        <View style={styles.suggestions}>
          {suggestions.map(suggestion => (
            <TouchableOpacity accessibilityRole="button" key={suggestion} style={styles.suggestionChip} onPress={() => sendMessage(suggestion)}>
              <Text style={styles.suggestionText}>{suggestion}</Text>
            </TouchableOpacity>
          ))}
        </View>
        )}
        </>}
      />

      <View style={styles.composer}>
        <TextInput
          style={styles.input}
          accessibilityLabel="Message to Gasto AI"
          value={input}
          onChangeText={setInput}
          placeholder="Ask about your shop or GastoTrack..."
          placeholderTextColor={COLORS.placeholder}
          multiline
          maxLength={2000}
          editable={!isSending}
        />
        <TouchableOpacity
          style={[styles.sendButton, (!input.trim() || isSending) && styles.sendButtonDisabled]}
          onPress={() => sendMessage()}
          disabled={!input.trim() || isSending}
          accessibilityRole="button"
          accessibilityState={{disabled: !input.trim() || isSending, busy: isSending}}
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
  messageList: {paddingHorizontal: 16, paddingTop: 12, paddingBottom: 10},
  messageBubble: {maxWidth: '88%', padding: 12, borderRadius: RADIUS.lg, marginBottom: 9},
  assistantBubble: {alignSelf: 'flex-start', backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderTopLeftRadius: 5, ...SHADOWS.card},
  userBubble: {alignSelf: 'flex-end', backgroundColor: COLORS.accentDark, borderTopRightRadius: 5},
  errorBubble: {borderColor: COLORS.danger, backgroundColor: '#FFF2F2'},
  assistantLabel: {flexDirection: 'row', alignItems: 'center', gap: 5, marginBottom: 5},
  assistantLabelText: {fontSize: 11, fontWeight: '700', color: COLORS.accentDark},
  assistantText: {fontSize: 14, lineHeight: 21, color: COLORS.textDark},
  userText: {fontSize: 14, lineHeight: 21, color: '#FFFFFF'},
  typingBubble: {flexDirection: 'row', alignItems: 'center', gap: 9},
  typingText: {fontSize: 13, color: COLORS.textGray},
  suggestions: {paddingBottom: 6, gap: 6},
  suggestionChip: {minHeight: 48, justifyContent: 'center', alignSelf: 'flex-start', borderWidth: 1, borderColor: COLORS.accent, backgroundColor: COLORS.surface, paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.pill},
  suggestionText: {fontSize: 13, fontWeight: '600', color: COLORS.accentDark},
  composer: {flexDirection: 'row', alignItems: 'flex-end', gap: 8, backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border, paddingHorizontal: 12, paddingTop: 8, paddingBottom: 6},
  input: {flex: 1, minHeight: 44, maxHeight: 110, borderWidth: 1, borderColor: COLORS.border, borderRadius: 22, paddingHorizontal: 16, paddingVertical: 11, color: COLORS.textDark, backgroundColor: COLORS.background},
  sendButton: {width: 48, height: 48, borderRadius: 24, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  sendButtonDisabled: {opacity: 0.45},
  disclaimer: {backgroundColor: COLORS.surface, color: COLORS.textGray, fontSize: 10, textAlign: 'center', paddingBottom: 7},
});

export default AiAssistantScreen;
