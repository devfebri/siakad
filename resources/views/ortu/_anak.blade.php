{{-- Pilihan anak untuk orang tua dengan lebih dari satu anak. Variabel: $daftarAnak, $anak --}}
@if ($daftarAnak->count() > 1)
    <div class="mb-5 flex flex-wrap items-center gap-2 print:hidden" role="group" aria-label="Pilih anak">
        <span class="text-sm font-semibold text-slate-500">Anak:</span>
        @foreach ($daftarAnak as $a)
            <form method="POST" action="{{ route('ortu/anak.pilih', $a) }}">
                @csrf
                <button type="submit" @class(['btn btn-sm', 'btn-primary' => $a->id === $anak?->id, 'btn-outline' => $a->id !== $anak?->id])>
                    <i class="bi bi-person"></i> {{ $a->nama_siswa }}{{ $a->kelas ? ' · '.$a->kelas->nama_kelas : '' }}
                </button>
            </form>
        @endforeach
    </div>
@endif
