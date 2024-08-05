## Gemini Provider

This module serves as a [Google Gemini](https://ai.google.dev/gemini-api/docs)
provider for the [Drupal AI](https://www.drupal.org/project/ai) module.

By installing and enabling this module, you can seamlessly integrate Google's
Gemini API through the [Drupal AI](https://www.drupal.org/project/ai) module's
abstraction layer.

### Features

- **Chat Integration**: Implement ChatInterface (text to text).
- **Configuration**: Provides a configuration form for Gemini authentication.
- **Plugin Implementation**: Offers a plugin implementation for the Drupal AI
  module.

As Google's Gemini is multimodal thing, we need to implement other interfaces
too
(text to speech, embeddings etc.)

## Requirements

To use this module, the following dependencies are required:

- [Drupal AI](https://www.drupal.org/project/ai)
- [Drupal Key](https://www.drupal.org/project/key)
- [Google Gemini PHP Client](https://github.com/google-gemini-php/client)

## Maintainers

- Giorgi Jibladze (jibla) - https://www.drupal.org/u/jibla
