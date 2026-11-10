@props(['class' => 'theme-toggle', 'label' => false])
<button type="button" data-theme-toggle aria-pressed="false" aria-label="Ganti mode terang atau gelap" class="{{ $class }}">
    <span class="i-moon"><x-icon name="moon" :size="20" /></span><span class="i-sun"><x-icon name="sun" :size="20" /></span>
    @if ($label)<span class="t-lbl"><span class="t-to-dark">Gelap</span><span class="t-to-light">Terang</span></span>@endif
</button>
