<?php

namespace Drupal\gemini_provider\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the Google's Gemini.
 */
#[AiProvider(
  id: 'gemini',
  label: new TranslatableMarkup('Gemini')
)]
class GeminiProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * The Gemini Client.
   *
   * @var \Gemini\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(string $operation_type = NULL): array {
    $this->loadClient();

    $supported_models = [];
    try {
      $models = $this->client->models()->list()->toArray();

      if (!empty($models['models'])) {
        foreach ($models['models'] as $model) {
          $supported_models[$model['name']] = $model['displayName'];
        }
      }
    }
    catch (\JsonException $e) {
      throw new AiResponseErrorException('Couldn\'t fetch gemini models.');
    }

    return $supported_models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(string $operation_type = NULL): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    // @todo We need to add other operation types here later.
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('gemini_provider.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    $definition = Yaml::parseFile(
      $this->moduleHandler->getModule('gemini_provider')
        ->getPath() . '/definitions/api_defaults.yml'
    );
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    $chat_input = $input;
    $system_prompt = '';
    if ($input instanceof ChatInput) {
      $chat_input = [];
      foreach ($input->getMessages() as $message) {
        if ($message->getRole() == 'system') {
          $system_prompt = $message->getText();
          continue;
        }

        $chat_input[] = [
          'role' => $message->getRole(),
          'content' => $message->getText(),
        ];
      }
    }

    $payload = ([
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration);

    if (!isset($payload['system']) && $system_prompt) {
      $payload['system'] = $system_prompt;
    }

    $response = $this->client->generativeModel($payload['model'])
      ->generateContent($payload['system'] . "\n" . $payload['messages'][0]['content']);

    $text = '';
    if (!empty($response->parts())) {
      $text = $response->text();
    }

    $message = new ChatMessage('', $text);

    return new ChatOutput($message, $response, []);
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return Client
   *   The Gemini client.
   */
  public function getClient(string $api_key = '') {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }

    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Gemini Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }

      $this->client = \Gemini::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient($this->httpClient)
        ->make();
    }
  }

  /**
   * Load API key from key module.
   *
   * @return string
   *   The API key.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))
      ->getKeyValue();
  }

}
