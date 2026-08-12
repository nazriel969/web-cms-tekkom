<?php
/**
 * Reusable alert component
 * Usage: include dengan $alertType ('success'|'error'|'warning'|'info') dan $alertMsg
 * Contoh:
 *   $alertMsg  = 'Data berhasil disimpan!';
 *   $alertType = 'success';
 *   include __DIR__ . '/../includes/alert.php';
 */
if (!empty($alertMsg)):
    $styles = [
        'success' => 'bg-green-50 border-green-400 text-green-800',
        'error'   => 'bg-red-50 border-red-400 text-red-800',
        'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
        'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
    ];
    $icons = [
        'success' => 'fa-circle-check',
        'error'   => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        'info'    => 'fa-circle-info',
    ];
    $type  = $alertType ?? 'info';
    $style = $styles[$type] ?? $styles['info'];
    $icon  = $icons[$type]  ?? $icons['info'];
?>
<div class="border-l-4 p-4 rounded-r-lg mb-5 flex items-start gap-3 <?= $style ?>" role="alert" data-auto-dismiss>
    <i class="fas <?= $icon ?> mt-0.5 flex-shrink-0"></i>
    <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
</div>
<?php endif; ?>
