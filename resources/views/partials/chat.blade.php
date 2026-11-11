@php
    $isCourier = auth()->user()->role === 'kurir';
    $quick = $isCourier
        ? ['Siap, segera ke sana', 'Agak telat, macet', 'Sudah di depan outlet', 'Mohon ditunggu sebentar']
        : ['Tolong lebih cepat ya', 'Tolong diantar duluan ya', 'Kami sudah siap terima', 'Kabari 10 menit sebelum sampai'];
    $active = $note->courier_id && $note->status === 'dikirim';
@endphp
<section class="card chat" id="chat" style="margin-top:12px" data-url="{{ route('delivery.chat', $note) }}" data-token="{{ csrf_token() }}" data-active="{{ $active ? 1 : 0 }}">
    <h3 class="sect">Chat {{ $isCourier ? 'dengan outlet' : 'dengan kurir' }}</h3>
    <div class="chatlog" id="chatlog" aria-live="polite"><p class="chatempty">Belum ada pesan.</p></div>
    @if ($active)
        <div class="quick">@foreach ($quick as $q)<button type="button" data-q="{{ $q }}">{{ $q }}</button>@endforeach</div>
        <form class="chatform" id="chatform" autocomplete="off">
            <input type="text" name="body" maxlength="300" placeholder="Tulis pesan…" aria-label="Pesan">
            <button class="btn primary" type="submit">Kirim</button>
        </form>
    @else
        <small style="color:var(--muted)">Chat hanya aktif selama pengantaran.</small>
    @endif
</section>
<script src="/js/chat.js?v=1" defer></script>
