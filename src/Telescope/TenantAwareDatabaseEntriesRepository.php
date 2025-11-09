<?php

declare(strict_types=1);

namespace Quvel\Tenant\Telescope;

use Illuminate\Support\Collection;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Throwable;

/**
 * Tenant-aware Telescope entries repository.
 *
 * Extends Laravel Telescope's DatabaseEntriesRepository to automatically
 * inject tenant_id into all telescope entries when tenant context is available.
 */
class TenantAwareDatabaseEntriesRepository extends DatabaseEntriesRepository
{
    /**
     * Store the given array of entries.
     */
    public function store(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        [$exceptions, $entries] = $entries->partition->isException();

        $this->storeExceptions($exceptions);

        $table = $this->table('telescope_entries');

        $tenantId = tenant_id();

        $entries->chunk($this->chunkSize)->each(function ($chunked) use ($table, $tenantId): void {
            $table->insert(
                $chunked->map(function ($entry) use ($tenantId) {
                    $entry->content = json_encode($entry->content, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

                    $data = $entry->toArray();

                    if ($tenantId !== null) {
                        $data['tenant_id'] = $tenantId;
                    }

                    return $data;
                })->toArray()
            );
        });

        $this->storeTags($entries->pluck('tags', 'uuid'));
    }

    /**
     * Store the given array of exception entries.
     */
    protected function storeExceptions(Collection $exceptions)
    {
        $tenantId = tenant_id();

        $exceptions->chunk($this->chunkSize)->each(function ($chunked) use ($tenantId): void {
            $table = $this->table('telescope_entries');

            [$stored, $new] = $this->getExistingExceptionsFingerprints($chunked);

            $table->insert(
                $new->map(function ($exception) use ($tenantId) {
                    $exception->content = array_merge(
                        $exception->content,
                        ['occurrences' => 1]
                    );

                    $exception->content = json_encode(
                        $exception->content,
                        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                    );

                    $data = $exception->toArray();

                    $data['should_display_on_index'] = true;

                    if ($tenantId !== null) {
                        $data['tenant_id'] = $tenantId;
                    }

                    return $data;
                })->toArray()
            );

            $stored->each(function ($exception) use ($table, $tenantId): void {
                $table
                    ->where('family_hash', $exception->familyHash)
                    ->when($tenantId !== null, fn($query) => $query->where('tenant_id', $tenantId))
                    ->update([
                        'content->occurrences' => $table->raw('JSON_EXTRACT(`content`, "$.occurrences") + 1'),
                        'should_display_on_index' => true,
                    ]);
            });
        });
    }

    /**
     * Store the tags for the given entries.
     */
    protected function storeTags(Collection $results)
    {
        $tenantId = tenant_id();

        $results->chunk($this->chunkSize)->each(function ($chunked) use ($tenantId): void {
            $table = $this->table('telescope_entries_tags');

            try {
                $table->insert(
                    $chunked->flatMap(
                        fn($tags, $uuid) => collect($tags)->map(function ($tag) use ($uuid, $tenantId): array {
                            $data = [
                                'entry_uuid' => $uuid,
                                'tag' => $tag,
                            ];

                            if ($tenantId !== null) {
                                $data['tenant_id'] = $tenantId;
                            }

                            return $data;
                        })
                    )->toArray()
                );
            } catch (Throwable $throwable) {
                // Ignore unique constraint violations - silently fail
                unset($throwable);
            }
        });
    }
}
