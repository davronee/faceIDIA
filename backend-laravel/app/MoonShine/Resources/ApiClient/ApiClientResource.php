<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ApiClient;

use App\Models\ApiClient;
use App\MoonShine\Resources\ApiClient\Pages\ApiClientIndexPage;
use App\MoonShine\Resources\ApiClient\Pages\ApiClientFormPage;
use App\MoonShine\Resources\ApiClient\Pages\ApiClientDetailPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<ApiClient, ApiClientIndexPage, ApiClientFormPage, ApiClientDetailPage>
 */
class ApiClientResource extends ModelResource
{
    protected string $model = ApiClient::class;

    protected string $title = 'API Mijozlari (B2B)';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ApiClientIndexPage::class,
            ApiClientFormPage::class,
            ApiClientDetailPage::class,
        ];
    }
}
