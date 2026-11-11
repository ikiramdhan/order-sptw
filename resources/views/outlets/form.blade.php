@extends('layouts.app')
@section('title', $outlet->exists ? 'Ubah outlet' : 'Tambah outlet')
@section('content')
@php $term = (string) old('payment_term_days', $outlet->payment_term_days ?? 7); @endphp
<header class="page-head compact">
    <div>
        <h1>{{ $outlet->exists ? 'Ubah outlet' : 'Tambah outlet' }}</h1>
        <p>{{ $outlet->exists ? $outlet->name : 'Setelah disimpan, buat akun login untuk outlet ini di menu Pengguna.' }}</p>
    </div>
    <a class="btn" href="{{ route('outlets.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>

<form method="POST" class="fbento cols-7-5" action="{{ $outlet->exists ? route('outlets.update', $outlet) : route('outlets.store') }}" novalidate>
    @csrf
    @if ($outlet->exists) @method('PUT') @endif
    <div class="card">
        <h3 class="sect">Data outlet</h3>

        <label class="field">Nama outlet
            <input type="text" name="name" value="{{ old('name', $outlet->name) }}" placeholder="Outlet Bintaro" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Alamat
            <input type="text" name="address" value="{{ old('address', $outlet->address) }}">
            @error('address')<span class="err">{{ $message }}</span>@enderror
        </label>
        <div class="row">
            <label class="field">Penanggung jawab
                <input type="text" name="pic_name" value="{{ old('pic_name', $outlet->pic_name) }}">
            </label>
            <label class="field"><span class="lbl">No. WhatsApp <span class="hint">(untuk kirim invoice)</span></span>
                <input type="text" name="phone" value="{{ old('phone', $outlet->phone) }}" inputmode="tel" placeholder="08xx-xxxx-xxxx">
            </label>
        </div>
        <div class="row">
            <label class="field"><span class="lbl">Latitude <span class="hint">(titik lokasi untuk peta kurir)</span></span>
                <input type="text" name="lat" id="olat" value="{{ old('lat', $outlet->lat) }}" inputmode="decimal" placeholder="-6.2088">
                @error('lat')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field">Longitude
                <input type="text" name="lng" id="olng" value="{{ old('lng', $outlet->lng) }}" inputmode="decimal" placeholder="106.8456">
                @error('lng')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        <button type="button" class="btn outline" id="usegps">Pakai lokasi saya sekarang</button>
        <small style="display:block;color:var(--muted);margin-top:6px">Buka form ini dari HP saat berada di depan outlet, lalu tekan tombol di atas. Atau salin koordinat dari Google Maps (tekan lama di titik outlet).</small>
        <script>
        document.getElementById('usegps').addEventListener('click', function () {
          var b = this; b.textContent = 'Mencari lokasi…';
          navigator.geolocation.getCurrentPosition(function (p) {
            document.getElementById('olat').value = p.coords.latitude.toFixed(7);
            document.getElementById('olng').value = p.coords.longitude.toFixed(7);
            b.textContent = 'Lokasi terisi ✓';
          }, function () { b.textContent = 'Gagal. Izinkan lokasi atau isi manual'; }, { enableHighAccuracy: true, timeout: 15000 });
        });
        </script>
    </div>
    <div class="card">
        <h3 class="sect">Termin tempo</h3>
        <p style="color:var(--muted);margin:0 0 12px">Jatuh tempo invoice outlet ini mengikuti termin yang dipilih.</p>
        <div class="field">
            <div class="seg" role="radiogroup" aria-label="Termin tempo">
                @foreach (\App\Models\Outlet::TERMS as $days => $label)
                    <label><input type="radio" name="payment_term_days" value="{{ $days }}" {{ $term === (string) $days ? 'checked' : '' }}><span>{{ $days === 0 ? 'Tunai' : $days.' hari' }}</span></label>
                @endforeach
            </div>
            @error('payment_term_days')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="hint-box" style="margin-top:auto">
            <b>Contoh jika barang diterima hari ini ({{ now('Asia/Jakarta')->translatedFormat('j M Y') }})</b>
            <ul style="margin:6px 0 0;padding-left:18px;line-height:1.7">
                @foreach (\App\Models\Outlet::TERMS as $days => $label)
                    <li>{{ $days === 0 ? 'Tunai' : $days.' hari' }}: jatuh tempo {{ now('Asia/Jakarta')->addDays($days)->translatedFormat('j M Y') }}</li>
                @endforeach
            </ul>
        </div>

    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('outlets.index') }}">Batal</a>
        <button type="submit" class="btn primary">Simpan outlet</button>
    </div>
</form>
@endsection
