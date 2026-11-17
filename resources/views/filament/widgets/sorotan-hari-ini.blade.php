@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\Support\View\Components\SectionComponent\IconComponent;

    /**
     * Companion of "Selamat datang" in the first dashboard row. It borrows the account widget's
     * classes on purpose: the panels have no custom theme, so only Filament's own CSS exists,
     * and the same structure (icon, two lines, button) gives both halves of the row the same look.
     * When a long line wraps, the <style> below stretches both cards to the row's height (the grid
     * already stretches the widget; Filament's section inside it keeps its own height).
     *
     * @var string $judul
     * @var string $keterangan
     * @var ?string $tautan
     * @var ?string $labelTautan
     * @var \Filament\Support\Icons\Heroicon|string|null $ikonTautan
     */
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <style>
        .fi-account-widget .fi-section { height: 100%; display: flex; flex-direction: column; }
        .fi-account-widget .fi-section-content-ctn { flex: 1; display: flex; }
        .fi-account-widget .fi-section-content { flex: 1; }
    </style>

    <x-filament::section>
        {{
            \Filament\Support\generate_icon_html(
                Heroicon::OutlinedCalendarDays,
                attributes: (new ComponentAttributeBag)->color(IconComponent::class, 'primary'),
                size: IconSize::TwoExtraLarge,
            )
        }}

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                {{ $judul }}
            </h2>

            <p class="fi-account-widget-user-name">
                {{ $keterangan }}
            </p>
        </div>

        @if ($tautan)
            <div class="fi-account-widget-logout-form">
                <x-filament::button
                    color="gray"
                    :href="$tautan"
                    :icon="$ikonTautan"
                    labeled-from="sm"
                    tag="a"
                >
                    {{ $labelTautan }}
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
