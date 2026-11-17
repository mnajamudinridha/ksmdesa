{{-- Report letterhead + print rules. Shown only when printed ("Cetak / PDF" → the browser's
     print dialog, where "Save as PDF" makes the PDF). Inline styles: the panels use Filament's
     prebuilt CSS, so Tailwind classes not used by Filament would not exist. --}}
<div>
    <style>
        .ksm-kop-cetak, .ksm-ttd-cetak { display: none; }

        @media print {
            /* Panel chrome, filter and the on-screen heading (the letterhead repeats title and period). */
            .fi-sidebar, .fi-sidebar-close-overlay, .fi-topbar-ctn, .fi-header,
            .ksm-filter-periode, .fi-ta-header-toolbar { display: none !important; }
            .fi-layout, .fi-main-ctn, .fi-main { margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .ksm-kop-cetak, .ksm-ttd-cetak { display: block !important; }
            /* Paper is white whatever the screen theme: dark-mode text would vanish without its background. */
            html { color-scheme: light !important; }
            *, *::before, *::after { color: #111827 !important; background: transparent !important; box-shadow: none !important; }
            /* Wide tables fit the page instead of being cut off at the scroll edge. */
            .fi-ta-content-ctn { overflow: visible !important; }
            .fi-ta-table { width: 100% !important; font-size: 9pt !important; }
            .fi-ta-cell, .fi-ta-header-cell { padding: 3px 5px !important; border-bottom: 1px solid #d1d5db !important; }
            .fi-wi-widget, .fi-ta-ctn { break-inside: avoid; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>

    <div class="ksm-kop-cetak" style="border-bottom: 2px solid #111827; padding-bottom: 0.75rem; margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            @if ($logo = $ksm?->logoUrl())
                <img src="{{ $logo }}" alt="Logo {{ $ksm->nama }}" style="height: 56px; width: auto;">
            @endif
            <div>
                <div style="font-size: 1.25rem; font-weight: 700;">{{ $ksm?->nama }}</div>
                @if (filled($ksm?->desa))
                    <div>Desa {{ $ksm->desa }}</div>
                @endif
            </div>
        </div>
        <div style="margin-top: 0.75rem; font-size: 1.1rem; font-weight: 600;">{{ $judul }}</div>
        <div>Periode: {{ $periode }}</div>
        <div style="font-size: 0.85rem; color: #4b5563;">Dicetak {{ $dicetak }}@if (filled($pencetak)) oleh {{ $pencetak }}@endif</div>
    </div>
</div>
