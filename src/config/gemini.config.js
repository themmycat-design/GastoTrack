/**
 * Gemini AI Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to https://aistudio.google.com/app/apikey
 * 2. Sign in with your Google account
 * 3. Click "Create API Key"
 * 4. Copy the API key
 * 5. Replace 'YOUR_GEMINI_API_KEY_HERE' below with your actual key
 * 
 * SECURITY NOTE:
 * For production, move this to:
 * - React Native Config (.env file)
 * - Or secure backend API that proxies Gemini requests
 */

export const GEMINI_CONFIG = {
  // Replace with your actual Gemini API key
  // IMPORTANT: DO NOT commit your real API key! Use environment variables
  apiKey: 'YOUR_GEMINI_API_KEY_HERE',
  
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
  return GEMINI_CONFIG.apiKey && GEMINI_CONFIG.apiKey !== 'YOUR_GEMINI_API_KEY_HERE';
};

/**
 * Get full API URL with key
 */
export const getGeminiUrl = (endpoint) => {
  return `${endpoint}?key=${GEMINI_CONFIG.apiKey}`;
};
