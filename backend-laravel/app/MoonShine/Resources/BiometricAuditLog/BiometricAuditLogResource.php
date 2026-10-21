<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BiometricAuditLog;

use App\Models\BiometricAuditLog;
use App\MoonShine\Resources\BiometricAuditLog\Pages\BiometricAuditLogIndexPage;
use App\MoonShine\Resources\BiometricAuditLog\Pages\BiometricAuditLogDetailPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

class BiometricAuditLogResource extends ModelResource
{
    protected string $model = BiometricAuditLog::class;

    protected string $title = 'Xavfsizlik audit jurnali';

    /**
     * @return ListOf<Action>
     */
    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [
            Action::VIEW,
//            Action::DELETE,
//            Action::MASS_DELETE,
        ]);
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            BiometricAuditLogIndexPage::class,
            BiometricAuditLogDetailPage::class,
        ];
    }
}
