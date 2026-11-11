@php $o = $outlet; @endphp
<section class="card locbox" style="margin-bottom:12px">
    <h3 class="sect">{{ $o->lat !== null ? 'Titik lokasi outlet' : 'Atur titik lokasi outlet' }}</h3>
    @if ($o->lat !== null)
        <p style="margin:0 0 10px;color:var(--muted)">Tersimpan. Perjalanan kurir ke {{ $o->name }} dihitung dari titik ini. <a class="link" href="https://www.google.com/maps?q={{ $o->lat }},{{ $o->lng }}" target="_blank" rel="noopener">Lihat di Google Maps</a></p>
    @else
        <p style="margin:0 0 10px;color:var(--muted)">Supaya perjalanan kurir sampai ke {{ $o->name }} terlihat di peta lengkap dengan estimasi waktunya, tentukan titik outlet satu kali saja.</p>
    @endif
    <form method="POST" action="{{ route('outlet.location', $o) }}" id="locform">@csrf
        <input type="hidden" name="lat" id="locLat"><input type="hidden" name="lng" id="locLng">
        <button type="button" class="btn {{ $o->lat !== null ? 'outline' : 'primary' }} block" id="locgps">{{ $o->lat !== null ? 'Perbarui dengan lokasi saya sekarang' : 'Pakai lokasi saya sekarang' }}</button>
        <small style="display:block;margin:6px 0 10px;color:var(--muted)">Lakukan saat HP berada di outlet.</small>
        <label class="field">Atau tempel link Google Maps / koordinat
            <input type="text" name="link" placeholder="https://maps.app.goo.gl/… atau -6.2088, 106.8456">
            @error('link')<span class="err">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="btn outline block">Simpan dari link</button>
    </form>
    <script>
    document.getElementById('locgps').addEventListener('click', function () {
      var b = this; b.textContent = 'Mencari lokasi…'; b.disabled = true;
      navigator.geolocation.getCurrentPosition(function (p) {
        document.getElementById('locLat').value = p.coords.latitude.toFixed(7);
        document.getElementById('locLng').value = p.coords.longitude.toFixed(7);
        document.getElementById('locform').submit();
      }, function () { b.disabled = false; b.textContent = 'Gagal. Izinkan lokasi atau tempel link di bawah'; }, { enableHighAccuracy: true, timeout: 20000 });
    });
    </script>
</section>
