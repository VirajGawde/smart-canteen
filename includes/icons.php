<?php
/**
 * icons.php — a tiny, consistent icon set used across the whole app.
 * All paths use stroke="currentColor" so an icon's color is simply
 * whatever CSS `color` is set on its wrapper — no separate light/dark
 * variants to maintain.
 *
 * Usage: <?= icon('home', 'nav-icon') ?>
 */

function icon(string $name, string $class = '', int $size = 20): string
{
    $paths = [
        'home'         => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/>',
        'seat'         => '<path d="M7 4h10v7a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V4Z"/><path d="M7 13v7"/><path d="M17 13v7"/><path d="M5 20h14"/>',
        'bell'         => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6Z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
        'user'         => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.2-4 4-6 7-6s5.8 2 7 6"/>',
        'qr'           => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20v.01"/>',
        'flag'         => '<path d="M6 3v18"/><path d="M6 4h11l-3 4 3 4H6"/>',
        'star'         => '<path d="M12 3.5l2.6 5.4 5.9.7-4.3 4.1 1.1 5.9L12 16.8 6.7 19.6l1.1-5.9-4.3-4.1 5.9-.7Z"/>',
        'logout'       => '<path d="M9 20H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'menu'         => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close'        => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-right'=> '<path d="M9 6l6 6-6 6"/>',
        'check'        => '<path d="M5 13l4 4L19 7"/>',
        'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
        'grid'         => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'chart'        => '<path d="M4 20V10"/><path d="M11 20V4"/><path d="M18 20v-7"/><path d="M3 20h18"/>',
        'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.64 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.64 1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.36 9c.1.36.5 1 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1Z"/>',
        'help'         => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.2a2.5 2.5 0 0 1 4.87.8c0 1.7-2.37 2-2.37 3.5"/><path d="M12 17.2v.1"/>',
        'plus'         => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'trash'        => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
        'image'        => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17.5l5-5 4 4 3-3 4 4"/>',
        'camera'       => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2l1-2h7l1 2h2A1.5 1.5 0 0 1 20 8.5v9A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5Z"/><circle cx="12" cy="13" r="3.3"/>',
        'shield'       => '<path d="M12 3.5 5 6v5.5c0 4.6 3 7.3 7 9 4-1.7 7-4.4 7-9V6Z"/>',
        'arrow-right'  => '<path d="M4 12h16"/><path d="M14 6l6 6-6 6"/>',
        'refresh'      => '<path d="M20 11A8 8 0 1 0 18.5 16"/><path d="M20 5v6h-6"/>',
        'download'     => '<path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/>',
        'search'       => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.5-4.5"/>',
        'building'     => '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M9 16h.01M15 16h.01"/>',
    ];

    $inner = $paths[$name] ?? $paths['help'];
    return '<svg class="icon ' . htmlspecialchars($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
