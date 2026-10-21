<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BiometricAuditLog\Pages;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Date;
use MoonShine\Support\ListOf;
use MoonShine\Contracts\UI\ActionButtonContract;
use App\Models\BiometricAuditLog;

class BiometricAuditLogIndexPage extends IndexPage
{
    protected bool $isLazy = false;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Mijoz / Fuqaro', 'person_name')
                ->default('— Noma\'lum —')
                ->sortable(),
            Text::make('Harakat turi', 'action')
                ->badge(fn($val) => match($val) {
                    'login_success', 'egov_login_success' => 'green',
                    'external_passport_face_verify' => 'purple',
                    'api_auth_token_issued' => 'blue',
                    'register' => 'blue',
                    'login_pending' => 'yellow',
                    'egov_face_mismatch' => 'yellow',
                    'api_verify_failed', 'api_auth_failed', 'api_unauthorized', 'egov_passport_failed', 'login_not_found' => 'red',
                    default => 'gray',
                }),
            Text::make('Holat', 'status')
                ->badge(fn($val) => match($val) {
                    'success' => 'green',
                    'warning' => 'yellow',
                    'danger', 'failed', 'error' => 'red',
                    default => 'red',
                }),
            Text::make('Kosinus masofa', 'similarity_distance')
                ->default('—')
                ->sortable(),
            Text::make('IP Manzil', 'ip_address'),
            Text::make('Tafsilot', 'details'),
            Date::make('Vaqti', 'created_at')
                ->format('d.m.Y H:i:s')
                ->sortable(),
        ];
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function topLeftButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, []);
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make('Barcha jurnallar', fn($q) => $q->orderBy('id', 'desc'))->default(),
            QueryTag::make('❌ Xatoliklar / Rad etilganlar (Failed API)', fn($q) => $q->where('status', '!=', 'success')->orderBy('id', 'desc')),
            QueryTag::make('🌐 B2B API Loglari', fn($q) => $q->where(fn($sub) => $sub->where('action', 'like', '%api%')->orWhere('action', 'like', '%external%'))->orderBy('id', 'desc')),
            QueryTag::make('✅ Muvaffaqiyatli kirishlar', fn($q) => $q->where('status', 'success')->orderBy('id', 'desc')),
        ];
    }
}
