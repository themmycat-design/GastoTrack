import React, {useRef, useState} from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import {AppAlert as Alert} from '../../services/AppDialogService';
import api from '../../services/api';
import {getAiErrorMessage} from '../../services/aiErrors';
import {COLORS, RADIUS, SHADOWS} from '../../theme';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const welcomeMessage = {
  id: 'welcome',
  role: 'assistant',
  localOnly: true,
  text: "Ask about your shop's sales, recorded income and expenses, orders, stock, or how GastoTrack works.",
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
  const showWelcome = messages.length === 1 && messages[0].id === 'welcome';

  const sendMessage = async suggestedText => {
    const text = (suggestedText || input).trim();
    if (!text || sendingRef.current) return;
    sendingRef.current = true;
    nearBottomRef.current = true;

    const userMessage = {id: `user-${Date.now()}`, role: 'user', text};
    const nextMessages = [...messages.filter(message => message.id !== 'welcome'), userMessage];
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
    if (isSending || showWelcome) return;
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

  const renderMessage = ({item}) => {
    if (item.id === 'welcome') {
      return (
        <View style={styles.welcomeCard}>
          <Image source={require('../../../assets/mascot/gasto-chat-welcome.png')} style={styles.welcomeMascot} resizeMode="contain" accessibilityLabel="Gasto waving hello" />
          <View style={styles.welcomeCopy}>
            <Text style={styles.welcomeTitle}>Hi, I'm Gasto!</Text>
            <Text style={styles.welcomeText}>{item.text}</Text>
          </View>
        </View>
      );
    }

    if (item.role === 'user') {
      return <View style={[styles.messageBubble, styles.userBubble]}><Text selectable style={styles.userText}>{item.text}</Text></View>;
    }

    return (
      <View style={styles.assistantRow}>
        <Image source={item.isError ? require('../../../assets/mascot/gasto-error.png') : require('../../../assets/mascot/gasto-chat-avatar.png')} style={styles.assistantMascot} resizeMode="contain" accessibilityLabel="Gasto the AI assistant mascot" />
        <View style={[styles.messageBubble, styles.assistantBubble, item.isError && styles.errorBubble]}>
          <Text style={[styles.assistantLabelText, item.isError && styles.errorLabel]}>{item.isError ? 'Could not reply' : 'Gasto AI'}</Text>
          <Text selectable style={styles.assistantText}>{item.text}</Text>
        </View>
      </View>
    );
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <StaffScreenHeader
        title="AI Assistant"
        subtitle="Live, read-only business guidance"
        leftIcon="arrow-left"
        leftLabel="Go back"
        onLeftPress={goBack}
        actionIcon="delete-outline"
        actionLabel="Clear chat"
        onActionPress={isSending || showWelcome ? undefined : clearChat}
        centered
      />

      <FlatList
        ref={listRef}
        style={styles.chatList}
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
          if (!showWelcome && nearBottomRef.current) listRef.current?.scrollToEnd({animated: true});
        }}
        ListFooterComponent={<>
          {isSending ? (
            <View style={styles.typingRow} accessibilityLiveRegion="polite">
              <Image source={require('../../../assets/mascot/gasto-thinking.png')} style={styles.thinkingMascot} resizeMode="contain" accessibilityLabel="Gasto is thinking" />
              <View style={[styles.messageBubble, styles.assistantBubble, styles.typingBubble]}>
                <ActivityIndicator size="small" color={COLORS.accentDark} />
                <Text style={styles.typingText}>Checking your business data...</Text>
              </View>
            </View>
          ) : null}
          {showWelcome ? (
            <View style={styles.suggestions}>
              <Text style={styles.suggestionsTitle}>TRY ASKING</Text>
              {suggestions.map(suggestion => (
                <TouchableOpacity accessibilityRole="button" accessibilityLabel={suggestion} key={suggestion} style={styles.suggestionChip} onPress={() => sendMessage(suggestion)} activeOpacity={0.8}>
                  <Text style={styles.suggestionText}>{suggestion}</Text>
                  <Icon name="arrow-forward" size={17} color={COLORS.accentDark} />
                </TouchableOpacity>
              ))}
            </View>
          ) : null}
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
          <Icon name="send" size={20} color={COLORS.textWhite} />
        </TouchableOpacity>
      </View>
      <Text style={styles.disclaimer}>AI can make mistakes. Check important details against your records.</Text>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  chatList: {flex: 1},
  messageList: {paddingHorizontal: 16, paddingTop: 16, paddingBottom: 16, flexGrow: 1},
  welcomeCard: {flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.surfaceMuted, borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.lg, padding: 12, marginBottom: 18},
  welcomeMascot: {width: 76, height: 76},
  welcomeCopy: {flex: 1},
  welcomeTitle: {fontSize: 16, fontWeight: '800', color: COLORS.textDark, marginBottom: 4},
  welcomeText: {fontSize: 13, lineHeight: 19, color: COLORS.textGray},
  assistantRow: {flexDirection: 'row', alignItems: 'flex-end', gap: 8, marginBottom: 12},
  assistantMascot: {width: 34, height: 34},
  messageBubble: {paddingHorizontal: 14, paddingVertical: 11, borderRadius: RADIUS.lg},
  assistantBubble: {flexShrink: 1, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderBottomLeftRadius: 5, ...SHADOWS.card},
  userBubble: {maxWidth: '84%', alignSelf: 'flex-end', backgroundColor: COLORS.accentDark, borderBottomRightRadius: 5, marginBottom: 12},
  errorBubble: {borderColor: COLORS.danger, backgroundColor: COLORS.dangerSurface},
  assistantLabelText: {fontSize: 11, fontWeight: '800', color: COLORS.accentDark, marginBottom: 5},
  errorLabel: {color: COLORS.danger},
  assistantText: {fontSize: 14, lineHeight: 21, color: COLORS.textDark},
  userText: {fontSize: 14, lineHeight: 21, color: COLORS.textWhite},
  typingRow: {flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12},
  thinkingMascot: {width: 34, height: 34},
  typingBubble: {flexDirection: 'row', alignItems: 'center', gap: 9, minHeight: 44},
  typingText: {flexShrink: 1, fontSize: 13, color: COLORS.textGray},
  suggestions: {gap: 8, paddingBottom: 6},
  suggestionsTitle: {fontSize: 11, fontWeight: '800', letterSpacing: 1, color: COLORS.accentDark, marginBottom: 2},
  suggestionChip: {minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface, paddingHorizontal: 14, paddingVertical: 10, borderRadius: RADIUS.md},
  suggestionText: {flex: 1, fontSize: 13, lineHeight: 18, fontWeight: '600', color: COLORS.textDark},
  composer: {flexDirection: 'row', alignItems: 'flex-end', gap: 8, backgroundColor: COLORS.surface, borderTopWidth: 1, borderTopColor: COLORS.border, paddingHorizontal: 12, paddingTop: 10, paddingBottom: 6},
  input: {flex: 1, minHeight: 48, maxHeight: 110, borderWidth: 1, borderColor: COLORS.border, borderRadius: RADIUS.lg, paddingHorizontal: 14, paddingVertical: 11, color: COLORS.textDark, backgroundColor: COLORS.background, fontSize: 14},
  sendButton: {width: 48, height: 48, borderRadius: RADIUS.md, backgroundColor: COLORS.accentDark, alignItems: 'center', justifyContent: 'center'},
  sendButtonDisabled: {opacity: 0.45},
  disclaimer: {backgroundColor: COLORS.surface, color: COLORS.textGray, fontSize: 11, lineHeight: 15, textAlign: 'center', paddingHorizontal: 12, paddingBottom: 8},
});

export default AiAssistantScreen;
