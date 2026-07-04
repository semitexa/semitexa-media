<?php

declare(strict_types=1);

namespace Semitexa\Media\Application\Db\MySQL\Repository;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesRepositoryContract;
use Semitexa\Media\Application\Db\MySQL\Model\MediaCollectionResource;
use Semitexa\Media\Domain\Contract\MediaCollectionRepositoryInterface;
use Semitexa\Orm\OrmManager;
use Semitexa\Orm\Query\Operator;
use Semitexa\Orm\Query\SystemScopeToken;
use Semitexa\Orm\Repository\DomainRepository;

#[SatisfiesRepositoryContract(of: MediaCollectionRepositoryInterface::class)]
class MediaCollectionRepository extends AbstractMediaRepository implements MediaCollectionRepositoryInterface
{

    protected function getResourceClass(): string
    {
        return MediaCollectionResource::class;
    }

    #[InjectAsReadonly]
    protected OrmManager $orm;

    private ?DomainRepository $repository = null;

    private ?DomainRepository $system = null;

    public function findActive(string $collectionKey, ?string $tenantId = null): ?MediaCollectionResource
    {
        if ($tenantId !== null) {
            /** @var MediaCollectionResource|null $row */
            $row = $this->system()->query()
                ->where(MediaCollectionResource::column('collection_key'), Operator::Equals, $collectionKey)
                ->where(MediaCollectionResource::column('tenant_id'), Operator::Equals, $tenantId)
                ->where(MediaCollectionResource::column('enabled'), Operator::Equals, 1)
                ->fetchOneAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());

            if ($row !== null) {
                return $row;
            }
        }

        /** @var MediaCollectionResource|null */
        return $this->system()->query()
            ->where(MediaCollectionResource::column('collection_key'), Operator::Equals, $collectionKey)
            ->whereNull(MediaCollectionResource::column('tenant_id'))
            ->where(MediaCollectionResource::column('enabled'), Operator::Equals, 1)
            ->fetchOneAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());
    }

    public function save(MediaCollectionResource $entity): MediaCollectionResource
    {
        /** @var MediaCollectionResource */
        return $entity->id === ''
            ? $this->system()->insert($entity)
            : $this->system()->update($entity);
    }

    public function findAllEnabled(): array
    {
        /** @var list<MediaCollectionResource> */
        return $this->system()->query()
            ->where(MediaCollectionResource::column('enabled'), Operator::Equals, 1)
            ->fetchAllAs(MediaCollectionResource::class, $this->orm()->getMapperRegistry());
    }

    /**
     * SYSTEM-scope view — the media pipeline (worker, planner, recalculator)
     * operates rows by their own ids across tenants; tenant-parameterised
     * finders below scope with forTenant()/explicit predicates. The resources
     * are #[TenantScoped], so any FUTURE finder must pick a posture — unscoped
     * queries fail closed instead of leaking across tenants.
     */
    private function system(): DomainRepository
    {
        return $this->system ??= $this->repository()->withoutTenantScope(SystemScopeToken::issue());
    }

    private function repository(): DomainRepository
    {
        return $this->repository ??= $this->orm()->repository(
            MediaCollectionResource::class,
            MediaCollectionResource::class,
        );
    }

    private function orm(): OrmManager
    {
        return $this->orm ??= new OrmManager();
    }

}
