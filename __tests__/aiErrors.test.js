import {getAiErrorMessage} from '../src/services/aiErrors';

describe('AI assistant error messages', () => {
  it('shows the safe model configuration error returned by Laravel', () => {
    expect(getAiErrorMessage({response: {status: 502, data: {
      message: 'The configured AI model is unavailable for this server key. Ask the administrator to check GEMINI_MODEL and model access.',
    }}})).toMatch(/check GEMINI_MODEL/);
  });

  it('shows safe missing-key and rate-limit messages', () => {
    expect(getAiErrorMessage({response: {status: 503, data: {
      message: 'AI Assistant is not configured. Ask the administrator to add GEMINI_API_KEY.',
    }}})).toMatch(/GEMINI_API_KEY/);
    expect(getAiErrorMessage({response: {status: 503, data: {
      message: 'The AI service is busy. Please wait a moment and try again.',
    }}})).toMatch(/busy/);
  });

  it('does not expose unexpected server diagnostics', () => {
    expect(getAiErrorMessage({response: {status: 502, data: {
      message: 'provider-internal-secret',
    }}})).not.toContain('provider-internal-secret');
  });
});
