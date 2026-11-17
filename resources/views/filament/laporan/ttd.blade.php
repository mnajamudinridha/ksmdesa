{{-- Signature lines under a printed report (hidden on screen; print rules live in kop.blade.php). --}}
<div class="ksm-ttd-cetak" style="margin-top: 2.5rem;">
    <div style="display: flex; justify-content: space-between; gap: 2rem;">
        @foreach (['Ketua KSM', 'Bendahara'] as $jabatan)
            <div style="width: 40%; text-align: center;">
                <div>{{ $jabatan }}</div>
                <div style="height: 4.5rem;"></div>
                <div style="border-top: 1px solid #111827;">&nbsp;</div>
            </div>
        @endforeach
    </div>
</div>
