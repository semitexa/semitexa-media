<?php

declare(strict_types=1);

namespace Semitexa\Media\Domain\Model;

final class QueuedMediaTransformMessage implements \JsonSerializable
{
    public const string TYPE = 'media_transform';

    public function __construct(
        public readonly string $assetId,
        public readonly string $variantKey,
        public readonly string $tenantId,
        public string $queuedAt = '',
    ) {
        $this->queuedAt = $queuedAt ?: date(DATE_ATOM);
    }

    public function jsonSerialize(): array
    {
        return [
            'type'       => self::TYPE,
            'assetId'    => $this->assetId,
            'variantKey' => $this->variantKey,
            'tenantId'   => $this->tenantId,
            'queuedAt'   => $this->queuedAt,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $payload): self
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            assetId:    $data['assetId'],
            variantKey: $data['variantKey'],
            tenantId:   $data['tenantId'],
            queuedAt:   $data['queuedAt'] ?? date(DATE_ATOM),
        );
    }
}
