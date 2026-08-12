<?php
/**
 * Footer widgets — include sebelum </body> di semua halaman publik
 * Otomatis ambil wa_number dari $sp atau $profile (keduanya didukung)
 */
$wa_number = $sp['wa_number'] ?? $profile['wa_number'] ?? '';
$_school   = $sp['school_name'] ?? $profile['school_name'] ?? 'sekolah kami';
$wa_text   = urlencode('Halo, saya ingin bertanya tentang ' . $_school);
?>
<?php if (!empty($wa_number)): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wa_number) ?>?text=<?= $wa_text ?>"
   class="wa-float" target="_blank" rel="noopener" aria-label="Hubungi via WhatsApp">
    <span class="wa-tooltip">Hubungi Kami</span>
    &#128241;
</a>
<?php endif; ?>
