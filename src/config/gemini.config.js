// Retained as a compatibility module only. Credentials and provider endpoints
// belong exclusively to Laravel; never add a Gemini key to this bundle.
export const isGeminiConfigured = () => false;

export const getGeminiUrl = () => {
  throw new Error('Gemini requests must be made through the authenticated Laravel API.');
};
