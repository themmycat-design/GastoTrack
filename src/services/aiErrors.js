import {getApiErrorMessage} from './apiErrors';

// Only display server messages that the AI endpoint deliberately makes safe.
// Never pass arbitrary 5xx response bodies (which might contain diagnostics) to the UI.
const safeAiMessages = new Set([
  'AI Assistant is not configured. Ask the administrator to add GEMINI_API_KEY.',
  'The AI service is temporarily unreachable. Please try again.',
  'The AI service is busy. Please wait a moment and try again.',
  'The AI service could not answer right now. Please try again.',
  'The AI service returned an empty response. Please rephrase your question.',
  'The configured AI model is unavailable for this server key. Ask the administrator to check GEMINI_MODEL and model access.',
]);

export const getAiErrorMessage = error => {
  const status = error?.response?.status;
  const message = error?.response?.data?.message;
  if ((status === 502 || status === 503) && safeAiMessages.has(message)) {
    return message;
  }
  return getApiErrorMessage(error, 'The AI assistant could not respond. Please try again.');
};
