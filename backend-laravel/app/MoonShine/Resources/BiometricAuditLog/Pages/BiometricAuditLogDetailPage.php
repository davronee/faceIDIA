<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BiometricAuditLog\Pages;

use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Date;
use App\MoonShine\Resources\BiometricAuditLog\BiometricAuditLogResource;
use MoonShine\Support\ListOf;
use Throwable;

/**
 * @extends DetailPage<BiometricAuditLogResource>
 */
class BiometricAuditLogDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Fuqaro', 'person_name')
                ->default('— Noma\'lum shaxs —'),
            Text::make('Fuqaro ID', 'person_id')
                ->default('—'),
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
            Text::make('Xavfsizlik holati', 'status')
                ->badge(fn($val) => match($val) {
                    'success' => 'green',
                    'warning' => 'yellow',
                    'danger', 'failed', 'error' => 'red',
                    default => 'red',
                }),
            Text::make('Kosinus masofa', 'similarity_distance')
                ->default('—'),
            Text::make('IP Manzil', 'ip_address')
                ->default('—'),
            Text::make('User Agent / Qurilma', 'user_agent')
                ->default('—'),
            Text::make('Tafsilot', 'details')
                ->default('—'),
            Date::make('Vaqti', 'created_at')
                ->format('d.m.Y H:i:s'),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }
}
