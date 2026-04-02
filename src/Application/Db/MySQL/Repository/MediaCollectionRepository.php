<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attributes\InjectAsReadonly;
use Semitexa\Core\Attributes\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;
use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionTableModel;
use Semitexa\Media\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaCollectionRepositoryInterface::class)]
class MediaCollectionRepository implements MediaCollectionRepositoryInterface
{
    use AssertsExpectedResourceType;

    protected function getResourceClass(): string
    {
        return MediaCollectionResource::class;
    }

    #[InjectAsReadonly]
    protected ?OrmManager $orm = null;

    private ?DomainRepository $repository = null;

    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollectionResource
    {
        if ($tenantId !== null) {
            /** @var MediaCollectionResource|null $row */
            $row = $this->repository()->query()
                ->where(MediaCollectionTableModel::column('collection_key'), Operator::Equals, $collectionKey)
                ->where(MediaCollectionTableModel::column('tenant_id'), Operator::Equals, $tenantId)
                ->where(MediaCollectionTableModel::column('enabled'), Operator::Equals, 1)
                ->fetchOneAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());

            if ($row !== null) {
                return $row;
            }
        }

        /** @var MediaCollectionResource|null */
        return $this->repository()->query()
            ->where(MediaCollectionTableModel::column('collection_key'), Operator::Equals, $collectionKey)
            ->whereNull(MediaCollectionTableModel::column('tenant_id'))
            ->where(MediaCollectionTableModel::column('enabled'), Operator::Equals, 1)
            ->fetchOneAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaCollectionResource $entity): void
    {
        $resource = $this->assertResourceType($entity);
        $persisted = $resource->id === ''
            ? $this->repository()->insert($resource)
            : $this->repository()->update($resource);

        $this->copyIntoMutableResource($persisted, $resource);
    }

    public function findAllEnabled(): array
    {
        /** @var list<MediaCollectionResource> */
        return $this->repository()->query()
            ->where(MediaCollectionTableModel::column('enabled'), Operator::Equals, 1)
            ->fetchAllAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaCollectionTableModel::class,
            MediaCollectionResource::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ??= new OrmManager();
    }

    private function copyIntoMutableResource(object $source, MediaCollectionResource $target): void
    {
        $source instanceof MediaCollectionResource || throw new \InvalidArgumentException('Unexpected persisted resource.');

        foreach (get_object_vars($source) as $property => $value) {
            $target->{$property} = $value;
        }
    }
}
