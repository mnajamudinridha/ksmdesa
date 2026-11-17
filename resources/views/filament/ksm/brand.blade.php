{{-- Panel brand inside the app: the KSM logo with its name beside it. Inline styles: the panel
     uses Filament's prebuilt CSS, so Tailwind classes not used by Filament would not exist. --}}
<span class="ksm-brand" style="display: flex; align-items: center; gap: 0.75rem; height: 100%;">
    <img src="{{ $ksm->logoUrl() }}" alt="Logo {{ $ksm->nama }}" style="height: 100%; width: auto; flex-shrink: 0;">
    <span style="font-weight: 600; font-size: 1rem; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">{{ $ksm->nama }}</span>
</span>
