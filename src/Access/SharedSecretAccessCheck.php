<?php

namespace Drupal\cap_alerts_aggregator_connector\Access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates the CAP Aggregator's shared-secret header.
 *
 * Deliberately checked inside the controller (see CapFeedSourceApiController)
 * rather than as a route `_custom_access` requirement: Drupal's page cache
 * does not reliably respect cache metadata from custom access checkers on
 * the resulting access-denied response, which previously caused a denied (or
 * allowed) response to be cached and served regardless of the header on
 * subsequent requests. Controller-generated responses with explicit
 * Cache-Control: no-store are cache-safe in both directions.
 */
class SharedSecretAccessCheck implements ContainerInjectionInterface {

  public const HEADER = 'X-Cap-Aggregator-Secret';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('key.repository'),
    );
  }

  /**
   * Checks whether the request carries the correct shared-secret header.
   */
  public function isValid(Request $request): bool {
    $keyId = $this->configFactory->get('cap_alerts_aggregator_connector.settings')->get('aggregator_secret_key');
    if (!$keyId) {
      return FALSE;
    }

    $key = $this->keyRepository->getKey($keyId);
    $expected = $key ? (string) $key->getKeyValue() : '';
    $provided = (string) $request->headers->get(self::HEADER, '');

    return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
  }

}

