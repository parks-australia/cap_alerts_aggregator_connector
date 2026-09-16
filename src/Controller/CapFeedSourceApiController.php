<?php

namespace Drupal\cap_alerts_aggregator_connector\Controller;

use Drupal\cap_alerts_aggregator_connector\Access\SharedSecretAccessCheck;
use Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes enabled CAP Feed Source entities to the CAP Aggregator.
 *
 * Locked down via a shared-secret header, checked inside the controller (see
 * SharedSecretAccessCheck for why) — not the open JSON:API, since this
 * deliberately reveals filter thresholds, feed URLs, and resolved
 * credentials, which should never be publicly readable.
 */
class CapFeedSourceApiController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly SharedSecretAccessCheck $secretCheck,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('key.repository'),
      SharedSecretAccessCheck::create($container),
    );
  }

  /**
   * Returns all enabled CAP Feed Sources as JSON, including resolved secrets.
   */
  public function list(Request $request): JsonResponse {
    if (!$this->secretCheck->isValid($request)) {
      return $this->uncacheableJsonResponse(['message' => 'Invalid or missing shared secret.'], 403);
    }

    $storage = $this->entityTypeManager->getStorage('cap_feed_source');
    $ids = $storage->getQuery()->condition('status', TRUE)->accessCheck(FALSE)->execute();

    $sources = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      /** @var \Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface $entity */
      $sources[] = $this->normalize($entity);
    }

    return $this->uncacheableJsonResponse(['sources' => $sources]);
  }

  /**
   * Builds a JSON response that Drupal's page cache will never store.
   *
   * The access decision depends entirely on a request header the page cache
   * doesn't vary by, so caching either outcome would leak an allowed
   * response to unauthenticated callers, or a denied one to authenticated
   * ones (see SharedSecretAccessCheck).
   */
  private function uncacheableJsonResponse(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    return $response;
  }

  /**
   * Normalizes one Feed Source, resolving its credential to a real value.
   */
  private function normalize(CapFeedSourceInterface $entity): array {
    $credential = NULL;
    $keyId = $entity->getCredentialKeyId();
    if ($keyId !== '') {
      $key = $this->keyRepository->getKey($keyId);
      $credential = $key ? $key->getKeyValue() : NULL;
    }

    return [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'feedUrl' => $entity->getFeedUrl(),
      'feedFormat' => $entity->getFeedFormat(),
      'capXmlRootElement' => $entity->getCapXmlRootElement(),
      'credential' => $credential,
      'minSeverity' => $entity->get('min_severity'),
      'minCertainty' => $entity->get('min_certainty'),
      'minUrgency' => $entity->get('min_urgency'),
      'categoryAllowlist' => $this->splitList($entity->get('category_allowlist')),
      'msgtypeDenylist' => $this->splitList($entity->get('msgtype_denylist')),
      'agencyAllowlist' => $this->splitList($entity->get('agency_allowlist')),
      'agencyDenylist' => $this->splitList($entity->get('agency_denylist')),
      'requireGeometry' => (bool) $entity->get('require_geometry'),
      'parkOverrides' => $entity->getParkOverrides(),
    ];
  }

  /**
   * Splits a comma-separated config value into a trimmed, non-empty array.
   */
  private function splitList(string $value): array {
    if (trim($value) === '') {
      return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
  }

}
