import { GEMINI_CONFIG, isGeminiConfigured, getGeminiUrl } from '../config/gemini.config';

/**
 * Gemini AI Service
 * Wrapper for Google's Gemini API
 */
class GeminiService {
  /**
   * Generate text response from Gemini
   * @param {string} prompt - The text prompt
   * @param {object} options - Optional configuration
   */
  async generateText(prompt, options = {}) {
    if (!isGeminiConfigured()) {
      throw new Error('Gemini API key not configured. Please add your API key to gemini.config.js');
    }

    const url = getGeminiUrl(GEMINI_CONFIG.endpoints.generateText);
    
    const requestBody = {
      contents: [
        {
          parts: [
            {
              text: prompt,
            },
          ],
        },
      ],
      generationConfig: {
        ...GEMINI_CONFIG.generationConfig,
        ...options.generationConfig,
      },
      safetySettings: GEMINI_CONFIG.safetySettings,
    };

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || 'Gemini API request failed');
      }

      const data = await response.json();
      
      // Extract text from response
      const text = data.candidates?.[0]?.content?.parts?.[0]?.text || '';
      
      return {
        success: true,
        text,
        rawResponse: data,
      };
    } catch (error) {
      console.error('Gemini generateText error:', error);
      return {
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * Generate response from image (Vision API for OCR)
   * @param {string} base64Image - Base64 encoded image
   * @param {string} prompt - Text prompt for the image
   */
  async generateFromImage(base64Image, prompt = 'Extract all text from this image') {
    if (!isGeminiConfigured()) {
      throw new Error('Gemini API key not configured. Please add your API key to gemini.config.js');
    }

    const url = getGeminiUrl(GEMINI_CONFIG.endpoints.generateVision);
    
    // Remove data URL prefix if present
    const cleanBase64 = base64Image.replace(/^data:image\/\w+;base64,/, '');
    
    const requestBody = {
      contents: [
        {
          parts: [
            {
              text: prompt,
            },
            {
              inline_data: {
                mime_type: 'image/jpeg',
                data: cleanBase64,
              },
            },
          ],
        },
      ],
      generationConfig: {
        temperature: 0.4, // Lower temperature for more accurate OCR
        topK: 32,
        topP: 1,
        maxOutputTokens: 4096,
      },
      safetySettings: GEMINI_CONFIG.safetySettings,
    };

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || 'Gemini Vision API request failed');
      }

      const data = await response.json();
      
      // Extract text from response
      const text = data.candidates?.[0]?.content?.parts?.[0]?.text || '';
      
      return {
        success: true,
        text,
        rawResponse: data,
      };
    } catch (error) {
      console.error('Gemini generateFromImage error:', error);
      return {
        success: false,
        error: error.message,
      };
    }
  }

  /**
   * Chat completion with conversation history
   * @param {array} messages - Array of {role: 'user'|'model', text: string}
   */
  async chat(messages) {
    if (!isGeminiConfigured()) {
      throw new Error('Gemini API key not configured. Please add your API key to gemini.config.js');
    }

    const url = getGeminiUrl(GEMINI_CONFIG.endpoints.generateText);
    
    // Convert messages to Gemini format
    const contents = messages.map(msg => ({
      role: msg.role === 'user' ? 'user' : 'model',
      parts: [{ text: msg.text }],
    }));

    const requestBody = {
      contents,
      generationConfig: GEMINI_CONFIG.generationConfig,
      safetySettings: GEMINI_CONFIG.safetySettings,
    };

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || 'Gemini Chat API request failed');
      }

      const data = await response.json();
      
      // Extract text from response
      const text = data.candidates?.[0]?.content?.parts?.[0]?.text || '';
      
      return {
        success: true,
        text,
        rawResponse: data,
      };
    } catch (error) {
      console.error('Gemini chat error:', error);
      return {
        success: false,
        error: error.message,
      };
    }
  }
}

export default new GeminiService();
