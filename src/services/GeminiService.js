import api from './api';

// Compatibility wrapper for older imports. Gemini is never called from the
// device: all chat requests use the authenticated, business-scoped Laravel API.
class GeminiService {
  async generateText(prompt) {
    return this.chat([{role: 'user', text: String(prompt || '')}]);
  }

  async generateFromImage() {
    throw new Error('Receipt scanning uses on-device OCR. Image requests to Gemini are not supported.');
  }

  async chat(messages) {
    try {
      const response = await api.post('/ai/chat', {messages});
      return {success: true, text: response.data.reply};
    } catch (error) {
      return {
        success: false,
        error: error?.response?.data?.message || 'The AI assistant is temporarily unavailable. Please try again.',
      };
    }
  }
}

export default new GeminiService();
