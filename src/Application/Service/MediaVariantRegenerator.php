<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Service;

use Semitexa\Core\Attribute\AsService;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Media\Domain\Contract\MediaVariantRepositoryInterface;
use Semitexa\Media\Domain\Enum\MediaVariantStatus;
use Semitexa\Media\Domain\Model\MediaAsset;

/**
 * Puts an asset's variants back on the queue.
 *
 * It takes the asset, not its id, and that is the point. An asset id is
 * public — it is written into the markup that names the picture — while the
 * variant repository reads and writes under system scope, so an id alone
 * decides nothing about whose rows are about to be reset. Whoever calls this
 * has already answered that question and holds the row that proves it:
 *
 *  - a request goes through {@see MediaService::queueRegeneration()}, which
 *    gets the asset from {@see MediaObjectLocator::ownedAsset()} and so never
 *    holds another tenant's;
 *  - the operator's `media:regenerate` loads the asset itself, as system,
 *    and calls this directly. This class is the system path: it is not on
 *    {@see \Semitexa\Media\Domain\Contract\MediaServiceInterface} and asks
 *    nothing of the tenant context, because a console has none.
 *
 * Either way a variant is only touched when it carries the asset's tenant. A
 * row that claims the asset but another tenant is somebody else's data, and
 * the message built from it would be worked under that tenant's name.
 */
#[AsService]
final class MediaVariantRegenerator
{
    #[InjectAsReadonly]
    protected MediaVariantRepositoryInterface $variantRepository;

    #[InjectAsReadonly]
    protected MediaQueueDispatcher $queueDispatcher;

    /**
     * @return int how many variants were queued
     */
    public function requeue(MediaAsset $asset, ?string $variantKey = null): int
    {
        $assetId = $asset->getId();
        $tenantId = $asset->getTenantId() ?? '';

        $variants = $variantKey !== null
            ? [$this->variantRepository->findByAssetAndKey($assetId, $variantKey)]
            : $this->variantRepository->findByAssetId($assetId);

        $queued = 0;
        foreach ($variants as $variant) {
            if ($variant === null || ($variant->getTenantId() ?? '') !== $tenantId) {
                continue;
            }

            // Reset to queued (readonly resource — dispatch the saved row).
            $variant = $this->variantRepository->save($variant->with([
                'status' => MediaVariantStatus::Queued->value,
                'queuedAt' => new \DateTimeImmutable(),
                'errorCode' => null,
                'errorMessage' => null,
            ]));
            $this->queueDispatcher->dispatch($assetId, $variant);
            $queued++;
        }

        return $queued;
    }
}
