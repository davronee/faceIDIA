<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use App\MoonShine\Resources\Person\PersonResource;
use App\MoonShine\Resources\BiometricAuditLog\BiometricAuditLogResource;
use App\MoonShine\Resources\ApiClient\ApiClientResource;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuItem::make(PersonResource::class, 'Fuqarolar arizalari'),
            MenuItem::make(BiometricAuditLogResource::class, 'Xavfsizlik audit jurnali'),
            MenuItem::make(ApiClientResource::class, 'API Boshqaruvi (B2B)'),
            MenuItem::make(static fn() => '/docs', 'API Hujjatlari (Swagger)')->icon('document-text')->blank(),
        ];
    }

    protected function getFooterCopyright(): string
    {
        return '© ' . date('Y') . ' «Moliyachi» Axborot Tizimi';
    }

    protected function getFooterMenu(): array
    {
        return [];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
