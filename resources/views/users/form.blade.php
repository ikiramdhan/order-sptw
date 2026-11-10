@extends('layouts.app')
@section('title', $user->exists ? 'Ubah pengguna' : 'Tambah pengguna')
@section('content')
@php $roleNow = old('role', $user->role ?: 'outlet'); @endphp
<header class="page-head">
    <div>
        <h1>{{ $user->exists ? 'Ubah pengguna' : 'Tambah pengguna' }}</h1>
        <p>{{ $user->exists ? $user->email : 'Password sementara dibuat otomatis dan ditampilkan sekali setelah disimpan.' }}</p>
    </div>
    <a class="btn" href="{{ route('users.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>

<form method="POST" class="fbento" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" novalidate>
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="card">
        <h3 class="sect">Akun</h3>

        <label class="field">Nama lengkap
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Email (untuk login)
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </label>

    </div>
    <div class="card">
        <h3 class="sect">Peran dan akses</h3>
        <div class="field">Peran
            <div class="seg" role="radiogroup" aria-label="Peran">
                @foreach ($user->exists ? \App\Models\User::ASSIGNABLE_ROLES : \App\Models\User::CREATABLE_ROLES as $key => $label)
                    <label><input type="radio" name="role" value="{{ $key }}" {{ $roleNow === $key ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
            </div>
            @error('role')<span class="err">{{ $message }}</span>@enderror
        </div>

        <div class="alert info" id="superNote" style="display:none">Super Admin punya akses penuh: semua modul, Pengaturan, dan kelola pengguna. Berikan hanya kepada pemilik atau orang yang sangat dipercaya.</div>
        <label class="field"><span class="lbl">Outlet <span class="hint">(khusus peran Outlet)</span></span>
            <select name="outlet_id">
                <option value="">Pilih outlet</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}" {{ (string) old('outlet_id', $user->outlet_id) === (string) $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                @endforeach
            </select>
            @error('outlet_id')<span class="err">{{ $message }}</span>@enderror
        </label>

    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('users.index') }}">Batal</a>
        <button type="submit" class="btn primary">{{ $user->exists ? 'Simpan perubahan' : 'Simpan pengguna' }}</button>
    </div>
</form>
<script>
(function(){
  var radios=document.querySelectorAll('input[name=role]'), note=document.getElementById('superNote');
  function sync(){var v=document.querySelector('input[name=role]:checked');if(note)note.style.display=v&&v.value==='super_admin'?'block':'none';}
  radios.forEach(function(r){r.addEventListener('change',sync)});sync();
})();
</script>
@endsection
