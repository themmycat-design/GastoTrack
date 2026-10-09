/**
 * Gemini AI Configuration
 * 
 * Gemini credentials must stay on the Laravel server. The mobile app uses the
 * authenticated Laravel AI endpoint and local ML Kit for receipt OCR.
 */

export const GEMINI_CONFIG = {
  // Intentionally empty: never bundle a Gemini key in the mobile application.
  apiKey: null,
  
  // Gemini API endpoints
  endpoints: {
    // For text generation (AI chatbot, analytics)
    generateText: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
    
    // For vision (OCR from receipts)
    generateVision: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
  },
  
  // Model configurations
  models: {
    // For chatbot and analytics
    text: 'gemini-1.5-flash',
    
    // For OCR and image analysis
    vision: 'gemini-1.5-flash',
  },
  
  // Generation parameters
  generationConfig: {
    temperature: 0.7,
    topK: 40,
    topP: 0.95,
    maxOutputTokens: 2048,
  },
  
  // Safety settings (block harmful content)
  safetySettings: [
    {
      category: 'HARM_CATEGORY_HARASSMENT',
      threshold: 'BLOCK_MEDIUM_AND_ABOVE',
    },
    {
      category: 'HARM_CATEGORY_HATE_SPEECH',
      threshold: 'BLOCK_MEDIUM_AND_ABOVE',
    },
    {
      category: 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
      threshold: 'BLOCK_MEDIUM_AND_ABOVE',
    },
    {
      category: 'HARM_CATEGORY_DANGEROUS_CONTENT',
      threshold: 'BLOCK_MEDIUM_AND_ABOVE',
    },
  ],
};

/**
 * Check if API key is configured
 */
export const isGeminiConfigured = () => {
  return false;
};

/**
 * Get full API URL with key
 */
export const getGeminiUrl = () => {
  throw new Error('Gemini requests must be made through the Laravel backend.');
};
